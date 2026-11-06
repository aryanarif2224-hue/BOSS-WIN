<?php
// vip.php — on-bet hook: accumulates rebate turnover + VIP exp, awards level-up.
// Caller must set: $conn, $byabaharkarta (user id), $totalamount (total staked).
// Wrapped so a failure here never breaks the bet response.

if (!isset($shnunc)) $shnunc = date("Y-m-d H:i:s");
if (!isset($byabaharkarta) || !isset($totalamount) || !isset($conn)) {
    return;
}

$uid = intval($byabaharkarta);
$tot = floatval($totalamount);

try {
    // 1) Rebate turnover — running total, paid out by AddCodeWashRecord (Laundry).
    $reb = $conn->prepare("UPDATE shonu_kaichila SET rebet = rebet + ? WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
    $reb->bind_param("di", $tot, $uid);
    $reb->execute();
    $reb->close();

    // 2) VIP exp + level.
    $thresholds = [ // expe needed => level
        2000000 => 5, 4000000 => 4, 400000 => 3, 30000 => 2, 3000 => 1,
    ];
    $rewards = [ // per-level-up award (sum credited to wallet)
        1 => 90, 2 => 270, 3 => 980, 4 => 2780, 5 => 8790,
    ];
    $level_of = function ($expe) use ($thresholds) {
        foreach ($thresholds as $need => $lv) if ($expe >= $need) return $lv;
        return 1;
    };

    $vipQuery = $conn->prepare("SELECT expe, lvl FROM vip WHERE userid = ?");
    $vipQuery->bind_param("i", $uid);
    $vipQuery->execute();
    $vipQuery->store_result();

    if ($vipQuery->num_rows > 0) {
        $vipQuery->bind_result($expe_db, $lvl_db);
        $vipQuery->fetch();
        $vipQuery->close();

        $newExpe = floatval($expe_db) + $tot;
        $orlvl   = intval($lvl_db);
        $lvl     = $level_of($newExpe);

        $giveamt = 0;
        for ($lv = $orlvl + 1; $lv <= $lvl; $lv++) {
            if (isset($rewards[$lv])) $giveamt += $rewards[$lv];
        }

        $upd = $conn->prepare("UPDATE vip SET expe = ?, lvl = ? WHERE userid = ?");
        $ex = (string)$newExpe; $lv = (string)$lvl; $u = (string)$uid;
        $upd->bind_param("sss", $ex, $lv, $u);
        $upd->execute();
        $upd->close();
    } else {
        $vipQuery->close();
        $newExpe = $tot;
        $lvl     = $level_of($newExpe);

        $giveamt = 0;
        for ($lv = 1; $lv <= $lvl; $lv++) {
            if (isset($rewards[$lv])) $giveamt += $rewards[$lv];
        }

        $ins = $conn->prepare("INSERT INTO vip (userid, expe, lvl) VALUES (?, ?, ?)");
        $ex = (string)$newExpe; $lv = (string)$lvl; $u = (string)$uid;
        $ins->bind_param("sss", $u, $ex, $lv);
        $ins->execute();
        $ins->close();
    }

    // 3) Credit level-up reward to wallet.
    if (!empty($giveamt) && $giveamt > 0) {
        $upd2 = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
        $upd2->bind_param("di", $giveamt, $uid);
        $upd2->execute();
        $upd2->close();
    }
} catch (\Throwable $e) {
    // never break the bet on a rebate/vip error
    @error_log("vip.php hook error for uid $uid: " . $e->getMessage());
}
