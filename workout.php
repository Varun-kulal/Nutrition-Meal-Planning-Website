<?php
session_start();

// Strict Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database Config
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle AJAX Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_workout') {
    header('Content-Type: application/json');
    $ex_name = $_POST['exercise_name'];
    $reps = intval($_POST['reps']);
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO workout_logs (user_id, exercise_name, reps_completed) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $user_id, $ex_name, $reps);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workout Zone | NutriPlan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1A1A1A;
            --bg-card: #1E1E1E;
            --accent-yellow: #FFC107;
            --accent-blue: #2196F3;
            --text-primary: #FFFFFF;
            --text-secondary: #B0B0B0;
            --success: #10B981;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 60px;
        }

        /* NAVBAR */
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
            margin: 0;
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
            margin: 0;
            padding: 0;
        }

        .nav-menu li a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            transition: 0.2s;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            color: var(--accent-yellow);
        }

        .logout-btn {
            border: 2px solid var(--accent-yellow);
            padding: 8px 24px;
            border-radius: 6px;
            color: var(--accent-yellow) !important;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background-color: var(--accent-yellow);
            color: var(--bg-primary) !important;
        }

        /* HEADER */
        .page-header {
            background: radial-gradient(circle at center, #222 0%, #121212 70%);
            padding: 80px 0;
            text-align: center;
            border-bottom: 1px solid #333;
        }

        .page-header h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .page-header span {
            color: var(--accent-yellow);
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* VIEWS CONTROL */
        .view-section {
            display: none;
            padding: 60px 0;
            animation: fadeIn 0.4s ease;
        }

        .view-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* CATEGORIES GRID */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .cat-card {
            background: var(--bg-card);
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s;
            height: 300px;
            position: relative;
        }

        .cat-card:hover {
            transform: translateY(-10px);
            border-color: var(--accent-yellow);
            box-shadow: 0 10px 40px rgba(255, 193, 7, 0.2);
        }

        .cat-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.6;
            transition: 0.3s;
        }

        .cat-card:hover .cat-img {
            opacity: 0.3;
            transform: scale(1.1);
        }

        .cat-info {
            position: absolute;
            bottom: 0;
            left: 0;
            padding: 25px;
            width: 100%;
            background: linear-gradient(to top, #000, transparent);
        }

        .cat-info h3 {
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
            font-weight: 800;
        }

        .cat-info p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 5px;
        }

        /* EXERCISE LIST */
        .back-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 40px;
            transition: 0.2s;
        }

        .back-btn:hover {
            color: var(--accent-yellow);
        }

        .exercise-list {
            display: grid;
            gap: 30px;
        }

        .ex-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            gap: 30px;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .ex-thumb {
            width: 120px;
            height: 120px;
            background: #000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--accent-yellow);
            flex-shrink: 0;
        }

        .ex-thumb-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .ex-details {
            flex: 1;
        }

        .ex-details h3 {
            font-size: 22px;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .ex-details p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .tags {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .tag {
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .reps-tag {
            background: var(--accent-yellow);
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .ex-action {
            text-align: right;
        }

        .start-btn {
            background: var(--accent-yellow);
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .start-btn:hover {
            transform: scale(1.05);
            background: #FFB300;
        }

        /* TIMER MODAL (GAMIFIED HUD) */
        .timer-overlay {
            position: fixed;
            inset: 0;
            background: #000;
            z-index: 3000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow: hidden;
        }

        .hud-video-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.4;
            z-index: -1;
        }

        .hud-container {
            width: 100%;
            padding: 0 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            height: 80vh;
        }

        .hud-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }

        .hud-center {
            position: relative;
            width: 400px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hud-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 20px;
        }

        /* SVG CIRCULAR TIMER */
        .timer-svg {
            width: 380px;
            height: 380px;
            transform: rotate(-90deg);
        }

        .timer-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 15;
        }

        .timer-progress {
            fill: none;
            stroke: var(--accent-yellow);
            stroke-width: 15;
            stroke-linecap: round;
            stroke-dasharray: 1131;
            /* 2 * PI * 180 */
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear, stroke 0.3s ease;
            filter: drop-shadow(0 0 10px var(--accent-yellow));
        }

        .timer-text-container {
            position: absolute;
            text-align: center;
        }

        .timer-countdown {
            font-size: 80px;
            font-weight: 800;
            display: block;
            font-family: monospace;
            text-shadow: 0 0 20px rgba(255, 193, 7, 0.5);
        }

        .timer-countdown span {
            font-size: 40px;
            color: var(--text-secondary);
            margin-right: -10px;
        }

        .timer-label {
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--text-secondary);
        }

        .hud-card {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 12px;
            border-left: 4px solid var(--accent-yellow);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hud-title {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            color: var(--accent-yellow);
        }

        .hud-desc {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 10px 0 0;
            max-width: 300px;
        }

        .hud-stat {
            text-align: right;
        }

        .hud-stat-val {
            font-size: 40px;
            font-weight: 800;
            display: block;
        }

        .hud-stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .hud-controls {
            position: absolute;
            bottom: 40px;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 30px;
        }

        .hud-btn {
            background: transparent;
            border: 2px solid #fff;
            color: #fff;
            padding: 12px 40px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .hud-btn:hover {
            background: #fff;
            color: #000;
        }

        .hud-btn-done {
            border-color: var(--success);
            color: var(--success);
        }

        .hud-btn-done:hover {
            background: var(--success);
            color: #000;
        }

        /* VICTORY OVERLAY */
        .victory-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 4000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            backdrop-filter: blur(20px);
        }

        .victory-card {
            background: var(--bg-card);
            padding: 50px;
            border-radius: 24px;
            border: 2px solid var(--success);
            box-shadow: 0 0 50px rgba(16, 185, 129, 0.3);
            max-width: 500px;
            width: 90%;
            animation: victoryPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes victoryPop {
            from {
                transform: scale(0.5);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .victory-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
            display: block;
        }

        .victory-title {
            font-size: 48px;
            font-weight: 800;
            margin: 0 0 10px;
            color: #fff;
            text-transform: uppercase;
        }

        .victory-stats {
            margin: 30px 0;
            display: flex;
            justify-content: center;
            gap: 40px;
        }

        .vic-stat-val {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent-yellow);
            display: block;
        }

        .vic-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        @media(max-width: 1024px) {
            .hud-container {
                flex-direction: column;
                justify-content: center;
                height: auto;
                gap: 40px;
            }

            .hud-right {
                align-items: center;
            }

            .hud-left {
                align-items: center;
                text-align: center;
            }

            .hud-desc {
                max-width: 100%;
            }
        }

        /* GENDER TOGGLE */
        .gender-toggle {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 20px;
        }

        .gender-option {
            background: var(--bg-card);
            border: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
        }

        .gender-option.active {
            background: var(--accent-yellow);
            color: #000;
            border-color: var(--accent-yellow);
            transform: scale(1.05);
        }

        .gender-option:hover {
            border-color: var(--accent-yellow);
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Nutri<span>Plan</span></h2>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="dailychallenge.php">Daily Challenge</a></li>
                <li><a href="product.php">Supplement Order</a></li>
                <li><a href="personaldashbord.php">Dashboard</a></li>
                <li><a href="workout.php" class="active">Workout</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="user_logout.php" class="logout-btn">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- HERO -->
    <div class="page-header">
        <div class="container">
            <h1>Train Like a <span>Pro</span></h1>
            <p>Select a workout category to start your session.</p>
        </div>
    </div>

    <!-- CATEGORIES VIEW -->
    <div class="view-section active" id="categoriesView">
        <div class="container">

            <div class="gender-toggle">
                <button class="gender-option active" id="btnMen" onclick="switchGender('men')">MEN</button>
                <button class="gender-option" id="btnWomen" onclick="switchGender('women')">WOMEN</button>
            </div>

            <!-- MEN CATEGORIES -->
            <div class="cat-grid" id="menCategories">
                <div class="cat-card" onclick="openWorkout('fullbody')">
                    <img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Full Body (Men)</h3>
                        <p>Total body conditioning, no equipment needed.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('chest')">
                    <img src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Chest</h3>
                        <p>Build power and definition.</p>
                    </div>
                </div>
                <!-- More men categories if needed, for new keep logic same -->
                <div class="cat-card" onclick="openWorkout('back')">
                    <img src="https://images.unsplash.com/photo-1605296867304-46d5465a13f1?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Back & Lats</h3>
                        <p>Build a wider, stronger back.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('arms')">
                    <img src="https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Biceps & Triceps</h3>
                        <p>Sculpt your arms with focused movements.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('legs')">
                    <img src="young-man-training-on-a-leg-machine-in-the-gym-royalty-free-image-1704212901.avif"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Legs</h3>
                        <p>Strength foundation for lower body.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('abs')">
                    <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Abs & Core</h3>
                        <p>Strengthen your core stability.</p>
                    </div>
                </div>
            </div>

            <!-- WOMEN CATEGORIES -->
            <div class="cat-grid" id="womenCategories" style="display: none;">
                <div class="cat-card" onclick="openWorkout('fullbody')">
                    <img src="uploads/WhatsApp Image 2026-02-03 at 9.41.39 AM.jpeg" class="cat-img">
                    <div class="cat-info">
                        <h3>Full Body (Women)</h3>
                        <p>Lean muscle and toning routine.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('abs')">
                    <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Core & Pilates</h3>
                        <p>Strengthen inner core and stability.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('legs')">
                    <img src="uploads/WhatsApp Image 2026-02-03 at 9.49.17 AM.jpeg" class="cat-img">
                    <div class="cat-info">
                        <h3>Legs & Glutes</h3>
                        <p>Shape and tone lower body.</p>
                    </div>
                </div>
                <!-- Reusing same workout logic for demo purposes, can extend 'workouts' object for women specifically later -->
                <div class="cat-card" onclick="openWorkout('arms')">
                    <img src="https://images.unsplash.com/photo-1518310383802-640c2de311b2?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Toned Arms</h3>
                        <p>Light weights for definition.</p>
                    </div>
                </div>
                <div class="cat-card" onclick="openWorkout('back')">
                    <img src="https://images.unsplash.com/photo-1605296867304-46d5465a13f1?w=500&auto=format&fit=crop&q=60"
                        class="cat-img">
                    <div class="cat-info">
                        <h3>Toned Back</h3>
                        <p>Posture correction and strengthening.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- EXERCISES VIEW -->
    <div class="view-section" id="exerciseView">
        <div class="container">
            <button class="back-btn" onclick="showCategories()"><i class="fas fa-arrow-left"></i> Back to
                Categories</button>
            <h2 id="workoutTitle" style="margin-bottom: 30px; font-size: 32px;">Full Body Routine</h2>

            <div class="exercise-list" id="exerciseList">
                <!-- Injected via JS -->
            </div>
        </div>
    </div>

    <!-- TIMER OVERLAY (GAMIFIED HUD) -->
    <div class="timer-overlay" id="timerModal">
        <!-- Background Video -->
        <video id="hudVideo" class="hud-video-bg" autoplay loop muted></video>

        <div class="hud-container">
            <!-- Left Info Pane -->
            <div class="hud-left">
                <div class="hud-card">
                    <h2 class="hud-title" id="hudExName">Jumping Jacks</h2>
                    <p class="hud-desc" id="hudExDesc">Start with feet together and hands by your side. Jump up and
                        raise hands.</p>
                </div>
                <div class="hud-card" style="border-left-color: var(--accent-blue);">
                    <p class="hud-desc"><strong>PRO TIP:</strong> Keep your core tight and maintain a steady rhythm.</p>
                </div>
            </div>

            <!-- Center SVG Timer -->
            <div class="hud-center">
                <svg class="timer-svg">
                    <circle class="timer-bg" cx="190" cy="190" r="180"></circle>
                    <circle class="timer-progress" id="timerProgress" cx="190" cy="190" r="180"></circle>
                </svg>
                <div class="timer-text-container">
                    <span class="timer-countdown" id="timerHUDVal">00:00</span>
                    <span class="timer-label">Time Remaining</span>
                </div>
            </div>

            <!-- Right Stat Pane -->
            <div class="hud-right">
                <div class="hud-card">
                    <div class="hud-stat">
                        <span class="hud-stat-val" id="hudExReps">30</span>
                        <span class="hud-stat-label">Target Reps</span>
                    </div>
                </div>
                <div class="hud-card" style="border-left-color: var(--success); text-align: right;">
                    <div class="hud-stat">
                        <span class="hud-stat-val" style="color: var(--success);">LVL 5</span>
                        <span class="hud-stat-label">Difficulty</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="hud-controls">
            <button class="hud-btn" onclick="closeTimer()">EXIT SESSION</button>
        </div>
    </div>

    <!-- VICTORY OVERLAY -->
    <div class="victory-overlay" id="victoryOverlay">
        <div class="victory-card">
            <span class="victory-icon">🏆</span>
            <h2 class="victory-title">Victory!</h2>
            <p style="color: var(--text-secondary);">Workout successfully logged to your profile.</p>

            <div class="victory-stats">
                <div>
                    <span class="vic-stat-val" id="vicExName">Exercise</span>
                    <span class="vic-stat-label">COMPLETED</span>
                </div>
                <div>
                    <span class="vic-stat-val" id="vicExReps">0</span>
                    <span class="vic-stat-label">REPS TOTAL</span>
                </div>
            </div>

            <button class="hud-btn hud-btn-done" style="width: 100%;" onclick="closeVictory()">AWESOME!</button>
        </div>
    </div>

    <script>
        let currentGender = 'men';

        const menWorkouts = {
            fullbody: [
                {
                    name: "Jumping Jacks",
                    desc: "Start with feet together and hands by your side. Jump up, spread feet and raise hands.",
                    tags: ["Cardio", "Warmup"],
                    icon: "🏃",
                    reps: 30,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-jumping-jack-fitness-exercise-workout-animation-male-muscle-highlight-demonstration-at-k.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3"
                },
                {
                    name: "Push-Ups",
                    desc: "Keep body straight, lower chest to floor, push back up.",
                    tags: ["Chest", "Triceps"],
                    icon: "💪",
                    reps: 15,
                    duration: 40,
                    video: "uploads/stock-footage-exercise-fitness-exercise-workout-animation-male-muscle-highlight-demonstration-at-k-resolution.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3"
                },
                {
                    name: "Squats",
                    desc: "Feet shoulder-width apart, lower hips back and down, stand back up.",
                    tags: ["Legs", "Glutes"],
                    icon: "🦵",
                    reps: 20,
                    duration: 50,
                    video: "uploads/stock-footage-barbell-low-bar-squat-d copy.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3"
                },
                {
                    name: "Plank",
                    desc: "Hold push-up position on elbows, keep core tight.",
                    tags: ["Core", "Stability"],
                    icon: "🧱",
                    reps: 1,
                    duration: 60,
                    video: "uploads/vedio/stock-footage-plank-on-elbows-fitness-exercise-workout-animation-male-muscle-highlight-demonstration-at-k.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3"
                }
            ],
            chest: [
                {
                    name: "dumbbell bench press",
                    desc: "This exercise works your upper chest, triceps, and hits a bit more of your shoulders",
                    tags: ["Chest"],
                    icon: "🏋️",
                    reps: 12,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-dumbbell-bench-press-exercise-d-video.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3"
                },
                {
                    name: "imcline hammer",
                    desc: "The incline dumbbell press effectively builds the upper pectoralis major, improves shoulder stability, and corrects muscle imbalances.",
                    tags: ["Chest"],
                    icon: "🏋️",
                    reps: 12,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-kettlebell-incline-hammer-press-chest-exercise-workout-animation-video-training-guide.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3"
                },
                {
                    name: "decline hammer",
                    desc: "The decline bench press with dumbbells is a powerful exercise that targets the lower portion of the chest while also working the shoulders and triceps",
                    tags: ["Chest"],
                    icon: "🏋️",
                    reps: 12,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-dumbbell-decline-hammer-press-chest-exercise-workout-animation-video-training-guide.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3"
                }

            ],
            arms: [
                {
                    name: "Bicep Curls",
                    desc: "Focus on controlled bicep contraction.",
                    tags: ["Arms"],
                    icon: "💪",
                    reps: 15,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-dumbbell-cross-body-curl-exercise-anatomy-of-fitness-and-bodybuilding-target-muscles-are-red-d.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-6.mp3"
                }
            ],
            legs: [
                {
                    name: "Leg Press",
                    desc: "Building strength on the leg press machine.",
                    tags: ["Legs"],
                    icon: "🏋️‍♂️",
                    reps: 20,
                    duration: 50,
                    video: "uploads/vedio/stock-footage-incline-degree-leg-press-machine-fitness-exercise-workout-animation-male-muscle-highlight.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3"
                }
            ],
            back: [
                {
                    name: "Pull-Ups",
                    desc: "Grip the bar comfortably. Pull your chin above the bar.",
                    tags: ["Lats", "Biceps"],
                    icon: "💪",
                    reps: 10,
                    duration: 45,
                    video: "uploads/vedio/stock-footage-pull-up-exercise-d-video.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-9.mp3"
                },
                {
                    name: "Superman",
                    desc: "Lie on stomach, lift arms and legs simultaneously. Hold.",
                    tags: ["Lower Back"],
                    icon: "🦸",
                    reps: 15,
                    duration: 30,
                    video: "uploads/vedio/stock-footage-around-the-world-superman-hold-fitness-exercise-workout-animation-male-muscle-highlight.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-10.mp3"
                }
            ],
            abs: [
                {
                    name: "Plank",
                    desc: "Standard core stability hold.",
                    tags: ["Abs"],
                    icon: "🧱",
                    reps: 1,
                    duration: 60,
                    video: "uploads/vedio/stock-footage-plank-on-elbows-fitness-exercise-workout-animation-male-muscle-highlight-demonstration-at-k.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3"
                }
            ]
        };

        const womenWorkouts = {
            fullbody: [
                {
                    name: "Jumping Jacks",
                    desc: "Start with feet together and hands by your side. Jump up, spread feet and raise hands.",
                    tags: ["Cardio", "Warmup"],
                    icon: "🏃‍♀️",
                    reps: 30,
                    duration: 45,
                    video: "uploads/vedio/84375-585180958_medium.mp4",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3"
                },
                {
                    name: "Push-Ups",
                    desc: "Classic push-up to build upper body strength.",
                    tags: ["Chest", "Triceps"],
                    icon: "💪",
                    reps: 12,
                    duration: 40,
                    video: "uploads/stock-footage-woman-workout-by-push-up-on-exercise-mat-video-about-fitness.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3"
                },
                {
                    name: "Squat Jumps",
                    desc: "Explosive movement for lower body power.",
                    tags: ["Legs", "Glutes"],
                    icon: "🦵",
                    reps: 20,
                    duration: 50,
                    video: "uploads/stock-footage-woman-doing-squat-jump-in-side-view-for-strengthens-entire-lower-body-cartoon-animation-about.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3"
                },
                {
                    name: "Diagonal Plank",
                    desc: "Core stability with a twist.",
                    tags: ["Core", "Stability"],
                    icon: "🧱",
                    reps: 1,
                    duration: 60,
                    video: "uploads/stock-footage-diagonal-plank-exercise-tutorial-female-workout-on-mat-fitness-woman-exercising-looped-d.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3"
                }
            ],
            chest: [
                {
                    name: "Push-Up Focus",
                    desc: "Female-specific push-up tutorial.",
                    tags: ["Chest"],
                    icon: "🏋️‍♀️",
                    reps: 12,
                    duration: 45,
                    video: "uploads/stock-footage-woman-workout-by-push-up-on-exercise-mat-video-about-fitness.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3"
                }
            ],
            arms: [
                {
                    name: "Bicep Curls",
                    desc: "Standing dumbbell curls for toned arms.",
                    tags: ["Arms"],
                    icon: "💪",
                    reps: 15,
                    duration: 45,
                    video: "uploads/stock-footage-standing-dumbbell-curl-exercise-tutorial-female-workout-on-mat-fitness-woman-exercising-looped.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-6.mp3"
                },
                {
                    name: "Tricep Kickbacks",
                    desc: "Isolate and tone the back of your arms.",
                    tags: ["Arms"],
                    icon: "💪",
                    reps: 15,
                    duration: 40,
                    video: "uploads/stock-footage-triceps-kickbacks-exercise-tutorial-female-workout-on-mat-fitness-woman-exercising-looped-d.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-6.mp3"
                }
            ],
            legs: [
                {
                    name: "Squats",
                    desc: "Perfect form squats for women.",
                    tags: ["Legs"],
                    icon: "🏋️‍♀️",
                    reps: 20,
                    duration: 50,
                    video: "uploads/stock-footage-squats-exercise-tutorial-female-workout-on-mat.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3"
                },
                {
                    name: "Plie Squats",
                    desc: "Target inner thighs and glutes.",
                    tags: ["Glutes", "Quads"],
                    icon: "🦵",
                    reps: 15,
                    duration: 45,
                    video: "uploads/stock-footage-plie-squats-exercise-tutorial-female-workout-on-mat.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3"
                },
                {
                    name: "Wall Sit",
                    desc: "Hold position against a wall to build endurance.",
                    tags: ["Legs"],
                    icon: "🦵",
                    reps: 1,
                    duration: 40,
                    video: "uploads/stock-footage-wall-sit-exercise-tutorial-female-workout-on-mat.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3"
                }
            ],
            back: [
                {
                    name: "Back Toning",
                    desc: "Strength training for a healthy back.",
                    tags: ["Lats"],
                    icon: "💪",
                    reps: 12,
                    duration: 45,
                    video: "uploads/stock-footage-strong-model-lifting-weight-equipment-at-grey-wall-background-young-sportswoman-bodybuilder-making.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-9.mp3"
                }
            ],
            abs: [
                {
                    name: "Mountain Climbers",
                    desc: "High-intensity core and cardio.",
                    tags: ["Abs"],
                    icon: "🏃‍♀️",
                    reps: 30,
                    duration: 45,
                    video: "uploads/stock-footage-woman-of-doing-the-mountain-climber-exercise-for-abs-workout-video-about-exercise-guide.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3"
                },
                {
                    name: "Leg In and Outs",
                    desc: "Seated core exercise for lower abs.",
                    tags: ["Abs"],
                    icon: "�",
                    reps: 20,
                    duration: 45,
                    video: "uploads/stock-footage-leg-in-and-outs-exercise-tutorial-female-workout-on-mat-fitness-woman-exercising-looped-d.webm",
                    music: "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3"
                }
            ]
        };

        // Data mapping
        const workoutsData = {
            men: menWorkouts,
            women: womenWorkouts
        };

        let currentEx = null;
        let timerInterval;
        const audioPlayer = new Audio();
        const svgCircumference = 2 * Math.PI * 180;

        function openWorkout(type) {
            // Use currentGender to select correct data
            const genderData = workoutsData[currentGender];
            const list = genderData[type] || genderData['fullbody'];

            const container = document.getElementById('exerciseList');
            document.getElementById('workoutTitle').innerText = type.charAt(0).toUpperCase() + type.slice(1) + " Workout";

            container.innerHTML = list.map((ex, index) => `
                <div class="ex-card">
                    ${ex.image ? `<img src="${ex.image}" class="ex-thumb-img">` : `<div class="ex-thumb">${ex.icon}</div>`}
                    <div class="ex-details">
                        <h3>${ex.name}</h3>
                        <p>${ex.desc}</p>
                        <div class="tags">
                            <span class="reps-tag">⚡ ${ex.reps} Reps</span>
                            <span class="tag" style="background: rgba(33, 150, 243, 0.2); color: #2196F3;"><i class="fas fa-stopwatch"></i> ${ex.duration}s</span>
                            ${ex.tags.map(t => `<span class="tag">${t}</span>`).join('')}
                        </div>
                    </div>
                    <div class="ex-action">
                        <button class="start-btn" onclick="startExercise('${type}', ${index})"><i class="fas fa-play"></i> Start</button>
                    </div>
                </div>
            `).join('');

            document.getElementById('categoriesView').classList.remove('active');
            document.getElementById('exerciseView').classList.add('active');
            window.scrollTo(0, 0);
        }

        function showCategories() {
            document.getElementById('exerciseView').classList.remove('active');
            document.getElementById('categoriesView').classList.add('active');
        }

        function switchGender(gender) {
            currentGender = gender; // Update state

            const menDiv = document.getElementById('menCategories');
            const womenDiv = document.getElementById('womenCategories');
            const btnMen = document.getElementById('btnMen');
            const btnWomen = document.getElementById('btnWomen');

            if (gender === 'men') {
                menDiv.style.display = 'grid';
                womenDiv.style.display = 'none';
                btnMen.classList.add('active');
                btnWomen.classList.remove('active');
            } else {
                menDiv.style.display = 'none';
                womenDiv.style.display = 'grid';
                btnMen.classList.remove('active');
                btnWomen.classList.add('active');
            }
        }

        function startExercise(type, index) {
            const genderData = workoutsData[currentGender];
            currentEx = genderData[type][index];

            const modal = document.getElementById('timerModal');
            const video = document.getElementById('hudVideo');
            const timerProgress = document.getElementById('timerProgress');

            // Set HUD Data
            document.getElementById('hudExName').innerText = currentEx.name;
            document.getElementById('hudExDesc').innerText = currentEx.desc;
            document.getElementById('hudExReps').innerText = currentEx.reps;

            // Initialize Progress Bar
            timerProgress.style.strokeDasharray = svgCircumference;
            timerProgress.style.strokeDashoffset = 0;

            // Background Video
            video.src = currentEx.video;
            video.play().catch(e => console.log("Video playback requires local files in videos/ folder"));

            // Background Music
            audioPlayer.src = currentEx.music;
            audioPlayer.play().catch(e => console.log("Audio deferred"));

            modal.style.display = 'flex';

            let timeLeft = currentEx.duration;
            updateHUD(timeLeft);

            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                timeLeft--;
                updateHUD(timeLeft);
                if (timeLeft <= 0) finishExercise();
            }, 1000);
        }

        function updateHUD(timeLeft) {
            const min = Math.floor(timeLeft / 60);
            const sec = timeLeft % 60;
            const minText = min < 10 ? '0' + min : min;
            const secText = sec < 10 ? '0' + sec : sec;

            document.getElementById('timerHUDVal').innerHTML = `<span>${minText}:</span>${secText}`;

            // Update SVG Ring
            const progress = (timeLeft / currentEx.duration);
            const offset = svgCircumference * (1 - progress);
            document.getElementById('timerProgress').style.strokeDashoffset = offset;

            // Change color based on time remaining
            const ring = document.getElementById('timerProgress');
            if (progress < 0.2) {
                ring.style.stroke = "#FF2E63"; // Red
                ring.style.filter = "drop-shadow(0 0 10px #FF2E63)";
            } else if (progress < 0.5) {
                ring.style.stroke = "#FFD700"; // Yellow
                ring.style.filter = "drop-shadow(0 0 10px #FFD700)";
            } else {
                ring.style.stroke = "#10B981"; // Green
                ring.style.filter = "drop-shadow(0 0 10px #10B981)";
            }
        }

        function closeTimer() {
            clearInterval(timerInterval);
            audioPlayer.pause();
            document.getElementById('hudVideo').pause();
            document.getElementById('timerModal').style.display = 'none';
        }

        function finishExercise() {
            closeTimer();
            if (!currentEx) return;

            const formData = new FormData();
            formData.append('action', 'log_workout');
            formData.append('exercise_name', currentEx.name);
            formData.append('reps', currentEx.reps);

            fetch('workout.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show Victory Screen
                        document.getElementById('vicExName').innerText = currentEx.name;
                        document.getElementById('vicExReps').innerText = currentEx.reps;
                        document.getElementById('victoryOverlay').style.display = 'flex';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function closeVictory() {
            document.getElementById('victoryOverlay').style.display = 'none';
        }
    </script>
</body>

</html>