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
		list($auth_ok, $auth_res) = api_authenticate($conn, $shonupost);
		if (!$auth_ok) {
			http_response_code(isset($auth_res['http']) ? $auth_res['http'] : 200);
			$res['code'] = $auth_res['code'];
			$res['msg'] = $auth_res['msg'];
			$res['msgCode'] = $auth_res['msgCode'];
			echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

		$categoryType = (int)($shonupost['categoryType'] ?? ($shonupost['gameType'] ?? 0));
		$data = [];
						if($categoryType == 1){
							$data[0]['slotsTypeID'] = 7;
							$data[0]['slotsName'] = 'DG';
							$data[0]['state'] = 1;
							$data[0]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_202404121129431pg2.png';
							
							$data[1]['slotsTypeID'] = 16;
							$data[1]['slotsName'] = 'EVO_Video';
							$data[1]['state'] = 1;
							$data[1]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240306233708h2lc.png';
							
							$data[2]['slotsTypeID'] = 10;
							$data[2]['slotsName'] = 'AG_Video';
							$data[2]['state'] = 1;
							$data[2]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240306233831aub4.png';
							
							$data[3]['slotsTypeID'] = 26;
							$data[3]['slotsName'] = 'WM_Video';
							$data[3]['state'] = 1;
							$data[3]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240306234023867q.png';
							
							$data[4]['slotsTypeID'] = 27;
							$data[4]['slotsName'] = 'SEXY_Video';
							$data[4]['state'] = 1;
							$data[4]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240316123435brno.png';
						}
						else if($categoryType == 4){
							$data[0]['slotsTypeID'] = 19;
							$data[0]['slotsName'] = 'Card365';
							$data[0]['state'] = 1;
							$data[0]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240306233818ju4l.png';
							
							$data[1]['slotsTypeID'] = 21;
							$data[1]['slotsName'] = 'V8Card';
							$data[1]['state'] = 1;
							$data[1]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240306233853vhdr.png';
						}						
						else if($categoryType == 0){
							$data[0]['slotsTypeID'] = 2;
							$data[0]['slotsName'] = 'CQ9';
							$data[0]['state'] = 1;
							$data[0]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183506uo8v.png';
							
							$data[1]['slotsTypeID'] = 4;
							$data[1]['slotsName'] = 'MG';
							$data[1]['state'] = 1;
							$data[1]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183534v51y.png';
							
							$data[2]['slotsTypeID'] = 6;
							$data[2]['slotsName'] = 'JDB';
							$data[2]['state'] = 1;
							$data[2]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183450ph8e.png';
							
							$data[3]['slotsTypeID'] = 17;
							$data[3]['slotsName'] = 'EVO_Electronic';
							$data[3]['state'] = 1;
							$data[3]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183343jxf6.png';
							
							$data[4]['slotsTypeID'] = 18;
							$data[4]['slotsName'] = 'JILI';
							$data[4]['state'] = 1;
							$data[4]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183353rwkf.png';
							
							$data[5]['slotsTypeID'] = 12;
							$data[5]['slotsName'] = 'AG_Electronic';
							$data[5]['state'] = 1;
							$data[5]['vendorImg'] = 'https://apiimages.skywin786.in/BDGWin/vendorlogo/vendorlogo_20240321183403qjx1.png';
						}
						$res['data'] = $data;
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
						exit;
	} else {		
		http_response_code(405);
		echo json_encode($res);
	}	
?>