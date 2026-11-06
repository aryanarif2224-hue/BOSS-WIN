<?php
// mine.php - AGGRESSIVE KILL MODE & DEBUGGING ADDED

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');

date_default_timezone_set("Asia/Kolkata");

// 1. INPUT
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$action = isset($data['action']) ? $data['action'] : '';

// 2. AUTH
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) { echo json_encode(['code' => 401, 'msg' => 'Token missing']); exit; }
$is_jwt_valid = is_jwt_valid($token);
$auth_data = json_decode($is_jwt_valid, true);
if ($auth_data['status'] !== 'Success') { echo json_encode(['code' => 401, 'msg' => 'Invalid Token']); exit; }
$userid = $auth_data['payload']['id'];

// --- INIT ---
if ($action == 'init') {
    $q = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = '$userid'");
    $row = $q->fetch_assoc();
    echo json_encode(['code' => 0, 'balance' => $row['motta']]);
    exit;
}

// --- START GAME ---
if ($action == 'start') {
    $amount = floatval($data['amount']);
    $userMines = intval($data['mines']);

    if ($amount < 10) { echo json_encode(['code' => 1, 'msg' => 'Min bet 10']); exit; }
    if ($userMines < 1 || $userMines > 24) { echo json_encode(['code' => 1, 'msg' => 'Invalid mines']); exit; }

    // Balance
    $q = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = '$userid'");
    $row = $q->fetch_assoc();
    $balance = floatval($row['motta']);

    if ($balance < $amount) { echo json_encode(['code' => 1, 'msg' => 'Low balance']); exit; }
    $newBal = $balance - $amount;
    $conn->query("UPDATE shonu_kaichila SET motta = '$newBal' WHERE balakedara = '$userid'");

    // Generate Mines (Randomly for now, will fix on Reveal)
    $allSpots = range(0, 24);
    shuffle($allSpots);
    $activeMines = array_slice($allSpots, 0, $userMines);

    $minesJson = json_encode(array_values($activeMines));
    $revealedJson = json_encode([]);

    $conn->query("DELETE FROM mines_game_session WHERE user_id = '$userid'");
    $stmt = $conn->prepare("INSERT INTO mines_game_session (user_id, bet_amount, mines_count, mines_locations, revealed_tiles, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("idiis", $userid, $amount, $userMines, $minesJson, $revealedJson);
    $stmt->execute();

    echo json_encode(['code' => 0, 'msg' => 'Started', 'balance' => $newBal]);
    exit;
}

// --- REVEAL (MAIN LOGIC) ---
if ($action == 'reveal') {
    $idx = intval($data['index']);

    $q = $conn->query("SELECT * FROM mines_game_session WHERE user_id = '$userid' AND active = 1");
    if ($q->num_rows == 0) { echo json_encode(['code' => 1, 'msg' => 'Game expired']); exit; }
    
    $game = $q->fetch_assoc();
    $minesLoc = json_decode($game['mines_locations'], true);
    $revealed = json_decode($game['revealed_tiles'], true);
    $bet = floatval($game['bet_amount']);
    $userMines = intval($game['mines_count']);

    if (in_array($idx, $revealed)) { echo json_encode(['code' => 1, 'msg' => 'Clicked']); exit; }

    // =========================================================
    // 🔥 FORCE LOGIC START 🔥
    // =========================================================
    
    // 1. Fetch Setting (Ensure 0 is treated as 0, not null)
    $setQ = $conn->query("SELECT setting_value FROM game_settings WHERE setting_name = 'mines_win_chance'");
    if ($setQ->num_rows > 0) {
        $row = $setQ->fetch_assoc();
        $winChance = intval($row['setting_value']);
    } else {
        $winChance = 50; // Default
    }

    $debugMsg = "Chance: $winChance%";
    $isBomb = in_array($idx, $minesLoc);
    $gridUpdated = false;

    // 2. APPLY LOGIC
    if ($winChance == 0) {
        // === 💀 KILL MODE (0%) ===
        // User MUST hit a bomb.
        if (!$isBomb) {
            // Agar Safe box par click kiya, to waha BOMB laga do!
            $minesLoc[] = $idx; // Add bomb here
            
            // Ek purana bomb hata do (Taaki count same rahe)
            // (Optional: Agar hume bas harana hai to count badha bhi sakte hain)
            array_shift($minesLoc); 
            
            $minesLoc = array_values($minesLoc); // Reset Indexes (Very Important!)
            $isBomb = true; // Ab ye bomb ban gaya
            $gridUpdated = true;
            $debugMsg .= " | Mode: KILL | Action: Safe -> Bomb";
        }
    } 
    elseif ($winChance == 100) {
        // === 💎 WIN MODE (100%) ===
        // User MUST NOT hit a bomb.
        if ($isBomb) {
            // Agar Bomb par click kiya, to Bomb hata do!
            $key = array_search($idx, $minesLoc);
            unset($minesLoc[$key]);
            
            // Bomb ko kisi aur safe jagah bhej do
            $safeSpots = array_diff(range(0, 24), $minesLoc, $revealed, [$idx]);
            if (!empty($safeSpots)) {
                $safeSpots = array_values($safeSpots);
                $newSpot = $safeSpots[0]; // Pick first available safe spot
                $minesLoc[] = $newSpot;
            }
            
            $minesLoc = array_values($minesLoc); // Reset Indexes
            $isBomb = false; // Ab ye safe ban gaya
            $gridUpdated = true;
            $debugMsg .= " | Mode: WIN | Action: Bomb -> Safe";
        }
    } 
    else {
        // === ⚖️ PERCENTAGE MODE (1-99%) ===
        $roll = rand(1, 100);
        $shouldWin = ($roll <= $winChance);
        
        if ($shouldWin && $isBomb) {
            // Make Safe
            $key = array_search($idx, $minesLoc);
            unset($minesLoc[$key]);
            
            $safeSpots = array_diff(range(0, 24), $minesLoc, $revealed, [$idx]);
            if (!empty($safeSpots)) {
                $minesLoc[] = array_values($safeSpots)[0];
            }
            $minesLoc = array_values($minesLoc);
            $isBomb = false;
            $gridUpdated = true;
        } 
        elseif (!$shouldWin && !$isBomb) {
            // Make Bomb
            $minesLoc[] = $idx;
            array_shift($minesLoc);
            $minesLoc = array_values($minesLoc);
            $isBomb = true;
            $gridUpdated = true;
        }
    }

    // 3. SAVE UPDATED GRID
    if ($gridUpdated) {
        $newMinesJson = json_encode($minesLoc);
        $conn->query("UPDATE mines_game_session SET mines_locations = '$newMinesJson' WHERE user_id = '$userid'");
    }

    // =========================================================
    // END LOGIC
    // =========================================================

    if ($isBomb) {
        // 💥 BOOM
        $conn->query("DELETE FROM mines_game_session WHERE user_id = '$userid'");
        echo json_encode([
            'code' => 0, 
            'status' => 'bomb', 
            'mines' => $minesLoc, 
            'profit' => 0,
            'debug' => $debugMsg // Check this in Network Tab
        ]);
        exit;
    }

    // 💎 SAFE
    $revealed[] = $idx;
    
    // Profit Calc
    $multiplier = 1.0;
    $steps = count($revealed);
    for ($i = 0; $i < $steps; $i++) {
        $avail = 25 - $i;
        $safe = 25 - $i - $userMines;
        if($safe > 0) $multiplier *= ($avail / $safe);
    }
    $profit = floor($bet * $multiplier * 100) / 100;

    // Auto Win Check
    $maxSafe = 25 - $userMines;
    $game_over = (count($revealed) >= $maxSafe);

    $revJson = json_encode($revealed);
    $conn->query("UPDATE mines_game_session SET revealed_tiles = '$revJson' WHERE user_id = '$userid'");

    echo json_encode([
        'code' => 0, 
        'status' => 'safe', 
        'profit' => $profit, 
        'game_over' => $game_over,
        'debug' => $debugMsg
    ]);
    exit;
}

// --- CASHOUT ---
if ($action == 'cashout') {
    $q = $conn->query("SELECT * FROM mines_game_session WHERE user_id = '$userid' AND active = 1");
    if ($q->num_rows == 0) { echo json_encode(['code' => 1, 'msg' => 'Play first']); exit; }

    $game = $q->fetch_assoc();
    $revealed = json_decode($game['revealed_tiles'], true);
    $bet = floatval($game['bet_amount']);
    $userMines = intval($game['mines_count']);
    $minesLoc = json_decode($game['mines_locations'], true);

    if (count($revealed) == 0) { echo json_encode(['code' => 1, 'msg' => 'Play first']); exit; }

    $multiplier = 1.0;
    $steps = count($revealed);
    for ($i = 0; $i < $steps; $i++) {
        $avail = 25 - $i;
        $safe = 25 - $i - $userMines;
        if($safe > 0) $multiplier *= ($avail / $safe);
    }
    $winAmount = floor($bet * $multiplier * 100) / 100;

    $conn->query("UPDATE shonu_kaichila SET motta = motta + $winAmount WHERE balakedara = '$userid'");
    $conn->query("DELETE FROM mines_game_session WHERE user_id = '$userid'");

    echo json_encode(['code' => 0, 'winAmount' => $winAmount, 'mines' => $minesLoc]);
    exit;
}
?>