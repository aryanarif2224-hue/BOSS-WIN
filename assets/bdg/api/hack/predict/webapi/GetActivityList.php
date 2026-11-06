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


// GetActivityList — Raxi captured response
header("Content-Type: application/json; charset=utf-8");
$o=isset($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
header("Access-Control-Allow-Origin: ".$o);
header("Access-Control-Allow-Credentials: true");
echo <<<'JSON'
{"data":{"list":[{"bannerTitle":"🎉 GRAND LAUNCH OF RAXI.WIN!🎉 ","bannerID":86,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261003114840cmuf.png","jumpType":2,"contents":"https://777jas.com/","accessAuthType":0},{"bannerTitle":"FIRST DEPOSIT GET FREE ₹ 488","bannerID":57,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261002225809tw53.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"3TH-13TH-23TH RECHARGE BONUS 5 %","bannerID":85,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261002225620irb5.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"LUCKY SPIN TO WIN IPHONE 18","bannerID":77,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261002225711p2jd.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"DAILY & WEEKLY MISSIONS ","bannerID":74,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260506140352chfe.png","jumpType":4,"contents":"#/activity/DailyTasks","accessAuthType":0},{"bannerTitle":"DAILY SALARY SYSTEM","bannerID":75,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260507134852xghx.png","jumpType":3,"contents":"","accessAuthType":1},{"bannerTitle":"CHECK IN DAILY  BONUS UP TO ₹ 7,000","bannerID":60,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260506140710sl16.png","jumpType":4,"contents":"#/activity/DailySignIn","accessAuthType":0},{"bannerTitle":"RANDOM  BONUS  MYSTERIOUS GIFT","bannerID":58,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20261008150654o936.png","jumpType":2,"contents":"#/main/RechargeCoupon","accessAuthType":0},{"bannerTitle":"GET 20% BACK","bannerID":66,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605061406145ilx.png","jumpType":4,"contents":"#/activity/MemberPackage","accessAuthType":0},{"bannerTitle":"PILOT CHALLENGE REWARDS ₹20,000","bannerID":69,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260507192104lfxs.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"INVITE FRIENDS BONUS UP TO ₹ 308,888","bannerID":64,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260508151039f1u8.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"🐓CHICKEN ROAD 2 BET&WIN BONUS ₹ 2500","bannerID":65,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260507134044p8fu.png","jumpType":3,"contents":"","accessAuthType":0},{"bannerTitle":"AR Wallet Buy & Sell ARB  5% Bonus","bannerID":55,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_202605061408045oaq.png","jumpType":4,"contents":"https://arwwallet.com/","accessAuthType":1},{"bannerTitle":"Benefits of Using ARWALLET","bannerID":56,"bannerUrl":"https://ossimg.raxiwsx.com/RAXI.WIN/banner/Banner_20260506140752t4yp.png","jumpType":2,"contents":"https://t.me/ARWalletCLUB","accessAuthType":0}],"pageNo":1,"totalPage":1,"totalCount":14},"code":0,"msg":"Succeed","msgCode":0,"traceId":"","serviceNowTime":"2026-10-20 13:07:43"}
JSON;
