<?php
session_start();

// Database connection
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit(json_encode(['success' => false, 'message' => 'Database connection failed!']));
}

// Check if user is logged in
if (!isset($_SESSION['google_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Not authenticated!']));
}

// Handle JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data['type']) && !empty($data['preference'])) {
    $type = $data['type'];
    $preference = $data['preference'];
    $userId = $_SESSION['google_id'];

    // Insert preference into database
    // Insert preference into database
$stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, type, preference) VALUES (?, ?, ?)');
$stmt->execute([$userId, $type, $preference]);

$insertedId = $pdo->lastInsertId();  // ✅ Get the new ID

echo json_encode([
    'success' => true,
    'id' => $insertedId  // ✅ Send it back to JS
]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input!']);
}
?>