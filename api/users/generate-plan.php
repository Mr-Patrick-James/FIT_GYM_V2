<?php
/**
 * generate-plan.php
 * Generates a weekly workout plan based on the user's questionnaire answers.
 * Called after questionnaire save OR manually via GET.
 *
 * GET  → returns current saved plan for the logged-in user
 * POST → generates + saves a new plan, returns it
 */
require_once '../config.php';
require_once '../session.php';

if (!isLoggedIn()) {
    sendResponse(false, 'Unauthorized', null, 401);
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// ─── Ensure table exists ────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `generated_workout_plans` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `plan_json`  LONGTEXT     NOT NULL COMMENT 'JSON array of day objects',
    `generated_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ─── GET: return existing plan ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT plan_json, generated_at FROM generated_workout_plans WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        sendResponse(true, 'Plan found', [
            'plan'         => json_decode($row['plan_json'], true),
            'generated_at' => $row['generated_at']
        ]);
    }
    sendResponse(false, 'No plan generated yet', null, 404);
}

// ─── POST: generate + save ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Fetch questionnaire
$stmt = $conn->prepare("SELECT * FROM user_questionnaire WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$q = $stmt->get_result()->fetch_assoc();

if (!$q) {
    sendResponse(false, 'Please complete the fitness survey first.', null, 422);
}

// ─── Plan Generator Logic ────────────────────────────────────────────────────
function generateWeeklyPlan($q) {
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $plan = [];
    
    // Parse workout frequency
    $workoutDays = match($q['workout_days_per_week']) {
        '5+ days' => 5,
        '3-4 days' => 4,
        default => 2
    };
    
    // Determine split based on goals and focus
    $focusAreas = strtolower($q['focus_areas'] ?? 'full body');
    $workoutType = strtolower($q['workout_type'] ?? 'mixed');
    $level = strtolower($q['exercise_history'] ?? 'beginner');
    
    // Sets/reps by level
    $intensity = match($level) {
        'advanced' => ['sets' => 4, 'reps' => '6-10'],
        'intermediate' => ['sets' => 3, 'reps' => '8-12'],
        default => ['sets' => 3, 'reps' => '10-12']
    };
    
    // Build rotation schedule
    $rotation = [];
    if ($workoutDays >= 5) {
        $rotation = ['Legs','Push','Pull','Core','Cardio','Rest','Rest'];
    } elseif ($workoutDays == 4) {
        $rotation = ['Legs','Push','Rest','Pull','Full Body','Rest','Rest'];
    } else {
        $rotation = ['Full Body','Rest','Rest','Cardio','Rest','Rest','Rest'];
    }
    
    // Build each day
    foreach ($days as $i => $day) {
        $focus = $rotation[$i];
        $isRest = $focus === 'Rest';
        
        $dayPlan = [
            'day' => $day,
            'focus' => $focus === 'Legs' ? 'Leg Day' : 
                      ($focus === 'Push' ? 'Push Day (Chest/Shoulders/Triceps)' :
                      ($focus === 'Pull' ? 'Pull Day (Back/Biceps)' :
                      ($focus === 'Core' ? 'Core & Abs' :
                      ($focus === 'Cardio' ? 'Cardio Day' : 
                      ($focus === 'Full Body' ? 'Full Body Workout' : 'Rest Day')))))),
            'type' => $isRest ? 'rest' : 'workout',
            'color' => match($focus) {
                'Legs' => '#10b981',
                'Push' => '#3b82f6',
                'Pull' => '#f59e0b',
                'Core' => '#eab308',
                'Cardio' => '#ef4444',
                'Full Body' => '#a855f7',
                default => '#6b7280'
            },
            'exercises' => $isRest ? [
                'Active recovery',
                'Light stretching or yoga',
                'Stay hydrated',
                'Get 7-9 hours of sleep'
            ] : generateExercises($focus, $intensity, $workoutType),
            'notes' => $isRest ? 'Recovery is when muscles grow!' : 'Stay consistent!'
        ];
        
        $plan[] = $dayPlan;
    }
    
    return $plan;
}

function generateExercises($focus, $intensity, $workoutType) {
    $s = $intensity['sets'];
    $r = $intensity['reps'];
    
    $exercises = match($focus) {
        'Legs' => [
            "Squats {$s}×{$r}",
            "Lunges {$s}×{$r} each leg",
            "Leg Press {$s}×{$r}",
            "Leg Curls {$s}×{$r}",
            "Calf Raises {$s}×15-20"
        ],
        'Push' => [
            "Bench Press {$s}×{$r}",
            "Shoulder Press {$s}×{$r}",
            "Incline Dumbbell Press {$s}×{$r}",
            "Lateral Raises {$s}×12-15",
            "Tricep Dips {$s}×{$r}"
        ],
        'Pull' => [
            "Pull-ups or Lat Pulldown {$s}×{$r}",
            "Barbell Rows {$s}×{$r}",
            "Dumbbell Rows {$s}×{$r}",
            "Face Pulls {$s}×15",
            "Bicep Curls {$s}×{$r}"
        ],
        'Core' => [
            "Planks {$s}×45-60sec",
            "Russian Twists {$s}×20",
            "Leg Raises {$s}×12-15",
            "Mountain Climbers {$s}×30sec",
            "Cable Crunches {$s}×15-20"
        ],
        'Cardio' => [
            "Treadmill Run 20-30 min",
            "Rowing Machine 15 min",
            "Battle Ropes 4×30sec",
            "Jump Rope 5×1min",
            "Burpees 3×10"
        ],
        default => [ // Full Body
            "Deadlifts {$s}×{$r}",
            "Bench Press {$s}×{$r}",
            "Squats {$s}×{$r}",
            "Pull-ups {$s}×{$r}",
            "Planks 3×60sec"
        ]
    };
    
    return $exercises;
}

$weeklyPlan = generateWeeklyPlan($q);
$planJson = json_encode($weeklyPlan, JSON_UNESCAPED_UNICODE);

// Upsert into DB
$stmt = $conn->prepare("INSERT INTO generated_workout_plans (user_id, plan_json)
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE plan_json = VALUES(plan_json), generated_at = NOW()");
$stmt->bind_param("is", $user_id, $planJson);

if (!$stmt->execute()) {
    sendResponse(false, 'Failed to save plan', null, 500);
}

sendResponse(true, 'Workout plan generated!', [
    'plan' => $weeklyPlan,
    'generated_at' => date('Y-m-d H:i:s')
]);
