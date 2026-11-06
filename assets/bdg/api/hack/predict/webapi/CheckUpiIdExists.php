<?php
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

$body = file_get_contents("php://input");
$input = json_decode($body, true);

$accountNo = trim($input['accountNo'] ?? $_REQUEST['accountNo'] ?? '');

$token = "";
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $parts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
    if (isset($parts[1])) $token = $parts[1];
}

$current_user_id = 0;
if ($token) {
    $valid = is_jwt_valid($token);
    $auth = json_decode($valid, true);
    if (is_array($auth) && isset($auth['status']) && $auth['status'] === 'Success') {
        $current_user_id = intval($auth['payload']['id']);
    }
}

$exists = false;
if (!empty($accountNo)) {
    $clean_acc = mysqli_real_escape_string($conn, $accountNo);
    // Check upi_withdrawal table
    $query = "SELECT user_id FROM upi_withdrawal WHERE upi_id = '$clean_acc'";
    if ($current_user_id > 0) {
        $query .= " AND user_id != '$current_user_id'";
    }
    $query .= " LIMIT 1";
    
    $res = mysqli_query($conn, $query);
    if ($res && mysqli_num_rows($res) > 0) {
        $exists = true;
    }
}

echo json_encode([
    "data" => $exists,
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
]);
exit;
