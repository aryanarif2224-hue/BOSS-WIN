<?php
/* index.php — serves the built React app, injecting the session boot payload
 * (CSRF token + the logged-in admin, if any) as window.__BOOT__. */
require_once __DIR__ . '/_core.php';

$csrf = csrf_token();
$a = current_admin();
$boot = [
    'csrf'  => $csrf,
    'admin' => $a ? [
        'id' => $a['id'], 'username' => $a['username'], 'name' => $a['name'],
        'is_super' => (int)$a['is_super'], 'perms' => $a['perms'] ?? [],
    ] : null,
    'perms_all' => $GLOBALS['PERMS'],
    'brand' => BRAND,
];

$file = __DIR__ . '/build/index.html';
if (!is_file($file)) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo "Admin app not built yet. Run: cd /raxi/support/admin-src && npm run build";
    exit();
}
$html = file_get_contents($file);

// inject boot payload just before the app's module script
$inject = '<script>window.__BOOT__=' . json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';
$html = preg_replace('/<script type="module"/', $inject . "\n<script type=\"module\"", $html, 1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $html;
