<?php
/**
 * ARBWalletEnterNet.php — shreewin06.site -> arbpay AR Wallet SSO bridge.
 */
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

define('ARBPAY_ENTER_URL', 'https://arbpay.nextapi.site/api/merchant/enter');
define('ARBPAY_API_KEY',  'ARB_95B97D46ABC99B021F88CF51CBD38276');
define('ARBPAY_RETURN',   'https://shreewin06.site/#/main');

function o6($code,$msg,$data=null){ echo json_encode(['code'=>$code,'msg'=>$msg,'data'=>$data,'msgCode'=>$code,'serviceNowTime'=>date("Y-m-d H:i:s")],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e=$auth[1]; http_response_code($e['http']??200); o6($e['code'],$e['msg']); }
$userId = $auth[1];

$row=null; $q=$conn->query("SELECT id,mobile,codechorkamukala AS nickname,abidpic AS avatar FROM shonu_subjects WHERE id='$userId' LIMIT 1");
if ($q && $q->num_rows) $row=$q->fetch_assoc();
if (!$row) o6(404,'User not found');

$payload=json_encode(['userId'=>(string)$row['id'],'phone'=>(string)$row['mobile'],'nickName'=>(string)($row['nickname']??''),'avatar'=>(string)($row['avatar']??'10'),'returnUrl'=>ARBPAY_RETURN]);
$ch=curl_init(ARBPAY_ENTER_URL);
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-API-KEY: '.ARBPAY_API_KEY],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
$resp=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
if ($resp===false) o6(500,'arbpay unreachable: '.$err);
$j=json_decode($resp,true);
if (!is_array($j) || ($j['code']??1)!=0) o6(500,'arbpay error: '.($j['msg']??substr($resp,0,120)));

o6(0,'Succeed',[
  'walletAccessUrl'=>$j['data']['walletAccessUrl'],
  'token'=>$j['data']['token']??'',
  'tokenHeader'=>$j['data']['tokenHeader']??'Bearer ',
  'refreshToken'=>$j['data']['refreshToken']??'',
  'expire'=>$j['data']['expire']??86400,
]);
