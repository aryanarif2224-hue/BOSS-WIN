<?php 
    // GetUserInfo.php - COMPLETE WORKING VERSION
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
    
    // Allow POST only (not GET)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        $author = get_bearer_token();
        if (empty($author) && !empty($shonupost['signature']) && strpos($shonupost['signature'], '.') !== false) {
            $author = $shonupost['signature'];
        }
        $data_auth = !empty($author) ? json_decode(is_jwt_valid($author), true) : null;
        $is_auth_ok = ($data_auth && ($data_auth['status'] ?? '') === 'Success');
        
        $signature = isset($shonupost['signature']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature'])) : '';
        $language = isset($shonupost['language']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language'])) : '0';
        $random = isset($shonupost['random']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random'])) : '';
        $shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));
        $sig_ok = ($shonusign === $signature || verify_signature($shonupost, $signature) || $is_auth_ok);

        if ($sig_ok) {
            if ($is_auth_ok) {
                $userId = intval($data_auth['payload']['id'] ?? 0);
                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '" . $conn->real_escape_string($author) . "' OR id = $userId";
                $sesresult = $conn->query($sesquery);
                $sesnum = $sesresult ? mysqli_num_rows($sesresult) : 0;
                
                if ($sesnum >= 1) {
                                
                                // =============================================
                                // FETCH ACTUAL USER DATA FROM DATABASE
                                // =============================================
                                $userQuery = "SELECT id, mobile, email, codechorkamukala, abidpic AS avatar, createdate, shonullgnt, status FROM shonu_subjects WHERE id = '$userId'";
                                $userResult = $conn->query($userQuery);
                                $userData = mysqli_fetch_assoc($userResult);
                                
                                if($userData) {
                                    // Basic user info
                                    $data['userId'] = (int)$userId;
                                    
                                    // FIXED: Get actual photo from database (not hardcoded)
                                    $data['userPhoto'] = isset($userData['avatar']) && !empty($userData['avatar']) ? $userData['avatar'] : '1';
                                    
                                    $data['userName'] = '91' . $data_auth['payload']['mobile'];
                                    
                                    // FIXED: Get actual nickname from database
                                    $data['nickName'] = isset($userData['codechorkamukala']) && !empty($userData['codechorkamukala']) 
                                                        ? $userData['codechorkamukala'] 
                                                        : (isset($data_auth['payload']['codechorkamukala']) ? $data_auth['payload']['codechorkamukala'] : 'User');
                                    
                                    // Get user balance
                                    $balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = " . $userId . " ORDER BY kramasankhye DESC LIMIT 1";
                                    $balresult = $conn->query($balquery);
                                    $balarr = mysqli_fetch_array($balresult);
                                    $data['amount'] = isset($balarr['motta']) ? (string)round(floatval($balarr['motta']), 2) : "0";
                                    
                                    // Get USDT rate
                                    $query = "SELECT rate FROM tbl_pg WHERE value = 'usdt' LIMIT 1";
                                    $result = $conn->query($query);
                                    $row = $result->fetch_assoc();
                                    $data['uRate'] = isset($row['rate']) ? (float)$row['rate'] : 0;
                                    
                                    // Get creation date
                                    $data['createdate'] = isset($userData['createdate']) ? $userData['createdate'] : '';
                                    
                                    // Get unread notifications count
                                    $countQuery = "SELECT COUNT(*) AS total_notifications FROM notification WHERE user_id = " . $userId . " AND state = 0";
                                    $countResult = $conn->query($countQuery);
                                    $unRead = 0;
                                    if ($countResult) {
                                        $row = $countResult->fetch_assoc();
                                        $unRead = (int)$row['total_notifications'];
                                    }
                                    
                                    // Generate sign for verification
                                    $knbdstr = '{"userId":'.$data['userId'].',"userPhoto":"'.$data['userPhoto'].'","userName":'.$data['userName'].',"nickName":"'.$data['nickName'].'","createdate":"'.$data['createdate'].'"}';
                                    $data['sign'] = strtoupper(hash('sha256', $knbdstr));
                                    
                                    // Additional fields
                                    $data['amountofCode'] = 0.00;
                                    $data['isWithdraw'] = null;
                                    $data['message'] = null;
                                    $data['withdrawCount'] = 0;
                                    $data['addTime'] = '2024-04-17 14:10:50';
                                    $data['userLoginDate'] = isset($userData['shonullgnt']) ? $userData['shonullgnt'] : $shnunc;
                                    $data['startTime'] = null;
                                    $data['endTime'] = null;
                                    $data['fee'] = 0.0;
                                    $data['unRead'] = $unRead;
                                    $data['facebookAppID'] = null;
                                    $data['googleAppID'] = null;
                                    $data['twitterAppID'] = null;
                                    $data['keyCode'] = null;
                                    $data['trxRate'] = 10.0;
                                    $data['uGold'] = 0.00;
                                    $data['googleVerify'] = 0;
                                    $data['isvalidator'] = 0;
                                    $data['isRePwd'] = '1';
                                    $data['integral'] = 0;
                                    $data['isOpenPointMall'] = '0';
                                    $data['isOpenAmountOfCode'] = '1';
                                    $data['isOpenOfficialRechargeInputDialog'] = '0';
                                    $data['isAllowUserAddUSDT'] = '1';
                                    $data['isShowWalletTotalCT'] = '0';
                                    $data['isShowRechargeBankList'] = '0';
                                    $data['isPopupCommissionSwitch'] = '1';
                                    
                                    // Group data show auth
                                    $data['groupDataShowAuth'][0] = ['id' => 11, 'isShow' => true];
                                    $data['groupDataShowAuth'][1] = ['id' => 12, 'isShow' => true];
                                    $data['groupDataShowAuth'][2] = ['id' => 15, 'isShow' => true];
                                    $data['groupDataShowAuth'][3] = ['id' => 16, 'isShow' => true];
                                    $data['groupDataShowAuth'][4] = ['id' => 17, 'isShow' => true];
                                    $data['groupDataShowAuth'][5] = ['id' => 18, 'isShow' => true];
                                    $data['groupDataShowAuth'][6] = ['id' => 19, 'isShow' => true];
                                    $data['groupDataShowAuth'][7] = ['id' => 20, 'isShow' => true];
                                    
                                    // Verify methods
                                    $data['verifyMethods']['mobile'] = '91' . $data_auth['payload']['mobile'];
                                    $data['verifyMethods']['email'] = isset($userData['email']) ? $userData['email'] : '';
                                    $data['verifyMethods']['google'] = '0';
                                    
                                    $data['regType'] = 1;
                                    
                                    // User group auth
                                    $data['userGroupAuth'] = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                    
                                    $data['bindReward'] = 0.0;
                                    $data['isGoogle'] = '0';
                                    $data['isOpenChampion'] = '0';
                                    $data['isAllowWithdraw'] = 1;
                                    
                                    $res['data'] = $data;
                                    $res['code'] = 0;
                                    $res['msg'] = 'Succeed';
                                    $res['msgCode'] = 0;
                                    http_response_code(200);
                                } else {
                                    $res['code'] = 4;
                                    $res['msg'] = 'User not found';
                                    $res['msgCode'] = 2;
                                    http_response_code(401);
                                }
                            } else {
                                $res['code'] = 4;
                                $res['msg'] = 'No operation permission';
                                $res['msgCode'] = 2;
                                http_response_code(401);
                            }
                        } else {
                            $res['code'] = 4;
                            $res['msg'] = 'Invalid token';
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
                    $res['code'] = 11;
                    $res['msg'] = 'Method not allowed - Use POST';
                    $res['msgCode'] = 12;
                    http_response_code(405);
                }
                
                echo json_encode($res);
?>