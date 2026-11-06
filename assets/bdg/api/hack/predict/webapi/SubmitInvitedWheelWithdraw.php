<?php 
	require_once __DIR__ . '/../../conn.php';
	require_once __DIR__ . '/../../functions2.php';
	
	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('vary: Origin');
	
	date_default_timezone_set("Asia/Kolkata");
	$shnunc = date("Y-m-d H:i:s");
	$res = [
		'code' => 11,
		'msg' => 'Method not allowed',
		'msgCode' => 12,
		'serviceNowTime' => $shnunc,
	];
	$shonubody = file_get_contents("php://input");
	$shonupost = json_decode($shonubody, true);
	
	if ($_SERVER['REQUEST_METHOD'] != 'GET') {
		if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
			$shonusign = strtoupper(md5($shonustr));
			if($shonusign == $signature || verify_signature($shonupost, $signature)){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = $bearer[1];				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author' OR id = '".intval($data_auth['payload']['id'])."'";
					$sesresult = $conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					if($sesnum == 1){
					    $userId = $data_auth['payload']['id'];
						
						// Get withdrawal amount from request
						$withdrawAmount = isset($shonupost['amount']) ? (float)$shonupost['amount'] : 0;
						
						if($withdrawAmount <= 0) {
							$res['code'] = 7;
							$res['msg'] = 'Invalid amount';
							$res['msgCode'] = 6;
							http_response_code(200);
							echo json_encode($res);
							exit();
						}
						
						// Get user's current balance
						$balanceQuery = "SELECT invited_wheel_amount FROM shonu_turntable WHERE user_id = ".$userId;
						$balanceResult = $conn->query($balanceQuery);
						$currentBalance = 0.00;
						
						if($balanceResult && mysqli_num_rows($balanceResult) > 0) {
							$balanceRow = mysqli_fetch_array($balanceResult);
							$currentBalance = (float)$balanceRow['invited_wheel_amount'];
						}
						
						// Check if user has sufficient balance
						if($currentBalance < $withdrawAmount) {
							$res['code'] = 8;
							$res['msg'] = 'Insufficient balance';
							$res['msgCode'] = 7;
							http_response_code(200);
							echo json_encode($res);
							exit();
						}
						
						// Get minimum withdrawal amount
						$minWithdrawQuery = "SELECT value FROM tbl_config WHERE name = 'min_withdraw_amount' LIMIT 1";
						$minWithdrawResult = $conn->query($minWithdrawQuery);
						$minWithdrawAmount = 1.00;
						
						if($minWithdrawResult && mysqli_num_rows($minWithdrawResult) > 0) {
							$minWithdrawRow = mysqli_fetch_array($minWithdrawResult);
							$minWithdrawAmount = (float)$minWithdrawRow['value'];
						}
						
						// Check if amount meets minimum requirement
						if($withdrawAmount < $minWithdrawAmount) {
							$res['code'] = 9;
							$res['msg'] = "Minimum withdrawal amount is $minWithdrawAmount";
							$res['msgCode'] = 8;
							http_response_code(200);
							echo json_encode($res);
							exit();
						}
						
						// Start transaction
						$conn->begin_transaction();
						
						try {
							// 1. Deduct amount from turntable balance
							$updateTurntableQuery = "UPDATE shonu_turntable 
													SET invited_wheel_amount = invited_wheel_amount - $withdrawAmount,
														updated_at = NOW()
													WHERE user_id = $userId";
							$conn->query($updateTurntableQuery);
							
							// 2. Add amount to main balance (shonu_kaichila)
							$updateMainBalanceQuery = "UPDATE shonu_kaichila 
													  SET motta = motta + $withdrawAmount,
														  updated_at = NOW()
													  WHERE balakedara = $userId";
							$conn->query($updateMainBalanceQuery);
							
							// 3. Create withdrawal record
							$withdrawTime = date('Y-m-d H:i:s');
							$insertWithdrawQuery = "INSERT INTO shonu_withdrawals 
													(user_id, amount, withdrawal_type, status, created_at) 
													VALUES 
													($userId, $withdrawAmount, 'turntable', 'pending', '$withdrawTime')";
							$conn->query($insertWithdrawQuery);
							$withdrawalId = $conn->insert_id;
							
							// 4. Create transaction record
							$insertTransactionQuery = "INSERT INTO shonu_transactions 
													 (user_id, amount, transaction_type, description, status, created_at) 
													 VALUES 
													 ($userId, $withdrawAmount, 'withdrawal', 'Turntable withdrawal', 'completed', '$withdrawTime')";
							$conn->query($insertTransactionQuery);
							
							// Commit transaction
							$conn->commit();
							
							// Prepare success response
							$data['withdrawalId'] = (int)$withdrawalId;
							$data['amount'] = (float)$withdrawAmount;
							$data['newBalance'] = (float)($currentBalance - $withdrawAmount);
							$data['withdrawTime'] = $withdrawTime;
							
							$res['data'] = $data;
							$res['code'] = 0;
							$res['msg'] = 'Withdrawal request submitted successfully';
							$res['msgCode'] = 0;
							
						} catch (Exception $e) {
							// Rollback transaction on error
							$conn->rollback();
							
							$res['code'] = 500;
							$res['msg'] = 'Transaction failed';
							$res['msgCode'] = 13;
						}
						
						$res['serviceNowTime'] = $shnunc;
						http_response_code(200);
						echo json_encode($res);					
					}
					else{
						$res['code'] = 4;
						$res['msg'] = 'No operation permission';
						$res['msgCode'] = 2;
						http_response_code(401);
						echo json_encode($res);
					}					
				}
				else{					
					$res['code'] = 4;
					$res['msg'] = 'No operation permission';
					$res['msgCode'] = 2;
					http_response_code(401);
					echo json_encode($res);					
				}
			}
			else{
				$res['code'] = 5;
				$res['msg'] = 'Wrong signature';
				$res['msgCode'] = 3;
				http_response_code(200);
				echo json_encode($res);				
			}
		}
		else{
			$res['code'] = 7;
			$res['msg'] = 'Param is Invalid';
			$res['msgCode'] = 6;
			http_response_code(200);
			echo json_encode($res);			
		}		
	}
	else{
		$res['code'] = 11;
		$res['msg'] = 'Method not allowed';
		$res['msgCode'] = 12;
		http_response_code(405);
		echo json_encode($res);		
	}
?>