<?php
/* _admin_cli.php — manage admin accounts from the shell (no web UI needed to bootstrap).
 * Usage:
 *   php _admin_cli.php list
 *   php _admin_cli.php add <username> <password> "<name>" [super|perm1,perm2,...]
 *   php _admin_cli.php passwd <username> <newpassword>
 *   php _admin_cli.php perms <username> "escalations,withdrawals,users"   (or * for all)
 *   php _admin_cli.php super <username> <0|1>
 *   php _admin_cli.php enable|disable|delete <username>
 * Never web-served (_ prefix denied by nginx).
 */
require_once __DIR__ . '/_core.php';   // gives $conn + PERMS

$argv0 = $argv; array_shift($argv0);
$cmd = $argv0[0] ?? 'list';
$valid = array_keys($GLOBALS['PERMS']);

function findAdmin($conn, $u) {
    $st = $conn->prepare("SELECT * FROM support_admins WHERE username=? LIMIT 1");
    $st->bind_param('s', $u); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r;
}

switch ($cmd) {
    case 'list': {
        $r = $conn->query("SELECT id,username,name,is_super,is_active,permissions,last_seen FROM support_admins ORDER BY id");
        printf("%-4s %-16s %-18s %-6s %-7s %s\n", 'ID', 'USER', 'NAME', 'SUPER', 'ACTIVE', 'PERMS');
        while ($x = $r->fetch_assoc()) {
            $p = $x['is_super'] ? 'ALL(super)' : implode(',', json_decode($x['permissions'] ?? '[]', true) ?: []);
            printf("%-4d %-16s %-18s %-6s %-7s %s\n", $x['id'], $x['username'], $x['name'],
                   $x['is_super'] ? 'yes' : '-', $x['is_active'] ? 'yes' : 'NO', $p);
        }
        break;
    }
    case 'add': {
        $u = $argv0[1] ?? ''; $p = $argv0[2] ?? ''; $name = $argv0[3] ?? $u; $opt = $argv0[4] ?? '';
        if ($u === '' || $p === '') exit("usage: add <username> <password> \"<name>\" [super|perm1,perm2]\n");
        if (findAdmin($conn, $u)) exit("✗ '$u' already exists\n");
        $isSuper = 0; $perms = [];
        if ($opt === 'super') { $isSuper = 1; $perms = ['*']; }
        elseif ($opt !== '') {
            foreach (explode(',', $opt) as $k) { $k = trim($k); if ($k === '*' || in_array($k, $valid, true)) $perms[] = $k; }
            if (!$perms) $perms = ['dashboard'];
        } else { $perms = ['dashboard']; }
        $hash = password_hash($p, PASSWORD_DEFAULT); $pj = json_encode($perms); $now = date('Y-m-d H:i:s');
        $st = $conn->prepare("INSERT INTO support_admins (username,password_hash,name,is_super,permissions,is_active,created_by,created_at) VALUES (?,?,?,?,?,1,'cli',?)");
        $st->bind_param('sssiss', $u, $hash, $name, $isSuper, $pj, $now);
        echo $st->execute() ? "✓ added '$u' (" . ($isSuper ? 'super' : implode(',', $perms)) . ")\n" : "✗ " . $conn->error . "\n";
        $st->close();
        break;
    }
    case 'passwd': {
        $u = $argv0[1] ?? ''; $p = $argv0[2] ?? '';
        if ($u === '' || $p === '') exit("usage: passwd <username> <newpassword>\n");
        if (!findAdmin($conn, $u)) exit("✗ no such admin\n");
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE support_admins SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE username=?");
        $st->bind_param('ss', $hash, $u); $st->execute(); $st->close();
        echo "✓ password updated for '$u'\n";
        break;
    }
    case 'perms': {
        $u = $argv0[1] ?? ''; $csv = $argv0[2] ?? '';
        if ($u === '') exit("usage: perms <username> \"a,b,c\"  (or *)\n");
        if (!findAdmin($conn, $u)) exit("✗ no such admin\n");
        $perms = [];
        foreach (explode(',', $csv) as $k) { $k = trim($k); if ($k === '*' || in_array($k, $valid, true)) $perms[] = $k; }
        $pj = json_encode($perms);
        $st = $conn->prepare("UPDATE support_admins SET permissions=? WHERE username=?");
        $st->bind_param('ss', $pj, $u); $st->execute(); $st->close();
        echo "✓ perms for '$u' = " . implode(',', $perms) . "\n";
        break;
    }
    case 'super': {
        $u = $argv0[1] ?? ''; $v = (int)($argv0[2] ?? 0) ? 1 : 0;
        if ($u === '') exit("usage: super <username> <0|1>\n");
        $st = $conn->prepare("UPDATE support_admins SET is_super=? WHERE username=?");
        $st->bind_param('is', $v, $u); $st->execute(); $st->close();
        echo "✓ '$u' is_super=$v\n";
        break;
    }
    case 'enable': case 'disable': {
        $u = $argv0[1] ?? ''; $v = $cmd === 'enable' ? 1 : 0;
        if ($u === '') exit("usage: $cmd <username>\n");
        $st = $conn->prepare("UPDATE support_admins SET is_active=?, session_token=NULL WHERE username=?");
        $st->bind_param('is', $v, $u); $st->execute(); $st->close();
        echo "✓ '$u' is_active=$v\n";
        break;
    }
    case 'delete': {
        $u = $argv0[1] ?? '';
        if ($u === '') exit("usage: delete <username>\n");
        $st = $conn->prepare("DELETE FROM support_admins WHERE username=?");
        $st->bind_param('s', $u); $st->execute(); $st->close();
        echo "✓ deleted '$u'\n";
        break;
    }
    default:
        echo "commands: list | add | passwd | perms | super | enable | disable | delete\n";
        echo "perm keys: " . implode(', ', $valid) . "\n";
}
