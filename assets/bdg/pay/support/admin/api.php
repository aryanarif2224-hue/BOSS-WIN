<?php
/* api.php — JSON action dispatcher for the ADMIN panel (thin router).
 * Each module lives in _mod/<name>.php and registers actions into $ROUTES.
 * Auth + CSRF + RBAC + audit are enforced here / inside modules. */
require_once __DIR__ . '/_core.php';

// anti-clone: reject calls whose Origin/Referer host isn't ours
$refsrc = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($refsrc !== '') {
    $rh = parse_url($refsrc, PHP_URL_HOST);
    if ($rh !== null && strcasecmp($rh, ALLOWED_HOST) !== 0) {
        json_out(['ok' => false, 'error' => 'Forbidden origin'], 403);
    }
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$action = (string)($in['action'] ?? ($_GET['action'] ?? ''));

/* $ROUTES: action => [handlerFn, perm|null, isWrite]
 * perm null = any logged-in admin; isWrite = requires CSRF. */
$ROUTES = [];
$GLOBALS['IN'] = $in;

// public (no session) actions handled first
require __DIR__ . '/_mod/auth.php';
if (isset($AUTH_ACTIONS[$action])) { $AUTH_ACTIONS[$action]($conn, $in); exit(); }

// everything else needs a session
$me = require_login_json();

// load module route tables
foreach (['session','dashboard','escalations','withdrawals','deposits','users','games','staff','settings','audit','site'] as $mod) {
    $f = __DIR__ . '/_mod/' . $mod . '.php';
    if (is_file($f)) { $reg = require $f; if (is_array($reg)) $ROUTES += $reg; }
}

if (!isset($ROUTES[$action])) json_out(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);

[$fn, $perm, $isWrite] = array_pad($ROUTES[$action], 3, null);
if ($isWrite) csrf_check();
if ($perm) require_perm($perm);
$fn($conn, $in, $me);
