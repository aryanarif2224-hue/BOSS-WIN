<?php
    // GetAppDownloadConfigList.php - APK Link Provide karega
    require_once __DIR__ . '/../../conn.php';
    require_once __DIR__ . '/../../functions2.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');

    date_default_timezone_set("Asia/Kolkata");

    // ============================================================
    // 🔥 SETTING: Apna APK Link yahan dalo
    // ============================================================
    
    // Agar APK aapki site par hai:
    $apkUrl = "https://91club.nextr.online/app.apk"; 
    
    // ============================================================
    
    $appData = [];

    // Android App ki details
    $appData[] = [
        "id" => 1,
        "type" => 1,            // 1 = Android
        "name" => "55DK Official",
        "version" => "1.0.0",
        "size" => "5MB",        // Optional: Size dikhane ke liye
        "forced_update" => 0,   // 0 = Zabardasti update nahi, 1 = Update zaroori
        "url" => $apkUrl,       // Ye main link hai
        "download_url" => $apkUrl, 
        "image" => ""           // Agar koi icon hai to link dalo
    ];

    $res = [
        'code' => 0,
        'msg' => 'Succeed',
        'msgCode' => 0,
        'serviceNowTime' => date("Y-m-d H:i:s"),
        'data' => $appData // Ab ye khali nahi hoga!
    ];

    echo json_encode($res);
?>