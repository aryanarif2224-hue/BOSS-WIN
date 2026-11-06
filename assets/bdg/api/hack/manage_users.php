<?php
// POST /api/hack/manage_users.php - Admin endpoint to manage Hack Tool users
require_once __DIR__ . '/common.php';

$MASTER_KEY = 'shreewin_hack_master_key_2026';

$req = get_request_data();
$adminKey = trim($req['admin_key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '');

if ($adminKey !== $MASTER_KEY) {
    hack_json(403, 'Unauthorized: Invalid master admin key.');
}

$action = trim($req['action'] ?? 'list');

if ($action === 'create' || $action === 'add') {
    $username = trim($req['username'] ?? '');
    $password = trim($req['password'] ?? '');
    $linkedMobile = trim($req['linked_mobile'] ?? '');

    if (empty($username) || empty($password) || empty($linkedMobile)) {
        hack_json(400, 'username, password, and linked_mobile are all required.');
    }

    if (strlen($linkedMobile) === 12 && substr($linkedMobile, 0, 2) === '91') {
        $linkedMobile = substr($linkedMobile, 2);
    }

    $uEsc = $conn->real_escape_string($username);
    $mEsc = $conn->real_escape_string($linkedMobile);
    $hashedPw = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO hack_users (username, password, linked_mobile, is_active) 
            VALUES ('$uEsc', '$hashedPw', '$mEsc', 1)
            ON DUPLICATE KEY UPDATE password = '$hashedPw', linked_mobile = '$mEsc', is_active = 1";

    if ($conn->query($sql)) {
        hack_json(200, "Hack user '$username' created/updated successfully.", [
            'username' => $username,
            'linked_mobile' => $linkedMobile
        ]);
    } else {
        hack_json(500, 'Failed to save hack user: ' . $conn->error);
    }
} elseif ($action === 'delete') {
    $username = trim($req['username'] ?? '');
    if (empty($username)) {
        hack_json(400, 'username is required to delete.');
    }
    $uEsc = $conn->real_escape_string($username);
    $conn->query("DELETE FROM hack_users WHERE username = '$uEsc'");
    hack_json(200, "Hack user '$username' deleted.");
} else {
    // List users
    $list = [];
    $q = $conn->query("SELECT id, username, linked_mobile, is_active, token_expires_at, created_at, updated_at FROM hack_users ORDER BY id DESC LIMIT 50");
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $list[] = $row;
        }
    }
    hack_json(200, 'Hack users list', $list);
}
