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


// GetGameCategoryList — Raxi captured response
header("Content-Type: application/json; charset=utf-8");
$o=isset($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
header("Access-Control-Allow-Origin: ".$o);
header("Access-Control-Allow-Credentials: true");
echo <<<'JSON'
{"data":[{"id":2,"typeNameCode":9302,"categoryCode":"Popular","categoryName":"热门游戏","state":1,"sort":18,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_2026042515262834bu.png"},{"id":1,"typeNameCode":9301,"categoryCode":"Lottery","categoryName":"彩票","state":1,"sort":17,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152734yj8b.png"},{"id":9,"typeNameCode":9309,"categoryCode":"BigAward","categoryName":"电子大奖","state":1,"sort":16,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_202604261225496q35.png"},{"id":8,"typeNameCode":9308,"categoryCode":"Flash","categoryName":"小游戏","state":1,"sort":9,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152636wdy2.png"},{"id":4,"typeNameCode":9304,"categoryCode":"Slot","categoryName":"电子游戏","state":1,"sort":8,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152748trmx.png"},{"id":5,"typeNameCode":9305,"categoryCode":"Sport","categoryName":"体育游戏","state":1,"sort":7,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152727mmy5.png"},{"id":6,"typeNameCode":9306,"categoryCode":"Video","categoryName":"视讯游戏","state":1,"sort":6,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152718ttr9.png"},{"id":7,"typeNameCode":9307,"categoryCode":"Chess","categoryName":"棋牌游戏","state":1,"sort":5,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_20260425152658a7uf.png"},{"id":3,"typeNameCode":9303,"categoryCode":"Fish","categoryName":"捕鱼游戏","state":1,"sort":2,"categoryImg":"https://ossimg.raxiwsx.com/RAXI.WIN/gamecategory/gamecategory_202604251526438lj8.png"}],"code":0,"msg":"Succeed","msgCode":0,"traceId":"","serviceNowTime":"2026-10-20 13:07:21"}
JSON;
