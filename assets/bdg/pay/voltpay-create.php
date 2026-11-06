<?php
// voltpay-create.php - VoltPay deposit order creator for bdgwin (INR + USDT)
// Called from frontend via redirect: pay/voltpay-create.php?amount=X&tyid=Y&uid=Z&sign=...&urlInfo=...
// tyid: payment type id (2 = INR, USDT type handled via amount/urlInfo or tyid)

@ob_start();
header('Content-Type: application/json; charset=utf-8');

include "/bdg/bdg/conn.php";   // defines $conn
$config = include __DIR__ . '/voltpay-config.php';

function voltlog($msg) {
    global $config;
    $f = $config['LOG_FILE'] ?? '/tmp/voltpay_integration.log';
    file_put_contents($f, date('c') . " | " . $msg . PHP_EOL, FILE_APPEND);
}
function volt_resp($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$required = ['amount', 'tyid', 'uid'];
foreach ($required as $r) {
    if (!isset($_GET[$r]) || $_GET[$r] === '') {
        volt_resp(['status' => false, 'message' => "Missing param: $r"]);
    }
}

$amountRaw = (string)$_GET['amount'];
$tyid      = (int)$_GET['tyid'];
$uid       = (int)$_GET['uid'];

// Normalize amount
$amount = preg_replace('/[^\d.]/', '', $amountRaw);
if ($amount === '' || !is_numeric($amount)) $amount = '0.00';
$parts = explode('.', $amount, 2);
if (count($parts) === 1) $amount = $parts[0] . '.00';
else {
    $dec = substr($parts[1], 0, 2);
    $amount = $parts[0] . '.' . str_pad($dec, 2, '0');
}

// Determine currency: USDT when tyid matches USDT pay type (from GetRechargeTypes)
// Convention used by frontend: USDT option passes tyid that maps to USDT.
// Here we accept a urlInfo flag too: urlInfo containing "usdt" forces USDT.
$currency = 'INR';
$urlInfo = isset($_GET['urlInfo']) ? strtolower((string)$_GET['urlInfo']) : '';
if ($urlInfo === 'usdt' || $urlInfo === 'usdtpay' || stripos($urlInfo, 'usdt') !== false) {
    $currency = 'USDT';
}
// Also honor tyid==5 (USDT channel) convention + bdgwin USDT payTypeIDs (2123 UPAY-USDT, 2190 UU-USDT)
if ($tyid == 5 || $tyid == 1030 || $tyid == 1124 || $tyid == 2123 || $tyid == 2190) {
    $currency = 'USDT';
}

// ---- user lookup ----
$user = null;
if ($stmt = $conn->prepare("SELECT id, mobile FROM shonu_subjects WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
}
if (!$user) {
    volt_resp(['status' => false, 'message' => 'Invalid uid or missing user']);
}
$mobile = $user['mobile'];

// ---- demo fast-credit (like bondpay/lgpay pattern) ----
$existsDemo = false;
if ($stmt = $conn->prepare("SELECT 1 FROM demo WHERE balakedara = ? LIMIT 1")) {
    $uidStr = (string)$uid;
    $stmt->bind_param('s', $uidStr);
    $stmt->execute();
    $stmt->store_result();
    $existsDemo = $stmt->num_rows > 0;
    $stmt->close();
}

// For USDT we credit INR equivalent (rate conversion)
$creditAmount = (float)$amount;
if ($currency === 'USDT') {
    $creditAmount = round((float)$amount * (float)$config['USDT_RATE'], 2);
}

if ($existsDemo) {
    $serial = date("Ymd") . time() . random_int(100000, 999900);
    $createdate = date("Y-m-d H:i:s");
    $payName = $currency === 'USDT' ? 'VoltPay-USDT' : 'VoltPay-INR';

    $mottaFloat = $creditAmount;
    $uidStr = (string)$uid;
    if ($ins = $conn->prepare("INSERT INTO thevani (balakedara, motta, dharavahi, mula, ullekha, duravani, ekikrtapavati, dinankavannuracisi, madari, pavatiaidi, sthiti) VALUES (?,?,?,?,?,?,?,?,?,?,?)")) {
        $ullekha = 'DEMO'; $duravani = $mobile; $ekikrtapavati = '1005'; $madari = '1005'; $pavatiaidi = 2; $sthiti = 1;
        $ins->bind_param('sdsssssssss', $uidStr, $mottaFloat, $serial, $payName, $ullekha, $duravani, $ekikrtapavati, $createdate, $madari, $pavatiaidi, $sthiti);
        $ins->execute();
        $ins->close();
    }
    if ($upd = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ?")) {
        $upd->bind_param('ds', $mottaFloat, $uidStr);
        $upd->execute();
        $upd->close();
    }
    header('Location: ' . $config['RETURN_URL']);
    exit;
}

// ---- create VoltPay order ----
$merchant_order_no = 'VP_' . date("Ymd") . '_' . time() . '_' . random_int(1000, 9999);
$orderPayload = [
    'amount' => (float)$amount,
    'currency' => $currency,
    'merchant_order_no' => $merchant_order_no,
    'merchant_id' => $config['VOLT_MERCHANT_ID'],
    'callback_url' => $config['NOTIFY_URL'],
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, rtrim($config['VOLT_BASE_URL'], '/') . $config['VOLT_CREATE_PATH']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $config['VOLT_API_KEY'],
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

voltlog("CreateOrder {$merchant_order_no} {$currency} amount={$amount} http={$httpcode} err={$curlErr} resp=" . substr($response, 0, 800));

if ($curlErr) {
    volt_resp(['status' => false, 'message' => 'Gateway request error', 'debug' => $curlErr]);
}
$resData = json_decode($response, true);
if (!is_array($resData)) {
    volt_resp(['status' => false, 'message' => 'Invalid gateway response', 'debug' => substr($response, 0, 800)]);
}

// Success check: voltpay returns {status:true,...} or {success:true,...}
$ok = (!empty($resData['status']) && $resData['status'] === true) ||
      (!empty($resData['success']) && $resData['success'] === true);
$paymentUrl = $resData['payment_url'] ?? ($resData['result']['payment_url'] ?? '') ?? '';
// old/new response shapes: {payment_url} | {result:{payment_url}} | {success:{payment_url}}
if (!$paymentUrl && isset($resData['result']['payment_url'])) $paymentUrl = $resData['result']['payment_url'];
if (!$paymentUrl && isset($resData['success']['payment_url'])) $paymentUrl = $resData['success']['payment_url'];
if (!$paymentUrl && isset($resData['paymentUrl'])) $paymentUrl = $resData['paymentUrl'];
if (!$paymentUrl && isset($resData['result']['paymentUrl'])) $paymentUrl = $resData['result']['paymentUrl'];

if ($httpcode >= 200 && $httpcode < 300 && $ok && $paymentUrl) {
    $createdate = date("Y-m-d H:i:s");
    $payid = 2;
    $srl = $merchant_order_no;
    $refNum = $resData['orderNo'] ?? $resData['result']['orderId'] ?? $merchant_order_no;
    $payName = $currency === 'USDT' ? 'VoltPay-USDT' : 'VoltPay-INR';

    // DB value: for USDT store INR-equivalent (same as lgpay pattern)
    $dbAmount = $creditAmount;

    $balakedara = (string)$uid;
    $payNameVar = (string)$tyid;
    $ekik = '1005';
    $pav = 2;

    $insertSql = "INSERT INTO thevani (payid, balakedara, motta, dharavahi, mula, ullekha, duravani, ekikrtapavati, dinankavannuracisi, madari, pavatiaidi, sthiti) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    if ($ins = $conn->prepare($insertSql)) {
        $typeString = 'isdsssssssii';
        $sthiti = 0; // pending
        $ins->bind_param(
            $typeString,
            $payid, $balakedara, $dbAmount, $srl, $payNameVar,
            $refNum, $mobile, $payName, $createdate, $ekik, $pav, $sthiti
        );
        if (!$ins->execute()) {
            voltlog("DB INSERT failed: " . $ins->error);
        }
        $ins->close();
    }

    header('Location: ' . $paymentUrl);
    exit;
}

volt_resp(['status' => false, 'message' => $resData['message'] ?? 'Failed to create order', 'debug' => $resData]);
