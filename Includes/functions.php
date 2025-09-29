<?php
// includes/functions.php - Common Functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../connection.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user info
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Check user role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role;
}

// Redirect based on role
function redirectToDashboard() {
    $role = $_SESSION['role'] ?? '';
    switch($role) {
        case 'voter':
            header('Location: /JUCSU_Election_Management/voter/dashboard.php');
            break;
        case 'candidate':
            header('Location: /JUCSU_Election_Management/candidate/dashboard.php');
            break;
        case 'central_commissioner':
            header('Location: /JUCSU_Election_Management/central/dashboard.php');
            break;
        case 'hall_commissioner':
            header('Location: /JUCSU_Election_Management/hall/dashboard.php');
            break;
        default:
            header('Location: /JUCSU_Election_Management/login.php');
    }
    exit();
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>