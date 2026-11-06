<?php
/* _mod/deposits.php — recharge orders (thevani).
 * sthiti: 0=pending, 1=success/credited. (pavatiaidi=3 = failed)
 *   approve -> sthiti 0->1 AND credit wallet motta += amount  (mirror voltpay-callback, idempotent)
 *   reject  -> mark failed (pavatiaidi=3), no balance change. */

return [
'deposits' => [function ($conn, $in, $me) {
    $status = isset($in['status']) && $in['status'] !== '' ? (int)$in['status'] : 0;
    $page = max(1, (int)($in['page'] ?? 1)); $size = 30; $off = ($page - 1) * $size;
    $q = trim((string)($in['q'] ?? ''));

    $where = "t.sthiti = ?";
    $types = 'i'; $args = [$status];
    if ($q !== '') {
        if (ctype_digit($q)) { $where .= " AND (t.balakedara = ? OR t.shonu = ?)"; $types .= 'ii'; $qi = (int)$q; $args[] = $qi; $args[] = $qi; }
        else { $where .= " AND (t.dharavahi LIKE ? OR t.payid LIKE ? OR t.ekikrtapavati LIKE ?)"; $types .= 'sss'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%"; }
    }
    $sql = "SELECT t.shonu, t.balakedara, t.motta, t.dharavahi, t.payid, t.ekikrtapavati, t.dinankavannuracisi, t.madari, t.sthiti, t.pavatiaidi,
                   s.mobile
            FROM thevani t LEFT JOIN shonu_subjects s ON s.id = t.balakedara
            WHERE $where ORDER BY t.shonu DESC LIMIT ? OFFSET ?";
    $types .= 'ii'; $args[] = $size; $args[] = $off;
    $st = $conn->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $r = $st->get_result();
    $list = [];
    while ($x = $r->fetch_assoc()) {
        $list[] = [
            'id' => (int)$x['shonu'], 'uid' => (int)$x['balakedara'], 'mobile' => $x['mobile'],
            'amount' => (float)$x['motta'], 'serial' => $x['dharavahi'], 'txn' => $x['payid'],
            'utr' => $x['ekikrtapavati'], 'date' => $x['dinankavannuracisi'], 'type' => (int)$x['madari'],
            'status' => (int)$x['sthiti'], 'failed' => ((int)$x['pavatiaidi'] === 3),
        ];
    }
    $st->close();
    json_out(['ok' => true, 'list' => $list, 'page' => $page]);
}, 'deposits', false],

'dep_action' => [function ($conn, $in, $me) {
    $id  = (int)($in['id'] ?? 0);
    $act = (string)($in['do'] ?? '');
    $note = trim((string)($in['note'] ?? ''));
    if ($id <= 0) json_out(['ok' => false, 'error' => 'Missing order']);

    $conn->begin_transaction();
    try {
        $st = $conn->prepare("SELECT balakedara, motta, sthiti FROM thevani WHERE shonu=? FOR UPDATE");
        $st->bind_param('i', $id); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        if (!$row) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Order not found']); }
        $uid = (int)$row['balakedara']; $amt = (float)$row['motta'];

        if ($act === 'approve') {
            if ((int)$row['sthiti'] === 1) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Already credited']); }
            $st = $conn->prepare("UPDATE thevani SET sthiti=1, ullekha=CONCAT('manual:',?) WHERE shonu=? AND sthiti<>1");
            $by = $me['name'] . ($note !== '' ? ' ' . $note : '');
            $st->bind_param('si', $by, $id); $st->execute(); $aff = $st->affected_rows; $st->close();
            if ($aff < 1) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Race — already changed']); }
            $st = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ?");
            $st->bind_param('di', $amt, $uid); $st->execute(); $st->close();
            $conn->commit();
            alog('deposit_approve_credit', $note, $uid, (string)$id, $amt);
            json_out(['ok' => true, 'status' => 1, 'credited' => $amt]);
        } elseif ($act === 'reject') {
            if ((int)$row['sthiti'] === 1) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Already credited — cannot reject']); }
            $st = $conn->prepare("UPDATE thevani SET pavatiaidi=3, ullekha=CONCAT('rejected:',?) WHERE shonu=?");
            $by = $me['name'] . ($note !== '' ? ' ' . $note : '');
            $st->bind_param('si', $by, $id); $st->execute(); $st->close();
            $conn->commit();
            alog('deposit_reject', $note, $uid, (string)$id, $amt);
            json_out(['ok' => true, 'status' => 0, 'failed' => true]);
        } else {
            $conn->rollback(); json_out(['ok' => false, 'error' => 'Bad action']);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        @error_log("deposit_action error: " . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}, 'deposits', true],
];
