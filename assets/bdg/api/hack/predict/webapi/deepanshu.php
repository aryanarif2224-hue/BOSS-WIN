<?php
// deepanshu.php - AVIATOR ENGINE

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
date_default_timezone_set("Asia/Kolkata");

$input = file_get_contents("php://input");
$post = json_decode($input, true);
$action = isset($post['action']) ? $post['action'] : '';

// --- GAME HEARTBEAT ---
if ($action == 'game_sync') {
    $q = $conn->query("SELECT * FROM aviator_game WHERE id=1");
    $game = $q->fetch_assoc();
    
    $now = microtime(true);
    $phase = $game['phase'];
    $current_multi = 1.00;
    $timeLeft = 0;

    // 1. WAITING (Betting Open) - 5 Seconds
    if ($phase == 'waiting') {
        $elapsed = $now - $game['start_time'];
        $timeLeft = max(0, 5 - $elapsed);
        
        if ($timeLeft <= 0) {
            // GO TO FLYING
            $newPeriod = date('YmdHis');
            
            // Result Logic (Admin or Random)
            if ($game['next_result_mode'] == 'fixed') {
                $crashPoint = $game['next_result_value'];
            } else {
                $r = rand(1, 100);
                if ($r <= 20) $crashPoint = rand(100, 150) / 100; // 1.00 - 1.50
                else if ($r <= 70) $crashPoint = rand(150, 400) / 100; // 1.50 - 4.00
                else $crashPoint = rand(400, 1000) / 100; // High
            }

            $conn->query("UPDATE aviator_game SET phase='flying', start_time='$now', period_id='$newPeriod', crash_point='$crashPoint' WHERE id=1");
            $phase = 'flying';
        }
    }

    // 2. FLYING (Multiplier Rising)
    if ($phase == 'flying') {
        $elapsed = $now - $game['start_time'];
        // Curve Formula: Slow start, fast end
        $current_multi = 1.00 + ($elapsed * 0.15) + ($elapsed * $elapsed * 0.008);
        
        if ($current_multi >= $game['crash_point']) {
            // CRASH!
            $conn->query("UPDATE aviator_game SET phase='crashed', start_time='$now' WHERE id=1");
            $conn->query("UPDATE aviator_bets SET status='lost' WHERE period_id='{$game['period_id']}' AND status='active'");
            $phase = 'crashed';
            $current_multi = $game['crash_point'];
        }
    }

    // 3. CRASHED (Cooldown) - 3 Seconds
    if ($phase == 'crashed') {
        $elapsed = $now - $game['start_time'];
        $timeLeft = max(0, 3 - $elapsed);
        $current_multi = $game['crash_point'];

        if ($timeLeft <= 0) {
            // RESTART
            $conn->query("UPDATE aviator_game SET phase='waiting', start_time='$now' WHERE id=1");
            $phase = 'waiting';
        }
    }

    echo json_encode([
        'phase' => $phase,
        'multiplier' => number_format($current_multi, 2),
        'timeLeft' => number_format($timeLeft, 1),
        'period_id' => $game['period_id']
    ]);
    exit;
}

// --- PLACE BET ---
if ($action == 'place_bet') {
    $uid = intval($post['userId']);
    $amt = floatval($post['amount']);
    
    $g = $conn->query("SELECT * FROM aviator_game WHERE id=1")->fetch_assoc();
    
    if ($g['phase'] != 'waiting') {
        echo json_encode(['code'=>1, 'msg'=>'Betting Closed!']); exit;
    }
    
    $balQ = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara='$uid'");
    $bal = $balQ->fetch_assoc()['motta'];
    
    if ($bal < $amt) {
        echo json_encode(['code'=>1, 'msg'=>'Low Balance']); exit;
    }
    
    $conn->query("UPDATE shonu_kaichila SET motta = motta - $amt WHERE balakedara='$uid'");
    $conn->query("INSERT INTO aviator_bets (user_id, period_id, amount, status) VALUES ('$uid', '{$g['period_id']}', '$amt', 'active')");
    
    echo json_encode(['code'=>0, 'msg'=>'Bet Placed']); exit;
}

// --- CASHOUT ---
if ($action == 'cashout') {
    $uid = intval($post['userId']);
    $pid = mysqli_real_escape_string($conn, $post['periodId']);
    
    $g = $conn->query("SELECT * FROM aviator_game WHERE id=1")->fetch_assoc();
    
    if ($g['phase'] != 'flying') {
        echo json_encode(['code'=>1, 'msg'=>'Plane Crashed!']); exit;
    }
    
    $now = microtime(true);
    $elapsed = $now - $g['start_time'];
    $current_multi = floor((1.00 + ($elapsed * 0.15) + ($elapsed * $elapsed * 0.008)) * 100) / 100;
    
    $betQ = $conn->query("SELECT * FROM aviator_bets WHERE user_id='$uid' AND period_id='$pid' AND status='active'");
    if ($betQ->num_rows == 0) {
        echo json_encode(['code'=>1, 'msg'=>'Invalid Bet']); exit;
    }
    
    $bet = $betQ->fetch_assoc();
    $winAmt = floor($bet['amount'] * $current_multi);
    
    $conn->query("UPDATE shonu_kaichila SET motta = motta + $winAmt WHERE balakedara='$uid'");
    $conn->query("UPDATE aviator_bets SET status='cashed_out', cashout_multi='$current_multi', win_amount='$winAmt' WHERE id='{$bet['id']}'");
    
    echo json_encode(['code'=>0, 'msg'=>'Success', 'amount'=>$winAmt]); exit;
}

// --- USER DATA ---
if ($action == 'get_user_data') {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    $is_jwt_valid = is_jwt_valid($token);
    $data_auth = json_decode($is_jwt_valid, true);
    
    if($data_auth['status'] === 'Success') {
        $uid = $data_auth['payload']['id'];
        $bal = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara='$uid'")->fetch_assoc()['motta'];
        echo json_encode(['code'=>0, 'balance'=>$bal, 'userId'=>$uid]);
    } else {
        echo json_encode(['code'=>1]);
    }
    exit;
}
?>