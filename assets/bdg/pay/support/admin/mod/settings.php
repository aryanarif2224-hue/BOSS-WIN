<?php
/* _mod/settings.php — banners (images / images_usdt) + site settings
 * (maintenance toggle + recharge/withdraw limits in parametredepaiement). */

return [
/* ---------------- banners (perm: banners) ---------------- */
'banners' => [function ($conn, $in, $me) {
    $out = ['inr' => [], 'usdt' => []];
    foreach (['inr' => 'images', 'usdt' => 'images_usdt'] as $k => $tbl) {
        $r = $conn->query("SELECT id,filename,status,time FROM `$tbl` ORDER BY id DESC");
        while ($x = $r->fetch_assoc()) $out[$k][] = ['id' => (int)$x['id'], 'filename' => $x['filename'], 'status' => (int)$x['status'], 'time' => $x['time']];
    }
    json_out(['ok' => true] + $out);
}, 'banners', false],

'banner_toggle' => [function ($conn, $in, $me) {
    $set = ($in['set'] ?? 'inr') === 'usdt' ? 'images_usdt' : 'images';
    $id = (int)($in['id'] ?? 0); $on = !empty($in['on']) ? 1 : 0;
    $st = $conn->prepare("UPDATE `$set` SET status=? WHERE id=?"); $st->bind_param('ii', $on, $id); $st->execute(); $st->close();
    alog('banner_toggle', $set . ' #' . $id . ' -> ' . $on);
    json_out(['ok' => true]);
}, 'banners', true],

'banner_delete' => [function ($conn, $in, $me) {
    $set = ($in['set'] ?? 'inr') === 'usdt' ? 'images_usdt' : 'images';
    $id = (int)($in['id'] ?? 0);
    $st = $conn->prepare("DELETE FROM `$set` WHERE id=?"); $st->bind_param('i', $id); $st->execute(); $st->close();
    alog('banner_delete', $set . ' #' . $id);
    json_out(['ok' => true]);
}, 'banners', true],

'banner_add' => [function ($conn, $in, $me) {
    $set = ($in['set'] ?? 'inr') === 'usdt' ? 'images_usdt' : 'images';
    $fn = trim((string)($in['filename'] ?? ''));
    if ($fn === '') json_out(['ok' => false, 'error' => 'Image filename/URL required']);
    $now = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO `$set` (filename,status,time) VALUES (?,1,?)"); $st->bind_param('ss', $fn, $now); $st->execute(); $id = $st->insert_id; $st->close();
    alog('banner_add', $set . ' ' . $fn);
    json_out(['ok' => true, 'id' => $id]);
}, 'banners', true],

/* ---------------- site settings (perm: settings) ---------------- */
'settings_get' => [function ($conn, $in, $me) {
    $m = $conn->query("SELECT status,message FROM maintenance ORDER BY id LIMIT 1");
    $maint = $m ? $m->fetch_assoc() : null;
    $p = $conn->query("SELECT rechargeamount,withdrawalamount,bonusamount,rechargebonus FROM parametredepaiement LIMIT 1");
    $param = $p ? $p->fetch_assoc() : null;
    json_out(['ok' => true, 'maintenance' => $maint, 'params' => $param]);
}, 'settings', false],

'settings_save' => [function ($conn, $in, $me) {
    if (isset($in['maintenance'])) {
        $on = !empty($in['maintenance']['on']) ? 'active' : 'inactive';
        $msg = trim((string)($in['maintenance']['message'] ?? ''));
        $has = $conn->query("SELECT id FROM maintenance ORDER BY id LIMIT 1");
        if ($has && ($row = $has->fetch_assoc())) {
            $st = $conn->prepare("UPDATE maintenance SET status=?, message=? WHERE id=?");
            $st->bind_param('ssi', $on, $msg, $row['id']); $st->execute(); $st->close();
        } else {
            $st = $conn->prepare("INSERT INTO maintenance (status,message) VALUES (?,?)");
            $st->bind_param('ss', $on, $msg); $st->execute(); $st->close();
        }
        alog('maintenance', $on);
    }
    if (isset($in['params']) && is_array($in['params'])) {
        $p = $in['params'];
        $ra = (string)($p['rechargeamount'] ?? ''); $wa = (string)($p['withdrawalamount'] ?? '');
        $ba = (string)($p['bonusamount'] ?? ''); $rb = (string)($p['rechargebonus'] ?? '');
        $has = $conn->query("SELECT COUNT(*) c FROM parametredepaiement")->fetch_assoc()['c'] ?? 0;
        if ((int)$has > 0) {
            $st = $conn->prepare("UPDATE parametredepaiement SET rechargeamount=?, withdrawalamount=?, bonusamount=?, rechargebonus=?");
            $st->bind_param('ssss', $ra, $wa, $ba, $rb); $st->execute(); $st->close();
            alog('payment_params', 'updated');
        }
    }
    json_out(['ok' => true]);
}, 'settings', true],
];
