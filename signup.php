<?php
// Start session and connect to database
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_app";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if (isset($_POST['signup'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email exists
        $check_email = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($check_email);

        if ($result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            // Insert into database
            $sql = "INSERT INTO users (fullname, email, password) 
                    VALUES ('$fullname', '$email', '$hashed_password')";

            if ($conn->query($sql) === TRUE) {
                // Get the new user ID
                $user_id = $conn->insert_id;
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $fullname; // Assuming we want this too

                // Redirect to onboarding page
                header("Location: userinput.php");
                exit();
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | NutriPlan</title>
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

        .signup-card{
            background:var(--card);
            width:100%;
            max-width:460px;
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
            margin-bottom:18px;
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

        .signup-btn{
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

        .signup-btn:hover{
            background: #ffca2c;
            transform: translateY(-2px);
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
    </style>
</head>
<body>

<div class="signup-card">
    <div class="logo">Nutri<span>Plan</span></div>
    <p class="subtitle">Join today to start your journey.</p>

    <?php if (isset($error)) echo "<div class='error-msg'>$error</div>"; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="fullname" placeholder="John Doe" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@example.com" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Create a password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm your password" required>
        </div>

        <button class="signup-btn" name="signup">Create Account</button>
    </form>

    <div class="extra-links">
        Already have an account? <a href="login.php">Login</a>
    </div>

    <div class="back-home">
        <a href="index.php">← Back to Home</a>
    </div>
</div>

</body>
</html>
