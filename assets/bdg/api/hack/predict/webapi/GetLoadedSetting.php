<?php
	require_once __DIR__ . '/../../conn.php';
	require_once __DIR__ . '/../../functions2.php';
	require_once __DIR__ . '/_activity_helpers.php';

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

	// Helper: does a table exist
	function tblExists($conn, $t) {
		$q = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
		return $q && $q->num_rows > 0;
	}
	// Helper: count unclaimed (processed=0) rows for a user in an award table
	function unclaimedCount($conn, $table, $userId) {
		if (!tblExists($conn, $table)) return 0;
		$q = $conn->query("SELECT COUNT(*) AS c FROM `$table` WHERE userkani = $userId AND processed = 0");
		if (!$q) return 0;
		$row = $q->fetch_assoc();
		return (int)($row['c'] ?? 0);
	}
	// Helper: SUM(price) for a user in an award table, optional "today only"
	function awardSum($conn, $table, $userId, $todayOnly = false) {
		if (!tblExists($conn, $table)) return 0.0;
		$where = "userkani = $userId";
		if ($todayOnly) $where .= " AND DATE(shonu) = CURDATE()";
		$q = $conn->query("SELECT COALESCE(SUM(price),0) AS s FROM `$table` WHERE $where");
		if (!$q) return 0.0;
		$row = $q->fetch_assoc();
		return (float)($row['s'] ?? 0);
	}

	$shonubody = file_get_contents("php://input");
	$shonupost = json_decode($shonubody, true);

	if ($_SERVER['REQUEST_METHOD'] != 'GET') {
		if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			// Frontend signs the payload with keys sorted alphabetically (excluding
			// signature/track/xosoBettingData). The red-dot poller sends onlyRedDot:true,
			// so it must be included in the signature string in its sorted position.
			$onlyRedDot = isset($shonupost['onlyRedDot']) ? filter_var($shonupost['onlyRedDot'], FILTER_VALIDATE_BOOLEAN) : false;
			if (isset($shonupost['onlyRedDot'])) {
				$boolStr = $onlyRedDot ? 'true' : 'false';
				$shonustr = '{"language":'.$language.',"onlyRedDot":'.$boolStr.',"random":"'.$random.'"}';
			} else {
				$shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
			}
			$shonusign = strtoupper(md5($shonustr));
			if($shonusign == $signature || verify_signature($shonupost, $signature)){
				$bearer = explode(" ", isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '');
				$author = isset($bearer[1]) ? $bearer[1] : '';
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				if($data_auth && $data_auth['status'] === 'Success') {
					$sesquery = "SELECT id FROM shonu_subjects WHERE akshinak = '$author' OR id = '".intval($data_auth['payload']['id'])."'";
					$sesresult = $conn->query($sesquery);
					$sesnum = $sesresult ? mysqli_num_rows($sesresult) : 0;
					if($sesnum == 1){
						$userId = (int)$data_auth['payload']['id'];

						// ---- RED DOT COUNTS (unclaimed awards per activity) ----
						$invitationBonusCount = unclaimedCount($conn, 'invite_bonus_table', $userId);
						$bettingRebateCount   = unclaimedCount($conn, 'return_awards_table', $userId);
						$activityAwardCount   = unclaimedCount($conn, 'daily_awards_table', $userId);
						$weeklyAwardCount     = unclaimedCount($conn, 'weekly_awards_table', $userId);

						// Invite Wheel: available spins from turntable
						$invitedWheelCount = 0;
						if (tblExists($conn, 'shonu_turntable')) {
							$wq = $conn->query("SELECT total_spins FROM shonu_turntable WHERE user_id = $userId");
							if ($wq && $wq->num_rows > 0) {
								$invitedWheelCount = (int)($wq->fetch_assoc()['total_spins'] ?? 0);
							}
						}

						$superJackpotCount    = 0;
						$firstGiftCount       = 0;
						$attendanceBonusCount = 0;
						$newbieGiftPackCount  = 0;

						$totalCount = $activityAwardCount + $invitationBonusCount + $bettingRebateCount
									+ $superJackpotCount + $firstGiftCount + $invitedWheelCount
									+ $attendanceBonusCount + $newbieGiftPackCount;

						// ---- REWARDS TOTALS (across all award tables for this user) ----
						$bonusTotals = get_all_activity_bonus_totals($conn, $userId);
						$totalRewards = $bonusTotals['totalRewards'];
						$todayRewards = $bonusTotals['todayRewards'];

						// Unclaimed "return award" amount (top-level returnAwards)
						$returnAwards = 0.0;
						if (tblExists($conn, 'return_awards_table')) {
							$rq = $conn->query("SELECT COALESCE(SUM(price),0) AS s FROM return_awards_table WHERE userkani = $userId AND processed = 0");
							if ($rq && ($rrow = $rq->fetch_assoc())) $returnAwards = (float)$rrow['s'];
						}

						// First recharge popup disabled
						$needPopupFirstRecharge = false;

						$data = [
							'needPopupFirstRecharge' => $needPopupFirstRecharge,
							'isExistGrandAward' => false,
							'children_Lv_RebateAmount_Yesterday' => 0.0,
							'returnAwards' => $returnAwards,
							'returnNewRechargeAwards' => null,
							'isARPay' => true,
							'isRSNPay' => false,
							'isLandingPageEnabled' => false,
							'landingPageUrl' => null,
							'isFinancePromptTextEnabled' => false,
							'financePromptText' => null,
							'isAppDownloadPromptTextEnabled' => false,
							'appDownloadPromptText' => null,
							'isAppForcedDownloadEnabled' => false,
							'appForcedDownloadUrl' => null,
							'downAppRewardBonusAmount' => 0.0,
							'registerGiftAmount' => 0.0,
							'activityRedDot' => [
								'activityAwardCount' => $activityAwardCount,
								'invitationBonusCount' => $invitationBonusCount,
								'bettingRebateCount' => $bettingRebateCount,
								'superJackpotCount' => $superJackpotCount,
								'firstGiftCount' => $firstGiftCount,
								'invitedWheelCount' => $invitedWheelCount,
								'attendanceBonusCount' => $attendanceBonusCount,
								'totalCount' => $totalCount,
								'newbieGiftPackCount' => $newbieGiftPackCount,
							],
							'isTaskState' => "1",
							'isOpenJackpotReward' => "1",
							'isOpenWashCode' => "1",
							'unJackpotCount' => 0,
							'isOpenActivityAward' => "1",
							'unWeeklyAwardCount' => $weeklyAwardCount,
							'isFinishUserGuidelines' => true,
							'isFirstUserDayRequest' => false,
							'isOpenChampion' => "0",
							'newbieGiftPackCount' => $newbieGiftPackCount,
							'newMemberGiftPackageSwitch' => "1",
							'todayRewards' => round($todayRewards, 2),
							'totalRewards' => round($totalRewards, 2),
						];

						$res = [
							'data' => $data,
							'code' => 0,
							'msg' => 'Succeed',
							'msgCode' => 0,
							'traceId' => "",
							'serviceNowTime' => $shnunc,
						];
						http_response_code(200);
						echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
	} else {
		http_response_code(405);
		echo json_encode($res);
	}
?>
