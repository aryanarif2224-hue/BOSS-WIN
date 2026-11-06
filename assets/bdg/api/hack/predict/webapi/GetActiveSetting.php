<?php
// GetActiveSetting.php - Dynamic Activity settings & bonus totals
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';
require_once __DIR__ . '/_activity_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$todayRewards = 0.0;
$totalRewards = 0.0;

$author = get_bearer_token();
if (!empty($author)) {
    $is_jwt_valid = is_jwt_valid($author);
    $data_auth = json_decode($is_jwt_valid, true);
    if ($data_auth && $data_auth['status'] === 'Success' && !empty($data_auth['payload']['id'])) {
        $userId = (int)$data_auth['payload']['id'];
        $stats = get_all_activity_bonus_totals($conn, $userId);
        $todayRewards = $stats['todayRewards'];
        $totalRewards = $stats['totalRewards'];
    }
}

$res = [
    'code' => 0,
    'msg' => 'Succeed',
    'msgCode' => 0,
    'serviceNowTime' => $now,
    'data' => [
        'isTaskState' => "1",
        'isOpenJackpotReward' => "1",
        'isOpenWashCode' => "1",
        'unJackpotCount' => 0,
        'isOpenActivityAward' => "1",
        'unWeeklyAwardCount' => 0,
        'isFinishUserGuidelines' => true,
        'isFirstUserDayRequest' => false,
        'isOpenChampion' => "0",
        'newbieGiftPackCount' => 0,
        'newMemberGiftPackageSwitch' => 1,
        'todayRewards' => $todayRewards,
        'totalRewards' => $totalRewards
    ]
];

echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);