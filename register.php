<?php
// register.php
$page_title = "Register - JUCSU Election System";
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $university_id = sanitizeInput($_POST['university_id'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $hall_name = sanitizeInput($_POST['hall_name'] ?? '');
    $enrollment_year = (int)($_POST['enrollment_year'] ?? 0);
    $gender = sanitizeInput($_POST['gender'] ?? '');
    
    if (empty($university_id) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE university_id = ? OR email = ?");
        $stmt->execute([$university_id, $email]);
        
        if ($stmt->fetch()) {
            $error = 'University ID or Email already registered';
        } else {
            $hashed_password = hashPassword($password);
            $stmt = $pdo->prepare("
                INSERT INTO users (university_id, email, password, full_name, department, 
                                 hall_name, enrollment_year, gender, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'voter')
            ");
            
            if ($stmt->execute([$university_id, $email, $hashed_password, $full_name, 
                              $department, $hall_name, $enrollment_year, $gender])) {
                $success = 'Registration successful! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$halls_stmt = $pdo->query("SELECT DISTINCT hall_name FROM halls ORDER BY hall_name");
$halls = $halls_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header text-center bg-success text-white">
                <h4>Student Registration</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-success">Go to Login</a>
                    </div>
                <?php else: ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">University ID *</label>
                            <input type="text" class="form-control" name="university_id" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Physics">Physics</option>
                                <option value="Chemistry">Chemistry</option>
                                <option value="Economics">Economics</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hall *</label>
                            <select class="form-select" name="hall_name" required>
                                <option value="">Select Hall</option>
                                <?php foreach ($halls as $hall): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hall_name']); ?>">
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Enrollment Year *</label>
                            <select class="form-select" name="enrollment_year" required>
                                <?php for ($year = 2024; $year >= 2020; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">Register</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>