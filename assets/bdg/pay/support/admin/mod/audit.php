<?php
/* _mod/audit.php — unified audit trail (admin actions + agent actions). */

return [
'audit' => [function ($conn, $in, $me) {
    $page = max(1, (int)($in['page'] ?? 1)); $size = 40; $off = ($page - 1) * $size;
    $q = trim((string)($in['q'] ?? ''));
    $src = (string)($in['src'] ?? 'admin');   // admin | agent

    if ($src === 'agent') {
        if ($q !== '') {
            $like = "%$q%"; $qi = ctype_digit($q) ? (int)$q : -1;
            $st = $conn->prepare("SELECT agent_name AS who,action,order_no,target_user_id,detail,0 AS amount,ip,created_at
                                  FROM support_activity_log
                                  WHERE agent_name LIKE ? OR action LIKE ? OR order_no LIKE ? OR target_user_id=?
                                  ORDER BY id DESC LIMIT ? OFFSET ?");
            $st->bind_param('sssiii', $like, $like, $like, $qi, $size, $off);
        } else {
            $st = $conn->prepare("SELECT agent_name AS who,action,order_no,target_user_id,detail,0 AS amount,ip,created_at
                                  FROM support_activity_log ORDER BY id DESC LIMIT ? OFFSET ?");
            $st->bind_param('ii', $size, $off);
        }
    } else {
        if ($q !== '') {
            $like = "%$q%"; $qi = ctype_digit($q) ? (int)$q : -1;
            $st = $conn->prepare("SELECT admin_name AS who,action,order_no,target_user_id,detail,amount,ip,created_at
                                  FROM support_admin_log
                                  WHERE admin_name LIKE ? OR action LIKE ? OR order_no LIKE ? OR target_user_id=?
                                  ORDER BY id DESC LIMIT ? OFFSET ?");
            $st->bind_param('sssiii', $like, $like, $like, $qi, $size, $off);
        } else {
            $st = $conn->prepare("SELECT admin_name AS who,action,order_no,target_user_id,detail,amount,ip,created_at
                                  FROM support_admin_log ORDER BY id DESC LIMIT ? OFFSET ?");
            $st->bind_param('ii', $size, $off);
        }
    }
    $st->execute(); $r = $st->get_result(); $list = [];
    while ($x = $r->fetch_assoc()) { $x['amount'] = (float)$x['amount']; $x['target_user_id'] = (int)$x['target_user_id']; $list[] = $x; }
    $st->close();
    json_out(['ok' => true, 'list' => $list, 'page' => $page, 'src' => $src]);
}, 'audit', false],
];
