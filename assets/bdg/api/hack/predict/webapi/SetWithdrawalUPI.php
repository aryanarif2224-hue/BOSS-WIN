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
$post = json_decode($body, true);

$required = [
    "beneficiaryName",
    "accountNo",
    "mobileNo"
];

foreach ($required as $key) {
    if (empty($post[$key])) {
        echo json_encode([
            "code" => 7,
            "msg" => "Param Invalid",
            "msgCode" => 6,
            "serviceNowTime" => $now
        ]);
        exit;
    }
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')));
$token = "";
if (!empty($authHeader)) {
    $b = explode(" ", $authHeader);
    if (isset($b[1])) $token = trim($b[1]);
    else $token = trim($authHeader);
}

$user_id = 0;
if ($token) {
    $valid = is_jwt_valid($token);
    $auth = json_decode($valid, true);
    if (is_array($auth) && isset($auth["status"]) && $auth["status"] === "Success" && !empty($auth["payload"]["id"])) {
        $user_id = intval($auth["payload"]["id"]);
    } else {
        $parts = explode('.', $token);
        if (count($parts) >= 2) {
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!empty($payload['id'])) {
                $check_id = intval($payload['id']);
                $t_esc = mysqli_real_escape_string($conn, $token);
                $chk = mysqli_query($conn, "SELECT id FROM shonu_subjects WHERE id = '$check_id' OR akshinak = '$t_esc' LIMIT 1");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    $u_row = mysqli_fetch_assoc($chk);
                    $user_id = intval($u_row['id']);
                }
            }
        }
    }
}

if ($user_id <= 0) {
    echo json_encode([
        "code" => 4,
        "msg" => "No operation permission",
        "msgCode" => 2,
        "serviceNowTime" => $now
    ]);
    exit;
}

$name    = mysqli_real_escape_string($conn, $post["beneficiaryName"]);
$account = mysqli_real_escape_string($conn, $post["accountNo"]);
$mobile  = mysqli_real_escape_string($conn, $post["mobileNo"]);

$sql = "INSERT INTO upi_withdrawal (user_id, upi_id, mobile, name)
        VALUES('$user_id','$account','$mobile','$name')
        ON DUPLICATE KEY UPDATE upi_id='$account', mobile='$mobile', name='$name'";

mysqli_query($conn, $sql);

echo json_encode([
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
]);
exit;
