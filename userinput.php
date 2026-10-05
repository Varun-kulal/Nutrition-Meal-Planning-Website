<?php
// Database configuration
session_start();
$host = 'localhost';

// Strict Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$username = 'root';
$password = '';
$database = 'fitness_app';

// Create connection
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select the database
if (!$conn->select_db($database)) {
    die("Error selecting database: " . $conn->error);
}

// Create tables
$sql = "CREATE TABLE IF NOT EXISTS usersinput (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    age INT NOT NULL,
    height DECIMAL(5,2) NOT NULL,
    height_unit ENUM('cm', 'ft') DEFAULT 'cm',
    current_weight DECIMAL(5,2) NOT NULL,
    target_weight DECIMAL(5,2) NOT NULL,
    weight_unit ENUM('kg', 'lb') DEFAULT 'kg',
    activity_level VARCHAR(50),
    medical_conditions TEXT,
    calorie_experience VARCHAR(50) NOT NULL,
    bmr DECIMAL(8,2),
    daily_calories DECIMAL(8,2),
    protein_g DECIMAL(8,2),
    carbs_g DECIMAL(8,2),
    fats_g DECIMAL(8,2),
    bmi_current DECIMAL(5,2),
    bmi_target DECIMAL(5,2),
    weight_difference DECIMAL(5,2),
    target_duration INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    // Self-healing: Ensure target_duration column exists if table already existed
    $conn->query("ALTER TABLE usersinput ADD COLUMN IF NOT EXISTS target_duration INT DEFAULT 3");

    // Add unique constraint on user_id if not already present
    $conn->query("ALTER TABLE usersinput ADD UNIQUE INDEX IF NOT EXISTS unique_user_id (user_id)");
}


// Helper functions
function calculateBMR($weight_kg, $height_cm, $age, $gender)
{
    if ($gender == 'Male') {
        return (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) + 5;
    } else {
        return (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) - 161;
    }
}

function calculateDailyCalories($bmr, $activity_level, $current_weight, $target_weight, $target_months)
{
    $activity_multipliers = [
        'Sedentary' => 1.2,
        'Lightly Active' => 1.375,
        'Moderately Active' => 1.55,
        'Very Active' => 1.725
    ];

    $tdee = $bmr * ($activity_multipliers[$activity_level] ?? 1.2);

    // Weight difference in kg
    $weight_diff = $current_weight - $target_weight;

    // Roughly 7700 calories per 1kg of weight change
    $total_cal_change = $weight_diff * 7700;

    // Days to achieve goal
    $days = $target_months * 30;

    if ($days <= 0)
        $days = 90; // Fallback

    $daily_adjustment = $total_cal_change / $days;

    // Cap the adjustment to avoid extreme diets (max +/- 1000 calories)
    if ($daily_adjustment > 1000)
        $daily_adjustment = 1000;
    if ($daily_adjustment < -1000)
        $daily_adjustment = -1000;

    return $tdee - $daily_adjustment;
}

function calculateMacronutrients($daily_calories, $current_weight, $target_weight)
{
    $macros = [];

    $weight_difference = $current_weight - $target_weight;

    if ($weight_difference > 2) {
        $macros['protein_percentage'] = 0.35;
        $macros['carbs_percentage'] = 0.40;
        $macros['fats_percentage'] = 0.25;
    } else if ($weight_difference < -2) {
        $macros['protein_percentage'] = 0.25;
        $macros['carbs_percentage'] = 0.50;
        $macros['fats_percentage'] = 0.25;
    } else {
        $macros['protein_percentage'] = 0.30;
        $macros['carbs_percentage'] = 0.45;
        $macros['fats_percentage'] = 0.25;
    }

    $macros['protein_g'] = round(($daily_calories * $macros['protein_percentage']) / 4, 2);
    $macros['carbs_g'] = round(($daily_calories * $macros['carbs_percentage']) / 4, 2);
    $macros['fats_g'] = round(($daily_calories * $macros['fats_percentage']) / 9, 2);

    return $macros;
}

function calculateBMI($weight_kg, $height_m)
{
    if ($height_m == 0)
        return 0;
    return round($weight_kg / ($height_m * $height_m), 2);
}

// Process form submission
$submitted = false;
$results = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Check if all required fields are filled
    $required_fields = ['name', 'gender', 'age', 'height', 'current_weight', 'target_weight', 'activity_level', 'calorie_experience'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        $error = "Please fill in all required fields. Missing: " . implode(', ', $missing_fields);
    } else {
        // Get and sanitize form data
        $name = $conn->real_escape_string($_POST['name']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $age = intval($_POST['age']);
        $height = floatval($_POST['height']);
        $height_unit = $conn->real_escape_string($_POST['height_unit'] ?? 'cm');
        $current_weight = floatval($_POST['current_weight']);
        $target_weight = floatval($_POST['target_weight']);
        $weight_unit = $conn->real_escape_string($_POST['weight_unit'] ?? 'kg');
        $activity_level = $conn->real_escape_string($_POST['activity_level']);
        $target_duration = intval($_POST['target_duration'] ?? 3);

        // Handle medical conditions
        $medical_conditions_str = '';
        if (isset($_POST['medical_conditions']) && !empty($_POST['medical_conditions'])) {
            $conditions_array = json_decode($_POST['medical_conditions'], true);
            if (is_array($conditions_array)) {
                $medical_conditions_str = implode(', ', array_map(function ($cond) use ($conn) {
                    return $conn->real_escape_string($cond);
                }, $conditions_array));
            }
        }

        $calorie_experience = $conn->real_escape_string($_POST['calorie_experience']);

        // Convert units to metric for calculations
        $height_cm = $height_unit == 'ft' ? $height * 30.48 : $height;
        $current_weight_kg = $weight_unit == 'lb' ? $current_weight * 0.453592 : $current_weight;
        $target_weight_kg = $weight_unit == 'lb' ? $target_weight * 0.453592 : $target_weight;

        // Calculate metrics
        $bmr = calculateBMR($current_weight_kg, $height_cm, $age, $gender);
        $daily_calories = calculateDailyCalories($bmr, $activity_level, $current_weight_kg, $target_weight_kg, $target_duration);

        // Calculate macronutrients
        $macros = calculateMacronutrients($daily_calories, $current_weight_kg, $target_weight_kg);

        // Calculate BMI
        $height_m = $height_cm / 100;
        $bmi_current = calculateBMI($current_weight_kg, $height_m);
        $bmi_target = calculateBMI($target_weight_kg, $height_m);

        // Calculate weight difference
        $weight_difference = abs($current_weight_kg - $target_weight_kg);

        // Get user_id from session or set to null if not logged in
        $user_id_val = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

        // Check if record already exists for THIS user
        if ($user_id_val !== null) {
            $check_stmt = $conn->prepare("SELECT user_id FROM usersinput WHERE user_id = ?");
            $check_stmt->bind_param("i", $user_id_val);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                // Switch to UPDATE
                $sql = "UPDATE usersinput SET 
                    name=?, gender=?, age=?, height=?, height_unit=?, 
                    current_weight=?, target_weight=?, weight_unit=?, 
                    activity_level=?, medical_conditions=?, calorie_experience=?,
                    bmr=?, daily_calories=?, protein_g=?, carbs_g=?, fats_g=?,
                    bmi_current=?, bmi_target=?, weight_difference=?, target_duration=?
                    WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                $type_string = "ssidsddssssddddddddii";
                $stmt->bind_param(
                    $type_string,
                    $name,
                    $gender,
                    $age,
                    $height,
                    $height_unit,
                    $current_weight,
                    $target_weight,
                    $weight_unit,
                    $activity_level,
                    $medical_conditions_str,
                    $calorie_experience,
                    $bmr,
                    $daily_calories,
                    $macros['protein_g'],
                    $macros['carbs_g'],
                    $macros['fats_g'],
                    $bmi_current,
                    $bmi_target,
                    $weight_difference,
                    $target_duration,
                    $user_id_val
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: personaldashbord.php?updated=1");
                    exit();
                } else {
                    $error = "Update failed: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $check_stmt->close();
            }
        }

        // INSERT logic if not updated or user not logged in
        $sql = "INSERT INTO usersinput (
            user_id, name, gender, age, height, height_unit, 
            current_weight, target_weight, weight_unit, 
            activity_level, medical_conditions, calorie_experience,
            bmr, daily_calories, protein_g, carbs_g, fats_g,
            bmi_current, bmi_target, weight_difference, target_duration
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $type_string = "issidsddssssddddddddi";
            $stmt->bind_param(
                $type_string,
                $user_id_val,
                $name,
                $gender,
                $age,
                $height,
                $height_unit,
                $current_weight,
                $target_weight,
                $weight_unit,
                $activity_level,
                $medical_conditions_str,
                $calorie_experience,
                $bmr,
                $daily_calories,
                $macros['protein_g'],
                $macros['carbs_g'],
                $macros['fats_g'],
                $bmi_current,
                $bmi_target,
                $weight_difference,
                $target_duration
            );

            if ($stmt->execute()) {
                $user_id_res = $stmt->insert_id;
                $stmt->close();
                header("Location: login.php?success=1&id=" . $user_id_res);
                exit();
            } else {
                $error = "Error executing query: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition Onboarding | NutriPlan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #121212;
            --card: #1c1c1c;
            --yellow: #FFC107;
            --text: #ffffff;
            --muted: #b0b0b0;
            --input-bg: #252525;
            --border: #333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }

        .app {
            width: 100%;
            max-width: 480px;
            background: var(--bg);
            padding: 40px 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
            gap: 10px;
            /* Consistent spacing between progress, card, and footer */
        }

        form {
            display: contents;
        }

        .progress {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 15px;
            width: 100%;
        }

        .progress span {
            width: 8px;
            height: 8px;
            background: #333;
            border-radius: 50%;
            transition: 0.3s;
        }

        .progress span.active {
            background: var(--yellow);
            transform: scale(1.2);
        }

        .card {
            background: var(--card);
            border-radius: 26px;
            padding: 32px 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            border: 1px solid #2a2a2a;
            text-align: center;
            width: 100%;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        p {
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            font-size: 1.1rem;
            transition: 0.3s;
            text-align: center;
            /* Center input text */
        }

        input:focus {
            outline: none;
            border-color: var(--yellow);
            box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.1);
        }

        .options {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .option {
            border: 1px solid var(--border);
            background: var(--input-bg);
            padding: 18px;
            border-radius: 16px;
            cursor: pointer;
            transition: .3s;
            font-weight: 600;
            color: var(--muted);
        }

        .option:hover {
            background: #2a2a2a;
            color: var(--text);
        }

        .option.active {
            border-color: var(--yellow);
            background: rgba(255, 193, 7, 0.1);
            color: var(--yellow);
        }

        .pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
            width: 100%;
        }

        .pill {
            border: 1px solid var(--border);
            background: var(--input-bg);
            padding: 10px 20px;
            border-radius: 999px;
            text-align: center;
            font-size: .9rem;
            cursor: pointer;
            color: var(--muted);
            transition: 0.3s;
        }

        .pill:hover {
            border-color: #555;
            color: var(--text);
        }

        .pill.active {
            background: var(--yellow);
            color: #000;
            border-color: var(--yellow);
            font-weight: 600;
        }

        .footer {
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 10px 8px;
            align-items: center;
        }

        button {
            background: var(--yellow);
            color: #000;
            border: none;
            padding: 14px 32px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #ffca2c;
            transform: translateY(-2px);
        }

        button.secondary {
            background: none;
            color: var(--muted);
            padding: 10px 20px;
        }

        button.secondary:hover {
            color: var(--text);
        }

        .step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .step.active {
            display: block
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

        .weight-selector-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 20px 0;
        }

        .weight-input-wrapper {
            display: flex;
            align-items: baseline;
            justify-content: center;
            margin: 10px 0;
            position: relative;
        }

        .weight-input {
            font-size: 4rem !important;
            font-weight: 800;
            color: var(--yellow) !important;
            border: none !important;
            background: transparent !important;
            text-align: center;
            width: 180px !important;
            padding: 0 !important;
            outline: none;
        }

        .weight-unit {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--muted);
            margin-left: 5px;
        }

        .weight-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 10px;
        }

        .weight-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            font-size: 1.8rem;
            font-weight: 400;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .weight-btn:hover {
            background: var(--yellow);
            color: black;
            border-color: var(--yellow);
        }

        .unit-toggle {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            background: var(--input-bg);
            padding: 5px;
            border-radius: 30px;
            width: fit-content;
        }

        .unit-btn {
            padding: 8px 24px;
            border: none;
            border-radius: 25px;
            background: transparent;
            color: var(--muted);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .unit-btn.active {
            background: var(--card);
            color: var(--yellow);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .decimal-controls {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
        }

        .decimal-btn {
            padding: 8px 18px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--input-bg);
            color: var(--muted);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .decimal-btn:hover {
            border-color: var(--yellow);
            color: var(--yellow);
        }

        .number-input-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 30px 0;
        }

        .number-display {
            font-size: 4rem;
            font-weight: 800;
            color: var(--yellow);
            margin: 10px 0;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .number-controls {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }

        .number-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 400;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .number-btn:hover {
            background: var(--yellow);
            color: black;
            border-color: var(--yellow);
        }

        .number-unit {
            font-size: 1.2rem;
            color: var(--muted);
            margin-top: 5px;
        }

        .bmi-info {
            margin-top: 25px;
            padding: 16px;
            background: rgba(255, 193, 7, 0.05);
            border-radius: 16px;
            text-align: center;
            font-size: 0.95rem;
            color: var(--muted);
            border: 1px solid rgba(255, 193, 7, 0.1);
        }

        .bmi-value {
            color: var(--yellow);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .weight-connection {
            text-align: center;
            margin-top: 20px;
            padding: 16px;
            background: var(--input-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            color: var(--muted);
        }

        .weight-difference {
            font-weight: 700;
            color: var(--text);
        }

        .arrow {
            margin: 0 10px;
            color: var(--yellow);
        }

        .error {
            background: rgba(255, 59, 48, 0.1);
            color: #ff4d4d;
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid rgba(255, 59, 48, 0.2);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #444;
        }

        /* Wheel Picker Styles - Professional Design Matching Index.html */
        .wheel-picker-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 30px 0;
            position: relative;
            width: 100%;
        }

        .wheel-picker {
            height: 280px;
            overflow-y: scroll;
            scroll-snap-type: y proximity;
            scroll-behavior: smooth;
            position: relative;
            width: 100%;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            /* Gradient fade for professional look */
            mask-image: linear-gradient(to bottom,
                    transparent 0%,
                    rgba(0, 0, 0, 0.1) 10%,
                    black 25%,
                    black 75%,
                    rgba(0, 0, 0, 0.1) 90%,
                    transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom,
                    transparent 0%,
                    rgba(0, 0, 0, 0.1) 10%,
                    black 25%,
                    black 75%,
                    rgba(0, 0, 0, 0.1) 90%,
                    transparent 100%);
        }

        .wheel-picker::-webkit-scrollbar {
            display: none;
        }

        .wheel-picker-item {
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            scroll-snap-align: center;
            font-size: 1rem;
            font-weight: 400;
            color: #808080;
            transition: all 0.25s cubic-bezier(0.4, 0.0, 0.2, 1);
            opacity: 0.2;
            transform: scale(0.7);
            user-select: none;
            -webkit-user-select: none;
            cursor: pointer;
        }

        .wheel-picker-item.selected {
            font-size: 4.5rem;
            font-weight: 800;
            color: #FFC107;
            opacity: 1;
            transform: scale(1);
            letter-spacing: -0.03em;
            text-shadow: 0 0 20px rgba(255, 193, 7, 0.3);
        }

        .wheel-picker-item.near-selected-1 {
            font-size: 2.2rem;
            font-weight: 600;
            opacity: 0.45;
            transform: scale(0.82);
            color: #B0B0B0;
        }

        .wheel-picker-item.near-selected-2 {
            font-size: 1.5rem;
            font-weight: 500;
            opacity: 0.3;
            transform: scale(0.75);
            color: #909090;
        }

        .wheel-picker-highlight {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 85%;
            height: 70px;
            pointer-events: none;
            z-index: 10;
        }

        .wheel-picker-highlight::before,
        .wheel-picker-highlight::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            height: 1.5px;
            background: linear-gradient(90deg,
                    transparent 0%,
                    rgba(255, 193, 7, 0.3) 20%,
                    rgba(255, 193, 7, 0.5) 50%,
                    rgba(255, 193, 7, 0.3) 80%,
                    transparent 100%);
        }

        .wheel-picker-highlight::before {
            top: 0;
        }

        .wheel-picker-highlight::after {
            bottom: 0;
        }

        .wheel-unit {
            font-size: 1.1rem;
            font-weight: 600;
            color: #B0B0B0;
            margin-top: 18px;
            text-align: center;
            letter-spacing: 0.05em;
            text-transform: lowercase;
        }
    </style>
</head>

<body>
    <div class="app">

        <?php if ($error): ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Progress -->
        <div class="progress" id="progress"></div>

        <form method="POST" id="nutritionForm" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <!-- Step 0: Name -->
            <div class="step active">
                <div class="card">
                    <h1>Hey there!</h1>
                    <p>What's your name?</p>
                    <input type="text" name="name" id="userName" placeholder="Enter your name" required>
                </div>
            </div>

            <!-- Step 1: Gender -->
            <div class="step">
                <div class="card">
                    <h1>Biological Sex</h1>
                    <p>Used to calculate body metrics</p>
                    <div class="options">
                        <div class="option" data-value="Male">Male</div>
                        <div class="option" data-value="Female">Female</div>
                    </div>
                    <input type="hidden" name="gender" id="genderInput" required>
                </div>
            </div>

            <!-- Step 2: Activity Level -->
            <div class="step">
                <div class="card">
                    <h1>How active are you?</h1>
                    <div class="options">
                        <div class="option" data-value="Sedentary">Sedentary</div>
                        <div class="option" data-value="Lightly Active">Lightly Active</div>
                        <div class="option" data-value="Moderately Active">Moderately Active</div>
                        <div class="option" data-value="Very Active">Very Active</div>
                    </div>
                    <input type="hidden" name="activity_level" id="activityInput" required>
                </div>
            </div>

            <!-- Step 3: Age -->
            <div class="step">
                <div class="card">
                    <h1>Your age</h1>
                    <p>This helps us personalize your plan</p>
                    <div class="wheel-picker-container">
                        <div class="wheel-picker-highlight"></div>
                        <div class="wheel-picker" id="agePicker"></div>
                        <div class="wheel-unit">years</div>
                    </div>
                    <input type="hidden" name="age" id="ageInput" value="25" required>
                </div>
            </div>

            <!-- Step 4: Height -->
            <div class="step">
                <div class="card">
                    <h1>Your height</h1>
                    <p>This will help us calculate your BMI</p>
                    <div class="wheel-picker-container">
                        <div class="wheel-picker-highlight"></div>
                        <div class="wheel-picker" id="heightPicker"></div>
                        <div class="wheel-unit" id="heightUnit">cm</div>
                        <div class="unit-toggle" style="margin-top: 20px;">
                            <button type="button" class="unit-btn active" onclick="changeUnit('cm')">cm</button>
                            <button type="button" class="unit-btn" onclick="changeUnit('ft')">ft</button>
                        </div>
                    </div>
                    <input type="hidden" name="height" id="heightInput" value="170" required>
                    <input type="hidden" name="height_unit" id="heightUnitInput" value="cm">
                </div>
            </div>

            <!-- Step 5: Current Weight -->
            <div class="step">
                <div class="card">
                    <h1>What's your current weight?</h1>
                    <p>This will help us determine your goal, and monitor your progress over time.</p>
                    <div class="wheel-picker-container">
                        <div class="wheel-picker-highlight"></div>
                        <div class="wheel-picker" id="currentWeightPicker"></div>
                        <div class="wheel-unit" id="currentWeightUnit">kg</div>
                        <div class="unit-toggle">
                            <button type="button" class="unit-btn active" onclick="changeWeightUnit('kg')">Kg</button>
                            <button type="button" class="unit-btn" onclick="changeWeightUnit('lb')">Lb</button>
                        </div>
                    </div>
                    <input type="hidden" name="current_weight" id="currentWeightHidden" value="80.0" required>
                    <input type="hidden" name="weight_unit" id="weightUnitInput" value="kg">
                </div>
            </div>

            <!-- Step 6: Target Weight -->
            <div class="step">
                <div class="card">
                    <h1>Your target weight</h1>
                    <p>Healthy goal based on BMI</p>
                    <div class="wheel-picker-container">
                        <div class="wheel-picker-highlight"></div>
                        <div class="wheel-picker" id="targetWeightPicker"></div>
                        <div class="wheel-unit" id="targetWeightUnit">kg</div>
                        <div class="unit-toggle">
                            <button type="button" class="unit-btn active"
                                onclick="changeTargetWeightUnit('kg')">Kg</button>
                            <button type="button" class="unit-btn" onclick="changeTargetWeightUnit('lb')">Lb</button>
                        </div>
                    </div>
                    <input type="hidden" name="target_weight" id="targetWeightHidden" value="70.0" required>
                </div>
            </div>

            <!-- Step 7: Medical conditions (Optional) -->
            <div class="step">
                <div class="card">
                    <h1>Medical conditions</h1>
                    <p>Select all that apply (optional)</p>
                    <div class="pills">
                        <div class="pill" data-value="None">None</div>
                        <div class="pill" data-value="Diabetes">Diabetes</div>
                        <div class="pill" data-value="Hypertension">Hypertension</div>
                        <div class="pill" data-value="Thyroid">Thyroid</div>
                        <div class="pill" data-value="PCOS">PCOS</div>
                        <div class="pill" data-value="Stress">Stress</div>
                        <div class="pill" data-value="Sleep Issues">Sleep Issues</div>
                        <div class="pill" data-value="Anxiety">Anxiety</div>
                    </div>
                    <input type="hidden" name="medical_conditions" id="medicalConditionsInput" value="[]">
                </div>
            </div>

            <!-- Step 8: Calorie counting experience (REQUIRED) -->
            <div class="step">
                <div class="card">
                    <h1>Calorie counting experience</h1>
                    <p>Select your experience level with calorie tracking</p>
                    <div class="options">
                        <div class="option" data-value="beginner">I'm new</div>
                        <div class="option" data-value="little">Very little</div>
                        <div class="option" data-value="intermediate">Sometimes</div>
                        <div class="option" data-value="expert">All the time</div>
                    </div>
                    <input type="hidden" name="calorie_experience" id="calorieExperienceInput" value="" required>
                </div>
            </div>

            <!-- Step 9: Target Timeline (NEW) -->
            <div class="step">
                <div class="card">
                    <h1>When to achieve?</h1>
                    <p>Select your target timeline for this goal</p>
                    <div class="options">
                        <div class="option active" data-value="3">3 Months</div>
                        <div class="option" data-value="6">6 Months</div>
                        <div class="option" data-value="12">1 Year</div>
                    </div>
                    <input type="hidden" name="target_duration" id="targetDurationInput" value="3" required>
                </div>
            </div>

            <!-- Navigation -->
            <div class="footer">
                <button type="button" class="secondary" onclick="prevStep()">Back</button>
                <button type="button" onclick="nextStep()">Next</button>
                <button type="submit" name="submit" id="submitBtn" style="display:none;">Submit</button>
            </div>
        </form>
    </div>

    <script>
        let step = 0;
        const steps = document.querySelectorAll('.step');
        const progress = document.getElementById('progress');

        // Data storage
        let userData = {
            name: '',
            age: 25,
            height: 170,
            heightUnit: 'cm',
            currentWeight: 80.0,
            currentWeightUnit: 'kg',
            targetWeight: 70.0,
            targetWeightUnit: 'kg',
            gender: '',
            activity: '',
            medicalConditions: [],
            calorieExperience: '',
            targetDuration: 3
        };

        // Calculate and display BMI
        function calculateBMI() {
            const heightInMeters = userData.heightUnit === 'cm' ? userData.height / 100 : userData.height * 0.3048;

            let currentWeightKg = userData.currentWeight;
            let targetWeightKg = userData.targetWeight;

            if (userData.currentWeightUnit === 'lb') {
                currentWeightKg = userData.currentWeight * 0.453592;
            }

            if (userData.targetWeightUnit === 'lb') {
                targetWeightKg = userData.targetWeight * 0.453592;
            }

            const currentBMI = (currentWeightKg / (heightInMeters * heightInMeters)).toFixed(1);
            const targetBMI = (targetWeightKg / (heightInMeters * heightInMeters)).toFixed(1);

            const currentBMIElement = document.getElementById('currentBMI');
            const targetBMIElement = document.getElementById('targetBMI');
            if (currentBMIElement) currentBMIElement.textContent = currentBMI;
            if (targetBMIElement) targetBMIElement.textContent = targetBMI;
        }

        // Update weight displays and connections
        function updateWeightDisplays() {
            const currentWeightFormatted = parseFloat(userData.currentWeight).toFixed(1);
            const targetWeightFormatted = parseFloat(userData.targetWeight).toFixed(1);

            if (document.getElementById('currentWeightInput')) document.getElementById('currentWeightInput').value = currentWeightFormatted;
            if (document.getElementById('targetWeightInput')) document.getElementById('targetWeightInput').value = targetWeightFormatted;

            document.getElementById('currentWeightHidden').value = currentWeightFormatted;
            document.getElementById('targetWeightHidden').value = targetWeightFormatted;

            const currentUnit = userData.currentWeightUnit;
            const targetUnit = userData.targetWeightUnit;

            const currentDisplay = document.getElementById('currentWeightDisplay');
            const targetDisplay = document.getElementById('targetWeightDisplay');
            if (currentDisplay) currentDisplay.textContent = `${currentWeightFormatted} ${currentUnit}`;
            if (targetDisplay) targetDisplay.textContent = `${targetWeightFormatted} ${targetUnit}`;

            let currentKg = userData.currentWeight;
            let targetKg = userData.targetWeight;

            if (userData.currentWeightUnit === 'lb') {
                currentKg = userData.currentWeight * 0.453592;
            }

            if (userData.targetWeightUnit === 'lb') {
                targetKg = userData.targetWeight * 0.453592;
            }

            const difference = Math.abs(currentKg - targetKg).toFixed(1);
            const differenceElement = document.getElementById('weightDifference');
            if (differenceElement) differenceElement.textContent = `${difference} kg`;

            calculateBMI();
        }

        function renderProgress() {
            progress.innerHTML = '';
            steps.forEach((_, i) => {
                const dot = document.createElement('span');
                if (i <= step) dot.classList.add('active');
                progress.appendChild(dot);
            });

            // Hide back button on first step
            const backBtn = document.querySelector('.footer button.secondary');
            if (backBtn) {
                backBtn.style.visibility = step === 0 ? 'hidden' : 'visible';
            }
        }
        renderProgress();

        function nextStep() {
            // Check if current step is completed before moving to next
            let canProceed = true;
            let errorMessage = '';

            switch (step) {
                case 0: // Name
                    userData.name = document.getElementById('userName').value.trim();
                    if (!userData.name) {
                        canProceed = false;
                        errorMessage = 'Please enter your name';
                    }
                    break;
                case 1: // Gender
                    if (!userData.gender) {
                        canProceed = false;
                        errorMessage = 'Please select your biological sex';
                    }
                    break;
                case 2: // Activity level
                    if (!userData.activity) {
                        canProceed = false;
                        errorMessage = 'Please select your activity level';
                    }
                    break;
                case 3: // Age - already has default value
                    break;
                case 4: // Height - already has default value
                    break;
                case 5: // Current weight - already has default value
                    break;
                case 6: // Target weight - already has default value
                    break;
                case 7: // Medical conditions - optional, no validation needed
                    break;
                case 8: // Calorie experience
                    if (!userData.calorieExperience) {
                        canProceed = false;
                        errorMessage = 'Please select your calorie counting experience';
                    }
                    break;
                case 9: // Target duration
                    if (!userData.targetDuration) {
                        canProceed = false;
                        errorMessage = 'Please select your target timeline';
                    }
                    break;
            }

            if (!canProceed) {
                alert(errorMessage);
                return;
            }

            // Update form fields before moving to next step
            updateFormFields();

            // Show submit button on last step
            if (step === steps.length - 2) {
                document.querySelector('.footer button:nth-child(2)').style.display = 'none';
                document.getElementById('submitBtn').style.display = 'block';
            }

            if (step < steps.length - 1) {
                steps[step].classList.remove('active');
                step++;
                steps[step].classList.add('active');
                renderProgress();

                // Initialize wheel pickers when navigating to those steps
                if (step === 3) { // Age step
                    initAgePicker();
                } else if (step === 4) { // Height step
                    initHeightPicker();
                } else if (step === 5) { // Current Weight step
                    initCurrentWeightPicker();
                } else if (step === 6) { // Target Weight step
                    initTargetWeightPicker();
                }
            }
        }

        function prevStep() {
            document.querySelector('.footer button:nth-child(2)').style.display = 'block';
            document.getElementById('submitBtn').style.display = 'none';

            if (step > 0) {
                steps[step].classList.remove('active');
                step--;
                steps[step].classList.add('active');
                renderProgress();

                // Reinitialize wheel pickers when navigating back to those steps
                if (step === 3) { // Age step
                    initAgePicker();
                } else if (step === 4) { // Height step
                    initHeightPicker();
                } else if (step === 5) { // Current Weight step
                    initCurrentWeightPicker();
                } else if (step === 6) { // Target Weight step
                    initTargetWeightPicker();
                }
            }
        }

        // Update all form fields before submission
        function updateFormFields() {
            document.getElementById('calorieExperienceInput').value = userData.calorieExperience || '';
            document.getElementById('targetDurationInput').value = userData.targetDuration || 3;
        }

        // Wheel Picker Functions
        function createWheelPicker(pickerId, values, defaultValue, onValueChange) {
            const picker = document.getElementById(pickerId);
            picker.innerHTML = '';

            // Add padding items at top for smooth scrolling
            for (let i = 0; i < 4; i++) {
                const paddingItem = document.createElement('div');
                paddingItem.className = 'wheel-picker-item';
                paddingItem.innerHTML = '&nbsp;';
                picker.appendChild(paddingItem);
            }

            // Add actual values
            values.forEach((value, index) => {
                const item = document.createElement('div');
                item.className = 'wheel-picker-item';
                item.textContent = value;
                item.dataset.value = value;
                picker.appendChild(item);
            });

            // Add padding items at bottom
            for (let i = 0; i < 4; i++) {
                const paddingItem = document.createElement('div');
                paddingItem.className = 'wheel-picker-item';
                paddingItem.innerHTML = '&nbsp;';
                picker.appendChild(paddingItem);
            }

            // Scroll to default value
            const defaultIndex = values.indexOf(defaultValue);
            if (defaultIndex !== -1) {
                const targetItem = picker.children[defaultIndex + 4]; // +4 for padding
                picker.scrollTop = targetItem.offsetTop - picker.offsetHeight / 2 + targetItem.offsetHeight / 2;
            }

            // Handle scroll events with improved debouncing
            let scrollTimeout;
            let isScrolling = false;

            picker.addEventListener('scroll', function () {
                isScrolling = true;
                clearTimeout(scrollTimeout);

                // Update visual feedback immediately
                updateWheelSelection(pickerId, onValueChange);

                // Snap to center after scrolling stops
                scrollTimeout = setTimeout(() => {
                    isScrolling = false;
                    snapToCenter(pickerId, onValueChange);
                }, 150);
            });

            // Initial selection update
            updateWheelSelection(pickerId, onValueChange);
        }

        function snapToCenter(pickerId, onValueChange) {
            const picker = document.getElementById(pickerId);
            const items = Array.from(picker.querySelectorAll('.wheel-picker-item'));
            const pickerRect = picker.getBoundingClientRect();
            const centerY = pickerRect.top + pickerRect.height / 2;

            let closestItem = null;
            let closestDistance = Infinity;

            items.forEach(item => {
                if (item.textContent.trim() === '') return;

                const itemRect = item.getBoundingClientRect();
                const itemCenterY = itemRect.top + itemRect.height / 2;
                const distance = Math.abs(centerY - itemCenterY);

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestItem = item;
                }
            });

            if (closestItem && closestDistance > 5) {
                // Smooth scroll to center the closest item
                const targetScrollTop = closestItem.offsetTop - picker.offsetHeight / 2 + closestItem.offsetHeight / 2;
                picker.scrollTo({
                    top: targetScrollTop,
                    behavior: 'smooth'
                });
            }
        }

        function updateWheelSelection(pickerId, onValueChange) {
            const picker = document.getElementById(pickerId);
            const items = Array.from(picker.querySelectorAll('.wheel-picker-item'));
            const pickerRect = picker.getBoundingClientRect();
            const centerY = pickerRect.top + pickerRect.height / 2;

            let closestItem = null;
            let closestDistance = Infinity;

            items.forEach(item => {
                item.classList.remove('selected', 'near-selected-1', 'near-selected-2');

                if (item.textContent.trim() === '') return;

                const itemRect = item.getBoundingClientRect();
                const itemCenterY = itemRect.top + itemRect.height / 2;
                const distance = Math.abs(centerY - itemCenterY);

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestItem = item;
                }

                // Progressive proximity classes for smooth visual transition
                // Adjusted thresholds for 56px item height
                if (distance < 15) {
                    // This is the selected item (centered)
                    item.classList.add('selected');
                } else if (distance < 70) {
                    // Immediate neighbors
                    item.classList.add('near-selected-1');
                } else if (distance < 130) {
                    // Second-level neighbors
                    item.classList.add('near-selected-2');
                }
                // Items beyond this remain in default faded state
            });

            if (closestItem) {
                const value = closestItem.dataset.value;
                if (value && onValueChange) {
                    onValueChange(parseFloat(value) || value);
                }
            }
        }

        // Initialize Age Picker
        function initAgePicker() {
            const ages = [];
            for (let i = 18; i <= 100; i++) {
                ages.push(i);
            }
            createWheelPicker('agePicker', ages, userData.age, (value) => {
                userData.age = value;
                document.getElementById('ageInput').value = value;
            });
        }

        // Initialize Height Picker
        function initHeightPicker() {
            let values = [];
            if (userData.heightUnit === 'cm') {
                for (let i = 100; i <= 250; i++) {
                    values.push(i);
                }
            } else {
                for (let i = 30; i <= 80; i++) {
                    values.push((i / 10).toFixed(1));
                }
            }
            createWheelPicker('heightPicker', values, userData.heightUnit === 'cm' ? userData.height : userData.height.toFixed(1), (value) => {
                userData.height = value;
                document.getElementById('heightInput').value = value;
                calculateBMI();
            });
        }

        // Initialize Current Weight Picker
        function initCurrentWeightPicker() {
            const values = [];
            const unit = userData.currentWeightUnit;
            const min = unit === 'kg' ? 30 : 66;
            const max = unit === 'kg' ? 200 : 440;

            for (let i = min * 10; i <= max * 10; i++) {
                values.push((i / 10).toFixed(1));
            }

            createWheelPicker('currentWeightPicker', values, userData.currentWeight.toFixed(1), (value) => {
                userData.currentWeight = parseFloat(value);
                document.getElementById('currentWeightHidden').value = value;
                updateWeightDisplays();
            });
        }

        // Initialize Target Weight Picker
        function initTargetWeightPicker() {
            const values = [];
            const unit = userData.targetWeightUnit;
            const min = unit === 'kg' ? 30 : 66;
            const max = unit === 'kg' ? 200 : 440;

            for (let i = min * 10; i <= max * 10; i++) {
                values.push((i / 10).toFixed(1));
            }

            createWheelPicker('targetWeightPicker', values, userData.targetWeight.toFixed(1), (value) => {
                userData.targetWeight = parseFloat(value);
                document.getElementById('targetWeightHidden').value = value;
                updateWeightDisplays();
            });
        }

        // Number input functions (kept for backward compatibility but not used)
        function adjustNumber(type, change) {
            if (type === 'age') {
                userData.age = Math.max(18, Math.min(100, userData.age + change));
                document.getElementById('ageInput').value = userData.age;
            } else if (type === 'height') {
                if (userData.heightUnit === 'cm') {
                    userData.height = Math.max(100, Math.min(250, userData.height + change));
                } else {
                    if (change > 0) {
                        userData.height = Math.min(8, parseFloat((userData.height + 0.1).toFixed(1)));
                    } else {
                        userData.height = Math.max(3, parseFloat((userData.height - 0.1).toFixed(1)));
                    }
                }
                document.getElementById('heightInput').value = userData.height;

                calculateBMI();
            }
        }

        function changeUnit(unit) {
            const container = event.target.closest('.unit-toggle');
            container.querySelectorAll('.unit-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const oldUnit = userData.heightUnit;
            userData.heightUnit = unit;
            document.getElementById('heightUnit').textContent = unit;
            document.getElementById('heightUnitInput').value = unit;

            // Convert height value when changing units
            if (unit === 'ft' && oldUnit === 'cm') {
                userData.height = parseFloat((userData.height / 30.48).toFixed(1));
            } else if (unit === 'cm' && oldUnit === 'ft') {
                userData.height = Math.round(userData.height * 30.48);
            }

            document.getElementById('heightInput').value = userData.height;

            // Reinitialize the height picker with new unit
            initHeightPicker();

            calculateBMI();
        }

        function changeWeightUnit(unit) {
            const container = event.target.closest('.unit-toggle');
            container.querySelectorAll('.unit-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (unit === 'lb' && userData.currentWeightUnit === 'kg') {
                userData.currentWeight = parseFloat((userData.currentWeight * 2.20462).toFixed(1));
                userData.currentWeightUnit = 'lb';
            } else if (unit === 'kg' && userData.currentWeightUnit === 'lb') {
                userData.currentWeight = parseFloat((userData.currentWeight * 0.453592).toFixed(1));
                userData.currentWeightUnit = 'kg';
            }

            document.getElementById('currentWeightUnit').textContent = unit;
            document.getElementById('weightUnitInput').value = unit;

            // Reinitialize the current weight picker with new unit
            initCurrentWeightPicker();

            updateWeightDisplays();
        }

        function changeTargetWeightUnit(unit) {
            const container = event.target.closest('.unit-toggle');
            container.querySelectorAll('.unit-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (unit === 'lb' && userData.targetWeightUnit === 'kg') {
                userData.targetWeight = parseFloat((userData.targetWeight * 2.20462).toFixed(1));
                userData.targetWeightUnit = 'lb';
            } else if (unit === 'kg' && userData.targetWeightUnit === 'lb') {
                userData.targetWeight = parseFloat((userData.targetWeight * 0.453592).toFixed(1));
                userData.targetWeightUnit = 'kg';
            }

            document.getElementById('targetWeightUnit').textContent = unit;

            // Reinitialize the target weight picker with new unit
            initTargetWeightPicker();

            updateWeightDisplays();
        }

        // Selection UI
        document.addEventListener('click', e => {
            if (e.target.classList.contains('option')) {
                e.target.parentElement.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
                e.target.classList.add('active');

                const value = e.target.getAttribute('data-value');

                // Update based on current step
                const currentStepCard = e.target.closest('.card');
                const stepTitle = currentStepCard.querySelector('h1').textContent;

                if (stepTitle.includes('Biological Sex') || step === 1) {
                    userData.gender = value;
                    document.getElementById('genderInput').value = value;
                } else if (stepTitle.includes('How active') || step === 2) {
                    userData.activity = value;
                    document.getElementById('activityInput').value = value;
                } else if (stepTitle.includes('Calorie counting') || step === 8) {
                    userData.calorieExperience = value;
                    document.getElementById('calorieExperienceInput').value = value;
                } else if (stepTitle.includes('achieve') || step === 9) {
                    userData.targetDuration = value;
                    document.getElementById('targetDurationInput').value = value;
                }
            }

            if (e.target.classList.contains('pill')) {
                e.target.classList.toggle('active');

                const conditions = [];
                document.querySelectorAll('.pill.active').forEach(pill => {
                    if (pill.getAttribute('data-value') !== 'None') {
                        conditions.push(pill.getAttribute('data-value'));
                    }
                });

                userData.medicalConditions = conditions;
                document.getElementById('medicalConditionsInput').value = JSON.stringify(conditions);
            }
        });

        // Update name
        document.getElementById('userName').addEventListener('input', function (e) {
            userData.name = e.target.value.trim();
        });

        // Initialize displays
        updateWeightDisplays();

        // Initialize form values
        document.getElementById('genderInput').value = '';
        document.getElementById('activityInput').value = '';
        document.getElementById('calorieExperienceInput').value = '';
        document.getElementById('targetDurationInput').value = '3';

        // Initialize medical conditions with empty array
        document.getElementById('medicalConditionsInput').value = '[]';

        // Form validation before submission
        document.getElementById('nutritionForm').addEventListener('submit', function (e) {
            // Final validation
            if (!userData.calorieExperience) {
                e.preventDefault();
                alert('Please select your calorie counting experience before submitting.');
                // Go to the calorie experience step
                steps[step].classList.remove('active');
                step = 8; // Calorie experience step index
                steps[step].classList.add('active');
                renderProgress();
                return false;
            }

            return true;
        });
    </script>
</body>

</html>