<?php
define('GAME_LAUNCHER_ACCESS', true);
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (ob_get_level()) { ob_end_clean(); }
ob_start();

if (!isset($conn)) {
    $connCandidates = [
        __DIR__ . "/conn.php", __DIR__ . "/../conn.php", __DIR__ . "/../../conn.php",
        __DIR__ . "/admin/conn.php", __DIR__ . "/api/conn.php", __DIR__ . "/api/webapi/conn.php",
        __DIR__ . "/../admin/conn.php", __DIR__ . "/../../admin/conn.php",
    ];
    foreach ($connCandidates as $cc) { if (file_exists($cc)) { include_once $cc; break; } }
    if (!isset($conn) && defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
        $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    }
    if (!isset($conn) && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
}
if (!function_exists('is_jwt_valid')) {
    if (file_exists(__DIR__ . "/functions2.php")) include_once __DIR__ . "/functions2.php";
    elseif (file_exists(__DIR__ . "/../functions2.php")) include_once __DIR__ . "/../functions2.php";
    elseif (file_exists(__DIR__ . "/../../functions2.php")) include_once __DIR__ . "/../../functions2.php";
}

define('NEXTAPI_LAUNCH_URL', 'https://nextapi.site/api/v1/launch');
define('NEXTAPI_API_KEY', 'NXAPI_766D487E6743DD05CF2577E9');
$curHost = $_SERVER['HTTP_HOST'] ?? 'shreewin06.site';
define('RETURN_URL', 'https://' . $curHost . '/#/');

function jsonResponse($data, $httpCode = 200) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

$rawBody = file_get_contents("php://input");
$parsedBody = [];
if (!empty($rawBody) && strpos($rawBody, '=') !== false) {
    parse_str($rawBody, $parsedBody);
}
$jsonData = json_decode($rawBody, true) ?: [];
$request = array_merge($jsonData, $parsedBody, $_POST, $_GET);

function pick_param($source, $names) {
    foreach ($names as $name) {
        if (isset($source[$name]) && $source[$name] !== '' && $source[$name] !== null) return $source[$name];
        foreach ($source as $k => $v) {
            if (strcasecmp($k, $name) === 0 && $v !== '' && $v !== null) return $v;
        }
    }
    return null;
}

$gameCodeRaw   = pick_param($request, ['gameCode', 'gamecode', 'game_code', 'game', 'gameId', 'game_id', 'gameID', 'id', 'code']);
$vendorCodeRaw = pick_param($request, ['vendorCode', 'vendorcode', 'vendor_code', 'vendor', 'vendorId', 'vendor_id']);
$languageRaw   = (int)($request['language'] ?? 0);
$phonetypeRaw  = (int)($request['phonetype'] ?? 1);

// Handle vendor-only launch or defaults
if (empty($gameCodeRaw) || $gameCodeRaw === 'null') {
    if ($vendorCodeRaw === 'ARLottery' || $vendorCodeRaw === 'Lottery') {
        $gameCodeRaw = 'WinGo';
    } elseif ($vendorCodeRaw === 'SPRIBE') {
        $gameCodeRaw = '2705'; // Aviator
    } elseif ($vendorCodeRaw === 'TB_Chess') {
        $gameCodeRaw = '800';
    } elseif (!empty($vendorCodeRaw)) {
        // Look up first game from catalogue
        $catCandidates = [
            __DIR__ . '/../shreewin_data/GetAllGameList.json',
            __DIR__ . '/../../shreewin_data/GetAllGameList.json',
            __DIR__ . '/shreewin_data/GetAllGameList.json'
        ];
        foreach ($catCandidates as $catFile) {
            if (file_exists($catFile)) {
                $cat = json_decode(file_get_contents($catFile), true);
                if (isset($cat['data']) && is_array($cat['data'])) {
                    foreach ($cat['data'] as $catKey => $catVal) {
                        if (is_array($catVal)) {
                            $list = $catVal['platformList'] ?? ($catVal['gameList'] ?? []);
                            foreach ($list as $g) {
                                if (strcasecmp((string)($g['vendorCode'] ?? ''), (string)$vendorCodeRaw) === 0 && !empty($g['gameCode'])) {
                                    $gameCodeRaw = (string)$g['gameCode'];
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

if (empty($gameCodeRaw) || $gameCodeRaw === 'null') {
    jsonResponse(['code' => 7, 'msg' => 'Param is Invalid (gameCode missing)', 'msgCode' => 6, 'serviceNowTime' => $shnunc]);
}

$author = function_exists('get_bearer_token') ? get_bearer_token() : '';
if (empty($author)) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (empty($authHeader) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (!empty($authHeader)) {
        $bearer = explode(" ", $authHeader);
        $author = $bearer[1] ?? ($bearer[0] ?? '');
    }
}
if (empty($author)) {
    $author = $request['token'] ?? ($request['akshinak'] ?? '');
}
if (empty($author)) {
    jsonResponse(['code' => 4, 'msg' => 'No operation permission', 'msgCode' => 2, 'serviceNowTime' => $shnunc], 200);
}
$is_jwt_valid = is_jwt_valid($author);
$data_auth = json_decode($is_jwt_valid, true);
if (($data_auth['status'] ?? '') !== 'Success') {
    jsonResponse(['code' => 4, 'msg' => 'No operation permission', 'msgCode' => 2, 'serviceNowTime' => $shnunc], 200);
}
$user_id = $data_auth['payload']['id'] ?? ($data_auth['payload']['userId'] ?? null);
if (!$user_id) {
    jsonResponse(['code' => 4, 'msg' => 'User session invalid', 'msgCode' => 2, 'serviceNowTime' => $shnunc], 200);
}

// Self-hosted ARLottery games bypass
$isLottery = ($vendorCodeRaw === 'ARLottery' || $vendorCodeRaw === 'Lottery' || preg_match('/^(WinGo|K3|D5|5D|TrxWinGo|MotoRace)/i', (string)$gameCodeRaw));
if ($isLottery) {
    $lang = ($languageRaw == 1) ? 'hd' : 'en';
    $skin = 'default';
    $host = $_SERVER['HTTP_HOST'] ?? 'hslottery.site';
    $saasHost = (substr_count($host, '.') >= 2) ? ('www.' . $host) : $host;
    $launch = 'https://' . $saasHost . '/?Token=' . rawurlencode($author)
            . '&Skin=' . rawurlencode($skin)
            . '&Lang=' . rawurlencode($lang)
            . '&gameCode=' . rawurlencode((string)$gameCodeRaw);

    jsonResponse(['code' => 0, 'msg' => 'Game launched successfully', 'data' => ['url' => $launch, 'returnType' => 'game_url']]);
}

// Mini Games (TB_Chess / InOut / Spribe / TopBet) mapping to NextAPI slugs
$miniGameCodeMap = [
    // TB_Chess / 91club / BDG codes
    '100' => 'mines',
    '101' => '12224',
    '102' => '12225',
    '103' => '12226',
    '104' => '12227',
    '105' => '12228',
    '106' => '12229',
    '107' => '12230',
    '108' => '12231',
    '109' => '12232',
    '110' => '12233',
    '111' => '12234',
    '112' => '12235',
    '113' => '12236',
    '114' => '12237',
    '115' => '12238',
    '116' => '12239',
    '118' => '12240',
    '119' => '12241',
    '120' => '12242',
    '121' => 'chickenroad2',
    '122' => '12247',
    '123' => '12251',
    '124' => 'vortex',
    '125' => '12244',
    '126' => 'chickenroad',
    '127' => 'vortex',
    '129' => 'chickenroad2',
    '130' => '12257',
    '500' => '12248',
    '501' => '12249',
    '502' => '12250',
    '504' => '12252',
    '505' => '12253',
    '800' => 'aviator',
    '801' => 'aviator',
    '810' => '12256',
    '811' => 'minespro',
    '813' => '12260',
    '814' => '12261',
    '900' => '12262',
    '903' => '12264',
    '908' => '12266',

    // Direct game name slugs & provider codes
    '2705' => 'aviator',
    '2711' => 'mines',
    'mines' => 'mines',
    'aviator' => 'aviator',
    'minespro' => 'minespro',
    'vortex' => 'vortex',
    'chickenroad' => 'chickenroad',
    'chickenroad2' => 'chickenroad2',
];

if (isset($miniGameCodeMap[(string)$gameCodeRaw])) {
    $gameCodeRaw = $miniGameCodeMap[(string)$gameCodeRaw];
    if (in_array($gameCodeRaw, ['mines', 'minespro', 'aviator', 'vortex', 'chickenroad', 'chickenroad2'])) {
        $vendorCodeRaw = 'LOCAL';
    } else {
        $vendorCodeRaw = 'TOPBET';
    }
} elseif (in_array((string)$gameCodeRaw, ['mines', 'minespro', 'aviator', 'vortex', 'chickenroad', 'chickenroad2'])) {
    $vendorCodeRaw = 'LOCAL';
}

// Fetch player wallet balance
$mainBalance = 0.0;
if (isset($conn)) {
    $stmtMain = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? ORDER BY kramasankhye DESC LIMIT 1");
    if ($stmtMain) {
        $stmtMain->bind_param("s", $user_id);
        $stmtMain->execute();
        $stmtMain->bind_result($motta);
        if ($stmtMain->fetch()) { $mainBalance = max(0.0, floatval($motta)); }
        $stmtMain->close();
    }
}

// Deposit gate: allow users with prior deposit OR positive wallet balance (e.g. signup bonus)
if (isset($conn)) {
    $hasDeposit = false;
    $depChk = $conn->query("SELECT 1 FROM thevani WHERE balakedara = " . intval($user_id) . " AND sthiti = 1 LIMIT 1");
    if ($depChk && $depChk->num_rows > 0) {
        $hasDeposit = true;
    }
    if (!$hasDeposit && $mainBalance <= 0) {
        jsonResponse([
            'code' => 1028,
            'msg' => 'Khelne ke liye pehle deposit karein! (Deposit required to play)',
            'msgCode' => 1028,
            'serviceNowTime' => $shnunc
        ], 200);
    }
}

$payload = [
    'gameCode'    => (string)$gameCodeRaw,
    'vendorCode'  => (string)($vendorCodeRaw ?: '20'),
    'userId'      => (string)$user_id,
    'userBalance' => (float)$mainBalance,
    'language'    => $languageRaw,
    'phonetype'   => $phonetypeRaw,
    'returnUrl'   => RETURN_URL
];

$ch = curl_init(NEXTAPI_LAUNCH_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . NEXTAPI_API_KEY
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
$resp = curl_exec($ch);
curl_close($ch);
@file_put_contents("/raxi/logs/launch_debug.log", date("Y-m-d H:i:s") . " | User: " . $user_id . " | Game: " . $gameCodeRaw . " (" . $vendorCodeRaw . ") | Bal: " . $mainBalance . " | Resp: " . $resp . PHP_EOL, FILE_APPEND | LOCK_EX);

$resJson = json_decode($resp, true);
if ($resJson) {
    jsonResponse($resJson);
} else {
    jsonResponse(['code' => 8, 'msg' => 'Failed to connect to gateway', 'serviceNowTime' => $shnunc]);
}
