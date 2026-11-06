<?php
/* commission.php — 6-level upline commission on every settled bet stake.
 *
 * Called by game handlers (GameBetting / K3 / 5D / TRX) AND the self-hosted SaaS
 * engine (saas/bet.php) after a bet is placed and the stake deducted.
 *
 * Caller must set:  $conn, $byabaharkarta (bettor uid), $totalamount (stake),
 *                   $shnunc (datetime), and optionally $comm_ref (unique bet ref).
 *
 * Rates come from the `web_commission` table (level1..level6 = percent, e.g. 0.85).
 * Each upline in the referral chain (shonu_subjects.code..code5) is credited and
 * the payout is logged to `vyavahara` (legacy) and `commission_log` (with a
 * UNIQUE (ref,level) guard so a retry can never double-pay).
 *
 * Safe to include multiple times — the work is wrapped in a function.
 */

if (!function_exists('sw_award_bet_commission')) {

    function sw_commission_rates($conn) {
        $rates = [1 => 0.85, 2 => 0.75, 3 => 0.50, 4 => 0.35, 5 => 0.20, 6 => 0.15];
        $q = $conn->query("SELECT level1,level2,level3,level4,level5,level6 FROM web_commission ORDER BY id LIMIT 1");
        if ($q && $r = $q->fetch_assoc()) {
            for ($i = 1; $i <= 6; $i++) {
                if ($r['level' . $i] !== null) $rates[$i] = (float)$r['level' . $i];
            }
        }
        return $rates;
    }

    function sw_award_bet_commission($conn, $bettorId, $stake, $shnunc = null, $ref = null) {
        $bettorId = (int)$bettorId;
        $stake    = (float)$stake;
        if ($bettorId <= 0 || $stake <= 0) return 0.0;

        if ($shnunc === null) $shnunc = date('Y-m-d H:i:s');
        // fallback ref: bettor + stake + minute bucket (best-effort uniqueness)
        if ($ref === null || $ref === '') $ref = 'B' . $bettorId . '-' . md5($stake . '|' . date('YmdHi'));

        // load the 6-level upline codes for this bettor
        $st = $conn->prepare("SELECT code, code1, code2, code3, code4, code5 FROM shonu_subjects WHERE id = ? LIMIT 1");
        $st->bind_param('i', $bettorId); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        if (!$row) return 0.0;

        $codes  = [$row['code'], $row['code1'], $row['code2'], $row['code3'], $row['code4'], $row['code5']];
        $rates  = sw_commission_rates($conn);
        $totalPaid = 0.0;

        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $code = $codes[$lvl - 1] ?? null;
            if ($code === null || $code === '') continue;

            // resolve upline user id from owncode
            $st = $conn->prepare("SELECT id FROM shonu_subjects WHERE owncode = ? LIMIT 1");
            $st->bind_param('s', $code); $st->execute();
            $up = $st->get_result()->fetch_assoc(); $st->close();
            if (!$up) continue;
            $uplineId = (int)$up['id'];
            if ($uplineId <= 0 || $uplineId === $bettorId) continue;

            $amount = round($stake * $rates[$lvl] / 100.0, 4);
            if ($amount <= 0) continue;

            // idempotent log insert — if this (ref,level) already paid, skip
            $tag = 'LVLCOMM' . $lvl;
            $st = $conn->prepare("INSERT IGNORE INTO commission_log (ref, bettor_id, level, upline_id, stake, rate, amount, created_at)
                                  VALUES (?,?,?,?,?,?,?,?)");
            $st->bind_param('siiiddds', $ref, $bettorId, $lvl, $uplineId, $stake, $rates[$lvl], $amount, $shnunc);
            $st->execute();
            $inserted = $st->affected_rows; $st->close();
            if ($inserted < 1) continue;   // already paid for this bet/level

            // credit the upline wallet — UPDATE the newest existing row only.
            // NEVER insert a new wallet row (shonu_kaichila has no UNIQUE on
            // balakedara; the app reads the newest row, so we must not fork it).
            $st = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
            $st->bind_param('di', $amount, $uplineId); $st->execute();
            $credited = $st->affected_rows; $st->close();
            if ($credited < 1) {
                // no wallet row exists for this upline — skip quietly (no fork)
                $conn->query("DELETE FROM commission_log WHERE ref='" . $conn->real_escape_string($ref) . "' AND level=$lvl");
                continue;
            }

            // legacy audit row in vyavahara
            $bal = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = $uplineId ORDER BY kramasankhye DESC LIMIT 1")->fetch_row()[0] ?? 0;
            $nuamotta = round((float)$bal, 2);
            $purba = round($nuamotta - $amount, 2);
            $ketebida = (string)$stake; $ayoga = (string)$amount;
            $st = $conn->prepare("INSERT INTO vyavahara (balakedara, ketebida, prakara, purba, bartaman, ayoga, koduvavanu, tiarikala)
                                  VALUES (?,?,?,?,?,?,?,?)");
            $st->bind_param('isssssss', $uplineId, $ketebida, $tag, $purba, $nuamotta, $ayoga, $bettorId, $shnunc);
            $st->execute(); $st->close();

            $totalPaid += $amount;
        }
        return $totalPaid;
    }
}

// ---- backward-compatible auto-run when included by legacy game handlers ----
if (isset($conn) && isset($byabaharkarta) && isset($totalamount)) {
    if (!isset($shnunc)) $shnunc = date('Y-m-d H:i:s');
    $__ref = isset($comm_ref) ? $comm_ref : null;
    sw_award_bet_commission($conn, (int)$byabaharkarta, (float)$totalamount, $shnunc, $__ref);
}
?>
