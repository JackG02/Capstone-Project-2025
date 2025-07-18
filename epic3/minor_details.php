<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['google_loggedin'])) {
    header('Location: login.php');
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
    exit('Database connection failed!');
}

// Get minor ID from the URL
if (!isset($_GET['minor_id']) || empty($_GET['minor_id'])) {
    exit('Invalid Minor ID');
}

$minor_id = $_GET['minor_id'];

// Fetch minor details
$stmt = $pdo->prepare("SELECT * FROM Minors WHERE MinorID = :minor_id");
$stmt->execute([':minor_id' => $minor_id]);
$minor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$minor) {
    exit('Minor not found.');
}

// Fetch required courses
$stmt_courses = $pdo->prepare("SELECT c.course_name, c.course_code, c.instructor, c.semester_offered, c.description 
                               FROM Courses c 
                               INNER JOIN MinorCourses mc ON c.CourseID = mc.CourseID 
                               WHERE mc.MinorID = :minor_id");
$stmt_courses->execute([':minor_id' => $minor_id]);
$required_courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

// Coursera API Credentials
$coursera_access_token = "DF4q8el8wkId5HKNoWBmDG5r5N0C";

// Function to get Coursera courses
function getCourseraCourses($minor_name, $access_token) {
    $query = urlencode($minor_name);
    $api_url = "https://api.coursera.org/api/courses.v1?q=search&fields=id,name,slug,primaryLanguages&query=$query";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['elements']) || empty($data['elements'])) return [];

    return array_map(function ($course) {
        return [
            'name' => $course['name'],
            'url' => isset($course['slug']) ? "https://www.coursera.org/learn/" . $course['slug'] : "#"
        ];
    }, array_slice($data['elements'], 0, 3)); // Limit to 3 courses
}

// Fetch Coursera courses
$coursera_courses = getCourseraCourses($minor['name'], $coursera_access_token);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars($minor['name']) ?> - Details</title>
    <link rel="stylesheet" href="https://unpkg.com/rivet-core@2.8.1/css/rivet.min.css">
     <!-- Bootstrap 5 CSS -->
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* Custom Styling */
        .minor-title {
            font-size: 2rem;
            font-weight: bold;
            color: #990000;
            text-transform: uppercase;
            border-bottom: 2px solid #990000;
            padding-bottom: 10px;
        }

        .details-container {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    margin-top: 20px;
}

/* Default: Side-by-Side */
.details-section {
    flex: 1;
    min-width: 45%;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
}

/* On Small Screens (Stacked View) */
@media screen and (max-width: 768px) {
    .details-container {
        flex-direction: column; /* Stack sections */
    }
    
    .details-section {
        width: 100%; /* Full width */
        min-width: unset;
    }
}


        .details-section h2 {
            font-size: 1.5rem;
            color: #0055A2;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }

        .course-list {
            list-style: none;
            padding: 0;
        }

        .course-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-top: 10px;
        }

        .course-item h6 {
            font-size: 1.2rem;
            color: #0055A2;
            margin: 0;
        }

        .coursera-course a {
            color: #0055A2;
            text-decoration: none;
            font-weight: bold;
        }

        .button-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
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
            margin-right: 10px;
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
    border-left: 5px solid #990000; /* IU Crimson */
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
    gap: 10px;
}

.saved-minor-actions button {
    padding: 6px 12px;
    font-size: 0.875rem;
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

.coursera-course a {
    display: inline-block;
    font-size: 1.1rem;
    font-weight: bold;
    /* color: #0056D2; Coursera Blue */
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 6px;
    transition: color 0.3s ease, transform 0.2s ease;
}

.coursera-course a:hover {
    color: #6A0DAD; /* Purple on hover */
    transform: scale(1.05); /* Slight zoom effect */
}

.coursera-course {
    display: flex;
    align-items: center;
    justify-content: space-between; /* Pushes course name left, button right */
    gap: 10px;
}

.coursera-course h6 {
    flex-grow: 1; /* Allows course name to take up available space */
    margin: 0;
}

.like-course {
    padding: 6px 12px;
    font-size: 0.875rem;
    background-color: #0055A2; /* Keep button blue */
    border: none;
    cursor: pointer;
    border-radius: 5px;
    color: white;
    transition: background 0.2s ease, transform 0.2s ease;
}

.like-course:hover {
    background-color: #003366; /* Darker blue on hover */
    transform: scale(1.05);
}




    </style>
</head>
<body class="rvt-layout">

<header class="rvt-header-wrapper">
    <div class="rvt-header-global">
        <div class="container-fluid">
            <nav class="navbar navbar-expand-md">
                <!-- Logo + Title -->
                <a class="navbar-brand rvt-lockup d-flex align-items-center" href="../google-login/profile.php">
    <img src="../images/IULogo.jpg" alt="IU Logo" style="height: 40px; width: auto; margin-right: -2px;"
    >
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

<main class="rvt-container-lg rvt-p-tb-xxl">
    <h1 class="minor-title"><?= htmlspecialchars($minor['name']) ?></h1>
    <div class="button-container">
        <button class="rvt-button rvt-button--primary save-minor" data-minor-id="<?= htmlspecialchars($minor['MinorID']) ?>">Save Minor to Profile</button>
        <button class="rvt-button rvt-button--secondary" onclick="window.history.back()">Back to Minors</button>
    </div>

    <div class="rvt-box rvt-m-top-lg">
        <div class="rvt-box__body">
            <p><strong>Department:</strong> <?= htmlspecialchars($minor['department']) ?></p>
            <p><strong>Total Credits:</strong> <?= htmlspecialchars($minor['total_credits']) ?></p>
            <p><strong>Description:</strong> <?= htmlspecialchars($minor['description']) ?></p>
        </div>
    </div>

    <div class="details-container">
        <!-- Required Courses Section -->
        <div class="details-section">
            <h2>Required Courses <span class="info-icon" data-tooltip="These are the core courses required to complete this minor.">i</span></h2>
            <?php if (!empty($required_courses)): ?>
                <ul class="course-list">
                    <?php foreach ($required_courses as $course): ?>
                        <li class="course-item">
                            <h6><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)</h6>
                            <p><strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?></p>
                            <p><strong>Semester:</strong> <?= htmlspecialchars($course['semester_offered']) ?></p>
                            <p><strong>Description:</strong> <?= htmlspecialchars($course['description']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No required courses listed for this minor.</p>
            <?php endif; ?>
        </div>

         <!-- Coursera Courses Section -->
         <div class="details-section">
            <h2>Relevant Coursera Courses<span class="info-icon" data-tooltip="Online courses that align with this minor, available on Coursera.">i</span></h2>
            <?php if (!empty($coursera_courses)): ?>
                <ul class="course-list">
                    <?php foreach ($coursera_courses as $course): ?>
                        <li class="course-item coursera-course">
    <h6>
        <a href="<?= htmlspecialchars($course['url']) ?>" target="_blank">
            <?= htmlspecialchars($course['name']) ?>
        </a>
    </h6>
    <div class="like-dislike-buttons">
    <button class="rvt-button like-course" 
        id="like-<?= md5($course['name']) ?>" 
        data-course-name="<?= htmlspecialchars($course['name']) ?>"
        data-course-url="<?= htmlspecialchars($course['url']) ?>"> Like
</button>


    </div>
</li>

                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No Coursera courses found for this minor.</p>
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

</body>



<script>
document.addEventListener("DOMContentLoaded", function () {
    function sendPreference(courseName, liked) {
        fetch("../epic3/save_course_preference.php", {
            method: "POST",
            body: JSON.stringify({ course_name: courseName, liked }),
            headers: { "Content-Type": "application/json" }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(liked ? "Course Liked! Check your Profile to see saved Coursera courses!" : "Course Disliked!");
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("An error occurred while saving your preference.");
        });
    }

    document.querySelectorAll(".like-course").forEach(button => {
        button.addEventListener("click", function () {
            sendPreference(this.dataset.courseName, 1);
        });
    });

    document.querySelectorAll(".dislike-course").forEach(button => {
        button.addEventListener("click", function () {
            sendPreference(this.dataset.courseName, 0);
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelector(".save-minor").addEventListener("click", function () {
        let minorId = this.dataset.minorId;
        if (!minorId) {
            alert("Error: Minor ID is missing.");
            return;
        }

        fetch("../epic4-saveminor/save_minor.php", {
            method: "POST",
            body: JSON.stringify({ minor_id: minorId }),
            headers: { "Content-Type": "application/json" }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Minor saved successfully!");
                this.innerText = "Saved";
                this.classList.remove("rvt-button--primary");
                this.classList.add("rvt-button--success");
                this.disabled = true;
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("An error occurred while saving the minor.");
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".like-course").forEach(button => {
        button.addEventListener("click", function () {
            const courseName = this.dataset.courseName;
            const courseUrl = this.dataset.courseUrl;

            fetch("../epic3/save_course_preference.php", {
                method: "POST",
                body: JSON.stringify({ course_name: courseName, course_url: courseUrl, liked: 1 }),
                headers: { "Content-Type": "application/json" }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.innerText = "✔ Liked";
                    this.style.backgroundColor = "#28a745"; // Green for liked
                    this.style.color = "white";
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });
        });
    });
});


</script>

</body>
</html>
