<?php
session_start();

/* ---------- CHECK LOGIN ---------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ---------- DATABASE CONNECTION ---------- */
$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/* ---------- USER ID FROM SESSION ---------- */
$user_id = $_SESSION['user_id'];

/* ---------- FETCH TARGET DATA ---------- */
$sql = "SELECT * FROM usersinput WHERE user_id = ? LIMIT 1";
// Note: In a real app we'd link usersinput to users table properly. 
// For this demo, assuming user_id works.
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: userinput.php");
    exit();
}
$user = $result->fetch_assoc();

/* ---------- FETCH JOIN DATE & PROGRESS-BASED CHALLENGE DAY ---------- */
$u_sql = "SELECT created_at FROM users WHERE id = ? LIMIT 1";
$u_stmt = $conn->prepare($u_sql);
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result()->fetch_assoc();
$join_date = new DateTime($u_res['created_at']);
$join_date->setTime(0, 0, 0); // Normalize
$today = new DateTime();
$today->setTime(0, 0, 0); // Normalize
$diff = $join_date->diff($today);
$time_based_day = $diff->days + 1;
$u_stmt->close();

// Check progress-based day (highest day with 7+ meals completed)
$p_stmt = $conn->prepare("SELECT day_number, completed_meals, custom_meals FROM user_progress WHERE user_id = ? ORDER BY day_number ASC");
$p_stmt->bind_param("i", $user_id);
$p_stmt->execute();
$p_res = $p_stmt->get_result();

$max_completed_day = 0;
while ($row = $p_res->fetch_assoc()) {
    $meals_done = json_decode($row['completed_meals'], true) ?? [];
    $custom_meals = json_decode($row['custom_meals'], true) ?? [];
    
    $day_complete = true;
    // Check all 7 slots (0 to 6)
    for ($i = 0; $i < 7; $i++) {
        $slot_done = false;
        
        // 1. Check default meal
        if (is_array($meals_done) && in_array($i, $meals_done)) {
            $slot_done = true;
        } 
        
        // 2. Check replacements (if default not done)
        if (!$slot_done && is_array($custom_meals)) {
            foreach ($custom_meals as $cm) {
                if (isset($cm['replaces']) && $cm['replaces'] == $i && isset($cm['isDone']) && $cm['isDone']) {
                    $slot_done = true;
                    break;
                }
            }
        }

        if (!$slot_done) {
            $day_complete = false;
            break;
        }
    }

    if ($day_complete) {
        $max_completed_day = max($max_completed_day, intval($row['day_number']));
    }
}
$p_stmt->close();

$progress_based_day = $max_completed_day + 1;
// Use ONLY time-based day (no max logic)
$challenge_day = $time_based_day;

if ($challenge_day > 30) $challenge_day = 30;

/* ---------- FETCH PROGRESS DATA (SPECIFIC CHALLENGE DAY) ---------- */
// Show progress only for the current challenge day (Day 1, Day 2, etc.)
$prog_sql = "SELECT 
                total_calories, 
                total_protein, 
                total_carbs, 
                total_fat,
                water_liters
             FROM user_progress 
             WHERE user_id = ? AND day_number = ?";
$prog_stmt = $conn->prepare($prog_sql);
$prog_stmt->bind_param("ii", $user_id, $challenge_day);
$prog_stmt->execute();
$prog_result = $prog_stmt->get_result();

// If no progress for specific challenge day, try fetching by current date as fallback
if ($prog_result->num_rows === 0) {
    $today_sql = "SELECT total_calories, total_protein, total_carbs, total_fat, water_liters 
                  FROM user_progress 
                  WHERE user_id = ? AND date_completed = CURDATE() LIMIT 1";
    $today_stmt = $conn->prepare($today_sql);
    $today_stmt->bind_param("i", $user_id);
    $today_stmt->execute();
    $prog_result = $today_stmt->get_result();
}

// Default values if no progress found for THIS day
$consumed = [
    'calories' => 0,
    'protein' => 0,
    'carbs' => 0,
    'fat' => 0,
    'water' => 0
];
$days_logged = 0;

if ($prog_result->num_rows > 0) {
    $progress = $prog_result->fetch_assoc();
    $consumed['calories'] = $progress['total_calories'] ?? 0;
    $consumed['protein'] = $progress['total_protein'] ?? 0;
    $consumed['carbs'] = $progress['total_carbs'] ?? 0;
    $consumed['fat'] = $progress['total_fat'] ?? 0;
    $consumed['water'] = $progress['water_liters'] ?? 0;
}

// Fetch total days logged across all time for "Journey" stats
$total_days_sql = "SELECT COUNT(DISTINCT date_completed) as total_days FROM user_progress WHERE user_id = ?";
$td_stmt = $conn->prepare($total_days_sql);
$td_stmt->bind_param("i", $user_id);
$td_stmt->execute();
$total_days_res = $td_stmt->get_result()->fetch_assoc();
$days_logged = $total_days_res['total_days'] ?? 0;
$td_stmt->close();

$stmt->close();
$prog_stmt->close();

/* ---------- FETCH GRAPH DATA (BY CHALLENGE DAY) ---------- */
// Get existing data
$graph_sql = "SELECT day_number, total_calories, total_protein, total_carbs, total_fat, water_liters 
              FROM user_progress 
              WHERE user_id = ? 
              ORDER BY day_number ASC";
$graph_stmt = $conn->prepare($graph_sql);
$graph_stmt->bind_param("i", $user_id);
$graph_stmt->execute();
$graph_res = $graph_stmt->get_result();

$existing_data = [];
while ($row = $graph_res->fetch_assoc()) {
    $existing_data[$row['day_number']] = $row;
}
$graph_stmt->close();

$graph_labels = [];
$graph_data = [
    'calories' => [],
    'protein' => [],
    'carbs' => [],
    'fats' => [],
    'water' => []
];

// Only show up to the current challenge day
$max_display_day = $challenge_day;
if ($max_display_day > 30)
    $max_display_day = 30;

for ($i = 1; $i <= $max_display_day; $i++) {
    $graph_labels[] = "Day " . $i;
    if (isset($existing_data[$i])) {
        $graph_data['calories'][] = $existing_data[$i]['total_calories'];
        $graph_data['protein'][] = $existing_data[$i]['total_protein'];
        $graph_data['carbs'][] = $existing_data[$i]['total_carbs'];
        $graph_data['fats'][] = $existing_data[$i]['total_fat'];
        $graph_data['water'][] = $existing_data[$i]['water_liters'];
    } else {
        // Day has no data collected
        $graph_data['calories'][] = 0;
        $graph_data['protein'][] = 0;
        $graph_data['carbs'][] = 0;
        $graph_data['fats'][] = 0;
        $graph_data['water'][] = 0;
    }
}

$conn->close();

/* ---------- CALCULATE PERCENTAGES ---------- */
function calcPct($consumed, $target)
{
    if ($target <= 0)
        return 0;
    $pct = ($consumed / $target) * 100;
    return min($pct, 100); // Max 100% for bar visualization
}

$pct_cal = calcPct($consumed['calories'], $user['daily_calories']);
$pct_pro = calcPct($consumed['protein'], $user['protein_g']);
$pct_carb = calcPct($consumed['carbs'], $user['carbs_g']);
$pct_fat = calcPct($consumed['fat'], $user['fats_g']);

/* ---------- DAILY TIPS GENERATOR ---------- */
$tips = [
    "Hydration is key! Drink at least 3-4 liters of water daily to keep your metabolism active.",
    "Protein isn't just for muscle. It keeps you full longer, reducing cravings.",
    "Eat more fiber. Vegetables and whole grains helps digestion and blood sugar control.",
    "Sleep is when your muscles grow. Aim for 7-9 hours of quality sleep every night.",
    "Consistency > Intensity. A mediocre workout you do is better than a perfect one you skip.",
    "Don't fear fats. Healthy fats (avocado, nuts) are essential for hormone production.",
    "Eat slowly. It takes 20 minutes for your brain to realize you're full.",
    "Pre-workout meals fuel performance. Carbs + Protein 1 hour before training is ideal.",
    "Recovery matters. Stretch and foam roll to prevent injuries and improve mobility.",
    "Sugar crash is real. Avoid high-sugar snacks to maintain steady energy levels."
];
$random_keys = array_rand($tips, 3);
$selected_tips = [$tips[$random_keys[0]], $tips[$random_keys[1]], $tips[$random_keys[2]]];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NutriPlan</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ==================== COPIED FROM STYLES.CSS (NAVBAR) ==================== */
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1A1A1A;
            --accent-yellow: #FFC107;
            --text-primary: #FFFFFF;
            --text-secondary: #B0B0B0;
            --success: #4CAF50;
            --danger: #EF4444;
            --transition-fast: 0.2s ease;
            --transition-medium: 0.3s ease;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            min-height: 100vh;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 60px;
        }

        .highlight {
            color: var(--accent-yellow);
        }

        /* Navbar specific container to match index.html layout */
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar {
            background-color: var(--bg-secondary);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 193, 7, 0.1);
        }

        .nav-brand h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .nav-brand span {
            color: var(--accent-yellow);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 40px;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-menu li a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition-fast);
            position: relative;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            color: var(--accent-yellow);
        }

        .logout-btn {
            background-color: transparent;
            border: 2px solid var(--accent-yellow);
            padding: 8px 24px !important;
            border-radius: 6px;
            color: var(--accent-yellow) !important;
            transition: var(--transition-fast);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }

        .logout-btn:hover {
            background-color: var(--accent-yellow);
            color: var(--bg-primary) !important;
        }

        .mobile-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
        }

        .mobile-toggle span {
            width: 25px;
            height: 3px;
            background-color: var(--text-primary);
            transition: var(--transition-fast);
        }

        /* ==================== DASHBOARD SPECIFIC STYLES ==================== */
        .dashboard-container {
            padding: 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 40px;
        }

        .hero-dashboard h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 10px;
            margin-top: 0;
        }

        .hero-dashboard span {
            color: var(--accent-yellow);
        }

        .hero-dashboard p {
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0;
        }

        .recalc-btn {
            background: var(--accent-yellow);
            color: #000;
            border: none;
            padding: 12px 24px;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .recalc-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            margin-bottom: 50px;
        }

        .metric-card {
            background: #1c1c1c;
            /* Panel color */
            padding: 24px;
            border-radius: 20px;
            border: 1px solid #333;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            border-color: #444;
        }

        .metric-card.goal-met {
            border-color: var(--success);
            background: linear-gradient(145deg, #1c1c1c, rgba(76, 175, 80, 0.1));
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .metric-card h4 {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0;
        }

        .value-display {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 15px;
        }

        .current-val {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .target-val {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .p-bar-bg {
            width: 100%;
            height: 12px;
            background: #333;
            border-radius: 6px;
            overflow: hidden;
        }

        .p-bar-fill {
            height: 100%;
            background: var(--accent-yellow);
            border-radius: 6px;
            transition: width 1s ease-in-out;
        }

        .metric-card.goal-met .p-bar-fill {
            background: var(--success);
        }

        .metric-card.goal-met .current-val {
            color: var(--success);
        }

        /* GOAL EXCEEDED (RED) */
        .metric-card.goal-exceeded {
            border-color: var(--danger);
            background: linear-gradient(145deg, #1c1c1c, rgba(239, 68, 68, 0.1));
        }

        .metric-card.goal-exceeded .p-bar-fill {
            background: var(--danger);
        }

        .metric-card.goal-exceeded .current-val {
            color: var(--danger);
        }

        .limit-badge {
            display: none;
            background: var(--danger);
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
            animation: pulse-red 2s infinite;
        }

        .goal-exceeded .limit-badge {
            display: inline-block;
        }

        @keyframes pulse-red {
            0% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                transform: scale(1.05);
                opacity: 0.8;
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .section-panel {
            background: #1c1c1c;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .section-panel::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-yellow), transparent);
            opacity: 0.5;
        }

        .section-panel h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0;
        }

        .chart-controls {
            display: flex;
            gap: 10px;
        }

        .chart-btn {
            background: #252525;
            border: 1px solid #333;
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            cursor: pointer;
            transition: 0.3s;
        }

        .chart-btn.active {
            background: var(--accent-yellow);
            color: #000;
            border-color: var(--accent-yellow);
        }

        .tips-grid {
            display: grid;
            gap: 15px;
        }

        .tip-card {
            background: #252525;
            padding: 20px;
            border-radius: 16px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .tip-card:hover {
            border-color: rgba(255, 193, 7, 0.3);
            background: #2a2a2a;
        }

        .tip-icon {
            font-size: 20px;
        }

        /* Footer */
        .footer {
            background-color: var(--bg-secondary);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(255, 193, 7, 0.1);
            margin-top: 60px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }

        .footer-column h3 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--accent-yellow);
        }

        .footer-column h4 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .footer-column p {
            color: var(--text-secondary);
            line-height: 1.8;
            font-size: 15px;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: 0.2s ease;
            font-size: 15px;
        }

        .footer-column ul li a:hover {
            color: var(--accent-yellow);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 16px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background-color: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: 0.2s ease;
        }

        .social-links a:hover {
            background-color: var(--accent-yellow);
            color: var(--bg-primary);
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-bottom p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }

        @media(max-width:1100px) {
            .dashboard-grid {
                grid-template-columns: 1fr 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:900px) {
            .nav-menu {
                display: none;
            }

            .mobile-toggle {
                display: flex;
            }
        }

        @media(max-width:600px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 20px;
            }

            .dashboard-container {
                padding: 40px 20px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-container">
        <!-- HEADER -->
        <div class="header-section">
            <div class="hero-dashboard">
                <h1>Welcome back, <span><?= htmlspecialchars($user['name']) ?></span></h1>
                <p>Track your transformation progress for <strong>Day <?= $challenge_day ?></strong> | Journey:
                    <strong><?= ($user['target_duration'] ?? 3) >= 12 ? '1 Year' : ($user['target_duration'] ?? 3) . ' Months' ?></strong>
                </p>
                <!-- Debug: Day=<?= $challenge_day ?>, User=<?= $user_id ?> -->
            </div>
            <a href="userinput.php" class="recalc-btn"><i class="fas fa-calculator"></i> Re-Calculate</a>
        </div>

        <!-- METRICS GRID -->
        <!-- Logic: Visually reflect daily progress. Green highlight if target met (>= 100%) -->
        <div class="dashboard-grid">

            <!-- Calories -->
            <?php
            $cal_class = '';
            if ($consumed['calories'] > $user['daily_calories'])
                $cal_class = 'goal-exceeded';
            elseif ($consumed['calories'] >= $user['daily_calories'])
                $cal_class = 'goal-met';

            $cal_icon_color = 'var(--accent-yellow)';
            if ($cal_class == 'goal-exceeded')
                $cal_icon_color = 'var(--danger)';
            elseif ($cal_class == 'goal-met')
                $cal_icon_color = 'var(--success)';
            ?>
            <div class="metric-card <?= $cal_class ?>">
                <div class="metric-header">
                    <h4>Calories</h4>
                    <i class="fas fa-fire" style="color: <?= $cal_icon_color ?>"></i>
                </div>
                <div class="value-display">
                    <div>
                        <span class="current-val"><?= $consumed['calories'] ?></span>
                        <div class="limit-badge">Limit Exceeded</div>
                    </div>
                    <span class="target-val">/ <?= number_format($user['daily_calories']) ?> kcal</span>
                </div>
                <div class="p-bar-bg">
                    <div class="p-bar-fill" style="width: <?= $pct_cal ?>%;"></div>
                </div>
            </div>

            <!-- Protein -->
            <?php
            $pro_class = '';
            if ($consumed['protein'] > $user['protein_g'])
                $pro_class = 'goal-exceeded';
            elseif ($consumed['protein'] >= $user['protein_g'])
                $pro_class = 'goal-met';

            $pro_icon_color = 'var(--accent-yellow)';
            if ($pro_class == 'goal-exceeded')
                $pro_icon_color = 'var(--danger)';
            elseif ($pro_class == 'goal-met')
                $pro_icon_color = 'var(--success)';
            ?>
            <div class="metric-card <?= $pro_class ?>">
                <div class="metric-header">
                    <h4>Protein</h4>
                    <i class="fas fa-drumstick-bite" style="color: <?= $pro_icon_color ?>"></i>
                </div>
                <div class="value-display">
                    <div>
                        <span class="current-val"><?= $consumed['protein'] ?></span>
                        <div class="limit-badge">Limit Exceeded</div>
                    </div>
                    <span class="target-val">/ <?= number_format($user['protein_g']) ?> g</span>
                </div>
                <div class="p-bar-bg">
                    <div class="p-bar-fill" style="width: <?= $pct_pro ?>%;"></div>
                </div>
            </div>

            <!-- Carbs -->
            <?php
            $carb_class = '';
            if ($consumed['carbs'] > $user['carbs_g'])
                $carb_class = 'goal-exceeded';
            elseif ($consumed['carbs'] >= $user['carbs_g'])
                $carb_class = 'goal-met';

            $carb_icon_color = 'var(--accent-yellow)';
            if ($carb_class == 'goal-exceeded')
                $carb_icon_color = 'var(--danger)';
            elseif ($carb_class == 'goal-met')
                $carb_icon_color = 'var(--success)';
            ?>
            <div class="metric-card <?= $carb_class ?>">
                <div class="metric-header">
                    <h4>Carbs</h4>
                    <i class="fas fa-bread-slice" style="color: <?= $carb_icon_color ?>"></i>
                </div>
                <div class="value-display">
                    <div>
                        <span class="current-val"><?= $consumed['carbs'] ?></span>
                        <div class="limit-badge">Limit Exceeded</div>
                    </div>
                    <span class="target-val">/ <?= number_format($user['carbs_g']) ?> g</span>
                </div>
                <div class="p-bar-bg">
                    <div class="p-bar-fill" style="width: <?= $pct_carb ?>%;"></div>
                </div>
            </div>

            <!-- Fats -->
            <?php
            $fat_class = '';
            if ($consumed['fat'] > $user['fats_g'])
                $fat_class = 'goal-exceeded';
            elseif ($consumed['fat'] >= $user['fats_g'])
                $fat_class = 'goal-met';

            $fat_icon_color = 'var(--accent-yellow)';
            if ($fat_class == 'goal-exceeded')
                $fat_icon_color = 'var(--danger)';
            elseif ($fat_class == 'goal-met')
                $fat_icon_color = 'var(--success)';
            ?>
            <div class="metric-card <?= $fat_class ?>">
                <div class="metric-header">
                    <h4>Fats</h4>
                    <i class="fas fa-cheese" style="color: <?= $fat_icon_color ?>"></i>
                </div>
                <div class="value-display">
                    <div>
                        <span class="current-val"><?= $consumed['fat'] ?></span>
                        <div class="limit-badge">Limit Exceeded</div>
                    </div>
                    <span class="target-val">/ <?= number_format($user['fats_g']) ?> g</span>
                </div>
                <div class="p-bar-bg">
                    <div class="p-bar-fill" style="width: <?= $pct_fat ?>%;"></div>
                </div>
            </div>

            <!-- Hydration (Water) -->
            <?php
            $water_goal = 3.0; // 3 Liters
            $pct_water = calcPct($consumed['water'], $water_goal);
            $water_class = '';
            if ($consumed['water'] >= $water_goal)
                $water_class = 'goal-met';
            ?>
            <div class="metric-card <?= $water_class ?>" style="border-left: 4px solid #3b82f6;">
                <div class="metric-header">
                    <h4>Hydration</h4>
                    <i class="fas fa-droplet" style="color: #3b82f6"></i>
                </div>
                <div class="value-display">
                    <div>
                        <span class="current-val"><?= number_format($consumed['water'], 1) ?></span>
                    </div>
                    <span class="target-val">/ <?= number_format($water_goal, 1) ?> L</span>
                </div>
                <div class="p-bar-bg">
                    <div class="p-bar-fill" style="width: <?= $pct_water ?>%; background: #3b82f6;"></div>
                </div>
            </div>

        </div>

        <!-- CONTENT AREA -->
        <div class="content-grid">
            <div class="section-panel">
                <h3>
                    <span><i class="fas fa-chart-line" id="chartIcon" style="color:var(--accent-yellow)"></i> Challenge
                        Progress
                        (Daily Summary)</span>
                    <div class="chart-controls">
                        <button class="chart-btn active" onclick="updateChartType('daily')">Daily</button>
                        <button class="chart-btn" onclick="updateChartType('macros')">Macros</button>
                    </div>
                </h3>
                <div style="height: 550px; position: relative; margin-top: 10px;">
                    <div id="lineChartContainer" style="height: 100%;">
                        <canvas id="nutritionChart"></canvas>
                    </div>
                    <div id="pieChartContainer" style="display:none; height: 100%;">
                        <canvas id="macroPieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="section-panel" style="grid-column: span 1;">
                <h3><span><i class="fas fa-lightbulb" style="color:var(--accent-yellow)"></i> Daily Tips</span></h3>
                <div class="tips-grid">
                    <?php
                    $icons = ['🔥', '💡', '🥗'];
                    foreach ($selected_tips as $index => $tip):
                        ?>
                        <div class="tip-card">
                            <div class="tip-icon"><?= $icons[$index] ?></div>
                            <div>
                                <div style="font-weight: 700; color: #fff;">Tip #<?= $index + 1 ?></div>
                                <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.4;">
                                    "<?= $tip ?>"
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Hydration Trend (Separate Panel) -->
            <div class="section-panel" style="grid-column: span 2;">
                <h3>
                    <span><i class="fas fa-faucet-drip" style="color:#3b82f6; margin-right: 10px;"></i> Hydration
                        Journey</span>
                    <span style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Weekly Intake Trend
                        (Liters)</span>
                </h3>
                <div style="height: 350px; position: relative; margin-top: 10px;">
                    <canvas id="hydrationJourneyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Mobile toggle interaction (simple toggle)
        document.querySelector('.mobile-toggle').addEventListener('click', function () {
            const navMenu = document.querySelector('.nav-menu');
            if (navMenu.style.display === 'flex') {
                navMenu.style.display = 'none';
            } else {
                navMenu.style.display = 'flex';
                navMenu.style.flexDirection = 'column';
                navMenu.style.position = 'absolute';
                navMenu.style.top = '70px';
                navMenu.style.left = '0';
                navMenu.style.width = '100%';
                navMenu.style.background = '#1A1A1A';
                navMenu.style.padding = '20px';
                navMenu.style.boxShadow = '0 10px 20px rgba(0,0,0,0.5)';
            }
        });

        // Professional Nutrition Progress Chart
        const canvas = document.getElementById('nutritionChart');
        const ctx = canvas.getContext('2d');
        const labels = <?php echo json_encode($graph_labels); ?>;
        const graphData = <?php echo json_encode($graph_data); ?>;
        let chart;

        // Custom Shadow Plugin
        const shadowPlugin = {
            id: 'shadow',
            beforeDraw: (chart) => {
                const ctx = chart.ctx;
                const datasets = chart.data.datasets;
                datasets.forEach((dataset, i) => {
                    if (dataset.shadowColor) {
                        ctx.shadowColor = dataset.shadowColor;
                        ctx.shadowBlur = dataset.shadowBlur || 0;
                        ctx.shadowOffsetX = 0;
                        ctx.shadowOffsetY = 4;
                    }
                });
            },
            afterDraw: (chart) => {
                const ctx = chart.ctx;
                ctx.shadowColor = 'transparent';
                ctx.shadowBlur = 0;
            }
        };

        function initChart() {
            if (labels.length > 0) {
                // Create Gradients
                const createGradient = (color1, color2) => {
                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, color1);
                    gradient.addColorStop(1, color2);
                    return gradient;
                };

                const calGradient = createGradient('rgba(255, 193, 7, 0.4)', 'rgba(255, 193, 7, 0)');
                const proGradient = createGradient('rgba(76, 175, 80, 0.2)', 'rgba(76, 175, 80, 0)');
                const carbGradient = createGradient('rgba(33, 150, 243, 0.2)', 'rgba(33, 150, 243, 0)');
                const fatGradient = createGradient('rgba(156, 39, 176, 0.2)', 'rgba(156, 39, 176, 0)');

                const config = {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Calories (kcal)',
                                data: graphData.calories,
                                borderColor: '#FFC107',
                                backgroundColor: calGradient,
                                yAxisID: 'yCalories',
                                borderWidth: 4,
                                fill: true,
                                tension: 0.5,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 5,
                                pointBackgroundColor: '#FFC107',
                                pointBorderColor: '#1A1A1A',
                                pointBorderWidth: 2,
                                pointHoverRadius: 8,
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round',
                                shadowColor: 'rgba(255, 193, 7, 0.5)',
                                shadowBlur: 10
                            },
                            {
                                label: 'Protein (g)',
                                data: graphData.protein,
                                borderColor: '#4CAF50',
                                backgroundColor: proGradient,
                                yAxisID: 'yMacros',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.5,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 0,
                                pointHoverRadius: 5
                            },
                            {
                                label: 'Carbs (g)',
                                data: graphData.carbs,
                                borderColor: '#2196F3',
                                backgroundColor: carbGradient,
                                yAxisID: 'yMacros',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.5,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 0,
                                pointHoverRadius: 5
                            },
                            {
                                label: 'Fat (g)',
                                data: graphData.fats,
                                borderColor: '#9C27B0',
                                backgroundColor: fatGradient,
                                yAxisID: 'yMacros',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.5,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 0,
                                pointHoverRadius: 5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1500,
                            easing: 'easeInOutQuart'
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: {
                                    color: '#B0B0B0',
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 30,
                                    font: { size: 14, weight: '700', family: 'Inter' }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1A1A1A',
                                titleColor: '#FFFFFF',
                                titleFont: { size: 16, weight: '800' },
                                bodyColor: '#B0B0B0',
                                bodyFont: { size: 14 },
                                borderColor: '#333',
                                borderWidth: 1,
                                padding: 18,
                                cornerRadius: 15,
                                displayColors: true,
                                boxPadding: 10,
                                usePointStyle: true,
                                callbacks: {
                                    label: function (context) {
                                        let label = context.dataset.label || '';
                                        if (label) label += ': ';
                                        if (context.parsed.y !== null) {
                                            label += context.parsed.y.toLocaleString() + (context.dataset.yAxisID === 'yCalories' ? ' kcal' : ' g');
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            yCalories: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Daily Calories',
                                    color: '#FFC107',
                                    font: { size: 14, weight: '800' }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    color: '#B0B0B0',
                                    font: { size: 13, weight: '600' }
                                }
                            },
                            yMacros: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Macros (g)',
                                    color: '#808080',
                                    font: { size: 14, weight: '800' }
                                },
                                grid: {
                                    drawOnChartArea: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    color: '#B0B0B0',
                                    font: { size: 13, weight: '600' }
                                }
                            },

                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#B0B0B0',
                                    font: { size: 13, weight: '600' }
                                }
                            }
                        }
                    }
                };

                chart = new Chart(ctx, {
                    ...config,
                    plugins: [shadowPlugin, ...Object.values(config.plugins || {})]
                });
            } else {
                ctx.font = '16px Inter';
                ctx.fillStyle = '#666';
                ctx.textAlign = 'center';
                ctx.fillText('Complete daily challenges to see your professional progress chart!', canvas.width / 2, canvas.height / 2);
            }
        }

        // Macro Pie Chart Initialization
        let macroChart;
        function initMacroChart() {
            const pieCtx = document.getElementById('macroPieChart').getContext('2d');
            const consumedMacros = {
                protein: <?= (float) $consumed['protein'] ?>,
                carbs: <?= (float) $consumed['carbs'] ?>,
                fat: <?= (float) $consumed['fat'] ?>,
                calories: <?= (float) $consumed['calories'] ?>
            };

            const totalMacros = consumedMacros.protein + consumedMacros.carbs + consumedMacros.fat;

            if (totalMacros > 0) {
                macroChart = new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Protein', 'Carbs', 'Fat'],
                        datasets: [{
                            data: [consumedMacros.protein, consumedMacros.carbs, consumedMacros.fat],
                            backgroundColor: ['#4CAF50', '#2196F3', '#9C27B0'],
                            borderColor: '#1c1c1c',
                            borderWidth: 8,
                            hoverOffset: 20,
                            borderRadius: 10
                        }]
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw: (chart) => {
                            const { ctx, width, height } = chart;
                            ctx.save();

                            const centerX = width / 2;
                            const centerY = height / 2;

                            // Draw "CALORIES" label
                            ctx.font = '700 12px Inter';
                            ctx.fillStyle = '#B0B0B0'; // Lighter gray for better visibility
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText('TOTAL CALORIES', centerX, centerY - 25);

                            // Draw Value
                            ctx.font = '800 56px Inter'; // Larger font
                            ctx.fillStyle = '#FFFFFF';
                            ctx.shadowColor = 'rgba(255, 255, 255, 0.4)';
                            ctx.shadowBlur = 10;
                            ctx.fillText(Math.round(consumedMacros.calories).toLocaleString(), centerX, centerY + 15);

                            ctx.restore();
                        }
                    }],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '80%', // Thinner ring for better center focus
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#B0B0B0',
                                    usePointStyle: true,
                                    padding: 20,
                                    font: { size: 14, weight: '700' }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1A1A1A',
                                padding: 15,
                                bodyFont: { size: 14, weight: '600' },
                                cornerRadius: 10,
                                displayColors: true,
                                boxPadding: 8,
                                callbacks: {
                                    label: (ctx) => {
                                        const calorieVal = ctx.label === 'Fat' ? ctx.raw * 9 : ctx.raw * 4;
                                        return ` ${ctx.label}: ${ctx.raw}g (${Math.round(calorieVal)} kcal)`;
                                    }
                                }
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true,
                            duration: 2000
                        }
                    }
                });
            }
        }

        function updateChartType(type) {
            document.querySelectorAll('.chart-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const lineCont = document.getElementById('lineChartContainer');
            const pieCont = document.getElementById('pieChartContainer');
            const icon = document.getElementById('chartIcon');

            if (type === 'macros') {
                lineCont.style.display = 'none';
                pieCont.style.display = 'block';
                icon.className = 'fas fa-chart-pie';
                if (!macroChart) initMacroChart();
            } else {
                lineCont.style.display = 'block';
                pieCont.style.display = 'none';
                icon.className = 'fas fa-chart-line';

                if (type === 'cumulative') {
                    const getCumulative = (arr) => { let s = 0; return arr.map(v => s += v); };
                    chart.data.datasets[0].data = getCumulative(graphData.calories);
                    chart.data.datasets[1].data = getCumulative(graphData.protein);
                    chart.data.datasets[2].data = getCumulative(graphData.carbs);
                    chart.data.datasets[3].data = getCumulative(graphData.fats);
                    chart.options.scales.yCalories.title.text = 'Total Calories';
                } else {
                    chart.data.datasets[0].data = graphData.calories;
                    chart.data.datasets[1].data = graphData.protein;
                    chart.data.datasets[2].data = graphData.carbs;
                    chart.data.datasets[3].data = graphData.fats;
                    chart.options.scales.yCalories.title.text = 'Daily Calories';
                }
                chart.update();
            }
        }

        initChart();

        // Hydration Journey Chart Initialization
        function initHydrationJourneyChart() {
            const waterCtx = document.getElementById('hydrationJourneyChart').getContext('2d');
            const hLabels = <?php echo json_encode($graph_labels); ?>;
            const hData = <?php echo json_encode($graph_data['water']); ?>;

            const hGradient = waterCtx.createLinearGradient(0, 0, 0, 300);
            hGradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
            hGradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            new Chart(waterCtx, {
                type: 'line',
                data: {
                    labels: hLabels,
                    datasets: [{
                        label: 'Water Intake (L)',
                        data: hData,
                        borderColor: '#3b82f6',
                        backgroundColor: hGradient,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#3b82f6',
                        pointHoverRadius: 6,
                        shadowColor: 'rgba(59, 130, 246, 0.4)',
                        shadowBlur: 10
                    }]
                },
                plugins: [shadowPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1A1A1A',
                            padding: 15,
                            callbacks: {
                                label: (ctx) => ` Intake: ${ctx.raw.toFixed(1)} Liters`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#B0B0B0' },
                            title: { display: true, text: 'Liters (L)', color: '#3b82f6' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#B0B0B0' }
                        }
                    }
                }
            });
        }
        initHydrationJourneyChart();


    </script>

</body>

</html>