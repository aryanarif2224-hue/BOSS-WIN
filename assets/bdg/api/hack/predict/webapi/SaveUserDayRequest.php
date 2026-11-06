<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

$res = [
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'data' => null,
    'serviceNowTime' => $shnunc,
];

http_response_code(200);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
