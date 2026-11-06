<?php
/* _mod/site.php — Site content manager (V2).
 * - site_games   : catalogue (from captured GetAllGameList) + on/off/maintain state
 * - game_set     : toggle a game visible / maintenance
 * - hbanners     : home banner list (drives main-site GetBannerList)
 * - hbanner_add / hbanner_toggle / hbanner_del
 * - gc_*         : gift codes (create/list/toggle/del + redemptions)
 * All backed by tables in the main site DB; the main-site webapi reads them.
 */
if (!function_exists('alog')) { function alog($a, $d = '') { if (function_exists('admin_log')) admin_log($a, $d); } }

$__catalogue = null;
function site_catalogue() {
    global $__catalogue;
    if ($__catalogue !== null) return $__catalogue;
    $f = '/raxi/bdg/shreewin_data/GetAllGameList.json';
    $out = [];
    if (is_file($f)) {
        $d = json_decode(file_get_contents($f), true);
        $data = $d['data'] ?? [];
        $walk = function ($node) use (&$walk, &$out) {
            if (!is_array($node)) return;
            if (array_keys($node) !== range(0, count($node) - 1)) { foreach ($node as $v) $walk($v); return; }
            foreach ($node as $item) {
                if (is_array($item) && isset($item['gameCode'])) {
                    $gc = (string)$item['gameCode'];
                    if (!isset($out[$gc])) $out[$gc] = [
                        'gameCode' => $gc,
                        'name' => (string)($item['gameNameEn'] ?? $item['gameName'] ?? $gc),
                        'vendor' => (string)($item['vendorCode'] ?? ''),
                        'imgUrl' => (string)($item['imgUrl'] ?? $item['img'] ?? ''),
                        'category' => (string)($item['categoryName'] ?? ''),
                    ];
                    foreach ($item as $vv) if (is_array($vv)) $walk($vv);
                } else { $walk($item); }
            }
        };
        $walk($data);
    }
    $__catalogue = array_values($out);
    return $__catalogue;
}

return [

'site_games' => [function ($conn, $in) {
    $cat = site_catalogue();
    $ov = [];
    $q = $conn->query("SELECT game_code, visible, maintain FROM site_game_controls");
    if ($q) while ($r = $q->fetch_assoc()) $ov[$r['game_code']] = $r;
    foreach ($cat as &$c) {
        $c['visible']  = isset($ov[$c['gameCode']]) ? (int)$ov[$c['gameCode']]['visible'] : 1;
        $c['maintain'] = isset($ov[$c['gameCode']]) ? (int)$ov[$c['gameCode']]['maintain'] : 0;
    }
    json_out(['ok' => true, 'games' => $cat, 'count' => count($cat)]);
}, 'games', false],

'game_set' => [function ($conn, $in) {
    $gc = trim((string)($in['gameCode'] ?? ''));
    if ($gc === '') json_out(['ok' => false, 'error' => 'gameCode required'], 400);
    $cur = null;
    $q = $conn->query("SELECT visible, maintain FROM site_game_controls WHERE game_code='" . $conn->real_escape_string($gc) . "'");
    if ($q && $q->num_rows) $cur = $q->fetch_assoc();
    $visible  = isset($in['visible'])  ? (int)(bool)$in['visible']  : (int)($cur['visible'] ?? 1);
    $maintain = isset($in['maintain']) ? (int)(bool)$in['maintain'] : (int)($cur['maintain'] ?? 0);
    $esc = $conn->real_escape_string($gc);
    $conn->query("INSERT INTO site_game_controls (game_code, visible, maintain) VALUES ('$esc', $visible, $maintain)
                  ON DUPLICATE KEY UPDATE visible=$visible, maintain=$maintain");
    alog('game_set', $gc . ' v=' . $visible . ' m=' . $maintain);
    json_out(['ok' => true, 'gameCode' => $gc, 'visible' => $visible, 'maintain' => $maintain]);
}, 'games', true],

'hbanners' => [function ($conn) {
    $list = [];
    $q = $conn->query("SELECT id,title,image_url,link_url,sort,active,created_at FROM site_home_banners ORDER BY sort ASC, id ASC");
    if ($q) while ($r = $q->fetch_assoc()) $list[] = $r;
    json_out(['ok' => true, 'banners' => $list]);
}, 'banners', false],

'hbanner_add' => [function ($conn, $in) {
    $title = trim((string)($in['title'] ?? ''));
    $img   = trim((string)($in['image_url'] ?? ''));
    $link  = trim((string)($in['link_url'] ?? ''));
    $sort  = (int)($in['sort'] ?? 0);
    if ($img === '') json_out(['ok' => false, 'error' => 'image_url required'], 400);
    $st = $conn->prepare("INSERT INTO site_home_banners (title,image_url,link_url,sort,active) VALUES (?,?,?,?,1)");
    $st->bind_param('sssi', $title, $img, $link, $sort); $st->execute(); $id = $st->insert_id; $st->close();
    alog('hbanner_add', '#' . $id . ' ' . $title);
    json_out(['ok' => true, 'id' => $id]);
}, 'banners', true],

'hbanner_toggle' => [function ($conn, $in) {
    $id = (int)($in['id'] ?? 0); $on = (int)(bool)($in['active'] ?? 0);
    if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
    $conn->query("UPDATE site_home_banners SET active=$on WHERE id=$id");
    alog('hbanner_toggle', '#' . $id . ' -> ' . $on);
    json_out(['ok' => true]);
}, 'banners', true],

'hbanner_del' => [function ($conn, $in) {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
    $conn->query("DELETE FROM site_home_banners WHERE id=$id");
    alog('hbanner_del', '#' . $id);
    json_out(['ok' => true]);
}, 'banners', true],

'gc_list' => [function ($conn) {
    $list = [];
    $q = $conn->query("SELECT id,code,amount,max_uses,used,active,expires_at,note,created_at FROM gift_codes ORDER BY id DESC");
    if ($q) while ($r = $q->fetch_assoc()) $list[] = $r;
    json_out(['ok' => true, 'codes' => $list]);
}, 'settings', false],

'gc_create' => [function ($conn, $in) {
    $amount = max(0, (float)($in['amount'] ?? 0));
    $maxUses = max(1, (int)($in['max_uses'] ?? 1));
    $note = trim((string)($in['note'] ?? ''));
    $expires = null;
    if (!empty($in['expires_at'])) {
        $ts = strtotime((string)$in['expires_at']);
        if ($ts && $ts > 0) {
            $expires = date('Y-m-d H:i:s', $ts);
        }
    }
    if ($amount <= 0) json_out(['ok' => false, 'error' => 'amount required'], 400);
    $code = strtoupper(trim((string)($in['code'] ?? '')));
    if ($code === '') $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $st = $conn->prepare("INSERT INTO gift_codes (code,amount,max_uses,note,expires_at,active) VALUES (?,?,?,?,?,1)");
    if (!$st) {
        json_out(['ok' => false, 'error' => 'Database error: ' . $conn->error]);
    }
    $st->bind_param('sdiss', $code, $amount, $maxUses, $note, $expires);
    if (!$st->execute()) { json_out(['ok' => false, 'error' => 'Code exists or invalid: ' . $st->error]); }
    $id = $st->insert_id; $st->close();
    alog('gc_create', $code . ' amt=' . $amount);
    json_out(['ok' => true, 'id' => $id, 'code' => $code]);
}, 'settings', true],

'gc_toggle' => [function ($conn, $in) {
    $id = (int)($in['id'] ?? 0); $on = (int)(bool)($in['active'] ?? 0);
    if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
    $conn->query("UPDATE gift_codes SET active=$on WHERE id=$id");
    alog('gc_toggle', '#' . $id . ' -> ' . $on);
    json_out(['ok' => true]);
}, 'settings', true],

'gc_del' => [function ($conn, $in) {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
    $conn->query("DELETE FROM gift_codes WHERE id=$id");
    alog('gc_del', '#' . $id);
    json_out(['ok' => true]);
}, 'settings', true],

'gc_redeems' => [function ($conn) {
    $list = [];
    $q = $conn->query("SELECT g.id,g.code,g.user_id,g.amount,g.created_at,s.mobile
                       FROM gift_redemptions g LEFT JOIN shonu_subjects s ON s.id=g.user_id
                       ORDER BY g.id DESC LIMIT 200");
    if ($q) while ($r = $q->fetch_assoc()) $list[] = $r;
    json_out(['ok' => true, 'redeems' => $list]);
}, 'settings', false],

];
