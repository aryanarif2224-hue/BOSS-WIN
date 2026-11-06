<?php
// SaaS Lottery Result Engine - Pure Upstream Mode
// WinGo (30S, 1M, 3M, 5M), K3, 5D, TRX, MotoRace
// All results are taken directly and authentically from upstream draw server (draw.ar-lottery01.com).
// Admin manual overrides in saas_manual_results are respected if set.
// No automatic outcome alteration or profit-based manipulation.

require_once __DIR__ . '/settle.php';
require_once __DIR__ . '/game_info.php';

function saas_candidate_matches_pred($pt, $pred, $cand) {
    $num = (int)$cand['number'];
    $colors = array_map('trim', explode(',', strtolower($cand['color'])));
    $pt = strtoupper(trim((string)$pt));
    $pred = strtoupper(trim((string)$pred));

    if ($pt === 'BS' || in_array($pred, ['BIG', 'SMALL'])) {
        $is_big = ($num >= 5);
        return ($pred === 'BIG') ? $is_big : !$is_big;
    }
    if ($pt === 'COLOR' || in_array($pred, ['GREEN', 'RED', 'VIOLET'])) {
        return in_array(strtolower($pred), $colors, true);
    }
    if ($pt === 'NUM' || is_numeric($pred)) {
        return $num === (int)$pred;
    }
    return false;
}

function saas_protect_and_optimize_draw($conn, $gameCode, $currentIssue) {
    if (!$conn || empty($gameCode) || empty($currentIssue)) return null;

    $gc = $conn->real_escape_string($gameCode);
    $iss = $conn->real_escape_string($currentIssue);

    // 1. Check if Admin has manually locked a result in saas_manual_results
    $man_q = $conn->query("SELECT number, color, premium, sum_val FROM saas_manual_results 
                           WHERE game_code='$gc' AND issue_number='$iss' AND state=1 LIMIT 1");
    if ($man_q && $man_q->num_rows > 0) {
        $mr = $man_q->fetch_assoc();
        $num = $conn->real_escape_string($mr['number']);
        $col = $conn->real_escape_string($mr['color']);
        $pre = $conn->real_escape_string($mr['premium']);
        $sm  = (int)$mr['sum_val'];
        $conn->query("INSERT INTO saas_results (game_code, issue_number, number, color, premium, sum_val, is_protected)
                      VALUES ('$gc', '$iss', '$num', '$col', '$pre', $sm, 1)
                      ON DUPLICATE KEY UPDATE number=VALUES(number), color=VALUES(color), premium=VALUES(premium), sum_val=VALUES(sum_val), is_protected=1");
        return $mr;
    }

    // Pure upstream mode: no auto-manipulation
    return null;
}
?>
