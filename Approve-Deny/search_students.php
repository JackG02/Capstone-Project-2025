<?php
session_start();

// Ensure only advisors can access this
if (!isset($_SESSION['google_loggedin']) || $_SESSION['role'] !== 'advisor') {
    echo json_encode([]); // Return an empty array if unauthorized
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
    echo json_encode(["error" => "Database connection failed!"]);
    exit;
}

// Get the search term
$searchTerm = isset($_GET['query']) ? trim($_GET['query']) : '';

if ($searchTerm === 'all') {
    // Return all submitted and pending requests
    $stmt = $pdo->prepare("
        SELECT 
        sm.id AS request_id, 
        a.id AS student_id, 
        a.name AS student_name, 
        a.email, 
        ud.major, 
        m.name AS minor_name 
    FROM SavedMinors sm
    JOIN accounts a ON sm.user_id = a.id
    JOIN Minors m ON sm.minor_id = m.MinorID
    LEFT JOIN user_demographics ud ON a.id = ud.account_id
    WHERE sm.submitted_at IS NOT NULL AND sm.status = 'Submitted'
    ORDER BY sm.submitted_at DESC
    ");
    $stmt->execute();
} else {
    // Return filtered list
    $stmt = $pdo->prepare("
        SELECT 
        sm.id AS request_id, 
        a.id AS student_id, 
        a.name AS student_name, 
        a.email, 
        ud.major, 
        m.name AS minor_name 
    FROM SavedMinors sm
    JOIN accounts a ON sm.user_id = a.id
    JOIN Minors m ON sm.minor_id = m.MinorID
    LEFT JOIN user_demographics ud ON a.id = ud.account_id
    WHERE sm.submitted_at IS NOT NULL
    AND sm.status = 'Submitted'
    AND LOWER(a.name) LIKE :searchTerm
    ORDER BY a.name
    ");
    $stmt->execute(['searchTerm' => '%' . strtolower($searchTerm) . '%']);
}

// Return JSON response
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($students);
