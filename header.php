<?php
// header.php - Reusable navigation header
if (!isset($_SESSION)) {
    session_start();
}

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Navigation Bar -->
<nav class="navbar">
    <div class="container">
        <div class="nav-brand">
            <h2>Nutri<span>Plan</span></h2>
        </div>
        <ul class="nav-menu">
            <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="dailychallenge.php"
                    class="<?php echo ($current_page == 'dailychallenge.php') ? 'active' : ''; ?>">Daily Challenge</a>
            </li>
            <li><a href="product.php" class="<?php echo ($current_page == 'product.php') ? 'active' : ''; ?>">Supplement
                    Order</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="personaldashbord.php"
                        class="<?php echo ($current_page == 'personaldashbord.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <?php endif; ?>

            <li><a href="workout.php"
                    class="<?php echo ($current_page == 'workout.php') ? 'active' : ''; ?>">Workout</a></li>
            <li><a href="feedback.php"
                    class="<?php echo ($current_page == 'feedback.php') ? 'active' : ''; ?>">Feedback</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="user_logout.php" class="logout-btn">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="logout-btn">Login</a></li>
            <?php endif; ?>
        </ul>
        <div class="mobile-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</nav>