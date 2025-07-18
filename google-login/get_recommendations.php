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
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Fetch academic and career preferences
$stmt = $pdo->prepare("SELECT preference FROM user_preferences WHERE user_id = ? AND type = 'academic'");
$stmt->execute([$_SESSION['google_id']]);
$academicPreferences = $stmt->fetchAll(PDO::FETCH_COLUMN);
$academicCount = count($academicPreferences); // Count the academic preferences

$stmt = $pdo->prepare("SELECT preference FROM user_preferences WHERE user_id = ? AND type = 'career'");
$stmt->execute([$_SESSION['google_id']]);
$careerPreferences = $stmt->fetchAll(PDO::FETCH_COLUMN);
$careerCount = count($careerPreferences); // Count the career preferences

$recommendedAcademic = [];
$recommendedCareer = [];

$userInterests = array_merge($academicPreferences, $careerPreferences);

if (!empty($userInterests)) {
    $query = "SELECT * FROM Minors WHERE MinorID NOT IN (SELECT minor_id FROM SavedMinors WHERE user_id = ?)";
    
    $conditions = [];
    $params = [$_SESSION['google_id']];

    foreach ($userInterests as $interest) {
        $conditions[] = "(academic_tags LIKE ? OR career_tags LIKE ?)";
        $params[] = "%" . $interest . "%";
        $params[] = "%" . $interest . "%";
    }

    if (!empty($conditions)) {
        $query .= " AND (" . implode(" OR ", $conditions) . ") LIMIT " . ($academicCount + $careerCount);

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $recommendedMinors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($recommendedMinors as $minor) {
            $academicTags = explode(", ", $minor['academic_tags'] ?? "");
            $careerTags = explode(", ", $minor['career_tags'] ?? "");

            $isAcademicMatch = false;
            $isCareerMatch = false;

            foreach ($academicPreferences as $pref) {
                if (in_array($pref, $academicTags)) {
                    $isAcademicMatch = true;
                    break;
                }
            }

            foreach ($careerPreferences as $pref) {
                if (in_array($pref, $careerTags)) {
                    $isCareerMatch = true;
                    break;
                }
            }

            if ($isAcademicMatch && !$isCareerMatch) {
                $recommendedAcademic[] = $minor;
            } elseif ($isCareerMatch && !$isAcademicMatch) {
                $recommendedCareer[] = $minor;
            }
        }
    }
}

// Return the results
echo json_encode([
    'success' => true,
    'academic' => $recommendedAcademic,
    'career' => $recommendedCareer,
    'academicCount' => $academicCount,
    'careerCount' => $careerCount
]);
?>