<?php
/* _mod/session.php — session meta: me, logout, online toggle, sidebar counts. */

return [
'me' => [function ($conn, $in, $me) {
    json_out(['ok' => true, 'admin' => admin_public(current_admin()), 'csrf' => csrf_token(), 'perms_all' => $GLOBALS['PERMS']]);
}, null, false],

'logout' => [function ($conn, $in, $me) {
    $conn->query("UPDATE support_admins SET is_online=0, session_token=NULL WHERE id=" . (int)$me['id']);
    alog('logout');
    admin_logout();
    json_out(['ok' => true]);
}, null, false],

// badge counts for the sidebar (only what this admin can see)
'counts' => [function ($conn, $in, $me) {
    $r = ['ok' => true];
    if (can('escalations'))
        $r['escalations'] = (int)$conn->query("SELECT COUNT(*) FROM support_workorders WHERE escalated=1 AND status NOT IN (3,4)")->fetch_row()[0];
    if (can('withdrawals'))
        $r['withdrawals'] = (int)$conn->query("SELECT COUNT(*) FROM hintegedukolli WHERE sthiti=0")->fetch_row()[0];
    if (can('deposits'))
        $r['deposits'] = (int)$conn->query("SELECT COUNT(*) FROM thevani WHERE sthiti=0")->fetch_row()[0];
    json_out($r);
}, null, false],


// realtime poll: new deposits/withdrawals/escalated tickets since a cursor
'poll' => [function ($conn, $in, $me) {
    $r = ['ok' => true, 'ts' => time(), 'max_cursor' => 0, 'new' => []];
    $cursor = max(0, (int)($in['cursor'] ?? 0));
    if (can('deposits')) {
        $q = $conn->query("SELECT id, dharavahi, motta, balakedara FROM thevani WHERE sthiti=0 AND id>$cursor ORDER BY id ASC LIMIT 15");
        if ($q) while ($x = $q->fetch_assoc()) {
            $r['new'][] = ['kind'=>'deposit','id'=>(int)$x['id'],'no'=>$x['dharavahi'],'amount'=>(float)$x['motta'],'uid'=>(int)$x['balakedara']];
            if ((int)$x['id'] > $r['max_cursor']) $r['max_cursor'] = (int)$x['id'];
        }
    }
    if (can('withdrawals')) {
        $q = $conn->query("SELECT id, dharavahi, motta, balakedara FROM hintegedukolli WHERE sthiti=0 AND id>$cursor ORDER BY id ASC LIMIT 15");
        if ($q) while ($x = $q->fetch_assoc()) {
            $r['new'][] = ['kind'=>'withdraw','id'=>(int)$x['id'],'no'=>$x['dharavahi'],'amount'=>(float)$x['motta'],'uid'=>(int)$x['balakedara']];
            if ((int)$x['id'] > $r['max_cursor']) $r['max_cursor'] = (int)$x['id'];
        }
    }
    if (can('escalations')) {
        $q = $conn->query("SELECT id, work_order_no, user_id FROM support_workorders WHERE escalated=1 AND status NOT IN (3,4) AND id>$cursor ORDER BY id ASC LIMIT 15");
        if ($q) while ($x = $q->fetch_assoc()) {
            $r['new'][] = ['kind'=>'ticket','id'=>(int)$x['id'],'no'=>$x['work_order_no'],'uid'=>(int)$x['user_id']];
            if ((int)$x['id'] > $r['max_cursor']) $r['max_cursor'] = (int)$x['id'];
        }
    }
    json_out($r);
}, null, false],

// first-login activation gate (V2). code must equal ACTIVATION_KEY.
'activation_state' => [function ($conn) {
    $v = '';
    if ($q = $conn->query("SELECT meta_value FROM admin_meta WHERE meta_key='activated'")) { if ($x=$q->fetch_assoc()) $v=$x['meta_value']; }
    json_out(['ok' => true, 'activated' => $v === '1', 'features' => [
        'Modern UI + animations', 'Real-time alerts with sound', 'Game ON/OFF + Maintenance manager',
        'Home banner manager', 'Gift code generator', 'Activation lock',
    ]]);
}, null, false],

'activate' => [function ($conn, $in) {
    $code = strtoupper(trim((string)($in['code'] ?? '')));
    if ($code === ACTIVATION_KEY) {
        $conn->query("INSERT INTO admin_meta (meta_key, meta_value) VALUES ('activated','1') ON DUPLICATE KEY UPDATE meta_value='1'");
        alog('activate', 'site activated');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'Invalid activation code']);
}, null, true],

'self_password' => [function ($conn, $in, $me) {
    json_out(['ok' => false, 'error' => 'Password changes are disabled in this demo.']);
}, null, true],
];
