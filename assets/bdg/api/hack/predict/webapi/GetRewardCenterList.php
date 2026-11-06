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

	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

	date_default_timezone_set("Asia/Kolkata");
	$shnunc = date("Y-m-d H:i:s");
	$res = [
		'code' => 11,
		'msg' => 'Method not allowed',
		'msgCode' => 12,
		'serviceNowTime' => $shnunc,
	];

	// ---- helpers ----------------------------------------------------------
	function tblExists($conn, $t) {
		$q = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
		return $q && $q->num_rows > 0;
	}
	// expected_signature() is provided by functions2.php (included above).

	$shonubody = file_get_contents("php://input");
	$shonupost = json_decode($shonubody, true);

	if ($_SERVER['REQUEST_METHOD'] != 'POST') {
		echo json_encode($res);
		exit();
	}

	if (!(isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp']))) {
		$res['code'] = 7; $res['msg'] = 'Param is Invalid'; $res['msgCode'] = 6;
		echo json_encode($res); exit();
	}

	$signature = strtoupper(trim($shonupost['signature']));
	if (!verify_signature($shonupost, $signature)) {
		$res['code'] = 5; $res['msg'] = 'Wrong signature'; $res['msgCode'] = 3;
		echo json_encode($res); exit();
	}

	// ---- auth -------------------------------------------------------------
	$bearer = explode(" ", isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '');
	$author = isset($bearer[1]) ? $bearer[1] : '';
	$data_auth = json_decode(is_jwt_valid($author), true);
	if (!$data_auth || $data_auth['status'] !== 'Success') {
		$res['code'] = 4; $res['msg'] = 'No operation permission'; $res['msgCode'] = 2;
		http_response_code(401); echo json_encode($res); exit();
	}
	$authEsc = $conn->real_escape_string($author);
	$sesresult = $conn->query("SELECT id FROM shonu_subjects WHERE akshinak = '$authEsc'");
	if (!$sesresult || $sesresult->num_rows !== 1) {
		$res['code'] = 4; $res['msg'] = 'No operation permission'; $res['msgCode'] = 2;
		http_response_code(401); echo json_encode($res); exit();
	}
	$userId = (int)$data_auth['payload']['id'];

	// ---- request params ---------------------------------------------------
	$pageNo   = isset($shonupost['pageNo'])   ? max(1, (int)$shonupost['pageNo'])   : 1;
	$pageSize = isset($shonupost['pageSize']) ? max(1, (int)$shonupost['pageSize']) : 20;
	// receiveState: 0 = to-be-collected, 1 = received, 2 = expired, 3 = all
	$receiveState = isset($shonupost['receiveState']) && $shonupost['receiveState'] !== null ? (int)$shonupost['receiveState'] : 3;
	// rewardType: null = all, else a specific activity type
	$rewardType = (isset($shonupost['rewardType']) && $shonupost['rewardType'] !== null && $shonupost['rewardType'] !== '') ? (int)$shonupost['rewardType'] : null;
	// optional date filter (single "Y-m-d" string, or [start,end] array)
	$dateStart = null; $dateEnd = null;
	if (isset($shonupost['date']) && $shonupost['date'] !== null && $shonupost['date'] !== '') {
		$d = $shonupost['date'];
		if (is_array($d)) {
			$dateStart = isset($d[0]) ? substr($conn->real_escape_string($d[0]), 0, 10) : null;
			$dateEnd   = isset($d[1]) ? substr($conn->real_escape_string($d[1]), 0, 10) : $dateStart;
		} else {
			$dateStart = substr($conn->real_escape_string($d), 0, 10);
			$dateEnd   = $dateStart;
		}
	}

	// ---- reward sources: table -> rewardType ------------------------------
	// Award tables share schema: id, userkani, price, serial, shonu, remark, processed
	$sources = [
		['table' => 'invite_bonus_table',  'rewardType' => 20,  'kind' => 'award'],   // InvitationBonus
		['table' => 'return_awards_table', 'rewardType' => 102, 'kind' => 'award'],   // Laundry / washing-code rebate
		['table' => 'daily_awards_table',  'rewardType' => 7,   'kind' => 'award'],   // DailySignIn
		['table' => 'weekly_awards_table', 'rewardType' => 107, 'kind' => 'award'],   // Weekly / DailyTasks
		['table' => 'card_binding_gift_table', 'rewardType' => 101, 'kind' => 'award'], // Card binding gift
		['table' => 'first_full_gift_table',   'rewardType' => 105, 'kind' => 'award'], // First deposit gift
		['table' => 'recharge_gift_table',     'rewardType' => 106, 'kind' => 'award'], // Recharge gift
		['table' => 'agent_bonus_table',       'rewardType' => 20,  'kind' => 'award'], // Agent bonus
		['table' => 'new_members_bonus_table', 'rewardType' => 108, 'kind' => 'award'], // New member bonus
		['table' => 'gift_redemptions',        'rewardType' => 110, 'kind' => 'gift'],  // Gift Code Redemptions
		['table' => 'shonu_turntable_spins',   'rewardType' => 119, 'kind' => 'spin'],  // Turntable (auto-credited)
	];

	$all = [];

	foreach ($sources as $src) {
		// rewardType filter: skip non-matching sources
		if ($rewardType !== null && (int)$src['rewardType'] !== $rewardType) continue;
		if (!tblExists($conn, $src['table'])) continue;

		if ($src['kind'] === 'award') {
			// receiveState -> processed filter
			$where = "userkani = $userId";
			if ($receiveState === 0)      $where .= " AND processed = 0";   // to be collected
			else if ($receiveState === 1) $where .= " AND processed = 1";   // received
			else if ($receiveState === 2) $where .= " AND 1 = 0";           // expired (not tracked)
			// receiveState 3 -> all
			if ($dateStart) $where .= " AND DATE(shonu) BETWEEN '$dateStart' AND '$dateEnd'";

			$q = $conn->query("SELECT id, price, shonu, processed FROM `{$src['table']}` WHERE $where");
			if ($q) {
				while ($row = $q->fetch_assoc()) {
					$processed = (int)$row['processed'];
					$all[] = [
						'receiveTaskId'        => (int)$row['id'],
						'vipLevel'             => null,
						'vipRewardType'        => null,
						'amount'               => (float)$row['price'],
						'rewardType'           => (int)$src['rewardType'],
						'createTime'           => $row['shonu'],
						'receiveStatus'        => $processed === 1 ? 1 : 0,
						'recordType'           => 0,
						'maxRewardableAmount'  => null,
						'maxRewardableRate'    => null,
						'isReceiveButtonHidden'=> $processed === 1,
						'_sort'                => $row['shonu'],
					];
				}
			}
		} else if ($src['kind'] === 'gift') {
			if ($receiveState === 0 || $receiveState === 2) continue;
			$where = "user_id = $userId";
			if ($dateStart) $where .= " AND DATE(created_at) BETWEEN '$dateStart' AND '$dateEnd'";
			$q = $conn->query("SELECT id, amount, created_at FROM `{$src['table']}` WHERE $where AND amount > 0");
			if ($q) {
				while ($row = $q->fetch_assoc()) {
					$all[] = [
						'receiveTaskId'        => (int)$row['id'],
						'vipLevel'             => null,
						'vipRewardType'        => null,
						'amount'               => (float)$row['amount'],
						'rewardType'           => (int)$src['rewardType'],
						'createTime'           => $row['created_at'],
						'receiveStatus'        => 1,
						'recordType'           => 0,
						'maxRewardableAmount'  => null,
						'maxRewardableRate'    => null,
						'isReceiveButtonHidden'=> true,
						'_sort'                => $row['created_at'],
					];
				}
			}
		} else { // spin: always already received
			// spins never show under "to be collected" (0); show for 1 and 3
			if ($receiveState === 0 || $receiveState === 2) continue;
			$where = "user_id = $userId";
			if ($dateStart) $where .= " AND DATE(spin_time) BETWEEN '$dateStart' AND '$dateEnd'";
			$q = $conn->query("SELECT id, prize_amount, spin_time FROM `{$src['table']}` WHERE $where AND prize_amount > 0");
			if ($q) {
				while ($row = $q->fetch_assoc()) {
					$all[] = [
						'receiveTaskId'        => (int)$row['id'],
						'vipLevel'             => null,
						'vipRewardType'        => null,
						'amount'               => (float)$row['prize_amount'],
						'rewardType'           => (int)$src['rewardType'],
						'createTime'           => $row['spin_time'],
						'receiveStatus'        => 1,
						'recordType'           => 0,
						'maxRewardableAmount'  => null,
						'maxRewardableRate'    => null,
						'isReceiveButtonHidden'=> true,
						'_sort'                => $row['spin_time'],
					];
				}
			}
		}
	}

	// newest first
	usort($all, function ($a, $b) {
		return strcmp($b['_sort'], $a['_sort']);
	});
	$totalCount = count($all);
	$totalPage  = (int)ceil($totalCount / $pageSize);

	// paginate
	$offset = ($pageNo - 1) * $pageSize;
	$pageItems = array_slice($all, $offset, $pageSize);
	foreach ($pageItems as &$it) { unset($it['_sort']); }
	unset($it);

	$res = [
		'data' => [
			'list'       => $pageItems,
			'pageNo'     => $pageNo,
			'totalPage'  => $totalPage,
			'totalCount' => $totalCount,
		],
		'code' => 0,
		'msg' => 'Succeed',
		'msgCode' => 0,
		'traceId' => "",
		'serviceNowTime' => $shnunc,
	];
	http_response_code(200);
	echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit();
?>
