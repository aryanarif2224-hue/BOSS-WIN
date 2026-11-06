<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");
$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

list($auth_ok, $auth_res) = api_authenticate($conn, $shonupost);
if (!$auth_ok) {
    http_response_code($auth_res['http'] ?? 200);
    echo json_encode(['code' => $auth_res['code'], 'msg' => $auth_res['msg'], 'msgCode' => $auth_res['msgCode'] ?? 2, 'serviceNowTime' => $shnunc]);
    exit();
}
$userId = (int)$auth_res;

$userRechargeTimes = 0;
$userRechargeAmount = 0.0;
$balquery = "SELECT COUNT(*) as rechargeTimes, COALESCE(SUM(motta), 0) as totalRechargeAmount FROM thevani WHERE balakedara = '$userId' AND sthiti = 1";
$balresult = $conn->query($balquery);
if ($balresult && $balresult->num_rows > 0) {
    $balrow = $balresult->fetch_assoc();
    $userRechargeTimes = (int)$balrow['rechargeTimes'];
    $userRechargeAmount = (float)$balrow['totalRechargeAmount'];
}

$data = [
    'canDirectToGame' => true,
    'userRechargeTimes' => $userRechargeTimes,
    'allowNoRechargeGame' => "1",
    'userRechargeAmount' => $userRechargeAmount,
    'lowestRechargeAmountToGame' => "0"
];

echo json_encode([
    'data' => $data,
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'serviceNowTime' => $shnunc
]);
exit();
?>
