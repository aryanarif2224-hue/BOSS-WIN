<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$response = [
    "code" => 11,
    "msg" => "Method not allowed",
    "msgCode" => 12,
    "serviceNowTime" => $now
];

$body = file_get_contents("php://input");
$post = json_decode($body, true);

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

// Required params
if (!isset($post['language'], $post['random'], $post['signature'], $post['timestamp'])) {
    $response['code'] = 7;
    $response['msg'] = "Param is Invalid";
    $response['msgCode'] = 6;
    echo json_encode($response);
    exit();
}

$language  = $post['language'];
$random    = $post['random'];
$signature = strtoupper(trim($post['signature']));

$signStr   = '{"language":'.$language.',"random":"'.$random.'"}';
$signCheck = strtoupper(md5($signStr));

if ($signCheck !== $signature && !verify_signature($post, $signature)) {
    $response['code'] = 5;
    $response['msg']  = "Wrong signature";
    $response['msgCode'] = 3;
    echo json_encode($response);
    exit();
}

// JWT token
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$auth = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
$token = $auth[1];

$jwt = json_decode(is_jwt_valid($token), true);

if (!$jwt || $jwt['status'] !== "Success") {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userId = intval($jwt['payload']['id']);

// Check user exists
$q = $conn->query("SELECT id, codechorkamukala FROM shonu_subjects WHERE akshinak='$token'");
if (!$q || mysqli_num_rows($q) !== 1) {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userName = mysqli_fetch_assoc($q)['codechorkamukala'];

// CHECK TURN TABLE RECORD
$spinQ = $conn->query("SELECT total_spins, invited_wheel_amount FROM shonu_turntable WHERE user_id=$userId");

if (mysqli_num_rows($spinQ) == 0) {
    // FIRST TIME → 1 spin only (as per your sample)
    $conn->query("INSERT INTO shonu_turntable (user_id,total_spins,invited_wheel_amount,created_at,updated_at) VALUES ($userId,1,0,NOW(),NOW())");
    $spinCount  = 1;
    $spinAmount = 0.0;
    $isFirst    = true;
} else {
    $row = mysqli_fetch_assoc($spinQ);
    $spinCount  = intval($row['total_spins']);
    $spinAmount = floatval($row['invited_wheel_amount']);

    // first spin?
    $h = $conn->query("SELECT COUNT(*) as c FROM shonu_turntable_spins WHERE user_id=$userId");
    $isFirst = (mysqli_fetch_assoc($h)['c'] == 0);
}

// TOTAL PRIZE
$tp = $conn->query("SELECT SUM(prize_amount) AS t FROM shonu_turntable_spins WHERE user_id=$userId");
$totalPrize = floatval(mysqli_fetch_assoc($tp)['t'] ?? 0);

// enforce minimum as your sample: 500
if ($totalPrize < 500) $totalPrize = 500;

// LAST 10 RECORDS
$history = [];
$hQ = $conn->query("SELECT * FROM shonu_turntable_spins WHERE user_id=$userId ORDER BY spin_time DESC LIMIT 10");

while ($r = mysqli_fetch_assoc($hQ)) {
    $history[] = [
        "userId" => $userId,
        "userName" => $userName,
        "invitedWheelAmount" => $spinAmount,
        "prizeAmount" => floatval($r['prize_amount']),
        "createTime" => $r['spin_time']
    ];
}

if (empty($history)) {
    $history[] = [
        "userId" => $userId,
        "userName" => $userName,
        "invitedWheelAmount" => $spinAmount,
        "prizeAmount" => 0.00,
        "createTime" => $now
    ];
}

// BUILD PERFECT RESPONSE
$data = [
    "isOpenInvitedWheel" => true,
    "isFirstInvitedWheel" => $isFirst,
    "userInvitedWheelCount" => $spinCount,
    "userInvitedWheelAmount" => $spinAmount,
    "invitedWheelTotalPrizeAmount" => $totalPrize,
    "invitedWheelAmountofcodeAmount" => 1.0,          // EXACT AS YOUR SAMPLE
    "expiredTime" => "",                              // EXACT AS YOUR SAMPLE
    "diskDisplayAmount" => [38.0, 58.0, 98.0, 118.0, 158.0, 178.0, 198.0],   // EXACT
    "noWinningRandomAmount" => [0.0,10.0],            // EXACT
    "lastWheelRecordList" => $history
];

$response = [
    "data" => $data,
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
];

echo json_encode($response);
exit();

?>
