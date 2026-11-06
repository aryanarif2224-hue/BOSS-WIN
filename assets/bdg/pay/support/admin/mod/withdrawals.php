<?php
/* _mod/withdrawals.php — payout orders (hintegedukolli).
 * sthiti: 0=pending, 1=paid/approved, 2=rejected.
 * Balance was ALREADY deducted when the user applied, so:
 *   approve -> just mark sthiti=1 (operator pays out externally)
 *   reject  -> mark sthiti=2 AND refund the amount back to the wallet. */

return [
'withdrawals' => [function ($conn, $in, $me) {
    $status = isset($in['status']) && $in['status'] !== '' ? (int)$in['status'] : 0;
    $page = max(1, (int)($in['page'] ?? 1)); $size = 30; $off = ($page - 1) * $size;
    $q = trim((string)($in['q'] ?? ''));

    $where = "w.sthiti = ?";
    $types = 'i'; $args = [$status];
    if ($q !== '') {
        if (ctype_digit($q)) { $where .= " AND (w.balakedara = ? OR w.shonu = ?)"; $types .= 'ii'; $qi = (int)$q; $args[] = $qi; $args[] = $qi; }
        else { $where .= " AND w.dharavahi LIKE ?"; $types .= 's'; $args[] = "%$q%"; }
    }
    $sql = "SELECT w.shonu, w.balakedara, w.motta, w.dharavahi, w.khateshonu, w.dinankavannuracisi, w.madari, w.remarks, w.sthiti,
                   s.mobile
            FROM hintegedukolli w LEFT JOIN shonu_subjects s ON s.id = w.balakedara
            WHERE $where ORDER BY w.shonu DESC LIMIT ? OFFSET ?";
    $types .= 'ii'; $args[] = $size; $args[] = $off;
    $st = $conn->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $r = $st->get_result();
    $list = [];
    while ($x = $r->fetch_assoc()) {
        $list[] = [
            'id' => (int)$x['shonu'], 'uid' => (int)$x['balakedara'], 'mobile' => $x['mobile'],
            'amount' => (float)$x['motta'], 'serial' => $x['dharavahi'], 'bank_ref' => $x['khateshonu'],
            'date' => $x['dinankavannuracisi'], 'type' => (int)$x['madari'], 'remarks' => $x['remarks'],
            'status' => (int)$x['sthiti'],
        ];
    }
    $st->close();
    json_out(['ok' => true, 'list' => $list, 'page' => $page]);
}, 'withdrawals', false],

'wd_action' => [function ($conn, $in, $me) {
    $id  = (int)($in['id'] ?? 0);
    $act = (string)($in['do'] ?? '');
    $note = trim((string)($in['note'] ?? ''));
    if ($id <= 0) json_out(['ok' => false, 'error' => 'Missing order']);

    // lock the row, ensure it is still pending
    $conn->begin_transaction();
    try {
        $st = $conn->prepare("SELECT balakedara, motta, sthiti, remarks FROM hintegedukolli WHERE shonu=? FOR UPDATE");
        $st->bind_param('i', $id); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        if (!$row) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Order not found']); }
        if ((int)$row['sthiti'] !== 0) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Already processed (status ' . (int)$row['sthiti'] . ')']); }

        $uid = (int)$row['balakedara']; $amt = (float)$row['motta'];
        $oldRemarks = (string)($row['remarks'] ?? '');

        if ($act === 'approve') {
            $tag = ' | paid by ' . ($me['name'] ?? 'admin') . ($note !== '' ? ': ' . $note : '');
            $newRemarks = mb_substr($oldRemarks . $tag, 0, 255);
            $st = $conn->prepare("UPDATE hintegedukolli SET sthiti=1, remarks=? WHERE shonu=? AND sthiti=0");
            $st->bind_param('si', $newRemarks, $id); $st->execute(); $aff = $st->affected_rows; $st->close();
            if ($aff < 1) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Race — already changed']); }
            $conn->commit();
            alog('withdraw_approve', $note, $uid, (string)$id, $amt);
            json_out(['ok' => true, 'status' => 1]);
        } elseif ($act === 'reject') {
            // refund: balance was deducted at apply time
            $tag = ' | rejected by ' . ($me['name'] ?? 'admin') . ($note !== '' ? ': ' . $note : '');
            $newRemarks = mb_substr($oldRemarks . $tag, 0, 255);
            $st = $conn->prepare("UPDATE hintegedukolli SET sthiti=2, remarks=? WHERE shonu=? AND sthiti=0");
            $st->bind_param('si', $newRemarks, $id); $st->execute(); $aff = $st->affected_rows; $st->close();
            if ($aff < 1) { $conn->rollback(); json_out(['ok' => false, 'error' => 'Race — already changed']); }
            $st = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ?");
            $st->bind_param('di', $amt, $uid); $st->execute(); $st->close();
            $conn->commit();
            alog('withdraw_reject_refund', $note, $uid, (string)$id, $amt);
            json_out(['ok' => true, 'status' => 2, 'refunded' => $amt]);
        } else {
            $conn->rollback(); json_out(['ok' => false, 'error' => 'Bad action']);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        @error_log("wd_action error: " . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}, 'withdrawals', true],
];
