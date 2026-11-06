<?php
// GetRedpagePageList — gift-code redemption history for the logged-in user.
// POST { pageNo, pageSize, startDate?, endDate?, language, random, signature, timestamp }
// History now comes from gift_redemptions (admin-issued codes) instead of the old
// bdg red-page tables. List items: { addTime, amount, code }.
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, AR-REAL-IP');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
date_default_timezone_set("Asia/Kolkata");

function rp_out($data) { echo json_encode(['data' => $data, 'code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'serviceNowTime' => date('Y-m-d H:i:s')]); exit; }
function rp_err($msg, $code, $msgCode, $http = 200) { http_response_code($http); echo json_encode(['data' => null, 'code' => $code, 'msg' => $msg, 'msgCode' => $msgCode, 'serviceNowTime' => date('Y-m-d H:i:s')]); exit; }

$post = json_decode(file_get_contents("php://input"), true);
if (!is_array($post)) $post = [];
$auth = api_authenticate($conn, $post);
if ($auth[0] !== true) { $e = $auth[1]; rp_err($e['msg'], $e['code'], $e['msgCode'], isset($e['http']) ? $e['http'] : 200); }
$uid = (int)$auth[1];

$pageNo   = max(1, (int)($post['pageNo'] ?? 1));
$pageSize = min(50, max(1, (int)($post['pageSize'] ?? 10)));
$offset   = ($pageNo - 1) * $pageSize;

$total = 0;
if ($c = $conn->query("SELECT COUNT(*) c FROM gift_redemptions WHERE user_id = $uid")) $total = (int)$c->fetch_assoc()['c'];

$list = [];
$q = $conn->query("SELECT code, amount, created_at FROM gift_redemptions WHERE user_id = $uid
                   ORDER BY id DESC LIMIT $pageSize OFFSET $offset");
if ($q) while ($r = $q->fetch_assoc()) {
    $list[] = [
        'addTime'   => $r['created_at'],
        'amount'    => (int)$r['amount'],
        'code'      => $r['code'],
    ];
}
rp_out(['list' => $list, 'pageNo' => $pageNo, 'totalPage' => (int)ceil($total / $pageSize), 'totalCount' => $total]);
