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


// GetSiteMessageList — Raxi captured response
header("Content-Type: application/json; charset=utf-8");
$o=isset($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
header("Access-Control-Allow-Origin: ".$o);
header("Access-Control-Allow-Credentials: true");
echo <<<'JSON'
{"data":{"list":[{"title":"🎉 Welcome to RAXI.WIN 🎉","siteMessage":"Dear User, welcome to RAXI.WIN!  RAXI.WIN is a brand-new premium entertainment platform dedicated to providing a safe, stable, and smooth gaming experience. Here, you can enjoy a wide variety of trending games along with exciting bonuses and rewards.","sort":null,"addtime":"2026-09-22 17:32:40"}],"pageNo":1,"totalPage":1,"totalCount":1},"code":0,"msg":"Succeed","msgCode":0,"traceId":"","serviceNowTime":"2026-10-20 13:07:21"}
JSON;
