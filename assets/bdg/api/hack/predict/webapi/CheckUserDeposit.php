<?php
// CheckUserDeposit.php - Fast check if logged-in user has made at least one approved deposit
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set("Asia/Kolkata");

// 1. Get bearer token from header or query or post body
$token = get_bearer_token();
if (empty($token)) {
    $body = json_decode(file_get_contents("php://input"), true);
    if (!empty($body['token'])) $token = trim($body['token']);
    elseif (!empty($_GET['token'])) $token = trim($_GET['token']);
}

if (empty($token)) {
    echo json_encode([
        'code' => 4,
        'msg' => 'Unauthorized: Token required',
        'msgCode' => 2,
        'data' => [
            'is_logged_in' => false,
            'has_deposited' => false,
            'total_deposit' => 0
        ]
    ]);
    exit;
}

// 2. Validate token
$is_jwt_valid = is_jwt_valid($token);
$data_auth = json_decode($is_jwt_valid, true);

if (!isset($data_auth['status']) || $data_auth['status'] !== 'Success' || empty($data_auth['payload']['id'])) {
    echo json_encode([
        'code' => 4,
        'msg' => 'Invalid or expired token',
        'msgCode' => 2,
        'data' => [
            'is_logged_in' => false,
            'has_deposited' => false,
            'total_deposit' => 0
        ]
    ]);
    exit;
}

$userId = intval($data_auth['payload']['id']);

// Verify user exists in shonu_subjects
$u_chk = $conn->query("SELECT id, mobile FROM shonu_subjects WHERE id = $userId LIMIT 1");
if (!$u_chk || $u_chk->num_rows < 1) {
    echo json_encode([
        'code' => 4,
        'msg' => 'User not found',
        'msgCode' => 2,
        'data' => [
            'is_logged_in' => false,
            'has_deposited' => false,
            'total_deposit' => 0
        ]
    ]);
    exit;
}

// 3. Query approved deposits from thevani (sthiti = 1)
$depQ = $conn->query("SELECT COALESCE(SUM(CAST(motta AS DECIMAL(10,2))), 0) AS total_deposit, COUNT(*) AS deposit_count FROM thevani WHERE balakedara = $userId AND sthiti = 1");
$depRow = $depQ ? $depQ->fetch_assoc() : null;
$totalDeposit = $depRow ? floatval($depRow['total_deposit']) : 0.0;
$depositCount = $depRow ? intval($depRow['deposit_count']) : 0;

$hasDeposited = ($totalDeposit > 0 && $depositCount > 0);

echo json_encode([
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'data' => [
        'userId' => $userId,
        'is_logged_in' => true,
        'has_deposited' => $hasDeposited,
        'total_deposit' => $totalDeposit,
        'deposit_count' => $depositCount
    ],
    'serviceNowTime' => date('Y-m-d H:i:s')
]);
