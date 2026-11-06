<?php
// WinGo 30s High-Profit Prediction Engine for shreewin06.site
// Strictly Idempotent: Never changes prediction for the same period once locked in DB.
// 0 Platform Bets -> Guarantees 100% PREDICTION WIN!
// Active Bets -> Safeguards House Profit!

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../saas_lib.php';
require_once __DIR__ . '/../../saas/auto_protect.php';

$now = time();
$today_start = date('Y-m-d 00:00:00');

// 1. Fetch current issue & timer from SaaS WinGo_30S draw
$body = saas_fetch_draw("WinGo/WinGo_30S.json", 0);
$j = $body ? json_decode($body, true) : null;

$current_issue = $j['current']['issueNumber'] ?? null;
$previous_issue = $j['previous']['issueNumber'] ?? null;
$end_time_ms = $j['current']['endTime'] ?? null;

// Fallback issue calculation if draw upstream is momentarily unreachable
if (!$current_issue) {
    $midnight = strtotime(date('Y-m-d 00:00:00'));
    $elapsed = $now - $midnight;
    $period_num = intval($elapsed / 30) + 1;
    $current_issue = date('Ymd') . '10005' . str_pad($period_num, 4, '0', STR_PAD_LEFT);
    $time_left = 30 - ($elapsed % 30);
} else {
    $time_left = $end_time_ms ? max(0, intval(($end_time_ms / 1000) - $now)) : 15;
}

// In the final 5 seconds (when betting is closed), resolve/protect current issue outcome
if ($time_left <= 5 && !empty($current_issue)) {
    saas_protect_and_optimize_draw($conn, 'WinGo_30S', $current_issue);
}

// 2. Fetch fresh draw history & settle any past pending predictions in DB
$hist_body = saas_fetch_draw("WinGo/WinGo_30S/GetHistoryIssuePage.json", 0);
$hist_j = $hist_body ? json_decode($hist_body, true) : null;
$drawn_map = [];
if (isset($hist_j['data']['list']) && is_array($hist_j['data']['list'])) {
    foreach ($hist_j['data']['list'] as $hr) {
        $drawn_map[$hr['issueNumber']] = [
            'number' => $hr['number'],
            'color'  => $hr['color']
        ];
    }
}

$pend_q = mysqli_query($conn, "SELECT id, issue_number, prediction, predict_type FROM wingo_predictions 
                               WHERE game_code='WinGo_30S' AND status='PENDING' AND issue_number != '$current_issue' 
                               ORDER BY id DESC LIMIT 10");
if ($pend_q && mysqli_num_rows($pend_q) > 0) {
    while ($p_row = mysqli_fetch_assoc($pend_q)) {
        $p_id = (int)$p_row['id'];
        $p_iss = $p_row['issue_number'];
        $p_pred = $p_row['prediction'];

        // Trigger protect & optimize for this issue to ensure it has outcome
        saas_protect_and_optimize_draw($conn, 'WinGo_30S', $p_iss);

        $act_num = null;
        $act_col = null;

        // 1. Check saas_results first (especially protected/optimized outcome)
        $res_q = mysqli_query($conn, "SELECT number, color, is_protected FROM saas_results WHERE game_code='WinGo_30S' AND issue_number='" . mysqli_real_escape_string($conn, $p_iss) . "' LIMIT 1");
        if ($res_q && mysqli_num_rows($res_q) > 0) {
            $r_row = mysqli_fetch_assoc($res_q);
            if ($r_row['number'] !== null && $r_row['number'] !== '') {
                $act_num = intval($r_row['number']);
                $act_col = strtolower($r_row['color'] ?? '');
            }
        }

        // 2. Fallback to drawn_map from draw server
        if ($act_num === null && isset($drawn_map[$p_iss])) {
            $act_num = intval($drawn_map[$p_iss]['number']);
            $act_col = strtolower($drawn_map[$p_iss]['color']);
        }

        if ($act_num !== null) {
            $cand_test = ['number' => (string)$act_num, 'color' => $act_col];
            $is_win = saas_candidate_matches_pred($p_row['predict_type'], $p_pred, $cand_test);
            $st_val = $is_win ? 'WIN' : 'LOSS';
            mysqli_query($conn, "UPDATE wingo_predictions SET status='$st_val', actual_number='$act_num', actual_color='$act_col', settled_at=NOW() WHERE id=$p_id");
        }
    }
}

// 3. IDEMPOTENT LOCK: Check if prediction for current period is already generated
$chk = mysqli_query($conn, "SELECT id, prediction, predict_type, step_in_cycle FROM wingo_predictions 
                            WHERE game_code='WinGo_30S' AND issue_number='$current_issue' LIMIT 1");

if ($chk && mysqli_num_rows($chk) > 0) {
    // ALREADY GENERATED -> RETURN EXACT SAME PREDICTION! NEVER CHANGE!
    $existing = mysqli_fetch_assoc($chk);
    $active_prediction = $existing['prediction'];
} else {
    // BRAND NEW PERIOD -> Generate next prediction and lock it in DB
    $last_step_q = mysqli_query($conn, "SELECT step_in_cycle FROM wingo_predictions WHERE game_code='WinGo_30S' ORDER BY id DESC LIMIT 1");
    $last_step = 0;
    if ($last_step_q && mysqli_num_rows($last_step_q) > 0) {
        $last_step = intval(mysqli_fetch_assoc($last_step_q)['step_in_cycle']);
    }
    $current_step = ($last_step % 10) + 1;

    // RANDOM WIN/LOSS with streak protection (looks 100% natural):
    // Base: ~72% chance WIN, ~28% chance LOSS (each round independent random)
    // Streak guard: Never more than 5 consecutive WINs, never more than 2 consecutive LOSSes
    $recent_q = mysqli_query($conn, "SELECT target_intent FROM wingo_predictions WHERE game_code='WinGo_30S' ORDER BY id DESC LIMIT 5");
    $recent_intents = [];
    if ($recent_q) {
        while ($ri = mysqli_fetch_assoc($recent_q)) {
            $recent_intents[] = $ri['target_intent'];
        }
    }

    // Count consecutive wins/losses from most recent
    $consec_wins = 0;
    $consec_losses = 0;
    foreach ($recent_intents as $ri) {
        if ($ri === 'WIN' && $consec_losses === 0) { $consec_wins++; }
        elseif ($ri === 'LOSS' && $consec_wins === 0) { $consec_losses++; }
        else { break; }
    }

    // Random roll with streak protection
    $roll = mt_rand(1, 100); // 1-100
    if ($consec_wins >= 5) {
        // Too many wins in a row — force a LOSS to look natural
        $target_intent = 'LOSS';
    } elseif ($consec_losses >= 2) {
        // 2 losses in a row — force a WIN so channel doesn't look bad
        $target_intent = 'WIN';
    } else {
        // Pure random: 72% WIN, 28% LOSS
        $target_intent = ($roll <= 72) ? 'WIN' : 'LOSS';
    }

    // 70% chance of BIG/SMALL, 30% chance of COLOR (GREEN/RED)
    $type_choice = (mt_rand(1, 10) <= 7) ? 'BS' : 'COLOR';

    // Check if manual result is set in advance
    $known_num = null;
    $mq = mysqli_query($conn, "SELECT number FROM saas_manual_results WHERE game_code='WinGo_30S' AND issue_number='$current_issue' AND state=1 LIMIT 1");
    if ($mq && mysqli_num_rows($mq) > 0) {
        $known_num = intval(mysqli_fetch_assoc($mq)['number']);
    }

    if ($known_num !== null) {
        // Result is known: steer prediction to match intent
        $is_big = ($known_num >= 5);
        $is_green = in_array($known_num, [1, 3, 5, 7, 9]);

        if ($type_choice === 'BS') {
            if ($target_intent === 'WIN') {
                $chosen_pred = $is_big ? 'BIG' : 'SMALL';
            } else {
                $chosen_pred = $is_big ? 'SMALL' : 'BIG';
            }
        } else {
            if ($target_intent === 'WIN') {
                $chosen_pred = $is_green ? 'GREEN' : 'RED';
            } else {
                $chosen_pred = $is_green ? 'RED' : 'GREEN';
            }
        }
    } else {
        // Upstream random draw: just pick a random side
        // auto_protect will force WIN or LOSS based on target_intent
        if ($type_choice === 'BS') {
            $chosen_pred = (mt_rand(0, 1) === 1) ? 'BIG' : 'SMALL';
        } else {
            $chosen_pred = (mt_rand(0, 1) === 1) ? 'GREEN' : 'RED';
        }
    }

    // Lock prediction into database with UNIQUE constraint
    $ins = $conn->prepare("INSERT INTO wingo_predictions 
        (game_code, issue_number, predict_type, prediction, step_in_cycle, target_intent, status)
        VALUES ('WinGo_30S', ?, ?, ?, ?, ?, 'PENDING')
        ON DUPLICATE KEY UPDATE prediction=prediction");
    $ins->bind_param('sssis', $current_issue, $type_choice, $chosen_pred, $current_step, $target_intent);
    $ins->execute();
    $ins->close();

    $active_prediction = $chosen_pred;
}

// 4. Fetch Previous prediction with full result
$prev_data = null;
$prev_q = mysqli_query($conn, "SELECT issue_number, prediction, status, actual_number, actual_color 
                               FROM wingo_predictions 
                               WHERE game_code='WinGo_30S' AND issue_number != '$current_issue' AND status != 'PENDING' 
                               ORDER BY id DESC LIMIT 1");
if ($prev_q && mysqli_num_rows($prev_q) > 0) {
    $pr = mysqli_fetch_assoc($prev_q);
    $prev_data = [
        'period'        => $pr['issue_number'],
        'prediction'    => $pr['prediction'],
        'result_number' => $pr['actual_number'],
        'result_color'  => $pr['actual_color'],
        'status'        => $pr['status'] // "WIN" or "LOSS"
    ];
}

// 5. Fetch Recent History (Last 10 settled rounds)
$history = [];
$hist_q = mysqli_query($conn, "SELECT issue_number, prediction, status, actual_number, actual_color 
                               FROM wingo_predictions 
                               WHERE game_code='WinGo_30S' AND status != 'PENDING' 
                               ORDER BY id DESC LIMIT 10");
if ($hist_q) {
    while ($hr = mysqli_fetch_assoc($hist_q)) {
        $history[] = [
            'period'        => $hr['issue_number'],
            'prediction'    => $hr['prediction'],
            'result_number' => $hr['actual_number'],
            'result_color'  => $hr['actual_color'],
            'status'        => $hr['status']
        ];
    }
}

// 6. Platform Real-time House Profit & Turnover Stats (for Admin Monitor)
$today_start = date('Y-m-d 00:00:00');
$today_stats_q = mysqli_query($conn, "SELECT 
    COALESCE(SUM(amount), 0) AS total_in,
    COALESCE(SUM(CASE WHEN state = 1 THEN (CASE WHEN win_amount > 0 THEN win_amount ELSE (win_lose_amount + amount) END) ELSE 0 END), 0) AS total_payout,
    COUNT(id) AS total_bets_count
    FROM saas_bets WHERE created_at >= '$today_start' AND settled = 1");
$today_stats = mysqli_fetch_assoc($today_stats_q);
$today_in = floatval($today_stats['total_in']);
$today_payout = floatval($today_stats['total_payout']);
$today_profit = $today_in - $today_payout; // Real House Profit: Turnover - Gross Payouts!

$prev_period = $prev_data['period'] ?? '';
$last_round_in = 0.0;
$last_round_out = 0.0;
$last_round_count = 0;
if (!empty($prev_period)) {
    $lr_q = mysqli_query($conn, "SELECT 
        COALESCE(SUM(amount), 0) AS round_in,
        COALESCE(SUM(CASE WHEN state = 1 THEN (CASE WHEN win_amount > 0 THEN win_amount ELSE (win_lose_amount + amount) END) ELSE 0 END), 0) AS round_payout,
        COUNT(id) AS round_bets_count
        FROM saas_bets WHERE game_code='WinGo_30S' AND issue_number='" . mysqli_real_escape_string($conn, $prev_period) . "'");
    if ($lr_q) {
        $lr = mysqli_fetch_assoc($lr_q);
        $last_round_in = floatval($lr['round_in']);
        $last_round_out = floatval($lr['round_payout']);
        $last_round_count = intval($lr['round_bets_count']);
    }
}
$last_round_profit = $last_round_in - $last_round_out; // Real House Round Profit!

$platform_stats = [
    'today_in'           => round($today_in, 2),
    'today_out'          => round($today_payout, 2),
    'today_net_profit'   => round($today_profit, 2),
    'today_bets_count'   => intval($today_stats['total_bets_count']),
    'last_round_period'  => $prev_period,
    'last_round_in'      => round($last_round_in, 2),
    'last_round_out'     => round($last_round_out, 2),
    'last_round_profit'  => round($last_round_profit, 2),
    'last_round_bets_cnt'=> $last_round_count
];

// Clean JSON response
echo json_encode([
    'code' => 0,
    'msg'  => 'Succeed',
    'data' => [
        'game'           => 'WinGo_30S',
        'period'         => $current_issue,
        'prediction'     => $active_prediction, // ONLY ONE VALUE: "BIG", "SMALL", "GREEN", or "RED"
        'time_left'      => $time_left,
        'previous'       => $prev_data,
        'history'        => $history,
        'platform_stats' => $platform_stats,
        'server_time'    => date('Y-m-d H:i:s')
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
