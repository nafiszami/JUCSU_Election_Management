<?php
// includes/header.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';
$current_user = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'JUCSU Election System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/JUCSU_Election_Management/css/style.css" rel="stylesheet">

    <style>
        .navbar-brand .logo-img {
            height: 40px; /* Adjust height as needed */
            margin-right: 10px; /* Space between logo and text, if you add text */
            vertical-align: middle; /* Align nicely with any adjacent text */
        }
        /* If you keep text next to the logo, you might want to adjust font size or weight */
        .navbar-brand .logo-text {
            font-weight: bold;
            color: white; /* Or your brand color */
            
        }
        .navbar .navbar-brand {
    margin-left: -30px; /* move left by 10px */
}

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            
            <a class="navbar-brand" href="/JUCSU_Election_Management/">
                <img src="/JUCSU_Election_Management/assets/picture/ju_logo.png" 
                     alt="JUCSU Election Logo" 
                     class="logo-img">
                <span class="logo-text d-none d-sm-inline">Jahangirnagar University</span> 
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasRole(['hall_commissioner'])): ?>
                            <li class="nav-item"
                                <a class="nav-link" href="/JUCSU_Election_Management/hall/verify_voters.php">Verify Voters</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/JUCSU_Election_Management/hall/monitor.php">Monitor Voting</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/JUCSU_Election_Management/hall/reports.php">Reports</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($current_user['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/JUCSU_Election_Management/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/JUCSU_Election_Management/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/JUCSU_Election_Management/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container-fluid">