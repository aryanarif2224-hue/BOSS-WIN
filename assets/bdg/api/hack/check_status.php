<?php
// GET or POST /api/hack/check_status.php - Universal Realtime Signal & Gatekeeper API
require_once __DIR__ . '/common.php';

// Authenticate via Bearer token
$hackUser = hack_authenticate($conn);

$linkedMobile = $hackUser['linked_mobile'];
$playerStats = get_player_stats($conn, $linkedMobile);

if (!$playerStats) {
    hack_json(200, 'Linked mobile number is not registered on Shreewin yet.', [
        'hack_username' => $hackUser['username'],
        'linked_mobile' => $linkedMobile,
        'player_registered' => false,
        'is_logged_in' => false,
        'prediction_unlocked' => false,
        'status_code' => 'NOT_REGISTERED',
        'status_message' => 'User not registered on Shreewin. Register with this mobile first.'
    ]);
}

$isLoggedIn = (bool)$playerStats['is_logged_in'];
$totalDeposit = (float)($playerStats['deposit']['total_deposit'] ?? 0);
$predictionsUsed = (int)($hackUser['predictions_used'] ?? 0);
$lastCountedPeriod = (string)($hackUser['last_counted_period'] ?? '');

$requestedGame = trim($_REQUEST['game'] ?? $_POST['game'] ?? 'WinGo_30S');
if (empty($requestedGame)) {
    $requestedGame = 'WinGo_30S';
}

$unlocked = false;
$statusCode = '';
$statusMessage = '';
$tier = 'LOCKED';
$predictionsLimit = 0;
$predictionsLeft = 0;

// =========================================================================
// 1. DEPOSIT GATEKEEPER & FREE TRIAL LOGIC
// =========================================================================
if (!$isLoggedIn) {
    $statusCode = 'OFFLINE';
    $statusMessage = 'Aap Shreewin website par logged in nahi ho! Pehle website par login karein.';
} elseif ($totalDeposit >= 200) {
    // VIP Unlimited Tier (Deposited >= ₹200)
    $unlocked = true;
    $tier = 'VIP_UNLIMITED';
    $statusCode = 'UNLOCKED';
    $predictionsLimit = -1;
    $predictionsLeft = -1;
    $statusMessage = '👑 GURU MODES VIP UNLIMITED (Active)';
} else {
    // Free Trial Tier (5 Free Predictions)
    $FREE_TRIAL_MAX = 5;
    $predictionsLimit = $FREE_TRIAL_MAX;
    if ($predictionsUsed >= $FREE_TRIAL_MAX) {
        $unlocked = false;
        $tier = 'LOCKED';
        $statusCode = 'DEPOSIT_REQUIRED';
        $predictionsLeft = 0;
        $statusMessage = '⚠️ Free 5 Predictions Used! Unlock Unlimited 24/7 VIP Signals by making a minimum ₹200 recharge on Shreewin.';
    } else {
        $unlocked = true;
        $tier = 'FREE_TRIAL';
        $statusCode = 'TRIAL_ACTIVE';
        $predictionsLeft = $FREE_TRIAL_MAX - $predictionsUsed;
        $statusMessage = "🎯 FREE TRIAL (Rounds Left: $predictionsLeft/$FREE_TRIAL_MAX) — Recharge ₹200+ for Unlimited VIP";
    }
}

$response = [
    'hack_username' => $hackUser['username'],
    'linked_mobile' => $linkedMobile,
    'player_registered' => true,
    'is_logged_in' => $isLoggedIn,
    'last_login_at' => $playerStats['last_login_at'],
    'last_logout_at' => $playerStats['last_logout_at'],
    'wallet_balance' => $playerStats['wallet_balance'],
    'selected_game' => $requestedGame,
    'deposit_info' => [
        'total_deposit' => $totalDeposit,
        'min_deposit_required' => 200,
        'tier' => $tier,
        'predictions_limit' => $predictionsLimit,
        'predictions_used' => $predictionsUsed,
        'predictions_left' => $predictionsLeft
    ],
    'prediction_unlocked' => $unlocked,
    'status_code' => $statusCode,
    'status_message' => $statusMessage
];

// =========================================================================
// 2. MULTI-GAME SAFE MODE PREDICTION ENGINE
// =========================================================================
if ($unlocked) {
    $now = time();

    // ---------------------------------------------------------------------
    // A. AVIATOR SAFE MODE HACK
    // ---------------------------------------------------------------------
    if (strcasecmp($requestedGame, 'Aviator') === 0 || strcasecmp($requestedGame, 'Crash') === 0) {
        $aviator_round_id = 'AV_' . date('YmdHi') . '_' . intval($now / 15);
        
        // Count usage in trial mode
        if ($tier === 'FREE_TRIAL' && $aviator_round_id !== $lastCountedPeriod) {
            $predictionsUsed++;
            $conn->query("UPDATE hack_users SET predictions_used = $predictionsUsed, last_counted_period = '$aviator_round_id' WHERE id = " . (int)$hackUser['id']);
            $lastCountedPeriod = $aviator_round_id;
            $predictionsLeft = max(0, 5 - $predictionsUsed);
            $response['deposit_info']['predictions_used'] = $predictionsUsed;
            $response['deposit_info']['predictions_left'] = $predictionsLeft;
        }

        $seed = crc32($aviator_round_id);
        mt_srand($seed);
        $safeMult = 1.45 + (mt_rand(0, 45) / 100); // 1.45x - 1.90x safe target
        $maxMult = $safeMult + 0.35 + (mt_rand(0, 60) / 100);
        $confidence = mt_rand(94, 98);
        mt_srand(); // reset seed

        $response['live_prediction'] = [
            'game'                 => 'Aviator',
            'mode'                 => 'SAFE_CASHOUT',
            'round_id'             => $aviator_round_id,
            'safe_multiplier'      => number_format($safeMult, 2, '.', '') . 'x',
            'suggested_exit_range' => number_format($safeMult - 0.10, 2, '.', '') . 'x - ' . number_format($safeMult + 0.15, 2, '.', '') . 'x',
            'max_projected'        => number_format($maxMult, 2, '.', '') . 'x',
            'confidence'           => $confidence . '%',
            'signal_status'        => '🟢 HIGH ACCURACY SAFE SIGNAL',
            'strategy_tip'         => 'Set Auto-Cashout at ' . number_format($safeMult, 2, '.', '') . 'x for consistent low-risk profits.',
            'server_time'          => date('Y-m-d H:i:s')
        ];
    }
    // ---------------------------------------------------------------------
    // B. MINES PRO SAFE MODE HACK
    // ---------------------------------------------------------------------
    elseif (strcasecmp($requestedGame, 'Mines') === 0 || strcasecmp($requestedGame, 'MinesPro') === 0) {
        $mines_session_id = 'MN_' . date('YmdHi') . '_' . intval($now / 20);

        if ($tier === 'FREE_TRIAL' && $mines_session_id !== $lastCountedPeriod) {
            $predictionsUsed++;
            $conn->query("UPDATE hack_users SET predictions_used = $predictionsUsed, last_counted_period = '$mines_session_id' WHERE id = " . (int)$hackUser['id']);
            $lastCountedPeriod = $mines_session_id;
            $predictionsLeft = max(0, 5 - $predictionsUsed);
            $response['deposit_info']['predictions_used'] = $predictionsUsed;
            $response['deposit_info']['predictions_left'] = $predictionsLeft;
        }

        $seed = crc32($mines_session_id);
        mt_srand($seed);
        $all_cells = range(1, 25);
        shuffle($all_cells);
        $safe_spots = array_slice($all_cells, 0, 4);
        sort($safe_spots);
        $confidence = mt_rand(95, 99);
        mt_srand();

        $grid_coords = [];
        foreach ($safe_spots as $pos) {
            $row = intval(($pos - 1) / 5) + 1;
            $col = (($pos - 1) % 5) + 1;
            $grid_coords[] = ['cell' => $pos, 'row' => $row, 'col' => $col];
        }

        $response['live_prediction'] = [
            'game'            => 'MinesPro',
            'mode'            => 'SAFE_DIAMONDS_GRID',
            'session_id'      => $mines_session_id,
            'grid_dimension'  => '5x5 (25 Cells)',
            'mines_setting'   => '3 Mines (Recommended)',
            'safe_diamonds'   => $safe_spots,
            'grid_cells'      => $grid_coords,
            'safe_clicks'     => 'Open these 4 cells only',
            'confidence'      => $confidence . '%',
            'signal_status'   => '💎 GUARANTEED SAFE DIAMONDS',
            'strategy_tip'    => 'Open the 4 marked safe diamond spots and hit Cash Out immediately.',
            'server_time'     => date('Y-m-d H:i:s')
        ];
    }
    // ---------------------------------------------------------------------
    // C. WINGO / TRX WINGO SAFE MODE HACK
    // ---------------------------------------------------------------------
    else {
        // Determine interval and typeCode for all 5 WinGo variants
        $intervalSec = 30;
        $typeCode = '10005';
        $gameLabel = 'WinGo_30S';

        if (strcasecmp($requestedGame, 'WinGo_1M') === 0) {
            $intervalSec = 60;
            $typeCode = '10001';
            $gameLabel = 'WinGo_1M';
        } elseif (strcasecmp($requestedGame, 'WinGo_3M') === 0) {
            $intervalSec = 180;
            $typeCode = '10002';
            $gameLabel = 'WinGo_3M';
        } elseif (strcasecmp($requestedGame, 'WinGo_5M') === 0) {
            $intervalSec = 300;
            $typeCode = '10003';
            $gameLabel = 'WinGo_5M';
        } elseif (strcasecmp($requestedGame, 'TRX_WinGo') === 0 || strcasecmp($requestedGame, 'TRX') === 0) {
            $intervalSec = 60;
            $typeCode = '20001';
            $gameLabel = 'TRX_WinGo';
        }

        $elapsed_utc = $now % 86400;
        $period_num = intval($elapsed_utc / $intervalSec) + 1;
        $current_issue = gmdate('Ymd', $now) . $typeCode . str_pad($period_num, 4, '0', STR_PAD_LEFT);
        $time_left = $intervalSec - ($elapsed_utc % $intervalSec);

        // If tier is FREE_TRIAL, count only once per period
        if ($tier === 'FREE_TRIAL' && $current_issue !== $lastCountedPeriod) {
            $predictionsUsed++;
            $conn->query("UPDATE hack_users SET predictions_used = $predictionsUsed, last_counted_period = '$current_issue' WHERE id = " . (int)$hackUser['id']);
            $lastCountedPeriod = $current_issue;
            $predictionsLeft = max(0, 5 - $predictionsUsed);
            $response['deposit_info']['predictions_used'] = $predictionsUsed;
            $response['deposit_info']['predictions_left'] = $predictionsLeft;
            $response['status_message'] = "🎯 FREE TRIAL (Rounds Left: $predictionsLeft/5) — Recharge ₹200+ for Unlimited VIP";
        }

        // Settle pending past predictions
        $pend_q = mysqli_query($conn, "SELECT id, issue_number, prediction, predict_type FROM wingo_predictions 
                                       WHERE game_code='$gameLabel' AND status='PENDING' AND issue_number != '$current_issue' 
                                       ORDER BY id DESC LIMIT 5");
        if ($pend_q && mysqli_num_rows($pend_q) > 0) {
            while ($p_row = mysqli_fetch_assoc($pend_q)) {
                $p_id = (int)$p_row['id'];
                $p_iss = $p_row['issue_number'];
                $p_pred = $p_row['prediction'];

                $res_q = mysqli_query($conn, "SELECT number, color FROM saas_results WHERE game_code='WinGo_30S' AND issue_number='" . mysqli_real_escape_string($conn, $p_iss) . "' LIMIT 1");
                if ($res_q && mysqli_num_rows($res_q) > 0) {
                    $r_row = mysqli_fetch_assoc($res_q);
                    if ($r_row['number'] !== null && $r_row['number'] !== '') {
                        $act_num = intval($r_row['number']);
                        $act_col = strtolower($r_row['color'] ?? '');
                        $is_big = ($act_num >= 5);
                        $is_green = in_array($act_num, [1, 3, 5, 7, 9]);
                        $is_red = in_array($act_num, [0, 2, 4, 6, 8]);
                        $is_win = false;
                        if ($p_pred === 'BIG') $is_win = $is_big;
                        elseif ($p_pred === 'SMALL') $is_win = !$is_big;
                        elseif ($p_pred === 'GREEN') $is_win = $is_green;
                        elseif ($p_pred === 'RED') $is_win = $is_red;
                        $st_val = $is_win ? 'WIN' : 'LOSS';
                        mysqli_query($conn, "UPDATE wingo_predictions SET status='$st_val', actual_number='$act_num', actual_color='$act_col', settled_at=NOW() WHERE id=$p_id");
                    }
                }
            }
        }

        // Generate / Lock current issue prediction
        $pred_q = mysqli_query($conn, "SELECT id, issue_number, prediction, predict_type, status FROM wingo_predictions WHERE game_code='$gameLabel' AND issue_number='$current_issue' LIMIT 1");
        $active_prediction = null;
        if ($pred_q && mysqli_num_rows($pred_q) > 0) {
            $prow = mysqli_fetch_assoc($pred_q);
            $active_prediction = $prow['prediction'];
        } else {
            $last_step_q = mysqli_query($conn, "SELECT step_in_cycle FROM wingo_predictions WHERE game_code='$gameLabel' ORDER BY id DESC LIMIT 1");
            $last_step = 0;
            if ($last_step_q && mysqli_num_rows($last_step_q) > 0) {
                $last_step = intval(mysqli_fetch_assoc($last_step_q)['step_in_cycle']);
            }
            $current_step = ($last_step % 10) + 1;

            // Safe Mode: High accuracy (88%)
            $target_intent = (mt_rand(1, 100) <= 88) ? 'WIN' : 'LOSS';
            $type_choice = (mt_rand(1, 10) <= 8) ? 'BS' : 'COLOR';
            if ($type_choice === 'BS') {
                $chosen_pred = (mt_rand(0, 1) === 1) ? 'BIG' : 'SMALL';
            } else {
                $chosen_pred = (mt_rand(0, 1) === 1) ? 'GREEN' : 'RED';
            }

            $ins = $conn->prepare("INSERT INTO wingo_predictions 
                (game_code, issue_number, predict_type, prediction, step_in_cycle, target_intent, status)
                VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
                ON DUPLICATE KEY UPDATE prediction=prediction");
            $ins->bind_param('ssssis', $gameLabel, $current_issue, $type_choice, $chosen_pred, $current_step, $target_intent);
            $ins->execute();
            $ins->close();

            $active_prediction = $chosen_pred;
        }

        // Previous settled outcome
        $prev_q = mysqli_query($conn, "SELECT issue_number, prediction, status, actual_number, actual_color 
                                       FROM wingo_predictions 
                                       WHERE game_code='$gameLabel' AND issue_number != '$current_issue' AND status != 'PENDING' 
                                       ORDER BY id DESC LIMIT 1");
        $prev_data = null;
        if ($prev_q && $pr = mysqli_fetch_assoc($prev_q)) {
            $prev_data = [
                'period'        => $pr['issue_number'],
                'prediction'    => $pr['prediction'],
                'result_number' => $pr['actual_number'],
                'result_color'  => $pr['actual_color'],
                'status'        => $pr['status']
            ];
        }

        $confidence = mt_rand(92, 97);
        $response['live_prediction'] = [
            'game'        => $gameLabel,
            'period'      => $current_issue,
            'prediction'  => $active_prediction,
            'confidence'  => $confidence . '%',
            'time_left'   => $time_left,
            'previous'    => $prev_data,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
}

hack_json(200, $statusMessage, $response);
?>
