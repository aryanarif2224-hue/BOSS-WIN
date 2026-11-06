<?php
/**
 * NextAPI Seamless Webhook Callback Handler
 */
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Don't let mysqli throw fatals (PHP 8 default) — a missing table must not 500 the callback.
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
header('Content-Type: application/json; charset=utf-8');

if (!isset($conn)) {
    $connCandidates = [
        __DIR__ . "/conn.php", __DIR__ . "/../conn.php", __DIR__ . "/../../conn.php",
        __DIR__ . "/admin/conn.php", __DIR__ . "/api/conn.php", __DIR__ . "/api/webapi/conn.php",
        __DIR__ . "/../admin/conn.php", __DIR__ . "/../../admin/conn.php",
    ];
    foreach ($connCandidates as $cc) { if (file_exists($cc)) { include_once $cc; break; } }
    if (!isset($conn) && defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
        $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    }
    if (!isset($conn) && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
}
if (!function_exists('is_jwt_valid')) {
    if (file_exists(__DIR__ . "/functions2.php")) include_once __DIR__ . "/functions2.php";
    elseif (file_exists(__DIR__ . "/../functions2.php")) include_once __DIR__ . "/../functions2.php";
    elseif (file_exists(__DIR__ . "/../../functions2.php")) include_once __DIR__ . "/../../functions2.php";
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['code' => 1, 'msg' => 'Method not allowed'], 405);
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (!$input || !is_array($input)) {
    sendResponse(['code' => 1, 'msg' => 'Invalid JSON input'], 400);
}

$user_id       = (string)($input['user_id'] ?? $input['playerId'] ?? '');
$game_code     = (string)($input['game_code'] ?? $input['gameCode'] ?? '');
$bet_amount    = floatval($input['bet_amount'] ?? $input['betAmount'] ?? 0);
$win_amount    = floatval($input['win_amount'] ?? $input['winAmount'] ?? 0);
$serial_number = (string)($input['serial_number'] ?? $input['serialNumber'] ?? '');
$game_round    = (string)($input['game_round'] ?? $input['gameRound'] ?? '');

if (empty($user_id)) {
    sendResponse(['code' => 1, 'msg' => 'Missing user_id'], 400);
}

if (!isset($conn)) {
    sendResponse(['code' => 1, 'msg' => 'Database connection missing'], 500);
}

// Ensure the bet-log table exists (self-provision so a fresh merchant DB never 500s).
@$conn->query("CREATE TABLE IF NOT EXISTS game_bet_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(255), vendor_code VARCHAR(64), game_code VARCHAR(64), game_name_en VARCHAR(160), bet_amount DECIMAL(15,2), win_amount DECIMAL(15,2), profit_loss DECIMAL(15,2), balance_before DECIMAL(15,2), balance_after DECIMAL(15,2), serial_number VARCHAR(160) UNIQUE, game_round VARCHAR(160), created_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Build a globally unique serial number to prevent slot collisions (e.g. Aviator slots '1'/'2')
$effective_serial = $serial_number;
if (!empty($game_round) && (strlen($serial_number) <= 4 || is_numeric($serial_number))) {
    $effective_serial = $game_round . '_' . $serial_number;
}

// 1. Check duplicate effective_serial
if (!empty($effective_serial)) {
    $chk = $conn->prepare("SELECT id FROM game_bet_logs WHERE serial_number = ?");
    if ($chk) {
        $chk->bind_param("s", $effective_serial);
        $chk->execute();
        $res = $chk->get_result();
        if ($res && $res->num_rows > 0) {
            $chk->close();
            $balQ = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
            $curBal = 0.0;
            if ($balQ) {
                $balQ->bind_param("s", $user_id);
                $balQ->execute();
                $bRes = $balQ->get_result();
                if ($bRow = $bRes->fetch_assoc()) { $curBal = floatval($bRow['motta']); }
                $balQ->close();
            }
            sendResponse(['code' => 0, 'msg' => 'Duplicate', 'balance' => $curBal, 'balance_inr' => $curBal]);
        }
        $chk->close();
    }
}

// 2. Fetch current balance
$current_balance = 0.0;
$bal_stmt = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
if ($bal_stmt) {
    $bal_stmt->bind_param("s", $user_id);
    $bal_stmt->execute();
    $bRes = $bal_stmt->get_result();
    if ($bRow = $bRes->fetch_assoc()) {
        $current_balance = floatval($bRow['motta']);
    } else {
        $bal_stmt->close();
        sendResponse(['code' => 1, 'msg' => 'User not found'], 404);
    }
    $bal_stmt->close();
}

$balance_change = -$bet_amount + $win_amount;
$new_balance    = $current_balance + $balance_change;
if ($new_balance < 0) {
    sendResponse(['code' => 1, 'msg' => 'Insufficient balance'], 400);
}

// 3. Update shonu_kaichila — newest row only (no UNIQUE on balakedara; the app
//    reads the newest row, so we must not fork it by inserting/updating blindly).
$upd = $conn->prepare("UPDATE shonu_kaichila SET motta = ? WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
if ($upd) {
    $new_balance_str = strval(round($new_balance, 2));
    $upd->bind_param("ss", $new_balance_str, $user_id);
    $upd->execute();
    $upd->close();
}

// 3b. 6-level upline commission on the third-party stake (parity with SaaS/native).
if ($bet_amount > 0 && is_numeric($user_id)) {
    if (!function_exists('sw_award_bet_commission')) {
        @include_once __DIR__ . '/commission.php';
    }
    if (function_exists('sw_award_bet_commission')) {
        $cref = 'T' . (int)$user_id . '-' . ($effective_serial !== '' ? $effective_serial : md5($game_code . $bet_amount . microtime(true)));
        sw_award_bet_commission($conn, (int)$user_id, $bet_amount, date('Y-m-d H:i:s'), $cref);
    }
}

// 4. Update argame_balances if table exists
$updAr = $conn->prepare("INSERT INTO argame_balances (user_id, balance, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE balance = ?, updated_at = NOW()");
if ($updAr) {
    $updAr->bind_param("sdd", $user_id, $new_balance, $new_balance);
    $updAr->execute();
    $updAr->close();
}

// 5. Log bet transaction
$vendor_code_input = (string)($input['vendor_code'] ?? $input['vendorCode'] ?? $input['provider'] ?? 'TBL');
$game_name_en = (string)($input['game_name'] ?? $input['gameName'] ?? $game_code);
$category = (string)($input['category'] ?? '');

// Look up from nextapi_games if available
if (tbl_exists($conn, 'nextapi_games')) {
    $gQ = $conn->prepare("SELECT name, provider, category, vendor_code FROM nextapi_games WHERE id = ? OR slug = ? OR vendor_id = ? LIMIT 1");
    if ($gQ) {
        $gQ->bind_param("sss", $game_code, $game_code, $game_code);
        $gQ->execute();
        $gRes = $gQ->get_result();
        if ($gRow = $gRes->fetch_assoc()) {
            if (!empty($gRow['name'])) $game_name_en = $gRow['name'];
            if (!empty($gRow['provider'])) $vendor_code_input = $gRow['provider'];
            if (!empty($gRow['category'])) $category = $gRow['category'];
        }
        $gQ->close();
    }
}

// Determine game_type and game_type_name
$catLower = strtolower($category);
$codeLower = strtolower($game_code);
if ($catLower === 'crash' || in_array($codeLower, ['aviator', 'mines', 'minespro', 'plinko', 'diver', 'chicken', 'luckyjet'])) {
    $game_type = 6;
    $game_type_name = 'smallgame';
} elseif ($catLower === 'live' || stripos($vendor_code_input, 'live') !== false) {
    $game_type = 1;
    $game_type_name = 'video';
} elseif ($catLower === 'fish') {
    $game_type = 3;
    $game_type_name = 'fish';
} elseif ($catLower === 'chess') {
    $game_type = 5;
    $game_type_name = 'chesscard';
} else {
    $game_type = 2;
    $game_type_name = 'electronic';
}

$pl = round($win_amount - $bet_amount, 2);
$vendor_id = 20;

$log_stmt = $conn->prepare("
    INSERT INTO game_bet_logs 
    (user_id, vendor_id, vendor_code, game_type, game_type_name, game_code, game_name_en, bet_amount, win_amount, profit_loss, balance_before, balance_after, serial_number, game_round, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
if ($log_stmt) {
    $log_stmt->bind_param("sisisssdddddss", $user_id, $vendor_id, $vendor_code_input, $game_type, $game_type_name, $game_code, $game_name_en, $bet_amount, $win_amount, $pl, $current_balance, $new_balance, $effective_serial, $game_round);
    $log_stmt->execute();
    $log_stmt->close();
}

sendResponse([
    'code' => 0,
    'msg'  => 'Success',
    'balance' => round($new_balance, 2),
    'balance_inr' => round($new_balance, 2)
]);
