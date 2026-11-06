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

	// Only POST allowed
	if ($_SERVER['REQUEST_METHOD'] != 'GET') {

		if (
			isset($shonupost['amount']) && isset($shonupost['bid']) &&
			isset($shonupost['language']) && isset($shonupost['pwd']) &&
			isset($shonupost['random']) && isset($shonupost['signature']) &&
			isset($shonupost['timestamp']) && isset($shonupost['type'])
		) {

			// Sanitizing
			$amount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['amount']));
			$bid = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['bid']));
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$pwd = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['pwd']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$type = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['type']));

			// Signature Check
			$shonustr = '{"amount":'.$amount.',"bid":"'.$bid.'","language":'.$language.',"pwd":"'.$pwd.'","random":"'.$random.'","type":'.$type.'}';
			$shonusign = strtoupper(md5($shonustr));

			if ($shonusign == $signature || verify_signature($shonupost, $signature)) {

				// JWT CHECK
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = $bearer[1];
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, true);

				if ($data_auth['status'] === 'Success') {

					$shonuid = $data_auth['payload']['id'];

					// DEMO CHECK
					$checkDemoQuery = "SELECT 1 FROM demo WHERE balakedara = '$shonuid'";
					$isDemoUser = mysqli_num_rows($conn->query($checkDemoQuery)) > 0;

					// 1. Daily withdrawal limit check (Max 2 withdrawals per day)
					$withdrawalsMade = get_user_daily_withdrawals($conn, $shonuid);
					if ($withdrawalsMade >= 2) {
						$res['code'] = 8;
						$res['msg'] = 'Daily withdrawal limit reached (2/2)';
						$res['msgCode'] = 9;
						http_response_code(200);
						echo json_encode($res);
						exit;
					}

					// 2. Wagering requirement (Need to Bet = 1x Deposit + 1x Bonus)
					$wStats = get_user_wagering_stats($conn, $shonuid);
					if ($wStats['need_to_bet'] > 0) {
						$res['code'] = 1;
						$res['msg'] = 'Need to bet ₹' . number_format($wStats['need_to_bet'], 2) . ' before withdrawal';
						$res['msgCode'] = 128;
						http_response_code(200);
						echo json_encode($res);
						exit;
					}

					// Balance fetch
					$balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = '$shonuid'";
					$balresult = $conn->query($balquery);
					$balarr = mysqli_fetch_array($balresult);

					// VALIDATION
					if ($amount >= 110 && $amount <= 50000 && $amount <= $balarr['motta']) {

						// UPDATE Balance
						$mottanutan = $balarr['motta'] - $amount;
						$conn->query("UPDATE shonu_kaichila SET motta='$mottanutan' WHERE balakedara='$shonuid'");

						// SERIAL GENERATE
						$date = date("Ymd");
						$time = time();
						$serial = 'W' . $date . $time . rand(1000, 9999);

						// ------------------------------------------
						// ⭐ USER INFO FROM upi_withdrawal TABLE (UPI only) ⭐
						// ------------------------------------------

						$userName = ''; $userMobile = ''; $userUpiId = '';

						if ($type == 2) {
							$userQ = $conn->query("SELECT name, mobile, upi_id
								FROM upi_withdrawal
								WHERE user_id = '$shonuid'
								LIMIT 1");

							$userData = $userQ ? mysqli_fetch_assoc($userQ) : null;

							if (!$userData) {
								$res['code'] = 10;
								$res['msg'] = 'User UPI details not found';
								$res['msgCode'] = 404;
								echo json_encode($res);
								exit;
							}
							$userName   = $userData['name'];
							$userMobile = $userData['mobile'];
							$userUpiId  = $userData['upi_id'];
						}

						// ------------------------------------------
						// ⭐ WITHDRAWAL INSERT ⭐
						// ------------------------------------------

						if ($type == 2) {
							$sthiti = $isDemoUser ? 1 : 0;
							$tathya = $conn->query("INSERT INTO `hintegedukolli`
								(`balakedara`, `motta`, `dharavahi`, `khateshonu`, `dinankavannuracisi`, `madari`, `tike`, `sthiti`, `remarks`)
								VALUES
								('$shonuid', '$amount', '$serial', '$bid', '$shnunc', '$type', 'Applied', '$sthiti', 'UPI: $userUpiId')");

							if (tbl_exists($conn, 'upi_withdraw_requests')) {
								@$conn->query("INSERT INTO `upi_withdraw_requests`
									(`user_id`, `user_name`, `mobile`, `upi_id`, `amount`, `serial`, `bid`, `created_at`, `type`, `status`)
									VALUES
									('$shonuid', '$userName', '$userMobile', '$userUpiId', '$amount', '$serial', '$bid', '$shnunc', '$type', 'Pending')");
							}

						} else if ($type == 21) {
							// ===== ARPay (ARB): convert INR -> ARB, credit AR wallet =====
							$rate = 21.0;
							$rateResp = @file_get_contents('https://arbpay.nextapi.site/api/merchant/rate', false, stream_context_create([
								'http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\nX-API-KEY: ARB_95B97D46ABC99B021F88CF51CBD38276\r\n",'content'=>'{}','timeout'=>8]
							]));
							$rj = json_decode($rateResp, true);
							if (is_array($rj) && isset($rj['data']['buy']) && $rj['data']['buy'] > 0) $rate = (float)$rj['data']['buy'];
							$arb = round($amount / $rate, 8);

							$creditResp = @file_get_contents('https://arbpay.nextapi.site/api/merchant/credit', false, stream_context_create([
								'http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\nX-API-KEY: ARB_95B97D46ABC99B021F88CF51CBD38276\r\n",
									'content'=>json_encode(['userId'=>(string)$shonuid,'amount'=>$arb,'kind'=>'withdraw_as_arb','title'=>'Withdraw as ARB','ref'=>$serial]),'timeout'=>12]
							]));
							$cj = json_decode($creditResp, true);
							$creditOk = is_array($cj) && (($cj['code'] ?? 1) == 0);

							// ARB is credited to the AR wallet instantly -> mark as SUCCESS (1)
							$sthiti = ($isDemoUser || $creditOk) ? 1 : 0;
							$tike21 = $creditOk ? 'Success' : 'Applied';
							$tathya = $conn->query("INSERT INTO `hintegedukolli`
								(`balakedara`, `motta`, `dharavahi`, `khateshonu`, `dinankavannuracisi`, `madari`, `tike`, `sthiti`)
								VALUES
								('$shonuid', '$amount', '$serial', '$bid', '$shnunc', '$type', '$tike21', '$sthiti')");

							$shonupost['arbAmount'] = $arb;

						} else {
							// NORMAL withdraw
							$sthiti = $isDemoUser ? 1 : 0;

							$tathya = $conn->query("INSERT INTO `hintegedukolli`
								(`balakedara`, `motta`, `dharavahi`, `khateshonu`, `dinankavannuracisi`, `madari`, `tike`, `sthiti`)
								VALUES
								('$shonuid', '$amount', '$serial', '$bid', '$shnunc', '$type', 'Applied', '$sthiti')");
						}

						// FINAL RESPONSE
						if ($tathya && (!isset($creditOk) || $creditOk)) {

							$res['data'] = [
								'user_id' => $shonuid,
								'serial' => $serial,
								'amount' => $amount,
								'type' => $type,
								'time' => $shnunc
							];
							if ($type == 21 && isset($shonupost['arbAmount'])) { $res['data']['arbAmount'] = $shonupost['arbAmount']; $res['data']['rate'] = $rate; }

							$res['code'] = 0;
							$res['msg'] = 'Succeed';
							$res['msgCode'] = 0;
							http_response_code(200);

						} else {

							$res['code'] = 1;
							$res['msg'] = 'DB Insert Error: ' . mysqli_error($conn);
							$res['msgCode'] = 101;
							http_response_code(500);

						}

					} else {

						$res['code'] = 1;
						$res['msg'] = 'Insufficient balance or invalid amount';
						$res['msgCode'] = 142;
						http_response_code(200);

					}

				} else {

					$res['code'] = 4;
					$res['msg'] = 'Invalid Token';
					$res['msgCode'] = 2;
					http_response_code(401);

				}

			} else {

				$res['code'] = 5;
				$res['msg'] = 'Wrong signature';
				$res['msgCode'] = 3;
				http_response_code(200);

			}

		} else {

			$res['code'] = 7;
			$res['msg'] = 'Invalid Parameters';
			$res['msgCode'] = 6;
			http_response_code(200);

		}

	} else {
		http_response_code(405);
		echo json_encode($res);
		exit;
	}

	echo json_encode($res);
	mysqli_close($conn);
?>
