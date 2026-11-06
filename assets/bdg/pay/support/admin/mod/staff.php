<?php
/* _mod/staff.php — manage support agents + admin accounts/permissions. */

return [
/* ---------------- support agents (perm: agents) ---------------- */
'agents' => [function ($conn, $in, $me) {
    $r = $conn->query("SELECT id,username,name,role,is_active,is_online,last_seen FROM support_agents ORDER BY id");
    $list = []; while ($x = $r->fetch_assoc()) $list[] = $x;
    json_out(['ok' => true, 'list' => $list]);
}, 'agents', false],

'agent_save' => [function ($conn, $in, $me) {
    $id = (int)($in['id'] ?? 0);
    $username = trim((string)($in['username'] ?? ''));
    $name = trim((string)($in['name'] ?? ''));
    $pw = (string)($in['password'] ?? '');
    if ($username === '') json_out(['ok' => false, 'error' => 'Username required']);
    if ($id > 0) {
        $st = $conn->prepare("UPDATE support_agents SET username=?, name=? WHERE id=?");
        $st->bind_param('ssi', $username, $name, $id); $st->execute(); $st->close();
        if ($pw !== '') {
            if (strlen($pw) < 8) json_out(['ok' => false, 'error' => 'Password must be 8+ chars']);
            $h = password_hash($pw, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE support_agents SET password_hash=? WHERE id=?");
            $st->bind_param('si', $h, $id); $st->execute(); $st->close();
        }
        alog('agent_update', $username, 0, ''); json_out(['ok' => true, 'id' => $id]);
    } else {
        if (strlen($pw) < 8) json_out(['ok' => false, 'error' => 'Password must be 8+ chars']);
        $dup = $conn->prepare("SELECT id FROM support_agents WHERE username=?"); $dup->bind_param('s', $username); $dup->execute();
        if ($dup->get_result()->fetch_assoc()) { $dup->close(); json_out(['ok' => false, 'error' => 'Username already exists']); } $dup->close();
        $h = password_hash($pw, PASSWORD_DEFAULT); $now = date('Y-m-d H:i:s');
        $st = $conn->prepare("INSERT INTO support_agents (username,password_hash,name,role,is_active,created_at) VALUES (?,?,?,'agent',1,?)");
        $st->bind_param('ssss', $username, $h, $name, $now); $st->execute(); $nid = $st->insert_id; $st->close();
        alog('agent_create', $username, 0, ''); json_out(['ok' => true, 'id' => $nid]);
    }
}, 'agents', true],

'agent_toggle' => [function ($conn, $in, $me) {
    $id = (int)($in['id'] ?? 0); $on = !empty($in['active']) ? 1 : 0;
    $st = $conn->prepare("UPDATE support_agents SET is_active=?, session_token=IF(?=0,NULL,session_token) WHERE id=?");
    $st->bind_param('iii', $on, $on, $id); $st->execute(); $st->close();
    alog($on ? 'agent_enable' : 'agent_disable', '', 0, (string)$id);
    json_out(['ok' => true]);
}, 'agents', true],

'agent_delete' => [function ($conn, $in, $me) {
    $id = (int)($in['id'] ?? 0);
    $st = $conn->prepare("DELETE FROM support_agents WHERE id=?"); $st->bind_param('i', $id); $st->execute(); $st->close();
    alog('agent_delete', '', 0, (string)$id);
    json_out(['ok' => true]);
}, 'agents', true],

/* ---------------- admin accounts (perm: admins) ---------------- */
'admins' => [function ($conn, $in, $me) {
    $r = $conn->query("SELECT id,username,name,is_super,permissions,is_active,is_online,last_seen FROM support_admins ORDER BY id");
    $list = [];
    while ($x = $r->fetch_assoc()) {
        $x['permissions'] = json_decode($x['permissions'] ?? '[]', true) ?: [];
        $x['is_super'] = (int)$x['is_super']; $x['is_active'] = (int)$x['is_active']; $x['is_online'] = (int)$x['is_online'];
        $list[] = $x;
    }
    json_out(['ok' => true, 'list' => $list, 'perms_all' => $GLOBALS['PERMS']]);
}, 'admins', false],

'admin_save' => [function ($conn, $in, $me) {
    // only super-admins may create/modify admins or grant permissions
    if (empty(current_admin()['is_super'])) json_out(['ok' => false, 'error' => 'Only a super-admin can manage admins'], 403);
    $id = (int)($in['id'] ?? 0);
    $username = trim((string)($in['username'] ?? ''));
    $name = trim((string)($in['name'] ?? ''));
    $pw = (string)($in['password'] ?? '');
    $isSuper = !empty($in['is_super']) ? 1 : 0;
    $perms = is_array($in['perms'] ?? null) ? array_values(array_intersect($in['perms'], array_keys($GLOBALS['PERMS']))) : [];
    if ($isSuper) $perms = ['*'];
    $pj = json_encode($perms);
    if ($username === '') json_out(['ok' => false, 'error' => 'Username required']);

    if ($id > 0) {
        $st = $conn->prepare("UPDATE support_admins SET username=?, name=?, is_super=?, permissions=? WHERE id=?");
        $st->bind_param('ssisi', $username, $name, $isSuper, $pj, $id); $st->execute(); $st->close();
        if ($pw !== '') {
            if (strlen($pw) < 8) json_out(['ok' => false, 'error' => 'Password must be 8+ chars']);
            $h = password_hash($pw, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE support_admins SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?");
            $st->bind_param('si', $h, $id); $st->execute(); $st->close();
        }
        alog('admin_update', $username . ($isSuper ? ' [super]' : ' [' . implode(',', $perms) . ']'), 0, (string)$id);
        json_out(['ok' => true, 'id' => $id]);
    } else {
        if (strlen($pw) < 8) json_out(['ok' => false, 'error' => 'Password must be 8+ chars']);
        $dup = $conn->prepare("SELECT id FROM support_admins WHERE username=?"); $dup->bind_param('s', $username); $dup->execute();
        if ($dup->get_result()->fetch_assoc()) { $dup->close(); json_out(['ok' => false, 'error' => 'Username already exists']); } $dup->close();
        $h = password_hash($pw, PASSWORD_DEFAULT); $now = date('Y-m-d H:i:s'); $by = $me['name'];
        $st = $conn->prepare("INSERT INTO support_admins (username,password_hash,name,is_super,permissions,is_active,created_by,created_at) VALUES (?,?,?,?,?,1,?,?)");
        $st->bind_param('sssisss', $username, $h, $name, $isSuper, $pj, $by, $now); $st->execute(); $nid = $st->insert_id; $st->close();
        alog('admin_create', $username . ($isSuper ? ' [super]' : ' [' . implode(',', $perms) . ']'), 0, (string)$nid);
        json_out(['ok' => true, 'id' => $nid]);
    }
}, 'admins', true],

'admin_toggle' => [function ($conn, $in, $me) {
    if (empty(current_admin()['is_super'])) json_out(['ok' => false, 'error' => 'Only a super-admin'], 403);
    $id = (int)($in['id'] ?? 0); $on = !empty($in['active']) ? 1 : 0;
    if ($id === (int)$me['id']) json_out(['ok' => false, 'error' => 'You cannot disable yourself']);
    $st = $conn->prepare("UPDATE support_admins SET is_active=?, session_token=IF(?=0,NULL,session_token) WHERE id=?");
    $st->bind_param('iii', $on, $on, $id); $st->execute(); $st->close();
    alog($on ? 'admin_enable' : 'admin_disable', '', 0, (string)$id);
    json_out(['ok' => true]);
}, 'admins', true],

'admin_delete' => [function ($conn, $in, $me) {
    if (empty(current_admin()['is_super'])) json_out(['ok' => false, 'error' => 'Only a super-admin'], 403);
    $id = (int)($in['id'] ?? 0);
    if ($id === (int)$me['id']) json_out(['ok' => false, 'error' => 'You cannot delete yourself']);
    $st = $conn->prepare("DELETE FROM support_admins WHERE id=?"); $st->bind_param('i', $id); $st->execute(); $st->close();
    alog('admin_delete', '', 0, (string)$id);
    json_out(['ok' => true]);
}, 'admins', true],
];
