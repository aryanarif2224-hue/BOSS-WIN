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
    $pageNo = isset($shonupost['pageNo']) ? max(1, (int)$shonupost['pageNo']) : 1;
    $pageSize = isset($shonupost['pageSize']) ? max(1, (int)$shonupost['pageSize']) : 20;
    $offset = ($pageNo - 1) * $pageSize;

    $list = [];
    $totalCount = 0;

    if (tbl_exists($conn, 'daily_awards_table')) {
        $cQ = $conn->query("SELECT COUNT(*) AS cnt FROM daily_awards_table WHERE userkani = $shonuid");
        if ($cQ && $cR = $cQ->fetch_assoc()) {
            $totalCount = (int)$cR['cnt'];
        }
        $q = $conn->query("SELECT * FROM daily_awards_table WHERE userkani = $shonuid ORDER BY id DESC LIMIT $pageSize OFFSET $offset");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $list[] = [
                    'id' => (int)$r['id'],
                    'awardAmount' => (float)$r['price'],
                    'awardTitle' => (string)($r['remark'] ?: 'Daily Task Award'),
                    'createTime' => (string)$r['shonu'],
                    'state' => 1,
                ];
            }
        }
    }

    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = [
        'list' => $list,
        'pageNo' => $pageNo,
        'totalPage' => $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 0,
        'totalCount' => $totalCount,
    ];
    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>
