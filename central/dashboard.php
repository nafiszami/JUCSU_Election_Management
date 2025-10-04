<?php
// central/dashboard.php
require_once '../includes/check_auth.php';
requireRole('central_commissioner');

$current_user = getCurrentUser();

// Page Parameter Handling & Secure File Mapping
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$allowed_pages = ['home', 'schedule', 'approve_candidate', 'cancel_nomination', 'result', 'complaints', 'audit'];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

$file_map = [
    'schedule'          => 'schedule.php',           
    'approve_candidate' => 'approve_candidate.php',     
    'result'            => 'result.php',
    'complaints'        => 'complaints.php',
    'cancel_nomination' => 'cancel_nomination.php',
    'audit'             => 'audit.php',
    'home'              => null
];

$file_to_include = $file_map[$page] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>CENTRAL ELECTION COMMISSIONER</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Override default styles to match your design */
        body {
            margin: 0;
            padding: 0;
        }
        
        /* Custom header that matches sidebar height */
        .custom-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .custom-header h3 {
            margin: 0;
            flex: 1;
            text-align: center;
            font-weight: bold;
        }
        
        .custom-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .custom-header a {
            color: white;
            text-decoration: none;
        }
        
        /* Adjust sidebar to start below header */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            width: 250px;
            overflow-y: auto;
        }
        
        /* Adjust content area */
        .content {
            margin-left: 250px;
            margin-top: 80px;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                top: 80px;
            }
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Custom Fixed Header -->
    <div class="custom-header">
        <div></div>
        <h3>CENTRAL ELECTION COMMISSIONER</h3>
        <div class="user-info">
            <span>
                <i class="bi bi-person-circle"></i> 
                <?php echo htmlspecialchars($current_user['full_name']); ?>
            </span>
            <a href="../logout.php" title="Logout">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="dashboard.php?page=schedule" <?= $page === 'schedule' ? 'aria-current="page"' : '' ?>>
            📅 Schedule Election
        </a>
        <a href="dashboard.php?page=approve_candidate" <?= $page === 'approve_candidate' ? 'aria-current="page"' : '' ?>>
            ✅ Approve Candidate
        </a>
        <a href="dashboard.php?page=cancel_nomination" <?= $page === 'cancel_nomination' ? 'aria-current="page"' : '' ?>>
            ❌ Cancel Nomination
        </a>
        <a href="dashboard.php?page=result" <?= $page === 'result' ? 'aria-current="page"' : '' ?>>
            📊 Live Result
        </a>
        <a href="dashboard.php?page=complaints" <?= $page === 'complaints' ? 'aria-current="page"' : '' ?>>
            🗣️ Complaints
        </a>
        <a href="dashboard.php?page=audit" <?= $page === 'audit' ? 'aria-current="page"' : '' ?>>
            🔎 Audit Logs
        </a>
    </div>

    <!-- Content Area -->
    <div class="content">
        <?php
        if ($file_to_include && file_exists($file_to_include)) {
            include $file_to_include;
        } else {
            echo '
            <div class="welcome-box mt-5 mx-auto p-4 alert alert-success text-center">
                <p class="mb-0 fs-4 fw-bold">Welcome, ' . htmlspecialchars($current_user['full_name']) . '. Please select an option from the sidebar to begin.</p>
            </div>
            ';
        }
        ?>
    </div>
     <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>