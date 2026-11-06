<?php
// CLI cron: settle any matured SaaS bets whose result is now known, for every
// user with pending bets. Run every minute:
//   * * * * * /usr/bin/php /bdg/bdg/saas/cron_settle.php >/dev/null 2>&1
//
// It first refreshes results from the draw server for each game that has pending
// bets, then settles.

include "/raxi/bdg/conn.php";
include "/raxi/bdg/functions2.php";
include "/raxi/bdg/saas_lib.php";
include "/raxi/bdg/saas/game_info.php";
include "/raxi/bdg/saas/settle.php";

// 1) For each distinct game with unsettled bets, pull fresh history so results
//    get persisted into saas_results.
$gq = $conn->query("SELECT DISTINCT game_code, lottery FROM saas_bets WHERE settled = 0");
if ($gq) {
    while ($g = $gq->fetch_assoc()) {
        $lottery  = $g['lottery'];
        $gameCode = $g['game_code'];
        $body = saas_fetch_draw("$lottery/$gameCode/GetHistoryIssuePage.json", 0); // force fresh
        if ($body === null) continue;
        $j = json_decode($body, true);
        if (!isset($j['data']['list'])) continue;
        foreach ($j['data']['list'] as $row) {
            $iss = $conn->real_escape_string($row['issueNumber'] ?? '');
            if ($iss === '') continue;
            $num = $conn->real_escape_string($row['number'] ?? '');
            $col = $conn->real_escape_string($row['color'] ?? '');
            $pre = $conn->real_escape_string($row['premium'] ?? '');
            $sm  = (int)($row['sum'] ?? 0);
            $gcE = $conn->real_escape_string($gameCode);
            $conn->query("INSERT INTO saas_results (game_code,issue_number,number,color,premium,sum_val,is_protected)
                          VALUES ('$gcE','$iss','$num','$col','$pre',$sm, 0)
                          ON DUPLICATE KEY UPDATE 
                            number=IF(is_protected=1, number, VALUES(number)),
                            color=IF(is_protected=1, color, VALUES(color)),
                            premium=IF(is_protected=1, premium, VALUES(premium)),
                            sum_val=IF(is_protected=1, sum_val, VALUES(sum_val))");
        }
        // Include auto-protect optimizer
        require_once __DIR__ . '/auto_protect.php';
        // Auto-protect against heavy bets on pending unsettled issues
        $open_issues_q = $conn->query("SELECT DISTINCT issue_number FROM saas_bets WHERE game_code='$gcE' AND settled = 0");
        if ($open_issues_q) {
            while ($oi = $open_issues_q->fetch_assoc()) {
                saas_protect_and_optimize_draw($conn, $gameCode, $oi['issue_number']);
            }
        }
        // admin-set manual results take precedence over upstream numbers
        saas_apply_manual($conn, $gameCode);
    }
}

// 2) Settle for every user that has pending bets.
$uq = $conn->query("SELECT DISTINCT user_id FROM saas_bets WHERE settled = 0");
$n = 0;
if ($uq) {
    while ($u = $uq->fetch_assoc()) {
        saas_settle_user($conn, (int)$u['user_id']);
        $n++;
    }
}
echo date('Y-m-d H:i:s') . " settled pass for $n user(s)\n";
?>
