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
} else {
    $auth = api_authenticate($conn, $post);
    if ($auth[0] === true) {
        $userId = $auth[1];
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'code' => 4,
        'msg' => 'No operation permission',
        'msgCode' => 2,
        'serviceNowTime' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$amount = isset($post['amount']) ? (float)$post['amount'] : 0.0;
$payId = isset($post['payId']) ? (int)$post['payId'] : 2;

if ($amount <= 0) {
    echo json_encode([
        'code' => 6,
        'msg' => 'Invalid parameter',
        'msgCode' => 6,
        'serviceNowTime' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$sites = 'https://shreewin06.site';
$payUrl = $sites . '/pay/ZyraPay.php?amount=' . urlencode(number_format($amount, 2, '.', '')) . '&uid=' . urlencode($userId) . '&tyid=' . urlencode($payId);

echo json_encode([
    'data' => [
        'submitUrl' => $payUrl,
        'redirectUrl' => $payUrl,
        'scanCodePay' => false,
        'formUrl' => null,
        'formBody' => null,
    ],
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'traceId' => '',
    'serviceNowTime' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
