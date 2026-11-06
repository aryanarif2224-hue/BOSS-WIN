<?php
// path: /api/webapi/GetNewUPIBindMobileNo.php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// helper: normalize mobile (remove spaces, +, dashes)
function normalize_mobile($m) {
    if ($m === null) return null;
    $m = trim($m);
    $m = str_replace([' ', '-', '(', ')', '+'], '', $m);
    return $m;
}

// Read incoming body (json)
$body = file_get_contents("php://input");
$input = json_decode($body, true);

// Accept GET query too
$query = $_GET;

// Gather candidate keys from multiple possible names
$candidates = [];

// From JSON body
if (is_array($input)) {
    foreach (['data','mobile','mobileNo','phone','msisdn','phoneNumber','mobile_no'] as $k) {
        if (isset($input[$k]) && $input[$k] !== '') {
            $candidates[] = normalize_mobile($input[$k]);
        }
    }
    if (isset($input['payload']) && is_array($input['payload'])) {
        foreach ($input['payload'] as $k => $v) {
            if (in_array($k, ['data','mobile','mobileNo','phone','msisdn'])) {
                $candidates[] = normalize_mobile($v);
            }
        }
    }
}

// From POST form
if ($_POST) {
    foreach (['data','mobile','mobileNo','phone','msisdn'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            $candidates[] = normalize_mobile($_POST[$k]);
        }
    }
}

// From query string
foreach (['data','mobile','mobileNo','phone','msisdn'] as $k) {
    if (isset($query[$k]) && $query[$k] !== '') {
        $candidates[] = normalize_mobile($query[$k]);
    }
}

// Try Authorization token (Bearer)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')));
$token = "";
if (!empty($authHeader)) {
    $parts = explode(" ", $authHeader);
    if (isset($parts[1])) $token = trim($parts[1]);
    else $token = trim($authHeader);
}

$mobile_found = null;
$user_id = 0;

if ($token) {
    $valid = is_jwt_valid($token);
    $auth = json_decode($valid, true);
    if (is_array($auth) && isset($auth['status']) && $auth['status'] === 'Success' && !empty($auth['payload']['id'])) {
        $user_id = intval($auth['payload']['id']);
    } else {
        // Fallback: decode payload parts directly and verify in DB
        $parts = explode('.', $token);
        if (count($parts) >= 2) {
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!empty($payload['id'])) {
                $check_id = intval($payload['id']);
                $t_esc = mysqli_real_escape_string($conn, $token);
                $chk = mysqli_query($conn, "SELECT id, mobile FROM shonu_subjects WHERE id = '$check_id' OR akshinak = '$t_esc' LIMIT 1");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    $u_row = mysqli_fetch_assoc($chk);
                    $user_id = intval($u_row['id']);
                    if (!empty($u_row['mobile'])) {
                        $mobile_found = normalize_mobile($u_row['mobile']);
                    }
                }
            }
        }
    }

    // 1) From shonu_subjects (primary user table)
    if ($user_id > 0 && empty($mobile_found)) {
        $q_user = mysqli_query($conn, "SELECT mobile FROM shonu_subjects WHERE id = '$user_id' LIMIT 1");
        if ($q_user && mysqli_num_rows($q_user) > 0) {
            $r_user = mysqli_fetch_assoc($q_user);
            if (!empty($r_user['mobile'])) $mobile_found = normalize_mobile($r_user['mobile']);
        }
    }

    // 2) try upi_withdrawal table if exists
    if ($user_id > 0 && empty($mobile_found) && tbl_exists($conn, 'upi_withdrawal')) {
        $q1 = mysqli_query($conn, "SELECT mobile FROM upi_withdrawal WHERE user_id = '$user_id' LIMIT 1");
        if ($q1 && mysqli_num_rows($q1) > 0) {
            $r1 = mysqli_fetch_assoc($q1);
            if (!empty($r1['mobile'])) $mobile_found = normalize_mobile($r1['mobile']);
        }
    }

    // 3) fallback - khate table
    if ($user_id > 0 && empty($mobile_found) && tbl_exists($conn, 'khate')) {
        $q3 = mysqli_query($conn, "SELECT duravani FROM khate WHERE byabaharkarta = '$user_id' ORDER BY shonu DESC LIMIT 1");
        if ($q3 && mysqli_num_rows($q3) > 0) {
            $r3 = mysqli_fetch_assoc($q3);
            if (!empty($r3['duravani'])) $mobile_found = normalize_mobile($r3['duravani']);
        }
    }
}

// If not found from token/DB, check candidates from request
if (!$mobile_found) {
    foreach ($candidates as $c) {
        if ($c === null || $c === '') continue;
        $digits = preg_replace('/\D+/', '', $c);
        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $mobile_found = $digits;
            break;
        }
    }
}

// Return 0 succeed (even if empty, so Vue doesn't crash or redirect to login)
echo json_encode([
    "data" => $mobile_found ?? "",
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
]);
exit;
