<?php
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
