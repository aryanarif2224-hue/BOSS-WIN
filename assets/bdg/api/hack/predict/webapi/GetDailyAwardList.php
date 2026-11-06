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
    $todayDate = date("Y-m-d");

    // Calculate today's recharge
    $todayRecharge = 0.0;
    if (tbl_exists($conn, 'shonu_recharge')) {
        $rQ = $conn->query("SELECT COALESCE(SUM(pramana), 0) AS total_rec FROM shonu_recharge WHERE byabaharkarta = $shonuid AND sthiti = 1 AND DATE(tiarikala) = '$todayDate'");
        if ($rQ && $rRow = $rQ->fetch_assoc()) {
            $todayRecharge = (float)$rRow['total_rec'];
        }
    }

    // Calculate today's bets across lottery tables
    $lotteryTables = [
        'bajikattuttate', 'bajikattuttate_drei', 'bajikattuttate_funf', 'bajikattuttate_zehn',
        'bajikattuttate_aidudi', 'bajikattuttate_aidudi_drei', 'bajikattuttate_aidudi_funf', 'bajikattuttate_aidudi_zehn',
        'bajikattuttate_kemuru', 'bajikattuttate_kemuru_drei', 'bajikattuttate_kemuru_funf', 'bajikattuttate_kemuru_zehn',
        'bajikattuttate_trx', 'bajikattuttate_trx3', 'bajikattuttate_trx5', 'bajikattuttate_trx10'
    ];
    $bQueries = [];
    foreach ($lotteryTables as $tbl) {
        if (tbl_exists($conn, $tbl)) {
            $bQueries[] = "SELECT ketebida FROM $tbl WHERE byabaharkarta = $shonuid AND DATE(tiarikala) = '$todayDate'";
        }
    }
    $todayBet = 0.0;
    if (!empty($bQueries)) {
        $bRes = $conn->query("SELECT COALESCE(SUM(ketebida), 0) AS total_bet FROM (" . implode(" UNION ALL ", $bQueries) . ") AS today_b");
        if ($bRes && $bRow = $bRes->fetch_assoc()) {
            $todayBet = (float)$bRow['total_bet'];
        }
    }

    // Task configurations
    $tasksConfig = [
        [
            'mainConfigId' => 6,
            'configId' => 66,
            'taskId' => 'D20',
            'taskTitle' => 'Daily Recharge & Bet Task',
            'taskDescribe' => "Members must complete the deposit and place bets, also meet both the minimum betting and deposit requirements within a day to complete the task and receive the reward.",
            'targetOne' => 300.0,
            'targetTwo' => 1500.0,
            'award' => 10.0,
            'lId' => 42,
        ],
        [
            'mainConfigId' => 7,
            'configId' => 67,
            'taskId' => 'D20',
            'taskTitle' => 'Daily Recharge & Bet Task',
            'taskDescribe' => "Members must complete the deposit and place bets, also meet both the minimum betting and deposit requirements within a day to complete the task and receive the reward.",
            'targetOne' => 1000.0,
            'targetTwo' => 5000.0,
            'award' => 20.0,
            'lId' => 44,
        ],
        [
            'mainConfigId' => 8,
            'configId' => 68,
            'taskId' => 'D20',
            'taskTitle' => 'Daily Recharge & Bet Task',
            'taskDescribe' => "Members must complete the deposit and place bets, also meet both the minimum betting and deposit requirements within a day to complete the task and receive the reward.",
            'targetOne' => 1500.0,
            'targetTwo' => 7500.0,
            'award' => 25.0,
            'lId' => 46,
        ],
        [
            'mainConfigId' => 9,
            'configId' => 69,
            'taskId' => 'D20',
            'taskTitle' => 'Daily Recharge & Bet Task',
            'taskDescribe' => "Members must complete the deposit and place bets, also meet both the minimum betting and deposit requirements within a day to complete the task and receive the reward.",
            'targetOne' => 3000.0,
            'targetTwo' => 15000.0,
            'award' => 60.0,
            'lId' => 48,
        ],
        [
            'mainConfigId' => 10,
            'configId' => 70,
            'taskId' => 'D20',
            'taskTitle' => 'Daily Recharge & Bet Task',
            'taskDescribe' => "Members must complete the deposit and place bets, also meet both the minimum betting and deposit requirements within a day to complete the task and receive the reward.",
            'targetOne' => 5000.0,
            'targetTwo' => 25000.0,
            'award' => 100.0,
            'lId' => 50,
        ],
    ];

    $taskList = [];
    foreach ($tasksConfig as $cfg) {
        $cid = (int)$cfg['configId'];
        // Check if already claimed today
        $claimed = false;
        if (tbl_exists($conn, 'daily_awards_table')) {
            $cq = $conn->query("SELECT id FROM daily_awards_table WHERE userkani = $shonuid AND DATE(shonu) = '$todayDate' AND serial = 'Daily_Task_$cid'");
            if ($cq && $cq->num_rows > 0) {
                $claimed = true;
            }
        }

        $schedOne = min($cfg['targetOne'], $todayRecharge);
        $schedTwo = min($cfg['targetTwo'], $todayBet);

        if ($claimed) {
            $status = 3; // Claimed
        } elseif ($todayRecharge >= $cfg['targetOne'] && $todayBet >= $cfg['targetTwo']) {
            $status = 2; // Complete / Ready to receive
        } else {
            $status = 1; // Undone / In progress
        }

        $taskList[] = [
            'userId' => $shonuid,
            'mainConfigId' => $cfg['mainConfigId'],
            'configId' => $cfg['configId'],
            'schedule' => round($schedOne, 2),
            'status' => $status,
            'taskTitle' => $cfg['taskTitle'],
            'taskDescribe' => $cfg['taskDescribe'],
            'taskId' => $cfg['taskId'],
            'taskTarget' => $cfg['targetOne'],
            'taskAwardAmount' => $cfg['award'],
            'createDate' => $todayDate . ' 00:00:00',
            'targetTwo' => $cfg['targetTwo'],
            'targetItem' => 0,
            'targetSubItem' => null,
            'rechargeCategories' => '',
            'scheduleTwo' => round($schedTwo, 2),
            'lId' => $cfg['lId'],
            'receiveType' => 1,
            'displayRewardMinAmount' => 0.0,
            'displayRewardMaxAmount' => 0.0,
            'activityCenterDisplayMaxAmount' => null,
            'actualRewardMinAmount' => 0.0,
            'actualRewardMaxAmount' => 0.0,
            'isReceiveButtonHidden' => false,
        ];
    }

    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = $taskList;
    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>
