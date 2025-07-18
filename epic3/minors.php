<?php
// Coursera API Credentials (Hardcoded for now)
$coursera_client_id = "3zzmcJJoSHxT4AKMukU3uFlvJLjADLxyMMkesNHWAJ33vCGQ";
$coursera_client_secret = "McNjxjba09gD5YPOvRDCJyuUj7LN3FwUQ2o3MGXQ6GQ3t6Aer90aHACptbZnGV5w";
$coursera_access_token = "DF4q8el8wkId5HKNoWBmDG5r5N0C"; // Temporary token

// Database connection
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit(json_encode(['error' => 'Database connection failed!']));
}

// Capture search, department, and keyword filters
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$department = isset($_GET['department']) && $_GET['department'] !== '' ? $_GET['department'] : null;
$keywords = isset($_GET['keywords']) ? explode(',', $_GET['keywords']) : [];

// Build query to fetch minors
$sql = "SELECT * FROM Minors WHERE name LIKE :search";
$params = [':search' => $search];

if ($department) {
    $sql .= " AND department = :department";
    $params[':department'] = $department;
}

// Apply keyword filtering
if (!empty($keywords)) {
    $keyword_conditions = [];
    foreach ($keywords as $index => $keyword) {
        $key = ":keyword" . $index;
        $keyword_conditions[] = "name LIKE $key OR description LIKE $key";
        $params[$key] = '%' . $keyword . '%';
    }
    $sql .= " AND (" . implode(" OR ", $keyword_conditions) . ")";
}

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$minors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get Coursera courses for a given minor
function getCourseraCourses($minor_name, $access_token) {
    $query = urlencode($minor_name);
    $api_url = "https://api.coursera.org/api/courses.v1?q=search&query=$query";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['elements'] ?? [];
}

// Fetch Coursera courses for each minor
foreach ($minors as &$minor) {
    $minor_name = $minor['name'];
    $coursera_courses = getCourseraCourses($minor_name, $coursera_access_token);

    // Include only relevant course data
    $minor['courses'] = array_map(function ($course) {
        return [
            'id' => $course['id'],
            'name' => $course['name'],
            'description' => $course['description'] ?? 'No description available',
            'url' => "https://www.coursera.org/learn/" . $course['id']
        ];
    }, $coursera_courses);
}

// Return JSON response
echo json_encode($minors);
?>
