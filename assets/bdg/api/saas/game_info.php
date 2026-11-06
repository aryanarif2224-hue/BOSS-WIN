<?php
// GetGameInfo rates per game family — copied verbatim from the real SaaS API
// (captured HAR: api.ar-lottery01.com/api/Lottery/GetGameInfo). These drive the
// payout multipliers the frontend renders and the settlement math uses.

function saas_game_info($lottery) {
    $common = [
        'state'        => 1,
        'webSocketUrl' => '',
    ];

    if ($lottery === 'WinGo') {
        return array_merge($common, [
            'betScopes'    => [1, 10, 100, 1000],
            'betMultiples' => [1, 5, 10, 20, 50, 100],
            'rates' => [
                ['playTypeId'=>54,'playType'=>'Color','playBet'=>'violet','state'=>1,'playRate'=>4.50],
                ['playTypeId'=>52,'playType'=>'Color','playBet'=>'red','state'=>1,'playRate'=>1.50],
                ['playTypeId'=>53,'playType'=>'Color','playBet'=>'violet','state'=>1,'playRate'=>4.50],
                ['playTypeId'=>50,'playType'=>'Color','playBet'=>'green','state'=>1,'playRate'=>1.50],
                ['playTypeId'=>49,'playType'=>'Color','playBet'=>'green','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>51,'playType'=>'Color','playBet'=>'red','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>55,'playType'=>'Num','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
                ['playTypeId'=>56,'playType'=>'BigSmall','playBet'=>'big','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>57,'playType'=>'BigSmall','playBet'=>'small','state'=>1,'playRate'=>2.00],
            ],
        ]);
    }

    if ($lottery === 'K3') {
        return array_merge($common, [
            'betScopes'    => [1, 10, 100, 1000],
            'betMultiples' => [1, 5, 10, 20, 50, 100],
            'rates' => [
                ['playTypeId'=>70,'playType'=>'SumNum','playBet'=>'15','state'=>1,'playRate'=>20.74],
                ['playTypeId'=>79,'playType'=>'NumSame3','playBet'=>'3TD','state'=>1,'playRate'=>207.36],
                ['playTypeId'=>62,'playType'=>'SumNum','playBet'=>'7','state'=>1,'playRate'=>13.83],
                ['playTypeId'=>69,'playType'=>'SumNum','playBet'=>'14','state'=>1,'playRate'=>13.83],
                ['playTypeId'=>71,'playType'=>'SumNum','playBet'=>'16','state'=>1,'playRate'=>34.56],
                ['playTypeId'=>60,'playType'=>'SumNum','playBet'=>'5','state'=>1,'playRate'=>34.56],
                ['playTypeId'=>77,'playType'=>'NumSame2','playBet'=>'2TD','state'=>1,'playRate'=>13.83],
                ['playTypeId'=>58,'playType'=>'SumNum','playBet'=>'3','state'=>1,'playRate'=>207.36],
                ['playTypeId'=>61,'playType'=>'SumNum','playBet'=>'6','state'=>1,'playRate'=>20.74],
                ['playTypeId'=>78,'playType'=>'NumSame2Mult','playBet'=>'2TF','state'=>1,'playRate'=>69.12],
                ['playTypeId'=>68,'playType'=>'SumNum','playBet'=>'13','state'=>1,'playRate'=>9.88],
                ['playTypeId'=>65,'playType'=>'SumNum','playBet'=>'10','state'=>1,'playRate'=>7.68],
                ['playTypeId'=>67,'playType'=>'SumNum','playBet'=>'12','state'=>1,'playRate'=>8.30],
                ['playTypeId'=>59,'playType'=>'SumNum','playBet'=>'4','state'=>1,'playRate'=>69.12],
                ['playTypeId'=>64,'playType'=>'SumNum','playBet'=>'9','state'=>1,'playRate'=>8.30],
                ['playTypeId'=>74,'playType'=>'SumBigSmall','playBet'=>'HL','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>66,'playType'=>'SumNum','playBet'=>'11','state'=>1,'playRate'=>7.68],
                ['playTypeId'=>76,'playType'=>'NumDiff2','playBet'=>'2BT','state'=>1,'playRate'=>6.91],
                ['playTypeId'=>82,'playType'=>'NumNear3All','playBet'=>'3LT','state'=>1,'playRate'=>8.64],
                ['playTypeId'=>81,'playType'=>'NumDiff3','playBet'=>'3BT','state'=>1,'playRate'=>34.56],
                ['playTypeId'=>72,'playType'=>'SumNum','playBet'=>'17','state'=>1,'playRate'=>69.12],
                ['playTypeId'=>80,'playType'=>'NumSame3All','playBet'=>'3TT','state'=>1,'playRate'=>34.56],
                ['playTypeId'=>63,'playType'=>'SumNum','playBet'=>'8','state'=>1,'playRate'=>9.88],
                ['playTypeId'=>75,'playType'=>'SumOddEven','playBet'=>'OE','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>73,'playType'=>'SumNum','playBet'=>'18','state'=>1,'playRate'=>207.36],
            ],
        ]);
    }

    if ($lottery === 'D5') {
        return array_merge($common, [
            'betScopes'    => [1, 10, 100, 1000],
            'betMultiples' => [1, 5, 10, 20, 50, 100],
            'rates' => [
                ['playTypeId'=>104,'playType'=>'FifthBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>100,'playType'=>'FourthBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>102,'playType'=>'FourthOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>101,'playType'=>'FourthOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>92,'playType'=>'SecondOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>107,'playType'=>'FifthOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>106,'playType'=>'FifthOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>103,'playType'=>'FifthNum','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
                ['playTypeId'=>109,'playType'=>'SumBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>91,'playType'=>'SecondOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>97,'playType'=>'ThirdOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>111,'playType'=>'SumOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>88,'playType'=>'SecondNum','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
                ['playTypeId'=>85,'playType'=>'FirstBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>110,'playType'=>'SumOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>87,'playType'=>'FirstOddEven','playBet'=>'E','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>108,'playType'=>'SumBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>93,'playType'=>'ThirdNum','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
                ['playTypeId'=>86,'playType'=>'FirstOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>99,'playType'=>'FourthBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>90,'playType'=>'SecondBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>98,'playType'=>'FourthNum','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
                ['playTypeId'=>89,'playType'=>'SecondBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>96,'playType'=>'ThirdOddEven','playBet'=>'O','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>95,'playType'=>'ThirdBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>105,'playType'=>'FifthBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>94,'playType'=>'ThirdBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>84,'playType'=>'FirstBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>83,'playType'=>'FirstNum','playBet'=>'0-9','state'=>1,'playRate'=>9.00],
            ],
        ]);
    }

    if ($lottery === 'MotoRace') {
        return array_merge($common, [
            'betScopes'    => [1, 10, 50, 100],
            'betMultiples' => [1, 5, 10, 20, 50, 100],
            'rates' => [
                ['playTypeId'=>140,'playType'=>'ThirdNum','playBet'=>'1-10','state'=>1,'playRate'=>9.80],
                ['playTypeId'=>132,'playType'=>'FirstOddEven','playBet'=>'Even','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>133,'playType'=>'FirstBigSmall','playBet'=>'Big','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>141,'playType'=>'ThirdOddEven','playBet'=>'Odd','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>136,'playType'=>'SecondOddEven','playBet'=>'Odd','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>144,'playType'=>'ThirdBigSmall','playBet'=>'Small','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>131,'playType'=>'FirstOddEven','playBet'=>'Odd','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>139,'playType'=>'SecondBigSmall','playBet'=>'Small','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>130,'playType'=>'FirstNum','playBet'=>'1-10','state'=>1,'playRate'=>9.80],
                ['playTypeId'=>142,'playType'=>'ThirdOddEven','playBet'=>'Even','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>138,'playType'=>'SecondBigSmall','playBet'=>'Big','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>143,'playType'=>'ThirdBigSmall','playBet'=>'Big','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>135,'playType'=>'SecondNum','playBet'=>'1-10','state'=>1,'playRate'=>9.80],
                ['playTypeId'=>134,'playType'=>'FirstBigSmall','playBet'=>'Small','state'=>1,'playRate'=>2.00],
                ['playTypeId'=>137,'playType'=>'SecondOddEven','playBet'=>'Even','state'=>1,'playRate'=>2.00],
            ],
        ]);
    }

    return null;
}

// Look up the payout multiplier for a given (lottery, betContent) pair.
// betContent examples: "BigSmall_Big", "Color_red", "Num_5", "SumBigSmall_Small",
// "FirstBigSmall_Big". We match on playType + playBet.
function saas_rate_for($lottery, $betContent) {
    $info = saas_game_info($lottery);
    if (!$info) return null;
    // Split "PlayType_Bet" — last underscore separates the bet value.
    $pos = strrpos($betContent, '_');
    if ($pos === false) return null;
    $playType = substr($betContent, 0, $pos);
    $bet      = substr($betContent, $pos + 1);

    foreach ($info['rates'] as $r) {
        if (strcasecmp($r['playType'], $playType) === 0) {
            // Num / 0-9 style: any digit bet maps to the "0-9"/"1-10" rate.
            if ($r['playBet'] === '0-9' || $r['playBet'] === '1-10') {
                if (is_numeric($bet)) return (float)$r['playRate'];
            }
            if (strcasecmp($r['playBet'], $bet) === 0) return (float)$r['playRate'];
        }
    }
    return null;
}
?>
