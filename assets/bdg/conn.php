<?php

// ---- LICENSE GATE (domain-locked activation) ----
if (!defined('LC_SELF_HASH')) define('LC_SELF_HASH', 'c953c5b676e8150bc27ba1ba107a46e49edadf7d2b6882cd7349eb5fadcffcec');
if (!defined('LC_PRODUCT')) define('LC_PRODUCT', 'raxi-clone');
require_once __DIR__ . '/license_gate.php';









date_default_timezone_set('Asia/Kolkata');
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'raxi_user');
define('DB_PASSWORD', 'Raxi7x2k9m4p');
define('DB_NAME', 'raxi_ab888');
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if($conn == false){
    dir('Error: Cannot connect');
    Echo"Fail";
}

// ---- request audit log: trace which API calls actually reach the backend ----
if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    $__logDir = '/raxi/logs';
    if (!is_dir($__logDir)) @mkdir($__logDir, 0775, true);
    @file_put_contents(
        $__logDir . '/api.log',
        date('Y-m-d H:i:s') . ' ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' ' . $_SERVER['REQUEST_METHOD']
            . ' ' . substr($_SERVER['REQUEST_URI'], 0, 160)
            . ' auth=' . (!empty($_SERVER['HTTP_AUTHORIZATION']) ? 'Y' : 'N')
            . ' origin=' . (!empty($_SERVER['HTTP_ORIGIN']) ? substr($_SERVER['HTTP_ORIGIN'], 0, 60) : '-')
            . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// ---- global guards: never leak debug output; every failure returns clean JSON ----
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function ($e) {
    @error_log('[shreewin] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['data' => null, 'code' => 4, 'msg' => 'No operation permission', 'msgCode' => 2, 'serviceNowTime' => date('Y-m-d H:i:s')]);
    exit;
});
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) return false;      // respect @ suppression
    @error_log('[shreewin] err ' . $no . ': ' . $str . ' @ ' . $file . ':' . $line);
    return true;                                        // swallow: no debug in response
});

// ---- LICENSE GATE: enforce (needs $conn, so it runs after the DB connect) ----
if (function_exists('lc_guard')) { lc_guard(); }
