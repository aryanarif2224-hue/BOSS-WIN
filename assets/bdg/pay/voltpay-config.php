<?php
// voltpay-config.php - VoltPay payment gateway config for shreewin06.site
return [
    'VOLT_BASE_URL'    => 'https://api.voltpay.site',
    'VOLT_CREATE_PATH' => '/api/create-order',
    'VOLT_MERCHANT_ID' => 'BDGWIN',
    'VOLT_API_KEY'     => 'vp_live_bdgwin_001',   // Bearer token for create-order
    'VOLT_API_SECRET'  => 'vp_sec_bdgwin_001',    // for signature (if used)
    'MY_HOST'          => 'https://shreewin06.site',
    'RETURN_URL'       => 'https://shreewin06.site/#/wallet/RechargeHistory',
    'NOTIFY_URL'       => 'https://shreewin06.site/pay/voltpay-callback.php',
    'USDT_RATE'        => 87.50,                   // 1 USDT = X INR credited to wallet
    'LOG_FILE'         => '/bdg/pay/voltpay_integration.log',
];
