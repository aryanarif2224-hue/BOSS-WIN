<?php
header('Content-Type: application/json; charset=utf-8');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
date_default_timezone_set("Asia/Kolkata");
http_response_code(200);
echo json_encode(['code'=>0,'msg'=>'Succeed','msgCode'=>0,'data'=>[],'serviceNowTime'=>date("Y-m-d H:i:s")]);
