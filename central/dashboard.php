<?php
// Start session only if not already started (best practice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================================================
//  1. INCLUDE DATABASE CONNECTION (CRITICAL FIX)
// =======================================================
// Define path relative to the document root (adjust path as needed for your setup)
$connection_path = $_SERVER['DOCUMENT_ROOT'] . '/JUCSU_Election_Management/connection.php';

if (file_exists($connection_path)) {
    // This file MUST successfully define the $pdo variable
    require_once $connection_path; 
} else {
    // Fail gracefully if the connection file is missing
    die("<div class='alert alert-danger'>FATAL ERROR: Database connection file not found. Check path: " . htmlspecialchars($connection_path) . "</div>");
}
// $pdo is now available globally to this file and all included files (like schedule.php)
// =======================================================

// 2. Page Parameter Handling & Secure File Mapping
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$allowed_pages = ['home', 'schedule', 'approve_candidate', 'cancel_nomination', 'result', 'complaints', 'audit'];

if (!in_array($page, $allowed_pages)) {
    $page = 'home'; // Default to 'home' if invalid page is passed
}

// Secure mapping of public page name (URL parameter) to internal filename
$file_map = [
    'schedule'          => 'schedule.php',           // Corresponds to central/schedule.php task
    'approve_candidate' => 'approve_candidate.php',     // Corresponds to central/scrutiny_jucsu.php task
    'result'            => 'results.php',            // Corresponds to central/results.php task
    'complaints'        => 'complaints.php',         // Placeholder file
    'cancel_nomination' => 'cancel_nomination.php',  // Placeholder file
    'audit'             => 'audit.php',              // Placeholder file
    'home'              => null                      // Handled by the 'else' block (Welcome message)
];

$file_to_include = $file_map[$page] ?? null; 
?>
<!DOCTYPE html>
<html>
<head>
    <title>Election Commission</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <div class="header">
        <div style="flex:1;text-align:center;">Election Commission</div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php?page=schedule" <?= $page === 'schedule' ? 'aria-current="page"' : '' ?>>📅 Schedule Election</a>
        <a href="dashboard.php?page=approve_candidate" <?= $page === 'approve_candidate' ? 'aria-current="page"' : '' ?>>✅ Approve Candidate</a>
        <a href="dashboard.php?page=cancel_nomination" <?= $page === 'cancel_nomination' ? 'aria-current="page"' : '' ?>>❌ Cancel Nomination</a>
        <a href="dashboard.php?page=result" <?= $page === 'result' ? 'aria-current="page"' : '' ?>>📊 See Result</a>
        <a href="dashboard.php?page=complaints" <?= $page === 'complaints' ? 'aria-current="page"' : '' ?>>🗣️ Complaints</a>
        <a href="dashboard.php?page=audit" <?= $page === 'audit' ? 'aria-current="page"' : '' ?>>🔎 Audit Logs</a>
    </div>

    <div class="content">
        <?php
        if ($file_to_include && file_exists($file_to_include)) {
            // Include the file based on the secure map
            include $file_to_include;
        } else {
            // Default Welcome Page
            echo '
            <div class="welcome-box mt-5 mx-auto p-4 alert alert-success text-center">
                <p class="mb-0 fs-4 fw-bold"> Welcome, Commissioner. Please select an option from the sidebar to begin.</p>
            </div>
            ';
        }
        ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
