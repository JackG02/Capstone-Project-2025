<?php
// Start the session
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['google_loggedin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['google_id'];

// Database connection
$db_host = 'db.luddy.indiana.edu';
$db_name = 'i494f24_team29';
$db_user = 'i494f24_team29';
$db_pass = 'claro8540aloud';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('Failed to connect to the database!');
}

// Fetch user information
$stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->execute([$_SESSION['google_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch preferences
$stmt = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
$stmt->execute([$_SESSION['google_id']]);
$preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize preferences by type
$organizedPreferences = [];
foreach ($preferences as $preference) {
    $organizedPreferences[$preference['type']][] = $preference;
}

// Coursera preferences
$stmt = $pdo->prepare("
    SELECT course_name, course_url, liked 
    FROM CourseraPreferences 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['google_id']]);
$courseraPreferences = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch academic preferences
$stmt = $pdo->prepare("SELECT preference FROM user_preferences WHERE user_id = ? AND type = 'academic'");
$stmt->execute([$_SESSION['google_id']]);
$academicPreferences = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch career preferences
$stmt = $pdo->prepare("SELECT preference FROM user_preferences WHERE user_id = ? AND type = 'career'");
$stmt->execute([$_SESSION['google_id']]);
$careerPreferences = $stmt->fetchAll(PDO::FETCH_COLUMN);


$savedCourses = $savedCourses ?? [];  // Ensure it's always an array
$academicPreferences = $academicPreferences ?? [];
$careerPreferences = $careerPreferences ?? [];

$userInterests = array_merge($savedCourses, $academicPreferences, $careerPreferences);



// Ensure there are interests before running the recommendation query
$recommendedAcademic = [];
$recommendedCareer = [];

if (!empty($userInterests)) {
    $recommendationQuery = "
        SELECT * FROM Minors 
        WHERE MinorID NOT IN (
            SELECT minor_id FROM SavedMinors WHERE user_id = ?
        )";
    
    $conditions = [];
    $params = [$_SESSION['google_id']];

    foreach ($userInterests as $interest) {
        $conditions[] = "(academic_tags LIKE ? OR career_tags LIKE ?)";
        $params[] = "%" . $interest . "%";
        $params[] = "%" . $interest . "%";
    }

    if (!empty($conditions)) {
        $recommendationQuery .= " AND (" . implode(" OR ", $conditions) . ") 
                                  ORDER BY 
                                  (CASE 
                                    WHEN academic_tags IS NOT NULL THEN 1 
                                    WHEN career_tags IS NOT NULL THEN 2 
                                    ELSE 3 
                                  END) 
                                  LIMIT 6";

        $stmt = $pdo->prepare($recommendationQuery);
        $stmt->execute($params);
        $recommendedMinors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Distribute minors into academic and career categories
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
        
            if ($isAcademicMatch && !$isCareerMatch && count($recommendedAcademic) < 3) {
                $recommendedAcademic[] = $minor;
            } elseif ($isCareerMatch && !$isAcademicMatch && count($recommendedCareer) < 3) {
                $recommendedCareer[] = $minor;
            }
        }
        
    }
}


// Recommend minors by completed courses
$completedCoursesQuery = "SELECT CourseID FROM Take WHERE user_id = :user_id";
$stmt = $pdo->prepare($completedCoursesQuery);
$stmt->execute([':user_id' => $user_id]);
$completedCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

$courseRecommendationQuery = "SELECT m.* FROM Minors m 
                              JOIN MinorCourses mc ON m.MinorID = mc.MinorID 
                              WHERE ";
$courseConditions = [];
$courseParams = [];

foreach ($completedCourses as $index => $courseID) {
    $paramKey = ":course$index";
    $courseConditions[] = "mc.CourseID = $paramKey";
    $courseParams[$paramKey] = $courseID;
}

$rawMinors = $stmt->fetchAll(PDO::FETCH_ASSOC);
$recommendedMinorsByCourses = [];

if (!empty($courseConditions)) {
    $courseRecommendationQuery .= implode(" OR ", $courseConditions);
    $courseRecommendationQuery .= " GROUP BY m.MinorID";
    $stmt = $pdo->prepare($courseRecommendationQuery);
    $stmt->execute($courseParams);
    $rawMinors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawMinors as $minor) {
        $stmt = $pdo->prepare("SELECT CourseID FROM MinorCourses WHERE MinorID = ?");
        $stmt->execute([$minor['MinorID']]);
        $requiredCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $completedCount = count(array_intersect($requiredCourses, $completedCourses));
        $totalCourses = count($requiredCourses);

        $minor['completed_courses'] = $completedCount;
        $minor['required_courses'] = $totalCourses;
        $minor['completion_ratio'] = $totalCourses > 0 ? $completedCount / $totalCourses : 0;

        $recommendedMinorsByCourses[] = $minor;
    }

    // Sorting by how close the user is to completing each minor
    usort($recommendedMinorsByCourses, function ($a, $b) {
        return $b['completion_ratio'] <=> $a['completion_ratio'];
    });
}

//  If nothing matched, show fallback minors
if (empty($recommendedMinorsByCourses)) {
    $stmt = $pdo->query("SELECT * FROM Minors LIMIT 5");
    $recommendedMinorsByCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recommendedMinorsByCourses as &$minor) {
        $stmt = $pdo->prepare("SELECT CourseID FROM MinorCourses WHERE MinorID = ?");
        $stmt->execute([$minor['MinorID']]);
        $requiredCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $completedCount = count(array_intersect($requiredCourses, $completedCourses));
        $totalCourses = count($requiredCourses);

        $minor['completed_courses'] = $completedCount;
        $minor['required_courses'] = $totalCourses;
        $minor['completion_ratio'] = $totalCourses > 0 ? $completedCount / $totalCourses : 0;
    }
}

//$recommendedMinorsByCourses = [];
// if (!empty($courseConditions)) {
//     $courseRecommendationQuery .= implode(" OR ", $courseConditions);
//     $courseRecommendationQuery .= " GROUP BY m.MinorID ORDER BY COUNT(mc.CourseID) DESC"; // Prioritize minors with more matches
//     $stmt = $pdo->prepare($courseRecommendationQuery);
//     $stmt->execute($courseParams);
//     $recommendedMinorsByCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
// }



// Get the initials of the user
$name_parts = explode(' ', trim($user['name']));
$firstInitial = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : '';
$lastInitial = isset($name_parts[1][0]) ? strtoupper($name_parts[1][0]) : '';
$initials = $firstInitial . $lastInitial;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Profile</title>
    <link rel="stylesheet" href="https://unpkg.com/rivet-core@2.8.1/css/rivet.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</head>
<style>
    .rvt-header-global__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .rvt-input {
        width: 300px;
    }

    .rvt-avatar__image {
        background-color: transparent;
        color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1.5rem;
        width: 80px;
        height: 80px;
        border-radius: 50%;
    }

    main.rvt-layout__wrapper {
        /* margin-top: 0; */
        margin-top: -95px;
    }

    .rvt-button {
        margin-top: 10px;
    }

    .preference-item {
        margin-top: 15px;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .preference-item span {
        flex-grow: 1;
    }

    .preference-item button {
        margin-left: auto;
        background: none;
        border: none;
        font-size: 16px;
        color: grey;
        cursor: pointer;
    }

    .preference-item button:hover {
        color: black;
    }

    .rvt-prose h3 {
        margin-bottom: 10px;
    }

    .saved-minors-container {
        margin-top: 20px;
    }

    .rvt-header-global__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .rvt-input {
        width: 300px;
    }

    .rvt-avatar__image {
        background-color: transparent;
        color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1.5rem;
        width: 80px;
        height: 80px;
        border-radius: 50%;
    }

    main.rvt-layout__wrapper {
        /* margin-top: 0; */
        margin-top: -95px;
    }

    .rvt-button {
        margin-top: 10px;
    }

    .preference-item {
        margin-top: 15px;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .preference-item span {
        flex-grow: 1;
    }

    .preference-item button {
        margin-left: auto;
        background: none;
        border: none;
        font-size: 16px;
        color: grey;
        cursor: pointer;
    }

    .preference-item button:hover {
        color: black;
    }

    .rvt-prose h3 {
        margin-bottom: 10px;
    }

    .saved-minors-container {
        margin-top: 20px;
    }

    .saved-minor-card {
        border-left: 5px solid #990000;
        /* IU Crimson */
        background: #f7f7f7;
        padding: 16px;
        border-radius: 6px;
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease-in-out;
    }

    .saved-minor-card:hover {
        transform: scale(1.02);
    }

    .saved-minor-card h5 {
        font-size: 1.2rem;
        font-weight: bold;
    }

    .saved-minor-card p {
        margin-bottom: 10px;
    }

    .saved-minor-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .saved-minor-actions button {
        padding: 6px 12px;
        font-size: 0.875rem;
    }

    /* Reduce spacing below profile */
    .rvt-flex-md-up.rvt-items-center-md-up {
        margin-bottom: -60px !important;
        /* Reduce space below profile */
    }

    .rvt-header-global__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .rvt-input {
        width: 300px;
    }

    .rvt-button {
        margin-top: 10px;
        margin-right: 10px;
    }

    main.rvt-layout__wrapper {
        margin-top: -95px;
    }

    .preferences-container {
        display: flex;
        gap: 20px;
        /* Space between the two sections */
        justify-content: space-between;
        margin-top: 20px;
    }

    .preference-card {
        flex: 1;
        /* Makes both sections equal width */
        background: #f7f7f7;
        padding: 16px;
        border-radius: 6px;
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
    }

    .preference-card h3 {
        margin-bottom: 10px;
    }

/* Responsive Design */
@media (max-width: 768px) {
    .preferences-container {
        flex-direction: column; /* Stack them on mobile */
    }
}

.info-icon {
    display: inline-block;
    width: 18px;
    height: 18px;
    font-size: 14px;
    font-weight: bold;
    text-align: center;
    line-height: 18px;
    color: white;
    background-color: #0055A2;
    border-radius: 50%;
    cursor: pointer;
    position: relative;
    margin-left: 10px; 

}

/* Tooltip Styling */
.info-icon:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: #000;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.2s ease-in-out;
}

.info-icon::after {
    content: attr(data-tooltip);
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: #000;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease-in-out;
}
.description-container {
    background: #f7f7f7; /* Light gray background */
    border-left: 5px solid #990000; 
    padding: 20px;
    border-radius: 6px;
    box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
    margin: 40px auto; /* Add space below the navbar and center */
    max-width: 800px; /* Prevent full-width stretching */
    text-align: left; /* Center text */
}

.description-container h2 {
    font-size: 1.8rem;
    font-weight: bold;
    /* color: #990000; IU Crimson */
    margin-bottom: 10px;
}

.description-container p {
    font-size: 1rem;
    color: #333;
    line-height: 1.5;
}



</style>

<body class="rvt-layout">
<header class="rvt-header-wrapper">
    <div class="rvt-header-global">
        <div class="container-fluid">
            <nav class="navbar navbar-expand-md">
                <!-- Logo + Title -->
                <a class="navbar-brand rvt-lockup d-flex align-items-center" href="profile.php">
    <img src="../images/IULogo.jpg" alt="IU Logo" style="height: 40px; width: auto; margin-right: -2px;">
    <div class="rvt-lockup__body">
        <span class="rvt-lockup__title">PathFinder</span>
        <span class="rvt-lockup__subtitle">INFO I494/495 Capstone Team 29</span>
    </div>
</a>


                <!-- Hamburger Menu Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Navbar content collapses here -->
                <div class="collapse navbar-collapse" id="navbarContent">
                <form class="d-flex align-items-center ms-auto me-3 mt-md-0 mt-3" action="../epic3/search_minors.php" method="GET" style="gap: 0;">
    <input class="form-control rvt-input" type="search" name="query" placeholder="Search minors..." 
           style="height: 44px; border-top-right-radius: 0; border-bottom-right-radius: 0; margin: 0;">
    <button type="submit" class="rvt-button" 
            style="height: 44px; border-top-left-radius: 0; border-bottom-left-radius: 0; padding-top: 0.375rem; padding-bottom: 0.375rem; margin: 0;">
        Search
    </button>
</form>





<div class="d-flex align-items-center gap-2 mt-3 mt-md-0" style="height: 44px;">

    <button class="rvt-button" style="height: 44px; padding-top: 0.375rem; padding-bottom: 0.375rem; margin-bottom:10px;" onclick="window.location.href='../google-login/profile.php'">Profile</button>
    <button class="rvt-button" style="height: 44px; padding-top: 0.375rem; padding-bottom: 0.375rem; margin-bottom:10px;" onclick="window.location.href='../google-login/logout.php'">Logout</button>
</div>

                </div>
            </nav>
        </div>
    </div>
</header>

<main id="main-content" class="rvt-layout__wrapper rvt-container-sm">
    <div class="rvt-layout__content">
    
        <div class="rvt-flex-md-up rvt-items-center-md-up rvt-m-top-xxl">
            <div class="rvt-avatar rvt-avatar--xl rvt-m-right-lg-md-up">
                <div class="rvt-avatar__image" style="background-color:#990000; color:white; display:flex; justify-content:center; align-items:center; font-size:1.5rem;"> 
                    <?= $initials ?> 
                </div>
            </div>
            <div class="rvt-prose rvt-flow">
                <h1><?= htmlspecialchars($user['name']) ?></h1>
                <p class="rvt-ts-20 rvt-color-black-500"> <?= htmlspecialchars($user['email'] ?? '') ?> </p>

            </div>
        </div>
       
        </div>
        <div class=" mt-4 rvt-prose rvt-p-top-lg">
        <!-- <h2>Saved Minors<span class="info-icon" data-tooltip="These are the minors you have saved for future reference.">i</span></h2> -->
        <div class="description-container position-relative">
    <h2>Saved Minors</h2>
    <p>
        <strong>Saved Minors</strong> are the programs you've bookmarked for easy access. 
        You can <strong>view details</strong>, <strong>submit</strong> them for advisor approval, or <strong>remove</strong> them anytime.
    </p>
    
    <!-- Toggle Button Bottom Right -->
    <button class="toggle-btn rvt-button rvt-button--secondary" type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#savedMinorsCollapse" 
            aria-expanded="false" 
            aria-controls="savedMinorsCollapse">
        <span class="toggle-icon">-</span>
    </button>
</div>

<div class="card-body collapse show" id="savedMinorsCollapse">
    <?php
    $stmt = $pdo->prepare("
        SELECT m.MinorID, m.name, m.department 
        FROM SavedMinors sm
        INNER JOIN Minors m ON sm.minor_id = m.MinorID
        WHERE sm.user_id = ?
    ");
    $stmt->execute([$_SESSION['google_id']]);
    $savedMinors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="saved-minors-container">
        <?php if (empty($savedMinors)): ?>
            <p>You have not saved any minors yet.</p>
        <?php else: ?>
            <?php foreach ($savedMinors as $minor): ?>
                <div class="saved-minor-card">
                    <h5><?= htmlspecialchars($minor['name']) ?></h5>
                    <p><strong>Department:</strong> <?= htmlspecialchars($minor['department']) ?></p>
                    <div class="saved-minor-actions">
                    <a href="../epic3/minor_details.php?minor_id=<?= $minor['MinorID'] ?>" class="rvt-button rvt-button--secondary">View Details</a>

                        <button class="rvt-button rvt-button--danger delete-minor" data-minor-id="<?= $minor['MinorID'] ?>">Delete</button>
                        <?php
                        $status = $minor['status'] ?? 'Not Submitted';
                        $statusClasses = [
                            'Submitted' => '',
                            'Approved' => 'background-color: #c6efc6; border: 2px solid #5cb85c; color: #3c763d;',
                            'Denied' => 'background-color: #f8d7da; border: 2px solid #dc3545; color: #721c24;',
                            'Pending' => 'background-color: #ffeb99; border: 2px solid #ffcc00; color: #cc9900;',
                        ];
                        if ($status === 'Not Submitted') { ?>
                            <button class="rvt-button rvt-button--success submit-minor" data-minor-id="<?= $minor['MinorID'] ?>">Submit</button>
                        <?php } elseif ($status === 'Submitted') { ?>
                            <button class="rvt-button rvt-button--success" style="pointer-events: none;">Submitted</button>
                        <?php } else { ?>
                            <span class="rvt-button" style="pointer-events: none; <?= $statusClasses[$status] ?? '' ?>"><?= htmlspecialchars($status) ?></span>
                        <?php } ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>



<!-- Coursera Courses Saved -->
<div class="description-container position-relative">
    <div>
        <h2>Saved Coursera Courses</h2>
        <p>
            <strong>Coursera Preferences</strong> are online courses you've saved to support your academic and career interests. 
            These can complement your minor selection or help you explore new topics. Find Coursera courses associated with different minors by selecting <strong>view details</strong>.
        </p>
    </div>
    <button class="toggle-btn rvt-button rvt-button--secondary" type="button" data-bs-toggle="collapse" data-bs-target="#courseraCollapse" aria-expanded="true" aria-controls="courseraCollapse">
        <span class="toggle-icon">-</span>
    </button>
</div>

<div class="collapse show" id="courseraCollapse">
    <div class="rvt-prose rvt-flow rvt-p-top-lg rvt-m-top-xl">
        <div class="rvt-grid rvt-grid--gutter-md rvt-flex-md-up">
            <?php if (empty($courseraPreferences)): ?>
                <p>No Coursera courses saved yet.</p>
            <?php else: ?>
                <ul class="rvt-list-plain">
                    <?php foreach ($courseraPreferences as $course): ?>
                        <li class="preference-item">
                            <span>
                                <a href="<?= !empty($course['course_url']) ? htmlspecialchars($course['course_url']) : '#' ?>" target="_blank">
                                    <?= !empty($course['course_name']) ? htmlspecialchars($course['course_name']) : '<em>Unknown Course</em>' ?>
                                </a>
                            </span>
                            <button class="delete-course rvt-button rvt-button--primary" 
                                    data-course-name="<?= !empty($course['course_name']) ? htmlspecialchars($course['course_name']) : '' ?>">
                                Delete
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Preferences Section -->
<div class="description-container position-relative">
    <div>
        <h2>Preferences</h2>
        <p>
            <strong>Preferences</strong> help us tailor your experience by recommending minors that match your interests. 
            Select up to 3 preferences for both academic and career goals.
        </p>
    </div>
    <button class="toggle-btn rvt-button rvt-button--secondary"
 type="button" data-bs-toggle="collapse" data-bs-target="#preferencesCollapse" aria-expanded="true" aria-controls="preferencesCollapse">
        <span class="toggle-icon">–</span>
    </button>
</div>

<div class="collapse show" id="preferencesCollapse">
    <div class="preferences-container">
        <!-- Academic Preferences -->
        <div class="preference-card">
            <div class="rvt-card__body">
                <h3>Academic Preferences<span class="info-icon" data-tooltip="These preferences help suggest academic-relevant minors. Choose up to 3!">i</span></h3>
                <select id="academic-dropdown" class="rvt-select">
                    <option value="">Select an academic preference...</option>
                    <?php
                    $stmt = $pdo->prepare("SELECT DISTINCT academic_tags FROM Minors");
                    $stmt->execute();
                    $academicTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $tagSet = [];

                    foreach ($academicTags as $tagString) {
                        $tags = explode(', ', $tagString);
                        foreach ($tags as $tag) {
                            if (!in_array($tag, $tagSet)) {
                                $tagSet[] = $tag;
                                echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
                <button id="academic-save-btn" class="rvt-button rvt-button--primary" onclick="savePreferences('academic')">Save</button>

                <ul id="academic-list" class="rvt-list-plain">
                    <?php foreach ($organizedPreferences['academic'] ?? [] as $pref): ?>
                        <li id="preference-<?= $pref['id'] ?>" class="preference-item">
                            <span><?= htmlspecialchars($pref['preference']) ?></span>
                            <button onclick="deletePreference(<?= $pref['id'] ?>)">&#10005;</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Career Preferences -->
        <div class="preference-card">
            <div class="rvt-card__body">
                <h3>Career Preferences<span class="info-icon" data-tooltip="These preferences help suggest career-relevant minors. Choose up to 3!">i</span></h3>
                <select id="career-dropdown" class="rvt-select">
                    <option value="">Select a career preference...</option>
                    <?php
                    $stmt = $pdo->prepare("SELECT DISTINCT career_tags FROM Minors");
                    $stmt->execute();
                    $careerTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $tagSet = [];

                    foreach ($careerTags as $tagString) {
                        $tags = explode(', ', $tagString);
                        foreach ($tags as $tag) {
                            if (!in_array($tag, $tagSet)) {
                                $tagSet[] = $tag;
                                echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
                <button id="career-save-btn" class="rvt-button rvt-button--primary" onclick="savePreferences('career')">Save</button>

                <ul id="career-list" class="rvt-list-plain">
                    <?php foreach ($organizedPreferences['career'] ?? [] as $pref): ?>
                        <li id="preference-<?= $pref['id'] ?>" class="preference-item">
                            <span><?= htmlspecialchars($pref['preference']) ?></span>
                            <button onclick="deletePreference(<?= $pref['id'] ?>)">&#10005;</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

                    <!-- Recommended Minors Description with Collapse Button -->
<div class="description-container position-relative">
    <div>
        <h2>Recommended Minors</h2>
        <p>
            <strong>Recommended Minors</strong> are tailored based on your saved preferences. 
            Use these suggestions to discover programs that align with your goals and interests.
        </p>
    </div>
    <button class="toggle-btn rvt-button rvt-button--secondary"
 type="button" data-bs-toggle="collapse" data-bs-target="#recommendedMinorsCollapse" aria-expanded="true" aria-controls="recommendedMinorsCollapse">
        <span class="toggle-icon">–</span>
    </button>
</div>

<!-- Collapsible Recommended Minors Content -->
<div class="collapse show" id="recommendedMinorsCollapse">
    <div class="rvt-prose rvt-flow rvt-p-top-lg rvt-m-top-xl">
    <div class="recommended-minors-container">
    <!-- Academic Minors -->
    <div class="recommended-column academic">
        <h3>Academic Minors<span class="info-icon" data-tooltip="These are recommended based on your academic interests!">i</span></h3>
        <?php if (!empty($recommendedAcademic)): ?>
            <?php foreach ($recommendedAcademic as $minor): 
                $stmt = $pdo->prepare("SELECT CourseID FROM MinorCourses WHERE MinorID = ?");
                $stmt->execute([$minor['MinorID']]);
                $minorCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $completedCount = count(array_intersect($minorCourses, $completedCourses));
                $totalCourses = count($minorCourses);
                $completionText = "$completedCount/$totalCourses completed";
            ?>
                <div class="recommended-minor-card">
                    <h5><?= htmlspecialchars($minor['name']) ?></h5>
                    <p><strong>Department:</strong> <?= htmlspecialchars($minor['department']) ?></p>
                    <p><strong>Total Credits:</strong> <?= htmlspecialchars($minor['total_credits']) ?></p>
                    <p><strong>Description:</strong> <?= htmlspecialchars($minor['description']) ?></p>
                    <p><strong>Course Progress:</strong> <?= $completionText ?></p>
                    <div class="saved-minor-actions">
                        <a href="../epic3/minor_details.php?minor_id=<?= urlencode($minor['MinorID']) ?>" class="rvt-button rvt-button--primary">View Details</a>
                        <button class="rvt-button rvt-button--primary save-minor" data-minor-id="<?= htmlspecialchars($minor['MinorID']) ?>">Save Minor</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No academic recommendations yet.</p>
        <?php endif; ?>
    </div>

    <!-- Career Minors -->
    <div class="recommended-column career">
        <h3>Career Minors<span class="info-icon" data-tooltip="These minors align with your career preferences!">i</span></h3>
        <?php if (!empty($recommendedCareer)): ?>
            <?php foreach ($recommendedCareer as $minor): 
                $stmt = $pdo->prepare("SELECT CourseID FROM MinorCourses WHERE MinorID = ?");
                $stmt->execute([$minor['MinorID']]);
                $minorCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $completedCount = count(array_intersect($minorCourses, $completedCourses));
                $totalCourses = count($minorCourses);
                $completionText = "$completedCount/$totalCourses completed";
            ?>
                <div class="recommended-minor-card">
                    <h5><?= htmlspecialchars($minor['name']) ?></h5>
                    <p><strong>Department:</strong> <?= htmlspecialchars($minor['department']) ?></p>
                    <p><strong>Total Credits:</strong> <?= htmlspecialchars($minor['total_credits']) ?></p>
                    <p><strong>Description:</strong> <?= htmlspecialchars($minor['description']) ?></p>
                    <p><strong>Course Progress:</strong> <?= $completionText ?></p>
                    <div class="saved-minor-actions">
                        <a href="../epic3/minor_details.php?minor_id=<?= urlencode($minor['MinorID']) ?>" class="rvt-button rvt-button--primary">View Details</a>
                        <button class="rvt-button rvt-button--primary save-minor" data-minor-id="<?= htmlspecialchars($minor['MinorID']) ?>">Save Minor</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No career recommendations yet.</p>
        <?php endif; ?>
    </div>
</div>
</div>
        </div>



<style>
    .toggle-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 1rem;
    padding: 4px 10px;
    z-index: 10;
}

.toggle-btn .toggle-icon {
    font-weight: bold;
    font-size: 1.2rem;
}


    .recommended-minors-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px; /* Space between cards */
        justify-content: space-between; /* Ensures even spacing */
    }

                        .recommended-minor-card {
                            flex: 0 1 calc(33.33% - 20px);
                            /* Ensures exactly 3 per row */
                            background: #f7f7f7;
                            padding: 16px;
                            border-left: 5px solid #990000;
                            border-radius: 6px;
                            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
                            transition: transform 0.2s ease-in-out;
                        }

                        .recommended-minor-card:hover {
                            transform: scale(1.02);
                        }

                        .recommended-minor-card h5 {
                            font-size: 1.2rem;
                            font-weight: bold;
                        }

                        .recommended-minor-card p {
                            margin-bottom: 10px;
                        }

                        .saved-minor-actions {
                            display: flex;
                            gap: 10px;
                        }

                        .saved-minor-actions button {
                            padding: 6px 12px;
                            font-size: 0.875rem;
                        }

                        .recommended-minors-container {
                            margin-bottom: 30px !important;
                            /* Adds space below the section */
                        }

                        /* Responsive Fixes */
                        @media (max-width: 1024px) {
                            .recommended-minor-card {
                                flex: 0 1 calc(50% - 20px);
                                /* 2 per row on smaller screens */
                            }
                        }

                        @media (max-width: 768px) {
                            .recommended-minor-card {
                                flex: 0 1 100%;
                                /* 1 per row on mobile */
                            }
                        }

                        .saved-minors-container {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 20px;
                            /* Space between cards */
                            justify-content: space-between;
                            /* Ensures even spacing */
                        }

                        .saved-minor-card {
                            flex: 0 1 calc(50% - 20px);
                            /* Forces exactly 2 per row */
                            background: #f7f7f7;
                            padding: 16px;
                            border-left: 5px solid #990000;
                            /* IU Crimson */
                            border-radius: 6px;
                            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
                            transition: transform 0.2s ease-in-out;
                        }

                        .saved-minor-card:hover {
                            transform: scale(1.02);
                        }

                        .saved-minor-card h5 {
                            font-size: 1.2rem;
                            font-weight: bold;
                        }

                        .saved-minor-card p {
                            margin-bottom: 10px;
                        }

                        .saved-minor-actions {
                            display: flex;
                            gap: 10px;
                        }

                        /* Responsive Fix: Ensure 1 per row on small screens */
                        @media (max-width: 768px) {
                            .saved-minor-card {
                                flex: 0 1 100%;
                            }
                        }
                    </style>

                    <style>
                        .recommended-minors-container {
                            display: flex;
                            gap: 30px;
                            justify-content: space-between;
                        }

                        .recommended-column {
                            flex: 1;
                            min-width: 45%;
                        }

                        .recommended-minor-card {
                            background: #f7f7f7;
                            padding: 16px;
                            border-left: 5px solid #990000;
                            border-radius: 6px;
                            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
                            transition: transform 0.2s ease-in-out;
                            margin-bottom: 20px;
                        }

                        .recommended-minor-card:hover {
                            transform: scale(1.02);
                        }

                        .recommended-minor-card h5 {
                            font-size: 1.2rem;
                            font-weight: bold;
                        }

                        .recommended-minor-card p {
                            margin-bottom: 10px;
                        }

                        /* Make it stack on small screens */
                        @media (max-width: 768px) {
                            .recommended-minors-container {
                                flex-direction: column;
                            }

                            .recommended-column {
                                min-width: 100%;
                            }
                        }
                        @media (max-width: 768px) {
    .rvt-flex-md-up.rvt-items-center-md-up {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding-top: 2rem;
    }

    .rvt-avatar.rvt-avatar--xl {
        margin: 0 auto 1rem auto !important; /* center avatar wrapper */
        display: flex;
        justify-content: center;
    }

    .rvt-avatar__image {
        margin: 0 auto; /* center the actual initials circle */
    }
}



</style>


<!-- Description and Collapse Toggle -->
<div class="description-container position-relative">
    <div>
        <h2>Based on Your Completed Courses</h2>
        <p>
            These minors align with the classes you've already taken. 
            Consider them to make the most of your existing progress and reduce the number of additional requirements.
        </p>
    </div>
    <button class="toggle-btn rvt-button rvt-button--secondary" type="button" data-bs-toggle="collapse" data-bs-target="#courseBasedCollapse" aria-expanded="true" aria-controls="courseBasedCollapse">
        <span class="toggle-icon">–</span>
    </button>
</div>

<!-- Collapsible Content -->
<div class="collapse show" id="courseBasedCollapse">
    <div class="rvt-prose rvt-flow rvt-p-top-lg rvt-m-top-xl">
        <?php if (empty($recommendedMinorsByCourses)): ?>
        <?php else: ?>
            <div class="recommended-minors-container">
                <?php 
                foreach ($recommendedMinorsByCourses as $minor):
                    $stmt = $pdo->prepare("SELECT CourseID FROM MinorCourses WHERE MinorID = ?");
                    $stmt->execute([$minor['MinorID']]);
                    $minorCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $completedCount = count(array_intersect($minorCourses, $completedCourses));
                    $totalCourses = count($minorCourses);
                    $completionText = "$completedCount/$totalCourses completed";
                ?>
                    <div class="recommended-minor-card">
                        <h5><?= htmlspecialchars($minor['name']) ?></h5>
                        <p><strong>Department:</strong> <?= htmlspecialchars($minor['department']) ?></p>
                        <p><strong>Total Credits:</strong> <?= htmlspecialchars($minor['total_credits']) ?></p>
                        <p><strong>Description:</strong> <?= htmlspecialchars($minor['description']) ?></p>
                        <p><strong>Course Progress:</strong> <?= $completionText ?></p>

                        <div class="saved-minor-actions">
                            <a href="../epic3/minor_details.php?minor_id=<?= urlencode($minor['MinorID']) ?>" class="rvt-button rvt-button--primary">
                                View Details
                            </a>
                            <button class="rvt-button rvt-button--primary save-minor" data-minor-id="<?= htmlspecialchars($minor['MinorID']) ?>">
                                Save Minor
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>



</main>
<div aria-labelledby="social-heading" class="rvt-footer-social" role="complementary">
    <div class="rvt-container-lg">
        <h2 class="rvt-sr-only" id="social-heading">Social media</h2>
        <ul class="rvt-footer-social__list">
            <li>
                <a href="https://www.facebook.com/IndianaUniversity">
                    <span class="rvt-sr-only rvt-color-white">Facebook for IU</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" height="40" viewBox="0 0 40 40" width="40">
                        <path d="M20 40C31.0457 40 40 31.0457 40 20C40 8.9543 31.0457 0 20 0C8.9543 0 0 8.9543 0 20C0 31.0457 8.9543 40 20 40Z" fill="#7A1705"></path>
                        <path d="M24.8996 9.99982V13.1998H23.0996C23.0996 13.1998 21.4996 12.9998 21.4996 14.4998V16.9998H24.7996L24.3996 20.3998H21.4996V29.9998H17.6996V20.2998H15.0996V16.9998H17.7996V14.0998C17.7996 14.0998 17.4996 12.4998 18.8996 11.1998C20.2996 9.89982 22.1996 9.99982 22.1996 9.99982H24.8996Z" fill="#F7F7F8"></path>
                    </svg>
                </a>
            </li>
            <li>
                <a href="https://www.linkedin.com/company/indiana-university/">
                    <span class="rvt-sr-only rvt-color-white">Linkedin for IU</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" height="40" viewBox="0 0 40 40" width="40">
                        <path d="M20 40C31.0457 40 40 31.0457 40 20C40 8.9543 31.0457 0 20 0C8.9543 0 0 8.9543 0 20C0 31.0457 8.9543 40 20 40Z" fill="#7A1705"></path>
                        <path d="M11.3 16H15V28H11.3V16ZM13.2 10C14.4 10 15.4 11 15.4 12.2C15.4 13.4 14.4 14.4 13.2 14.4C12 14.4 11 13.4 11 12.2C11 11 12 10 13.2 10Z" fill="#F7F7F8"></path>
                        <path d="M17.3999 16.0002H20.9999V17.6002C21.4999 16.7002 22.6999 15.7002 24.4999 15.7002C28.2999 15.7002 28.9999 18.2002 28.9999 21.4002V28.0002H25.2999V22.2002C25.2999 20.8002 25.2999 19.0002 23.3999 19.0002C21.4999 19.0002 21.1999 20.5002 21.1999 22.1002V28.0002H17.4999V16.0002H17.3999Z" fill="#F7F7F8"></path>
                    </svg>
                </a>
            </li>
            <li>
                <a href="https://twitter.com/IndianaUniv">
                    <span class="rvt-sr-only rvt-color-white">Twitter for IU</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" height="40" viewBox="0 0 40 40" width="40">
                        <path d="M20 40C31.0457 40 40 31.0457 40 20C40 8.9543 31.0457 0 20 0C8.9543 0 0 8.9543 0 20C0 31.0457 8.9543 40 20 40Z" fill="#7A1705"></path>
                        <path d="M30.0002 13.7998C29.3002 14.0998 28.5002 14.2998 27.6002 14.3998C28.4002 13.8998 29.1002 13.0998 29.4002 12.0998C28.6002 12.5998 27.7002 12.8998 26.8002 13.0998C26.1002 12.2998 25.0002 11.7998 23.8002 11.7998C21.5002 11.7998 19.7002 13.5998 19.7002 15.8998C19.7002 16.1998 19.7002 16.4998 19.8002 16.7998C16.4002 16.5998 13.4002 14.9998 11.3002 12.4998C10.9002 13.0998 10.7002 13.7998 10.7002 14.5998C10.7002 15.9998 11.4002 17.2998 12.5002 17.9998C11.8002 17.9998 11.2002 17.7998 10.6002 17.4998C10.6002 17.4998 10.6002 17.4998 10.6002 17.5998C10.6002 19.5998 12.0002 21.1998 13.9002 21.5998C13.6002 21.6998 13.2002 21.6998 12.8002 21.6998C12.5002 21.6998 12.3002 21.6998 12.0002 21.5998C12.5002 23.1998 14.0002 24.3998 15.8002 24.3998C14.4002 25.4998 12.6002 26.1998 10.7002 26.1998C10.4002 26.1998 10.0002 26.1998 9.7002 26.0998C11.5002 27.2998 13.7002 27.8998 16.0002 27.8998C23.5002 27.8998 27.7002 21.5998 27.7002 16.1998C27.7002 15.9998 27.7002 15.7998 27.7002 15.6998C28.8002 15.2998 29.4002 14.5998 30.0002 13.7998Z" fill="#F7F7F8"></path>
                    </svg>
                </a>
            </li>
            <li>
                <a href="https://www.instagram.com/iubloomington/">
                    <span class="rvt-sr-only rvt-color-white">Instagram for IU</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" height="40" viewBox="0 0 40 40" width="40">
                        <path d="M20 40C31.0457 40 40 31.0457 40 20C40 8.9543 31.0457 0 20 0C8.9543 0 0 8.9543 0 20C0 31.0457 8.9543 40 20 40Z" fill="#7A1705"></path>
                        <path d="M24.3004 29.9999H15.6004C12.5004 29.9999 9.90039 27.4999 9.90039 24.2999V15.5999C9.90039 12.4999 12.4004 9.8999 15.6004 9.8999H24.3004C27.4004 9.8999 30.0004 12.3999 30.0004 15.5999V24.2999C30.0004 27.4999 27.5004 29.9999 24.3004 29.9999ZM24.3004 28.4999C25.4004 28.4999 26.5004 28.0999 27.2004 27.2999C27.9004 26.4999 28.4004 25.4999 28.4004 24.3999V15.6999C28.4004 14.5999 28.0004 13.4999 27.2004 12.7999C26.4004 11.9999 25.4004 11.5999 24.3004 11.5999H15.6004C14.5004 11.5999 13.4004 11.9999 12.7004 12.7999C11.9004 13.5999 11.5004 14.5999 11.5004 15.6999V24.3999C11.5004 25.4999 11.9004 26.5999 12.7004 27.2999C13.5004 27.9999 14.5004 28.4999 15.6004 28.4999H24.3004Z" fill="#F7F7F8"></path>
                        <path d="M25.4006 19.9C25.4006 22.9 23.0006 25.3 20.0006 25.3C17.0006 25.3 14.6006 22.9 14.6006 19.9C14.6006 16.9 17.0006 14.5 20.0006 14.5C23.0006 14.5 25.4006 17 25.4006 19.9ZM20.0006 16.4C18.1006 16.4 16.5006 18 16.5006 19.9C16.5006 21.8 18.1006 23.4 20.0006 23.4C21.9006 23.4 23.5006 21.8 23.5006 19.9C23.5006 18 21.9006 16.4 20.0006 16.4Z" fill="#F7F7F8"></path>
                        <path d="M25.5002 15.8002C26.2182 15.8002 26.8002 15.2182 26.8002 14.5002C26.8002 13.7822 26.2182 13.2002 25.5002 13.2002C24.7822 13.2002 24.2002 13.7822 24.2002 14.5002C24.2002 15.2182 24.7822 15.8002 25.5002 15.8002Z" fill="#F7F7F8"></path>
                    </svg>
                </a>
            </li>
            <li>
                <a href="https://www.youtube.com/user/iu">
                    <span class="rvt-sr-only rvt-color-white">Youtube for IU</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" height="40" viewBox="0 0 40 40" width="40">
                        <path d="M20,40A20,20,0,1,0,0,20,20,20,0,0,0,20,40Z" fill="#7A1705"></path>
                        <path d="M29.58,15.17a2.49,2.49,0,0,0-1.77-1.78C26.25,13,20,13,20,13s-6.25,0-7.81.42a2.49,2.49,0,0,0-1.77,1.78A26.26,26.26,0,0,0,10,20a26.23,26.23,0,0,0,.42,4.84,2.47,2.47,0,0,0,1.77,1.75C13.75,27,20,27,20,27s6.25,0,7.81-.42a2.47,2.47,0,0,0,1.77-1.75A26.23,26.23,0,0,0,30,20,26.26,26.26,0,0,0,29.58,15.17ZM18,23V17l5.23,3Z" fill="#F7F7F8"></path>
                    </svg>
                </a>
            </li>
        </ul>
    </div>
</div>
<div aria-labelledby="resources-heading" class="rvt-footer-resources" role="complementary">
    <h2 class="rvt-sr-only" id="resources-heading">Additional resources</h2>
    <div class="rvt-container-lg">
        <div class="rvt-row">
            <div class="rvt-cols-3-md">
                <h3 class="rvt-footer-resources__heading">Indiana University</h3>
                <div class="rvt-footer-resources__text-block">
                    107 S. Indiana Avenue
                    <br />
                    Bloomington, IN
                    <br />
                    47405-7000
                </div>
            </div>
            <div class="rvt-cols-3-md">
                <h3 class="rvt-footer-resources__heading">Services</h3>
                <ul class="rvt-footer-resources__list">
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://canvas.iu.edu">Canvas</a>
                    </li>
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://one.iu.edu">One.IU</a>
                    </li>
                </ul>
            </div>
            <div class="rvt-cols-3-md">
                <h3 class="rvt-footer-resources__heading">Email</h3>
                <ul class="rvt-footer-resources__list">
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://uits.iu.edu/exchange">Outlook Web Access</a>
                    </li>
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://google.iu.edu">Gmail at IU</a>
                    </li>
                </ul>
            </div>
            <div class="rvt-cols-3-md">
                <h3 class="rvt-footer-resources__heading">Find</h3>
                <ul class="rvt-footer-resources__list">
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://directory.iu.edu/">People Directory</a>
                    </li>
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://jobs.iu.edu/">Jobs at IU</a>
                    </li>
                    <li class="rvt-footer-resources__list-item">
                        <a href="https://www.iu.edu/nondiscrimination/index.html">Non-discrimination Notice</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<footer class="rvt-footer-base">
    <div class="rvt-container-lg">
        <div class="rvt-footer-base__inner">
            <div class="rvt-footer-base__logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <polygon fill="currentColor" points="15.3 3.19 15.3 5 16.55 5 16.55 15.07 13.9 15.07 13.9 1.81 15.31 1.81 15.31 0 8.72 0 8.72 1.81 10.12 1.81 10.12 15.07 7.45 15.07 7.45 5 8.7 5 8.7 3.19 2.5 3.19 2.5 5 3.9 5 3.9 16.66 6.18 18.98 10.12 18.98 10.12 21.67 8.72 21.67 8.72 24 15.3 24 15.3 21.67 13.9 21.67 13.9 18.98 17.82 18.98 20.09 16.66 20.09 5 21.5 5 21.5 3.19 15.3 3.19" fill="#231f20" />
                </svg>
            </div>
            <ul class="rvt-footer-base__list">
                <li class="rvt-footer-base__item">
                    <a class="rvt-footer-base__link" href="https://accessibility.iu.edu/assistance/">Accessibility</a>
                </li>
                <li class="rvt-footer-base__item">
                    <a class="rvt-footer-base__link" href="#0">Privacy Notice</a>
                </li>
                <li class="rvt-footer-base__item">
                    <a class="rvt-footer-base__link" href="#0">Copyright</a> © 2024 The Trustees of <a class="rvt-footer-base__link" href="https://www.iu.edu">Indiana University</a>
                </li>
            </ul>
        </div>
    </div>
</footer>
</main>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".delete-minor").forEach(button => {
                button.addEventListener("click", function() {
                    let minorId = this.dataset.minorId; // Get Minor ID from button
                    if (!confirm("Are you sure you want to delete this minor?")) return;

                    fetch("../epic4-saveminor/delete_minor.php", { // Full URL
                            method: "POST",
                            body: JSON.stringify({
                                minor_id: minorId
                            }),
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(response => response.text()) // Get raw response
                        .then(text => {
                            console.log("Raw response:", text); // Debugging
                            try {
                                return JSON.parse(text); // Convert to JSON
                            } catch (error) {
                                throw new Error("Invalid JSON response: " + text); // Handle errors
                            }
                        })
                        .then(data => {
                            if (data.success) {
                                alert("Minor deleted successfully!");
                                this.closest(".saved-minor-card").remove(); // Remove from UI
                            } else {
                                alert("Error: " + data.message);
                            }
                        })
                        .catch(error => {
                            console.error("Error deleting minor:", error);
                            alert("An error occurred. Check the console for details.");
                        });
                });
            });
        });


        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".submit-minor").forEach(button => {
                button.addEventListener("click", function() {
                    let minorId = this.dataset.minorId; // Get Minor ID
                    if (!confirm("Submit this minor to your advisor for approval?")) return;

                    fetch("../epic4-saveminor/submit_minor.php", { // Use full URL if needed
                            method: "POST",
                            body: JSON.stringify({
                                minor_id: minorId
                            }),
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(response => response.text()) // Get raw response
                        .then(text => {
                            console.log("Raw response:", text); // Debugging
                            try {
                                return JSON.parse(text); // Convert to JSON
                            } catch (error) {
                                throw new Error("Invalid JSON response: " + text);
                            }
                        })
                        .then(data => {
                            if (data.success) {
                                alert("Minor submitted successfully!");
                                this.innerText = "Submitted"; // Update button text
                                this.classList.remove("btn-primary");
                                this.classList.add("btn-secondary");
                                this.disabled = true; // Disable button after submission
                            } else {
                                alert("Error: " + data.message);
                            }
                        })
                        .catch(error => {
                            console.error("Error submitting minor:", error);
                            alert("An error occurred. Check the console for details.");
                        });
                });
            });
        });
    </script>

    <script>
        function savePreferences(type) {
            const dropdown = document.getElementById(`${type}-dropdown`);
            const selectedPreference = dropdown.value.trim();
            const list = document.getElementById(`${type}-list`);

            if (!selectedPreference) {
                alert('Please select a preference before saving.');
                return;
            }

            // Limit preferences to 3
            if (list.children.length >= 3) {
                alert(`You can only select up to 3 ${type} preferences.`);
                return;
            }

            fetch('save_preferences.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        type,
                        preference: selectedPreference
                    }),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Append preference to the list dynamically
                        const li = document.createElement("li");
                        li.className = "preference-item";
                        li.setAttribute("id", `preference-${data.id}`);
                        li.innerHTML = `
                <span>${selectedPreference}</span>
                <button onclick="deletePreference(${data.id})">&#10005;</button>
            `;
                        list.appendChild(li);

                        // Fetch recommendations after adding preference
                        fetchRecommendations();
                    } else {
                        alert("Error saving preference: " + data.message);
                    }
                });
        }

        function deletePreference(preferenceId) {
            if (!preferenceId) {
                alert("Error: Invalid preference ID.");
                return;
            }

            fetch("delete_preferences.php", {
                    method: "POST",
                    body: JSON.stringify({
                        id: preferenceId
                    }),
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the preference from the list dynamically
                        document.getElementById(`preference-${preferenceId}`).remove();
                        // Fetch recommendations after deletion
                        fetchRecommendations();
                    } else {
                        alert("Error deleting preference: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error deleting preference:", error);
                    alert("An error occurred.");
                });
        }


        function fetchRecommendations() {
    return fetch('get_recommendations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const academicContainer = document.querySelector(".recommended-column.academic");
                const careerContainer = document.querySelector(".recommended-column.career");

                // Clear existing content
                academicContainer.innerHTML = "<h3>Academic Minors</h3>";
                careerContainer.innerHTML = "<h3>Career Minors</h3>";

                const academicHasRecs = data.academic.length > 0;
                const careerHasRecs = data.career.length > 0;

                // If both are empty, show the no-recommendations message
                if (!academicHasRecs && !careerHasRecs) {
                    document.querySelector("#recommendedMinorsCollapse .rvt-prose").innerHTML = `
                        <p>No recommendations yet. Save more preferences to see recommendations!</p>
                    `;
                    return;
                }
                else {
    const proseContainer = document.querySelector("#recommendedMinorsCollapse .rvt-prose");

    if (!document.querySelector(".recommended-minors-container")) {
        proseContainer.innerHTML = `
            <div class="recommended-minors-container">
                <div class="recommended-column academic">
                    <h3>Academic Minors</h3>
                </div>
                <div class="recommended-column career">
                    <h3>Career Minors</h3>
                </div>
            </div>
        `;
    }
}


                // Make sure the container is visible
                const collapse = document.getElementById('recommendedMinorsCollapse');
                if (collapse && !collapse.classList.contains('show')) {
                    new bootstrap.Collapse(collapse, { toggle: true });
                }

                data.academic.forEach(minor => {
                    academicContainer.innerHTML += `
                        <div class="recommended-minor-card">
                            <h5>${minor.name}</h5>
                            <p><strong>Department:</strong> ${minor.department}</p>
                            <p><strong>Total Credits:</strong> ${minor.total_credits}</p>
                            <p><strong>Description:</strong> ${minor.description}</p>
                            <div class="saved-minor-actions">
                                <a href="../epic3/minor_details.php?minor_id=${encodeURIComponent(minor.MinorID)}" class="rvt-button rvt-button--primary">View Details</a>
                                <button class="rvt-button rvt-button--primary save-minor" data-minor-id="${minor.MinorID}">Save Minor</button>
                            </div>
                        </div>
                    `;
                });

                data.career.forEach(minor => {
                    careerContainer.innerHTML += `
                        <div class="recommended-minor-card">
                            <h5>${minor.name}</h5>
                            <p><strong>Department:</strong> ${minor.department}</p>
                            <p><strong>Total Credits:</strong> ${minor.total_credits}</p>
                            <p><strong>Description:</strong> ${minor.description}</p>
                            <div class="saved-minor-actions">
                                <a href="../epic3/minor_details.php?minor_id=${encodeURIComponent(minor.MinorID)}" class="rvt-button rvt-button--primary">View Details</a>
                                <button class="rvt-button rvt-button--primary save-minor" data-minor-id="${minor.MinorID}">Save Minor</button>
                            </div>
                        </div>
                    `;
                });
            } else {
                console.error("Error fetching recommendations:", data.message);
            }
        })
        .catch(error => {
            console.error("Error updating recommendations:", error);
        });
}

    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Add event listener for dynamically added delete buttons
            document.querySelector(".saved-minors-container").addEventListener("click", function(event) {
                if (event.target.classList.contains("delete-minor")) {
                    let minorId = event.target.dataset.minorId;
                    let minorCard = event.target.closest(".saved-minor-card");

                    // if (!confirm("Are you sure you want to delete this minor?")) return;

                    fetch("../epic4-saveminor/delete_minor.php", {
                            method: "POST",
                            body: JSON.stringify({
                                minor_id: minorId
                            }),
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert("Minor deleted successfully!");
                                minorCard.remove(); // Remove from saved list dynamically
                            } else {
                                alert("Error: " + data.message);
                            }
                        })
                }
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".save-minor").forEach(button => {
                button.addEventListener("click", function() {
                    let minorId = this.dataset.minorId;
                    let minorCard = this.closest(".recommended-minor-card");

                    if (!minorId) {
                        alert("Error: Minor ID is missing.");
                        return;
                    }

                    fetch("../epic4-saveminor/save_minor.php", {
                            method: "POST",
                            body: JSON.stringify({
                                minor_id: minorId
                            }),
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Move minor to saved list dynamically
                                addToSavedMinors(data.minor);
                                minorCard.remove(); // Remove from recommended list
                            } else {
                                alert("Error: " + data.message);
                            }
                        })
                        .catch(error => {
                            console.error("Error saving minor:", error);
                            alert("An error occurred.");
                        });
                });
            });
        });

        // Function to add the minor to the saved minors section
        function addToSavedMinors(minor) {
            if (!minor) return;

            let savedContainer = document.querySelector(".saved-minors-container");

            // Create a new saved minor card
            let newCard = document.createElement("div");
            newCard.classList.add("saved-minor-card");

            newCard.innerHTML = `
    <h5>${minor.name}</h5>
    <p><strong>Department:</strong> ${minor.department}</p>
    <div class="saved-minor-actions">
        <a href="../epic3/minor_details.php?minor_id=${encodeURIComponent(minor.MinorID)}" class="rvt-button rvt-button--primary">
            View Details
        </a>
        <button class="rvt-button rvt-button--danger delete-minor" data-minor-id="${minor.MinorID}">
            Delete
        </button>
        <button class="rvt-button rvt-button--success submit-minor" data-minor-id="${minor.MinorID}">
            Submit
        </button>
    </div>
`;



            savedContainer.appendChild(newCard);

            // Re-bind event listeners for delete and submit buttons
            newCard.querySelector(".delete-minor").addEventListener("click", function() {
                deleteMinor(this.dataset.minorId, newCard);
            });

            newCard.querySelector(".submit-minor").addEventListener("click", function() {
                submitMinor(this.dataset.minorId);
            });
        }
    </script>
    <script>
document.addEventListener("click", function(event) {
    if (event.target.classList.contains("save-minor")) {
        let minorId = event.target.dataset.minorId;
        let minorCard = event.target.closest(".recommended-minor-card");

        if (!minorId) {
            // alert("Error: Minor ID is missing.");
            return;
        }

        fetch("../epic4-saveminor/save_minor.php", {
            method: "POST",
            body: JSON.stringify({ minor_id: minorId }),
            headers: {
                "Content-Type": "application/json"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addToSavedMinors(data.minor);
                minorCard.remove(); // Remove from recommendations
            } else {
                // alert("Error: " + data.message);
            }
        })
        // .catch(error => {
        //     console.error("Error saving minor:", error);
        //     alert("An error occurred.");
        // });
    }
});
</script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".delete-course").forEach(button => {
                button.addEventListener("click", function() {
                    let courseName = this.dataset.courseName;
                    if (!confirm("Are you sure you want to remove this course?")) return;

                    fetch("../google-login/delete_course_preference.php", {

                            method: "POST",
                            body: JSON.stringify({
                                course_name: courseName
                            }),
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert("Course removed successfully!");
                                this.closest(".preference-item").remove(); // Remove from UI dynamically
                            } // else {
                            //     alert("Error: " + data.message);
                            // }
                        })
                        .catch(error => {
                            console.error("Error removing course:", error);
                            alert("An error occurred.");
                        });
                });
            });
        });
    </script>
   <script>
document.addEventListener("DOMContentLoaded", function () {
    const collapseElements = document.querySelectorAll('.collapse');

    collapseElements.forEach(collapse => {
        const collapseId = collapse.getAttribute('id');
        const toggleBtn = document.querySelector(`[data-bs-target="#${collapseId}"]`);
        if (!toggleBtn) return;

        const icon = toggleBtn.querySelector('.toggle-icon');
        if (!icon) return;

        // Set the correct initial icon
        icon.textContent = collapse.classList.contains('show') ? '–' : '+';

        // Use Bootstrap's Collapse API to listen to toggle events
        collapse.addEventListener('show.bs.collapse', () => {
            icon.textContent = '–';
        });

        collapse.addEventListener('hide.bs.collapse', () => {
            icon.textContent = '+';
        });
    });
});
</script>





</body>

</html>