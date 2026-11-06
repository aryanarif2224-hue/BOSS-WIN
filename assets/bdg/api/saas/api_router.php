<?php
// api.shreewin06.site router — self-hosted SaaS lottery API (ARLottery clone).
// Handles /api/Lottery/<Action>. Reuses the site's JWT for auth, real wallet
// (shonu_kaichila.motta), real issue numbers (from the draw server), and real
// results for settlement.

include "/raxi/bdg/conn.php";
include "/raxi/bdg/functions2.php";
include "/raxi/bdg/saas_lib.php";
include "/raxi/bdg/saas/game_info.php";
include "/raxi/bdg/saas/settle.php";

saas_headers();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!preg_match('#/Lottery/([A-Za-z0-9]+)$#', $uri, $m)) {
    saas_err(404, 'Not found', 404, 404);
}
$action = $m[1];

// GET params + POST body merged.
$body = file_get_contents('php://input');
$post = json_decode($body, true);
if (!is_array($post)) $post = [];
$req = array_merge($_GET, $post);

// ---- helpers to read the current issue window from the draw server ----------
function saas_current_window($lottery, $gameCode) {
    $body = saas_fetch_draw("$lottery/$gameCode.json", 2);
    if ($body === null) return null;
    return json_decode($body, true);
}

// =============================================================================
switch ($action) {

case 'GetUserInfo': {
    list($ok, $uid) = saas_authenticate($conn);
    if (!$ok) saas_err(401, 'No operation permission', 2, 401);
    saas_ok([
        'userId'        => (int)('1015' . str_pad((string)$uid, 8, '0', STR_PAD_LEFT)),
        'tenantId'      => 1015,
        'agentCode'     => '101501',
        'sysCurrency'   => 'INR',
        'state'         => 1,
        'tenantAccount' => (string)$uid,
        'isOpenFollow'  => false,
    ]);
    break;
}

case 'GetBalance': {
    list($ok, $uid) = saas_authenticate($conn);
    if (!$ok) saas_err(401, 'No operation permission', 2, 401);
    saas_settle_user($conn, $uid);   // settle matured bets before showing balance
    saas_ok(['balance' => round(saas_get_balance($conn, $uid), 4)]);
    break;
}

case 'GetGameInfo': {
    $gameCode = isset($req['gameCode']) ? preg_replace('/[^A-Za-z0-9_]/', '', $req['gameCode']) : '';
    $lottery  = saas_lottery_of($gameCode);
    if ($lottery === null) saas_err(7, 'Param is Invalid', 6);
    $info = saas_game_info($lottery);
    if (!$info) saas_err(7, 'Param is Invalid', 6);
    saas_ok($info);
    break;
}

case 'GetBetLimit': {
    saas_ok(['minAmount' => 1, 'maxAmount' => 1000000, 'limitGroupNum' => 0]);
    break;
}

case 'GetGameList': {
    // Grouped by lottery family — the frontend does data.filter(E=>E.gameList?.length>0)
    // and renders one time-tab per entry in each family's gameList. Format matches the
    // real SaaS API (captured HAR): data is an ARRAY of {gameType,gameTypeName,sort,gameList[]}.
    // gameList items need gameCode, gameName, sort, state, intervalMinute.
    $meta = [
        // family => [gameType, gameTypeName, familySort, [ [code, name, sort], ... ] ]
        'WinGo' => [100, 'WinGo', 1, [
            ['WinGo_30S', 'WinGo 30sec', 44],
            ['WinGo_1M',  'WinGo 1 Min', 43],
            ['WinGo_3M',  'WinGo 3 Min', 42],
            ['WinGo_5M',  'WinGo 5 Min', 41],
        ]],
        'K3' => [101, 'K3', 5, [
            ['K3_1M',  'K3 1 Min',  34],
            ['K3_3M',  'K3 3 Min',  33],
            ['K3_5M',  'K3 5 Min',  32],
            ['K3_10M', 'K3 10 Min', 31],
        ]],
        'D5' => [102, '5D', 4, [
            ['D5_1M',  '5D 1 Min',  24],
            ['D5_3M',  '5D 3 Min',  23],
            ['D5_5M',  '5D 5 Min',  22],
            ['D5_10M', '5D 10 Min', 21],
        ]],
        'MotoRace' => [105, 'MotoRace', 2, [
            ['MotoRace_1M', 'Moto Racing', 0],
        ]],
    ];

    $data = [];
    foreach ($meta as $fam => $info) {
        list($gameType, $gameTypeName, $famSort, $games) = $info;
        $gameList = [];
        foreach ($games as $g) {
            $gameList[] = [
                'gameCode'       => $g[0],
                'gameName'       => $g[1],
                'sort'           => $g[2],
                'state'          => 1,
                'intervalMinute' => saas_interval_minutes($g[0]),
            ];
        }
        $data[] = [
            'gameType'     => $gameType,
            'gameTypeName' => $gameTypeName,
            'sort'         => $famSort,
            'gameList'     => $gameList,
        ];
    }
    saas_ok($data);
    break;
}

case 'GetRecordPage': {
    list($ok, $uid) = saas_authenticate($conn);
    if (!$ok) saas_err(401, 'No operation permission', 2, 401);
    saas_settle_user($conn, $uid);

    $gameCode = isset($req['gameCode']) ? preg_replace('/[^A-Za-z0-9_]/', '', $req['gameCode']) : '';
    $pageNo   = isset($req['pageNo'])   ? max(1, (int)$req['pageNo'])   : 1;
    $pageSize = isset($req['pageSize']) ? max(1, (int)$req['pageSize']) : 10;
    $offset   = ($pageNo - 1) * $pageSize;
    $gcEsc    = $conn->real_escape_string($gameCode);

    $cq = $conn->query("SELECT COUNT(*) c FROM saas_bets WHERE user_id=$uid AND game_code='$gcEsc'");
    $total = $cq ? (int)$cq->fetch_assoc()['c'] : 0;

    $rq = $conn->query("SELECT * FROM saas_bets WHERE user_id=$uid AND game_code='$gcEsc'
                        ORDER BY id DESC LIMIT $pageSize OFFSET $offset");
    $list = [];
    if ($rq) while ($b = $rq->fetch_assoc()) {
        $list[] = [
            'issueNumber'   => $b['issue_number'],
            'playType'      => $b['play_type'],
            'orderNo'       => $b['order_no'],
            'amount'        => (float)$b['amount'],
            'betMultiple'   => (int)$b['bet_multiple'],
            'betContent'    => $b['bet_content'],
            'number'        => $b['result_number'],
            'color'         => '',
            'premium'       => $b['result_premium'],
            'realAmount'    => (float)$b['real_amount'],
            'fee'           => (float)$b['fee'],
            'state'         => (int)$b['settled'] === 0 ? 2 : ((int)$b['state']),  // 2 = pending
            'winAmount'     => (float)$b['win_amount'] > 0 ? (float)$b['win_amount'] : ((int)$b['state'] === 1 ? round((float)$b['win_lose_amount'] + (float)$b['amount'], 4) : 0.0),
            'winLoseAmount' => (float)$b['win_lose_amount'],
            'betTime'       => (int)$b['bet_time'],
        ];
    }
    saas_ok([
        'list'       => $list,
        'pageNo'     => $pageNo,
        'totalPage'  => (int)ceil($total / $pageSize),
        'totalCount' => $total,
    ]);
    break;
}

case 'GetWinLossResult': {
    list($ok, $uid) = saas_authenticate($conn);
    if (!$ok) saas_err(401, 'No operation permission', 2, 401);
    saas_settle_user($conn, $uid);

    $issue = isset($req['issueNumber']) ? $conn->real_escape_string($req['issueNumber']) : '';

    // If the bet on this issue is still pending, retry settle once after 400ms
    $chk_open = $conn->query("SELECT id FROM saas_bets WHERE user_id=$uid AND issue_number='$issue' AND settled=0 LIMIT 1");
    if ($chk_open && $chk_open->num_rows > 0) {
        usleep(400000);
        saas_settle_user($conn, $uid);
    }

    $rq = $conn->query("SELECT state, amount, real_amount, win_amount, win_lose_amount, settled FROM saas_bets
                        WHERE user_id=$uid AND issue_number='$issue' ORDER BY id DESC");
    $status = false; $winAmount = 0.0;
    if ($rq) while ($b = $rq->fetch_assoc()) {
        if ((int)$b['settled'] === 1 && (int)$b['state'] === 1) {
            $status = true;
            $gross = (float)$b['win_amount'];
            if ($gross <= 0) {
                $gross = (float)$b['win_lose_amount'] + (float)$b['amount'];
            }
            $winAmount += $gross; // Total winning payout: bet + profit!
        }
    }
    saas_ok(['status' => $status, 'winAmount' => round(max(0, $winAmount), 4)]);
    break;
}

case 'GetHistoryIssuePage': {
    // Proxy the draw server's history (and it persists results as a side effect).
    $gameCode = isset($req['gameCode']) ? preg_replace('/[^A-Za-z0-9_]/', '', $req['gameCode']) : '';
    $lottery  = saas_lottery_of($gameCode);
    if ($lottery === null) saas_err(7, 'Param is Invalid', 6);
    $drawBody = saas_fetch_draw("$lottery/$gameCode/GetHistoryIssuePage.json", 3);
    if ($drawBody === null) saas_err(502, 'upstream', 1, 502);
    // persist and apply manual overrides
    $j = json_decode($drawBody, true);
    if (isset($j['data']['list'])) {

        foreach ($j['data']['list'] as $row) {
            $iss = $conn->real_escape_string($row['issueNumber'] ?? '');
            if ($iss === '') continue;
            $num = $conn->real_escape_string($row['number'] ?? '');
            $col = $conn->real_escape_string($row['color'] ?? '');
            $pre = $conn->real_escape_string($row['premium'] ?? '');
            $sm  = (int)($row['sum'] ?? 0);
            $gcEsc = $conn->real_escape_string($gameCode);
            $conn->query("INSERT INTO saas_results (game_code,issue_number,number,color,premium,sum_val,is_protected) VALUES ('$gcEsc','$iss','$num','$col','$pre',$sm, 0) ON DUPLICATE KEY UPDATE number=IF(is_protected=1, number, VALUES(number)),color=IF(is_protected=1, color, VALUES(color)),premium=IF(is_protected=1, premium, VALUES(premium)),sum_val=IF(is_protected=1, sum_val, VALUES(sum_val))");
        }
        saas_apply_manual($conn, $gameCode);
        $gcEsc = $conn->real_escape_string($gameCode);

        $issList = [];
        foreach ($j['data']['list'] as $row) {
            if (!empty($row['issueNumber'])) $issList[] = "'" . $conn->real_escape_string($row['issueNumber']) . "'";
        }
        $mmap = [];
        if (!empty($issList)) {
            $inClause = implode(',', $issList);
            $rq = $conn->query("SELECT issue_number, number, color, premium, sum_val FROM saas_results WHERE game_code='$gcEsc' AND is_protected=1 AND issue_number IN ($inClause)");
            if ($rq && $rq->num_rows > 0) {
                while ($r = $rq->fetch_assoc()) { $mmap[$r['issue_number']] = $r; }
            }
            $mq = $conn->query("SELECT issue_number, number, color, premium, sum_val FROM saas_manual_results WHERE game_code='$gcEsc' AND state=1 AND issue_number IN ($inClause)");
            if ($mq && $mq->num_rows > 0) {
                while ($mr = $mq->fetch_assoc()) { $mmap[$mr['issue_number']] = $mr; }
            }
        }

        if (!empty($mmap)) {
            foreach ($j['data']['list'] as &$row) {
                $iss = $row['issueNumber'] ?? '';
                if (isset($mmap[$iss])) {
                    $row['number']  = (string)$mmap[$iss]['number'];
                    $row['color']   = (string)$mmap[$iss]['color'];
                    $row['premium'] = (string)$mmap[$iss]['premium'];
                    $row['sum']     = (int)$mmap[$iss]['sum_val'];
                }
            }
            unset($row);
        }
    }
    $j['result'] = true;
    echo json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit();
    break;
}

case 'GetTrendStatistics':
case 'GetDragonList': {
    saas_ok(['list' => []]);
    break;
}

case 'GetGameIntroduce': {
    saas_ok(['content' => '']);
    break;
}

// ---- Betting endpoints ------------------------------------------------------
case 'WinGoBet':
case 'K3Bet':
case 'D5Bet':
case 'MotoRaceBet':
case 'TrxWinGoBet':
case 'VideoWinGoBet': {
    include "/raxi/bdg/saas/bet.php";
    saas_place_bet($conn, $action, $req);
    break;
}

default:
    saas_err(404, 'Unknown action: ' . $action, 404, 404);
}
?>
