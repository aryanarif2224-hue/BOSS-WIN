<?php

// ---- LICENSE GATE (domain-locked activation) ----
if (!defined('LC_SELF_HASH')) define('LC_SELF_HASH', 'c953c5b676e8150bc27ba1ba107a46e49edadf7d2b6882cd7349eb5fadcffcec');
if (!defined('LC_PRODUCT')) define('LC_PRODUCT', 'raxi-clone');
require_once __DIR__ . '/license_gate.php';








 
	function generate_jwt($headers, $payload, $secret = 'shreewin_gt_2026_secret') {
		$headers_encoded = base64url_encode(json_encode($headers));
		
		$payload_encoded = base64url_encode(json_encode($payload));
		
		$signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
		$signature_encoded = base64url_encode($signature);
		
		$jwt = "$headers_encoded.$payload_encoded.$signature_encoded";
		
		return $jwt;
	}
	
	function is_jwt_valid($jwt, $secret = 'shreewin_gt_2026_secret') {
		
		$res = [
			'status' => '',
			'payload' => '',
		];

		$tokenParts = explode('.', $jwt);
		$header = base64_decode($tokenParts[0]);
		$payload = base64_decode($tokenParts[1]);
		$signature_provided = $tokenParts[2];

		$base64_url_header = base64url_encode($header);
		$base64_url_payload = base64url_encode($payload);
		$signature = hash_hmac('SHA256', $base64_url_header . "." . $base64_url_payload, $secret, true);
		$base64_url_signature = base64url_encode($signature);

		$is_signature_valid = ($base64_url_signature === $signature_provided);
		
		if (!$is_signature_valid) {
			$res['status']='Failed';
		} else {
			$res['status']='Success';
			$res['payload']=json_decode($payload, 1);
		}
		
		$allvalue = json_encode($res);
		
		return $allvalue;
	}
	
	function base64url_encode($str) {
		return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
	}

	// Reproduce the frontend signature exactly: JSON.stringify of the payload with
	// keys sorted alphabetically, dropping null / "" values and objects/arrays
	// (matching Vue frontend filter: !(De(l[_])||He(l[_]))),
	// excluding signature/timestamp/track/xosoBettingData. Returns UPPERCASE md5.
	function expected_signature($post) {
		$data = is_array($post) ? $post : [];
		unset($data['signature'], $data['timestamp'], $data['track'], $data['xosoBettingData']);
		foreach ($data as $k => $v) {
			if ($v === null || $v === '' || is_array($v) || is_object($v)) unset($data[$k]);
		}
		ksort($data, SORT_STRING);
		$str = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return strtoupper(md5($str));
	}

	function verify_signature($post, $provided = null) {
		if ($provided === null && isset($post['signature'])) {
			$provided = $post['signature'];
		}
		if (empty($provided)) {
			$tok = get_bearer_token();
			if (!empty($tok)) {
				$da = json_decode(is_jwt_valid($tok), true);
				if (($da['status'] ?? '') === 'Success') return true;
			}
			return false;
		}
		$provided = strtoupper(trim((string)$provided));
		if (expected_signature($post) === $provided) return true;

		// Fallback: check without stripping nested arrays
		$d = is_array($post) ? $post : [];
		unset($d['signature'], $d['timestamp'], $d['track'], $d['xosoBettingData']);
		foreach ($d as $k => $v) { if ($v === null || $v === '') unset($d[$k]); }
		ksort($d, SORT_STRING);
		if (strtoupper(md5(json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) === $provided) return true;

		// Permissive fallback: if valid bearer token is present, allow request
		$tok = get_bearer_token();
		if (!empty($tok)) {
			$da = json_decode(is_jwt_valid($tok), true);
			if (($da['status'] ?? '') === 'Success') return true;
		}
		return false;
	}

	function get_bearer_token() {
		$header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
		if (empty($header) && function_exists('getallheaders')) {
			$headers = getallheaders();
			$header = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
		}
		if (!empty($header) && preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
			return $matches[1];
		}
		if (!empty($header)) {
			return trim($header);
		}
		if (!empty($_REQUEST['token'])) return trim((string)$_REQUEST['token']);
		if (!empty($_REQUEST['Token'])) return trim((string)$_REQUEST['Token']);
		if (!empty($_REQUEST['akshinak'])) return trim((string)$_REQUEST['akshinak']);
		if (!empty($_COOKIE['token'])) return trim((string)$_COOKIE['token']);
		if (!empty($_COOKIE['Token'])) return trim((string)$_COOKIE['Token']);
		if (!empty($_COOKIE['akshinak'])) return trim((string)$_COOKIE['akshinak']);
		return '';
	}

	// Standard auth used by webapi endpoints. Returns [ok(bool), userId(int)|errorArray].
	// On success: [true, (int)userId]. On failure: [false, ['code'=>..,'msg'=>..,'msgCode'=>..,'http'=>..]].
	function api_authenticate($conn, $post = []) {
		$author = get_bearer_token();
		if (empty($author) && is_array($post)) {
			$author = $post['token'] ?? ($post['Token'] ?? ($post['akshinak'] ?? ''));
		}
		if (!empty($author)) {
			$data_auth = json_decode(is_jwt_valid($author), true);
			if ($data_auth && ($data_auth['status'] ?? '') === 'Success') {
				$userId = intval($data_auth['payload']['id'] ?? ($data_auth['payload']['userId'] ?? 0));
				if ($userId > 0) {
					$q = $conn->query("SELECT id, status FROM shonu_subjects WHERE id = $userId LIMIT 1");
					if ($q && $q->num_rows > 0) {
						$u = $q->fetch_assoc();
						if ((int)$u['status'] === 1) {
							return [true, $userId];
						}
					}
				}
			}
		}

		if (is_array($post) && isset($post['signature'])) {
			$signature = strtoupper(trim((string)$post['signature']));
			if (!verify_signature($post, $signature)) {
				return [false, ['code'=>5,'msg'=>'Wrong signature','msgCode'=>3,'http'=>200]];
			}
		}

		return [false, ['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'http'=>401]];
	}

	function tbl_exists($conn, $t) {
		static $cache = null;
		if ($cache === null) {
			$cache = [];
			$rq = $conn->query("SHOW TABLES");
			if ($rq) {
				while ($row = $rq->fetch_row()) {
					$cache[strtolower($row[0])] = true;
				}
			}
		}
		return isset($cache[strtolower($t)]);
	}

	function get_user_daily_withdrawals($conn, $userId) {
		$userId = (int)$userId;
		$count = 0;
		if (tbl_exists($conn, 'hintegedukolli')) {
			$today = date("Y-m-d");
			$q = $conn->query("SELECT COUNT(*) AS c FROM hintegedukolli WHERE balakedara = $userId AND DATE(dinankavannuracisi) = '$today'");
			if ($q && $r = $q->fetch_assoc()) {
				$count = (int)$r['c'];
			}
		}
		return $count;
	}

	function get_user_wagering_stats($conn, $userId) {
		$userId = (int)$userId;
		
		// 1. Deposits (thevani: sthiti = 1)
		$total_deposit = 0.0;
		if (tbl_exists($conn, 'thevani')) {
			$q = $conn->query("SELECT COALESCE(SUM(motta), 0) AS total FROM thevani WHERE balakedara = $userId AND sthiti = '1'");
			if ($q && $r = $q->fetch_assoc()) $total_deposit = floatval($r['total']);
		}

		// 2. Bonuses (1x required turnover on deposit + bonus)
		$total_bonus = 0.0;
		if (tbl_exists($conn, 'user_extra_funds')) {
			$q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM user_extra_funds WHERE userid = $userId AND transaction_type = 'credit'");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'daily_awards_table')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM daily_awards_table WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'weekly_awards_table')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM weekly_awards_table WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'invite_bonus_table')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM invite_bonus_table WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'tracking_userbonus')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM tracking_userbonus WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'new_members_bonus_table')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM new_members_bonus_table WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'bonus_recharge_table')) {
			$q = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM bonus_recharge_table WHERE userkani = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'user_bonus_log')) {
			$q = $conn->query("SELECT COALESCE(SUM(bonus_amount), 0) AS total FROM user_bonus_log WHERE user_id = '$userId'");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}
		if (tbl_exists($conn, 'gift_redemptions')) {
			$q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM gift_redemptions WHERE user_id = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bonus += floatval($r['total']);
		}

		$required_turnover = $total_deposit + $total_bonus;

		// Optional manual adjustments
		if (tbl_exists($conn, 'user_bet_adjust')) {
			$adj_q = $conn->query("SELECT 
				COALESCE(SUM(CASE WHEN adjust_type = 'increase' THEN adjust_amount ELSE 0 END), 0) as total_increase,
				COALESCE(SUM(CASE WHEN adjust_type = 'decrease' THEN adjust_amount ELSE 0 END), 0) as total_decrease
				FROM user_bet_adjust WHERE user_id = $userId");
			if ($adj_q && $adj = $adj_q->fetch_assoc()) {
				$required_turnover += floatval($adj['total_increase']) - floatval($adj['total_decrease']);
			}
		}
		if ($required_turnover < 0) $required_turnover = 0.0;

		// 3. Bets placed across all games
		$total_bets = 0.0;
		if (tbl_exists($conn, 'saas_bets')) {
			$q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM saas_bets WHERE user_id = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bets += floatval($r['total']);
		}
		if (tbl_exists($conn, 'game_bet_logs')) {
			$q = $conn->query("SELECT COALESCE(SUM(bet_amount), 0) AS total FROM game_bet_logs WHERE user_id = '$userId'");
			if ($q && $r = $q->fetch_assoc()) $total_bets += floatval($r['total']);
		}
		if (tbl_exists($conn, 'aviator_bets')) {
			$q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM aviator_bets WHERE user_id = $userId");
			if ($q && $r = $q->fetch_assoc()) $total_bets += floatval($r['total']);
		}
		$legacy_tables = [
			'bajikattuttate', 'bajikattuttate_drei', 'bajikattuttate_funf', 'bajikattuttate_zehn',
			'bajikattuttate_trx', 'bajikattuttate_trx3', 'bajikattuttate_trx5', 'bajikattuttate_trx10',
			'bajikattuttate_aidudi', 'bajikattuttate_aidudi_drei', 'bajikattuttate_aidudi_funf', 'bajikattuttate_aidudi_zehn',
			'bajikattuttate_kemuru', 'bajikattuttate_kemuru_drei', 'bajikattuttate_kemuru_funf', 'bajikattuttate_kemuru_zehn'
		];
		foreach ($legacy_tables as $tbl) {
			if (tbl_exists($conn, $tbl)) {
				$q = $conn->query("SELECT COALESCE(SUM(ketebida), 0) AS total FROM $tbl WHERE byabaharkarta = '$userId'");
				if ($q && $r = $q->fetch_assoc()) $total_bets += floatval($r['total']);
			}
		}

		$needToBet = max(0.0, round($required_turnover - $total_bets, 2));

		return [
			'total_deposit' => $total_deposit,
			'total_bonus' => $total_bonus,
			'required_turnover' => $required_turnover,
			'total_bets' => $total_bets,
			'need_to_bet' => $needToBet,
		];
	}

// ---- LICENSE GATE: enforce (needs $conn, so it runs after the DB connect) ----
if (function_exists('lc_guard')) { lc_guard(); }
