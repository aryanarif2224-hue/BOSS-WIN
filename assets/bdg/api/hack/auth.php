<?php
// POST /api/hack/auth.php - Hack Tool Login & Token Generation for Guru Modes
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hack_json(405, 'Method not allowed. Use POST.');
}

$req = get_request_data();
$username = trim($req['username'] ?? '');
$password = trim($req['password'] ?? '');

if (empty($username) || empty($password)) {
    hack_json(400, 'Username / Mobile and password are required.');
}

$usernameEsc = $conn->real_escape_string($username);
$cleanMobile = ltrim($username, '+');
if (str_starts_with($cleanMobile, '91') && strlen($cleanMobile) == 12) {
    $cleanMobile = substr($cleanMobile, 2);
}
$cleanMobileEsc = $conn->real_escape_string($cleanMobile);

$hackUser = null;
$pwValid = false;

// 1. Try checking hack_users first
$q = $conn->query("SELECT * FROM hack_users WHERE username = '$usernameEsc' OR linked_mobile = '$cleanMobileEsc' LIMIT 1");
if ($q && $q->num_rows > 0) {
    $row = $q->fetch_assoc();
    if (password_verify($password, $row['password']) || md5($password) === $row['password'] || $password === $row['password']) {
        $hackUser = $row;
        $pwValid = true;
    }
}

// 2. Fallback to main Shreewin player database (shonu_subjects)
if (!$pwValid) {
    $sq = $conn->query("SELECT id, mobile, password, pwd, status FROM shonu_subjects WHERE mobile = '$cleanMobileEsc' OR id = '$usernameEsc' LIMIT 1");
    if ($sq && $sq->num_rows > 0) {
        $pRow = $sq->fetch_assoc();
        if ((int)$pRow['status'] !== 1) {
            hack_json(403, 'Player account is suspended.');
        }

        if (md5($password) === $pRow['password'] || $password === ($pRow['pwd'] ?? '')) {
            $pwValid = true;
            $linkedMobile = $pRow['mobile'];
            $pwdHash = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO hack_users (username, password, linked_mobile, is_active) 
                          VALUES ('$usernameEsc', '$pwdHash', '$linkedMobile', 1) 
                          ON DUPLICATE KEY UPDATE password='$pwdHash', is_active=1");
            
            $q2 = $conn->query("SELECT * FROM hack_users WHERE username = '$usernameEsc' OR linked_mobile = '$linkedMobile' LIMIT 1");
            if ($q2 && $q2->num_rows > 0) {
                $hackUser = $q2->fetch_assoc();
            }
        }
    }
}

if (!$pwValid || !$hackUser) {
    hack_json(401, 'Invalid username or password.');
}

if ((int)$hackUser['is_active'] !== 1) {
    hack_json(403, 'Account is disabled. Please contact administrator.');
}

// Generate secure 64-char hex token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 7); // 7 days

$uId = (int)$hackUser['id'];
$tokenEsc = $conn->real_escape_string($token);
$conn->query("UPDATE hack_users SET auth_token = '$tokenEsc', token_expires_at = '$expiresAt' WHERE id = $uId");

hack_json(200, 'Login successful', [
    'token' => $token,
    'token_type' => 'Bearer',
    'expires_at' => $expiresAt,
    'hack_user' => [
        'id' => $uId,
        'username' => $hackUser['username'],
        'linked_mobile' => $hackUser['linked_mobile']
    ]
]);
