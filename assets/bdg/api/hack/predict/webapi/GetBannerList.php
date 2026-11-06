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


// GetBannerList — Raxi captured response
header("Content-Type: application/json; charset=utf-8");
$o=isset($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
header("Access-Control-Allow-Origin: ".$o);
header("Access-Control-Allow-Credentials: true");
echo <<<'JSON'
{"data":[{"url":"#/wallet/Recharge","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260922123853jtcl.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"https://t.me/RAXIGAME","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260504163352unjf.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/activity/DailyTasks","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605051403104uhf.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/activity/ActivityDetail?id=75","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260504163239ltmi.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605041631513kmu.png","categoryType":0,"jumpLinkType":1,"vendorCode":"TB_Chess","gameCode":"121","gameNameEn":"Chicken Road 2","accessAuthType":0},{"url":"#/activity/FirstRecharge","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261002194250sfr3.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/wallet/Recharge","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605041630437q1y.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"https://t.me/ARWalletCLUB","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260504163022oos4.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/activity/MemberPackage","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260505140350y9d7.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/activity/DailySignIn","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260504162738s7ro.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605041716408si6.png","categoryType":0,"jumpLinkType":1,"vendorCode":"SPRIBE","gameCode":"aviator","gameNameEn":"Aviator","accessAuthType":0},{"url":"#/activity/Turntable","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260925145903y8ws.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/main/InvitationBonus","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_2026050713062831pj.jpg","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"https://whatsapp.com/channel/0029VbCm1AjI7BeNqHV23k18","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605051404339dx6.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0},{"url":"#/main/RechargeCoupon","bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202610081529352pqb.png","categoryType":0,"jumpLinkType":0,"vendorCode":"","gameCode":"","gameNameEn":"","accessAuthType":0}],"code":0,"msg":"Succeed","msgCode":0,"traceId":"","serviceNowTime":"2026-10-20 13:07:21"}
JSON;
