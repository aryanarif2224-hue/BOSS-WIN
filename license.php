<?php
// Standalone activation page (no DB config needed to render).
$cfg = __DIR__ . '/bdg/conn.php';
if (is_file(__DIR__ . '/bdg/license_gate.php')) {
    if (is_file($cfg)) { require_once $cfg; }
    if (!defined('LC_PRODUCT')) define('LC_PRODUCT', 'raxi-clone');
    if (!defined('LC_PUBKEY')) define('LC_PUBKEY', 'MCowBQYDK2VwAyEAQAoWmmLRpwYds1sxiWOXCT/vORQCnBpMPk3XnmHa/U0=');
    require_once __DIR__ . '/bdg/license_gate.php';
    lc_page();
    exit;
}
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset=utf-8><body style="font-family:sans-serif;padding:40px">License component missing.</body>';
