<?php
// ============================================
// PHP BACKEND SECTION
// ============================================
session_start();

// Strict Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Handle API Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Database Config
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'fitness_app';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'DB Connection Failed']);
        exit;
    }

    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'initialize_db':
            // Create user_progress table if not exists
            $sql = "CREATE TABLE IF NOT EXISTS user_progress (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                day_number INT NOT NULL,
                diet_type VARCHAR(10) NOT NULL,
                completed_meals TEXT,
                custom_meals TEXT,
                water_liters FLOAT DEFAULT 0,
                workouts TEXT,
                total_burned INT DEFAULT 0,
                total_calories INT DEFAULT 0,
                total_carbs INT DEFAULT 0,
                total_protein INT DEFAULT 0,
                total_fat INT DEFAULT 0,
                date_completed DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_day (user_id, day_number)
            )";

            if ($conn->query($sql)) {
                // Ensure columns exist if table was already created
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS custom_meals TEXT");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS water_liters FLOAT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS workouts TEXT");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_burned INT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_calories INT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_carbs INT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_protein INT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_fat INT DEFAULT 0");
                $conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS date_completed DATE");
                $response = ['success' => true, 'message' => 'Table init success'];
            } else {
                $response = ['success' => false, 'message' => 'Table init failed'];
            }
            break;

        case 'save_progress':
            $day_number = intval($_POST['day']);
            $diet_type = $_POST['diet_type'];
            $completed_meals = $_POST['completed_meals']; // JSON string
            $custom_meals = $_POST['custom_meals'] ?? '[]';
            $water_liters = floatval($_POST['water_liters'] ?? 0);
            $workouts = $_POST['workouts'] ?? '[]';
            $total_burned = intval($_POST['total_burned'] ?? 0);
            $totals = json_decode($_POST['totals'], true) ?? ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];

            // Ensure values are numeric
            $tc = intval($totals['calories'] ?? 0);
            $tp = intval($totals['protein'] ?? 0);
            $tc_g = intval($totals['carbs'] ?? 0);
            $tf = intval($totals['fat'] ?? 0);

            // Check existing
            $check = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND day_number = ?");
            $check->bind_param("ii", $user_id, $day_number);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE user_progress SET diet_type=?, completed_meals=?, custom_meals=?, water_liters=?, workouts=?, total_burned=?, total_calories=?, total_carbs=?, total_protein=?, total_fat=?, date_completed=CURDATE() WHERE user_id=? AND day_number=?");
                $stmt->bind_param("sssdsiiiiiii", $diet_type, $completed_meals, $custom_meals, $water_liters, $workouts, $total_burned, $tc, $tc_g, $tp, $tf, $user_id, $day_number);
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO user_progress (user_id, day_number, diet_type, completed_meals, custom_meals, water_liters, workouts, total_burned, total_calories, total_carbs, total_protein, total_fat, date_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                $stmt->bind_param("iisssdsiiiii", $user_id, $day_number, $diet_type, $completed_meals, $custom_meals, $water_liters, $workouts, $total_burned, $tc, $tc_g, $tp, $tf);
            }

            if ($stmt->execute()) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => $conn->error];
            }
            break;

        case 'load_progress':
            $day_number = intval($_POST['day']);
            $stmt = $conn->prepare("SELECT * FROM user_progress WHERE user_id = ? AND day_number = ?");
            $stmt->bind_param("ii", $user_id, $day_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                $response = ['success' => true, 'progress' => $data];
            } else {
                $response = ['success' => true, 'progress' => null];
            }
            break;
    }

    $conn->close();
    echo json_encode($response);
    exit;
}

// Fetch User Daily Goal for UI
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'fitness_app';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$daily_goal = 2000;
$target_duration = 3;
$macro_goals = ['p' => 150, 'c' => 250, 'f' => 70];
if (!$conn->connect_error) {
    // Self-healing: Ensure target_duration column exists in usersinput
    $conn->query("ALTER TABLE usersinput ADD COLUMN IF NOT EXISTS target_duration INT DEFAULT 3");

    $g_stmt = $conn->prepare("SELECT daily_calories, protein_g, carbs_g, fats_g, target_duration FROM usersinput WHERE user_id = ?");
    $g_stmt->bind_param("i", $user_id);
    $g_stmt->execute();
    $g_res = $g_stmt->get_result();
    if ($g_row = $g_res->fetch_assoc()) {
        $daily_goal = intval($g_row['daily_calories']);
        $macro_goals = [
            'p' => intval($g_row['protein_g']),
            'c' => intval($g_row['carbs_g']),
            'f' => intval($g_row['fats_g'])
        ];
        $target_duration = intval($g_row['target_duration'] ?? 3);
    }
    $g_stmt->close();

    // Also calc current challenge day for JS init
    $u_sql = "SELECT created_at FROM users WHERE id = ? LIMIT 1";
    $u_stmt = $conn->prepare($u_sql);
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result()->fetch_assoc();
    $join_date = new DateTime($u_res['created_at']);
    $join_date->setTime(0, 0, 0); // Normalize to midnight for calendar-day calculation
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $diff = $join_date->diff($today);
    $time_based_day = ($diff->days + 1);
    $u_stmt->close();

    // Check progress-based day (Unlock next day ONLY if previous is "completed")
    // reliable metric: highest day that has 7 completed meals (either default or replacement)
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
        $debug_log = []; // Array to store slot statuses for debug
        for ($i = 0; $i < 7; $i++) {
            $slot_done = false;
            
            // 1. Check default meal
            if (is_array($meals_done) && in_array($i, $meals_done)) {
                $slot_done = true;
                $debug_log[] = "Slot $i: DONE (Default)";
            } 
            
            // 2. Check replacements (if default not done)
            if (!$slot_done && is_array($custom_meals)) {
                foreach ($custom_meals as $cm) {
                    // Check if this custom meal replaces the current slot index AND is marked done
                    if (isset($cm['replaces']) && $cm['replaces'] == $i && isset($cm['isDone']) && $cm['isDone']) {
                        $slot_done = true;
                        $debug_log[] = "Slot $i: DONE (Custom)";
                        break;
                    }
                }
            }

            if (!$slot_done) {
                $day_complete = false;
                $debug_log[] = "Slot $i: FAILED";
                break;
            }
        }
        
        // Log debug info for Day 1 specifically if it exists
        if ($row['day_number'] == 1) {
            echo "<script>console.log('DEBUG DAY 1 LOGIC:', " . json_encode($debug_log) . ");</script>";
            echo "<script>console.log('DEBUG DAY 1 CUSTOM MEALS:', " . json_encode($custom_meals) . ");</script>";
        }

        if ($day_complete) {
            $max_completed_day = max($max_completed_day, intval($row['day_number']));
        }
    }

    $progress_based_day = $max_completed_day + 1;
    $p_stmt->close();

    // Use ONLY time-based day (no max logic)
    $calc_challenge_day = $time_based_day;

    if ($calc_challenge_day > 30)
        $calc_challenge_day = 30;
    
    // DEBUG OUTPUT
    echo "<script>console.log('DEBUG: time_based_day = ', $time_based_day);</script>";
    echo "<script>console.log('DEBUG: calc_challenge_day = ', $calc_challenge_day);</script>";
    echo "<script>console.log('DEBUG: Join Date = ', '" . $u_res['created_at'] . "');</script>";
    echo "<script>console.log('DEBUG: Today = ', '" . date('Y-m-d H:i:s') . "');</script>";

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Challenge | NutriPlan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ==================== THEME VARIABLES ==================== */
        :root {
            --bg-primary: #050505;
            /* Deep matte black */
            --bg-secondary: #0f0f0f;
            --card-bg: linear-gradient(145deg, #161616, #0a0a0a);
            --card-hover: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            --input-bg: #1a1a1a;
            --accent-yellow: #FFC107;
            --accent-orange: #FF9800;
            --text-primary: #FFFFFF;
            --text-secondary: #9CA3AF;
            --border-color: #27272a;
            --border-highlight: rgba(255, 193, 7, 0.5);
            --success: #10B981;
            --success-glow: rgba(16, 185, 129, 0.2);
            --danger: #EF4444;
            --shadow-soft: 0 10px 40px -10px rgba(0, 0, 0, 0.7);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* ==================== WATER TRACKER ==================== */
        .water-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .water-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .water-header h3 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .water-stats-row {
            display: flex;
            align-items: baseline;
            gap: 5px;
            margin-bottom: 20px;
        }

        .water-total {
            font-size: 32px;
            font-weight: 800;
        }

        .water-unit {
            font-size: 14px;
            opacity: 0.8;
        }

        .glasses-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .glass-btn {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .glass-btn i {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.4);
            transition: 0.3s;
        }

        .glass-btn.active {
            background: #3b82f6;
            border-color: #60a5fa;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
            transform: scale(1.05);
        }

        .glass-btn.active i {
            color: #fff;
            transform: scale(1.2);
        }

        .glass-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .water-controls {
            display: flex;
            gap: 10px;
        }

        .w-btn {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: 0.2s;
        }

        .w-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ==================== CHART CONTAINER ==================== */
        .chart-panel {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 25px;
            margin-top: 20px;
            border: 1px solid var(--border-color);
        }

        .chart-header {
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 16px;
            margin: 0;
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* ==================== NAVBAR ==================== */
        .navbar {
            background-color: var(--bg-secondary);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 193, 7, 0.1);
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .nav-brand span {
            color: var(--accent-yellow);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 40px;
            align-items: center;
        }

        .nav-menu li a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition);
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            color: var(--accent-yellow);
        }

        .logout-btn {
            background: transparent;
            border: 2px solid var(--accent-yellow);
            padding: 8px 24px;
            border-radius: 6px;
            color: var(--accent-yellow) !important;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--accent-yellow);
            color: #000 !important;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
        }

        /* ==================== HEADER ==================== */
        .main-wrapper {
            padding: 40px 0 80px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }

        .page-header h1 {
            font-size: 48px;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 16px;
            background: linear-gradient(to right, #fff, #888);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header span {
            background: linear-gradient(to right, var(--accent-yellow), #FFA000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== DAY NAVIGATION ==================== */
        .day-nav-container {
            background: var(--bg-secondary);
            padding: 24px 0;
            border-bottom: 1px solid var(--border-color);
            /* Removed sticky positioning as requested */
            z-index: 900;
        }

        .day-tabs {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 4px 4px 20px;
            margin-bottom: 0;
            scroll-behavior: smooth;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .day-tabs::-webkit-scrollbar {
            display: none;
        }

        .day-tab {
            min-width: 50px;
            height: 50px;
            background: #121212;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            /* Slightly more rounded */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            flex-direction: column;
            /* Stack 'DAY' and number */
            line-height: 1;
        }

        .day-tab::before {
            content: 'DAY';
            font-size: 8px;
            font-weight: 800;
            margin-bottom: 2px;
            opacity: 0.7;
        }

        .day-tab:hover {
            border-color: var(--accent-yellow);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        .day-tab.active {
            background: var(--accent-yellow);
            color: #000;
            border-color: var(--accent-yellow);
            font-weight: 800;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.4);
            transform: scale(1.05);
            /* Slight pop for active state */
        }

        .day-tab.completed {
            background: var(--accent-orange);
            color: #000;
            border-color: var(--accent-orange);
            position: relative;
        }

        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .day-title-section {
            color: var(--text-primary);
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.02em;
        }

        .diet-toggle {
            display: flex;
            gap: 4px;
            background: #000;
            padding: 4px;
            border-radius: 99px;
            border: 1px solid var(--border-color);
        }

        .diet-btn {
            padding: 8px 24px;
            border-radius: 99px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
        }

        .diet-btn:hover {
            color: var(--text-primary);
        }

        .diet-btn.active {
            background: var(--card-bg);
            color: var(--accent-yellow);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        /* ==================== CONTENT GRID ==================== */
        .content-grid {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 60px;
            margin-top: 50px;
            align-items: start;
        }

        /* MEAL CARDS */
        .meal-card-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .meal-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .meal-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-yellow);
            opacity: 0.5;
            transition: var(--transition);
        }

        .meal-card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: var(--border-highlight);
            box-shadow: var(--shadow-soft);
        }

        .meal-card:hover::before {
            opacity: 1;
        }

        .meal-card.completed {
            border-color: var(--success);
            background: linear-gradient(145deg, #0d2119, #05130e);
            /* Subtle green tint */
        }

        .meal-card.completed::before {
            background: var(--success);
        }

        .meal-info {
            flex: 1;
        }

        .meal-time {
            font-size: 12px;
            color: var(--accent-yellow);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .meal-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .meal-macros {
            display: inline-flex;
            gap: 16px;
            font-size: 13px;
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.03);
            padding: 6px 12px;
            border-radius: 6px;
        }

        .meal-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.4;
            opacity: 0.8;
            border-left: 2px solid var(--accent-yellow);
            padding-left: 10px;
        }

        .meal-macros span {
            color: #fff;
            font-weight: 600;
        }

        .check-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 24px;
            transition: var(--transition);
            font-size: 14px;
            color: transparent;
        }

        .meal-card:hover .check-circle {
            border-color: var(--accent-yellow);
        }

        .meal-card.completed .check-circle {
            background: var(--success);
            border-color: var(--success);
            color: #000;
            box-shadow: 0 0 15px var(--success-glow);
        }

        /* STATS PANEL */
        .stats-sidebar {
            position: sticky;
            top: 180px;
        }

        .stats-panel {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 32px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
        }

        .stats-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 24px;
            color: var(--accent-yellow);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .stat-row:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
        }

        .stat-val {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 16px;
        }

        .save-btn {
            width: 100%;
            background: var(--accent-yellow);
            color: #000;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 32px;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .save-btn:hover {
            background: #FFD54F;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 193, 7, 0.2);
        }

        #resetBtn {
            width: 100%;
            margin-top: 12px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        #resetBtn:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }

        /* NOTIFICATION */
        .notification {
            position: fixed;
            bottom: 40px;
            right: 40px;
            padding: 16px 32px;
            border-radius: 12px;
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            z-index: 2000;
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--danger);
        }

        /* RESPONSIVE */
        @media(max-width: 1024px) {
            .container {
                padding: 0 24px;
            }

            .content-grid {
                gap: 40px;
            }
        }

        @media(max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-sidebar {
                position: static;
                margin-top: 40px;
            }

            .page-header h1 {
                font-size: 32px;
            }

            .day-nav-container {
                top: 60px;
            }

            .filter-bar {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .nav-menu {
                display: none;
            }
        }

        /* GOAL HIGHLIGHT */
        .stat-val.exceeded {
            color: var(--danger) !important;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
        }

        .stat-val .target-txt {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 400;
            margin-left: 4px;
        }

        /* SIDEBAR PROGRESS MINI BARS */
        .mini-pbar-bg {
            width: 100%;
            height: 4px;
            background: #111;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            display: block;
            /* Ensure visibility */
        }

        .mini-pbar-fill {
            height: 100%;
            background: var(--accent-yellow);
            width: 0%;
            transition: width 0.5s ease;
        }

        .exceeded+.mini-pbar-bg .mini-pbar-fill {
            background: var(--danger);
        }

        /* PULSE FOR EXCEEDED */
        .stat-val.exceeded {
            animation: pulse-red-text 2s infinite;
        }

        @keyframes pulse-red-text {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(10px);
        }

        .goal-modal {
            background: var(--card-bg);
            border: 1px solid var(--accent-yellow);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 0 50px rgba(255, 193, 7, 0.2);
            transform: scale(0.8);
            transition: var(--transition);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-overlay.active .goal-modal {
            transform: scale(1);
        }

        .goal-icon {
            font-size: 64px;
            color: var(--accent-yellow);
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-20px);
            }

            60% {
                transform: translateY(-10px);
            }
        }

        .goal-modal h2 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #fff;
        }

        .goal-modal p {
            color: var(--text-secondary);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .close-modal-btn {
            background: var(--accent-yellow);
            color: #000;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal-btn:hover {
            background: #FFD54F;
            transform: scale(1.05);
        }

        /* GRAND PRIZE MODAL (Day 30) */
        .grand-prize-modal {
            background: linear-gradient(145deg, #1a1200, #0f0800);
            border: 2px solid #FFD700;
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 80px rgba(255, 215, 0, 0.4), 0 0 40px rgba(255, 193, 7, 0.3);
            transform: scale(0.8);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .grand-prize-modal::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .modal-overlay.active .grand-prize-modal {
            transform: scale(1);
        }

        .grand-prize-icon {
            font-size: 80px;
            color: #FFD700;
            margin-bottom: 25px;
            animation: bounce 2s infinite, glow 2s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes glow {

            0%,
            100% {
                text-shadow: 0 0 20px rgba(255, 215, 0, 0.8), 0 0 40px rgba(255, 215, 0, 0.5);
            }

            50% {
                text-shadow: 0 0 30px rgba(255, 215, 0, 1), 0 0 60px rgba(255, 215, 0, 0.7);
            }
        }

        .grand-prize-modal h2 {
            font-size: 36px;
            margin-bottom: 15px;
            background: linear-gradient(to right, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 1;
        }

        .grand-prize-modal p {
            color: #D4AF37;
            margin-bottom: 30px;
            line-height: 1.8;
            font-size: 16px;
            position: relative;
            z-index: 1;
        }

        .grand-prize-modal .close-modal-btn {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
            font-size: 16px;
            padding: 14px 40px;
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4);
            position: relative;
            z-index: 1;
        }

        .grand-prize-modal .close-modal-btn:hover {
            background: linear-gradient(135deg, #FFA500, #FFD700);
            transform: scale(1.08);
            box-shadow: 0 6px 30px rgba(255, 215, 0, 0.6);
        }

        /* FOOD SELECTION MODAL */
        .food-modal {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            max-width: 600px;
            width: 95%;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .food-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .food-modal-body {
            overflow-y: auto;
            flex: 1;
            padding-right: 10px;
        }

        .food-grid-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .food-grid-item:hover {
            border-color: var(--accent-yellow);
            background: rgba(255, 193, 7, 0.05);
            transform: translateX(5px);
        }

        .food-details h4 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .food-details p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .add-icon {
            color: var(--accent-yellow);
            font-size: 18px;
        }

        .log-manual-btn {
            background: rgba(255, 193, 7, 0.1);
            color: var(--accent-yellow);
            border: 1px solid rgba(255, 193, 7, 0.2);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
        }

        .log-manual-btn:hover {
            background: var(--accent-yellow);
            color: #000;
        }

        /* CUSTOM MEAL TRACKER STYLES */
        .custom-tracker-container {
            margin-top: 40px;
            padding: 30px;
            background: var(--bg-secondary);
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        .tracker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .tracker-header h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent-yellow);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 14px 20px 14px 45px;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: var(--transition);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .search-box input:focus {
            border-color: var(--accent-yellow);
            outline: none;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.2);
        }

        .food-results {
            background: #111;
            border-radius: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: none;
        }

        .food-item {
            padding: 12px 20px;
            border-bottom: 1px solid #222;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            justify-content: space-between;
        }

        .food-item:hover {
            background: #222;
        }

        .log-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            margin-bottom: 25px;
            display: none;
        }

        .log-form input {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 8px;
            color: #fff;
        }

        .log-btn {
            background: var(--accent-yellow);
            border: none;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .custom-meal-list {
            margin-top: 20px;
        }

        .custom-log-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid var(--accent-yellow);
        }

        .delete-log {
            color: var(--danger);
            cursor: pointer;
            padding: 5px;
        }

        /* RECOMMENDATIONS */
        .rec-container {
            margin-top: 40px;
            background: linear-gradient(145deg, #101010, #050505);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }

        .rec-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--success);
            font-weight: 800;
        }

        .rec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .rec-card {
            background: #111;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #222;
            text-align: center;
            transition: 0.3s;
        }

        .rec-card:hover {
            border-color: var(--success);
            transform: translateY(-3px);
        }

        .rec-name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .rec-macro {
            font-size: 11px;
            color: var(--text-secondary);
        }

        /* Footer */
        .footer {
            background-color: var(--bg-secondary);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(255, 193, 7, 0.1);
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
            transition: var(--transition);
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
            transition: var(--transition);
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
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- DAY NAV -->
    <div class="day-nav-container">
        <div class="container">
            <div class="day-tabs" id="dayTabs"></div>

            <div class="filter-bar">
                <div class="day-title-section" id="dayTitleDisplay">Day 1 Challenge</div>
                <div class="diet-toggle">
                    <button class="diet-btn active" id="vegBtn">🥦 Veg</button>
                    <button class="diet-btn" id="nonVegBtn">🍗 Non-Veg</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper">
        <div class="container">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <div
                    style="font-size: 13px; color: var(--text-secondary); background: rgba(255,255,255,0.03); padding: 8px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-calendar-check" style="color: var(--accent-yellow);"></i>
                    Target Journey: <span
                        style="color: #fff; font-weight: 700;"><?= $target_duration >= 12 ? '1 Year' : $target_duration . ' Months' ?></span>
                </div>
            </div>

            <div class="page-header">
                <h1>Your 30-Day <span>Transformation</span></h1>
                <p>Track your meals daily to build consistency and achieve your goals.</p>
            </div>

            <div class="content-grid">
                <!-- Left: Meals -->
                <div class="meal-wrapper-left">
                    <div class="meal-card-container" id="mealList">
                        <!-- Meals injected via JS -->
                    </div>

                    <!-- Custom Meal Tracker -->
                    <div class="custom-tracker-container">
                        <div class="tracker-header">
                            <h3><i class="fas fa-utensils"></i> Dynamic Meal Tracker</h3>
                            <button class="log-manual-btn" onclick="openManualLogModal('Any Meal', -1)"><i class="fas fa-list"></i> Browse All</button>
                        </div>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="foodSearch" placeholder="Search food (e.g. Chicken, Oats...)"
                                autocomplete="off">
                            <div class="food-results" id="foodResults"></div>
                        </div>

                        <div class="log-form" id="logForm" style="grid-template-columns: 1fr 1fr; gap: 10px;">
                            <input type="text" id="selectedFoodName" placeholder="Food Name" readonly
                                style="grid-column: span 2;">
                            <select id="mealSlot"
                                style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: #fff; padding: 10px; border-radius: 8px;">
                                <option value="Early Morning">Early Morning</option>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Mid Morning">Mid Morning</option>
                                <option value="Lunch">Lunch</option>
                                <option value="Pre-Workout">Pre-Workout</option>
                                <option value="Post-Workout">Post-Workout</option>
                                <option value="Dinner">Dinner</option>
                            </select>
                            <input type="number" id="foodQty" placeholder="Qty (g/pc)">
                            <input type="number" id="manualCal" placeholder="Cals (kcal)">
                            <input type="number" id="manualProt" placeholder="Prot (g)">
                            <input type="number" id="manualCarb" placeholder="Carbs (g)">
                            <input type="number" id="manualFat" placeholder="Fats (g)">
                            <button class="log-btn" id="addFoodBtn" style="grid-column: span 2; padding: 12px;">Add
                                Meal</button>
                        </div>

                        <div class="custom-meal-list" id="customMealList">
                            <!-- Custom logs appear here -->
                        </div>
                    </div>







                    <!-- Recommendations Section -->
                    <div class="rec-container" id="recContainer" style="margin-top: 20px;">
                        <div class="rec-header">
                            <i class="fas fa-lightbulb"></i> Efficient Nutrition Recommender
                        </div>
                        <div class="rec-grid" id="recGrid"
                            style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
                            <!-- recommendations injected via JS -->
                        </div>
                    </div>
                </div>

                <!-- Right: Stats -->
                <div class="stats-sidebar">
                    <div class="stats-panel">
                        <h3 class="stats-title">Day Summary</h3>

                        <div class="stat-row" style="flex-direction:column; align-items:flex-start;">
                            <div style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                                <span class="stat-label">Calories</span>
                                <span class="stat-val" id="totalCal">0<span class="target-txt">/ 2000</span> kcal</span>
                            </div>
                            <div class="mini-pbar-bg">
                                <div id="barCal" class="mini-pbar-fill"></div>
                            </div>
                        </div>
                        <div class="stat-row" style="flex-direction:column; align-items:flex-start;">
                            <div style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                                <span class="stat-label">Protein</span>
                                <span class="stat-val" id="totalProt">0g<span class="target-txt">/ 150g</span></span>
                            </div>
                            <div class="mini-pbar-bg">
                                <div id="barPro" class="mini-pbar-fill"></div>
                            </div>
                        </div>
                        <div class="stat-row" style="flex-direction:column; align-items:flex-start;">
                            <div style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                                <span class="stat-label">Carbs</span>
                                <span class="stat-val" id="totalCarb">0g<span class="target-txt">/ 250g</span></span>
                            </div>
                            <div class="mini-pbar-bg">
                                <div id="barCarb" class="mini-pbar-fill"></div>
                            </div>
                        </div>
                        <div class="stat-row" style="flex-direction:column; align-items:flex-start;">
                            <div style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                                <span class="stat-label">Fats</span>
                                <span class="stat-val" id="totalFat">0g<span class="target-txt">/ 70g</span></span>
                            </div>
                            <div class="mini-pbar-bg">
                                <div id="barFat" class="mini-pbar-fill"></div>
                            </div>
                        </div>


                    </div>

                    <div class="stats-panel" style="margin-top: 20px; border-color: rgba(255, 193, 7, 0.2);">
                        <h3 class="stats-title" style="color: var(--accent-yellow); font-size: 16px;">
                            <i class="fas fa-chart-pie" style="margin-right: 8px;"></i> Suggested Goal Split
                        </h3>
                        <p style="font-size: 11px; color: #9CA3AF; margin-bottom: 20px; line-height: 1.4;">
                            Recommended distribution for your <strong><?= $daily_goal ?> kcal</strong> target:
                        </p>

                        <div id="mealSplitList" style="display: flex; flex-direction: column; gap: 10px;">
                            <!-- Injected via JS -->
                        </div>
                    </div>

                    <!-- Water Tracker -->
                    <div class="water-card">
                        <div class="water-header">
                            <h3><i class="fas fa-faucet-drip"></i> Hydration </h3>
                            <button onclick="resetWater()"
                                style="background:none; border:none; color:rgba(255,255,255,0.6); cursor:pointer;"><i
                                    class="fas fa-rotate-right"></i></button>
                        </div>
                        <div class="water-stats-row">
                            <span class="water-total" id="waterTotal">0.0</span>
                            <span class="water-unit">Liters of 3L Goal</span>
                        </div>
                        <div class="glasses-grid" id="glassesGrid">
                            <!-- 8-12 glasses injected via JS -->
                        </div>
                        <div class="water-controls">
                            <button class="w-btn" onclick="adjustWater(-0.25)"><i class="fas fa-minus"></i>
                                250ml</button>
                            <button class="w-btn" onclick="adjustWater(0.25)"><i class="fas fa-plus"></i> 250ml</button>
                        </div>
                    </div>



                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <button class="save-btn" id="saveBtn">
                            <i class="fas fa-cloud-upload-alt"></i> Save Progress
                        </button>
                        <button class="diet-btn" style="width:100%; margin-top:10px; border:1px solid #333;"
                            id="resetBtn">
                            Reset Day
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </div>

    <div id="notification" class="notification">Progress Saved!</div>

    <!-- GOAL MODAL -->
    <div class="modal-overlay" id="goalModal">
        <div class="goal-modal">
            <div class="goal-icon"><i class="fas fa-trophy"></i></div>
            <h2>Goal Achieved!</h2>
            <p>Congratulations! You've reached your daily calorie target for Day <span id="modalDayText">1</span>. Keep
                up the great work!</p>
            <button class="close-modal-btn" onclick="closeGoalModal()">Awesome!</button>
        </div>
    </div>

    <!-- GRAND PRIZE MODAL (Day 30) -->
    <div class="modal-overlay" id="grandPrizeModal">
        <div class="grand-prize-modal">
            <div class="grand-prize-icon"><i class="fas fa-crown"></i></div>
            <h2>🎉 Challenge Champion! 🎉</h2>
            <p>Incredible! You've completed the entire <strong>30-Day Challenge</strong> and crushed your final day's
                goal!<br><br>
                You are a true nutrition warrior. This is just the beginning of your journey!</p>
            <button class="close-modal-btn" onclick="closeGrandPrizeModal()">Claim Victory!</button>
        </div>
    </div>

    <!-- FOOD SELECTION MODAL -->
    <div class="modal-overlay" id="foodSearchModal">
        <div class="food-modal">
            <div class="food-modal-header">
                <h3><i class="fas fa-search"></i> Select Food for <span id="modalSlotTitle">Meal</span></h3>
                <button onclick="closeFoodModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="search-box" style="margin-bottom: 15px;">
                <i class="fas fa-search"></i>
                <input type="text" id="modalFoodSearch" placeholder="Search from 100+ items..." oninput="filterModalFoods(this.value)">
            </div>

            <div class="food-modal-body" id="modalFoodList">
                <!-- Food items injected here -->
            </div>
        </div>
    </div>

    <script>
        // ==================== DATA & LOGIC ====================
        // Diet Data
        const vegMeals = [
            {
                time: "Early Morning", name: "Lemon water + Almonds", cal: 50, c: 3, p: 2, f: 3, qty: "1 glass + 5-6 pcs",
                desc: "A refreshing start to boost metabolism and energy.",
                options: ["Almonds", "Apple", "Orange", "Green Tea", "Walnuts", "Chia Seeds", "Coconut Water", "Lemon Water", "Ginger Tea"]
            },
            {
                time: "Breakfast", name: "Oats with Milk & Nuts", cal: 320, c: 45, p: 12, f: 10, qty: "40g oats + 200ml milk",
                desc: "High-fiber oats with calcium-rich milk and healthy fats from nuts.",
                options: ["Oats", "Milk (Whole)", "Greek Yogurt", "Peanut Butter", "Upma", "Poha", "Besan Chilla", "Banana", "Idli", "Masala Dosa", "Aloo Paratha"]
            },
            {
                time: "Mid Morning", name: "Apple", cal: 95, c: 25, p: 0, f: 0, qty: "1 medium (150g)",
                desc: "A crisp, natural snack to keep you full until lunch.",
                options: ["Apple", "Orange", "Guava", "Makhana (Roasted)", "Buttermilk", "Watermelon", "Papaya", "Cucumber"]
            },
            {
                time: "Lunch", name: "Roti (2) + Dal + Veggies", cal: 450, c: 60, p: 18, f: 12, qty: "2 rotis + 1 bowl dal",
                desc: "Whole wheat rotis paired with protein-packed dal and fresh vegetables.",
                options: ["Dal (Lentils)", "Brown Rice", "Paneer", "Chickpeas", "Tofu", "Broccoli", "Soya Chunks", "Rajma Chawal", "Chole Bhature"]
            },
            {
                time: "Pre-Workout", name: "Banana", cal: 105, c: 27, p: 1, f: 0, qty: "1 large",
                desc: "Quick carbs for immediate energy before your training session.",
                options: ["Banana", "Apple", "Dates", "Sweet Potato", "Grapes"]
            },
            {
                time: "Post-Workout", name: "Protein Shake", cal: 120, c: 5, p: 25, f: 2, qty: "1 scoop (30g)",
                desc: "Optimal protein intake for muscle recovery and growth.",
                options: ["Greek Yogurt", "Egg", "Milk (Whole)", "Chickpeas", "Tofu", "Almonds", "Peanut Butter"]
            },
            {
                time: "Dinner", name: "Paneer Sabzi + Salad", cal: 380, c: 30, p: 25, f: 15, qty: "100g paneer + 1 bowl salad",
                desc: "Low-carb, high-protein paneer with a fiber-rich garden salad.",
                options: ["Paneer", "Tofu", "Broccoli", "Salmon", "Chickpeas", "Gulab Jamun"]
            }
        ];

        const nonVegMeals = [
            {
                time: "Early Morning", name: "Lemon water", cal: 5, c: 2, p: 0, f: 0, qty: "1 glass",
                desc: "Simple hydration to wake up your digestive system.",
                options: ["Almonds", "Walnuts", "Green Tea", "Chia Seeds", "Coconut Water", "Lemon Water", "Ginger Tea"]
            },
            {
                time: "Breakfast", name: "3 Egg Omelette + Toast", cal: 350, c: 20, p: 22, f: 18, qty: "3 eggs + 2 slices bread",
                desc: "Protein-rich eggs with whole grain toast for sustained energy.",
                options: ["Egg", "Bread (Whole Wheat)", "Oats", "Turkey", "Peanut Butter", "Salmon", "Idli", "Masala Dosa", "Aloo Paratha"]
            },
            {
                time: "Mid Morning", name: "Orange", cal: 60, c: 15, p: 1, f: 0, qty: "1 medium",
                desc: "Vitamin C boost to support your immune system.",
                options: ["Orange", "Apple", "Guava", "Makhana (Roasted)", "Buttermilk"]
            },
            {
                time: "Lunch", name: "Grilled Chicken + Rice", cal: 500, c: 50, p: 40, f: 12, qty: "150g chicken + 100g rice",
                desc: "Lean chicken breast paired with complex carbs for peak performance.",
                options: ["Chicken Breast", "White Rice", "Brown Rice", "Egg", "Fish (Grilled)", "Turkey", "Prawns", "Beef", "Butter Chicken", "Rajma Chawal", "Chole Bhature"]
            },
            {
                time: "Pre-Workout", name: "Banana", cal: 105, c: 27, p: 1, f: 0, qty: "1 large",
                desc: "Easily digestible sugars for an explosive workout.",
                options: ["Banana", "Apple", "Dates", "Sweet Potato", "Grapes"]
            },
            {
                time: "Post-Workout", name: "Protein Shake", cal: 120, c: 5, p: 25, f: 2, qty: "1 scoop (30g)",
                desc: "Rapid absorption protein to kickstart muscle repair.",
                options: ["Greek Yogurt", "Egg", "Chicken Breast", "Milk (Whole)", "Salmon", "Peanut Butter"]
            },
            {
                time: "Dinner", name: "Fish Fillet + Salad", cal: 320, c: 5, p: 35, f: 12, qty: "150g fish + 1 bowl salad",
                desc: "Omega-3 rich fish with a light, refreshing salad for a healthy heart.",
                options: ["Salmon", "Fish (Grilled)", "Chicken Breast", "Egg", "Prawns", "Turkey", "Veg Salad"]
            }
        ];

        // Generate 30 Days Data
        const dietPlan = { veg: {}, nonVeg: {} };
        for (let i = 1; i <= 30; i++) {
            dietPlan.veg[i] = JSON.parse(JSON.stringify(vegMeals));
            dietPlan.nonVeg[i] = JSON.parse(JSON.stringify(nonVegMeals));
            // Add slight variations if needed
        }

        // App State
        const DAILY_GOAL = <?= $daily_goal ?>;
        const MACRO_GOALS = <?= json_encode($macro_goals) ?>;
        const activeDay = <?= isset($calc_challenge_day) ? $calc_challenge_day : 1 ?>;
        
        console.log("DEBUG: User ID =", <?= $user_id ?>);
        console.log("DEBUG: Calculated Day =", <?= $progress_based_day ?>);
        console.log("DEBUG: Max Completed Day (PHP) =", <?= $max_completed_day ?>);
        
        let currentDay = activeDay;
        let currentDiet = 'veg';
        let completedMeals = {}; // { dayId: [mealIndices] }
        let customMeals = {}; // { dayId: [{name, qty, unit, cal, p, c, f}] }
        let waterIntake = {}; // { dayId: float } 


        let foodDatabase = [];
        let popupsShown = {}; // Track which day popups were shown in current session

        // Elements
        const dayTabs = document.getElementById('dayTabs');
        const mealList = document.getElementById('mealList');
        const saveBtn = document.getElementById('saveBtn');
        const notification = document.getElementById('notification');

        // Init
        async function init() {
            // 1. Init DB Table
            await sendRequest('initialize_db');

            // 1.5 Load Food DB
            try {
                const foodRes = await fetch('food_db.json?v=' + new Date().getTime());
                foodDatabase = await foodRes.json();
            } catch (e) { console.error("Food DB Load Failed", e); }

            // 2. Build Tabs
            renderTabs();

            // 3. Load Current Day Data
            await loadDayData(currentDay);
            document.getElementById('dayTitleDisplay').textContent = `Day ${currentDay} Challenge`;

            updateUI();
        }

        // Render Tabs
        function renderTabs() {
            dayTabs.innerHTML = '';
            for (let i = 1; i <= 30; i++) {
                const tab = document.createElement('div');
                const isLocked = (i > activeDay); // LOCK FUTURE DAYS

                tab.className = `day-tab ${i === currentDay ? 'active' : ''} ${isLocked ? 'locked' : ''}`;

                if (isLocked) {
                    tab.innerHTML = `<i class="fas fa-lock" style="font-size: 12px; margin-bottom: 2px;"></i> ${i}`;
                    tab.style.opacity = '0.5';
                    tab.style.cursor = 'not-allowed';
                    tab.style.background = '#0a0a0a';
                    tab.style.borderColor = '#222';
                } else {
                    tab.textContent = i;
                    tab.onclick = () => switchDay(i);
                }

                dayTabs.appendChild(tab);
            }
        }

        // Switch Day
        async function switchDay(day) {
            if (day > activeDay) {
                showNotify("That day is still locked!", "error");
                return;
            }

            currentDay = day;

            // Re-render tabs to update active class
            renderTabs();

            const titleEl = document.getElementById('dayTitleDisplay');
            titleEl.textContent = `Day ${day} Challenge`;

            // Show Read-Only Indicator if needed
            if (currentDay < activeDay) {
                titleEl.innerHTML += ` <span style="font-size:12px; background:#333; padding:4px 8px; border-radius:4px; vertical-align:middle; margin-left:10px; color:#aaa;"><i class="fas fa-eye"></i> History View</span>`;
            }

            await loadDayData(day);
            updateUI();
        }

        // Load Data from Server
        async function loadDayData(day) {
            const res = await sendRequest('load_progress', { day });
            if (res.success && res.progress) {
                // Load saved state
                currentDiet = res.progress.diet_type;
                completedMeals[day] = JSON.parse(res.progress.completed_meals || '[]');
                customMeals[day] = JSON.parse(res.progress.custom_meals || '[]');
                waterIntake[day] = parseFloat(res.progress.water_liters || 0);


                // Update diet buttons state
                document.getElementById('vegBtn').classList.toggle('active', currentDiet === 'veg');
                document.getElementById('nonVegBtn').classList.toggle('active', currentDiet !== 'veg');
            } else {
                // Default new day: reset to Veg if no saved data
                if (!completedMeals[day]) completedMeals[day] = [];
                if (!customMeals[day]) customMeals[day] = [];
                if (!waterIntake[day]) waterIntake[day] = 0;

                currentDiet = 'veg';
                document.getElementById('vegBtn').classList.add('active');
                document.getElementById('nonVegBtn').classList.remove('active');
            }
        }

        function getDayTotals(day) {
            let totals = { cal: 0, p: 0, c: 0, f: 0 };

            // Challenge Meals
            const meals = dietPlan[currentDiet][day];
            const completed = completedMeals[day] || [];
            const cMeals = customMeals[day] || [];

            meals.forEach((meal, idx) => {
                const replacements = cMeals.filter(cm => cm.replaces === idx);

                if (replacements.length > 0) {
                    // If replaced, sum ONLY completed replacements
                    replacements.forEach(rep => {
                        if (rep.isDone) {
                            totals.cal += rep.cal;
                            totals.p += rep.p;
                            totals.c += rep.c;
                            totals.f += rep.f;
                        }
                    });
                } else {
                    // Default meal logic
                    if (completed.includes(idx)) {
                        totals.cal += meal.cal;
                        totals.p += meal.p;
                        totals.c += meal.c;
                        totals.f += meal.f;
                    }
                }
            });

            // Custom Meals (only standalone ones)
            cMeals.forEach(meal => {
                if (meal.replaces === undefined || meal.replaces === -1) {
                    totals.cal += meal.cal;
                    totals.p += meal.p;
                    totals.c += meal.c;
                    totals.f += meal.f;
                }
            });

            return totals;
        }

        // Update UI (Meals & Stats)
        function updateUI() {
            const isReadOnly = (currentDay < activeDay);

            mealList.innerHTML = '';
            const meals = dietPlan[currentDiet][currentDay];
            const completed = completedMeals[currentDay] || [];

            const totals = getDayTotals(currentDay);

            // Define weights for target calc
            const weights = {
                "Early Morning": 0.05,
                "Breakfast": 0.20,
                "Mid Morning": 0.10,
                "Lunch": 0.30,
                "Pre-Workout": 0.10,
                "Post-Workout": 0.10,
                "Dinner": 0.15
            };

            meals.forEach((meal, idx) => {
                const isDone = completed.includes(idx);
                const replacements = (customMeals[currentDay] || []).map((cm, cmIdx) => ({ ...cm, globalIdx: cmIdx })).filter(cm => cm.replaces === idx);
                const hasReplacements = replacements.length > 0;

                const targetCal = Math.round(DAILY_GOAL * (weights[meal.time] || 0));

                // Calculate CURRENT tracked calories for this slot
                let currentSlotCal = 0;
                if (hasReplacements) {
                    replacements.forEach(rep => { if (rep.isDone) currentSlotCal += rep.cal; });
                } else if (isDone) {
                    currentSlotCal += meal.cal;
                }

                // RENDER SLOT HEADER
                const slotHeader = document.createElement('div');
                slotHeader.className = 'slot-header';
                slotHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; margin-top: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px;';
                slotHeader.innerHTML = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size: 14px; font-weight: 700; color: var(--accent-yellow); text-transform: uppercase; letter-spacing: 0.5px;">${meal.time}</div>
                        ${!isReadOnly ? `<button class="log-manual-btn" onclick="openManualLogModal('${meal.time}', ${idx})"><i class="fas fa-plus"></i> Log Manual</button>` : ''}
                    </div>
                    <div style="text-align: right; line-height: 1;">
                        <span style="font-size: 18px; color: ${currentSlotCal > targetCal ? '#ff4d4d' : '#fff'}; font-weight: 800;">${Math.round(currentSlotCal)}</span>
                        <span style="font-size: 13px; color: #777; font-weight: 500;">/ ${targetCal}</span>
                        <span style="font-size: 13px; color: #fff; font-weight: 700;">kcal</span>
                    </div>
                `;
                mealList.appendChild(slotHeader);

                if (hasReplacements) {
                    // RENDER REPLACEMENTS
                    replacements.forEach((rep, rIdx) => {
                        const card = document.createElement('div');
                        card.className = `meal-card ${rep.isDone ? 'completed' : ''}`;
                        card.onclick = () => toggleCustomMeal(rep.globalIdx);
                        card.innerHTML = `
                            <div class="meal-info">
                                <div class="meal-name">
                                    ${rep.name} 
                                    ${rep.qty ? `<span style="font-size: 14px; color: var(--accent-yellow); font-weight: 500; margin-left: 8px;">(${rep.qty})</span>` : ''}
                                    <span style="font-size: 10px; background: var(--accent-yellow); color: #000; padding: 2px 6px; border-radius: 4px; margin-left: 10px; vertical-align: middle;">ALT ${rIdx + 1}</span>
                                </div>
                                
                                ${rIdx === replacements.length - 1 && !isReadOnly ? `
                                <div class="options-trigger" onclick="event.stopPropagation(); toggleOptions(this)" style="font-size: 11px; color: var(--accent-yellow); cursor: pointer; margin-bottom: 12px; display: inline-flex; align-items: center; gap: 5px; opacity: 0.8;">
                                    <i class="fas fa-plus-circle"></i> Add Another / Reset
                                </div>
                                <div class="meal-options-list" style="display: none; padding-bottom: 15px;">
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                        ${meal.options.map(opt => {
                            const food = foodDatabase.find(f => f.name.toLowerCase() === opt.toLowerCase());
                            return `
                                            <div onclick="event.stopPropagation(); addFromAlternatives('${opt}', '${meal.time}', ${idx})" 
                                                 style="cursor: pointer; font-size: 11px; background: rgba(255,255,255,0.03); padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s;"
                                                 onmouseover="this.style.background='rgba(255,193,7,0.1)'; this.style.borderColor='var(--accent-yellow)';"
                                                 onmouseout="this.style.background='rgba(255,255,255,0.03)'; this.style.borderColor='rgba(255,255,255,0.05)';"
                                            >
                                                <div style="color: var(--text-primary); font-weight: 600; margin-bottom: 3px;">
                                                    <i class="fas fa-plus" style="font-size: 8px; color: var(--accent-yellow); margin-right: 5px;"></i> ${opt}
                                                </div>
                                            </div>
                                        `;
                        }).join('')}
                                        <div onclick="event.stopPropagation(); resetToDefault(${idx})" 
                                             style="cursor: pointer; font-size: 11px; background: rgba(239, 68, 68, 0.05); padding: 8px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); transition: all 0.2s; grid-column: span 2; text-align: center; color: var(--danger);"
                                             onmouseover="this.style.background='rgba(239, 68, 68, 0.1)';"
                                             onmouseout="this.style.background='rgba(239, 68, 68, 0.05)';"
                                        >
                                            <i class="fas fa-undo" style="margin-right: 5px;"></i> Reset All to Default
                                        </div>
                                    </div>
                                </div>
                                ` : ''}

                                <div class="meal-macros">
                                    <span style="font-size: 16px; font-weight: 800; color: #fff;">${Math.round(rep.cal)}</span> 
                                    <span style="font-size: 12px; font-weight: 700;">kcal</span> • 
                                    Carbs: ${Math.round(rep.c)}g • Prot: ${Math.round(rep.p)}g • Fats: ${Math.round(rep.f)}g
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                                <div class="check-circle" style="margin-left:10px;">${rep.isDone ? '<i class="fas fa-check"></i>' : ''}</div>
                                ${!isReadOnly ? `<i class="fas fa-times" onclick="event.stopPropagation(); removeAlternative(${rep.globalIdx})" style="color: #666; cursor: pointer; font-size: 12px;"></i>` : ''}
                            </div>
                        `;
                        mealList.appendChild(card);
                    });

                } else {
                    // RENDER DEFAULT MEAL
                    const card = document.createElement('div');
                    card.className = `meal-card ${isDone ? 'completed' : ''}`;
                    card.onclick = () => toggleMeal(idx);
                    card.innerHTML = `
                <div class="meal-info">
                    <div class="meal-name">${meal.name} ${meal.qty ? `<span style="font-size: 14px; color: var(--accent-yellow); font-weight: 500; margin-left: 8px;">(${meal.qty})</span>` : ''}</div>
                    ${meal.desc ? `<div class="meal-desc">${meal.desc}</div>` : ''}
                    
                    ${!isReadOnly ? `
                    <div class="options-trigger" onclick="event.stopPropagation(); toggleOptions(this)" style="font-size: 11px; color: var(--accent-yellow); cursor: pointer; margin-bottom: 12px; display: inline-flex; align-items: center; gap: 5px; opacity: 0.8;">
                        <i class="fas fa-plus-circle"></i> View & Add Alternatives
                    </div>` : ''}
                    <div class="meal-options-list" style="display: none; padding-bottom: 15px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                            ${meal.options.map(opt => {
                        const food = foodDatabase.find(f => f.name.toLowerCase() === opt.toLowerCase());
                        return `
                                <div onclick="event.stopPropagation(); addFromAlternatives('${opt}', '${meal.time}', ${idx})" 
                                     style="cursor: pointer; font-size: 11px; background: rgba(255,255,255,0.03); padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s;"
                                     onmouseover="this.style.background='rgba(255,193,7,0.1)'; this.style.borderColor='var(--accent-yellow)';"
                                     onmouseout="this.style.background='rgba(255,255,255,0.03)'; this.style.borderColor='rgba(255,255,255,0.05)';"
                                >
                                    <div style="color: var(--text-primary); font-weight: 600; margin-bottom: 3px;">
                                        <i class="fas fa-plus" style="font-size: 8px; color: var(--accent-yellow); margin-right: 5px;"></i> ${opt}
                                    </div>
                                    ${food ? `
                                    <div style="color: #9CA3AF; font-size: 10px; display: flex; gap: 5px; opacity: 0.8;">
                                        <span>${food.cal} kcal</span> | <span>P: ${food.p}g</span> | <span>C: ${food.c}g</span> | <span>F: ${food.f}g</span>
                                    </div>
                                    ` : ''}
                                </div>
                            `;
                    }).join('')}
                        </div>
                    </div>

                    <div class="meal-macros">
                        <span style="font-size: 16px; font-weight: 800; color: #fff;">${Math.round(meal.cal)}</span> 
                        <span style="font-size: 12px; font-weight: 700;">kcal</span> • 
                        Carbs: ${Math.round(meal.c)}g • Prot: ${Math.round(meal.p)}g • Fats: ${Math.round(meal.f)}g
                    </div>
                </div>
                <div class="check-circle">${isDone ? '<i class="fas fa-check"></i>' : ''}</div>
            `;
                    mealList.appendChild(card);
                }
            });

            // Water Tracker UI Update
            updateWaterUI();

            // Handle Custom Meals Rendering
            const customList = document.getElementById('customMealList');
            customList.innerHTML = '';
            const dayCustom = customMeals[currentDay] || [];

            dayCustom.forEach((meal, idx) => {
                if (meal.replaces !== undefined && meal.replaces !== -1) return; // Skip replacements here

                const item = document.createElement('div');
                item.className = 'custom-log-item';
                item.innerHTML = `
                    <div>
                        <div style="font-weight:700;">${meal.name}</div>
                        <div style="font-size:12px; color:var(--text-secondary);">
                            ${meal.qty}${meal.unit} • ${Math.round(meal.cal)} kcal • P: ${Math.round(meal.p)}g C: ${Math.round(meal.c)}g F: ${Math.round(meal.f)}g
                        </div>
                    </div>
                    ${!isReadOnly ? `<i class="fas fa-trash delete-log" onclick="deleteCustomMeal(${idx})"></i>` : ''}
                `;
                customList.appendChild(item);
            });

            // Update Stats Panel
            const calEl = document.getElementById('totalCal');
            const proEl = document.getElementById('totalProt');
            const carbEl = document.getElementById('totalCarb');
            const fatEl = document.getElementById('totalFat');

            calEl.innerHTML = `<span style="font-size: 20px; font-weight: 800; color: ${totals.cal > DAILY_GOAL ? '#ff4d4d' : '#fff'}">${Math.round(totals.cal)}</span> <span style="font-size: 14px; color: #555; font-weight: 500;">/ ${DAILY_GOAL}</span> <span style="font-size: 14px; color: #fff; font-weight: 700;">kcal</span>`;
            proEl.innerHTML = `${Math.round(totals.p)}g<span class="target-txt">/ ${MACRO_GOALS.p}g</span>`;
            carbEl.innerHTML = `${Math.round(totals.c)}g<span class="target-txt">/ ${MACRO_GOALS.c}g</span>`;
            fatEl.innerHTML = `${Math.round(totals.f)}g<span class="target-txt">/ ${MACRO_GOALS.f}g</span>`;

            // Update Mini Bars
            document.getElementById('barCal').style.width = Math.min((totals.cal / DAILY_GOAL) * 100, 100) + '%';
            document.getElementById('barPro').style.width = Math.min((totals.p / MACRO_GOALS.p) * 100, 100) + '%';
            document.getElementById('barCarb').style.width = Math.min((totals.c / MACRO_GOALS.c) * 100, 100) + '%';
            document.getElementById('barFat').style.width = Math.min((totals.f / MACRO_GOALS.f) * 100, 100) + '%';

            // Check if exceeded (Turn red if over target)
            calEl.classList.toggle('exceeded', totals.cal > DAILY_GOAL);
            proEl.classList.toggle('exceeded', totals.p > MACRO_GOALS.p);
            carbEl.classList.toggle('exceeded', totals.c > MACRO_GOALS.c);
            fatEl.classList.toggle('exceeded', totals.f > MACRO_GOALS.f);



            // Show Popup if calorie goal reached
            if (totals.cal >= DAILY_GOAL && !popupsShown[currentDay]) {
                // Show Grand Prize Modal on Day 30, regular modal otherwise
                if (currentDay === 30) {
                    showGrandPrizeModal();
                } else {
                    showGoalModal();
                }
                popupsShown[currentDay] = true;
            }

            // Highlight tab if day complete (e.g., all 7 meals)
            const tab = document.querySelectorAll('.day-tab')[currentDay - 1];
            if (completed.length === meals.length && meals.length > 0 && tab) {
                tab.classList.add('completed');
            } else if (tab) {
                tab.classList.remove('completed');
            }

            // Update Recommendations
            updateRecommendations(totals);

            // Update Meal Split UI
            const splitList = document.getElementById('mealSplitList');
            if (splitList) {
                splitList.innerHTML = '';
                // Correctly Aggegrate Logged Calories per Slot
                const loggedPerSlot = {};
                Object.keys(weights).forEach(time => loggedPerSlot[time] = 0);

                // Add from Diet Plan (Handling Replacements)
                meals.forEach((meal, idx) => {
                    const slot = meal.time;
                    const slotReplacements = (customMeals[currentDay] || []).filter(cm => cm.replaces === idx);
                    
                    if (slotReplacements.length > 0) {
                        slotReplacements.forEach(rep => { if (rep.isDone) loggedPerSlot[slot] += rep.cal; });
                    } else if (completed.includes(idx)) {
                        loggedPerSlot[slot] += meal.cal;
                    }
                });

                // Add Non-Replacement Custom Meals
                (customMeals[currentDay] || []).forEach(cm => {
                    if (cm.replaces === undefined || cm.replaces === -1) {
                        const slot = cm.time || "Lunch";
                        loggedPerSlot[slot] = (loggedPerSlot[slot] || 0) + cm.cal;
                    }
                });

                Object.keys(weights).forEach(time => {
                    const target = Math.round(DAILY_GOAL * weights[time]);
                    const logged = Math.round(loggedPerSlot[time] || 0);
                    const percent = Math.min((logged / target) * 100, 100);

                    const item = document.createElement('div');
                    item.style.marginBottom = '12px';
                    item.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 6px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="color: #9CA3AF; font-size: 12px; font-weight: 600;">${time}</span>
                                ${!isReadOnly ? `<button onclick="openManualLogModal('${time}')" style="background:none; border:none; color:var(--accent-yellow); cursor:pointer; font-size:10px; opacity:0.6; padding:0;" title="Manual Log"><i class="fas fa-plus-circle"></i></button>` : ''}
                            </div>
                            <div style="text-align: right; line-height: 1;">
                                <span style="font-size: 18px; color: ${logged > target ? '#ff4d4d' : '#fff'}; font-weight: 800;">${logged}</span>
                                <span style="font-size: 14px; color: #555; font-weight: 500;">/ ${target}</span>
                                <span style="font-size: 14px; color: #fff; font-weight: 700;">kcal</span>
                            </div>
                        </div>
                        <div class="mini-pbar-bg" style="height: 4px; background: rgba(255,193,7,0.05); margin-top: 0;">
                            <div class="mini-pbar-fill" style="width: ${percent}%; background: ${logged > target ? '#ff4d4d' : 'var(--accent-yellow)'};"></div>
                        </div>
                    `;
                    splitList.appendChild(item);
                });
            }






            // Toggle Global Buttons based on ReadOnly
            if (isReadOnly) {
                saveBtn.style.display = 'none';
                document.getElementById('resetBtn').style.display = 'none';

                // Hide custom tracker container or disable inputs
                document.querySelector('.custom-tracker-container').style.opacity = '0.5';
                document.querySelector('.custom-tracker-container').style.pointerEvents = 'none';

            } else {
                saveBtn.style.display = 'block';
                document.getElementById('resetBtn').style.display = 'block';

                document.querySelector('.custom-tracker-container').style.opacity = '1';
                document.querySelector('.custom-tracker-container').style.pointerEvents = 'auto';
            }
        }

        // NEW HELPERS
        let activeModalSlot = "";
        let activeModalReplaces = -1;

        function openManualLogModal(slot, replacesIdx = -1) {
            activeModalSlot = slot;
            activeModalReplaces = replacesIdx;
            document.getElementById('modalSlotTitle').textContent = slot;
            document.getElementById('foodSearchModal').classList.add('active');
            renderModalFoods();
            document.getElementById('modalFoodSearch').value = '';
            document.getElementById('modalFoodSearch').focus();
        }

        function closeFoodModal() {
            document.getElementById('foodSearchModal').classList.remove('active');
        }

        function filterModalFoods(query) {
            renderModalFoods(query);
        }

        function renderModalFoods(filter = "") {
            const list = document.getElementById('modalFoodList');
            list.innerHTML = '';
            const query = filter.toLowerCase().trim();

            const filtered = foodDatabase.filter(f => f.name.toLowerCase().includes(query));

            filtered.forEach(food => {
                const item = document.createElement('div');
                item.className = 'food-grid-item';
                item.onclick = () => {
                    addFromAlternatives(food.name, activeModalSlot, activeModalReplaces);
                    closeFoodModal();
                };
                item.innerHTML = `
                    <div class="food-details">
                        <h4>${food.name}</h4>
                        <p>${food.cal} kcal | P: ${food.p}g | C: ${food.c}g | F: ${food.f}g per 100${food.unit}</p>
                    </div>
                    <div class="add-icon"><i class="fas fa-plus-circle"></i></div>
                `;
                list.appendChild(item);
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div style="text-align:center; padding:40px; color:#666;">No items found for "${filter}"</div>`;
            }
        }

        function toggleOptions(el) {
            const list = el.nextElementSibling;
            const icon = el.querySelector('i');
            if (list.style.display === 'none') {
                list.style.display = 'block';
                icon.className = 'fas fa-chevron-down';
                el.style.opacity = '1';
            } else {
                list.style.display = 'none';
                icon.className = 'fas fa-chevron-right';
                el.style.opacity = '0.8';
            }
        }

        function addFromAlternatives(foodName, slotName = "Lunch", replacesIdx = -1) {
            if (currentDay < activeDay) return;
            const food = foodDatabase.find(f => f.name.toLowerCase() === foodName.toLowerCase());

            // Auto-select the slot in the form even if found (for consistency)
            document.getElementById('mealSlot').value = slotName;

            if (food) {
                let qty = (food.unit === 'g' || food.unit === 'ml') ? 100 : 1;
                const ratio = (food.unit === 'g' || food.unit === 'ml') ? qty / 100 : qty;

                const newMeal = {
                    name: food.name,
                    qty: qty,
                    unit: food.unit,
                    cal: food.cal * ratio,
                    p: food.p * ratio,
                    c: food.c * ratio,
                    f: food.f * ratio,
                    time: slotName,
                    replaces: replacesIdx, // LINK TO DEFAULT MEAL
                    isDone: false // START UNCHECKED
                };

                if (!customMeals[currentDay]) customMeals[currentDay] = [];

                // NO LONGER REMOVING EXISTING REPLACEMENTS, ALLOWING MULTIPLE

                customMeals[currentDay].push(newMeal);
                updateUI();
                autoSave(currentDay);
                showNotify(food.name + ' added!', 'success');
            } else {
                foodSearch.value = foodName;
                document.getElementById('selectedFoodName').value = foodName;
                document.getElementById('foodQty').value = 100;
                document.getElementById('manualCal').value = '';
                document.getElementById('manualProt').value = '';
                document.getElementById('manualCarb').value = '';
                document.getElementById('manualFat').value = '';

                logForm.style.display = 'grid';
                activeFood = null; // Mark as manual
                document.querySelector('.custom-tracker-container').scrollIntoView({ behavior: 'smooth' });
                showNotify('Item not in DB. Please fill macros manually!', 'info');
                setTimeout(() => document.getElementById('manualCal').focus(), 500);
            }
        }

        function toggleCustomMeal(globalIdx) {
            if (currentDay < activeDay) return;
            if (!customMeals[currentDay][globalIdx]) return;
            customMeals[currentDay][globalIdx].isDone = !customMeals[currentDay][globalIdx].isDone;
            updateUI();
            autoSave(currentDay);
        }

        function removeAlternative(globalIdx) {
            if (currentDay < activeDay) return;
            if (!customMeals[currentDay]) return;
            customMeals[currentDay].splice(globalIdx, 1);
            updateUI();
            autoSave(currentDay);
        }

        function resetToDefault(replacesIdx) {
            if (currentDay < activeDay) return;
            if (!customMeals[currentDay]) return;
            customMeals[currentDay] = customMeals[currentDay].filter(cm => cm.replaces !== replacesIdx);
            updateUI();
            autoSave(currentDay);
            showNotify('Restored default meal', 'info');
        }






        // FOOD SEARCH & LOGGING
        const foodSearch = document.getElementById('foodSearch');
        const foodResults = document.getElementById('foodResults');
        const logForm = document.getElementById('logForm');
        let activeFood = null;

        foodSearch.onfocus = () => {
            const query = foodSearch.value.toLowerCase().trim();
            if (!query) {
                const defaults = foodDatabase.slice(0, 5);
                renderFoodResults(defaults, "", true);
                foodResults.style.display = 'block';
            }
        };

        foodSearch.oninput = (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (!query) {
                const defaults = foodDatabase.slice(0, 5);
                renderFoodResults(defaults, "", true);
                return;
            }

            const matches = foodDatabase.filter(f => f.name.toLowerCase().includes(query)).slice(0, 8);
            renderFoodResults(matches, query);
            foodResults.style.display = 'block';
        };

        function renderFoodResults(foods, query, isDefault = false) {
            foodResults.innerHTML = isDefault ? '<div style="font-size:10px; color:#555; padding:8px 20px; text-transform:uppercase; letter-spacing:1px; font-weight:700;">Suggested Foods</div>' : '';

            // Show matches
            foods.forEach(food => {
                const div = document.createElement('div');
                div.className = 'food-item';
                div.innerHTML = `<span>${food.name}</span> <span style="font-size:11px; color:#666;">${food.cal} cal / 100${food.unit}</span>`;
                div.onclick = () => selectFood(food);
                foodResults.appendChild(div);
            });

            if (query) {
                const manualDiv = document.createElement('div');
                manualDiv.className = 'food-item';
                manualDiv.style.borderTop = '1px dashed #444';
                manualDiv.innerHTML = `<span><i class="fas fa-plus-circle"></i> Log "<b>${query}</b>" manually</span>`;
                manualDiv.onclick = () => {
                    activeFood = null;
                    document.getElementById('selectedFoodName').value = query;
                    document.getElementById('foodQty').value = 100;
                    document.getElementById('manualCal').value = '';
                    document.getElementById('manualProt').value = '';
                    document.getElementById('manualCarb').value = '';
                    document.getElementById('manualFat').value = '';
                    foodResults.style.display = 'none';
                    logForm.style.display = 'grid';
                    document.getElementById('manualCal').focus();
                };
                foodResults.appendChild(manualDiv);
            }
        };

        function selectFood(food) {
            activeFood = food;
            document.getElementById('selectedFoodName').value = food.name;
            document.getElementById('foodQty').value = 100;
            document.getElementById('manualCal').value = food.cal;
            document.getElementById('manualProt').value = food.p;
            document.getElementById('manualCarb').value = food.c;
            document.getElementById('manualFat').value = food.f;

            foodResults.style.display = 'none';
            logForm.style.display = 'grid';
        }

        document.getElementById('addFoodBtn').onclick = () => {
            const name = document.getElementById('selectedFoodName').value;
            const qty = parseFloat(document.getElementById('foodQty').value);
            const slot = document.getElementById('mealSlot').value;

            // Manual values
            const mCal = parseFloat(document.getElementById('manualCal').value || 0);
            const mProt = parseFloat(document.getElementById('manualProt').value || 0);
            const mCarb = parseFloat(document.getElementById('manualCarb').value || 0);
            const mFat = parseFloat(document.getElementById('manualFat').value || 0);

            if (!name || !qty || (!activeFood && mCal === 0)) {
                showNotify('Please fill name, qty and calories', 'error');
                return;
            }

            let newMeal;
            if (activeFood) {
                const ratio = (activeFood.unit === 'g' || activeFood.unit === 'ml') ? qty / 100 : qty;
                newMeal = {
                    name: activeFood.name,
                    qty: qty,
                    unit: activeFood.unit,
                    cal: activeFood.cal * ratio,
                    p: activeFood.p * ratio,
                    c: activeFood.c * ratio,
                    f: activeFood.f * ratio,
                    time: slot
                };
            } else {
                // Fully manual
                newMeal = {
                    name: name,
                    qty: qty,
                    unit: 'g', // Default unit for manual
                    cal: mCal,
                    p: mProt,
                    c: mCarb,
                    f: mFat,
                    time: slot
                };
            }

            if (!customMeals[currentDay]) customMeals[currentDay] = [];
            customMeals[currentDay].push(newMeal);

            // Reset UI
            foodSearch.value = '';
            logForm.style.display = 'none';
            activeFood = null;

            updateUI();
            autoSave(currentDay);
            showNotify('Logged to ' + slot, 'success');
        };

        function deleteCustomMeal(idx) {
            if (currentDay < activeDay) return;
            customMeals[currentDay].splice(idx, 1);
            updateUI();
            autoSave(currentDay);
        }

        function updateRecommendations(totals) {
            const recGrid = document.getElementById('recGrid');
            recGrid.innerHTML = '';

            const remCal = DAILY_GOAL - totals.cal;
            const remP = MACRO_GOALS.p - totals.p;

            if (remCal <= 0) {
                document.getElementById('recContainer').style.display = 'none';
                return;
            }
            document.getElementById('recContainer').style.display = 'block';

            // Filter foods based on remaining needs (prioritize protein if needed)
            let recs = [];
            if (remP > 20) {
                recs = foodDatabase.filter(f => (f.p / f.cal * 100) > 6).slice(0, 4); // High protein
            } else {
                recs = foodDatabase.filter(f => f.cal < 150).slice(0, 4); // Low cal / snacks
            }

            recs.forEach(f => {
                const card = document.createElement('div');
                card.className = 'rec-card';
                card.innerHTML = `
                    <div class="rec-name">${f.name}</div>
                    <div class="rec-macro">${f.p}g Protein • ${f.cal} kcal</div>
                    <div style="font-size:9px; color:#444; margin-top:5px;">Per 100${f.unit}</div>
                `;
                recGrid.appendChild(card);
            });
        }

        // Toggle Meal
        function toggleMeal(idx) {
            if (currentDay < activeDay) return;
            if (!completedMeals[currentDay]) completedMeals[currentDay] = [];

            const index = completedMeals[currentDay].indexOf(idx);
            if (index > -1) {
                completedMeals[currentDay].splice(index, 1);
            } else {
                completedMeals[currentDay].push(idx);
            }
            updateUI();

            // Auto-save on toggle
            autoSave(currentDay);
        }

        // Auto-save function
        let autoSaveTimeout;
        function autoSave(day) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveProgress(day, true); // silent save
            }, 500);
        }

        // Save Progress Logic
        async function saveProgress(targetDay, silent = false) {
            const dayToSave = targetDay || currentDay;
            if (dayToSave < activeDay) return; // Prevent saving for past days

            if (!silent) saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const totals = getDayTotals(dayToSave);
            const saveTotals = {
                calories: Math.round(totals.cal),
                protein: Math.round(totals.p),
                carbs: Math.round(totals.c),
                fat: Math.round(totals.f)
            };

            const res = await sendRequest('save_progress', {
                day: dayToSave,
                diet_type: currentDiet,
                completed_meals: JSON.stringify(completedMeals[dayToSave] || []),
                custom_meals: JSON.stringify(customMeals[dayToSave] || []),
                water_liters: waterIntake[dayToSave] || 0,
                totals: JSON.stringify(saveTotals)
            });

            if (!silent) {
                saveBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save Progress';
                if (res.success) {
                    showNotify('Day ' + dayToSave + ' Saved!', 'success');
                } else {
                    showNotify('Save Failed', 'error');
                }
            }
        }

        // Save Button Click
        saveBtn.onclick = () => {
            if (currentDay >= activeDay) saveProgress(currentDay);
        };

        // Reset Day
        document.getElementById('resetBtn').onclick = () => {
            if (currentDay < activeDay) return;
            if (confirm("Reset tracking for Day " + currentDay + "?")) {
                const dayToReset = currentDay;
                completedMeals[dayToReset] = [];
                customMeals[dayToReset] = [];
                waterIntake[dayToReset] = 0;

                updateUI();

                // Persistent reset: save the empty state immediately
                saveProgress(dayToReset, true);
                showNotify('Day ' + dayToReset + ' Resetted', 'success');
            }
        };

        // Diet Toggles
        document.getElementById('vegBtn').onclick = () => {
            currentDiet = 'veg';
            document.getElementById('vegBtn').classList.add('active');
            document.getElementById('nonVegBtn').classList.remove('active');
            updateUI();
        };
        document.getElementById('nonVegBtn').onclick = () => {
            currentDiet = 'nonVeg';
            document.getElementById('nonVegBtn').classList.add('active');
            document.getElementById('vegBtn').classList.remove('active');
            updateUI();
        };

        // Initial Call
        init();

        // Utility: Send Post Request
        async function sendRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                return await res.json();
            } catch (e) {
                console.error(e);
                return { success: false };
            }
        }

        function showNotify(msg, type) {
            notification.textContent = msg;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
            setTimeout(() => { notification.style.display = 'none'; }, 3000);
        }

        function showGoalModal() {
            document.getElementById('modalDayText').textContent = currentDay;
            document.getElementById('goalModal').classList.add('active');
        }

        function closeGoalModal() {
            document.getElementById('goalModal').classList.remove('active');
        }

        function showGrandPrizeModal() {
            document.getElementById('grandPrizeModal').classList.add('active');
        }

        function closeGrandPrizeModal() {
            document.getElementById('grandPrizeModal').classList.remove('active');
        }

        // ==================== WATER TRACKER LOGIC ====================
        function adjustWater(amount) {
            if (currentDay < activeDay) return;
            const current = waterIntake[currentDay] || 0;
            waterIntake[currentDay] = Math.max(0, current + amount);
            updateUI();
            autoSave(currentDay);
        }

        function resetWater() {
            if (currentDay < activeDay) return;
            if (confirm("Reset hydration for today?")) {
                waterIntake[currentDay] = 0;
                updateUI();
                autoSave(currentDay);
            }
        }

        function updateWaterUI() {
            const current = waterIntake[currentDay] || 0;
            document.getElementById('waterTotal').textContent = current.toFixed(2);

            const grid = document.getElementById('glassesGrid');
            grid.innerHTML = '';

            const totalGlasses = 12;
            const glassesDone = Math.floor(current / 0.25);

            for (let i = 1; i <= totalGlasses; i++) {
                const glass = document.createElement('div');
                glass.className = `glass-btn ${i <= glassesDone ? 'active' : ''}`;
                glass.innerHTML = `<i class="fas fa-glass-water"></i>`;
                glass.onclick = () => {
                    if (currentDay < activeDay) return;
                    waterIntake[currentDay] = i * 0.25;
                    updateUI();
                    autoSave(currentDay);
                };
                grid.appendChild(glass);
            }
        }


    </script>

    <?php include 'footer.php'; ?>

</body>

</html>