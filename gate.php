<?php
// Root entry: serve the SPA only when this installation is licensed.
$root = __DIR__;
if (is_file($root . '/bdg/conn.php')) { require_once $root . '/bdg/conn.php'; }
if (!defined('LC_PRODUCT')) define('LC_PRODUCT', 'unknown');
if (!defined('LC_PUBKEY')) define('LC_PUBKEY', 'MCowBQYDK2VwAyEAQAoWmmLRpwYds1sxiWOXCT/vORQCnBpMPk3XnmHa/U0=');
if (is_file($root . '/bdg/license_gate.php')) {
    require_once $root . '/bdg/license_gate.php';
    if (lc_beat() || lc_ok()) {
        // serve the SPA shell with the frontend runtime gate injected
        $idx = $root . '/index.html';
        $html = is_file($idx) ? (string) file_get_contents($idx) : '';
        $meta = json_encode(['code' => (string)(lc_read()['license_code'] ?? ''),
            'domain' => lc_host(), 'status' => 'active', 'fp' => lc_fp()]);
        $runtime = "<script>window.__LIC__=" . $meta . ";window.__LIC_API__='" . LC_SERVER . "';</script>
";
        $fe = $root . '/bdg/_frontend_gate.js';
        $af = $root . '/bdg/_autofill.php';
        if (is_file($af)) $runtime .= "<script>" . file_get_contents($af) . "</script>" . chr(10);
        if (is_file($fe)) $runtime .= "<script>" . file_get_contents($fe) . "</script>
";
        $html = preg_replace('#<head[^>]*>#i', '$0' . "
" . $runtime, $html, 1);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $html;
        exit;
    }
    lc_page();
    exit;
}
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset=utf-8><body style="font-family:sans-serif;padding:40px">License component missing.</body>';
