<?php
// debug_login.php - TEMPORARY DEBUG FILE (delete after fixing)
require_once 'connection.php';

echo "<h3>Checking Test Users in Database:</h3>";

$stmt = $pdo->query("SELECT university_id, email, full_name, role, password FROM users LIMIT 5");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "<hr>";
    echo "University ID: " . $user['university_id'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Name: " . $user['full_name'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    
    // Test password verification
    $test_password = 'password123';
    $is_valid = password_verify($test_password, $user['password']);
    echo "Password 'password123' works: " . ($is_valid ? "YES ✓" : "NO ✗") . "<br>";
}

echo "<hr><h4>Test Manual Password Hash:</h4>";
echo "Hash for 'password123': " . password_hash('password123', PASSWORD_DEFAULT);
?>