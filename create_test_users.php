<?php
// create_test_users.php - Run once to create test users
require_once 'connection.php';

// Delete old test users
$pdo->exec("DELETE FROM users WHERE university_id IN ('2022001', '2021045', '2020123', 'CENTRAL001', 'HALL001')");

// Create fresh password hash
$password = password_hash('password123', PASSWORD_DEFAULT);

// Insert test users
$sql = "INSERT INTO users (university_id, email, password, full_name, role, department, hall_name, enrollment_year, gender, is_verified, dues_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$users = [
    ['2022001', 'voter1@test.com', $password, 'Ahmed Hassan', 'voter', 'Computer Science', 'Al Beruni Hall', 2022, 'male', 1, 1],
    ['2021045', 'voter2@test.com', $password, 'Fatima Rahman', 'voter', 'Mathematics', 'Begum Rokeya Hall', 2021, 'female', 1, 1],
    ['2020123', 'candidate1@test.com', $password, 'Rahim Khan', 'voter', 'Physics', 'Maulana Bhashani Hall', 2020, 'male', 1, 1],
    ['CENTRAL001', 'central@test.com', $password, 'Dr. Karim Ahmed', 'central_commissioner', 'Administration', 'Administrative', 2015, 'male', 1, 1],
    ['HALL001', 'hall@test.com', $password, 'Prof. Nasrin Akter', 'hall_commissioner', 'Chemistry', 'Al Beruni Hall', 2016, 'female', 1, 1],
];

$stmt = $pdo->prepare($sql);

foreach ($users as $user) {
    $stmt->execute($user);
    echo "Created user: {$user[0]} - {$user[3]}<br>";
}

echo "<hr><h3>Test Users Created Successfully!</h3>";
echo "<p>Password for all users: <strong>password123</strong></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
?>