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

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    list($auth_ok, $auth_res) = api_authenticate($conn, $shonupost);
    if (!$auth_ok) {
        http_response_code(isset($auth_res['http']) ? $auth_res['http'] : 200);
        $res['code'] = $auth_res['code'];
        $res['msg'] = $auth_res['msg'];
        $res['msgCode'] = $auth_res['msgCode'];
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $shonuid = (int)$auth_res;
    $awardId = isset($shonupost['weeklyAwardId']) ? (int)$shonupost['weeklyAwardId'] : 0;

    $awardMap = [
        7 => 25.0,
        8 => 50.0,
        9 => 200.0,
        10 => 500.0,
        11 => 700.0,
        12 => 1500.0,
    ];

    $amount = isset($awardMap[$awardId]) ? $awardMap[$awardId] : 25.0;

    // Insert into weekly_awards_table
    if (tbl_exists($conn, 'weekly_awards_table')) {
        $conn->query("INSERT INTO weekly_awards_table (userkani, price, serial, shonu, remark, processed) VALUES ($shonuid, $amount, 'Weekly_Task_$awardId', NOW(), 'Weekly Task Reward', 1)");
    }

    // Add balance to user
    $conn->query("UPDATE shonu_subjects SET samasye = samasye + $amount WHERE id = $shonuid");

    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = null;
    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>
