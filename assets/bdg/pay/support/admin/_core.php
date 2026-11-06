<?php
/* _core.php — shared bootstrap + security foundation for the ADMIN panel.
 * Every admin endpoint includes this FIRST. Never output before it.
 *
 * Separate from the agent console: admins have money power (approve withdrawals,
 * credit deposits, adjust balances) plus staff/permission management. Agents do NOT.
 *
 * Data model (main site DB bdgwin_ab888):
 *   shonu_subjects  users (id, mobile, email, password, pwd, status, account_frozen, akshinak=token, createdate)
 *   shonu_kaichila  wallet (balakedara=uid, motta=balance, bonus, rebet)
 *   khate           bank/USDT accounts
 *   thevani         deposits    (balakedara=uid, motta, dharavahi=orderno, ekikrtapavati=UTR, sthiti, dinankavannuracisi)
 *   hintegedukolli  withdrawals (balakedara=uid, motta, dharavahi, sthiti, remarks, dinankavannuracisi)
 *   saas_bets/saas_results   SaaS game bets + draws
 *   support_workorders/support_comments   tickets
 *   support_admins/support_admin_log/support_site_settings   (this panel)
 */

if (php_sapi_name() !== 'cli') {
    @ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
}

require_once '/raxi/bdg/conn.php';   // $conn (mysqli) bdgwin_ab888
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

// ---- config -----------------------------------------------------------------
const ADMIN_BASE   = '/bdgscriptwala@admin';   // secret mount path
const BRAND        = 'Raxi Ops';        // hardcoded product branding
const ALLOWED_HOST = 'support.raxi.nextapi.online';
const SESSION_IDLE = 8 * 3600;
const ACTIVATION_KEY = 'RAXI-ADMIN-2026'; // V2 first-login activation code                 // auto-logout after 8h idle
const LOGIN_MAXTRY = 6;
const LOGIN_LOCK   = 900;                       // 15 min

// permission keys (order = sidebar order). '*' or is_super = all.
$GLOBALS['PERMS'] = [
    'dashboard'   => 'Dashboard',
    'escalations' => 'Escalations',
    'withdrawals' => 'Withdrawals',
    'deposits'    => 'Deposits',
    'users'       => 'Users',
    'games'       => 'Games',
    'banners'     => 'Banners',
    'settings'    => 'Site Settings',
    'agents'      => 'Agents',
    'admins'      => 'Admins',
    'audit'       => 'Audit Log',
];

// ---- session (hardened) ------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: frame-ancestors 'none'");

    session_name('ADMINSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => ADMIN_BASE, 'domain' => '',
        'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();

    $now = time();
    $ua  = substr(hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);
    if (!empty($_SESSION['admin'])) {
        if (($_SESSION['ua'] ?? '') !== $ua || ($now - ($_SESSION['seen'] ?? 0)) > SESSION_IDLE) {
            admin_logout();
        } else {
            // single-session: has this account logged in elsewhere? (token rotated)
            $aid = (int)$_SESSION['admin']['id'];
            $ck = $conn->prepare("SELECT session_token, is_active, is_super, permissions FROM support_admins WHERE id=?");
            $ck->bind_param('i', $aid); $ck->execute();
            $row = $ck->get_result()->fetch_assoc(); $ck->close();
            $dbtok = (string)($row['session_token'] ?? '');
            if (!$row || (int)$row['is_active'] !== 1) {
                admin_logout(); $GLOBALS['ADMIN_KICKED'] = true;
            } elseif ($dbtok !== '' && $dbtok !== ($_SESSION['stok'] ?? '')) {
                admin_logout(); $GLOBALS['ADMIN_KICKED'] = true;
            } else {
                // refresh live perms each request (so permission edits take effect immediately)
                $_SESSION['admin']['is_super'] = (int)$row['is_super'];
                $_SESSION['admin']['perms']    = json_decode($row['permissions'] ?? '[]', true) ?: [];
                $_SESSION['seen'] = $now;
            }
        }
    }
}

// ---- helpers -----------------------------------------------------------------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function client_ip() { return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''; }
function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit();
}
function redirect($to) { header('Location: ' . $to); exit(); }

// CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check() {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
        json_out(['ok' => false, 'error' => 'Bad CSRF token'], 419);
    }
}

// auth
function current_admin() { return $_SESSION['admin'] ?? null; }
function require_login_json() {
    if (!empty($GLOBALS['ADMIN_KICKED'])) json_out(['ok' => false, 'error' => 'Session ended', 'relogin' => true], 401);
    if (empty($_SESSION['admin'])) json_out(['ok' => false, 'error' => 'auth', 'relogin' => true], 401);
    return $_SESSION['admin'];
}
// RBAC
function can($key) {
    $a = current_admin();
    if (!$a) return false;
    if (!empty($a['is_super'])) return true;
    $p = $a['perms'] ?? [];
    return in_array('*', $p, true) || in_array($key, $p, true);
}
function require_perm($key) {
    if (!can($key)) json_out(['ok' => false, 'error' => 'You do not have permission for this'], 403);
}
function admin_login_set($row, $token = '') {
    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id'       => (int)$row['id'],
        'username' => $row['username'],
        'name'     => $row['name'] !== '' ? $row['name'] : $row['username'],
        'is_super' => (int)$row['is_super'],
        'perms'    => json_decode($row['permissions'] ?? '[]', true) ?: [],
    ];
    $_SESSION['ua']   = substr(hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);
    $_SESSION['seen'] = time();
    $_SESSION['stok'] = $token;
    csrf_token();
}
function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
}

// audit — every sensitive/money action lands here
function alog($action, $detail = '', $target_uid = 0, $order_no = '', $amount = 0) {
    global $conn;
    $a   = current_admin();
    $aid = (int)($a['id'] ?? 0);
    $an  = (string)($a['name'] ?? 'system');
    $ip  = client_ip();
    $now = date('Y-m-d H:i:s');
    $tuid = (int)$target_uid; $amt = (float)$amount;
    $st = $conn->prepare("INSERT INTO support_admin_log
            (admin_id,admin_name,action,target_user_id,order_no,amount,detail,ip,created_at)
            VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param('issisdsss', $aid, $an, $action, $tuid, $order_no, $amt, $detail, $ip, $now);
    $st->execute(); $st->close();
}

// ---- money / user helpers ---------------------------------------------------
// current wallet snapshot
function wallet_of($uid) {
    global $conn;
    $st = $conn->prepare("SELECT motta,bonus,rebet FROM shonu_kaichila WHERE balakedara=? ORDER BY kramasankhye DESC LIMIT 1");
    $st->bind_param('i', $uid); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r ?: ['motta' => '0.00', 'bonus' => '0.00', 'rebet' => '0.00'];
}
// atomically add/subtract main balance (delta may be negative). Returns [ok,newbal,error]
function wallet_adjust($uid, $delta) {
    global $conn;
    $uid = (int)$uid; $delta = (float)$delta;
    // ensure wallet row exists
    $chk = $conn->query("SELECT kramasankhye FROM shonu_kaichila WHERE balakedara=$uid ORDER BY kramasankhye DESC LIMIT 1");
    if (!$chk || $chk->num_rows === 0) {
        $conn->query("INSERT INTO shonu_kaichila (balakedara, motta, bonus, rebet) VALUES ($uid, '0', '0', '0') ON DUPLICATE KEY UPDATE balakedara=balakedara");
    }
    if ($delta < 0) {
        // guard against negative balance
        $st = $conn->prepare("UPDATE shonu_kaichila SET motta = ROUND(CAST(motta AS DECIMAL(15,2)) + ?, 2) WHERE balakedara=? AND CAST(motta AS DECIMAL(15,2)) + ? >= 0");
        $st->bind_param('did', $delta, $uid, $delta); $st->execute();
        $aff = $st->affected_rows; $st->close();
        if ($aff < 1) return [false, null, 'Insufficient balance for this deduction'];
    } else {
        $st = $conn->prepare("UPDATE shonu_kaichila SET motta = ROUND(CAST(motta AS DECIMAL(15,2)) + ?, 2) WHERE balakedara=?");
        $st->bind_param('di', $delta, $uid); $st->execute(); $st->close();
    }
    $w = wallet_of($uid);
    return [true, $w['motta'], null];
}
function wallet_set($uid, $targetAmount) {
    global $conn;
    $uid = (int)$uid; $targetAmount = max(0, round((float)$targetAmount, 2));
    $conn->query("INSERT INTO shonu_kaichila (balakedara, motta, bonus, rebet) VALUES ($uid, '$targetAmount', '0', '0') ON DUPLICATE KEY UPDATE motta = '$targetAmount'");
    $w = wallet_of($uid);
    return [true, $w['motta'], null];
}
function user_exists($uid) {
    global $conn;
    $uid = (int)$uid;
    $r = $conn->query("SELECT id FROM shonu_subjects WHERE id=$uid LIMIT 1");
    return $r && $r->num_rows === 1;
}
function tbl_exists($conn, $t) {
    $q = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $q && $q->num_rows > 0;
}

// "3h ago"
function ago($dt) {
    if (!$dt) return '';
    $s = time() - strtotime($dt); if ($s < 0) $s = 0;
    if ($s < 60) return $s . 's ago';
    if ($s < 3600) return floor($s / 60) . 'm ago';
    if ($s < 86400) return floor($s / 3600) . 'h ago';
    return floor($s / 86400) . 'd ago';
}
