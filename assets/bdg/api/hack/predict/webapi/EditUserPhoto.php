<?php 
    // EditUserPhoto.php - ABSOLUTE FINAL WORKING
    require_once __DIR__ . '/../../conn.php';
    require_once __DIR__ . '/../../functions2.php';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    
    date_default_timezone_set("Asia/Kolkata");
    
    $response = [];
    $shonubody = file_get_contents("php://input");
    $shonupost = json_decode($shonubody, true);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // Debug: Log received data
        error_log("EditUserPhoto received: " . print_r($shonupost, true));
        
        if (isset($shonupost['userPhoto']) && isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature'])) {
            
            $language = mysqli_real_escape_string($conn, $shonupost['language']);
            $random = mysqli_real_escape_string($conn, $shonupost['random']);
            $signature = mysqli_real_escape_string($conn, $shonupost['signature']);
            $userPhoto = mysqli_real_escape_string($conn, $shonupost['userPhoto']);
            
            // Generate signature (must match frontend)
            $shonustr = '{"language":' . $language . ',"random":"' . $random . '","userPhoto":"' . $userPhoto . '"}';
            $calculatedSign = strtoupper(md5($shonustr));
            
            error_log("Expected sign: " . $calculatedSign);
            error_log("Received sign: " . $signature);
            
            if($calculatedSign == $signature || verify_signature($shonupost, $signature)) {
                
                // Get Authorization token
                $headers = getallheaders();
                $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
                
                if(!empty($authHeader)) {
                    $bearer = explode(" ", $authHeader);
                    $token = isset($bearer[1]) ? $bearer[1] : '';
                    
                    if(!empty($token)) {
                        $is_jwt_valid = is_jwt_valid($token);
                        $data_auth = json_decode($is_jwt_valid, true);
                        
                        if(isset($data_auth['status']) && $data_auth['status'] === 'Success') {
                            $userId = $data_auth['payload']['id'];
                            
                            // Update avatar in database
                            $updateQuery = "UPDATE shonu_subjects SET avatar = '$userPhoto' WHERE id = '$userId'";
                            
                            if ($conn->query($updateQuery) === TRUE) {
                                $response = [
                                    'code' => 0,
                                    'msg' => 'Photo updated successfully',
                                    'msgCode' => 0,
                                    'userPhoto' => $userPhoto
                                ];
                                http_response_code(200);
                            } else {
                                $response = [
                                    'code' => 2,
                                    'msg' => 'Database Error: ' . $conn->error,
                                    'msgCode' => 2
                                ];
                            }
                        } else {
                            $response = ['code' => 4, 'msg' => 'Invalid or expired token', 'msgCode' => 2];
                            http_response_code(401);
                        }
                    } else {
                        $response = ['code' => 4, 'msg' => 'Token format invalid', 'msgCode' => 2];
                        http_response_code(401);
                    }
                } else {
                    $response = ['code' => 4, 'msg' => 'Authorization token missing', 'msgCode' => 2];
                    http_response_code(401);
                }
            } else {
                $response = ['code' => 5, 'msg' => 'Invalid signature', 'msgCode' => 3];
            }
        } else {
            $response = ['code' => 7, 'msg' => 'Missing required parameters: userPhoto, language, random, signature', 'msgCode' => 6];
        }
    } else {
        $response = ['code' => 11, 'msg' => 'Method not allowed - Use POST', 'msgCode' => 12];
        http_response_code(405);
    }
    
    echo json_encode($response);
?>