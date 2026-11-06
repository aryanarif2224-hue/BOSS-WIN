<?php
/* _mod/users.php — user search, 360 view, and account actions. */

return [
'user_search' => [function ($conn, $in, $me) {
    $q = trim((string)($in['q'] ?? ''));
    if ($q === '') json_out(['ok' => true, 'list' => []]);
    if (ctype_digit($q)) {
        $like = "%$q%"; $qi = (int)$q;
        $st = $conn->prepare("SELECT id,mobile,email,status,account_frozen,createdate FROM shonu_subjects WHERE id=? OR mobile LIKE ? ORDER BY id DESC LIMIT 25");
        $st->bind_param('is', $qi, $like);
    } else {
        $like = "%$q%";
        $st = $conn->prepare("SELECT id,mobile,email,status,account_frozen,createdate FROM shonu_subjects WHERE email LIKE ? ORDER BY id DESC LIMIT 25");
        $st->bind_param('s', $like);
    }
    $st->execute(); $r = $st->get_result(); $list = [];
    while ($x = $r->fetch_assoc()) $list[] = $x; $st->close();
    json_out(['ok' => true, 'list' => $list]);
}, 'users', false],

'user360' => [function ($conn, $in, $me) {
    $uid = (int)($in['uid'] ?? 0);
    if ($uid <= 0) json_out(['ok' => false, 'error' => 'Missing user']);
    $st = $conn->prepare("SELECT id,mobile,email,status,account_frozen,createdate,owncode,code FROM shonu_subjects WHERE id=?");
    $st->bind_param('i', $uid); $st->execute(); $u = $st->get_result()->fetch_assoc(); $st->close();
    if (!$u) json_out(['ok' => false, 'error' => 'User not found']);

    $w = wallet_of($uid);

    $banks = []; $st = $conn->prepare("SELECT shonu,khatesankhye,phalanubhavi,kod,khatehesaru,duravani FROM khate WHERE byabaharkarta=? ORDER BY shonu DESC");
    $st->bind_param('i', $uid); $st->execute(); $r = $st->get_result();
    while ($x = $r->fetch_assoc()) $banks[] = $x; $st->close();

    $dep = []; $st = $conn->prepare("SELECT motta,dharavahi,dinankavannuracisi,sthiti FROM thevani WHERE balakedara=? ORDER BY shonu DESC LIMIT 8");
    $st->bind_param('i', $uid); $st->execute(); $r = $st->get_result();
    while ($x = $r->fetch_assoc()) $dep[] = $x; $st->close();

    $wd = []; $st = $conn->prepare("SELECT motta,dharavahi,dinankavannuracisi,sthiti,remarks FROM hintegedukolli WHERE balakedara=? ORDER BY shonu DESC LIMIT 8");
    $st->bind_param('i', $uid); $st->execute(); $r = $st->get_result();
    while ($x = $r->fetch_assoc()) $wd[] = $x; $st->close();

    $bets = []; $st = $conn->prepare("SELECT game_code,issue_number,amount,win_lose_amount,state,settled,created_at FROM saas_bets WHERE user_id=? ORDER BY id DESC LIMIT 10");
    $st->bind_param('i', $uid); $st->execute(); $r = $st->get_result();
    while ($x = $r->fetch_assoc()) $bets[] = $x; $st->close();

    json_out(['ok' => true, 'user' => $u,
        'wallet' => ['balance' => (float)$w['motta'], 'bonus' => (float)$w['bonus'], 'rebet' => (float)$w['rebet']],
        'banks' => $banks, 'deposits' => $dep, 'withdrawals' => $wd, 'bets' => $bets]);
}, 'users', false],

'user_balance' => [function ($conn, $in, $me) {
    $uid = (int)($in['uid'] ?? 0);
    $mode = trim((string)($in['mode'] ?? ''));
    $reason = trim((string)($in['reason'] ?? ''));
    if ($uid <= 0 || !user_exists($uid)) json_out(['ok' => false, 'error' => 'User not found']);
    if ($reason === '') json_out(['ok' => false, 'error' => 'Reason is required']);

    if ($mode === 'set') {
        $amount = (float)($in['amount'] ?? $in['delta'] ?? 0);
        if ($amount < 0) json_out(['ok' => false, 'error' => 'Amount cannot be negative']);
        $old = wallet_of($uid);
        [$ok, $newbal, $err] = wallet_set($uid, $amount);
        if (!$ok) json_out(['ok' => false, 'error' => $err]);
        alog('balance_set', $reason . " (old: " . $old['motta'] . " -> new: " . $newbal . ")", $uid, '', $amount);
        json_out(['ok' => true, 'balance' => (float)$newbal]);
    } else {
        $delta = (float)($in['delta'] ?? 0);
        if ($delta == 0 && isset($in['amount'])) {
            $amt = (float)$in['amount'];
            $delta = ($mode === 'deduct') ? -abs($amt) : abs($amt);
        }
        if ($delta == 0) json_out(['ok' => false, 'error' => 'Amount cannot be zero']);
        [$ok, $newbal, $err] = wallet_adjust($uid, $delta);
        if (!$ok) json_out(['ok' => false, 'error' => $err]);
        alog($delta > 0 ? 'balance_add' : 'balance_deduct', $reason, $uid, '', abs($delta));
        json_out(['ok' => true, 'balance' => (float)$newbal]);
    }
}, 'users', true],

'user_freeze' => [function ($conn, $in, $me) {
    $uid = (int)($in['uid'] ?? 0); $fz = !empty($in['freeze']) ? 1 : 0;
    if ($uid <= 0 || !user_exists($uid)) json_out(['ok' => false, 'error' => 'User not found']);
    $st = $conn->prepare("UPDATE shonu_subjects SET account_frozen=? WHERE id=?");
    $st->bind_param('ii', $fz, $uid); $st->execute(); $st->close();
    alog($fz ? 'freeze' : 'unfreeze', '', $uid);
    json_out(['ok' => true, 'frozen' => $fz]);
}, 'users', true],

'user_password' => [function ($conn, $in, $me) {
    json_out(['ok' => false, 'error' => 'Password changes are disabled in this demo.']);
}, 'users', true],

'user_contact' => [function ($conn, $in, $me) {
    $uid = (int)($in['uid'] ?? 0);
    $mob = trim((string)($in['mobile'] ?? '')); $mail = trim((string)($in['email'] ?? ''));
    if ($uid <= 0 || !user_exists($uid)) json_out(['ok' => false, 'error' => 'User not found']);
    $st = $conn->prepare("UPDATE shonu_subjects SET mobile=?, email=? WHERE id=?");
    $st->bind_param('ssi', $mob, $mail, $uid); $st->execute(); $st->close();
    alog('contact_update', $mob . ' / ' . $mail, $uid);
    json_out(['ok' => true]);
}, 'users', true],
];
