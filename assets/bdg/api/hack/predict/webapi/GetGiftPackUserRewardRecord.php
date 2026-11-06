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

    // Check user registration date and recharge status
    $regDate = "2026-08-25 00:00:00";
    $uq = $conn->query("SELECT createdate FROM shonu_subjects WHERE id = $shonuid");
    if ($uq && $uRow = $uq->fetch_assoc()) {
        if (!empty($uRow['createdate'])) {
            $regDate = $uRow['createdate'];
        }
    }

    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = [
        'firstDepositConfig' => [
            'activityStartDate' => $regDate,
            'firstDeposiSendBonust' => 25.00,
            'bonusLimit' => 100.00,
            'isAutomaticDistribution' => true,
            'rewardState' => 0,
            'firstDepositTimeLiness' => 24,
        ],
        'giftPackConfigAwardList' => [],
        'newUserRewardRecordList' => [],
    ];

    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>
