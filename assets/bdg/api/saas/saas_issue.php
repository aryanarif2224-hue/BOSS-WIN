<?php
// Shreewin SaaS live-issue helper.
// The upstream draw server (draw.ar-lottery06.com) publishes the real current
// issue window per game. The copied bdg handlers used to read stale local
// issue tables; on Shreewin we prefer the upstream window so the frontend
// countdown and betting always match the real draws. Falls back to tables
// upstream-side (handlers keep their table logic when we return null).

require_once __DIR__ . '/../saas_lib.php';

// typeId (as the webapi handlers receive it) -> [family, gameCode]
function saas_typeid_game($typeId) {
    $map = [
        1  => ['WinGo', 'WinGo_30S'],
        2  => ['WinGo', 'WinGo_1M'],
        3  => ['WinGo', 'WinGo_3M'],
        4  => ['WinGo', 'WinGo_5M'],
        5  => ['D5',    'D5_1M'],
        6  => ['D5',    'D5_3M'],
        7  => ['D5',    'D5_5M'],
        8  => ['D5',    'D5_10M'],
        9  => ['K3',    'K3_1M'],
        10 => ['K3',    'K3_3M'],
        11 => ['K3',    'K3_5M'],
        12 => ['K3',    'K3_10M'],
        13 => ['MotoRace', 'MotoRace_1M'],
    ];
    return isset($map[$typeId]) ? $map[$typeId] : null;
}

// Returns the webapi issue payload from the upstream current window, or null.
// Payload keys match what the bdg handlers returned (issueNumber/startTime/
// endTime/serviceTime/intervalM) so the frontend is unchanged.
function saas_live_issue($typeId) {
    $g = saas_typeid_game($typeId);
    if ($g === null) return null;
    list($family, $gameCode) = $g;

    $body = saas_fetch_draw("$family/$gameCode.json", 2);
    if ($body === null) return null;
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['current']['issueNumber'])) return null;

    $cur = $j['current'];
    $startMs = isset($cur['startTime']) ? (int)$cur['startTime'] : 0;
    $endMs   = isset($cur['endTime'])   ? (int)$cur['endTime']   : 0;

    return [
        'issueNumber' => $cur['issueNumber'],
        'startTime'   => $startMs > 0 ? date('Y-m-d H:i:s', (int)($startMs / 1000)) : date('Y-m-d H:i:s'),
        'endTime'     => $endMs   > 0 ? date('Y-m-d H:i:s', (int)($endMs / 1000))   : date('Y-m-d H:i:s', time() + 60),
        'serviceTime' => date('Y-m-d H:i:s'),
        'intervalM'   => isset($j['intervalMinute']) ? (float)$j['intervalMinute'] : 1,
    ];
}
