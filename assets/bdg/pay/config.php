<?php
return [
    'db_host' => 'localhost',       
    'db_user' => 'shreewin06_user',  
    'db_pass' => 'Shreewin06_29eb3d8f8038',  
    'db_name' => 'shreewin06_ab888',

    // ==========================================
    // CONFIGURATION FOR ZYRAPAY (INR)
    // ==========================================
    'NOVAX_MERCHANT_ID' => 'MID_530ED7E6',
    'NOVAX_API_SECRET'  => '1ffeae493ce7abf43fe8526baf6ff2d0f52a6e45720822e7e248e6547f673c91',
    
    'NOVAX_BASE_URL'    => 'https://zyrapay.top',
    'NOVAX_CREATE_PATH' => '/api/v1/payment/create',
    
    // Aapke game ka domain
    'NOTIFY_URL'        => 'https://shreewin06.site/pay/zyrapay_webhook.php',
    'RETURN_URL'        => 'https://shreewin06.site/#/main',
];
