<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$currentDate = date("Y-m-d H:i:s");

$response = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $currentDate,
];

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestBody = file_get_contents("php://input");
$requestData = json_decode($requestBody, true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    if (!isset(
        $requestData['language'], $requestData['random'], $requestData['signature'],
        $requestData['timestamp'], $requestData['type'], $requestData['startDate'],
        $requestData['endDate'], $requestData['pageNo'], $requestData['pageSize']
    )) {

        $response['code'] = 8;
        $response['msg'] = 'Required parameters missing';
        $response['msgCode'] = 7;
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // SANITIZE
    $language = mysqli_real_escape_string($conn, $requestData['language']);
    $random = mysqli_real_escape_string($conn, $requestData['random']);
    $signature = mysqli_real_escape_string($conn, $requestData['signature']);
    $withdrawTypeReq = mysqli_real_escape_string($conn, $requestData['type']);
    $startDate = mysqli_real_escape_string($conn, $requestData['startDate']);
    $endDate = mysqli_real_escape_string($conn, $requestData['endDate']);
    $pageNo = (int)$requestData['pageNo'];
    $pageSize = min((int)$requestData['pageSize'], 100);

    // SIGNATURE CHECK
    $signStr = '{"language":"' . $language . '","random":"' . $random . '","withdrawid":' . $withdrawTypeReq . ',"startDate":"' . $startDate . '","endDate":"' . $endDate . '"}';
    $expectedSignature = strtoupper(md5($signStr));

    if ($expectedSignature !== $signature && !verify_signature($requestData, $signature)) {
        $response['code'] = 5;
        $response['msg'] = 'Wrong signature';
        $response['msgCode'] = 3;
        http_response_code(200);
        echo json_encode($response);
        exit;
    }

    // JWT CHECK
    $token = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? '';
    $jwtCheck = is_jwt_valid($token);
    $jwtData = json_decode($jwtCheck, true);

    if ($jwtData['status'] !== 'Success') {
        $response['code'] = 5;
        $response['msg'] = 'Invalid JWT';
        $response['msgCode'] = 3;
        http_response_code(401);
        echo json_encode($response);
        exit;
    }

    $userId = $jwtData['payload']['id'];

    // AUTH CHECK
    $chk = $conn->query("SELECT akshinak FROM shonu_subjects WHERE akshinak='$token'");
    if (mysqli_num_rows($chk) !== 1) {
        $response['code'] = 4;
        $response['msg'] = 'No operation permission';
        $response['msgCode'] = 2;
        http_response_code(403);
        echo json_encode($response);
        exit;
    }

    // DATE FILTER
    $dateCondition = "";
    if (!empty($startDate) && !empty($endDate)) {
        $dateCondition = " AND dinankavannuracisi BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' ";
    }

    // WITHDRAW TYPE 
    // 1 = BANK, 2 = UPI, -1 = All
    $whereBank = "";
    $whereUPI  = "";

    if ($withdrawTypeReq == 1) {
        $whereBank = " AND madari = 1 ";
        $whereUPI = " AND type = 2 ";
    } elseif ($withdrawTypeReq == 2) {
        $whereBank = " AND 1=2 "; // No bank
        $whereUPI = " AND type = 2 ";
    } else {
        // All
        $whereBank = "";
        $whereUPI  = "";
    }

    // COUNT records
    // UPI table (upi_withdraw_requests) may not exist on all deployments — guard it.
    $upiTableExists = false;
    $tblChk = $conn->query("SHOW TABLES LIKE 'upi_withdraw_requests'");
    if ($tblChk && mysqli_num_rows($tblChk) > 0) {
        $upiTableExists = true;
    }

    $bankCountQ = $conn->query("SELECT COUNT(*) AS total FROM hintegedukolli WHERE balakedara='$userId' $whereBank $dateCondition");
    $bankCount = ($bankCountQ && ($r = $bankCountQ->fetch_assoc())) ? (int)$r['total'] : 0;

    $upiCount = 0;
    if ($upiTableExists) {
        $upiCountQ = $conn->query("SELECT COUNT(*) AS total FROM upi_withdraw_requests WHERE user_id='$userId' $whereUPI $dateCondition");
        $upiCount = ($upiCountQ && ($r = $upiCountQ->fetch_assoc())) ? (int)$r['total'] : 0;
    }

    $totalCount = $bankCount + $upiCount;
    $totalPage = $pageSize > 0 ? ceil($totalCount / $pageSize) : 0;
    $offset = ($pageNo - 1) * $pageSize;

    // MAIN LIST ARRAY
    $finalList = [];

    // ---------------------------
    // ⭐ FETCH BANK WITHDRAWALS
    // ---------------------------
    $bankQ = $conn->query("
        SELECT shonu AS id, motta AS amount, dharavahi AS number, madari, remarks, dinankavannuracisi, sthiti 
        FROM hintegedukolli 
        WHERE balakedara='$userId' $whereBank $dateCondition
        ORDER BY dinankavannuracisi DESC
        LIMIT $offset, $pageSize
    ");

    while ($bankQ && $row = $bankQ->fetch_assoc()) {
        $finalList[] = [
            "withdrawID" => $row['id'],
            "type" => (int)$row['madari'],
            "withdrawNumber" => $row['number'],
            "withdrawName" => "BANK CARD",
            "price" => (int)$row['amount'],
            "addTime" => $row['dinankavannuracisi'],
            "realityAmount" => (int)$row['amount'],
            "remark" => $row['remarks'],
            "state" => (int)$row['sthiti'],
            "thirdpartyState" => (int)$row['sthiti']
        ];
    }

    // ---------------------------
    // ⭐ FETCH UPI WITHDRAWALS (only if table exists)
    // ---------------------------
    if ($upiTableExists) {
        $upiQ = $conn->query("
            SELECT id, amount, serial, created_at, status
            FROM upi_withdraw_requests
            WHERE user_id='$userId' $whereUPI $dateCondition
            ORDER BY created_at DESC
            LIMIT $offset, $pageSize
        ");

        if ($upiQ) {
            while ($row = $upiQ->fetch_assoc()) {
                $finalList[] = [
                    "withdrawID" => $row['id'],
                    "type" => 2,
                    "withdrawNumber" => $row['serial'],
                    "withdrawName" => "UPI",
                    "price" => (int)$row['amount'],
                    "addTime" => $row['created_at'],
                    "realityAmount" => (int)$row['amount'],
                    "remark" => null,
                    "state" => ($row['status'] == "Pending" ? 0 : ($row['status'] == "Success" ? 1 : 2)),
                    "thirdpartyState" => ($row['status'] == "Pending" ? 0 : ($row['status'] == "Success" ? 1 : 2))
                ];
            }
        }
    }

    // FINAL RESPONSE
    $response = [
        "data" => [
            "list" => $finalList,
            "pageNo" => $pageNo,
            "totalPage" => $totalPage,
            "totalCount" => $totalCount
        ],
        "code" => 0,
        "msg" => "Succeed",
        "msgCode" => 0,
        "serviceNowTime" => $currentDate
    ];

    echo json_encode($response);
    exit;
}

// METHOD NOT ALLOWED
http_response_code(405);
echo json_encode($response);
?>
