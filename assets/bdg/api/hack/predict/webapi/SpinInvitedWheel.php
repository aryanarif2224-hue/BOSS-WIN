<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// Read body
$body = file_get_contents("php://input");
$post = json_decode($body, true);

// Default response
$response = [
    "data" => null,
    "code" => 11,
    "msg" => "Method not allowed",
    "msgCode" => 12,
    "serviceNowTime" => $now
];

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

// Validate Params
if (!isset($post['language'], $post['random'], $post['signature'], $post['timestamp'])) {
    $response["code"] = 7;
    $response["msg"] = "Param is Invalid";
    $response["msgCode"] = 6;
    echo json_encode($response);
    exit();
}

$language   = $post['language'];
$random     = $post['random'];
$signature  = strtoupper($post['signature']);

$signStr = '{"language":'.$language.',"random":"'.$random.'"}';
$signCheck = strtoupper(md5($signStr));

if ($signature !== $signCheck && !verify_signature($post, $signature)) {
    $response["code"] = 5;
    $response["msg"]  = "Wrong signature";
    $response["msgCode"] = 3;
    echo json_encode($response);
    exit();
}

// TOKEN VERIFY
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $response["code"] = 4;
    $response["msg"]  = "No operation permission";
    echo json_encode($response);
    exit();
}

$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
$token = trim($bearer[1]);

$jwt = json_decode(is_jwt_valid($token), true);
if (!$jwt || $jwt["status"] !== "Success") {
    $response["code"] = 4;
    $response["msg"]  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userId = intval($jwt["payload"]["id"]);

// Check user exists
$uQ = $conn->query("SELECT codechorkamukala FROM shonu_subjects WHERE akshinak='$token'");
if (!$uQ || mysqli_num_rows($uQ) !== 1) {
    $response["code"] = 4;
    $response["msg"] = "No operation permission";
    echo json_encode($response);
    exit();
}
$userName = mysqli_fetch_assoc($uQ)['codechorkamukala'];

// ---- CHECK IF FIRST TIME SPIN ----
$spinCountQ = $conn->query("SELECT COUNT(*) AS c FROM shonu_turntable_spins WHERE user_id=$userId");
$isFirstTime = (mysqli_fetch_assoc($spinCountQ)['c'] == 0);


// ------------------------------
//   MODE 1 → FIRST TIME USER
// ------------------------------
if ($isFirstTime) {

    // 4 BOX AMOUNTS (FIXED)
    $boxAmounts = [
        495.40,
        467.00,
        51.15,
        498.50
    ];

    // pick random box
    $selectedIndex = array_rand($boxAmounts);
    $selectedAmount = $boxAmounts[$selectedIndex];

    // Prepare list
    $firstWheelList = [];
    foreach ($boxAmounts as $i => $amt) {
        $firstWheelList[] = [
            "amount" => floatval($amt),
            "isSelected" => ($i == $selectedIndex)
        ];
    }

    // Insert spin record
    $amtDb = number_format($selectedAmount, 2, '.', '');
    $userNameEsc = mysqli_real_escape_string($conn, $userName);
    $conn->query("
        INSERT INTO shonu_turntable_spins (user_id, prize_amount, spin_time, user_name)
        VALUES ($userId, $amtDb, '$now', '$userNameEsc')
    ");

    // Update turntable spin count (create if missing)
    $ttQ = $conn->query("SELECT id FROM shonu_turntable WHERE user_id=$userId");
    if (mysqli_num_rows($ttQ) == 0) {
        $conn->query("
            INSERT INTO shonu_turntable (user_id, total_spins, invited_wheel_amount, created_at, updated_at)
            VALUES ($userId, 1, $amtDb, NOW(), NOW())
        ");
    } else {
        $conn->query("
            UPDATE shonu_turntable
            SET total_spins = GREATEST(total_spins - 1, 0),
                invited_wheel_amount = invited_wheel_amount + $amtDb,
                updated_at = NOW()
            WHERE user_id = $userId
        ");
    }

    // Final response
    $response = [
        "data" => [
            "isFirstInvitedWheel" => true,
            "prizeAmount" => floatval($selectedAmount),
            "isWin" => ($selectedAmount > 0),
            "firstInvitedWheelDatas" => $firstWheelList
        ],
        "code" => 0,
        "msg" => "Succeed",
        "msgCode" => 0,
        "serviceNowTime" => $now
    ];

    echo json_encode($response);
    exit();
}



// ---------------------------------
//   MODE 2 → NORMAL SPIN (WHEEL)
// ---------------------------------

$wheelPrizes = [
    ['amount'=>0.02,'probability'=>0.35],
    ['amount'=>0.05,'probability'=>0.25],
    ['amount'=>0.10,'probability'=>0.15],
    ['amount'=>0.20,'probability'=>0.10],
    ['amount'=>0.50,'probability'=>0.08],
    ['amount'=>1.00,'probability'=>0.05],
    ['amount'=>2.00,'probability'=>0.02],
    ['amount'=>0.00,'probability'=>0.00]
];

$r = mt_rand() / mt_getrandmax();
$cum = 0;
$selected = null;

foreach ($wheelPrizes as $p) {
    $cum += $p['probability'];
    if ($r <= $cum) {
        $selected = $p;
        break;
    }
}

if (!$selected) $selected = ['amount'=>0.00];

$prize = floatval($selected['amount']);
$isWin = ($prize > 0);

// INSERT SPIN RECORD
$prizeDb = number_format($prize, 2, '.', '');
$userNameEsc = mysqli_real_escape_string($conn, $userName);

$conn->query("
    INSERT INTO shonu_turntable_spins (user_id, prize_amount, spin_time, user_name)
    VALUES ($userId, $prizeDb, '$now', '$userNameEsc')
");

$conn->query("
    UPDATE shonu_turntable
    SET total_spins = GREATEST(total_spins - 1, 0),
        invited_wheel_amount = invited_wheel_amount + $prizeDb,
        updated_at = NOW()
    WHERE user_id = $userId
");

// FINAL RESPONSE (NORMAL SPIN)
$response = [
    "data" => [
        "isFirstInvitedWheel" => false,
        "prizeAmount" => floatval($prize),
        "isWin" => $isWin,
        "firstInvitedWheelDatas" => null
    ],
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
];

echo json_encode($response);
exit();

?>
