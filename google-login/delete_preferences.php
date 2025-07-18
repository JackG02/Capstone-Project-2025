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
    error_log('Database connection error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

// Check if user is logged in
if (!isset($_SESSION['google_id'])) {
    exit(json_encode(['success' => false, 'message' => 'User not authenticated.']));
}

// Decode the incoming JSON payload
$data = json_decode(file_get_contents('php://input'), true);

// Validate the incoming data
if (isset($data['id']) && is_numeric($data['id'])) {
    $preferenceId = $data['id'];
    $userId = $_SESSION['google_id'];

    // Check if the preference exists before attempting to delete
    $checkStmt = $pdo->prepare('SELECT * FROM user_preferences WHERE id = :id AND user_id = :user_id');
    $checkStmt->execute([
        ':id' => $preferenceId,
        ':user_id' => $userId
    ]);

    if ($checkStmt->rowCount() > 0) {
        try {
            // Delete the preference from the preferences table
            $stmt = $pdo->prepare('DELETE FROM user_preferences WHERE id = :id AND user_id = :user_id');
            $stmt->execute([
                ':id' => $preferenceId,
                ':user_id' => $userId
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Preference deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Preference not found or unauthorized.']);
            }
        } catch (PDOException $e) {
            error_log('Error deleting preference: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete preference.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Preference not found or unauthorized.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing preference ID.']);
}
?>