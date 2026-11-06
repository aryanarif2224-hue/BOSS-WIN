<?php
/* _migrate.php — one-time schema setup for the ADMIN panel.
 * Run:  php /raxi/support/admin/_migrate.php
 * Safe to re-run (IF NOT EXISTS / ADD COLUMN guards). Never web-served (_ prefix denied).
 */
require_once '/raxi/bdg/conn.php';       // $conn (mysqli) bdgwin_ab888
$conn->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);

function run($conn, $label, $sql) {
    if ($conn->query($sql)) { echo "  ✓ $label\n"; }
    else { echo "  ✗ $label -> " . $conn->error . "\n"; }
}
function col_exists($conn, $table, $col) {
    $t = $conn->real_escape_string($table); $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

echo "== Admin panel migration ==\n";

/* ---- admins ------------------------------------------------------------- */
run($conn, 'support_admins', "
CREATE TABLE IF NOT EXISTS support_admins (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(64)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  name            VARCHAR(128) NOT NULL DEFAULT '',
  is_super        TINYINT(1)   NOT NULL DEFAULT 0,
  permissions     TEXT         NULL,               -- JSON array of permission keys
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  is_online       TINYINT(1)   NOT NULL DEFAULT 0,
  last_seen       DATETIME     NULL,
  failed_attempts INT          NOT NULL DEFAULT 0,
  locked_until    DATETIME     NULL,
  session_token   VARCHAR(64)  NULL,
  session_at      DATETIME     NULL,
  created_by      VARCHAR(128) NOT NULL DEFAULT '',
  created_at      DATETIME     NOT NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ---- admin audit log (kept separate from agent log for privacy) --------- */
run($conn, 'support_admin_log', "
CREATE TABLE IF NOT EXISTS support_admin_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  admin_id       INT          NOT NULL DEFAULT 0,
  admin_name     VARCHAR(128) NOT NULL DEFAULT '',
  action         VARCHAR(64)  NOT NULL DEFAULT '',
  target_user_id INT          NOT NULL DEFAULT 0,
  order_no       VARCHAR(64)  NOT NULL DEFAULT '',
  amount         DECIMAL(15,2) NOT NULL DEFAULT 0,
  detail         TEXT         NULL,
  ip             VARCHAR(64)  NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL,
  KEY k_admin (admin_id), KEY k_action (action), KEY k_user (target_user_id), KEY k_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ---- site settings (key/value) — created if the site has no config table  */
run($conn, 'support_site_settings', "
CREATE TABLE IF NOT EXISTS support_site_settings (
  skey       VARCHAR(64)  NOT NULL,
  sval       TEXT         NULL,
  updated_by VARCHAR(128) NOT NULL DEFAULT '',
  updated_at DATETIME     NULL,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ---- seed a super-admin if none exists ---------------------------------- */
$has = $conn->query("SELECT COUNT(*) c FROM support_admins")->fetch_assoc()['c'] ?? 0;
if ((int)$has === 0) {
    $u = 'admin';
    $p = 'Admin@BDG2026';                    // CHANGE THIS after first login
    $hash = password_hash($p, PASSWORD_DEFAULT);
    $perms = json_encode(['*']);             // super-admin: all perms (is_super also bypasses)
    $now = date('Y-m-d H:i:s');
    $nm = 'Super Admin';
    $st = $conn->prepare("INSERT INTO support_admins (username,password_hash,name,is_super,permissions,is_active,created_by,created_at)
                          VALUES (?,?,?,1,?,1,'seed',?)");
    $st->bind_param('sssss', $u, $hash, $nm, $perms, $now);
    if ($st->execute()) echo "  ✓ seeded super-admin  ($u / $p)  — CHANGE PASSWORD after login\n";
    else echo "  ✗ seed admin -> " . $conn->error . "\n";
    $st->close();
} else {
    echo "  · admins already present ($has) — no seed\n";
}

echo "Done.\n";
