<?php
// login.php
$page_title = "Login - JUCSU Election System";
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $university_id = sanitizeInput($_POST['university_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($university_id) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE university_id = ? AND is_active = 1");
        $stmt->execute([$university_id]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['university_id'] = $user['university_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['hall_name'] = $user['hall_name'];
            
            redirectToDashboard();
        } else {
            $error = 'Invalid credentials';
        }
    }
}

include 'includes/header.php';
?>

<style>
    .login-card {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        margin: 2rem auto;
    }
    .card-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-radius: 15px 15px 0 0;
        padding: 2rem;
    }
    .password-toggle {
        cursor: pointer;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        font-size: 1.2rem;
    }
    .password-container {
        position: relative;
    }
    .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    .btn-success {
        background: #28a745;
        border: none;
        transition: background 0.3s;
    }
    .btn-success:hover {
        background: #218838;
    }
</style>

<div class="container">
    <div class="login-card">
        <div class="card-header text-white text-center">
            <h4 class="mb-0">JUCSU Election Login</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="assertive">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="login-form" aria-label="Login Form">
                <div class="mb-3">
                    <label for="university_id" class="form-label">University ID</label>
                    <input type="text" class="form-control" id="university_id" name="university_id" required
                           aria-describedby="university_id_help" maxlength="20">
                    <div id="university_id_help" class="form-text">Enter your unique university ID (e.g., JU2022001).</div>
                </div>
                
                <div class="mb-3 password-container">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required
                           aria-describedby="password_help">
                    <i class="bi bi-eye-slash password-toggle" id="toggle-password" aria-label="Show password"></i>
                    <div id="password_help" class="form-text">Enter your password.</div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-success">Login</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
              
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('toggle-password').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = this;
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
        toggleIcon.setAttribute('aria-label', 'Hide password');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
        toggleIcon.setAttribute('aria-label', 'Show password');
    }
});
</script>

<?php include 'includes/footer.php'; ?>