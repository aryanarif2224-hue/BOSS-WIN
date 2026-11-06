<?php
// RedeemGiftCode — user redeems an admin-issued gift code; credits the wallet.
// Request: { code, language, random, signature, timestamp } + Bearer token.
// Response envelope code 0 on success.
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
function _ok($msg = 'Succeed', $extra = []) { echo json_encode(array_merge(['code'=>0,'msg'=>$msg,'msgCode'=>0,'serviceNowTime'=>date('Y-m-d H:i:s')], $extra)); exit; }
function _err($msg, $code = 1, $msgCode = 6, $http = 200) { http_response_code($http); echo json_encode(['code'=>$code,'msg'=>$msg,'msgCode'=>$msgCode,'serviceNowTime'=>date('Y-m-d H:i:s')]); exit; }

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e = $auth[1]; _err($e['msg'], $e['code'], $e['msgCode'], isset($e['http']) ? $e['http'] : 200); }
$uid = (int)$auth[1];

$code = isset($post['code']) ? strtoupper(trim((string)$post['code'])) : '';
if ($code === '') _err('Code is required', 7, 6);

$st = $conn->prepare("SELECT id, code, amount, max_uses, used, active, expires_at FROM gift_codes WHERE code = ? LIMIT 1");
$st->bind_param('s', $code); $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
if (!$row) _err('Invalid gift code', 1, 7001);
if ((int)$row['active'] !== 1) _err('Gift code inactive', 1, 7002);
if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) _err('Gift code expired', 1, 7003);
if ((int)$row['used'] >= (int)$row['max_uses']) _err('Gift code already fully used', 1, 7004);

// one redemption per user per code
$chk = $conn->prepare("SELECT id FROM gift_redemptions WHERE user_id = ? AND code = ?");
$chk->bind_param('is', $uid, $code); $chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) { $chk->close(); _err('You already used this gift code', 1, 7005); }
$chk->close();

// credit wallet (motta is varchar)
$amount = (float)$row['amount'];
$bal = 0.0;
$bq = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = $uid");
if ($bq && $bq->num_rows) { $bal = (float)$bq->fetch_assoc()['motta']; }
$newbal = $bal + $amount;

$conn->begin_transaction();
try {
    $up = $conn->query("UPDATE shonu_kaichila SET motta = '" . $newbal . "' WHERE balakedara = $uid");
    if (!$up) throw new Exception('wallet update failed');
    $ins = $conn->prepare("INSERT INTO gift_redemptions (code, user_id, amount) VALUES (?,?,?)");
    $ins->bind_param('sid', $code, $uid, $amount); $ins->execute(); $ins->close();
    $conn->query("UPDATE gift_codes SET used = used + 1 WHERE id = " . (int)$row['id']);
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    _err('Redeem failed', 2, 2);
}
_ok('Succeed', ['data' => ['amount' => $amount, 'balance' => round($newbal, 4)]]);
