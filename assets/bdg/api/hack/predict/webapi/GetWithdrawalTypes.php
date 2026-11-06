<?php
header('Content-Type: application/json; charset=utf-8');

$data = [
    "withdrawlist" => [
        [
            "withdrawID" => 21,
            "name" => "ARPay",
            "isAdd" => 0,
            "withBeforeImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon_20241111001429te29.png",
            "withAfterImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon2_20241111001429dntp.png",
            "recommandWithAmount" => "",
            "withdrawTip" => "Supports UPI for fast payment, and bonuses for withdrawals",
            "need100" => null
        ],
        [
            "withdrawID" => 1,
            "name" => " BANK CARD",
            "isAdd" => 0,
            "withBeforeImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon_202403161624569ini.png",
            "withAfterImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon2_20240316162456if4s.png",
            "recommandWithAmount" => "",
            "withdrawTip" => null,
            "need100" => null
        ],
        [
            "withdrawID" => 3,
            "name" => "USDT",
            "isAdd" => 0,
            "withBeforeImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon_20240323183235bhef.png",
            "withAfterImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon2_20240323183236ntpr.png",
            "recommandWithAmount" => "",
            "withdrawTip" => null,
            "need100" => null
        ],
        [
            "withdrawID" => 2,
            "name" => "UPI",
            "isAdd" => 0,
            "withBeforeImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon_20250917120621de5y.png",
            "withAfterImgUrl" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/WithBeforeImgIcon2_20250917120621ukfu.png",
            "recommandWithAmount" => "500,1000,2000,3000,5000,10000,30000,50000",
            "withdrawTip" => "1.Bind your UPI in ARB Wallet and earn 1% ARB coins after each successful sale. \r\n2.Enjoy 2% up to 5% BONUS just for BUY ARB Coins from AR Wallet. Start now!\r\n3.The fastest selling speed is achieved when selling a single order amount between 100 and 2000.",
            "need100" => true
        ]
    ],
    "isOpenSafeGuide" => false,
    "safeGuideContent" => ""
];

$response = [
    "data" => $data,
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "traceId" => "",
    "serviceNowTime" => date("Y-m-d H:i:s")
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
