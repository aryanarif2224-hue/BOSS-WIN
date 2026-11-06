<?php
/* _mod/dashboard.php — headline stats for the admin home. */

return [

// V2 analytics: last 7 days series + top users + top games
'stats_series' => [function ($conn) {
    $days = [];
    for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = ['dep'=>0,'wd'=>0,'profit'=>0,'turn'=>0,'bets'=>0];
    $q = $conn->query("SELECT DATE(dinankavannuracisi) d, COALESCE(SUM(motta),0) a FROM thevani WHERE sthiti=1 AND dinankavannuracisi >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d");
    if ($q) while ($r = $q->fetch_assoc()) if (isset($days[$r['d']])) $days[$r['d']]['dep'] = (float)$r['a'];
    $q = $conn->query("SELECT DATE(dinankavannuracisi) d, COALESCE(SUM(motta),0) a FROM hintegedukolli WHERE sthiti=1 AND dinankavannuracisi >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d");
    if ($q) while ($r = $q->fetch_assoc()) if (isset($days[$r['d']])) $days[$r['d']]['wd'] = (float)$r['a'];
    $q = $conn->query("SELECT DATE(created_at) d, COALESCE(SUM(amount),0) t, COALESCE(SUM(win_lose_amount),0) w, COUNT(*) n FROM saas_bets WHERE settled=1 AND created_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d");
    if ($q) while ($r = $q->fetch_assoc()) if (isset($days[$r['d']])) { $days[$r['d']]['turn'] = (float)$r['t']; $days[$r['d']]['profit'] = -1 * (float)$r['w']; $days[$r['d']]['bets'] = (int)$r['n']; }
    $labels = array_keys($days);
    $series = ['labels' => $labels, 'deposits' => [], 'withdrawals' => [], 'profit' => [], 'turnover' => [], 'bets' => []];
    foreach ($days as $d) { $series['deposits'][] = $d['dep']; $series['withdrawals'][] = $d['wd']; $series['profit'][] = $d['profit']; $series['turnover'][] = $d['turn']; $series['bets'][] = $d['bets']; }

    $topUsers = [];
    $q = $conn->query("SELECT s.mobile, COALESCE(SUM(t.motta),0) amt, COUNT(*) n FROM thevani t JOIN shonu_subjects s ON s.id=t.balakedara WHERE t.sthiti=1 AND DATE(t.dinankavannuracisi)=CURDATE() GROUP BY s.id ORDER BY amt DESC LIMIT 5");
    if ($q) while ($r = $q->fetch_assoc()) $topUsers[] = ['mobile' => $r['mobile'], 'amount' => (float)$r['amt'], 'orders' => (int)$r['n']];

    $topGames = [];
    $q = $conn->query("SELECT game_code, COALESCE(SUM(amount),0) t, -1*COALESCE(SUM(win_lose_amount),0) p, COUNT(*) n FROM saas_bets WHERE settled=1 AND DATE(created_at)=CURDATE() GROUP BY game_code ORDER BY t DESC LIMIT 5");
    if ($q) while ($r = $q->fetch_assoc()) $topGames[] = ['game' => $r['game_code'], 'turnover' => (float)$r['t'], 'profit' => (float)$r['p'], 'bets' => (int)$r['n']];

    json_out(['ok' => true, 'series' => $series, 'topUsers' => $topUsers, 'topGames' => $topGames]);
}, 'dashboard', false],
'dashboard' => [function ($conn, $in, $me) {
    $one = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_row() : [0]; };

    // deposits today (thevani sthiti=1 = paid). motta is varchar -> cast.
    $depT = $one("SELECT COALESCE(SUM(motta),0), COUNT(*) FROM thevani WHERE sthiti=1 AND DATE(dinankavannuracisi)=CURDATE()");
    // withdrawals paid today
    $wdT  = $one("SELECT COALESCE(SUM(motta),0), COUNT(*) FROM hintegedukolli WHERE sthiti=1 AND DATE(dinankavannuracisi)=CURDATE()");
    // pending queues
    $wdP  = $one("SELECT COALESCE(SUM(motta),0), COUNT(*) FROM hintegedukolli WHERE sthiti=0");
    $depP = $one("SELECT COUNT(*) FROM thevani WHERE sthiti=0");
    $esc  = $one("SELECT COUNT(*) FROM support_workorders WHERE escalated=1 AND status NOT IN (3,4)");
    $openTk = $one("SELECT COUNT(*) FROM support_workorders WHERE status IN (1,2,5)");
    $onl  = $one("SELECT COUNT(*) FROM support_agents WHERE is_online=1 AND is_active=1");
    $users = $one("SELECT COUNT(*) FROM shonu_subjects");

    // game profit today = -SUM(win_lose_amount) over settled bets; turnover = SUM(amount)
    $gp = $one("SELECT COALESCE(SUM(amount),0), COALESCE(SUM(win_lose_amount),0), COUNT(*)
                FROM saas_bets WHERE settled=1 AND DATE(created_at)=CURDATE()");
    $turnover = (float)$gp[0];
    $playerPL = (float)$gp[1];          // player net (win positive)
    $gameProfit = -1 * $playerPL;       // house profit

    // overall today money profit (deposits in - withdrawals out) — the user's "pay-in - pay-out"
    $moneyProfit = (float)$depT[0] - (float)$wdT[0];

    json_out(['ok' => true, 'stats' => [
        'dep_today'    => ['amt' => (float)$depT[0], 'n' => (int)$depT[1]],
        'wd_today'     => ['amt' => (float)$wdT[0],  'n' => (int)$wdT[1]],
        'wd_pending'   => ['amt' => (float)$wdP[0],  'n' => (int)$wdP[1]],
        'dep_pending'  => (int)$depP[0],
        'escalations'  => (int)$esc[0],
        'open_tickets' => (int)$openTk[0],
        'online_staff' => (int)$onl[0],
        'users'        => (int)$users[0],
        'money_profit' => $moneyProfit,
        'game'         => ['turnover' => $turnover, 'profit' => $gameProfit, 'bets' => (int)$gp[2]],
    ]]);
}, 'dashboard', false],
];
