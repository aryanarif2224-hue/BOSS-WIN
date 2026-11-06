<?php
/* _mod/games.php — Lottery manager (V2).
 * - games       : every lottery type (WinGo/K3/5D/TRX/MotoRace) with today's stats
 * - game_live   : per-game detail (open bets, countdown, results, manual rows, today)
 * - manual_list / manual_set / manual_clear : admin-set result override.
 * Manual results are applied on the SaaS side (saas_apply_manual in
 * saas_lib.php, called from draw_router.php + cron_settle.php) so bets settle
 * on the admin number instead of the upstream draw when one is set. */

if (!function_exists('saas_fetch_draw')) require_once '/raxi/bdg/saas_lib.php';

function game_interval_secs($code) {
    if (!preg_match('/_([0-9]+)([SM])$/', $code, $m)) return 60;
    $n = (int)$m[1]; return $m[2] === 'S' ? $n : $n * 60;
}
function game_label($code) {
    $map = ['WinGo' => 'Win Go', 'K3' => 'K3', 'D5' => '5D', 'TRX' => 'TRX', 'MotoRace' => 'MotoRace'];
    $fam = explode('_', $code)[0];
    $d = game_interval_secs($code);
    $dur = $d < 60 ? $d . 's' : ($d / 60) . 'm';
    return ($map[$fam] ?? $fam) . ' ' . $dur;
}
function master_codes() {
    $out = [];
    foreach ([['WinGo', ['WinGo_30S','WinGo_1M','WinGo_3M','WinGo_5M']],
              ['K3',    ['K3_1M','K3_3M','K3_5M','K3_10M']],
              ['D5',    ['D5_1M','D5_3M','D5_5M','D5_10M']],
              ['TRX',   ['TRX_1M','TRX_3M','TRX_5M','TRX_10M']],
              ['MotoRace', ['MotoRace_1M']]] as $g) foreach ($g[1] as $c) $out[] = $c;
    return $out;
}

return [

// all lottery types with today's turnover/profit/bets (+ open)
'games' => [function ($conn) {
    $codes = master_codes();
    $r = $conn->query("SELECT DISTINCT game_code FROM saas_bets");
    if ($r) while ($x = $r->fetch_row()) if (!in_array($x[0], $codes, true)) $codes[] = $x[0];

    $out = [];
    foreach ($codes as $code) {
        $gc = $conn->real_escape_string($code);
        $td = $conn->query("SELECT COALESCE(SUM(amount),0), COALESCE(SUM(win_lose_amount),0), COUNT(*)
                            FROM saas_bets WHERE game_code='$gc' AND settled=1 AND DATE(created_at)=CURDATE()")->fetch_row();
        $open = $conn->query("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM saas_bets WHERE game_code='$gc' AND settled=0")->fetch_row();
        $man = (int)$conn->query("SELECT COUNT(*) FROM saas_manual_results WHERE game_code='$gc' AND state=1")->fetch_row()[0];
        $out[] = [
            'code' => $code, 'label' => game_label($code), 'family' => explode('_', $code)[0],
            'interval' => game_interval_secs($code),
            'turnover_today' => (float)$td[0], 'profit_today' => -1 * (float)$td[1], 'bets_today' => (int)$td[2],
            'open_bets' => (int)$open[0], 'open_amount' => (float)$open[1],
            'manual' => $man,
        ];
    }
    json_out(['ok' => true, 'games' => $out]);
}, 'games', false],

// live view for one game: countdown + open bets + results + manual rows + today stats
'game_live' => [function ($conn, $in) {
    $code = (string)($in['code'] ?? '');
    if ($code === '' || !preg_match('/^[A-Za-z0-9_]+$/', $code)) json_out(['ok' => false, 'error' => 'Bad game']);
    $gc = $conn->real_escape_string($code);
    $iv = game_interval_secs($code);
    $left = $iv - (time() % $iv);

    $bets = []; $tot = 0.0;
    $st = $conn->prepare("SELECT b.user_id, s.mobile, b.issue_number, b.play_type, b.bet_content, b.amount, b.created_at
                          FROM saas_bets b LEFT JOIN shonu_subjects s ON s.id=b.user_id
                          WHERE b.game_code=? AND b.settled=0 ORDER BY b.id DESC LIMIT 100");
    $st->bind_param('s', $code); $st->execute(); $r = $st->get_result();
    while ($x = $r->fetch_assoc()) { $tot += (float)$x['amount']; $bets[] = [
        'uid' => (int)$x['user_id'], 'mobile' => $x['mobile'], 'issue' => $x['issue_number'],
        'play' => $x['play_type'], 'pick' => $x['bet_content'], 'amount' => (float)$x['amount'], 'at' => $x['created_at'],
    ]; }
    $st->close();

    $results = [];
    $r = $conn->query("SELECT issue_number,number,color,premium,sum_val,created_at FROM saas_results WHERE game_code='$gc' ORDER BY id DESC LIMIT 12");
    if ($r) while ($x = $r->fetch_assoc()) $results[] = $x;

    $manual = [];
    $r = $conn->query("SELECT issue_number,number,color,premium,sum_val,state,updated_at FROM saas_manual_results WHERE game_code='$gc' ORDER BY updated_at DESC LIMIT 12");
    if ($r) while ($x = $r->fetch_assoc()) $manual[] = $x;

    $td = $conn->query("SELECT COALESCE(SUM(amount),0), COALESCE(SUM(win_lose_amount),0), COUNT(*)
                        FROM saas_bets WHERE game_code='$gc' AND settled=1 AND DATE(created_at)=CURDATE()")->fetch_row();

    // current issue from upstream (admin may target it for a manual result)
    $currentIssue = null;
    $fam = explode('_', $code)[0];
    $body = saas_fetch_draw("$fam/$code.json", 2);
    if ($body) { $j = json_decode($body, true); if (isset($j['current']['issueNumber'])) $currentIssue = $j['current']['issueNumber']; }

    json_out(['ok' => true, 'code' => $code, 'label' => game_label($code), 'family' => $fam, 'interval' => $iv,
        'seconds_left' => $left, 'open_stake' => $tot, 'open_count' => count($bets),
        'bets' => $bets, 'results' => $results, 'manual' => $manual,
        'current_issue' => $currentIssue,
        'today' => ['turnover' => (float)$td[0], 'profit' => -1 * (float)$td[1], 'bets' => (int)$td[2]]]);
}, 'games', false],

'manual_list' => [function ($conn, $in) {
    $code = (string)($in['code'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) json_out(['ok' => false, 'error' => 'Bad game']);
    $gc = $conn->real_escape_string($code);
    $rows = [];
    $r = $conn->query("SELECT issue_number,number,color,premium,sum_val,state,updated_at FROM saas_manual_results WHERE game_code='$gc' ORDER BY updated_at DESC LIMIT 50");
    if ($r) while ($x = $r->fetch_assoc()) $rows[] = $x;
    json_out(['ok' => true, 'manual' => $rows]);
}, 'games', false],

'manual_set' => [function ($conn, $in) {
    $code = (string)($in['code'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) json_out(['ok' => false, 'error' => 'Bad game']);
    $gc = $conn->real_escape_string($code);
    $fam = explode('_', $code)[0];

    $issue = trim((string)($in['issue'] ?? ''));
    if ($issue === '') {
        $body = saas_fetch_draw("$fam/$code.json", 0);
        if ($body) { $j = json_decode($body, true); $issue = (string)($j['current']['issueNumber'] ?? ''); }
        if ($issue === '') json_out(['ok' => false, 'error' => 'Could not detect current issue']);
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $issue)) json_out(['ok' => false, 'error' => 'Bad issue']);

    $number  = (string)($in['number'] ?? '');
    $color   = (string)($in['color'] ?? '');
    $premium = (string)($in['premium'] ?? '');
    $sum     = max(0, (int)($in['sum'] ?? 0));

    if (in_array($fam, ['WinGo', 'TRX'], true)) {
        if ($number === '' || !ctype_digit($number) || (int)$number < 0 || (int)$number > 9) json_out(['ok' => false, 'error' => 'Pick a number 0-9']);
        if ($premium === '') $premium = $number;
        $n = (int)$number;
        if ($color === '' || $color === 'auto') {
            if ($n === 0) $color = 'red,violet';
            elseif ($n === 5) $color = 'green,violet';
            elseif (in_array($n, [1, 3, 7, 9], true)) $color = 'green';
            else $color = 'red';
        } elseif ($n === 0 && !str_contains($color, 'violet')) {
            $color = 'red,violet';
        } elseif ($n === 5 && !str_contains($color, 'violet')) {
            $color = 'green,violet';
        }
        $sum = $n;
    }
    if ($fam === 'K3') { if ($premium === '' || !preg_match('/^[1-6](?:,[1-6]){2}$/', $premium)) json_out(['ok' => false, 'error' => 'K3 needs 3 dice (1-6), e.g. 4,1,6']); if (!$sum) { $d = explode(',', $premium); $sum = array_sum($d); } }
    if ($fam === 'D5') { if ($premium === '' || !preg_match('/^[0-9]{5}$/', $premium)) json_out(['ok' => false, 'error' => '5D needs 5 digits (0-9)']); if (!$sum) { $d = str_split($premium); $sum = array_sum($d); } }
    if ($fam === 'MotoRace') { if ($premium === '') json_out(['ok' => false, 'error' => 'MotoRace needs a ranking']); }

    $st = $conn->prepare("INSERT INTO saas_manual_results (game_code,issue_number,number,color,premium,sum_val,state)
                          VALUES (?,?,?,?,?,?,1)
                          ON DUPLICATE KEY UPDATE number=VALUES(number),color=VALUES(color),premium=VALUES(premium),sum_val=VALUES(sum_val),state=1,updated_at=NOW()");
    $st->bind_param('sssssi', $code, $issue, $number, $color, $premium, $sum);
    $st->execute(); $st->close();

    $st2 = $conn->prepare("INSERT INTO saas_results (game_code, issue_number, number, color, premium, sum_val)
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE number=VALUES(number), color=VALUES(color), premium=VALUES(premium), sum_val=VALUES(sum_val)");
    if ($st2) {
        $st2->bind_param('sssssi', $code, $issue, $number, $color, $premium, $sum);
        $st2->execute();
        $st2->close();
    }
    alog('manual_set', "$code $issue -> n=$number p=$premium");
    json_out(['ok' => true, 'code' => $code, 'issue' => $issue, 'number' => $number, 'color' => $color, 'premium' => $premium, 'sum' => $sum]);
}, 'games', true],

'manual_clear' => [function ($conn, $in) {
    $code = (string)($in['code'] ?? ''); $issue = (string)($in['issue'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) json_out(['ok' => false, 'error' => 'Bad game']);
    $st = $conn->prepare("DELETE FROM saas_manual_results WHERE game_code=? AND issue_number=?");
    $st->bind_param('ss', $code, $issue); $st->execute(); $st->close();
    alog('manual_clear', "$code $issue");
    json_out(['ok' => true]);
}, 'games', true],

];
