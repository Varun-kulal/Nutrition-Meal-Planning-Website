<?php
session_start();

// ------------------- DATABASE CONNECTION -------------------
$servername = "localhost";
$username = "root"; // change if different
$password = "";     // change if different
$dbname = "fitness_app"; // your database name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ------------------- LOGIN VALIDATION -------------------
$error = '';
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        // Verify hashed password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: index.php"); // Redirect to home page (which checks session)
            exit;
        } else {
            $error = "Wrong password!";
        }
    } else {
        $error = "Email not registered!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | NutriPlan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#121212;
  --card:#1c1c1c;
  --yellow:#FFC107;
  --text:#ffffff;
  --muted:#b0b0b0;
  --input-bg: #252525;
  --border: #333;
}

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:'Inter',sans-serif;
}

body{
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 20px;
}

.login-card{
  background:var(--card);
  width:100%;
  max-width:420px;
  padding:40px;
  border-radius:26px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.5);
  border: 1px solid #2a2a2a;
}

.logo{
  font-size:26px;
  font-weight:800;
  letter-spacing:1px;
  text-align:center;
  margin-bottom: 10px;
}

.logo span{
  color:var(--yellow);
}

.subtitle {
  text-align: center;
  color: var(--muted);
  font-size: 15px;
  margin-bottom: 30px;
}

.form-group{
  margin-bottom:20px;
}

.form-group label{
  display:block;
  margin-bottom:8px;
  font-size: 14px;
  font-weight:600;
  color: var(--muted);
}

.form-group input{
  width:100%;
  padding:14px 16px;
  border-radius:12px;
  border:1px solid var(--border);
  background: var(--input-bg);
  color: var(--text);
  font-size:15px;
  transition: 0.3s;
}

.form-group input:focus{
  outline:none;
  border-color:var(--yellow);
  box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.1);
}

.form-group input::placeholder {
  color: #666;
}

.login-btn{
  width:100%;
  padding:14px;
  background:var(--yellow);
  color:#000;
  border:none;
  border-radius:30px;
  font-size:16px;
  font-weight:700;
  cursor:pointer;
  transition:0.3s;
  margin-top: 10px;
}

.login-btn:hover{
  background: #ffca2c;
  transform: translateY(-2px);
}

.extra-links{
  margin-top:24px;
  text-align:center;
  font-size:14px;
  color: var(--muted);
}

.extra-links a{
  color:var(--yellow);
  text-decoration:none;
  font-weight:600;
}

.extra-links a:hover{
  text-decoration:underline;
}

.back-home{
  margin-top:20px;
  text-align:center;
}

.back-home a{
  color: #666;
  font-size:13px;
  text-decoration:none;
  transition: 0.3s;
}

.back-home a:hover{
  color: var(--text);
}

.error-msg {
    background: rgba(231, 76, 60, 0.1);
    color: #ff6b6b;
    padding: 12px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid rgba(231, 76, 60, 0.2);
}
</style>
</head>
<body>

<div class="login-card">
    <div class="logo">Nutri<span>Plan</span></div>
    <p class="subtitle">Welcome back! Please login to continue.</p>

    <?php if($error != ''): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <button class="login-btn" type="submit" name="login">Login</button>
    </form>

    <div class="extra-links">
        <p>New to NutriPlan? <a href="signup.php">Create Account</a></p>
    </div>

    <div class="back-home">
        <a href="index.php">← Back to Home</a>
    </div>
</div>

</body>
</html>
