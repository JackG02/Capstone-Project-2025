<?php
session_start();
if (!isset($_SESSION['google_loggedin'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'advisor') {
    header("Location: ../google-login/profile.php?error=not_authorized");
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

// Get department filter if set
$departmentFilter = $_GET['department'] ?? '';

$whereClause = "";
$params = [];
if (!empty($departmentFilter)) {
    $whereClause = "WHERE m.department = ?";
    $params[] = $departmentFilter;
}

// Fetch distinct departments for filter dropdown
$departmentsStmt = $pdo->query("SELECT DISTINCT department FROM Minors ORDER BY department");
$departments = $departmentsStmt->fetchAll(PDO::FETCH_COLUMN);

// Top 5 most saved minors
$topQuery = "
    SELECT m.name, COUNT(sm.minor_id) AS count
    FROM SavedMinors sm
    JOIN Minors m ON sm.minor_id = m.MinorID
    $whereClause
    GROUP BY sm.minor_id
    ORDER BY count DESC
    LIMIT 5
";
$topStmt = $pdo->prepare($topQuery);
$topStmt->execute($params);
$topData = $topStmt->fetchAll(PDO::FETCH_ASSOC);

$topLabels = array_column($topData, 'name');
$topCounts = array_column($topData, 'count');

// Bottom 5 least saved minors
$bottomQuery = "
    SELECT m.name, COUNT(sm.minor_id) AS count
    FROM Minors m
    LEFT JOIN SavedMinors sm ON sm.minor_id = m.MinorID
    $whereClause
    GROUP BY m.MinorID
    ORDER BY count ASC
    LIMIT 5
";
$bottomStmt = $pdo->prepare($bottomQuery);
$bottomStmt->execute($params);
$bottomData = $bottomStmt->fetchAll(PDO::FETCH_ASSOC);

$bottomLabels = array_column($bottomData, 'name');
$bottomCounts = array_column($bottomData, 'count');

// Gender breakdown by department
$genderPieLabels = ['Male', 'Female'];
$genderPieData = [0, 0];
if (!empty($departmentFilter)) {
    $genderPieQuery = "
        SELECT ud.gender, COUNT(*) AS count
        FROM SavedMinors sm
        JOIN Minors m ON sm.minor_id = m.MinorID
        JOIN accounts a ON sm.user_id = a.id
        JOIN user_demographics ud ON a.id = ud.account_id
        WHERE m.department = ?
        GROUP BY ud.gender
    ";
    $stmt = $pdo->prepare($genderPieQuery);
    $stmt->execute([$departmentFilter]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $genderPieData = [
        $results['Male'] ?? 0,
        $results['Female'] ?? 0
    ];
}

// Top 5 minors by gender
function getTopMinorsByGender($pdo, $gender)
{
    $query = "
        SELECT m.name, COUNT(*) AS count
        FROM SavedMinors sm
        JOIN Minors m ON sm.minor_id = m.MinorID
        JOIN accounts a ON sm.user_id = a.id
        JOIN user_demographics ud ON a.id = ud.account_id
        WHERE ud.gender = ? AND sm.status = 'Not Submitted'
        GROUP BY sm.minor_id
        ORDER BY count DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$gender]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$maleMinorData = getTopMinorsByGender($pdo, 'Male');
$femaleMinorData = getTopMinorsByGender($pdo, 'Female');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Advisor Dashboard</title>
    <!-- Rivet and Bootstrap CSS -->
    <link rel="stylesheet" href="https://unpkg.com/rivet-core@2.8.1/css/rivet.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Additional styling for Rivet components can go here */
        .search-bar {
            margin-bottom: 20px;
        }

        .chart-section {
            margin-top: 60px;
            margin-bottom: 60px;
            padding: 20px;
            background-color: #f9f9f9;
            border-top: 4px solid #990000;
            text-align: center;
        }

        .chart-title {
            color: #990000;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .chart-wrapper {
            display: flex;
            flex-wrap: nowrap;
            gap: 40px;
            justify-content: space-between;
        }

        .chart-container {
            flex: 1;
            min-width: 0;
            max-width: 500px;
        }

        canvas {
            width: 100% !important;
            height: 300px !important;
        }

        .filter-form {
            margin-bottom: 30px;
        }


        .dashboard-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
            margin-top: 80px;
            margin-bottom: 40px;
        }

        .dashboard-header {
            flex: 2;
        }

        .dashboard-pies {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dashboard-pies canvas {
            max-width: 300px;
            margin: 0 auto;
        }

        .rvt-back-btn {
            position: absolute;
            left: 2rem;
            top: 6rem;
            margin-left: 10rem;
            float: left;
        }
    </style>
</head>

<body class="rvt-layout">
    <!-- Rivet Header -->
    <header class="rvt-header-wrapper">
        <div class="rvt-header-global">
            <div class="rvt-container-lg">
                <div class="rvt-header-global__inner">
                    <a class="rvt-lockup" href="adlanding.php">
                        <img src="../images/IULogo.jpg" alt="IU Logo" style="height: 40px; width: auto; margin-right: 10px;">
                        <div class="rvt-lockup__body">
                            <span class="rvt-lockup__title">PathFinder</span>
                            <span class="rvt-lockup__subtitle">Advisor Dashboard</span>
                        </div>
                    </a>
                    <div class="rvt-header-global__controls">
                        <button class="rvt-button" onclick="window.location.href='../google-login/logout.php'">Logout</button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- back button -->
    <div class="rvt-container-lg">
        <button class="rvt-button rvt-button--secondary rvt-back-btn" onclick="location.href='adlanding.php'">
            ← Back
        </button>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="rvt-layout__wrapper rvt-container-lg">
        <div class="rvt-container-lg dashboard-header-wrapper">
            <div class="dashboard-header">
                <h1 class="rvt-ts-32 rvt-text-bold mb-2">Welcome to Your Advisor Dashboard</h1>
                <p class="rvt-ts-16 mb-4">
                    This dashboard provides an overview of student minor interest trends, including the most and least selected minors, demographic breakdowns, and request activity. Use the visualizations and filters below to identify patterns and support students in making informed decisions about their academic paths.
                </p>
            </div>
            <div class="rvt-container-lg mt-6">
                <h3 class="rvt-text-bold rvt-ts-xl mb-2">Top Minors Selected by Gender</h3>
                <div class="d-flex flex-wrap justify-content-start gap-4">
                    <div style="flex: 1; min-width: 300px; max-width: 400px">
                        <canvas id="malePieChart"></canvas>
                    </div>
                    <div style="flex: 1; min-width: 300px; max-width: 400px">
                        <canvas id="femalePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-section">
            <h2 class="chart-title">Most & Least Chosen Minors by Department</h2>
            <form class="filter-form" method="GET">
                <label for="department">Filter by Department:</label>
                <select name="department" id="department" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= ($dept === $departmentFilter) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="chart-wrapper">
                <div class="chart-container">
                    <h3>Top 5 Most Chosen</h3>
                    <canvas id="topMinorsChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Bottom 5 Least Chosen</h3>
                    <canvas id="bottomMinorsChart"></canvas>
                </div>
            </div>
            <?php if (!empty($departmentFilter)): ?>
                <div style="margin-top: 60px; text-align: center;">
                    <h3>Gender Distribution for <?= htmlspecialchars($departmentFilter) ?> Department</h3>
                    <div style="display: inline-block; max-width: 400px; width: 100%;">
                        <canvas id="departmentGenderPieChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
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

    <!-- Rivet Footer -->
    <footer class="rvt-footer-base">
        <div class="rvt-container-lg">
            <div class="rvt-footer-base__inner">
                <div class="rvt-footer-base__logo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <polygon fill="currentColor" points="15.3 3.19 15.3 5 16.55 5 16.55 15.07 13.9 15.07 13.9 1.81 15.31 1.81 15.31 0 8.72 0 8.72 1.81 10.12 1.81 10.12 15.07 7.45 15.07 7.45 5 8.7 5 8.7 3.19 2.5 3.19 2.5 5 3.9 5 3.9 16.66 6.18 18.98 10.12 18.98 10.12 21.67 8.72 21.67 8.72 24 15.3 24 15.3 21.67 13.9 21.67 13.9 18.98 17.82 18.98 20.09 16.66 20.09 5 21.5 5 21.5 3.19 15.3 3.19" />
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
                        <a class="rvt-footer-base__link" href="#0">Copyright</a> © 2024 The Trustees of
                        <a class="rvt-footer-base__link" href="https://www.iu.edu">Indiana University</a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const topCtx = document.getElementById("topMinorsChart").getContext("2d");
            const bottomCtx = document.getElementById("bottomMinorsChart").getContext("2d");

            new Chart(topCtx, {
                type: "bar",
                data: {
                    labels: <?php echo json_encode($topLabels); ?>,
                    datasets: [{
                        label: "Saved Count",
                        data: <?php echo json_encode($topCounts); ?>,
                        backgroundColor: "#0055A5",
                        borderColor: "#333",
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `Saved: ${ctx.parsed.y}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Students',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                color: '#333'
                            },
                            ticks: {
                                color: '#333'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#333'
                            }
                        }
                    }
                }
            });

            new Chart(bottomCtx, {
                type: "bar",
                data: {
                    labels: <?php echo json_encode($bottomLabels); ?>,
                    datasets: [{
                        label: "Saved Count",
                        data: <?php echo json_encode($bottomCounts); ?>,
                        backgroundColor: "#990000",
                        borderColor: "#333",
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `Saved: ${ctx.parsed.y}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Students',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                color: '#333'
                            },
                            ticks: {
                                color: '#333'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#333'
                            }
                        }
                    }
                }
            });
            new Chart(document.getElementById("malePieChart"), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($maleMinorData)); ?>,
                    datasets: [{
                        label: 'Male Minor Interest',
                        data: <?php echo json_encode(array_values($maleMinorData)); ?>,
                        backgroundColor: ['#0055A5', '#990000', '#FDB515', '#3E8E41', '#FF6F61']
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Popular Minors (Male)'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            new Chart(document.getElementById("femalePieChart"), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($femaleMinorData)); ?>,
                    datasets: [{
                        label: 'Female Minor Interest',
                        data: <?php echo json_encode(array_values($femaleMinorData)); ?>,
                        backgroundColor: ['#0055A5', '#990000', '#FDB515', '#3E8E41', '#FF6F61']
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Popular Minors (Female)'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            <?php if (!empty($departmentFilter)): ?>
                new Chart(document.getElementById("departmentGenderPieChart"), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($genderPieLabels); ?>,
                        datasets: [{
                            label: 'Gender Distribution',
                            data: <?php echo json_encode($genderPieData); ?>,
                            backgroundColor: ['#0055A5', '#990000']
                        }]
                    },
                    options: {
                        aspectRatio: 1,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Gender Breakdown by Department'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>