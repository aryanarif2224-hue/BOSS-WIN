<?php
// Bet placement — deducts the stake from the wallet and records the bet against
// the REAL current issue number. Settlement happens later (saas/settle.php) once
// the real result for that issue is drawn.
//
// Request body (from HAR, e.g. WinGoBet):
//   { gameCode, issueNumber, amount, betMultiple, betContent, language, ... }
//   betContent is a string (WinGo/K3/MotoRace) OR array (D5) of "PlayType_Bet".

function saas_place_bet($conn, $action, $req) {
    list($ok, $uid) = saas_authenticate($conn);
    if (!$ok) saas_err(401, 'No operation permission', 2, 401);

    $gameCode = isset($req['gameCode']) ? preg_replace('/[^A-Za-z0-9_]/', '', $req['gameCode']) : '';
    $lottery  = saas_lottery_of($gameCode);
    if ($lottery === null) saas_err(7, 'Param is Invalid', 6);

    // Bet action must match the game family.
    $expected = $lottery . 'Bet';
    $aliasOk = ($action === $expected)
        || ($lottery === 'WinGo' && in_array($action, ['WinGoBet','TrxWinGoBet','VideoWinGoBet'], true));
    if (!$aliasOk) saas_err(7, 'Param is Invalid', 6);

    $amount      = isset($req['amount'])      ? (float)$req['amount']    : 0;
    $betMultiple = isset($req['betMultiple']) ? max(1, (int)$req['betMultiple']) : 1;
    $issue       = isset($req['issueNumber']) ? preg_replace('/[^0-9]/', '', $req['issueNumber']) : '';
    if ($amount <= 0 || $issue === '') saas_err(7, 'Param is Invalid', 6);

    // betContent may be a string or an array; normalise to a list.
    $bcRaw = isset($req['betContent']) ? $req['betContent'] : '';
    $betList = is_array($bcRaw) ? $bcRaw : [$bcRaw];
    $betList = array_values(array_filter(array_map(function ($x) {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$x);
    }, $betList)));
    if (!count($betList)) saas_err(7, 'Param is Invalid', 6);

    // Reject bets on an issue that is not the current open window (anti-replay).
    $win = saas_current_window_for($conn, $lottery, $gameCode);
    if ($win && isset($win['current']['issueNumber']) && $win['current']['issueNumber'] !== $issue) {
        // allow only the current issue
        saas_err(8, 'Issue closed', 8);
    }

    // Total stake = amount * betMultiple * (number of picks).
    $picks     = count($betList);
    $unitStake = $amount * $betMultiple;
    $totalStake = $unitStake * $picks;

    // Settle any matured bets first so balance is 100% current
    saas_settle_user($conn, $uid);

    // Check + deduct balance atomically-ish.
    $balance = saas_get_balance($conn, $uid);
    if ($balance < $totalStake) saas_err(6, 'Insufficient balance', 101);

    $conn->query("UPDATE shonu_kaichila SET motta = motta - $totalStake WHERE balakedara = $uid ORDER BY kramasankhye DESC LIMIT 1");

    // 2% platform fee (matches HAR: amount 1.0 -> realAmount 0.98, fee 0.02).
    $feeRate = 0.02;
    $now = (int)round(microtime(true) * 1000);

    foreach ($betList as $i => $bc) {
        $fee        = round($unitStake * $feeRate, 4);
        $realAmount = round($unitStake - $fee, 4);
        $orderNo    = saas_order_no($lottery, $uid, $i);
        $playType   = (strpos($bc, '_') !== false) ? substr($bc, 0, strrpos($bc, '_')) : $bc;

        $gcEsc = $conn->real_escape_string($gameCode);
        $lotEsc= $conn->real_escape_string($lottery);
        $issEsc= $conn->real_escape_string($issue);
        $ptEsc = $conn->real_escape_string($playType);
        $bcEsc = $conn->real_escape_string($bc);
        $onEsc = $conn->real_escape_string($orderNo);

        $conn->query("INSERT INTO saas_bets
            (user_id, game_code, lottery, issue_number, play_type, bet_content,
             amount, bet_multiple, real_amount, fee, order_no, state, bet_time)
            VALUES ($uid, '$gcEsc', '$lotEsc', '$issEsc', '$ptEsc', '$bcEsc',
             $unitStake, $betMultiple, $realAmount, $fee, '$onEsc', 0, $now)");
    }

    // Feed VIP exp + rebate turnover on the total staked amount (same as native games).
    // vip.php expects $byabaharkarta, $totalamount, $shnunc, $conn.
    $byabaharkarta = $uid;
    $totalamount   = $totalStake;
    $shnunc        = date("Y-m-d H:i:s");
    if (file_exists('/raxi/bdg/api/webapi/vip.php')) {
        include "/raxi/bdg/api/webapi/vip.php";
    }

    // 6-level upline commission on the staked amount (parity with native games).
    // $comm_ref makes the payout idempotent per placed bet group.
    $comm_ref = 'S' . $uid . '-' . $now . '-' . substr(md5($issue . $gameCode . $totalStake), 0, 8);
    if (file_exists('/raxi/bdg/api/webapi/commission.php')) {
        include_once "/raxi/bdg/api/webapi/commission.php";
        if (function_exists('sw_award_bet_commission')) {
            sw_award_bet_commission($conn, $uid, $totalStake, $shnunc, $comm_ref);
        }
    }

    // Real SaaS bet response is minimal.
    echo json_encode([
        'code' => 0, 'msg' => 'Succeed', 'msgCode' => 0,
        'serviceTime' => (int)round(microtime(true) * 1000),
    ]);
    exit();
}

// Build an order number similar to the real one (prefix + timestamp-ish + rand).
function saas_order_no($lottery, $uid, $i) {
    $prefix = ['WinGo'=>'WG','K3'=>'K3','D5'=>'D5','MotoRace'=>'MR'][$lottery] ?? 'XX';
    $t = date('YmdHis');
    $rand = str_pad((string)(($uid + $i * 7) % 100000), 5, '0', STR_PAD_LEFT);
    return $prefix . $t . $rand . substr(md5($uid . $i . microtime(true)), 0, 6);
}

// Current window with a slightly longer cache (avoid one fetch per pick).
function saas_current_window_for($conn, $lottery, $gameCode) {
    $body = saas_fetch_draw("$lottery/$gameCode.json", 2);
    if ($body === null) return null;
    return json_decode($body, true);
}
?>
