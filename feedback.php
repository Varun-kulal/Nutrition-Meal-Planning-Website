<?php
session_start();
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$msg = "";
$msg_type = "";

if (isset($_POST['submit_feedback'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

    if (!empty($name) && !empty($email) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, name, email, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $name, $email, $message);
        
        if ($stmt->execute()) {
            $msg = "Thank you! Your feedback has been submitted.";
            $msg_type = "success";
        } else {
            $msg = "Error submitting feedback: " . $conn->error;
            $msg_type = "error";
        }
    } else {
        $msg = "Please fill in all fields.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback | NutriPlan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css"> <!-- Reusing main site styles -->
<style>
    /* Specific styles for feedback form if needed, reusing main theme */
    body { background-color: #121212; color: #fff; font-family: 'Inter', sans-serif; }
    .feedback-container { max-width: 600px; margin: 80px auto; padding: 40px; background: #1c1c1c; border-radius: 16px; border: 1px solid #333; }
    .feedback-container h2 { text-align: center; margin-bottom: 30px; font-size: 32px; color: #fff; }
    .feedback-container span { color: #FFC107; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #b0b0b0; }
    .form-group input, .form-group textarea {
        width: 100%; padding: 12px; background: #252525; border: 1px solid #333; color: #fff; border-radius: 8px; font-family: inherit;
    }
    .form-group textarea { resize: vertical; min-height: 120px; }
    
    .btn-submit {
        width: 100%; padding: 14px; background: #FFC107; color: #000; border: none; border-radius: 30px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.2s;
    }
    .btn-submit:hover { background: #FFB300; transform: translateY(-2px); }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    .alert.success { background: rgba(16, 185, 129, 0.2); color: #10B981; border: 1px solid #10B981; }
    .alert.error { background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid #EF4444; }
</style>
</head>
<body>
    

    <!-- Reusing Navbar Structure -->
     <nav class="navbar">
        <div class="container">
            <div class="nav-brand"><h2>Nutri<span>Plan</span></h2></div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="dailychallenge.php">Daily Challenge</a></li>
                <li><a href="product.php">Supplement Order</a></li>
                <li><a href="personaldashbord.php">Dashboard</a></li>
                <li><a href="workout.php">Workout</a></li>
                <li><a href="feedback.php" class="active">Feedback</a></li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="user_logout.php" class="logout-btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="logout-btn">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="feedback-container">
        <h2>We Value Your <span>Feedback</span></h2>
        
        <?php if($msg): ?>
            <div class="alert <?= $msg_type ?>"><?= $msg ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" name="name" required placeholder="Enter your name" value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>">
            </div>
            
            <div class="form-group">
                <label>Your Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" required placeholder="Tell us what you think..."></textarea>
            </div>
            
            <button type="submit" name="submit_feedback" class="btn-submit">Submit Feedback</button>
        </form>
    </div>

</body>
</html>
