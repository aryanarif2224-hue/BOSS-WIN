<?php
// Settlement engine — decides win/loss for a settled issue using the REAL
// drawn result pulled from the upstream draw server (stored in saas_results).
//
// A saas_results row has: number, color, premium, sum_val.
//   WinGo    : number 0-9, color "red"/"green"/"violet" (0=red,violet; 5=green,violet)
//   K3       : premium "d,d,d" or "ddd" (three dice 1-6), sum_val = total
//   D5       : premium "ddddd" (five digits 0-9), sum_val = total
//   MotoRace : premium "n,n,...,n" ranking of 10 racers (positions 1..10)

// Returns true if the bet wins against the given result row, else false.
// $betContent is "PlayType_Bet" e.g. "BigSmall_Big", "SumNum_15", "FirstNum_3".
function saas_bet_wins($lottery, $betContent, $result) {
    $pos = strrpos($betContent, '_');
    if ($pos === false) return false;
    $playType = substr($betContent, 0, $pos);
    $bet      = substr($betContent, $pos + 1);

    if ($lottery === 'WinGo')    return saas_wins_wingo($playType, $bet, $result);
    if ($lottery === 'K3')       return saas_wins_k3($playType, $bet, $result);
    if ($lottery === 'D5')       return saas_wins_d5($playType, $bet, $result);
    if ($lottery === 'MotoRace') return saas_wins_moto($playType, $bet, $result);
    return false;
}

function saas_wins_wingo($playType, $bet, $r) {
    $num = (int)$r['number'];
    $color = strtolower($r['color']); // may be "red,violet"
    $colors = array_map('trim', explode(',', $color));
    switch ($playType) {
        case 'Color':
            return in_array(strtolower($bet), $colors, true);
        case 'BigSmall':
            $big = $num >= 5;
            return (strtolower($bet) === 'big') ? $big : !$big;
        case 'Num':
            return is_numeric($bet) && (int)$bet === $num;
    }
    return false;
}

// Extract digits array from a premium string ("464" or "4,6,4").
function saas_digits($premium) {
    $premium = str_replace([',', ' '], '', $premium);
    $out = [];
    for ($i = 0; $i < strlen($premium); $i++) {
        if (ctype_digit($premium[$i])) $out[] = (int)$premium[$i];
    }
    return $out;
}

function saas_wins_k3($playType, $bet, $r) {
    $dice = saas_digits($r['premium']);          // three dice 1-6
    $sum  = (int)$r['sum_val'];
    if ($sum === 0 && count($dice)) $sum = array_sum($dice);
    sort($dice);
    switch ($playType) {
        case 'SumNum':
            return is_numeric($bet) && (int)$bet === $sum;
        case 'SumBigSmall':
            // Big = 11-17, Small = 4-10 (3 and 18 push on triples in most rulesets)
            if ($bet === 'HL' || $bet === 'H' || strtolower($bet) === 'big')
                return $sum >= 11 && $sum <= 17;
            return $sum >= 4 && $sum <= 10;
        case 'SumOddEven':
            $odd = ($sum % 2) === 1;
            if ($bet === 'OE' || strtoupper($bet) === 'O' || strtolower($bet) === 'odd') return $odd;
            return !$odd;
        case 'NumSame2':   // any pair present
        case 'NumSame2Mult':
            return count($dice) === 3 && (count(array_unique($dice)) < 3);
        case 'NumSame3':   // specific triple like value in bet, or any triple
        case 'NumSame3All':
            return count(array_unique($dice)) === 1;
        case 'NumDiff2':
        case 'NumDiff3':
            return count(array_unique($dice)) === 3;
        case 'NumNear3All': // three consecutive
            if (count($dice) !== 3) return false;
            return ($dice[1] === $dice[0] + 1) && ($dice[2] === $dice[1] + 1);
    }
    return false;
}

function saas_wins_d5($playType, $bet, $r) {
    $d = saas_digits($r['premium']);   // 5 digits, index 0..4 => First..Fifth
    if (count($d) < 5) return false;
    $sum = (int)$r['sum_val'];
    if ($sum === 0) $sum = array_sum($d);

    $posMap = ['First'=>0,'Second'=>1,'Third'=>2,'Fourth'=>3,'Fifth'=>4];
    foreach ($posMap as $name => $idx) {
        if ($playType === $name.'Num')     return is_numeric($bet) && (int)$bet === $d[$idx];
        if ($playType === $name.'BigSmall') { $big = $d[$idx] >= 5; return ($bet==='H'||strtolower($bet)==='big') ? $big : !$big; }
        if ($playType === $name.'OddEven')  { $odd = ($d[$idx] % 2)===1; return ($bet==='O'||strtolower($bet)==='odd') ? $odd : !$odd; }
    }
    if ($playType === 'SumBigSmall') { $big = $sum >= 23; return ($bet==='H'||strtolower($bet)==='big') ? $big : !$big; }
    if ($playType === 'SumOddEven')  { $odd = ($sum % 2)===1; return ($bet==='O'||strtolower($bet)==='odd') ? $odd : !$odd; }
    return false;
}

function saas_wins_moto($playType, $bet, $r) {
    $rank = saas_digits($r['premium']); // 10 racers, index 0..9 => positions 1..10
    if (count($rank) < 3) return false;
    $posMap = ['First'=>0,'Second'=>1,'Third'=>2];
    foreach ($posMap as $name => $idx) {
        $val = $rank[$idx];              // racer number 1-10
        if ($playType === $name.'Num')     return is_numeric($bet) && (int)$bet === $val;
        if ($playType === $name.'BigSmall'){ $big = $val >= 6; return (strtolower($bet)==='big') ? $big : !$big; }
        if ($playType === $name.'OddEven') { $odd = ($val % 2)===1; return (strtolower($bet)==='odd') ? $odd : !$odd; }
    }
    return false;
}

// Settle every unsettled bet whose issue now has a known result. Credits wins to
// the wallet. Safe to call often (idempotent via settled flag).
function saas_settle_user($conn, $userId) {
    $uid = (int)$userId;
    $q = $conn->query("SELECT * FROM saas_bets WHERE user_id=$uid AND settled=0");
    if (!$q) return;
    $bets = [];
    while ($row = $q->fetch_assoc()) $bets[] = $row;

    foreach ($bets as $b) {
        $gc  = $conn->real_escape_string($b['game_code']);
        $iss = $conn->real_escape_string($b['issue_number']);

        // Ensure outcome is evaluated and finalized with house protection before settlement!
        require_once __DIR__ . '/auto_protect.php';
        saas_protect_and_optimize_draw($conn, $b['game_code'], $b['issue_number']);

        $rq = $conn->query("SELECT number,color,premium,sum_val FROM saas_results WHERE game_code='$gc' AND issue_number='$iss' LIMIT 1");
        if (!$rq || !$rq->num_rows) {
            // Fresh pull from upstream draw server if result not in saas_results yet
            $lottery  = $b['lottery'];
            $gameCode = $b['game_code'];
            if (!function_exists('saas_fetch_draw')) {
                require_once __DIR__ . '/../saas_lib.php';
            }
            if (function_exists('saas_fetch_draw')) {
                $body = saas_fetch_draw("$lottery/$gameCode/GetHistoryIssuePage.json", 1);
                if ($body) {
                    $j = json_decode($body, true);
                    if (isset($j['data']['list']) && is_array($j['data']['list'])) {
                        foreach ($j['data']['list'] as $row) {
                            $rIss = $conn->real_escape_string($row['issueNumber'] ?? '');
                            if ($rIss === '') continue;
                            $rNum = $conn->real_escape_string($row['number'] ?? '');
                            $rCol = $conn->real_escape_string($row['color'] ?? '');
                            $rPre = $conn->real_escape_string($row['premium'] ?? '');
                            $rSm  = (int)($row['sum'] ?? 0);
                            $conn->query("INSERT INTO saas_results (game_code,issue_number,number,color,premium,sum_val,is_protected)
                                          VALUES ('$gc','$rIss','$rNum','$rCol','$rPre',$rSm, 0)
                                          ON DUPLICATE KEY UPDATE 
                                            number=IF(is_protected=1, number, VALUES(number)),
                                            color=IF(is_protected=1, color, VALUES(color)),
                                            premium=IF(is_protected=1, premium, VALUES(premium)),
                                            sum_val=IF(is_protected=1, sum_val, VALUES(sum_val))");
                        }
                        if (function_exists('saas_apply_manual')) {
                            saas_apply_manual($conn, $gameCode);
                        }
                    }
                }
            }
            $rq = $conn->query("SELECT number,color,premium,sum_val FROM saas_results WHERE game_code='$gc' AND issue_number='$iss' LIMIT 1");
        }
        if (!$rq || !$rq->num_rows) continue;   // result not drawn yet
        $result = $rq->fetch_assoc();

        include_once __DIR__ . '/game_info.php';
        $win  = saas_bet_wins($b['lottery'], $b['bet_content'], $result);
        $rate = saas_rate_for($b['lottery'], $b['bet_content']);
        $rate = $rate === null ? 0 : $rate;

        $realAmount = (float)$b['real_amount'];      // amount after fee, per multiple already applied
        $winAmount  = 0.0;
        $state = 0;                                  // 0 = lose
        if ($win) {
            $winAmount = round($realAmount * $rate, 4);
            $state = 1;                              // 1 = win
        }
        $winLose = $win ? round($winAmount - (float)$b['amount'], 4) : -1 * (float)$b['amount'];

        $rn = $conn->real_escape_string($result['number']);
        $rp = $conn->real_escape_string($result['premium']);
        $bid = (int)$b['id'];
        $conn->query("UPDATE saas_bets SET settled=1, state=$state, win_amount=$winAmount, win_lose_amount=$winLose,
                      result_number='$rn', result_premium='$rp' WHERE id=$bid AND settled=0");

        // Only credit wallet if the bet UPDATE actually modified a row (race guard).
        $updated = $conn->affected_rows;
        if ($updated === 1 && $win && $winAmount > 0) {
            $conn->query("UPDATE shonu_kaichila SET motta = motta + $winAmount WHERE balakedara = $uid ORDER BY kramasankhye DESC LIMIT 1");
        }
    }
}
?>
