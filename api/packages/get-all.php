<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // Check if goal column exists, fallback if not
    $checkGoal = $conn->query("SHOW COLUMNS FROM packages LIKE 'goal'");
    $hasGoal = ($checkGoal && $checkGoal->num_rows > 0);
    
    $query = "SELECT id, name, duration, price, tag, description, is_trainer_assisted, " . 
             ($hasGoal ? "goal, " : "'General Fitness' as goal, ") . 
             "is_active, created_at, updated_at FROM packages WHERE is_active = TRUE ORDER BY name ASC";
             
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packageId = (int)$row['id'];
        
        // Fetch linked trainers for this package
        $trainerIds = [];
        try {
            $trainerStmt = $conn->prepare("SELECT trainer_id FROM package_trainers WHERE package_id = ?");
            if ($trainerStmt) {
                $trainerStmt->bind_param("i", $packageId);
                $trainerStmt->execute();
                $trainerResult = $trainerStmt->get_result();
                while ($tRow = $trainerResult->fetch_assoc()) {
                    $trainerIds[] = (int)$tRow['trainer_id'];
                }
                $trainerStmt->close();
            }
        } catch (Exception $te) {
            // Silently skip trainer lookup if table missing
        }

        $packages[] = [
            'id' => $packageId,
            'name' => $row['name'],
            'duration' => $row['duration'],
            'price' => (float)$row['price'],
            'tag' => $row['tag'],
            'description' => $row['description'],
            'is_trainer_assisted' => (bool)$row['is_trainer_assisted'],
            'goal' => $row['goal'] ?? 'General Fitness',
            'trainer_ids' => $trainerIds,
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    $stmt->close();
    $conn->close();

    sendResponse(true, 'Packages retrieved successfully', $packages);

} catch (Exception $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
}
?>