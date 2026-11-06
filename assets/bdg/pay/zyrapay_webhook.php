<?php
// Zyra Pay Webhook Callback Handler for shreewin06.site

if (file_exists(__DIR__ . '/../serive/samparka.php')) {
    include_once __DIR__ . '/../serive/samparka.php';
} elseif (file_exists('/raxi/bdg/conn.php')) {
    include_once '/raxi/bdg/conn.php';
}

$config = require __DIR__ . '/config.php';
if (!isset($conn) || !$conn) {
    $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
}

function logError($message) {
    @file_put_contents(__DIR__ . '/zyrapay_log.txt', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!$data) {
    logError("Empty or invalid payload. Raw input: " . substr($input, 0, 500));
    exit;
}

logError("Webhook received: " . json_encode($data, JSON_UNESCAPED_UNICODE));

$api_secret = $config['NOVAX_API_SECRET'];
$resSign = $data['signature'] ?? $data['sign'] ?? null;

if (!$resSign) {
    logError("Signature missing in webhook data");
    echo json_encode(["status" => false, "message" => "Sign missing"]);
    exit;
}

$paramArray = [];
foreach ($data as $key => $val) {
    if ($key !== 'signature' && $key !== 'sign' && $val !== '' && $val !== null) {
        $paramArray[$key] = $val;
    }
}

ksort($paramArray);

$stringA = '';
foreach ($paramArray as $key => $val) {
    $stringA .= $key . '=' . $val . '&';
}
$stringA = rtrim($stringA, '&');

$stringSignTemp = $stringA . '&key=' . $api_secret;
$calculatedSign = strtolower(md5($stringSignTemp));

if (strtolower($resSign) !== $calculatedSign) {
    logError("Signature mismatch. Expected: $calculatedSign, Got: $resSign");
    echo json_encode(["status" => false, "message" => "Invalid signature"]);
    exit;
}

$status = strtoupper((string)($data['status'] ?? ''));
if ($status !== 'SUCCESS') {
    logError("Transaction status not SUCCESS (Got: " . ($data['status'] ?? 'UNKNOWN') . ")");
    echo "SUCCESS";
    exit;
}

$mchOrderNo = $data['merchant_order_id'] ?? $data['order_id'] ?? $data['mchOrderNo'] ?? '';
$mchOrderNo = mysqli_real_escape_string($conn, trim((string)$mchOrderNo));

if ($mchOrderNo === '') {
    logError("Missing order number in webhook data");
    echo "FAIL";
    exit;
}

$checkamt = mysqli_query($conn, "SELECT motta, balakedara FROM thevani WHERE dharavahi = '" . $mchOrderNo . "' AND sthiti = '0' LIMIT 1");

if (!$checkamt) {
    logError("Database query error: " . mysqli_error($conn));
    echo "FAIL";
    exit;
}

if (mysqli_num_rows($checkamt) >= 1) {
    $checkamtar = mysqli_fetch_array($checkamt);
    $motta = floatval($checkamtar['motta']);
    $shonuid = mysqli_real_escape_string($conn, (string)$checkamtar['balakedara']);

    // Add balance to user wallet
    $nabikarana = "UPDATE shonu_kaichila SET motta = ROUND(motta + " . $motta . ", 2) WHERE balakedara = '" . $shonuid . "'";
    if (!$conn->query($nabikarana)) {
        logError("Balance update error for UID {$shonuid}: " . mysqli_error($conn));
        echo "FAIL";
        exit;
    }

    // Mark thevani status as 1 (Completed)
    $sql2 = mysqli_query($conn, "UPDATE thevani SET sthiti = '1' WHERE dharavahi = '" . $mchOrderNo . "'");
    if (!$sql2) {
        logError("thevani status update error for Order {$mchOrderNo}: " . mysqli_error($conn));
        echo "FAIL";
        exit;
    }

    logError("Order $mchOrderNo successfully processed. Added {$motta} to user UID {$shonuid}.");
} else {
    logError("Order $mchOrderNo already processed or not found in thevani with status 0.");
}

echo "SUCCESS";
?>
