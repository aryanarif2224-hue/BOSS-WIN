<?php
// GetRechargeRecord — Shreewin-compatible. Real frontend sends a MINIMAL body
// ({language, payId} + auto signature/random/timestamp); the bdg-copied handler
// required endDate/pageNo/pageSize/startDate/state too -> "Param is Invalid".
// Here every field except the standard auth envelope is optional with safe defaults,
// signature is validated exactly like the frontend computes it (expected_signature),
// and the response shape matches the real API: {data:{list,pageNo,totalPage,totalCount}}.

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, AR-REAL-IP');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
function okEnv() { return ['code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'traceId' => '', 'serviceNowTime' => date('Y-m-d H:i:s')]; }
function errRes($code, $msg, $msgCode, $http = 200) { http_response_code($http); echo json_encode(['code' => $code, 'msg' => $msg, 'msgCode' => $msgCode, 'serviceNowTime' => date('Y-m-d H:i:s')]); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') errRes(11, 'Method not allowed', 12);

$shonupost = json_decode(file_get_contents("php://input"), true);
if (!is_array($shonupost)) $shonupost = [];

// Auth: validates {language,random,signature,timestamp} + Bearer JWT, returns userId.
$auth = api_authenticate($conn, $shonupost);
if ($auth[0] !== true) { $e = $auth[1]; errRes($e['code'], $e['msg'], $e['msgCode'], isset($e['http']) ? $e['http'] : 200); }
$uid = (int)$auth[1];

$pageNo   = max(1, (int)($shonupost['pageNo']   ?? 1));
$pageSize = max(1, (int)($shonupost['pageSize'] ?? 10));
$state    = isset($shonupost['state']) ? (int)$shonupost['state'] : -1;
$startDate = isset($shonupost['startDate']) ? $conn->real_escape_string(trim((string)$shonupost['startDate'])) : '';
$endDate   = isset($shonupost['endDate'])   ? $conn->real_escape_string(trim((string)$shonupost['endDate']))   : '';
$offset   = ($pageNo - 1) * $pageSize;

$where = "balakedara = $uid";
if ($state >= 0) $where .= " AND sthiti = $state";
if ($startDate !== '' && $endDate !== '') {
    $where .= " AND date(dinankavannuracisi) >= date('$startDate') AND date(dinankavannuracisi) < date('$endDate')";
}

// Column meaning in thevani: dharavahi=no, dinankavannuracisi=time, madari=type,
// motta=amount, sthiti=state, pavatiaidi=payId, mula=payName.
$rows = [];
$cq = $conn->query("SELECT COUNT(*) AS c FROM thevani WHERE $where");
$total = ($cq && $cq->num_rows) ? (int)$cq->fetch_assoc()['c'] : 0;
$rq = $conn->query("SELECT dharavahi, dinankavannuracisi, madari, motta, sthiti, pavatiaidi, mula
                    FROM thevani WHERE $where ORDER BY shonu DESC LIMIT $pageSize OFFSET $offset");
if ($rq) {
    while ($row = $rq->fetch_assoc()) {
        $rows[] = [
            'rechargeNumber' => $row['dharavahi'],
            'addTime'        => $row['dinankavannuracisi'],
            'type'           => (int)$row['madari'],
            'price'          => $row['motta'],
            'state'          => (int)$row['sthiti'],
            'uRate'          => null,
            'uGold'          => 0,
            'payID'          => (int)$row['pavatiaidi'],
            'payName'        => $row['mula'],
        ];
    }
}

echo json_encode([
    'data' => [
        'list'       => $rows,
        'pageNo'     => $pageNo,
        'totalPage'  => (int)ceil($total / $pageSize),
        'totalCount' => $total,
    ],
] + okEnv(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
