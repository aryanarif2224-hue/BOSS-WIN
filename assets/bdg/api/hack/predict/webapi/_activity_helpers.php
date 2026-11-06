<?php
// _activity_helpers.php - Unified bonus & award calculation for Activity page
function get_all_activity_bonus_totals($conn, $userId) {
    $userId = (int)$userId;
    $totalRewards = 0.0;
    $todayRewards = 0.0;

    // 1. Award tables (id, userkani, price, shonu, processed)
    $awardTables = [
        'invite_bonus_table',
        'return_awards_table',
        'daily_awards_table',
        'weekly_awards_table',
        'card_binding_gift_table',
        'first_full_gift_table',
        'recharge_gift_table',
        'agent_bonus_table',
        'new_members_bonus_table'
    ];
    foreach ($awardTables as $t) {
        $check = $conn->query("SHOW TABLES LIKE '$t'");
        if ($check && $check->num_rows > 0) {
            $q = $conn->query("SELECT 
                COALESCE(SUM(price), 0) AS s, 
                COALESCE(SUM(CASE WHEN DATE(shonu) = CURDATE() THEN price ELSE 0 END), 0) AS st 
                FROM `$t` WHERE userkani = $userId");
            if ($q && $row = $q->fetch_assoc()) {
                $totalRewards += (float)$row['s'];
                $todayRewards += (float)$row['st'];
            }
        }
    }

    // 2. tracking_userbonus
    $check = $conn->query("SHOW TABLES LIKE 'tracking_userbonus'");
    if ($check && $check->num_rows > 0) {
        $q = $conn->query("SELECT 
            COALESCE(SUM(price), 0) AS s, 
            COALESCE(SUM(CASE WHEN DATE(shonu) = CURDATE() THEN price ELSE 0 END), 0) AS st 
            FROM tracking_userbonus WHERE userkani = $userId");
        if ($q && $row = $q->fetch_assoc()) {
            $totalRewards += (float)$row['s'];
            $todayRewards += (float)$row['st'];
        }
    }

    // 3. gift_redemptions (Gift codes / Red envelopes)
    $check = $conn->query("SHOW TABLES LIKE 'gift_redemptions'");
    if ($check && $check->num_rows > 0) {
        $q = $conn->query("SELECT 
            COALESCE(SUM(amount), 0) AS s, 
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) AS st 
            FROM gift_redemptions WHERE user_id = $userId");
        if ($q && $row = $q->fetch_assoc()) {
            $totalRewards += (float)$row['s'];
            $todayRewards += (float)$row['st'];
        }
    }

    // 4. shonu_turntable_spins (Lucky wheel winnings)
    $check = $conn->query("SHOW TABLES LIKE 'shonu_turntable_spins'");
    if ($check && $check->num_rows > 0) {
        $q = $conn->query("SELECT 
            COALESCE(SUM(prize_amount), 0) AS s, 
            COALESCE(SUM(CASE WHEN DATE(spin_time) = CURDATE() THEN prize_amount ELSE 0 END), 0) AS st 
            FROM shonu_turntable_spins WHERE user_id = $userId AND prize_amount > 0");
        if ($q && $row = $q->fetch_assoc()) {
            $totalRewards += (float)$row['s'];
            $todayRewards += (float)$row['st'];
        }
    }

    // 5. user_bonus_log
    $check = $conn->query("SHOW TABLES LIKE 'user_bonus_log'");
    if ($check && $check->num_rows > 0) {
        $q = $conn->query("SELECT 
            COALESCE(SUM(bonus_amount), 0) AS s, 
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN bonus_amount ELSE 0 END), 0) AS st 
            FROM user_bonus_log WHERE user_id = '$userId' OR user_id = $userId");
        if ($q && $row = $q->fetch_assoc()) {
            $totalRewards += (float)$row['s'];
            $todayRewards += (float)$row['st'];
        }
    }

    return [
        'todayRewards' => round($todayRewards, 2),
        'totalRewards' => round($totalRewards, 2)
    ];
}
