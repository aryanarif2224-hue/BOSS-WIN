<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$res = [
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'serviceNowTime' => $now,
];

$body = file_get_contents("php://input");
$post = json_decode($body, true) ?: [];

list($auth_ok, $auth_res) = api_authenticate($conn, $post);
$userId = $auth_ok ? (int)$auth_res : 0;

$bal = 0.0;
if ($userId > 0) {
    $balq = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = $userId ORDER BY kramasankhye DESC LIMIT 1");
    if ($balq && $row = $balq->fetch_assoc()) {
        $bal = floatval($row['motta']);
    }
}

$res['data'] = [
    'balance' => $bal,
    'gameBalance' => $bal,
    'mainMoney' => $bal,
    'amount' => $bal
];

echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
