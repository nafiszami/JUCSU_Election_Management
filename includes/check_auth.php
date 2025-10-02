<?php
// includes/check_auth.php - Authentication Check
require_once __DIR__ . '/functions.php';

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /JUCSU_Election_Management/login.php');
        exit();
    }
}

function requireRole($requiredRole) {
    requireLogin();
    if (!hasRole($requiredRole)) {
        header('Location: /JUCSU_Election_Management/unauthorized.php');
        exit();
    }
}
?>