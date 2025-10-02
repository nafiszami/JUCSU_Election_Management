<?php
session_start();
if (!isset($_SESSION['commission_authenticated'])) {
    header("Location: login.php");
    exit;
}

//  middle side 
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$allowed_pages = ['home','schedule','approve_candidate','results','complaints','cancel_nomination','audit'];
if (!in_array($page, $allowed_pages)) $page = 'home';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Election Commission</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Header  side -->
    <div class="header">
        <div style="flex:1;text-align:center;">Election Commission</div>
        
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="dashboard.php?page=schedule" <?= $page === 'schedule' ? 'aria-current="page"' : '' ?>>Schedule Election</a>
        <a href="dashboard.php?page=approve_candidate" <?= $page === 'approve_candidate' ? 'aria-current="page"' : '' ?>>Approve Candidate</a>
         <a href="dashboard.php?page=cancel_nomination" <?= $page === 'cancel_nomination' ? 'aria-current="page"' : '' ?>>Cancel Nomination</a>
        <a href="dashboard.php?page=result" <?= $page === 'result' ? 'aria-current="page"' : '' ?>>See Result</a>
        <a href="dashboard.php?page=complaints" <?= $page === 'complaints' ? 'aria-current="page"' : '' ?>>Complaints</a>
        <a href="dashboard.php?page=audit" <?= $page === 'audit' ? 'aria-current="page"' : '' ?>>Audit Logs</a>
    </div>

    <!-- Middle Content -->
    <div class="content">
        <?php
        if (file_exists("$page.php")) include "$page.php";
        else {
            // Updated default message with specific class for styling
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
