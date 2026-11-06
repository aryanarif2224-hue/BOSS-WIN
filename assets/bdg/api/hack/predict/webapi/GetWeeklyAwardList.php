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
    
    // Weekly bounds (Monday to Sunday)
    $monday = date("Y-m-d 00:00:00", strtotime("monday this week"));

    // Calculate this week's bets across lottery tables
    $lotteryTables = [
        'bajikattuttate', 'bajikattuttate_drei', 'bajikattuttate_funf', 'bajikattuttate_zehn',
        'bajikattuttate_aidudi', 'bajikattuttate_aidudi_drei', 'bajikattuttate_aidudi_funf', 'bajikattuttate_aidudi_zehn',
        'bajikattuttate_kemuru', 'bajikattuttate_kemuru_drei', 'bajikattuttate_kemuru_funf', 'bajikattuttate_kemuru_zehn',
        'bajikattuttate_trx', 'bajikattuttate_trx3', 'bajikattuttate_trx5', 'bajikattuttate_trx10'
    ];
    $bQueries = [];
    foreach ($lotteryTables as $tbl) {
        if (tbl_exists($conn, $tbl)) {
            $bQueries[] = "SELECT ketebida FROM $tbl WHERE byabaharkarta = $shonuid AND tiarikala >= '$monday'";
        }
    }
    $weekBet = 0.0;
    if (!empty($bQueries)) {
        $bRes = $conn->query("SELECT COALESCE(SUM(ketebida), 0) AS total_bet FROM (" . implode(" UNION ALL ", $bQueries) . ") AS week_b");
        if ($bRes && $bRow = $bRes->fetch_assoc()) {
            $weekBet = (float)$bRow['total_bet'];
        }
    }

    $tasksConfig = [
        [
            'mainConfigId' => 7,
            'configId' => 7,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 10000.0,
            'award' => 25.0,
            'lId' => 30,
        ],
        [
            'mainConfigId' => 8,
            'configId' => 8,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 20000.0,
            'award' => 50.0,
            'lId' => 32,
        ],
        [
            'mainConfigId' => 9,
            'configId' => 9,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 50000.0,
            'award' => 200.0,
            'lId' => 34,
        ],
        [
            'mainConfigId' => 10,
            'configId' => 10,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 100000.0,
            'award' => 500.0,
            'lId' => 36,
        ],
        [
            'mainConfigId' => 11,
            'configId' => 11,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 150000.0,
            'award' => 700.0,
            'lId' => 38,
        ],
        [
            'mainConfigId' => 12,
            'configId' => 12,
            'taskId' => 'B7',
            'taskTitle' => 'Weekly Betting Task',
            'taskDescribe' => 'Member must place bets and reach the minimum requirement betting amount in a week to complete this task and receive the reward.',
            'target' => 300000.0,
            'award' => 1500.0,
            'lId' => 40,
        ],
    ];

    $taskList = [];
    foreach ($tasksConfig as $cfg) {
        $cid = (int)$cfg['configId'];
        $claimed = false;
        if (tbl_exists($conn, 'weekly_awards_table')) {
            $cq = $conn->query("SELECT id FROM weekly_awards_table WHERE userkani = $shonuid AND shonu >= '$monday' AND serial = 'Weekly_Task_$cid'");
            if ($cq && $cq->num_rows > 0) {
                $claimed = true;
            }
        }

        $sched = min($cfg['target'], $weekBet);
        if ($claimed) {
            $status = 3;
        } elseif ($weekBet >= $cfg['target']) {
            $status = 2;
        } else {
            $status = 1;
        }

        $taskList[] = [
            'userId' => $shonuid,
            'mainConfigId' => $cfg['mainConfigId'],
            'configId' => $cfg['configId'],
            'schedule' => round($sched, 2),
            'status' => $status,
            'taskTitle' => $cfg['taskTitle'],
            'taskDescribe' => $cfg['taskDescribe'],
            'taskId' => $cfg['taskId'],
            'taskTarget' => $cfg['target'],
            'taskAwardAmount' => $cfg['award'],
            'createDate' => $monday,
            'targetTwo' => 0.0,
            'targetItem' => 0,
            'targetSubItem' => null,
            'rechargeCategories' => null,
            'scheduleTwo' => 0.0,
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
