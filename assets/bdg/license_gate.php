<?php
// ============================================================
// MAANWIN-family License Gate for bdg-based lottery sites.
// Domain-locked activation against source.nextapi.online.
// Ed25519-signed token, offline cache, heartbeat, revoke support.
//
// ANTI-BYPASS DESIGN (3 independent anchors):
//   A) This file is included by conn.php  -> delete me = site dies
//   B) conn.php + functions2.php both require it, so removing
//      either one still leaves the other enforcing.
//   C) The activation page + token live in the SITE'S OWN DATABASE
//      (shonu_subjects style table), so deleting PHP files does not
//      unlock anything. Signature is checked against a PUBLIC key
//      only - forging one is impossible without the server's private key.
// ============================================================

if (!defined('LC_SERVER')) define('LC_SERVER', 'https://source.nextapi.online/api/license');
if (!defined('LC_PRODUCT')) define('LC_PRODUCT', 'raxi-clone');
if (!defined('LC_PUBKEY')) define('LC_PUBKEY', 'MCowBQYDK2VwAyEAQAoWmmLRpwYds1sxiWOXCT/vORQCnBpMPk3XnmHa/U0=');
if (!defined('LC_REQUIRED')) define('LC_REQUIRED', true);
if (!defined('LC_GRACE')) define('LC_GRACE', 21600);   // 6h offline grace

// ---- integrity: if this file is edited, its stored hash won't match ----
if (!defined('LC_SELF_HASH')) {
    define('LC_SELF_HASH', '');   // filled in by the installer
}

function lc_b64u(string $s): string {
    $s = strtr((string)$s, '-_', '+/');
    $p = strlen($s) % 4; if ($p) $s .= str_repeat('=', 4 - $p);
    return (string) base64_decode($s, true);
}

function lc_host(): string {
    $h = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $h = preg_replace('/:\d+$/', '', $h);
    return trim(preg_replace('/^www\./', '', $h) ?: 'localhost');
}

/** Stable per-install id - changes if the package is copied to another server. */
function lc_fp(): string {
    $p = [defined('DB_NAME') ? DB_NAME : 'd', defined('DB_USERNAME') ? DB_USERNAME : 'u', __DIR__];
    return substr(hash('sha256', implode('|', $p)), 0, 32);
}

function lc_db(): ?mysqli { return isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null; }

function lc_table(): void {
    $c = lc_db(); if (!$c) return;
    @ $c->query("CREATE TABLE IF NOT EXISTS license_state (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      product_slug VARCHAR(80) DEFAULT '',
      license_code VARCHAR(64) DEFAULT '',
      domain VARCHAR(190) DEFAULT '',
      token LONGTEXT,
      status VARCHAR(24) DEFAULT 'inactive',
      reason VARCHAR(190) DEFAULT '',
      last_ok_at DATETIME DEFAULT NULL,
      last_check_at DATETIME DEFAULT NULL,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_slug(product_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function lc_read(): array {
    static $c = null;
    if (!empty($GLOBALS['__lc_reset'])) { $c = null; $GLOBALS['__lc_reset'] = false; }
    if ($c !== null) return $c;
    lc_table();
    $conn = lc_db();
    $row = ['license_code'=>'','domain'=>'','product_slug'=>'','token'=>'','status'=>'inactive','reason'=>'','last_ok_at'=>null,'last_check_at'=>null];
    if ($conn) {
        $slug = $conn->real_escape_string(LC_PRODUCT);
        $rs = @$conn->query("SELECT * FROM license_state WHERE product_slug='$slug' ORDER BY id DESC LIMIT 1");
        if ($rs && ($r = $rs->fetch_assoc())) $row = array_merge($row, $r);
    }
    return $c = $row;
}

function lc_write(array $d): void {
    lc_table();
    $conn = lc_db(); if (!$conn) return;
    $code = $conn->real_escape_string((string)($d['license_code'] ?? ''));
    $dom  = $conn->real_escape_string((string)($d['domain'] ?? lc_host()));
    $slug = $conn->real_escape_string((string)($d['product_slug'] ?? LC_PRODUCT));
    $tok  = $conn->real_escape_string((string)($d['token'] ?? ''));
    $st   = $conn->real_escape_string((string)($d['status'] ?? 'inactive'));
    $rsn  = $conn->real_escape_string((string)($d['reason'] ?? ''));
    $okAt = !empty($d['last_ok_at']) ? "'".$conn->real_escape_string($d['last_ok_at'])."'" : 'NULL';
    $ex = @$conn->query("SELECT id FROM license_state WHERE product_slug='$slug' LIMIT 1");
    $row = ($ex && ($x = $ex->fetch_assoc())) ? (int)$x['id'] : 0;
    if ($row > 0) {
        @$conn->query("UPDATE license_state SET license_code='$code', domain='$dom', token='$tok',
            status='$st', reason='$rsn', last_ok_at=$okAt, last_check_at=NOW() WHERE id=$row");
    } else {
        @$conn->query("INSERT INTO license_state (product_slug,license_code,domain,token,status,reason,last_ok_at,last_check_at)
            VALUES ('$slug','$code','$dom','$tok','$st','$rsn',$okAt,NOW())");
    }
    $GLOBALS['__lc_reset'] = true;
}

function lc_post(string $ep, array $payload, int $timeout = 10): ?array {
    $url = rtrim(LC_SERVER, '/') . '/' . ltrim($ep, '/');
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>6]);
        $raw = curl_exec($ch); curl_close($ch);
        if ($raw === false) return null;
        $j = json_decode((string)$raw, true);
        return is_array($j) ? $j : null;
    }
    $ctx = stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>$timeout,'ignore_errors'=>true]]);
    $j = json_decode((string)@file_get_contents($url, false, $ctx), true);
    return is_array($j) ? $j : null;
}

/** Verify the Ed25519 signature locally. The server signs the base64url STRING. */
function lc_verify(string $token): ?array {
    if (LC_PUBKEY === '') return null;
    $parts = explode('.', $token); if (count($parts) !== 2) return null;
    [$b64, $sigB64] = $parts;
    $sig = lc_b64u($sigB64); $der = base64_decode(LC_PUBKEY, true);
    if ($sig === '' || $der === false) return null;
    if (strlen($sig) !== 64) return null;
    $raw = (strlen($der) === 32) ? $der : substr($der, -32);
    try {
        if (function_exists('sodium_crypto_sign_verify_detached') && strlen($raw) === 32) {
            if (!sodium_crypto_sign_verify_detached($sig, $b64, $raw)) return null;
            return json_decode(lc_b64u($b64), true);
        }
    } catch (\Throwable $e) { /* fall through */ }
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $k = @openssl_pkey_get_public($pem); if (!$k) return null;
    return @openssl_verify($b64, $sig, $k, 0) === 1 ? json_decode(lc_b64u($b64), true) : null;
}

/** True when the cached token is valid, unexpired and bound to THIS domain. */
function lc_ok(): bool {
    $s = lc_read();
    if (($s['status'] ?? '') !== 'active' || empty($s['token'])) return false;
    if (strtolower((string)$s['domain']) !== lc_host()) return false;
    $p = lc_verify((string)$s['token']); if (!$p) return false;
    if (strtolower((string)($p['domain'] ?? '')) !== lc_host()) return false;
    if (!empty($p['expiresAt']) && strtotime((string)$p['expiresAt']) < time()) return false;
    if (!empty($p['product']) && (string)$p['product'] !== LC_PRODUCT) return false;
    return true;
}

function lc_activate(string $code): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok'=>false,'message'=>'Enter your activation code.'];
    $res = lc_post('activate', ['code'=>$code,'domain'=>lc_host(),'fingerprint'=>lc_fp()]);
    if ($res === null) return ['ok'=>false,'message'=>'License server unreachable. Check your connection.'];
    $data = $res['data'] ?? null;
    if (!is_array($data) || empty($data['token'])) {
        $msg = $res['error']['message'] ?? $res['message'] ?? 'Activation failed.';
        lc_write(['license_code'=>$code,'domain'=>lc_host(),'status'=>'invalid','reason'=>(string)$msg]);
        return ['ok'=>false,'message'=>(string)$msg];
    }
    $payload = lc_verify((string)$data['token']);
    if (!$payload) return ['ok'=>false,'message'=>'Server signature could not be verified.'];
    lc_write([
        'license_code'=>(string)($payload['code'] ?? $code),
        'domain'=>(string)($payload['domain'] ?? lc_host()),
        'product_slug'=>(string)($payload['product'] ?? ''),
        'token'=>(string)$data['token'],
        'status'=>'active','reason'=>'','last_ok_at'=>date('Y-m-d H:i:s'),
    ]);
    return ['ok'=>true,'message'=>'Activated successfully.'];
}

/** Heartbeat - re-checks with the server (also honours remote revoke). */
function lc_beat(bool $force = false): bool {
    $s = lc_read();
    if (($s['license_code'] ?? '') === '') return false;
    if (!$force && !empty($s['last_check_at']) && strtotime((string)$s['last_check_at']) > time() - LC_GRACE) return lc_ok();
    $res = lc_post('verify', ['code'=>(string)$s['license_code'],'domain'=>lc_host(),'fingerprint'=>lc_fp()], 8);
    if ($res === null) {
        if (!empty($s['last_ok_at']) && strtotime((string)$s['last_ok_at']) > time() - (LC_GRACE * 4)) return lc_ok();
        return false;
    }
    $data = $res['data'] ?? null;
    if (!empty($data['valid']) && !empty($data['token'])) {
        lc_write(['license_code'=>(string)$s['license_code'],
            'domain'=>(string)($data['payload']['domain'] ?? lc_host()),
            'product_slug'=>(string)($data['payload']['product'] ?? ''),
            'token'=>(string)$data['token'],'status'=>'active','reason'=>'','last_ok_at'=>date('Y-m-d H:i:s')]);
        return true;
    }
    $reason = (string)($data['reason'] ?? 'invalid');
    lc_write(['license_code'=>(string)$s['license_code'],'domain'=>(string)$s['domain'],
        'product_slug'=>(string)$s['product_slug'],'token'=>(string)$s['token'],
        'status'=>'invalid','reason'=>$reason,'last_ok_at'=>$s['last_ok_at']]);
    return false;
}

/** THE GATE. Call at the top of every request. */
function lc_guard(): void {
    if (!LC_REQUIRED) return;
    if (PHP_SAPI === 'cli') return;

    // integrity: this file must be the one we shipped
    if (LC_SELF_HASH !== '') {
        $actual = hash_file('sha256', __FILE__);
        if ($actual !== LC_SELF_HASH) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code'=>503,'msg'=>'Integrity check failed.','msgCode'=>503,'data'=>null]);
            exit;
        }
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(license|gate|__license)#', $path)) return;   // activation page

    // Consult the server first (it caches for 6h internally), so a revoked
    // license takes effect instead of the cached token keeping the site alive.
    if (lc_beat()) return;
    if (lc_ok()) return;

    if (lc_wants_json()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code'=>403,'msg'=>'License inactive. Activate this installation.','msgCode'=>403,'data'=>null]);
        exit;
    }
    lc_page();
    exit;
}

function lc_wants_json(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return strpos($accept,'application/json') !== false || $xhr === 'xmlhttprequest'
        || strpos($uri,'/api/') !== false || strpos($uri,'/webapi/') !== false
        || strpos($uri,'/bdg/') !== false;
}

function lc_page(): void {
    $s = lc_read();
    $domain = htmlspecialchars(lc_host(), ENT_QUOTES, 'UTF-8');
    $msg = htmlspecialchars((string)($s['reason'] ?? ''), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars((string)($s['license_code'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['activation_code'])) {
        $r = lc_activate((string)$_POST['activation_code']);
        if ($r['ok']) { header('Location: /'); exit; }
        $msg = htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string)$_POST['activation_code'], ENT_QUOTES, 'UTF-8');
    }
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(403);
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activate License</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
 font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
 background:radial-gradient(1000px 500px at 70% -10%,#1d2740,#0a0e17 60%);color:#e7ecf5}
.card{width:100%;max-width:440px;background:linear-gradient(180deg,#161d2c,#111725);
 border:1px solid #273049;border-radius:26px;padding:30px;box-shadow:0 30px 80px rgba(0,0,0,.55)}
.logo{font-size:26px;font-weight:800;letter-spacing:-.02em;text-align:center}
.logo span{color:#F97316}
.sub{text-align:center;color:#8b97b3;font-size:13px;margin-top:6px}
.badge{display:flex;align-items:center;gap:8px;justify-content:center;margin:20px 0 22px;font-size:12px;color:#fbbf24}
.dot{width:7px;height:7px;border-radius:50%;background:#fbbf24;box-shadow:0 0 0 4px rgba(251,191,36,.15)}
label{display:block;font-size:12px;color:#8b97b3;margin-bottom:7px}
.inp{width:100%;padding:14px 16px;border-radius:14px;border:1px solid #273049;background:#0d1320;
 color:#e7ecf5;font-size:15px;letter-spacing:.09em;text-transform:uppercase;outline:none;transition:.15s}
.inp:focus{border-color:#F97316;box-shadow:0 0 0 3px rgba(249,115,22,.15)}
.btn{width:100%;margin-top:14px;padding:15px;border:0;border-radius:9999px;cursor:pointer;
 font-size:15px;font-weight:800;color:#1b1200;background:linear-gradient(90deg,#F97316,#FFAF2D);transition:.15s}
.btn:active{transform:scale(.985)}
.meta{margin-top:18px;padding-top:16px;border-top:1px solid #1e2740;font-size:12px;color:#6b7793;line-height:1.7}
.meta b{color:#9aa7c2;font-weight:600}
.err{margin-top:14px;padding:11px 14px;border-radius:12px;font-size:13px;
 background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.3);color:#fda4af}
.help{margin-top:16px;text-align:center;font-size:12px;color:#6b7793}
.help a{color:#F97316;text-decoration:none}
</style></head><body>
<div class="card">
 <div class="logo"><span>MAAN</span>WIN License</div>
 <div class="sub">This installation is not activated</div>
 <div class="badge"><span class="dot"></span> Activation required</div>
 <form method="post" autocomplete="off">
  <label>Activation code</label>
  <input class="inp" name="activation_code" placeholder="SW-XXXX-XXXX-XXXX-XXXX" value="<?= $code ?>" required>
  <button class="btn" type="submit">Activate this domain</button>
 </form>
 <?php if ($msg !== ''): ?><div class="err"><?= $msg ?></div><?php endif; ?>
 <div class="meta">
  <div>Domain <b><?= $domain ?></b></div>
  <div>Product <b><?= htmlspecialchars(LC_PRODUCT, ENT_QUOTES, 'UTF-8') ?></b></div>
 </div>
 <div class="help">Need a license? <a href="https://source.nextapi.online" target="_blank" rel="noopener">source.nextapi.online</a></div>
</div></body></html>
    <?php
}
