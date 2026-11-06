<?php
@ob_start();

// Ensure DB connection is loaded
if (file_exists(__DIR__ . '/../serive/samparka.php')) {
    include_once __DIR__ . '/../serive/samparka.php';
} elseif (file_exists('/raxi/bdg/conn.php')) {
    include_once '/raxi/bdg/conn.php';
}

$config = require __DIR__ . '/config.php';
if (!isset($conn) || !$conn) {
    $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
}

function zyralog($msg) {
    $log = __DIR__ . '/zyrapay_log.txt';
    @file_put_contents($log, date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL, FILE_APPEND);
}

function normalize_amount($a){
    return number_format((float)$a, 2, '.', '');
}

$amount = normalize_amount($_GET['amount'] ?? $_POST['amount'] ?? $_GET['amt'] ?? $_POST['amt'] ?? 0);
$uid    = (int)($_GET['uid'] ?? $_POST['uid'] ?? $_GET['userId'] ?? $_POST['userId'] ?? 0);
$tyid   = htmlspecialchars($_GET['tyid'] ?? $_POST['tyid'] ?? '1023');

if ($amount <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'Invalid amount']);
    exit;
}

if ($uid <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'Invalid or missing user ID']);
    exit;
}

/* Get user mobile */
$mobile = null;
if ($stmt = $conn->prepare("SELECT mobile FROM shonu_subjects WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($mobile);
    $stmt->fetch();
    $stmt->close();
}

if (!$mobile) {
    $mobile = '98' . str_pad((string)$uid, 8, '0', STR_PAD_LEFT);
}

/* Create unique merchant order no */
$merchant_order_no = 'ORD_' . time() . rand(1000, 9999);

/* Payload for Zyra Pay */
$payload = [
    "merchant_id" => $config['NOVAX_MERCHANT_ID'],
    "order_id"    => $merchant_order_no,
    "amount"      => $amount,
    "currency"    => "INR",
    "pay_type"    => "UPI",
    "notify_url"  => $config['NOTIFY_URL'],
    "return_url"  => $config['RETURN_URL'],
    "timestamp"   => time()
];

/* Signature generation */
ksort($payload);
$signStr = "";
foreach ($payload as $k => $v) {
    if ($v !== '' && $v !== null) {
        $signStr .= $k . "=" . $v . "&";
    }
}
$signStr .= "key=" . $config['NOVAX_API_SECRET'];
$payload['sign'] = strtolower(md5($signStr));

// cURL Request to Zyra Pay
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, rtrim($config['NOVAX_BASE_URL'], '/') . $config['NOVAX_CREATE_PATH']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

zyralog("CreateOrder {$merchant_order_no} uid={$uid} amount={$amount} http={$httpcode} curlErr={$curlErr} resp=" . substr($response, 0, 500));

$res = json_decode($response, true);

// Success condition from Zyra Pay
if (isset($res['code']) && $res['code'] == 0 && !empty($res['data']['payment_url'])) {
    $createdate = date("Y-m-d H:i:s");
    $payid = 2; // Payment Gateway ID
    $balakedara = (string)$uid;
    $mottaFloat = (float)$amount;
    $srl = $merchant_order_no;
    $refNum = $res['data']['novax_order_id'] ?? $merchant_order_no;
    $upi = 'Zyra Pay';
    $sthiti = 0; // 0 = Pending
    $ekik = '1005';
    $pav = 2;

    $sql = "INSERT INTO thevani 
    (payid, balakedara, motta, dharavahi, mula, ullekha, duravani, ekikrtapavati, dinankavannuracisi, madari, pavatiaidi, sthiti)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $mulaVar = (string)$tyid;
        $stmt->bind_param(
            "isdsssssssii",
            $payid,
            $balakedara,
            $mottaFloat,
            $srl,
            $mulaVar,
            $refNum,
            $mobile,
            $upi,
            $createdate,
            $ekik,
            $pav,
            $sthiti
        );
        $stmt->execute();
        $stmt->close();
    } else {
        zyralog("thevani insert prepare failed: " . $conn->error);
    }

    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => true,
            'message' => 'Success',
            'order_id' => $merchant_order_no,
            'payment_url' => $res['data']['payment_url']
        ]);
        exit;
    }

    // Redirect user to the Zyra Pay checkout page
    header("Location: " . $res['data']['payment_url']);
    exit;
}

// Error handling
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => false, 'message' => 'Zyra Pay Gateway Error', 'response' => $res]);
?>
