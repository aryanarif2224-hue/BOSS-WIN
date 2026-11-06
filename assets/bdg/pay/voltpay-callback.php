<?php
// voltpay-callback.php - VoltPay webhook handler for bdgwin
// Credits user wallet (shonu_kaichila.motta) on SUCCESS, idempotent.

@ob_start();
header('Content-Type: application/json; charset=utf-8');

include "/bdg/bdg/conn.php";
$config = include __DIR__ . '/voltpay-config.php';

function vlog($msg) {
    global $config;
    $f = $config['LOG_FILE'] ?? '/tmp/voltpay_integration.log';
    file_put_contents($f, date('c') . " | " . $msg . PHP_EOL, FILE_APPEND);
}
function vresp($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
vlog("CALLBACK len=" . strlen($raw) . " | " . substr($raw, 0, 2000));

$data = json_decode($raw, true);
if (!is_array($data)) {
    // not JSON — could be browser hitting the URL; acknowledge
    vresp(['status' => 'ok', 'message' => 'received']);
}

// VoltPay webhook fields: order_id, status, merchant_order_no, amount, currency, txid/utr
$orderId       = isset($data['order_id']) ? (string)$data['order_id'] : (isset($data['orderId']) ? (string)$data['orderId'] : '');
$merchantOrder = isset($data['merchant_order_no']) ? (string)$data['merchant_order_no'] : (isset($data['merchantOrder']) ? (string)$data['merchantOrder'] : '');
$status        = isset($data['status']) ? strtolower(trim($data['status'])) : '';
$amount        = isset($data['amount']) ? (float)$data['amount'] : 0.0;

if (!$merchantOrder && !$orderId) {
    vlog("Callback missing order id. Payload: " . json_encode($data));
    vresp(['status' => 'error', 'message' => 'Missing order id']);
}

$lookupKey = $merchantOrder ?: $orderId;

// Find transaction by dharavahi (merchant_order_no) — fallback to ullekha (order ref)
$found = false;
$balakedara = null;
$motta_db = 0.0;
$sthiti_db = null;
if ($stmt = $conn->prepare("SELECT balakedara, motta, sthiti FROM thevani WHERE dharavahi = ? LIMIT 1")) {
    $stmt->bind_param('s', $lookupKey);
    $stmt->execute();
    $stmt->bind_result($balakedara, $motta_db, $sthiti_db);
    $found = $stmt->fetch();
    $stmt->close();
}
if (!$found) {
    vlog("Order not found for {$lookupKey}. Payload: " . json_encode($data));
    vresp(['status' => 'error', 'message' => 'Order not found']);
}

$isSuccess = in_array($status, ['success', 'SUCCESS', 'completed', 'COMPLETED', 'paid', 'PAID']);

// Idempotency: already paid + success → acknowledge
if ((int)$sthiti_db === 1 && $isSuccess) {
    vlog("Duplicate success callback ignored for {$lookupKey}");
    vresp(['status' => 'ok', 'message' => 'Already processed']);
}

if ($isSuccess) {
    $conn->begin_transaction();
    try {
        // mark thevani paid: ullekha=orderId, duravani=status ref, sthiti=1
        if ($up = $conn->prepare("UPDATE thevani SET ullekha = ?, duravani = ?, sthiti = 1 WHERE dharavahi = ? LIMIT 1")) {
            $ul = $orderId ?: $merchantOrder;
            $dur = $merchantOrder ?: $orderId;
            $up->bind_param('sss', $ul, $dur, $lookupKey);
            $up->execute();
            $up->close();
        }

        // credit user wallet by stored amount
        if ($up2 = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ? LIMIT 1")) {
            $creditAmount = (float)$motta_db;
            $up2->bind_param('ds', $creditAmount, $balakedara);
            $up2->execute();
            $affected = $up2->affected_rows;
            $up2->close();
            if ($affected === 0) {
                vlog("WARN: shonu_kaichila not updated (balakedara={$balakedara})");
            }
        }

        $conn->commit();
        vlog("SUCCESS processed {$lookupKey} credited={$motta_db} to balakedara={$balakedara}");
        vresp(['status' => 'ok', 'message' => 'Callback received successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        vlog("ERROR processing success: " . $e->getMessage());
        vresp(['status' => 'error', 'message' => 'Internal error']);
    }
}

// failed
if (in_array($status, ['failed', 'FAILED', 'cancelled', 'CANCELLED', 'expired', 'EXPIRED'])) {
    if ($up = $conn->prepare("UPDATE thevani SET pavatiaidi = 3, ullekha = ? WHERE dharavahi = ? LIMIT 1")) {
        $ul = $orderId ?: $merchantOrder;
        $up->bind_param('ss', $ul, $lookupKey);
        $up->execute();
        $up->close();
    }
    vlog("STATUS {$status} for {$lookupKey}");
    vresp(['status' => 'ok', 'message' => "Callback noted: {$status}"]);
}

// other statuses: acknowledge
vlog("Unhandled status '{$status}' for {$lookupKey}");
vresp(['status' => 'ok', 'message' => "Unhandled status '{$status}' ignored"]);
