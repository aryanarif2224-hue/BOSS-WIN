<?php
// draw.shreewin06.site router — serves real SaaS lottery results with manual override & auto protection.

include "/raxi/bdg/conn.php";
include "/raxi/bdg/functions2.php";
include "/raxi/bdg/saas_lib.php";
require_once __DIR__ . '/auto_protect.php';

header('Content-Type: application/json; charset=utf-8');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Headers: *');
header('Vary: Origin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if (!preg_match('#^([A-Za-z0-9]+)/([A-Za-z0-9_]+?)(?:\.json)?(?:/(GetHistoryIssuePage)(?:\.json)?)?$#', $uri, $m)) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => 'Not found']);
    exit();
}

$lottery  = $m[1];
$gameCode = $m[2];
$isHistory = isset($m[3]) && $m[3] === 'GetHistoryIssuePage';

$fam = saas_lottery_of($gameCode);
if ($fam === null || $fam !== $lottery) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => 'Unknown game']);
    exit();
}

if ($isHistory) {
    $body = saas_fetch_draw("$lottery/$gameCode/GetHistoryIssuePage.json", 3);
    if ($body === null) { http_response_code(502); echo json_encode(['code'=>502,'msg'=>'upstream']); exit(); }

    $j = json_decode($body, true);
    if (isset($j['data']['list']) && is_array($j['data']['list'])) {

        foreach ($j['data']['list'] as $row) {
            $issue = $conn->real_escape_string($row['issueNumber'] ?? '');
            if ($issue === '') continue;
            $num   = $conn->real_escape_string($row['number']  ?? '');
            $color = $conn->real_escape_string($row['color']   ?? '');
            $prem  = $conn->real_escape_string($row['premium'] ?? '');
            $sum   = (int)($row['sum'] ?? 0);
            $gc    = $conn->real_escape_string($gameCode);
            $conn->query("INSERT INTO saas_results (game_code, issue_number, number, color, premium, sum_val, is_protected)
                          VALUES ('$gc','$issue','$num','$color','$prem',$sum, 0)
                          ON DUPLICATE KEY UPDATE 
                            number=IF(is_protected=1, number, VALUES(number)),
                            color=IF(is_protected=1, color, VALUES(color)),
                            premium=IF(is_protected=1, premium, VALUES(premium)),
                            sum_val=IF(is_protected=1, sum_val, VALUES(sum_val))");
        }
        saas_apply_manual($conn, $gameCode);

        // Override history output with auto-protected and manual results
        $gc = $conn->real_escape_string($gameCode);
        $issList = [];
        foreach ($j['data']['list'] as $row) {
            if (!empty($row['issueNumber'])) {
                $issList[] = "'" . $conn->real_escape_string($row['issueNumber']) . "'";
            }
        }

        if (!empty($issList)) {
            $inClause = implode(',', $issList);
            $ovMap = [];

            // 1. Protected results from saas_results
            $rq = $conn->query("SELECT issue_number, number, color, premium, sum_val FROM saas_results WHERE game_code='$gc' AND is_protected=1 AND issue_number IN ($inClause)");
            if ($rq && $rq->num_rows > 0) {
                while ($r = $rq->fetch_assoc()) {
                    $ovMap[$r['issue_number']] = $r;
                }
            }

            // 2. Admin manual results take top priority
            $mq = $conn->query("SELECT issue_number, number, color, premium, sum_val FROM saas_manual_results WHERE game_code='$gc' AND state=1 AND issue_number IN ($inClause)");
            if ($mq && $mq->num_rows > 0) {
                while ($mr = $mq->fetch_assoc()) {
                    $ovMap[$mr['issue_number']] = $mr;
                }
            }

            if (!empty($ovMap)) {
                foreach ($j['data']['list'] as &$row) {
                    $iss = $row['issueNumber'] ?? '';
                    if (isset($ovMap[$iss])) {
                        $row['number']  = (string)$ovMap[$iss]['number'];
                        $row['color']   = (string)$ovMap[$iss]['color'];
                        $row['premium'] = (string)$ovMap[$iss]['premium'];
                        $row['sum']     = (int)$ovMap[$iss]['sum_val'];
                    }
                }
                unset($row);
            }
        }
    }
    $j['result'] = true;
    echo json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// Current window file
$body = saas_fetch_draw("$lottery/$gameCode.json", 2);
if ($body === null) { http_response_code(502); echo json_encode(['result'=>false,'code'=>502,'msg'=>'upstream']); exit(); }

$j = json_decode($body, true);

// In the final seconds (or when timer closes), protect the current issue if bets exist!
if (isset($j['current']['issueNumber']) && isset($j['current']['endTime'])) {
    $now_ms = time() * 1000;
    $time_left_sec = max(0, intval(($j['current']['endTime'] - $now_ms) / 1000));
    // When 5 seconds or less remain, calculate optimal outcome to protect house profit!
    if ($time_left_sec <= 5) {
        saas_protect_and_optimize_draw($conn, $gameCode, $j['current']['issueNumber']);
    }
}

if (isset($j['previous']['issueNumber'])) {
    $piss = $conn->real_escape_string($j['previous']['issueNumber']);
    $gc   = $conn->real_escape_string($gameCode);

    // Ensure previous issue is resolved with protection
    saas_protect_and_optimize_draw($conn, $gameCode, $j['previous']['issueNumber']);

    // Check 1: Admin manual result
    $mq   = $conn->query("SELECT number, color, premium, sum_val FROM saas_manual_results WHERE game_code='$gc' AND issue_number='$piss' AND state=1 LIMIT 1");
    if ($mq && $mq->num_rows > 0) {
        $mr = $mq->fetch_assoc();
        $j['previous']['number']  = (string)$mr['number'];
        $j['previous']['color']   = (string)$mr['color'];
        $j['previous']['premium'] = (string)$mr['premium'];
        $j['previous']['sum']     = (int)$mr['sum_val'];
    } else {
        // Check 2: Auto-protected result in saas_results
        $rq = $conn->query("SELECT number, color, premium, sum_val FROM saas_results WHERE game_code='$gc' AND issue_number='$piss' LIMIT 1");
        if ($rq && $rq->num_rows > 0) {
            $rr = $rq->fetch_assoc();
            if ($rr['number'] !== null && $rr['number'] !== '') {
                $j['previous']['number']  = (string)$rr['number'];
                $j['previous']['color']   = (string)$rr['color'];
                $j['previous']['premium'] = (string)$rr['premium'];
                $j['previous']['sum']     = (int)$rr['sum_val'];
            }
        }
    }
}
$j['result'] = true;
echo json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit();
?>
