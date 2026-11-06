<?php
/* GetPartnerRewards.php — real referral/partner rewards for the logged-in user.
 * Replaces the old static JSON stub. Returns the user's invitation code, how many
 * people joined with it, their total (valid) deposit + turnover, and the reward
 * tiers from partner_reward_config.
 */
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

function pr_fail($code, $msg, $msgCode = 2, $http = 200) {
    http_response_code($http);
    echo json_encode(['code' => $code, 'msg' => $msg, 'msgCode' => $msgCode, 'data' => null, 'serviceNowTime' => date('Y-m-d H:i:s')]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 11, 'msg' => 'Method not allowed', 'msgCode' => 12]);
    exit();
}

list($auth_ok, $auth_res) = api_authenticate($conn, json_decode(file_get_contents('php://input'), true) ?: []);
if (!$auth_ok) pr_fail($auth_res['code'] ?? 4, $auth_res['msg'] ?? 'No operation permission', $auth_res['msgCode'] ?? 2, $auth_res['http'] ?? 401);
$uid = (int)$auth_res;

// user's own invite code
$u = $conn->query("SELECT owncode FROM shonu_subjects WHERE id = $uid LIMIT 1")->fetch_assoc();
$inviteCode = (string)($u['owncode'] ?? '');

// direct invitees
$invitees = [];
$r = $conn->query("SELECT id, mobile FROM shonu_subjects WHERE code = '" . $conn->real_escape_string($inviteCode) . "'");
while ($r && $x = $r->fetch_assoc()) $invitees[] = $x;
$numberOfInvitations = count($invitees);

// effective = invitees who have at least one successful deposit
$effective = 0; $totalAmount = 0.0; $totalBet = 0.0;
if ($invitees) {
    $ids = array_map(fn($x) => (int)$x['id'], $invitees);
    $idList = implode(',', $ids);
    $effective = (int)($conn->query("SELECT COUNT(DISTINCT balakedara) FROM thevani WHERE sthiti=1 AND balakedara IN ($idList)")->fetch_row()[0] ?? 0);
    $totalAmount = (float)($conn->query("SELECT COALESCE(SUM(motta),0) FROM thevani WHERE sthiti=1 AND balakedara IN ($idList)")->fetch_row()[0] ?? 0);
    $totalBet = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM saas_bets WHERE user_id IN ($idList)")->fetch_row()[0] ?? 0);
}

// reward tiers from config
$items = [];
$r = $conn->query("SELECT id,tier,type,rechargeAmount,betAmount,rewardAmount,days FROM partner_reward_config WHERE active=1 ORDER BY tier ASC");
$maxReward = 0.0;
while ($r && $x = $r->fetch_assoc()) {
    $items[] = [
        'id' => (int)$x['id'], 'type' => (int)$x['type'],
        'rechargeAmount' => (float)$x['rechargeAmount'], 'betAmount' => (float)$x['betAmount'],
        'rewardAmount' => (float)$x['rewardAmount'],
        'createTime' => $shnunc, 'updateTime' => $shnunc, 'days' => (int)$x['days'],
    ];
    $maxReward = max($maxReward, (float)$x['rewardAmount']);
}

echo json_encode([
    'data' => [
        'configAmount'        => $maxReward,
        'numberOfInvitations' => $numberOfInvitations,
        'effectiveQuantity'   => $effective,
        'totalAmount'         => round($totalAmount, 2),
        'totalBet'            => round($totalBet, 2),
        'invitationCode'      => $inviteCode,
        'items'               => $items,
        'days'                => 7,
    ],
    'code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'serviceNowTime' => $shnunc,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
