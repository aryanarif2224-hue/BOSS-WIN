<?php
// ConversionRedpage — Redeem a gift code. Contract matches the SPA:
//   POST { giftCode, language, random, signature, timestamp } + Bearer token
// Success => code 0 (frontend shows a success dialog). Failure => code 1 with a
// msgCode the frontend turns into a message. Codes are admin-issued (gift_codes),
// redemptions are tracked in gift_redemptions and credit shonu_kaichila.motta.
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

function rr_out($code, $msg, $msgCode, $data = null) {
    echo json_encode(['data' => $data, 'code' => $code, 'msg' => $msg, 'msgCode' => $msgCode, 'serviceNowTime' => date('Y-m-d H:i:s')]);
    exit;
}

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e = $auth[1]; http_response_code(isset($e['http']) ? $e['http'] : 200); rr_out($e['code'], $e['msg'], $e['msgCode']); }
$uid = (int)$auth[1];

$code = isset($post['giftCode']) ? strtoupper(trim((string)$post['giftCode'])) : '';
if ($code === '') rr_out(7, 'Code is required', 6);

$st = $conn->prepare("SELECT id, code, amount, max_uses, used, active, expires_at FROM gift_codes WHERE code = ? LIMIT 1");
$st->bind_param('s', $code); $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
if (!$row) rr_out(1, 'Redemption code error', 230);
if ((int)$row['active'] !== 1) rr_out(1, 'Code inactive', 230);
if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) rr_out(1, 'Code expired', 230);
if ((int)$row['used'] >= (int)$row['max_uses']) rr_out(1, 'Code already fully used', 230);

$dupe = $conn->prepare("SELECT id FROM gift_redemptions WHERE user_id = ? AND code = ?");
$dupe->bind_param('is', $uid, $code); $dupe->execute(); $dupe->store_result();
if ($dupe->num_rows > 0) { $dupe->close(); rr_out(1, 'Already redeemed', 230); }
$dupe->close();

$amount = (float)$row['amount'];
$bal = 0.0;
$bq = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = $uid");
if ($bq && $bq->num_rows) $bal = (float)$bq->fetch_assoc()['motta'];

$conn->begin_transaction();
try {
    $conn->query("UPDATE shonu_kaichila SET motta = '" . ($bal + $amount) . "' WHERE balakedara = $uid");
    $ins = $conn->prepare("INSERT INTO gift_redemptions (code, user_id, amount) VALUES (?,?,?)");
    $ins->bind_param('sid', $code, $uid, $amount); $ins->execute(); $ins->close();
    $conn->query("UPDATE gift_codes SET used = used + 1 WHERE id = " . (int)$row['id']);
    $conn->commit();
} catch (Exception $e) { $conn->rollback(); rr_out(2, 'Redeem failed', 2); }

rr_out(0, 'Succeed', 0);
