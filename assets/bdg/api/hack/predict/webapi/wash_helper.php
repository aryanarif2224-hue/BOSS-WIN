<?php
/* wash_helper.php — DB-driven rebate ("wash") rate by VIP level.
 * Uses the `rebate_rules` table: the highest lv whose lvCount <= the user's VIP
 * level wins; falls back to a washRate band if the table is empty.
 */
if (!function_exists('sw_wash_rate')) {
    function sw_wash_rate($conn, $lvl) {
        $lvl = (int)$lvl;
        $q = $conn->query("SELECT washRate FROM rebate_rules WHERE lv <= $lvl AND washRate > 0 ORDER BY lv DESC LIMIT 1");
        if ($q && $r = $q->fetch_assoc()) {
            $rate = (float)$r['washRate'];
            if ($rate > 0) return $rate;
        }
        // fallback band (matches the original hard-coded logic)
        if ($lvl <= 2) return 0.05;
        if ($lvl <= 5) return 0.10;
        if ($lvl <= 8) return 0.15;
        if ($lvl == 9) return 0.20;
        return 0.30;
    }
}
?>
