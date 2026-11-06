<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';
require_once __DIR__ . '/wash_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $now,
];

try {

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        http_response_code(405);
        echo json_encode($res);
        exit;
    }

    $body = file_get_contents("php://input");
    $post = json_decode($body, true);

    if (!isset($post['language'], $post['random'], $post['signature'], $post['timestamp'])) {
        echo json_encode([
            'code' => 7,
            'msg' => 'Param is Invalid',
            'msgCode' => 6
        ]);
        exit;
    }

    // 🔐 sanitize
    $codeType = isset($post['codeType']) ? (int)$post['codeType'] : -1;
    $language = (int)$post['language'];
    $random = mysqli_real_escape_string($conn, $post['random']);
    $signature = strtoupper(trim($post['signature']));

    // ✅ CORRECT SIGNATURE (MATCH CLIENT)
    $signStr = '{"codeType":'.$codeType.',"language":'.$language.',"random":"'.$random.'"}';
    $serverSign = strtoupper(md5($signStr));

    if ($serverSign !== $signature && !verify_signature($post, $signature)) {
        echo json_encode([
            'code' => 5,
            'msg' => 'Wrong signature',
            'msgCode' => 3
        ]);
        exit;
    }

    // 🔐 JWT check
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        echo json_encode(['code' => 401, 'msg' => 'No token']);
        exit;
    }

    $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
    $token = $bearer[1] ?? '';

    $jwt = json_decode(is_jwt_valid($token), true);

    if (!$jwt || !$jwt['status']) {
        http_response_code(401);
        echo json_encode([
            'code' => 4,
            'msg' => 'No operation permission',
            'msgCode' => 2
        ]);
        exit;
    }

    $userId = (int)$jwt['payload']['id'];

    // ✅ session verify
    $checkUser = $conn->query("SELECT akshinak FROM shonu_subjects WHERE akshinak = '$token'");
    if (mysqli_num_rows($checkUser) != 1) {
        echo json_encode([
            'code' => 403,
            'msg' => 'Invalid session'
        ]);
        exit;
    }

    // 🔥 LEVEL RATE (DB-driven from rebate_rules)
    $lvlq = $conn->query("SELECT lvl FROM vip WHERE userid = '$userId'");
    $lvlData = $lvlq->fetch_assoc();
    $lvl = $lvlData['lvl'] ?? 0;

    $washRate = sw_wash_rate($conn, $lvl);

    // 💰 balance
    $balq = $conn->query("SELECT rebet FROM shonu_kaichila WHERE balakedara = '$userId' ORDER BY kramasankhye DESC LIMIT 1");
    $bal = $balq->fetch_assoc();
    $codeWashAmount = (int)($bal['rebet'] ?? 0);

    // 📄 pagination
    $pageNo = isset($post['pageNo']) ? max(1, (int)$post['pageNo']) : 1;
    $pageSize = 10;
    $offset = ($pageNo - 1) * $pageSize;

    $today = date("Y-m-d") . " 00:00:00";

    // 🔥 MAIN DATA
    $query = "SELECT rebet, motta 
              FROM rebetrec 
              WHERE user_id = $userId 
              AND created_at >= '$today'
              ORDER BY id DESC 
              LIMIT $pageSize OFFSET $offset";

    $result = $conn->query($query);

    $washList = [];
    $dayRebate = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $washList[] = [
            'washVolume' => (int)$row['rebet'],
            'washRate' => $washRate,
            'rebateAmount' => (float)$row['motta']
        ];
        $dayRebate += $row['motta'];
    }

    // 📊 total count
    $countQ = $conn->query("SELECT COUNT(*) as total FROM rebetrec WHERE user_id = $userId AND created_at >= '$today'");
    $countData = $countQ->fetch_assoc();
    $totalCount = (int)$countData['total'];
    $totalPage = ceil($totalCount / $pageSize);

    // 💰 total rebate
    $totalQ = $conn->query("SELECT SUM(motta) as total FROM rebetrec WHERE user_id = $userId");
    $totalData = $totalQ->fetch_assoc();
    $totalRebate = (float)($totalData['total'] ?? 0);

    // ✅ RESPONSE
    echo json_encode([
        'code' => 0,
        'msg' => 'Succeed',
        'msgCode' => 0,
        'serviceNowTime' => $now,
        'data' => [
            'codeWashAmount' => $codeWashAmount,
            'dayRebate' => round($dayRebate, 3),
            'totalRebate' => round($totalRebate, 3),
            'washRate' => $washRate,
            'washList' => $washList,
            'pageNo' => $pageNo,
            'totalPage' => $totalPage,
            'totalCount' => $totalCount
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 10,
        'msg' => 'Internal server error',
        'msgCode' => 5
    ]);
}
?>