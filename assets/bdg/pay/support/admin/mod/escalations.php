<?php
/* _mod/escalations.php — tickets agents escalated to admin (escalated=1). */

$GLOBALS['STATUS_LABEL'] = [1 => 'Pending', 2 => 'Processing', 3 => 'Rejected', 4 => 'Resolved', 5 => 'System Pending'];

return [
'escalations' => [function ($conn, $in, $me) {
    $showDone = !empty($in['all']);
    $cond = $showDone ? "escalated=1" : "escalated=1 AND status NOT IN (3,4)";
    $r = $conn->query("SELECT w.work_order_no, w.user_id, w.mobile, w.display_name, w.status, w.escalated_note, w.escalated_at, w.created_at, w.form_id
                       FROM support_workorders w WHERE $cond ORDER BY w.escalated_at DESC, w.id DESC LIMIT 100");
    $list = [];
    while ($x = $r->fetch_assoc()) {
        $list[] = [
            'no' => $x['work_order_no'], 'uid' => (int)$x['user_id'], 'mobile' => $x['mobile'],
            'title' => $x['display_name'], 'status' => (int)$x['status'], 'status_label' => $GLOBALS['STATUS_LABEL'][(int)$x['status']] ?? '',
            'note' => $x['escalated_note'], 'esc_at' => $x['escalated_at'], 'created' => $x['created_at'], 'form_id' => (int)$x['form_id'],
        ];
    }
    json_out(['ok' => true, 'list' => $list]);
}, 'escalations', false],

'esc_view' => [function ($conn, $in, $me) {
    $no = (string)($in['no'] ?? '');
    $st = $conn->prepare("SELECT * FROM support_workorders WHERE work_order_no=? LIMIT 1");
    $st->bind_param('s', $no); $st->execute(); $t = $st->get_result()->fetch_assoc(); $st->close();
    if (!$t) json_out(['ok' => false, 'error' => 'Ticket not found']);

    // decode submitted form fields
    $fields = [];
    $arr = json_decode($t['form_fields'] ?? '', true);
    if (is_array($arr)) foreach ($arr as $f) if (is_array($f)) $fields[] = [
        'name' => $f['fieldName'] ?? ($f['name'] ?? ($f['label'] ?? 'Field')),
        'value' => $f['fieldValue'] ?? ($f['value'] ?? ($f['val'] ?? '')),
    ];

    $msgs = []; $st = $conn->prepare("SELECT is_staff,content,created_at FROM support_comments WHERE order_no=? ORDER BY id ASC");
    $st->bind_param('s', $no); $st->execute(); $r = $st->get_result();
    while ($c = $r->fetch_assoc()) $msgs[] = ['kind' => ((int)$c['is_staff'] ? 'staff' : 'user'), 'text' => $c['content'], 'at' => $c['created_at']];
    $st->close();

    $uid = (int)$t['user_id']; $w = wallet_of($uid);
    json_out(['ok' => true, 'ticket' => [
        'no' => $t['work_order_no'], 'uid' => $uid, 'mobile' => $t['mobile'], 'title' => $t['display_name'],
        'status' => (int)$t['status'], 'note' => $t['escalated_note'], 'created' => $t['created_at'],
    ], 'fields' => $fields, 'messages' => $msgs, 'wallet' => ['balance' => (float)$w['motta'], 'bonus' => (float)$w['bonus']]]);
}, 'escalations', false],

'esc_reply' => [function ($conn, $in, $me) {
    $no = (string)($in['no'] ?? ''); $text = trim((string)($in['text'] ?? ''));
    if ($text === '') json_out(['ok' => false, 'error' => 'Empty message']);
    $st = $conn->prepare("SELECT user_id FROM support_workorders WHERE work_order_no=? LIMIT 1");
    $st->bind_param('s', $no); $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) json_out(['ok' => false, 'error' => 'Ticket not found']);
    $uid = (int)$row['user_id']; $now = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO support_comments (order_no,user_id,is_staff,content,created_at) VALUES (?,?,1,?,?)");
    $st->bind_param('siss', $no, $uid, $text, $now); $st->execute(); $st->close();
    $conn->query("UPDATE support_workorders SET updated_at='$now', status=IF(status=1,2,status) WHERE work_order_no='" . $conn->real_escape_string($no) . "'");
    alog('esc_reply', '', $uid, $no);
    json_out(['ok' => true]);
}, 'escalations', true],

'esc_resolve' => [function ($conn, $in, $me) {
    $no = (string)($in['no'] ?? ''); $s = (int)($in['status'] ?? 4);
    if (!in_array($s, [3, 4], true)) json_out(['ok' => false, 'error' => 'Bad status']);
    $now = date('Y-m-d H:i:s');
    $st = $conn->prepare("UPDATE support_workorders SET status=?, updated_at=? WHERE work_order_no=?");
    $st->bind_param('iss', $s, $now, $no); $st->execute(); $st->close();
    alog($s === 4 ? 'esc_resolved' : 'esc_rejected', '', 0, $no);
    json_out(['ok' => true]);
}, 'escalations', true],
];
