<?php
session_start();

// Strict Admin Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch All Feedback
$feedback_list = $conn->query("SELECT * FROM feedback ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback Management | NutriAdmin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ... Reusing core variables ... */
:root {
    --bg-primary: #121212;
    --bg-secondary: #1A1A1A;
    --bg-card: #1E1E1E;
    --accent-yellow: #FFC107;
    --text-primary: #FFFFFF;
    --text-secondary: #B0B0B0;
    --table-header: #333;
}

/* GLOBAL RESET */
* { box-sizing: border-box; }

body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Inter', sans-serif;
    margin: 0;
}

/* SIDEBAR */
.sidebar {
    width: 260px;
    background: var(--bg-secondary);
    height: 100vh;
    padding: 30px;
    position: fixed;
    top: 0;
    left: 0;
    border-right: 1px solid rgba(255,255,255,0.05);
    z-index: 100;
    overflow-y: auto;
}

.brand { font-size: 24px; font-weight: 800; margin-bottom: 50px; color: #fff; display: flex; align-items: center; gap: 10px; }
.brand span { color: var(--accent-yellow); }

.menu { list-style: none; padding: 0; margin: 0; }
.menu li { margin-bottom: 8px; }
.menu a { text-decoration: none; color: var(--text-secondary); font-weight: 500; padding: 12px 16px; display: flex; align-items: center; gap: 10px; border-radius: 12px; transition: 0.2s; font-size: 15px; }
.menu a i { width: 20px; text-align: center; }
.menu a:hover, .menu a.active { background: rgba(255, 193, 7, 0.1); color: var(--accent-yellow); }

/* MAIN CONTENT */
.main-content {
    margin-left: 260px;
    padding: 40px 60px;
    width: calc(100% - 260px);
    min-height: 100vh;
}

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; }
.header h1 { font-size: 32px; font-weight: 800; margin: 0; }
.admin-badge { padding: 8px 16px; background: rgba(255, 193, 7, 0.15); color: var(--accent-yellow); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 30px; font-weight: 700; font-size: 13px; display: inline-block; }

/* TABLE STYLES */
.table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
th { background: var(--table-header); color: var(--text-secondary); font-size: 12px; text-transform: uppercase; }
tr:hover { background: rgba(255,255,255,0.02); }

.user-tag { background: rgba(33, 150, 243, 0.15); color: #2196F3; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; text-decoration: none; }
.user-tag:hover { text-decoration: underline; }

.logout-link { margin-top: auto; display: block; color: #ff6b6b; text-decoration: none; font-weight: 600; padding: 10px 15px; }

</style>
</head>
<body>

<div class="sidebar">
    <div class="brand">Nutri<span>Admin</span></div>
    <ul class="menu">
        <li><a href="admin.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="admin.php#orders"><i class="fas fa-box"></i> Orders</a></li>
        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="admin_product.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
        <li><a href="admin_feedback.php" class="active"><i class="fas fa-comments"></i> Feedback</a></li>
        <li><a href="index.php"><i class="fas fa-globe"></i> View Website</a></li>
    </ul>
    <a href="admin_logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <h1>User Feedback</h1>
        <div style="text-align: right;">
            <span class="admin-badge">Admin Access</span>
            <div style="margin-top: 5px; font-size: 12px; color: #888;">
                ID: <?= $_SESSION['admin_id'] ?> | <?= htmlspecialchars($_SESSION['admin_user']) ?>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $feedback_list->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td>
                        <?php if($row['user_id']): ?>
                            <a href="admin_user_detail.php?user_id=<?= $row['user_id'] ?>" class="user-tag">User #<?= $row['user_id'] ?></a>
                        <?php else: ?>
                            <span style="color: #666;">Guest</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td style="max-width: 400px; line-height: 1.5; color: #ddd;"><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                    <td><?= date('M d, Y, h:i A', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
