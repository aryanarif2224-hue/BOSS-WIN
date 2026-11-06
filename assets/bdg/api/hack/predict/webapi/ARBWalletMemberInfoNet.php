<?php
// ARBWalletMemberInfoNet — AR wallet status for a shreewin06 member.
// Report the wallet as ACTIVE with the ARB balance so the button opens it directly.
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

define('ARBPAY_MEMBERINFO_URL', 'https://arbpay.nextapi.site/api/merchant/memberInfo');
define('ARBPAY_API_KEY', 'ARB_95B97D46ABC99B021F88CF51CBD38276');

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e = $auth[1]; http_response_code(isset($e['http']) ? $e['http'] : 200); echo json_encode(['code'=>$e['code'],'msg'=>$e['msg'],'msgCode'=>$e['msgCode'],'serviceNowTime'=>date('Y-m-d H:i:s')]); exit; }
$userId = $auth[1];

$bal = 0;
$ch = curl_init(ARBPAY_MEMBERINFO_URL);
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['userId'=>(string)$userId]),
  CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-API-KEY: '.ARBPAY_API_KEY],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12]);
$r=curl_exec($ch); curl_close($ch);
$j=json_decode($r,true);
if (is_array($j) && ($j['code']??1)==0) $bal = $j['data']['arbBalance'] ?? 0;

echo json_encode([
    'data'           => ['walletActivationStatus'=>1,'isActive'=>1,'activated'=>1,'arActive'=>1,'isActivated'=>1,'state'=>1,'arbBalance'=>$bal,'balance'=>$bal,'currency'=>'ARB','memberNo'=>null],
    'code'           => 0,
    'msg'            => 'Succeed',
    'msgCode'        => 0,
    'traceId'        => '',
    'serviceNowTime' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
