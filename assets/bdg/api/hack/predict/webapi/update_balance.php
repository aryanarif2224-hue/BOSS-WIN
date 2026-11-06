<?php
// api/update_balance.php

// 1. Database Connection & Functions Check
if (file_exists("../conn.php")) { 
    include "../conn.php"; 
    include "../functions2.php"; 
} else if (file_exists("../../conn.php")) { 
    require_once __DIR__ . '/../../conn.php'; 
    require_once __DIR__ . '/../../functions2.php'; 
} else { 
    header('Content-Type: application/json');
    die(json_encode(['code'=>500, 'msg'=>'DB Connection Not Found'])); 
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 2. Read Input & DEBUGGING
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Debug: Agar JSON khali hai
if ($data === null) {
    echo json_encode(['code'=>400, 'msg'=>'No Data Received', 'debug'=>$rawInput]);
    exit;
}

$action = isset($data['action']) ? $data['action'] : ''; 

// --- ROBUST AMOUNT CHECK (Ye kisi bhi key se amount utha lega) ---
$amount = 0;
if (isset($data['amount'])) $amount = floatval($data['amount']);
elseif (isset($data['bet_amount'])) $amount = floatval($data['bet_amount']);
elseif (isset($data['win_amount'])) $amount = floatval($data['win_amount']);
elseif (isset($data['bet'])) $amount = floatval($data['bet']);

// 3. Token Verify
$headers = apache_request_headers();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');

if(!$authHeader) {
    echo json_encode(['code'=>401, 'msg'=>'Token Missing']);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);

if (!function_exists('is_jwt_valid')) {
    echo json_encode(['code'=>500, 'msg'=>'JWT Function Missing']);
    exit;
}

$checkToken = is_jwt_valid($token);
$authData = json_decode($checkToken, true);

if(!isset($authData['status']) || $authData['status'] !== 'Success') {
    echo json_encode(['code'=>401, 'msg'=>'Invalid Token']);
    exit;
}

$userId = $authData['payload']['id'];

// 4. Logic Switch
switch ($action) {
    
    case 'fetch':
        $chk = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara='$userId'");
        if($chk && $chk->num_rows > 0){
            $row = $chk->fetch_assoc();
            echo json_encode(['code'=>0, 'balance'=>$row['motta']]);
        } else {
            echo json_encode(['code'=>0, 'balance'=>0]); 
        }
        break;

    case 'deduct':
        // DEBUG: Agar amount 0 hai to error ke sath value bhi dikhao
        if($amount <= 0) { 
            echo json_encode(['code'=>400, 'msg'=>"Invalid Amount Received: " . $amount, 'debug_data'=>$data]); 
            exit; 
        }
        
        $chk = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara='$userId'");
        $row = $chk->fetch_assoc();
        
        if(floatval($row['motta']) >= $amount) {
            $sql = "UPDATE shonu_kaichila SET motta = motta - $amount WHERE balakedara = '$userId'";
            if($conn->query($sql)) {
                $newBal = floatval($row['motta']) - $amount;
                echo json_encode(['code'=>0, 'msg'=>'Bet Placed', 'balance'=>$newBal]);
            } else {
                echo json_encode(['code'=>500, 'msg'=>'Database Error']);
            }
        } else {
            echo json_encode(['code'=>400, 'msg'=>'Low Balance']);
        }
        break;

    case 'win':
        if($amount <= 0) { 
            echo json_encode(['code'=>400, 'msg'=>"Invalid Win Amount: " . $amount]); 
            exit; 
        }
        
        $sql = "UPDATE shonu_kaichila SET motta = motta + $amount WHERE balakedara = '$userId'";
        if($conn->query($sql)) {
            $chk = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara='$userId'");
            $row = $chk->fetch_assoc();
            echo json_encode(['code'=>0, 'msg'=>'Win Added', 'balance'=>$row['motta']]);
        } else {
            echo json_encode(['code'=>500, 'msg'=>'Database Error']);
        }
        break;

    default:
        echo json_encode(['code'=>400, 'msg'=>"Unknown Action: $action"]);
        break;
}
?>