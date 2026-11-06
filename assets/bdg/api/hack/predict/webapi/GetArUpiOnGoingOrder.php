<?php
// GetArUpiOnGoingOrder — Shreewin-compatible envelope.
// Real backend returns code 0 with data:null when there is no pending UPI order
// for this user. The bdg copy of this file was empty (0 bytes) which made the
// frontend's Recharge page fail to parse a response. Keep auth so it is not open.

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
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e = $auth[1]; http_response_code(isset($e['http']) ? $e['http'] : 200); echo json_encode(['code'=>$e['code'],'msg'=>$e['msg'],'msgCode'=>$e['msgCode'],'serviceNowTime'=>date('Y-m-d H:i:s')]); exit; }

echo json_encode([
    'data'            => null,
    'code'            => 0,
    'msg'             => 'Succeed',
    'msgCode'         => 0,
    'traceId'         => '',
    'serviceNowTime'  => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
