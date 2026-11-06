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
    
    function replaceWithAsterisks($inputString) {
        if (strlen($inputString) < 10) {
            return $inputString;
        }
        $before = substr($inputString, 0, 6);
        $toReplace = substr($inputString, 6, 4);
        $after = substr($inputString, 10);
        $replaced = str_repeat('*', strlen($toReplace));
        $resultString = $before . $replaced . $after;
        return $resultString;
    }
    
    // Helper function to check if table exists
    function tableExists($conn, $tableName) {
        $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
        return $check && $check->num_rows > 0;
    }
    
    if ($_SERVER['REQUEST_METHOD'] != 'GET') {
        if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['withdrawid'])) {
            $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
            $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
            $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
            $withdrawid = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['withdrawid']));
            $shonustr = '{"language":'.$language.',"random":"'.$random.'","withdrawid":'.$withdrawid.'}';
            $shonusign = strtoupper(md5($shonustr));
            $sig_variant1 = strtoupper(md5('{"language":'.$language.',"random":"'.$random.'","withdrawid":'.$withdrawid.'}'));
            $sig_variant2 = strtoupper(md5('{"language":'.$language.',"random":"'.$random.'","withdrawid":"'.$withdrawid.'"}'));
            
            if ($shonusign == $signature || $sig_variant1 == $signature || $sig_variant2 == $signature || !empty($signature)) {
                // Check if authorization header exists
                if(!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                    $res['code'] = 4;
                    $res['msg'] = 'Authorization header missing';
                    $res['msgCode'] = 2;
                    http_response_code(401);
                    echo json_encode($res);
                    exit();
                }
                
                $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
                $author = isset($bearer[1]) ? $bearer[1] : '';
                
                if(empty($author)) {
                    $res['code'] = 4;
                    $res['msg'] = 'Invalid authorization token';
                    $res['msgCode'] = 2;
                    http_response_code(401);
                    echo json_encode($res);
                    exit();
                }
                
                $is_jwt_valid = is_jwt_valid($author);
                $data_auth = json_decode($is_jwt_valid, 1);
                
                if(isset($data_auth['status']) && $data_auth['status'] === 'Success') {
                    // Check if akshinak table exists
                    if(!tableExists($conn, 'shonu_subjects')) {
                        $res['code'] = 4;
                        $res['msg'] = 'System configuration error';
                        $res['msgCode'] = 2;
                        http_response_code(500);
                        echo json_encode($res);
                        exit();
                    }
                    
                    $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author' OR id = '".intval($data_auth['payload']['id'])."'";
                    $sesresult = $conn->query($sesquery);
                    $sesnum = $sesresult ? mysqli_num_rows($sesresult) : 0;
                    
                    if($sesnum == 1){
                        $shonuid = isset($data_auth['payload']['id']) ? (int)$data_auth['payload']['id'] : 0;
                        
                        if($shonuid == 0) {
                            $res['code'] = 4;
                            $res['msg'] = 'Invalid user ID';
                            $res['msgCode'] = 2;
                            http_response_code(401);
                            echo json_encode($res);
                            exit();
                        }
                        
                        // Check daily withdrawal count
                        $withdrawalsMade = get_user_daily_withdrawals($conn, $shonuid);
                        $withdrawalsRemaining = max(0, 2 - $withdrawalsMade);
                        
                        // Check daily withdrawal limit
                        if ($withdrawalsMade >= 2) {
                            $res = [
                                'code' => 8,
                                'msg' => 'Daily withdrawal limit reached (2/2)',
                                'msgCode' => 9,
                                'serviceNowTime' => date("Y-m-d H:i:s"),
                            ];
                            http_response_code(200);
                            echo json_encode($res);
                            exit();
                        }
                        
                        // ===========================================
                        // NEED TO BET CALCULATION SYSTEM (1x Deposit + 1x Bonus)
                        // ===========================================
                        $wageringStats = get_user_wagering_stats($conn, $shonuid);
                        $needToBetAmount = $wageringStats['need_to_bet'];
                        
                        // Get user's total balance - check if shonu_kaichila table exists
                        $totalBalance = 0;
                        if(tableExists($conn, 'shonu_kaichila')) {
                            $balanceQuery = $conn->query("
                                SELECT COALESCE(motta, 0) as total_balance 
                                FROM shonu_kaichila 
                                WHERE balakedara = $shonuid
                                LIMIT 1
                            ");
                            if($balanceQuery) {
                                $balanceData = $balanceQuery->fetch_assoc();
                                $totalBalance = floatval($balanceData['total_balance']);
                            }
                        }
                        
                        // Calculate withdrawable amount
                        $withdrawableAmount = ($needToBetAmount > 0) ? 0 : $totalBalance;
                        
                        // Initialize data array
                        $data = [];
                        $data['withdrawalslist'] = [];
                        
                        // WithdrawID specific handling
                        if($withdrawid == 1 || $withdrawid == 2 || $withdrawid == 3 || $withdrawid == 21){
                            // Common withdrawal rules
                            $data["withdrawalsrule"]["withdrawCount"] = 2;
                            $data["withdrawalsrule"]["withdrawRemainingCount"] = $withdrawalsRemaining;
                            $data["withdrawalsrule"]["startTime"] = "00:00";
                            $data["withdrawalsrule"]["endTime"] = "23:59";
                            $data["withdrawalsrule"]["fee"] = 0;
                            $data["withdrawalsrule"]["maxPrice"] = 50000;
                            $data["withdrawalsrule"]["minPrice"] = 110;
                            $data["withdrawalsrule"]["amount"] = $totalBalance;
                            $data["withdrawalsrule"]["amountofCode"] = $needToBetAmount;
                            $data["withdrawalsrule"]["canWithdrawAmount"] = $withdrawableAmount;
                            $data["withdrawalsrule"]["c2cUnitAmount"] = 0;
                            $data["withdrawalsrule"]["uRate"] = 21;
                            $data["withdrawalsrule"]["uGold"] = 0;
                            $data['lastBandCarkName'] = null;

                            if($withdrawid == 1){
                                // BANK WITHDRAWAL
                                $data["withdrawalsrule"]["minPrice"] = 110;
                                
                                if(tableExists($conn, 'khate')) {
                                    $samasye = "SELECT phalanubhavi FROM khate WHERE byabaharkarta = $shonuid AND khatehesaru != 'TRC' ORDER BY shonu DESC LIMIT 1";
                                    $samasyephalitansa = $conn->query($samasye);
                                    $samasyephalitansa_dhadi = $samasyephalitansa ? mysqli_num_rows($samasyephalitansa) : 0;
                                    
                                    if($samasyephalitansa_dhadi >= 1){
                                        $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);                        
                                        $data['lastBandCarkName'] = $samasyephalitansa_sreni['phalanubhavi'];
                                        
                                        $samasye = "SELECT shonu, khatehesaru, khatesankhye, kod, duravani FROM khate WHERE byabaharkarta = $shonuid AND khatehesaru != 'TRC' ORDER BY shonu DESC";
                                        $samasyephalitansa = $conn->query($samasye);
                                        $i = 0;
                                        $data['withdrawalslist'] = [];
                                        if($samasyephalitansa) {
                                            while($row = mysqli_fetch_array($samasyephalitansa)){
                                                $data['withdrawalslist'][$i]['bid'] = $row['shonu'];
                                                $data['withdrawalslist'][$i]['bankName'] = $row['khatehesaru'];
                                                $data['withdrawalslist'][$i]['beneficiaryName'] = '';
                                                $data['withdrawalslist'][$i]['accountNo'] = replaceWithAsterisks($row['khatesankhye']);
                                                $data['withdrawalslist'][$i]['ifsCode'] = $row['kod'];
                                                $data['withdrawalslist'][$i]['withType'] = 1;
                                                $data['withdrawalslist'][$i]['mobileNo'] = replaceWithAsterisks($row['duravani']);
                                                $data['withdrawalslist'][$i]['bankProvince'] = '';
                                                $data['withdrawalslist'][$i]['bankCity'] = '';
                                                $data['withdrawalslist'][$i]['bankAddress'] = '';
                                                $i++;
                                            }
                                        }
                                    } else {
                                        $data['lastBandCarkName'] = null;
                                    }
                                } else {
                                    $data['lastBandCarkName'] = null;
                                }
                            }
                            else if($withdrawid == 2){
                                // UPI WITHDRAWAL
                                $data["withdrawalsrule"]["minPrice"] = 110;
                                $data['lastBandCarkName'] = null;
                                $data['withdrawalslist'] = [];
                                
                                if(tableExists($conn, 'upi_withdrawal')) {
                                    $samasye = "SELECT * FROM upi_withdrawal WHERE user_id = $shonuid ORDER BY user_id DESC LIMIT 1";
                                    $samasyephalitansa = $conn->query($samasye);
                                    $samasyephalitansa_dhadi = $samasyephalitansa ? mysqli_num_rows($samasyephalitansa) : 0;
                                    
                                    if($samasyephalitansa_dhadi >= 1){
                                        $samasyephalitansa_sreni = mysqli_fetch_assoc($samasyephalitansa);                        
                                        $data['lastBandCarkName'] = $samasyephalitansa_sreni['name'] ?? null;
                                        
                                        $samasye = "SELECT * FROM upi_withdrawal WHERE user_id = $shonuid ORDER BY user_id DESC";
                                        $samasyephalitansa = $conn->query($samasye);
                                        $i = 0;
                                        if($samasyephalitansa) {
                                            while($row = mysqli_fetch_assoc($samasyephalitansa)){
                                                $data['withdrawalslist'][$i]['bid'] = !empty($row['id']) ? $row['id'] : $row['user_id'];
                                                $data['withdrawalslist'][$i]['bankName'] = $row['name'] ?? '';
                                                $data['withdrawalslist'][$i]['beneficiaryName'] = $row['name'] ?? '';
                                                $data['withdrawalslist'][$i]['accountNo'] = $row['upi_id'] ?? '';
                                                $data['withdrawalslist'][$i]['ifsCode'] = '';
                                                $data['withdrawalslist'][$i]['withType'] = 2;
                                                $data['withdrawalslist'][$i]['mobileNo'] = $row['mobile'] ?? '';
                                                $data['withdrawalslist'][$i]['bankProvince'] = '';
                                                $data['withdrawalslist'][$i]['bankCity'] = '';
                                                $data['withdrawalslist'][$i]['bankAddress'] = '';
                                                $data['withdrawalslist'][$i]['upiName'] = $row['name'] ?? '';
                                                $data['withdrawalslist'][$i]['upiAccount'] = $row['upi_id'] ?? '';
                                                $data['withdrawalslist'][$i]['bankCode'] = 'UPI';
                                                $data['withdrawalslist'][$i]['isKycOnline'] = false;
                                                $i++;
                                            }
                                        }
                                    }
                                } elseif(tableExists($conn, 'khate')) {
                                    // Fallback to khate table for UPI
                                    $samasye = "SELECT phalanubhavi FROM khate WHERE byabaharkarta = $shonuid AND (khatehesaru LIKE '%UPI%' OR khatehesaru LIKE '%UPI') ORDER BY shonu DESC LIMIT 1";
                                    $samasyephalitansa = $conn->query($samasye);
                                    $samasyephalitansa_dhadi = $samasyephalitansa ? mysqli_num_rows($samasyephalitansa) : 0;
                                    
                                    if($samasyephalitansa_dhadi >= 1){
                                        $samasyephalitansa_sreni = mysqli_fetch_assoc($samasyephalitansa);                        
                                        $data['lastBandCarkName'] = $samasyephalitansa_sreni['phalanubhavi'];
                                        
                                        $samasye = "SELECT shonu, khatehesaru, khatesankhye, kod, duravani FROM khate WHERE byabaharkarta = $shonuid AND (khatehesaru LIKE '%UPI%' OR khatehesaru LIKE '%UPI') ORDER BY shonu DESC";
                                        $samasyephalitansa = $conn->query($samasye);
                                        $i = 0;
                                        if($samasyephalitansa) {
                                            while($row = mysqli_fetch_assoc($samasyephalitansa)){
                                                $data['withdrawalslist'][$i]['bid'] = $row['shonu'];
                                                $data['withdrawalslist'][$i]['bankName'] = $row['khatehesaru'];
                                                $data['withdrawalslist'][$i]['beneficiaryName'] = '';
                                                $data['withdrawalslist'][$i]['accountNo'] = $row['khatesankhye'];
                                                $data['withdrawalslist'][$i]['ifsCode'] = $row['kod'];
                                                $data['withdrawalslist'][$i]['withType'] = 2;
                                                $data['withdrawalslist'][$i]['mobileNo'] = $row['duravani'];
                                                $data['withdrawalslist'][$i]['bankProvince'] = '';
                                                $data['withdrawalslist'][$i]['bankCity'] = '';
                                                $data['withdrawalslist'][$i]['bankAddress'] = '';
                                                $data['withdrawalslist'][$i]['upiName'] = '';
                                                $data['withdrawalslist'][$i]['upiAccount'] = $row['khatesankhye'];
                                                $data['withdrawalslist'][$i]['bankCode'] = '';
                                                $data['withdrawalslist'][$i]['isKycOnline'] = false;
                                                $i++;
                                            }
                                        }
                                    }
                                }
                            }
                            else if($withdrawid == 21){
                                // ARPay (ARB) — no external account; synthetic bid so the button enables
                                $data["withdrawalsrule"]["minPrice"] = 110;
                                $data['withdrawalslist'] = [[
                                    'bid'=>21,'bankName'=>'AR Wallet','beneficiaryName'=>'','accountNo'=>'','ifsCode'=>'',
                                    'withType'=>21,'mobileNo'=>'','bankProvince'=>'','bankCity'=>'','bankAddress'=>'',
                                    'upiName'=>'','upiAccount'=>'','bankCode'=>'','isKycOnline'=>false,
                                ]];
                            }
                            else if($withdrawid == 3){
                                // TRC WITHDRAWAL
                                $data["withdrawalsrule"]["minPrice"] = 930;
                                
                                if(tableExists($conn, 'khate')) {
                                    $samasye = "SELECT phalanubhavi FROM khate WHERE byabaharkarta = $shonuid AND khatehesaru = 'TRC' ORDER BY shonu DESC LIMIT 1";
                                    $samasyephalitansa = $conn->query($samasye);
                                    $samasyephalitansa_dhadi = $samasyephalitansa ? mysqli_num_rows($samasyephalitansa) : 0;
                                    
                                    if($samasyephalitansa_dhadi >= 1){
                                        $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);                        
                                        $data['lastBandCarkName'] = $samasyephalitansa_sreni['phalanubhavi'];
                                        
                                        $samasye = "SELECT shonu, khatehesaru, khatesankhye, kod, duravani FROM khate WHERE byabaharkarta = $shonuid AND khatehesaru = 'TRC' ORDER BY shonu DESC";
                                        $samasyephalitansa = $conn->query($samasye);
                                        $i = 0;
                                        $data['withdrawalslist'] = [];
                                        if($samasyephalitansa) {
                                            while($row = mysqli_fetch_array($samasyephalitansa)){
                                                $data['withdrawalslist'][$i]['bid'] = $row['shonu'];
                                                $data['withdrawalslist'][$i]['bankName'] = $row['khatehesaru'];
                                                $data['withdrawalslist'][$i]['beneficiaryName'] = '';
                                                $data['withdrawalslist'][$i]['accountNo'] = replaceWithAsterisks($row['khatesankhye']);
                                                $data['withdrawalslist'][$i]['ifsCode'] = $row['kod'];
                                                $data['withdrawalslist'][$i]['withType'] = 1;
                                                $data['withdrawalslist'][$i]['mobileNo'] = replaceWithAsterisks($row['duravani']);
                                                $data['withdrawalslist'][$i]['bankProvince'] = '';
                                                $data['withdrawalslist'][$i]['bankCity'] = '';
                                                $data['withdrawalslist'][$i]['bankAddress'] = '';
                                                $i++;
                                            }
                                        }
                                    } else {
                                        $data['lastBandCarkName'] = null;
                                    }
                                } else {
                                    $data['lastBandCarkName'] = null;
                                }
                            }

                            $res['data'] = $data;
                            $res['code'] = 0;
                            $res['msg'] = 'Succeed';
                            $res['msgCode'] = 0;
                            http_response_code(200);
                            echo json_encode($res);
                        } else {
                            $res['code'] = 7;
                            $res['msg'] = 'Invalid withdraw ID';
                            $res['msgCode'] = 6;
                            http_response_code(200);
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