<?php
session_start();

if (!isset($_SESSION['google_loggedin'])) {
    exit(json_encode(["success" => false, "message" => "User not logged in."]));
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
    exit(json_encode(["success" => false, "message" => "Database connection failed."]));
}

// Capture and log incoming data for debugging
$data = json_decode(file_get_contents("php://input"), true);
$log_file = "debug_log.txt";  // Log file location

file_put_contents($log_file, "Received Data:\n" . print_r($data, true) . "\n", FILE_APPEND);

$course_name = $data['course_name'] ?? '';
$course_url = $data['course_url'] ?? '';  // Capture the course URL
$liked = isset($data['liked']) ? (int)$data['liked'] : null;
$user_id = $_SESSION['google_id'];

if (!$course_name || !$course_url || is_null($liked)) {
    // file_put_contents($log_file, "Error: Invalid data received.\n", FILE_APPEND);
    // exit(json_encode(["success" => false, "message" => "Invalid data."]));
}

// Insert or update preference
$stmt = $pdo->prepare("
    INSERT INTO CourseraPreferences (user_id, course_name, course_url, liked)
    VALUES (:user_id, :course_name, :course_url, :liked)
    ON DUPLICATE KEY UPDATE liked = :liked, course_url = :course_url
");

$execution_status = $stmt->execute([
    ':user_id' => $user_id,
    ':course_name' => $course_name,
    ':course_url' => $course_url,
    ':liked' => $liked
]);

// Log SQL execution status
if ($execution_status) {
    file_put_contents($log_file, "Database Insert Success: " . print_r($stmt->rowCount(), true) . "\n", FILE_APPEND);
    exit(json_encode(["success" => true]));
} else {
    file_put_contents($log_file, "Error: Database insert failed.\n", FILE_APPEND);
    exit(json_encode(["success" => false, "message" => "Failed to save preference."]));
}

?>
