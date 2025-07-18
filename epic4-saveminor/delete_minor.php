<?php
session_start();
header('Content-Type: application/json');

// Database connection
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database connection failed!"]);
    exit;
}

// Read input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['minor_id'])) {
    echo json_encode(["success" => false, "message" => "Minor ID not provided"]);
    exit;
}

// Delete minor from the database
$stmt = $pdo->prepare("DELETE FROM SavedMinors WHERE minor_id = ? AND user_id = ?");
$stmt->execute([$data['minor_id'], $_SESSION['google_id']]);

// Check if any row was deleted
if ($stmt->rowCount() > 0) {
    echo json_encode(["success" => true, "message" => "Minor deleted successfully"]);
} // else {
//     echo json_encode(["success" => false, "message" => "Minor not found or already deleted"]);
// }
exit;
?>
