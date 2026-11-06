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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit();
}

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
		http_response_code($auth_res['http'] ?? 200);
		echo json_encode(['code' => $auth_res['code'], 'msg' => $auth_res['msg'], 'msgCode' => $auth_res['msgCode'] ?? 2, 'serviceNowTime' => $shnunc]);
		exit();
	}
	$byabaharkarta = (int)$auth_res;

	if (isset($shonupost['amount']) && isset($shonupost['betCount']) && isset($shonupost['gameType']) && isset($shonupost['issuenumber']) && 
		isset($shonupost['selectType']) && isset($shonupost['typeId'])) {
		$amount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['amount']));
		$betCount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['betCount']));
		$gameType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['gameType']));
		$issuenumber = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['issuenumber']));
		$selectType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['selectType']));
		$typeId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['typeId']));

		if($typeId == 1){
			$lordjesus = 'bajikattuttate';
			$sonofgod = 'gelluonduhogu';
		}
		else if($typeId == 2){
			$lordjesus = 'bajikattuttate_drei';
			$sonofgod = 'gelluonduhogu_drei';
		}
		else if($typeId == 3){
			$lordjesus = 'bajikattuttate_funf';
			$sonofgod = 'gelluonduhogu_funf';
		}
		else if($typeId == 4){
			$lordjesus = 'bajikattuttate_zehn';
			$sonofgod = 'gelluonduhogu_zehn';
		} else {
			$lordjesus = 'bajikattuttate';
			$sonofgod = 'gelluonduhogu';
		}

		if($betCount >= 1){
			if($amount >= 1){
				$samasye = "SELECT atadaaidi
				  FROM ".$sonofgod."
				  ORDER BY kramasankhye DESC LIMIT 1";
				$samasyephalitansa=$conn->query($samasye);
				$samasyesreni = mysqli_fetch_array($samasyephalitansa);
				if($samasyesreni['atadaaidi'] == $issuenumber){
					$totalamount = $amount * $betCount;								
					$balquery = "SELECT motta
					  FROM shonu_kaichila
					  WHERE balakedara = ".$byabaharkarta;
					$balresult = $conn->query($balquery);
					$balarr = mysqli_fetch_array($balresult);									
					$shonubalance = floatval($balarr['motta'] ?? 0);								
					if($shonubalance >= $totalamount){
						$sesabida = sprintf("%.2f", $totalamount * 0.98);
						$tathya = mysqli_query($conn,"INSERT INTO `".$lordjesus."` (`byabaharkarta`,`kalaparichaya`,`prakar`,`ojana`,`menge`,`wettanzahl`,`ketebida`,`phalaphala`,`sesabida`,`tiarikala`) VALUES ('".$byabaharkarta."','".$issuenumber."','".$gameType."','".$selectType."','".$amount."','".$betCount."','".$totalamount."','perte','".$sesabida."','".$shnunc."')");
						$mottanutan = $shonubalance - $totalamount;
						$nabikarana = "UPDATE shonu_kaichila set motta='$mottanutan' where balakedara='$byabaharkarta'";
						$conn->query($nabikarana);
						include "commission.php";
						include "vip.php";
						$res['data'] = null;
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);	
					}
					else{
						$res['code'] = 1;
						$res['msg'] = 'Balance is not enough';
						$res['msgCode'] = 142;
						http_response_code(200);
						echo json_encode($res);	
					}
				}
				else{
					$res['code'] = 1;
					$res['msg'] = 'The current period is settled';
					$res['msgCode'] = 404;
					http_response_code(200);
					echo json_encode($res);
				}																																				
			}
			else{
				$res['code'] = 7;
				$res['msg'] = "Invalid value for parameter 'Amount'";
				unset($res['msgCode']);
				unset($res['serviceNowTime']);
				http_response_code(200);
				echo json_encode($res);
			}
		}
		else{
			$res['code'] = 7;
			$res['msg'] = "Invalid value for parameter 'BetCount'";
			unset($res['msgCode']);
			unset($res['serviceNowTime']);
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