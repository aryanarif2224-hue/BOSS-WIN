<?php
// Shreewin Hack / Traffic Tool Common Functions
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

function hack_json($status, $message, $data = null, $extra = []) {
    $res = array_merge([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'server_time' => date('Y-m-d H:i:s')
    ], $extra);
    http_response_code($status === 200 ? 200 : ($status === 401 ? 401 : ($status === 400 ? 400 : 200)));
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_request_data() {
    $body = file_get_contents('php://input');
    $json = json_decode($body, true);
    if (is_array($json)) {
        return $json;
    }
    return $_REQUEST;
}

function hack_authenticate($conn) {
    $authHeader = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    $token = '';
    if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($_REQUEST['token'])) {
        $token = $_REQUEST['token'];
    }

    if (empty($token)) {
        hack_json(401, 'Unauthorized: Missing Authorization Bearer token');
    }

    $tokenEsc = $conn->real_escape_string($token);
    $q = $conn->query("SELECT * FROM hack_users WHERE auth_token = '$tokenEsc' AND is_active = 1 AND token_expires_at > NOW() LIMIT 1");
    if (!$q || $q->num_rows === 0) {
        hack_json(401, 'Unauthorized: Invalid or expired token');
    }

    return $q->fetch_assoc();
}

function get_player_stats($conn, $mobile) {
    $mobile = trim((string)$mobile);
    if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') {
        $mobile = substr($mobile, 2);
    }
    $mobileEsc = $conn->real_escape_string($mobile);

    $userQ = $conn->query("SELECT id, mobile, is_online, shonullgnt, last_logout, akshinak, createdate FROM shonu_subjects WHERE mobile = '$mobileEsc' LIMIT 1");
    if (!$userQ || $userQ->num_rows === 0) {
        return null;
    }
    $user = $userQ->fetch_assoc();
    $userId = (int)$user['id'];

    // Wallet balance
    $balQ = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = $userId LIMIT 1");
    $walletBal = ($balQ && $r = $balQ->fetch_assoc()) ? (float)$r['motta'] : 0.0;

    // Deposits (thevani: sthiti = 1)
    $depQ = $conn->query("SELECT COALESCE(SUM(motta), 0) AS total_dep, COUNT(*) AS dep_cnt, MAX(dinankavannuracisi) AS last_dep FROM thevani WHERE balakedara = $userId AND sthiti = 1");
    $depData = ($depQ && $r = $depQ->fetch_assoc()) ? $r : ['total_dep' => 0, 'dep_cnt' => 0, 'last_dep' => null];

    // SaaS bets
    $saasBet = 0.0; $saasWin = 0.0; $saasCnt = 0;
    $sbQ = $conn->query("SELECT COALESCE(SUM(amount), 0) AS b_sum, COALESCE(SUM(win_amount), 0) AS w_sum, COUNT(*) AS cnt FROM saas_bets WHERE user_id = $userId");
    if ($sbQ && $r = $sbQ->fetch_assoc()) {
        $saasBet = (float)$r['b_sum'];
        $saasWin = (float)$r['w_sum'];
        $saasCnt = (int)$r['cnt'];
    }

    // 3rd-party game bets
    $thirdBet = 0.0; $thirdWin = 0.0; $thirdCnt = 0;
    $tbQ = $conn->query("SELECT COALESCE(SUM(bet_amount), 0) AS b_sum, COALESCE(SUM(win_amount), 0) AS w_sum, COUNT(*) AS cnt FROM game_bet_logs WHERE user_id = $userId");
    if ($tbQ && $r = $tbQ->fetch_assoc()) {
        $thirdBet = (float)$r['b_sum'];
        $thirdWin = (float)$r['w_sum'];
        $thirdCnt = (int)$r['cnt'];
    }

    // Legacy lottery bets
    $lotteryBet = 0.0; $lotteryCnt = 0;
    $lbQ = $conn->query("SELECT COALESCE(SUM(ketebida), 0) AS b_sum, COUNT(*) AS cnt FROM bajikattuttate WHERE byabaharkarta = $userId");
    if ($lbQ && $r = $lbQ->fetch_assoc()) {
        $lotteryBet = (float)$r['b_sum'];
        $lotteryCnt = (int)$r['cnt'];
    }

    $totalBetAmount = round($saasBet + $thirdBet + $lotteryBet, 2);
    $totalWinAmount = round($saasWin + $thirdWin, 2);
    $totalBetCount = $saasCnt + $thirdCnt + $lotteryCnt;

    $isLoggedIn = (!empty($user['id']));

    return [
        'user_id' => $userId,
        'mobile' => $user['mobile'],
        'is_logged_in' => $isLoggedIn,
        'registered_at' => $user['createdate'],
        'last_login_at' => $user['shonullgnt'],
        'last_logout_at' => $user['last_logout'],
        'wallet_balance' => $walletBal,
        'deposit' => [
            'total_deposit' => (float)$depData['total_dep'],
            'deposit_count' => (int)$depData['dep_cnt'],
            'last_deposit_at' => $depData['last_dep'],
            'has_deposited' => (float)$depData['total_dep'] > 0
        ],
        'betting' => [
            'total_bet_amount' => $totalBetAmount,
            'total_win_amount' => $totalWinAmount,
            'total_bet_count' => $totalBetCount,
            'has_betted' => $totalBetCount > 0
        ]
    ];
}
