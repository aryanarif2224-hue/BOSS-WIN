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
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if(empty($authHeader) && function_exists('getallheaders')) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
            $bearer = explode(" ", $authHeader);
            $author = $bearer[1] ?? '';				
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            if($data_auth && ($data_auth['status'] ?? '') === 'Success') {
                $uid = intval($data_auth['payload']['id']);
                $balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = $uid ORDER BY kramasankhye DESC LIMIT 1";
                $balresult = $conn->query($balquery);
                $balarr = mysqli_fetch_array($balresult);
                $bal = isset($balarr['motta']) ? round(floatval($balarr['motta']), 2) : 0.0;
                
                $data = [
                    'amount' => $bal,
                    'mainMoney' => $bal,
                    'totalAmount' => $bal,
                    'userAmount' => $bal,
                    'platAmount' => $bal,
                    'thidGameBalanceList' => [
                        ['vendorCode' => 'Lottery', 'vendorName' => 'Lottery', 'balance' => $bal],
                        ['vendorCode' => 'TB_Chess', 'vendorName' => 'TB_Chess', 'balance' => 0],
                        ['vendorCode' => 'Wickets9', 'vendorName' => 'Wickets9', 'balance' => 0],
                        ['vendorCode' => 'CQ9', 'vendorName' => 'CQ9', 'balance' => 0],
                        ['vendorCode' => 'MG', 'vendorName' => 'MG', 'balance' => 0],
                        ['vendorCode' => 'JDB', 'vendorName' => 'JDB', 'balance' => 0],
                        ['vendorCode' => 'DG', 'vendorName' => 'DG', 'balance' => 0],
                    ],
                    'totalWithdraw' => 0,
                    'totalRecharge' => 0,
                ];
                
                $res['data'] = $data;
                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
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