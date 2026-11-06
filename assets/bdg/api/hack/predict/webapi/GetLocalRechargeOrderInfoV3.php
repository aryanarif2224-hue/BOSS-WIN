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

echo json_encode([
    'data' => null,
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'traceId' => '',
    'serviceNowTime' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
