<?php
// Shared helpers for the self-hosted SaaS lottery (ARLottery clone).
// Real results are pulled from the upstream draw server; bets settle against them.

define('SAAS_DRAW_UPSTREAM', 'https://draw.ar-lottery06.com');
// Secondary draw host (mirror) — bdg uses draw.ar-lottery01.com for K3; both
// serve identical live draws, so we fail over 06 -> 01 -> stale cache.
define('SAAS_DRAW_UPSTREAM_ALT', 'https://draw.ar-lottery01.com');
define('SAAS_JWT_SECRET', 'shreewin_gt_2026_secret');

// Valid game types the frontend can request.
function saas_valid_games() {
    return [
        'WinGo'    => ['WinGo_30S', 'WinGo_1M', 'WinGo_3M', 'WinGo_5M'],
        'K3'       => ['K3_1M', 'K3_3M', 'K3_5M', 'K3_10M'],
        'D5'       => ['D5_1M', 'D5_3M', 'D5_5M', 'D5_10M'],
        'MotoRace' => ['MotoRace_1M'],
    ];
}

// gameCode (e.g. "WinGo_30S") -> lottery family ("WinGo"). Null if unknown.
function saas_lottery_of($gameCode) {
    foreach (saas_valid_games() as $fam => $codes) {
        if (in_array($gameCode, $codes, true)) return $fam;
    }
    return null;
}

// Interval minutes from a game code suffix.
function saas_interval_minutes($gameCode) {
    if (preg_match('/_(\d+)(S|M)$/', $gameCode, $m)) {
        $n = (int)$m[1];
        return $m[2] === 'S' ? $n / 60.0 : (float)$n;
    }
    return 1.0;
}

// Fetch a URL (GET) with a short timeout. Returns [httpCode, body].
function saas_http_get($url, $headers = [], $timeout = 8) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

// Pull upstream JSON with a tiny on-disk cache to avoid hammering the source.
// $path e.g. "WinGo/WinGo_30S.json" or "WinGo/WinGo_30S/GetHistoryIssuePage.json"
function saas_fetch_draw($path, $ttlSeconds = 2) {
    $cacheDir = '/tmp/saas_draw_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . '/' . md5($path) . '.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $c = file_get_contents($cacheFile);
        if ($c !== false && $c !== '') return $c;
    }

    list($code, $body) = saas_http_get(SAAS_DRAW_UPSTREAM . '/' . $path . '?ts=' . (time() * 1000));
    if ($code === 200 && $body) {
        @file_put_contents($cacheFile, $body);
        return $body;
    }
    // Fail over to the mirror draw host (draw.ar-lottery01.com).
    list($code, $body) = saas_http_get(SAAS_DRAW_UPSTREAM_ALT . '/' . $path . '?ts=' . (time() * 1000));
    if ($code === 200 && $body) {
        @file_put_contents($cacheFile, $body);
        return $body;
    }
    // Fall back to stale cache if both upstreams hiccup.
    if (is_file($cacheFile)) {
        $c = file_get_contents($cacheFile);
        if ($c !== false && $c !== '') return $c;
    }
    return null;
}

// Authenticate the caller from the Bearer JWT (our own site token). Returns
// [true, userId] or [false, null]. We reuse the site's JWT so a single login
// works for both the main site and the SaaS games.
function saas_authenticate($conn) {
    $hdr = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($hdr) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $hdr = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    $token = '';
    if (!empty($hdr) && preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) {
        $token = $m[1];
    } elseif (!empty($hdr)) {
        $token = trim($hdr);
    } elseif (!empty($_REQUEST['token'])) {
        $token = trim($_REQUEST['token']);
    } elseif (!empty($_REQUEST['Token'])) {
        $token = trim($_REQUEST['Token']);
    } elseif (!empty($_REQUEST['akshinak'])) {
        $token = trim($_REQUEST['akshinak']);
    } elseif (!empty($_COOKIE['token'])) {
        $token = trim($_COOKIE['token']);
    } elseif (!empty($_COOKIE['Token'])) {
        $token = trim($_COOKIE['Token']);
    } elseif (!empty($_COOKIE['akshinak'])) {
        $token = trim($_COOKIE['akshinak']);
    }

    if ($token === '') return [false, null];

    $data = json_decode(is_jwt_valid($token, SAAS_JWT_SECRET), true);
    if (!$data || ($data['status'] ?? '') !== 'Success') {
        if (function_exists('is_jwt_valid')) {
            $data = json_decode(is_jwt_valid($token), true);
        }
    }
    if (!$data || ($data['status'] ?? '') !== 'Success') return [false, null];

    $userId = (int)($data['payload']['id'] ?? ($data['payload']['userId'] ?? 0));
    if ($userId <= 0) return [false, null];

    // Verify user exists and is active
    $q = $conn->query("SELECT id, status FROM shonu_subjects WHERE id = $userId LIMIT 1");
    if (!$q || $q->num_rows !== 1) return [false, null];
    $u = $q->fetch_assoc();
    if (isset($u['status']) && (int)$u['status'] !== 1) return [false, null];

    return [true, $userId];
}

// Standard SaaS JSON envelope + CORS. Call once at the top of an endpoint.
function saas_headers() {
    header('Content-Type: application/json; charset=utf-8');
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }
}

function saas_ok($data) {
    echo json_encode([
        'result'      => true,
        'data'        => $data,
        'code'        => 0,
        'msg'         => 'Succeed',
        'msgCode'     => 0,
        'serviceTime' => (int)round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

function saas_err($code, $msg, $msgCode = 1, $http = 200) {
    http_response_code($http);
    echo json_encode([
        'result'      => false,
        'data'        => null,
        'code'        => $code,
        'msg'         => $msg,
        'msgCode'     => $msgCode,
        'serviceTime' => (int)round(microtime(true) * 1000),
    ]);
    exit();
}


// Override saas_results with any admin-set manual results (saas_manual_results).
// Call AFTER persisting upstream history so bets settle on the manual number.
// $gameCode null = apply for all games.
function saas_apply_manual($conn, $gameCode = null) {
    $gc = $gameCode !== null ? $conn->real_escape_string($gameCode) : null;
    $cond = $gc !== null ? " AND m.game_code='$gc'" : '';
    $C = 'COLLATE utf8mb4_unicode_ci';
    $conn->query("UPDATE saas_results r JOIN saas_manual_results m
                  ON m.game_code $C = r.game_code $C AND m.issue_number $C = r.issue_number $C AND m.state=1
                  SET r.number=m.number, r.color=m.color, r.premium=m.premium, r.sum_val=m.sum_val
                  WHERE 1=1 $cond");
    $conn->query("INSERT IGNORE INTO saas_results (game_code,issue_number,number,color,premium,sum_val)
                  SELECT m.game_code,m.issue_number,m.number,m.color,m.premium,m.sum_val
                  FROM saas_manual_results m WHERE m.state=1 $cond
                  AND NOT EXISTS (SELECT 1 FROM saas_results r2
                                  WHERE r2.game_code $C = m.game_code $C AND r2.issue_number $C = m.issue_number $C)");
}

// Balance read/write on shonu_kaichila.motta (stored as varchar).
function saas_get_balance($conn, $userId) {
    $q = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = " . (int)$userId . " ORDER BY kramasankhye DESC LIMIT 1");
    if ($q && $q->num_rows) { $r = $q->fetch_assoc(); return (float)$r['motta']; }
    return 0.0;
}
?>
