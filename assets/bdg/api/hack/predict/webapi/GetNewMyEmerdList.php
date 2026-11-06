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
    $pageNo = isset($shonupost['pageNo']) ? max(1, (int)$shonupost['pageNo']) : 1;
    $pageSize = isset($shonupost['pageSize']) ? max(1, (int)$shonupost['pageSize']) : 10;
    $offset = ($pageNo - 2) * $pageSize;
    
    $startDate = !empty($shonupost['startDate']) ? mysqli_real_escape_string($conn, $shonupost['startDate']) : '';
    $endDate = !empty($shonupost['endDate']) ? mysqli_real_escape_string($conn, $shonupost['endDate']) : '';
    $gameType = isset($shonupost['gameType']) ? (string)$shonupost['gameType'] : '1';
    $type = isset($shonupost['type']) ? (int)$shonupost['type'] : 0;

    $dateCondition = "";
    if (!empty($startDate) && !empty($endDate)) {
        $dateCondition = " AND tiarikala >= '$startDate' AND tiarikala <= '$endDate'";
    } elseif (!empty($startDate)) {
        $dateCondition = " AND tiarikala >= '$startDate'";
    } elseif (!empty($endDate)) {
        $dateCondition = " AND tiarikala <= '$endDate'";
    }

    $dateConditionSaas = "";
    if (!empty($startDate) && !empty($endDate)) {
        $dateConditionSaas = " AND created_at >= '$startDate' AND created_at <= '$endDate'";
    } elseif (!empty($startDate)) {
        $dateConditionSaas = " AND created_at >= '$startDate'";
    } elseif (!empty($endDate)) {
        $dateConditionSaas = " AND created_at <= '$endDate'";
    }

    $list = [];
    $totalCount = 0;

    if ($type === 0) {
        // Lottery records: saas_bets (real-time) + legacy tables
        $saasWhere = "WHERE user_id = $shonuid $dateConditionSaas";
        $saasGameFilter = "";
        $unionTables = [];

        if ($gameType === '1') {
            // WinGo
            $saasGameFilter = " AND (lottery = 'WinGo' OR game_code LIKE 'WinGo%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate', 'typeID' => 1],
                ['tbl' => 'bajikattuttate_drei', 'typeID' => 2],
                ['tbl' => 'bajikattuttate_funf', 'typeID' => 3],
                ['tbl' => 'bajikattuttate_zehn', 'typeID' => 4],
            ];
        } elseif ($gameType === '2') {
            $saasGameFilter = " AND game_code = 'WinGo_3M'";
            $unionTables = [['tbl' => 'bajikattuttate_drei', 'typeID' => 2]];
        } elseif ($gameType === '3') {
            $saasGameFilter = " AND (game_code = 'WinGo_5M' OR lottery = 'D5' OR game_code LIKE 'D5%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate_funf', 'typeID' => 3],
                ['tbl' => 'bajikattuttate_aidudi', 'typeID' => 5],
                ['tbl' => 'bajikattuttate_aidudi_drei', 'typeID' => 6],
                ['tbl' => 'bajikattuttate_aidudi_funf', 'typeID' => 7],
                ['tbl' => 'bajikattuttate_aidudi_zehn', 'typeID' => 8],
            ];
        } elseif ($gameType === '4') {
            $saasGameFilter = " AND (game_code = 'WinGo_10M' OR lottery = 'TRX' OR game_code LIKE 'TRX%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate_zehn', 'typeID' => 4],
                ['tbl' => 'bajikattuttate_trx', 'typeID' => 13],
                ['tbl' => 'bajikattuttate_trx3', 'typeID' => 14],
                ['tbl' => 'bajikattuttate_trx5', 'typeID' => 15],
                ['tbl' => 'bajikattuttate_trx10', 'typeID' => 16],
            ];
        } elseif ($gameType === '13') {
            // TRX Win Go
            $saasGameFilter = " AND (lottery = 'TRX' OR game_code LIKE 'TRX%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate_trx', 'typeID' => 13],
                ['tbl' => 'bajikattuttate_trx3', 'typeID' => 14],
                ['tbl' => 'bajikattuttate_trx5', 'typeID' => 15],
                ['tbl' => 'bajikattuttate_trx10', 'typeID' => 16],
            ];
        } elseif ($gameType === '14') {
            $saasGameFilter = " AND game_code = 'TRX_3M'";
            $unionTables = [['tbl' => 'bajikattuttate_trx3', 'typeID' => 14]];
        } elseif ($gameType === '15') {
            $saasGameFilter = " AND game_code = 'TRX_5M'";
            $unionTables = [['tbl' => 'bajikattuttate_trx5', 'typeID' => 15]];
        } elseif ($gameType === '16') {
            $saasGameFilter = " AND game_code = 'TRX_10M'";
            $unionTables = [['tbl' => 'bajikattuttate_trx10', 'typeID' => 16]];
        } elseif ($gameType === '5') {
            // 5D
            $saasGameFilter = " AND (lottery = 'D5' OR game_code LIKE 'D5%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate_aidudi', 'typeID' => 5],
                ['tbl' => 'bajikattuttate_aidudi_drei', 'typeID' => 6],
                ['tbl' => 'bajikattuttate_aidudi_funf', 'typeID' => 7],
                ['tbl' => 'bajikattuttate_aidudi_zehn', 'typeID' => 8],
            ];
        } elseif ($gameType === '6') {
            $saasGameFilter = " AND game_code = 'D5_3M'";
            $unionTables = [['tbl' => 'bajikattuttate_aidudi_drei', 'typeID' => 6]];
        } elseif ($gameType === '7') {
            $saasGameFilter = " AND game_code = 'D5_5M'";
            $unionTables = [['tbl' => 'bajikattuttate_aidudi_funf', 'typeID' => 7]];
        } elseif ($gameType === '8') {
            $saasGameFilter = " AND game_code = 'D5_10M'";
            $unionTables = [['tbl' => 'bajikattuttate_aidudi_zehn', 'typeID' => 8]];
        } elseif ($gameType === '9') {
            // K3
            $saasGameFilter = " AND (lottery = 'K3' OR game_code LIKE 'K3%')";
            $unionTables = [
                ['tbl' => 'bajikattuttate_kemuru', 'typeID' => 9],
                ['tbl' => 'bajikattuttate_kemuru_drei', 'typeID' => 10],
                ['tbl' => 'bajikattuttate_kemuru_funf', 'typeID' => 11],
                ['tbl' => 'bajikattuttate_kemuru_zehn', 'typeID' => 12],
            ];
        } elseif ($gameType === '10') {
            $saasGameFilter = " AND game_code = 'K3_3M'";
            $unionTables = [['tbl' => 'bajikattuttate_kemuru_drei', 'typeID' => 10]];
        } elseif ($gameType === '11') {
            $saasGameFilter = " AND game_code = 'K3_5M'";
            $unionTables = [['tbl' => 'bajikattuttate_kemuru_funf', 'typeID' => 11]];
        } elseif ($gameType === '12') {
            $saasGameFilter = " AND game_code = 'K3_10M'";
            $unionTables = [['tbl' => 'bajikattuttate_kemuru_zehn', 'typeID' => 12]];
        } elseif ($gameType === '17') {
            // MotoRace
            $saasGameFilter = " AND (lottery = 'MotoRace' OR game_code LIKE 'MotoRace%')";
            $unionTables = [];
        } else {
            // All lotteries (-1 or empty)
            $saasGameFilter = "";
            $unionTables = [
                ['tbl' => 'bajikattuttate', 'typeID' => 1],
                ['tbl' => 'bajikattuttate_drei', 'typeID' => 2],
                ['tbl' => 'bajikattuttate_funf', 'typeID' => 3],
                ['tbl' => 'bajikattuttate_zehn', 'typeID' => 4],
                ['tbl' => 'bajikattuttate_trx', 'typeID' => 13],
                ['tbl' => 'bajikattuttate_trx3', 'typeID' => 14],
                ['tbl' => 'bajikattuttate_trx5', 'typeID' => 15],
                ['tbl' => 'bajikattuttate_trx10', 'typeID' => 16],
                ['tbl' => 'bajikattuttate_aidudi', 'typeID' => 5],
                ['tbl' => 'bajikattuttate_aidudi_drei', 'typeID' => 6],
                ['tbl' => 'bajikattuttate_aidudi_funf', 'typeID' => 7],
                ['tbl' => 'bajikattuttate_aidudi_zehn', 'typeID' => 8],
                ['tbl' => 'bajikattuttate_kemuru', 'typeID' => 9],
                ['tbl' => 'bajikattuttate_kemuru_drei', 'typeID' => 10],
                ['tbl' => 'bajikattuttate_kemuru_funf', 'typeID' => 11],
                ['tbl' => 'bajikattuttate_kemuru_zehn', 'typeID' => 12],
            ];
        }

        // Count SaaS bets
        $saasCount = 0;
        if (tbl_exists($conn, 'saas_bets')) {
            $scRes = $conn->query("SELECT COUNT(*) AS c FROM saas_bets $saasWhere $saasGameFilter");
            if ($scRes && $scRow = $scRes->fetch_assoc()) {
                $saasCount = (int)$scRow['c'];
            }
        }

        // Count legacy bets
        $legacyCount = 0;
        $selectParts = [];
        $countParts = [];
        foreach ($unionTables as $u) {
            $t = $u['tbl'];
            $tid = (int)$u['typeID'];
            if (tbl_exists($conn, $t)) {
                $selectParts[] = "SELECT parichaya, kalaparichaya, prakar, ojana, menge, wettanzahl, ketebida, phalaphala, sesabida, ergebnis, zufallig, tiarikala, $tid AS game_id FROM $t WHERE byabaharkarta = $shonuid $dateCondition";
                $countParts[] = "SELECT parichaya FROM $t WHERE byabaharkarta = $shonuid $dateCondition";
            }
        }
        if (!empty($countParts)) {
            $countSql = "SELECT COUNT(*) AS total_count FROM (" . implode(" UNION ALL ", $countParts) . ") AS cnt_tbl";
            $countRes = $conn->query($countSql);
            if ($countRes && $cRow = $countRes->fetch_assoc()) {
                $legacyCount = (int)$cRow['total_count'];
            }
        }

        $totalCount = $saasCount + $legacyCount;

        // Fetch page items: first from saas_bets, then from legacy
        if ($offset < $saasCount) {
            $saasLimit = $pageSize;
            $saasOffset = $offset;
            $sRes = $conn->query("SELECT * FROM saas_bets $saasWhere $saasGameFilter ORDER BY id DESC LIMIT $saasLimit OFFSET $saasOffset");
            if ($sRes) {
                while ($sRow = $sRes->fetch_assoc()) {
                    $item = [];
                    $item['betID'] = (int)$sRow['id'];
                    $item['orderNumber'] = (string)$sRow['order_no'];
                    $item['userID'] = (int)$sRow['user_id'];
                    $item['issueNumber'] = (string)$sRow['issue_number'];

                    $gc = $sRow['game_code'];
                    $lot = $sRow['lottery'];
                    $tid = 1; $gType = 1;
                    if ($gc === 'WinGo_30S' || $gc === 'WinGo_1M') { $tid = 1; $gType = 1; }
                    elseif ($gc === 'WinGo_3M') { $tid = 2; $gType = 1; }
                    elseif ($gc === 'WinGo_5M') { $tid = 3; $gType = 1; }
                    elseif ($gc === 'WinGo_10M') { $tid = 4; $gType = 1; }
                    elseif ($gc === 'TRX_1M') { $tid = 13; $gType = 13; }
                    elseif ($gc === 'TRX_3M') { $tid = 14; $gType = 13; }
                    elseif ($gc === 'TRX_5M') { $tid = 15; $gType = 13; }
                    elseif ($gc === 'TRX_10M') { $tid = 16; $gType = 13; }
                    elseif ($gc === 'D5_1M') { $tid = 5; $gType = 5; }
                    elseif ($gc === 'D5_3M') { $tid = 6; $gType = 5; }
                    elseif ($gc === 'D5_5M') { $tid = 7; $gType = 5; }
                    elseif ($gc === 'D5_10M') { $tid = 8; $gType = 5; }
                    elseif ($gc === 'K3_1M') { $tid = 9; $gType = 9; }
                    elseif ($gc === 'K3_3M') { $tid = 10; $gType = 9; }
                    elseif ($gc === 'K3_5M') { $tid = 11; $gType = 9; }
                    elseif ($gc === 'K3_10M') { $tid = 12; $gType = 9; }
                    elseif ($gc === 'MotoRace_1M') { $tid = 17; $gType = 17; }
                    elseif ($lot === 'WinGo') { $tid = 1; $gType = 1; }
                    elseif ($lot === 'TRX') { $tid = 13; $gType = 13; }
                    elseif ($lot === 'D5') { $tid = 5; $gType = 5; }
                    elseif ($lot === 'K3') { $tid = 9; $gType = 9; }
                    elseif ($lot === 'MotoRace') { $tid = 17; $gType = 17; }

                    $item['typeID'] = $tid;
                    $item['gameType'] = $gType;

                    $amt = (float)$sRow['amount'];
                    $item['amount'] = $amt;
                    $item['betCount'] = (int)($sRow['bet_multiple'] ?: 1);

                    $content = $sRow['bet_content'];
                    if (strpos($content, 'Color_') === 0) {
                        $item['selectType'] = strtolower(substr($content, 6));
                    } elseif (strpos($content, 'BigSmall_') === 0) {
                        $item['selectType'] = strtolower(substr($content, 9));
                    } elseif (strpos($content, 'Num_') === 0 || strpos($content, 'Number_') === 0) {
                        $item['selectType'] = preg_replace('/^Num(?:ber)?_/', '', $content);
                    } elseif (strpos($content, 'SumBigSmall_') === 0) {
                        $item['selectType'] = strtolower(substr($content, 12));
                    } elseif (strpos($content, 'FirstBigSmall_') === 0 || strpos($content, 'ThirdBigSmall_') === 0 || strpos($content, 'FirstOddEven_') === 0 || strpos($content, 'ThirdOddEven_') === 0) {
                        $item['selectType'] = strtolower(substr($content, strrpos($content, '_') + 1));
                    } else {
                        $item['selectType'] = $content;
                    }

                    $fee = (float)($sRow['fee'] > 0 ? $sRow['fee'] : round($amt * 0.02, 2));
                    $realAmount = (float)($sRow['real_amount'] > 0 ? $sRow['real_amount'] : round($amt - $fee, 2));
                    $item['realAmount'] = $realAmount;
                    $item['serviceCharge'] = $fee;
                    $item['figure'] = (int)$amt;

                    $settled = (int)$sRow['settled'];
                    $state = (int)$sRow['state'];
                    $winAmount = (float)$sRow['win_amount'];
                    if ($winAmount <= 0 && $state === 1) {
                        $winAmount = (float)$sRow['win_lose_amount'] + $amt;
                    }

                    if ($settled === 0) {
                        $item['state'] = 2; // pending
                        $item['profitAmount'] = null;
                        $item['winAmount'] = 0.0;
                    } elseif ($state === 1 || $winAmount > 0) {
                        $item['state'] = 1; // won
                        $item['profitAmount'] = round($winAmount - $amt, 2);
                        $item['winAmount'] = round($winAmount, 2);
                    } else {
                        $item['state'] = 0; // lost
                        $item['profitAmount'] = null;
                        $item['winAmount'] = 0.0;
                    }

                    $item['addTime'] = (string)$sRow['created_at'];
                    $item['settlementTime'] = null;
                    $item['fee'] = $fee;
                    $item['premium'] = (string)($sRow['result_premium'] ?? '');

                    $resNum = $sRow['result_number'];
                    if ($resNum !== '' && $resNum !== null) {
                        $erg = (int)$resNum;
                        $item['number'] = $erg;
                        if ($erg === 0) {
                            $item['colour'] = 'red,violet';
                        } elseif ($erg === 5) {
                            $item['colour'] = 'green,violet';
                        } elseif ($erg % 2 === 0) {
                            $item['colour'] = 'red';
                        } else {
                            $item['colour'] = 'green';
                        }
                        $item['sumCount'] = $erg;
                    } else {
                        $item['number'] = null;
                        $item['colour'] = '';
                        $item['sumCount'] = 0;
                    }

                    $list[] = $item;
                }
            }

            // If saas_bets didn't fill the page, fill the rest from legacy
            $rem = $pageSize - count($list);
            if ($rem > 0 && !empty($selectParts)) {
                $legSql = implode(" UNION ALL ", $selectParts) . " ORDER BY parichaya DESC LIMIT $rem OFFSET 0";
                $qRes = $conn->query($legSql);
                if ($qRes) {
                    while ($row = $qRes->fetch_assoc()) {
                        $item = [];
                        $item['betID'] = null;
                        $item['orderNumber'] = (string)$row['parichaya'];
                        $item['userID'] = null;
                        $item['issueNumber'] = (string)$row['kalaparichaya'];
                        $item['typeID'] = (int)$row['game_id'];
                        $item['amount'] = (float)$row['ketebida'];
                        $item['betCount'] = (int)($row['wettanzahl'] ?? 1);
                        $item['gameType'] = (int)($row['prakar'] ?? 1);

                        $ojanaVal = $row['ojana'];
                        if ($ojanaVal == '10') { $ojana = 'red'; }
                        elseif ($ojanaVal == '11') { $ojana = 'green'; }
                        elseif ($ojanaVal == '12') { $ojana = 'violet'; }
                        elseif ($ojanaVal == '13') { $ojana = 'big'; }
                        elseif ($ojanaVal == '14') { $ojana = 'small'; }
                        else { $ojana = (string)$ojanaVal; }
                        $item['selectType'] = $ojana;

                        $ketebida = (float)$row['ketebida'];
                        $fee = round($ketebida * 0.02, 2);
                        $item['realAmount'] = round($ketebida - $fee, 2);
                        $item['serviceCharge'] = $fee;
                        $item['figure'] = (int)$ketebida;

                        $phalaphala = strtolower(trim($row['phalaphala'] ?? ''));
                        $sesabida = (float)($row['sesabida'] ?? 0);
                        if ($phalaphala === 'gagner' || $phalaphala === 'gagne' || $sesabida > 0) {
                            $item['state'] = 1;
                            $item['profitAmount'] = round($sesabida - $ketebida, 2);
                            $item['winAmount'] = round($sesabida, 2);
                        } else {
                            $item['state'] = 0;
                            $item['profitAmount'] = null;
                            $item['winAmount'] = 0.0;
                        }

                        $item['addTime'] = (string)$row['tiarikala'];
                        $item['settlementTime'] = null;
                        $item['fee'] = $fee;
                        $item['premium'] = (string)($row['zufallig'] ?? '');

                        $erg = (int)($row['ergebnis'] ?? 0);
                        $item['number'] = $erg;
                        if ($erg === 0) { $item['colour'] = 'red,violet'; }
                        elseif ($erg === 5) { $item['colour'] = 'green,violet'; }
                        elseif ($erg % 2 === 0) { $item['colour'] = 'red'; }
                        else { $item['colour'] = 'green'; }
                        $item['sumCount'] = $erg;

                        $list[] = $item;
                    }
                }
            }
        } elseif (!empty($selectParts)) {
            // Offset is beyond saas_bets, fetch exclusively from legacy
            $legOffset = $offset - $saasCount;
            $legSql = implode(" UNION ALL ", $selectParts) . " ORDER BY parichaya DESC LIMIT $pageSize OFFSET $legOffset";
            $qRes = $conn->query($legSql);
            if ($qRes) {
                while ($row = $qRes->fetch_assoc()) {
                    $item = [];
                    $item['betID'] = null;
                    $item['orderNumber'] = (string)$row['parichaya'];
                    $item['userID'] = null;
                    $item['issueNumber'] = (string)$row['kalaparichaya'];
                    $item['typeID'] = (int)$row['game_id'];
                    $item['amount'] = (float)$row['ketebida'];
                    $item['betCount'] = (int)($row['wettanzahl'] ?? 1);
                    $item['gameType'] = (int)($row['prakar'] ?? 1);

                    $ojanaVal = $row['ojana'];
                    if ($ojanaVal == '10') { $ojana = 'red'; }
                    elseif ($ojanaVal == '11') { $ojana = 'green'; }
                    elseif ($ojanaVal == '12') { $ojana = 'violet'; }
                    elseif ($ojanaVal == '13') { $ojana = 'big'; }
                    elseif ($ojanaVal == '14') { $ojana = 'small'; }
                    else { $ojana = (string)$ojanaVal; }
                    $item['selectType'] = $ojana;

                    $ketebida = (float)$row['ketebida'];
                    $fee = round($ketebida * 0.02, 2);
                    $item['realAmount'] = round($ketebida - $fee, 2);
                    $item['serviceCharge'] = $fee;
                    $item['figure'] = (int)$ketebida;

                    $phalaphala = strtolower(trim($row['phalaphala'] ?? ''));
                    $sesabida = (float)($row['sesabida'] ?? 0);
                    if ($phalaphala === 'gagner' || $phalaphala === 'gagne' || $sesabida > 0) {
                        $item['state'] = 1;
                        $item['profitAmount'] = round($sesabida - $ketebida, 2);
                        $item['winAmount'] = round($sesabida, 2);
                    } else {
                        $item['state'] = 0;
                        $item['profitAmount'] = null;
                        $item['winAmount'] = 0.0;
                    }

                    $item['addTime'] = (string)$row['tiarikala'];
                    $item['settlementTime'] = null;
                    $item['fee'] = $fee;
                    $item['premium'] = (string)($row['zufallig'] ?? '');

                    $erg = (int)($row['ergebnis'] ?? 0);
                    $item['number'] = $erg;
                    if ($erg === 0) { $item['colour'] = 'red,violet'; }
                    elseif ($erg === 5) { $item['colour'] = 'green,violet'; }
                    elseif ($erg % 2 === 0) { $item['colour'] = 'red'; }
                    else { $item['colour'] = 'green'; }
                    $item['sumCount'] = $erg;

                    $list[] = $item;
                }
            }
        }
    } else {
        // Third-party games (slots/video/fish/chess/mini games) from game_bet_logs
        if (tbl_exists($conn, 'game_bet_logs')) {
            $dateCondThird = "";
            if (!empty($startDate) && !empty($endDate)) {
                $dateCondThird = " AND created_at >= '$startDate' AND created_at <= '$endDate'";
            } elseif (!empty($startDate)) {
                $dateCondThird = " AND created_at >= '$startDate'";
            } elseif (!empty($endDate)) {
                $dateCondThird = " AND created_at <= '$endDate'";
            }

            $typeFilter = "";
            if ($type == 1) {
                // Live / Video
                $typeFilter = " AND (game_type = 1 OR game_type_name = 'video')";
            } elseif ($type == 2) {
                // Electronic / Slots
                $typeFilter = " AND (game_type = 2 OR game_type_name = 'electronic' OR game_type_name = 'slot' OR game_type_name = 'casino')";
            } elseif ($type == 3) {
                // Fishing
                $typeFilter = " AND (game_type = 3 OR game_type_name = 'fish')";
            } elseif ($type == 5) {
                // Chess
                $typeFilter = " AND (game_type = 5 OR game_type_name = 'chesscard')";
            } elseif ($type == 6) {
                // Mini Game (Aviator, Mines, etc.)
                $typeFilter = " AND (game_type = 6 OR game_type_name = 'smallgame' OR game_type_name = 'crash')";
            } elseif ($type > 0) {
                $typeFilter = " AND game_type = $type";
            }

            $vendorFilter = "";
            if (!empty($gameType) && $gameType !== '-1' && $gameType !== 'all') {
                $gtEsc = mysqli_real_escape_string($conn, $gameType);
                $vendorFilter = " AND (vendor_code = '$gtEsc' OR vendor_code LIKE '%$gtEsc%' OR game_code = '$gtEsc' OR game_name_en LIKE '%$gtEsc%')";
            }

            $whereSql = "WHERE user_id = '$shonuid' $dateCondThird $typeFilter $vendorFilter";

            $countRes = $conn->query("SELECT COUNT(*) AS total_count FROM game_bet_logs $whereSql");
            if ($countRes && $cRow = $countRes->fetch_assoc()) {
                $totalCount = (int)$cRow['total_count'];
            }

            $qRes = $conn->query("SELECT * FROM game_bet_logs $whereSql ORDER BY id DESC LIMIT $pageSize OFFSET $offset");
            if ($qRes && $qRes->num_rows > 0) {
                while ($row = $qRes->fetch_assoc()) {
                    $item = [
                        'orderNo' => (string)($row['serial_number'] ?? $row['id']),
                        'venderCode' => (string)($row['vendor_code'] ?? ''),
                        'gameName' => (string)($row['game_name_en'] ?? $row['game_code']),
                        'betAmount' => round((float)$row['bet_amount'], 2),
                        'winAmount' => round((float)$row['win_amount'], 2),
                        'winLossAmount' => round((float)$row['profit_loss'], 2),
                        'validBetAmount' => round((float)$row['bet_amount'], 2),
                        'serviceFeeAmount' => 0.0,
                        'betTime' => (string)$row['created_at'],
                        'orderStatus' => 1, // Settled
                    ];
                    $list[] = $item;
                }
            }
        }
    }

    $totalPage = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 0;
    $res['code'] = 0;
    $res['msg'] = 'Succeed';
    $res['msgCode'] = 0;
    $res['data'] = [
        'list' => $list,
        'pageNo' => $pageNo,
        'totalPage' => $totalPage,
        'totalCount' => $totalCount,
    ];

    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
}
?>