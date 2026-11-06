<?php
// ---- LICENSE GATE (domain-locked activation) ----
require_once __DIR__ . '/../../conn.php';

// ---- LICENSE GATE (domain-locked activation) ----
require_once __DIR__ . '/../../conn.php';

// ---- LICENSE GATE (domain-locked activation) ----
require_once __DIR__ . '/../../conn.php';

// ---- LICENSE GATE (domain-locked activation) ----
require_once __DIR__ . '/../../conn.php';

// ---- LICENSE GATE (domain-locked activation) ----
require_once __DIR__ . '/../../conn.php';


// GetHomeSettings — Raxi captured response
header("Content-Type: application/json; charset=utf-8");
$o=isset($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
header("Access-Control-Allow-Origin: ".$o);
header("Access-Control-Allow-Credentials: true");
echo <<<'JSON'
{"data":{"version":"2026-04-20 001","arUpiInputUtrSwitch":true,"arbApiUrl":["https://apiweb.payapiar.com"],"isShowAppDownloadUp":true,"isShowAppDownloadDown":true,"isShowRewardCenter":true,"isShowLotteryDragon":true,"isSplitLocalEWallet":true,"jackportMaxReswadAmount":500.00,"projectName":"RAXI.WIN","projectLogo":"https://ossimg.raxiwsx.com/RAXI.WIN/other/h5setting_20260505202958ehbj.png","languages":"en|hd","webIco":"https://ossimg.raxiwsx.com/RAXI.WIN/other/h5setting_202604271157266bc2.png","headLogo":"https://ossimg.raxiwsx.com/RAXI.WIN/other/h5setting_20260505203004dpfp.png","dollarSign":"₹","upperOrLower":"0","defaultCurrentLanguage":"en","registerMobile":"1","registerEmail":"0","areaPhoneLenList":[{"area":"+91","len":"10"}],"registerSms":"0","isOpenLoginChangeLanguage":"1","rewardValidityTime":30,"electronicWinRateExternalLink":"","electronicWinRateImgUrl":"","isShowElectronicWinRateExternalLink":false,"isShowAppHandCodeWashingSwitch":true,"isShowHotGameWinOdds":true,"ossUrl":"https://ossimg.raxiwsx.com","bigTurntableLink":"","bigTurntableImgUrl":"","telegramExternalLink":"https://t.me/RAXIWIN_BOT","telegramImgUrl":"","isOpenActivityAward":true,"isOpenTurntable":true,"isPartnerReward":false,"isSelfCustomerService":true,"webSiteUrl":"https://www.raxi16.com","isOpenFacebookEvent":false,"firstDepositRewardCodeAmount":"1","isOpenRegisterPhoneFirstZeroSwitch":false,"eventRegionConfigList":null,"isOpenAdjustEvent":false,"firebaseConfig":{"apiKey":"AIzaSyBPskVaI5bK2GwxSO215XhqVOp4rfcSP4s","authDomain":"raxiwin-18e5f.firebaseapp.com","projectId":"raxiwin-18e5f","storageBucket":"raxiwin-18e5f.firebasestorage.app","messagingSenderId":"697346839100","appId":"1:697346839100:web:bf5ac04a194d6eead52939","measurementId":"G-QQYHCJPE0M","keyPair":""},"isOpenArLottery":true,"isSwitchSaasBalance":true,"isV3Mode":false,"isOpenRechargeAutoWeight":false,"isOpenInvitedWheel":false,"invitedWheelTotalPrizeAmount":100.0,"invitedWheelImgUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/tab/wheel.png","isOpenDownAppRewardSwitch":true,"isShowDownAppBonusAmountSwitch":true,"downAppBonusAmount":12.7,"downAppRechargeAmount":0.0,"needKycValidIsOpen":false,"needFastKycValidIsOpen":false,"lotteryDragonIcon":null,"bonusCenterImgUrl":"","isShowBonusCenterExternalLink":true,"isOpenBrowserConsoleDebug":true,"homeBigTurntableSwitch":true,"homeBigTurntableLink":"","homeBigTurntableImgUrl":"","jgConfig":{"webConfig":null,"appConfig":null},"lastestAppVersionInfo":null,"arUpiChannelIds":[26000,26001,26010,26011,26020,26021],"arWalletRechargeChannelIds":[10201],"arWalletWithdrawChannelIds":[10090],"fastUpiWithdrawChannelIds":[26003,26004]},"code":0,"msg":"Succeed","msgCode":0,"traceId":"","serviceNowTime":"2026-10-20 13:07:11"}
JSON;
