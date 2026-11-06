<?php
/* _mod/auth.php — public (pre-session) actions: login.
 * Exposes $AUTH_ACTIONS[action] = fn($conn,$in). */

$AUTH_ACTIONS = [];

$AUTH_ACTIONS['login'] = function ($conn, $in) {
    csrf_check();  // token injected into the page by index.php
    $username = trim((string)($in['username'] ?? ''));
    $password = (string)($in['password'] ?? '');
    if ($username === '' || $password === '') json_out(['ok' => false, 'error' => 'Enter username and password']);

    $st = $conn->prepare("SELECT * FROM support_admins WHERE username=? LIMIT 1");
    $st->bind_param('s', $username); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    $now = time();

    if (!$row) json_out(['ok' => false, 'error' => 'Invalid username or password']);
    if ((int)$row['is_active'] !== 1) json_out(['ok' => false, 'error' => 'This account is disabled']);
    if (!empty($row['locked_until']) && strtotime($row['locked_until']) > $now) {
        $mins = ceil((strtotime($row['locked_until']) - $now) / 60);
        json_out(['ok' => false, 'error' => "Too many attempts. Try again in {$mins} min."]);
    }
    if (!password_verify($password, $row['password_hash'])) {
        $fails = (int)$row['failed_attempts'] + 1;
        if ($fails >= LOGIN_MAXTRY) {
            $until = date('Y-m-d H:i:s', $now + LOGIN_LOCK);
            $u = $conn->prepare("UPDATE support_admins SET failed_attempts=?, locked_until=? WHERE id=?");
            $u->bind_param('isi', $fails, $until, $row['id']); $u->execute(); $u->close();
            json_out(['ok' => false, 'error' => 'Too many attempts. Locked for 15 min.']);
        }
        $u = $conn->prepare("UPDATE support_admins SET failed_attempts=? WHERE id=?");
        $u->bind_param('ii', $fails, $row['id']); $u->execute(); $u->close();
        json_out(['ok' => false, 'error' => 'Invalid username or password']);
    }

    // success — rotate single-session token (kicks any other active session)
    $tok = bin2hex(random_bytes(16));
    $ts  = date('Y-m-d H:i:s');
    $u = $conn->prepare("UPDATE support_admins SET failed_attempts=0, locked_until=NULL, is_online=1, last_seen=?, session_token=?, session_at=? WHERE id=?");
    $u->bind_param('sssi', $ts, $tok, $ts, $row['id']); $u->execute(); $u->close();
    admin_login_set($row, $tok);
    alog('login');

    $a = current_admin();
    json_out(['ok' => true, 'admin' => admin_public($a), 'csrf' => csrf_token(), 'perms_all' => $GLOBALS['PERMS']]);
};

// shared shape for the client
function admin_public($a) {
    return [
        'id' => $a['id'], 'username' => $a['username'], 'name' => $a['name'],
        'is_super' => (int)$a['is_super'], 'perms' => $a['perms'] ?? [],
    ];
}
