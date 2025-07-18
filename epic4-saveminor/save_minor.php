<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['google_loggedin'])) {
    echo json_encode(["success" => false, "message" => "Not authenticated."]);
    exit;
}

$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['minor_id'])) {
    echo json_encode(["success" => false, "message" => "Minor ID is missing."]);
    exit;
}

$minor_id = $data['minor_id'];
$user_id = $_SESSION['google_id'];

// Check if the minor is already saved
$stmt = $pdo->prepare("SELECT 1 FROM SavedMinors WHERE user_id = ? AND minor_id = ?");
$stmt->execute([$user_id, $minor_id]);
$existingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingRequest) {
    // If it's already submitted, return a message
    if ($existingRequest['status'] === 'Submitted') {
        echo json_encode(["success" => false, "message" => "This minor has already been submitted."]);
        exit;
    } else {
        echo json_encode(["success" => false, "message" => "This minor is already saved."]);
        exit;
    }
}

// Save minor
$stmt = $pdo->prepare("INSERT INTO SavedMinors (user_id, minor_id, status) VALUES (?, ?, 'Not Submitted')");
$stmt->execute([$user_id, $minor_id]);

// Fetch saved minor details
$stmt = $pdo->prepare("SELECT * FROM Minors WHERE MinorID = ?");
$stmt->execute([$minor_id]);
$minor = $stmt->fetch(PDO::FETCH_ASSOC);

if ($stmt->rowCount() > 0) {
    echo json_encode(["success" => true, "minor" => $minor]);
} else {
    echo json_encode(["success" => false, "message" => "Error saving minor."]);
}
