<?php
// RefreshToken — mints a fresh session token (was a debug stub that broke sessions,
// causing wallet to show 0). Validates the incoming token's SIGNATURE (expiry ignored,
// since refresh must work near/after expiry), re-issues token+refreshToken in the exact
// Login.php format, and keeps shonu_subjects.akshinak in sync so GetUserInfo matches.
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

$fail = function ($code, $msg) use ($shnunc) {
    echo json_encode(['code' => $code, 'msg' => $msg, 'msgCode' => $code, 'data' => null, 'serviceNowTime' => $shnunc]);
    exit;
};

// signature-only JWT check (no expiry) — correct for a refresh endpoint
function refresh_jwt_payload($jwt, $secret = 'shreewin_gt_2026_secret') {
    $p = explode('.', $jwt);
    if (count($p) != 3) return null;
    $sig = hash_hmac('SHA256', $p[0] . "." . $p[1], $secret, true);
    $calc = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    if (!hash_equals($calc, $p[2])) return null;
    return json_decode(base64_decode(strtr($p[1], '-_', '+/')), true);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader)) $fail(4, 'No operation permission');
$bearer = explode(" ", $authHeader);
$token = trim(isset($bearer[1]) ? $bearer[1] : $bearer[0]);
if ($token === '') $fail(4, 'No operation permission');

$payload = refresh_jwt_payload($token);
if (!$payload || !isset($payload['id'])) $fail(4, 'Invalid token');
$userId = (int)$payload['id'];

$q = $conn->query("SELECT id, mobile, status, ishonup, codechorkamukala FROM shonu_subjects WHERE id = '$userId' LIMIT 1");
if (!$q || !$q->num_rows) $fail(4, 'User not found');
$u = $q->fetch_assoc();
if ((int)$u['status'] !== 1) $fail(1, 'User suspended');

$expiresIn = time() + 86400;
$head = array('alg' => 'HS256', 'typ' => 'JWT');
$load = array('id' => $u['id'], 'mobile' => $u['mobile'], 'status' => $u['status'], 'expire' => $expiresIn, 'ishonup' => $u['ishonup'], 'codechorkamukala' => $u['codechorkamukala']);
$newToken = generate_jwt($head, $load);
$loadR = array('id' => $u['id'], 'mobile' => $u['mobile'], 'status' => $u['status'], 'expire' => $expiresIn);
$newRefresh = generate_jwt($head, $loadR);

$esc = $conn->real_escape_string($newToken);
$conn->query("UPDATE shonu_subjects SET akshinak='$esc' WHERE id='$userId'");

echo json_encode([
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'data' => [
        'expiresIn'   => $expiresIn,
        'tokenHeader' => 'Bearer ',
        'token'       => $newToken,
        'refreshToken' => $newRefresh,
    ],
    'serviceNowTime' => $shnunc,
]);
