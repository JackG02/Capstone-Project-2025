<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable error reporting for debugging

// Ensure user is logged in
if (!isset($_SESSION['google_loggedin'])) {
    echo json_encode(["success" => false, "message" => "You must be logged in to submit a minor."]);
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
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['minor_id']) || empty($data['minor_id'])) {
    echo json_encode(["success" => false, "message" => "Minor ID is missing."]);
    exit;
}

$minor_id = (int) $data['minor_id']; // Ensure integer type
$user_id = (int) $_SESSION['google_id']; // Ensure integer type

// Check if the minor exists for this user
$stmt = $pdo->prepare("SELECT * FROM SavedMinors WHERE user_id = ? AND minor_id = ?");
$stmt->execute([$user_id, $minor_id]);
$existingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingRequest) {  // Fix: Correct check for fetching result
    echo json_encode(["success" => false, "message" => "This minor is not saved or does not exist."]);
    exit;
}

// If the minor is already submitted, prevent resubmission
if ($existingRequest['status'] === 'Submitted') {
    echo json_encode(["success" => false, "message" => "This minor has already been submitted."]);
    exit;
}

// Mark the minor as submitted
$stmt = $pdo->prepare("UPDATE SavedMinors SET submitted_at = NOW(), status = 'Submitted' WHERE user_id = ? AND minor_id = ?");
if ($stmt->execute([$user_id, $minor_id])) {
    echo json_encode(["success" => true, "message" => "Minor submitted successfully!"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to submit minor."]);
}
exit;
