<?php
// Start session
session_start();

// Redirect if not authenticated
if (!isset($_SESSION['google_loggedin'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Database connection
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Failed to connect to the database"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);
$courseName = $data['course_name'] ?? '';

// Validate input
if (!$courseName) {
    echo json_encode(["success" => false, "message" => "Invalid course name"]);
    exit;
}

// Delete the course preference
$stmt = $pdo->prepare("DELETE FROM CourseraPreferences WHERE user_id = ? AND course_name = ?");
$success = $stmt->execute([$_SESSION['google_id'], $courseName]);

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to delete course"]);
}
?>
