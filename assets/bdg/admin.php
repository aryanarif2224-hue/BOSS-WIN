<?php
session_start();
include "db_conn.php";

// Admin Login Check
if(!isset($_SESSION['admin_logged'])) { 
    header("Location: login.php"); 
    exit; 
}

// 1. IP Approve karne ka logic (Status Active karna)
if(isset($_GET['approve'])){
    $id = mysqli_real_escape_string($conn, $_GET['approve']);
    mysqli_query($conn, "UPDATE approved_ips SET status='active' WHERE id='$id'");
    header("Location: admin.php?msg=IP Activated");
}

// 2. IP Block/Inactive karne ka logic
if(isset($_GET['block'])){
    $id = mysqli_real_escape_string($conn, $_GET['block']);
    mysqli_query($conn, "UPDATE approved_ips SET status='inactive' WHERE id='$id'");
    header("Location: admin.php?msg=IP Blocked");
}

// 3. IP Delete karne ka logic
if(isset($_GET['delete'])){
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM approved_ips WHERE id='$id'");
    header("Location: admin.php?msg=Deleted");
}

// 4. Manually IP Add karne ka logic
if(isset($_POST['add_ip'])){
    $ip = mysqli_real_escape_string($conn, $_POST['ip']);
    $domain = mysqli_real_escape_string($conn, $_POST['domain']);
    mysqli_query($conn, "INSERT INTO approved_ips (website_ip, domain_name, status) VALUES ('$ip', '$domain', 'active') ON DUPLICATE KEY UPDATE status='active'");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OPBANDA | Master Admin Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #0b0e14; color: #e2e8f0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: #151921; border-bottom: 1px solid #2d3748; padding: 15px; }
        .card { background: #151921; border: 1px solid #2d3748; border-radius: 12px; margin-bottom: 20px; }
        .table { color: #e2e8f0; vertical-align: middle; }
        .form-control { background: #0b0e14; border: 1px solid #2d3748; color: white; }
        .form-control:focus { background: #0b0e14; color: white; border-color: #4e8aff; box-shadow: none; }
        .btn-primary { background: linear-gradient(90deg, #4e8aff, #a855f7); border: none; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-microchip text-primary me-2"></i> OPBANDA <span class="text-muted">Master Panel</span></a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small">Welcome, Admin</span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card p-4">
                <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-plus-circle"></i> Manual Whitelist</h6>
                <form method="POST">
                    <input type="text" name="domain" class="form-control mb-2" placeholder="Website Name" required>
                    <input type="text" name="ip" class="form-control mb-3" placeholder="Server IP Address" required>
                    <button name="add_ip" class="btn btn-primary w-100">Approve IP</button>
                </form>
            </div>
            
            <div class="card p-3 text-center">
                <p class="mb-1 text-muted small">Master API Status</p>
                <h5 class="text-success fw-bold"><i class="fas fa-check-circle"></i> SYSTEM ONLINE</h5>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4">
                <h6 class="fw-bold mb-4 text-primary"><i class="fas fa-list"></i> Managed Web-Services</h6>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr class="text-muted small">
                                <th>DOMAIN / INFO</th>
                                <th>IP ADDRESS</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM approved_ips ORDER BY id DESC");
                            while($row = mysqli_fetch_assoc($res)){
                                $status_badge = ($row['status'] == 'active') ? 'badge-active' : 'badge-pending';
                                $toggle_link = ($row['status'] == 'active') ? "?block={$row['id']}" : "?approve={$row['id']}";
                                $toggle_icon = ($row['status'] == 'active') ? "fa-toggle-on text-success" : "fa-toggle-off text-muted";
                                
                                echo "<tr>
                                    <td><span class='fw-bold'>{$row['domain_name']}</span></td>
                                    <td><code>{$row['website_ip']}</code></td>
                                    <td><span class='badge $status_badge'>".strtoupper($row['status'])."</span></td>
                                    <td>
                                        <a href='$toggle_link' class='me-2 fs-5' title='Toggle Status'><i class='fas $toggle_icon'></i></a>
                                        <a href='?delete={$row['id']}' class='text-danger ms-2' onclick=\"return confirm('Delete this IP?')\"><i class='fas fa-trash-alt'></i></a>
                                    </td>
                                </tr>";
                            }
                            if(mysqli_num_rows($res) == 0) echo "<tr><td colspan='4' class='text-center text-muted p-4'>No IPs found in database.</td></tr>";
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>