<?php
session_start();
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$msg = "";

if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($pass, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $user;
            header("Location: admin.php");
            exit();
        } else {
            $msg = "Invalid password.";
        }
    } else {
        $msg = "Admin user not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | NutriAdmin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
body { background: #121212; color: #fff; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
.card { background: #1E1E1E; padding: 40px; border-radius: 16px; width: 100%; max-width: 400px; border: 1px solid #333; text-align: center; }
h2 { margin-bottom: 20px; color: #FFC107; text-transform: uppercase; letter-spacing: 1px; }
input { width: 100%; padding: 12px; margin: 10px 0; background: #252525; border: 1px solid #333; color: #fff; border-radius: 8px; box-sizing: border-box; }
button { width: 100%; padding: 12px; background: #FFC107; border: none; font-weight: 700; color: #000; border-radius: 8px; cursor: pointer; margin-top: 10px; }
button:hover { background: #FFB300; }
.link { margin-top: 15px; font-size: 14px; color: #888; }
.link a { color: #FFC107; text-decoration: none; }
.error { color: #ff6b6b; font-size: 14px; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="card">
    <h2>Admin Portal</h2>
    <p style="color:#888; margin-bottom:20px;">Secure Login Area</p>
    <?php if($msg): ?><div class="error"><?= $msg ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    <div class="link">Need access? <a href="admin_signup.php">Create Account</a></div>
</div>
</body>
</html>
