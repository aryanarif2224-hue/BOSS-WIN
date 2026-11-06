<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, AR-REAL-IP');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
date_default_timezone_set("Asia/Kolkata");

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];

$bearer = explode(" ", isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '');
$author = isset($bearer[1]) ? $bearer[1] : '';
$data_auth = json_decode(is_jwt_valid($author), true);

$userId = null;
if ($data_auth && $data_auth['status'] === 'Success') {
    $userId = (int)$data_auth['payload']['id'];
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['code' => 4, 'msg' => 'No operation permission', 'msgCode' => 2]);
    exit;
}

$amount = isset($post['amount']) ? (float)$post['amount'] : 0.0;
$orderNo = 'ORD_' . time() . rand(1000, 9999);
$now = date('Y-m-d H:i:s');

echo json_encode([
    'data' => [
        'orderNo' => $orderNo,
        'orderNumber' => $orderNo,
        'upiAccount' => 'paytmqr281005050101131008630713@paytm',
        'addTime' => $now,
        'addTime1' => date('Y-m-d H:i:s', time() + 1800),
        'serverTime' => $now,
        'amount' => (string)$amount,
        'payID' => 2,
    ],
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'serviceNowTime' => $now,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
