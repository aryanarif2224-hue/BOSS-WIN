<?php
/* GetTotalRebateRules.php — rebate/agent-tier rules from the `rebate_rules` table.
 * Replaces the old hard-coded JSON. Auth: signed request or valid bearer token.
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 11, 'msg' => 'Method not allowed', 'msgCode' => 12]);
    exit();
}

list($auth_ok, $auth_res) = api_authenticate($conn, json_decode(file_get_contents('php://input'), true) ?: []);
if (!$auth_ok) {
    http_response_code($auth_res['http'] ?? 401);
    echo json_encode(['code' => $auth_res['code'] ?? 4, 'msg' => $auth_res['msg'] ?? 'No operation permission', 'msgCode' => $auth_res['msgCode'] ?? 2]);
    exit();
}

$list = [];
$r = $conn->query("SELECT lv, lvCount, rechargeAmount, lotteryAmount, washRate, remark FROM rebate_rules ORDER BY lv ASC");
while ($r && $x = $r->fetch_assoc()) {
    $list[] = [
        'lv'             => (int)$x['lv'],
        'lvCount'        => (int)$x['lvCount'],
        'rechargeAmount' => (float)$x['rechargeAmount'],
        'lotteryAmount'  => (float)$x['lotteryAmount'],
        'washRate'       => (float)$x['washRate'],
        'remark'         => (string)$x['remark'],
    ];
}

echo json_encode([
    'data' => $list,
    'code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'serviceNowTime' => $shnunc,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
