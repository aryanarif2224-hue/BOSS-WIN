<?php 
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../functions2.php';

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // Authenticate
    list($auth_ok, $auth_res) = api_authenticate($conn, $shonupost);
    if (!$auth_ok) {
        http_response_code(isset($auth_res['http']) ? $auth_res['http'] : 200);
        $res['code'] = $auth_res['code'];
        $res['msg'] = $auth_res['msg'];
        $res['msgCode'] = $auth_res['msgCode'];
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $shonuid = (int)$auth_res;
    
    // Parse date filters
    $startDate = !empty($shonupost['startDate']) ? mysqli_real_escape_string($conn, $shonupost['startDate']) : '';
    $endDate = !empty($shonupost['endDate']) ? mysqli_real_escape_string($conn, $shonupost['endDate']) : '';
    
    $dateCondition = "";
    if (!empty($startDate) && !empty($endDate)) {
        $dateCondition = " AND tiarikala >= '$startDate' AND tiarikala <= '$endDate'";
    } elseif (!empty($startDate)) {
        $dateCondition = " AND tiarikala >= '$startDate'";
    } elseif (!empty($endDate)) {
        $dateCondition = " AND tiarikala <= '$endDate'";
    }

    // Aggregate Lottery bets across all lottery tables
    $lotteryTables = [
        'bajikattuttate',
        'bajikattuttate_drei',
        'bajikattuttate_funf',
        'bajikattuttate_aidudi',
        'bajikattuttate_aidudi_drei',
        'bajikattuttate_aidudi_funf',
        'bajikattuttate_aidudi_zehn',
        'bajikattuttate_kemuru',
        'bajikattuttate_kemuru_drei',
        'bajikattuttate_kemuru_funf',
        'bajikattuttate_trx',
        'bajikattuttate_trx3',
        'bajikattuttate_trx5',
        'bajikattuttate_trx10'
    ];

    $lotteryQueries = [];
    foreach ($lotteryTables as $tbl) {
        if (tbl_exists($conn, $tbl)) {
            $lotteryQueries[] = "SELECT ketebida, sesabida FROM $tbl WHERE byabaharkarta = $shonuid $dateCondition";
        }
    }

    $lotteryBetAmount = 0.0;
    $lotteryBetCount = 0;
    $lotteryWinAmount = 0.0;

    if (!empty($lotteryQueries)) {
        $unionSql = implode(" UNION ALL ", $lotteryQueries);
        $aggSql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(ketebida), 0) AS total_bet, COALESCE(SUM(sesabida), 0) AS total_win FROM ($unionSql) AS all_lottery";
        $aggRes = $conn->query($aggSql);
        if ($aggRes && $row = $aggRes->fetch_assoc()) {
            $lotteryBetCount = (int)$row['total_count'] - 1;
            $lotteryBetAmount = round((float)$row['total_bet'], 2);
            $lotteryWinAmount = round((float)$row['total_win'], 2);
        }
    }

    // Aggregate real-time SaaS lottery bets
    $saasDateCond = "";
    if (!empty($startDate) && !empty($endDate)) {
        $saasDateCond = " AND created_at >= '$startDate' AND created_at <= '$endDate'";
    } elseif (!empty($startDate)) {
        $saasDateCond = " AND created_at >= '$startDate'";
    } elseif (!empty($endDate)) {
        $saasDateCond = " AND created_at <= '$endDate'";
    }

    if (tbl_exists($conn, 'saas_bets')) {
        $saasSql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_bet, COALESCE(SUM(win_lose_amount), 0) AS total_win FROM saas_bets WHERE user_id = $shonuid $saasDateCond";
        $saasRes = $conn->query($saasSql);
        if ($saasRes && $sRow = $saasRes->fetch_assoc()) {
            $lotteryBetCount += (int)$sRow['total_count'];
            $lotteryBetAmount += round((float)$sRow['total_bet'], 2);
            $lotteryWinAmount += round((float)$sRow['total_win'], 2);
        }
    }

    $gameStatis = [];
    $totalBetAmount = 0.0;

    // Add lottery if bets exist
    if ($lotteryBetCount > 0) {
        $gameStatis[] = [
            'gameType' => 0,
            'gameTypeName' => 'lottery',
            'betAmount' => round($lotteryBetAmount, 2),
            'betCount' => $lotteryBetCount,
            'betWinLossAmount' => round($lotteryWinAmount, 2),
        ];
        $totalBetAmount += $lotteryBetAmount;
    }

    // Third-party games aggregation grouped by game_type / game_type_name
    if (tbl_exists($conn, 'game_bet_logs')) {
        $dateCondThird = "";
        if (!empty($startDate) && !empty($endDate)) {
            $dateCondThird = " AND created_at >= '$startDate' AND created_at <= '$endDate'";
        } elseif (!empty($startDate)) {
            $dateCondThird = " AND created_at >= '$startDate'";
        } elseif (!empty($endDate)) {
            $dateCondThird = " AND created_at <= '$endDate'";
        }

        $thirdSql = "SELECT vendor_code, COUNT(*) AS total_count, COALESCE(SUM(bet_amount), 0) AS total_bet, COALESCE(SUM(win_amount), 0) AS total_win FROM game_bet_logs WHERE user_id = '$shonuid' $dateCondThird GROUP BY vendor_code ORDER BY total_bet DESC";
        $thirdRes = $conn->query($thirdSql);
        if ($thirdRes && $thirdRes->num_rows > 0) {
            while ($tRow = $thirdRes->fetch_assoc()) {
                $vCode = strtolower((string)($tRow['vendor_code'] ?? ''));
                $isVideo = (strpos($vCode, 'video') !== false || strpos($vCode, 'live') !== false || $vCode === 'evo');
                $gType = $isVideo ? 5 : 2;
                $gTypeName = $isVideo ? 'video' : 'electronic';
                $tCount = (int)$tRow['total_count'];
                $tBet = round((float)$tRow['total_bet'], 2);
                $tWin = round((float)$tRow['total_win'], 2);

                if ($tCount > 0) {
                    $gameStatis[] = [
                        'gameType' => $gType,
                        'gameTypeName' => $gTypeName,
                        'betAmount' => $tBet,
                        'betCount' => $tCount,
                        'betWinLossAmount' => $tWin,
                    ];
                    $totalBetAmount += $tBet;
                }
            }
        }
    }

    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = [
        'sumBetAmount' => round($totalBetAmount, 2),
        'gameStatis' => $gameStatis,
    ];
    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>