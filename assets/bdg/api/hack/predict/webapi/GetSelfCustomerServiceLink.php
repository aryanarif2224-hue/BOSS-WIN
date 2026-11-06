<?php 
	require_once __DIR__ . '/../../conn.php';
	require_once __DIR__ . '/../../functions2.php';

//Dkh
//Made by dkh
	header('Content-Type: application/json; charset=utf-8');
//Dkh
//Made by dkh
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('vary: Origin');
	
	date_default_timezone_set("Asia/Kolkata");
//Dkh
//Made by dkh
	$shnunc = date("Y-m-d H:i:s");
	$res = [
		'code' => 11,
		'msg' => 'Method not allowed',
		'msgCode' => 12,
//Dkh
//Made by dkh
		'serviceNowTime' => $shnunc,
	];
//Dkh
//Made by dkh
	$shonubody = file_get_contents("php://input");
	$shonupost = json_decode($shonubody, true);
	if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    
		if (isset($shonupost['webSite']) && isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
			$webSite = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['webSite']));
//Dkh
//Made by dkh
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
//Dkh
//Made by dkh
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
//Dkh
//Made by dkh
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
//Dkh
//Made by dkh
			$timestamp = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['timestamp']));
			
			// Create signature string for validation
			$shonustr = '{"language":'.$language.',"random":"'.$random.'","webSite":"'.$webSite.'"}';
			$shonusign = strtoupper(md5($shonustr));
			
			// Extract authorization token from header
			$datablock = '';
			if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
//Dkh
//Made by dkh
				$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
				if (strpos($authHeader, 'Bearer ') === 0) {
					$datablock = substr($authHeader, 7); // Remove 'Bearer ' prefix
				} else {
					$datablock = $authHeader;
//Dkh
//Made by dkh
				}
//Dkh
//Made by dkh
			} else {
				// Generate random datablock if no authorization provided
//Dkh
//Made by dkh
				$datablock = bin2hex(random_bytes(32));
//Dkh
//Made by dkh
			}
			
			if($signature && ($signature === $shonusign || verify_signature($shonupost, $signature))){				
				// Use new workorder URL with tenant ID 7654
				$baseUrl = "https://support.raxi.nextapi.online";
//Dkh
//Made by dkh
				$tenantId = "1101";
//Dkh
//Made by dkh
				
				// Create the data URL with all required parameters
				$dataUrl = $baseUrl . "?tenantid=" . $tenantId . 
						  "&timestamp=" . $timestamp . 
						  "&website=" . urlencode($webSite) . 
						  "&datablock=" . $datablock;
				
				$res['data'] = $dataUrl;
				$res['code'] = 0;
				$res['msg'] = 'Succeed';
				$res['msgCode'] = 0;
				http_response_code(200);
				echo json_encode($res);			
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