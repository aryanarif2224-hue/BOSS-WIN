<?php
// POST /api/hack/user_info.php - Direct User Lookup by Mobile Number
require_once __DIR__ . '/common.php';

// Authenticate via Bearer token
$hackUser = hack_authenticate($conn);

$req = get_request_data();
$mobile = trim($req['mobile'] ?? '');

// If mobile is not passed, fallback to linked_mobile
if (empty($mobile)) {
    $mobile = $hackUser['linked_mobile'];
}

if (empty($mobile)) {
    hack_json(400, 'Mobile number is required.');
}

$playerStats = get_player_stats($conn, $mobile);

if (!$playerStats) {
    hack_json(404, 'User not found on Shreewin.', [
        'mobile' => $mobile,
        'found' => false
    ]);
}

$userId = (int)$playerStats['user_id'];

// Get recent deposits list (last 10)
$depList = [];
$dq = $conn->query("SELECT motta, dinankavannuracisi, sthiti FROM thevani WHERE balakedara = $userId ORDER BY shonu DESC LIMIT 10");
if ($dq) {
    while ($row = $dq->fetch_assoc()) {
        $depList[] = [
            'amount' => (float)$row['motta'],
            'date' => $row['dinankavannuracisi'],
            'status' => ((int)$row['sthiti'] === 1) ? 'Success' : (((int)$row['sthiti'] === 2) ? 'Failed' : 'Pending')
        ];
    }
}
$playerStats['deposit']['recent_deposits'] = $depList;

// Get recent bets list (last 10 across SaaS and 3rd-party)
$recentBets = [];

// From saas_bets
$sbq = $conn->query("SELECT game_code, amount AS bet_amount, win_amount, state, created_at FROM saas_bets WHERE user_id = $userId ORDER BY id DESC LIMIT 5");
if ($sbq) {
    while ($r = $sbq->fetch_assoc()) {
        $recentBets[] = [
            'game' => $r['game_code'],
            'bet_amount' => (float)$r['bet_amount'],
            'win_amount' => (float)$r['win_amount'],
            'status' => ((int)$r['state'] === 1) ? 'WIN' : (((int)$r['state'] === 2) ? 'LOSE' : 'PENDING'),
            'date' => $r['created_at']
        ];
    }
}

// From game_bet_logs
$gbq = $conn->query("SELECT game_code, vendor_code, bet_amount, win_amount, created_at FROM game_bet_logs WHERE user_id = $userId ORDER BY id DESC LIMIT 5");
if ($gbq) {
    while ($r = $gbq->fetch_assoc()) {
        $recentBets[] = [
            'game' => !empty($r['game_code']) ? $r['game_code'] : $r['vendor_code'],
            'bet_amount' => (float)$r['bet_amount'],
            'win_amount' => (float)$r['win_amount'],
            'status' => ((float)$r['win_amount'] > 0) ? 'WIN' : 'LOSE',
            'date' => $r['created_at']
        ];
    }
}

// Sort combined recent bets by date DESC
usort($recentBets, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$playerStats['betting']['recent_bets'] = array_slice($recentBets, 0, 10);

hack_json(200, 'User details fetched successfully.', [
    'found' => true,
    'user' => $playerStats
]);
