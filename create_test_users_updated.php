<?php
// =====================================================
// register_commissioner.php
// Single file for managing Hall & Central Election Commissioner Registration.
// NOTE: Requires 'connection.php' to define and initialize the $pdo database object.
// =====================================================

require_once 'connection.php'; // Ensure this file provides a PDO connection in $pdo

$message = '';
$message_type = '';

// --- 1. Fetch Halls and Departments for Dropdowns ---
$halls = [];
$departments = [];
try {
    $halls = $pdo->query("SELECT hall_name FROM halls ORDER BY hall_name")->fetchAll(PDO::FETCH_COLUMN);
    $departments = $pdo->query("SELECT department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Basic error handling for database access
    $message = 'Could not load Hall and Department lists. Database connection failed or tables missing.';
    $message_type = 'error';
}

// --- 2. Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $message_type !== 'error') {
    // Data Collection & Basic Cleaning
    $university_id = trim($_POST['university_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $hall_name = trim($_POST['hall_name'] ?? '');
    $gender = $_POST['gender'] ?? '';

    // Set assigned_hall based on role
    $assigned_hall = NULL;
    if ($role === 'hall_commissioner') {
        $assigned_hall = $_POST['assigned_hall'] ?? NULL;
    }

    // Validation
    if (empty($university_id) || empty($email) || empty($password) || empty($full_name) || empty($role) || empty($department) || empty($hall_name) || empty($gender)) {
        $message = 'All required fields must be filled out.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $message_type = 'error';
    } elseif ($role === 'hall_commissioner' && empty($assigned_hall)) {
        $message = 'Hall Commissioner must be assigned a hall to manage.';
        $message_type = 'error';
    } else {
        try {
            // Hash Password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Set safe defaults for a new commissioner (usually verified and dues paid)
            $enrollment_year = date('Y'); // Placeholder enrollment year
            $dues_paid = 1; 
            $is_verified = 1;
            
            // Prepare SQL Statement (Matches all columns in your schema)
            $sql = "INSERT INTO users (university_id, email, password, full_name, role, enrollment_year, department, hall_name, gender, assigned_hall, dues_paid, is_verified) 
                    VALUES (:university_id, :email, :password_hash, :full_name, :role, :enrollment_year, :department, :hall_name, :gender, :assigned_hall, :dues_paid, :is_verified)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':university_id' => $university_id,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':full_name' => $full_name,
                ':role' => $role,
                ':enrollment_year' => $enrollment_year,
                ':department' => $department,
                ':hall_name' => $hall_name,
                ':gender' => $gender,
                ':assigned_hall' => $assigned_hall,
                ':dues_paid' => $dues_paid,
                ':is_verified' => $is_verified
            ]);

            $message = "Successfully registered **{$full_name}** as a **{$role}**.";
            $message_type = 'success';
            // Clear POST data on success to prevent re-submission
            $_POST = []; 

        } catch (PDOException $e) {
            // Check for unique constraint violation (e.g., duplicate ID or email)
            if ($e->getCode() == 23000) { 
                $message = 'Registration failed: University ID or Email already exists.';
            } else {
                $message = 'Database error during insertion: ' . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissioner Registration Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #e9ecef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #007bff; margin-bottom: 20px; }
        p.subtitle { text-align: center; color: #6c757d; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #495057; }
        input[type="text"], input[type="email"], input[type="password"], select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; transition: border-color 0.15s ease-in-out; }
        input:focus, select:focus { border-color: #007bff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; line-height: 1.5; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .hidden-field { display: none; }
        button { background-color: #007bff; color: white; padding: 12px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 18px; font-weight: bold; margin-top: 10px; transition: background-color 0.2s; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>JUCSU/Hall Commissioner Registration</h2>
    <p class="subtitle">Use this interface to register new Central or Hall Election Commissioners.</p>

    <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <?php echo str_replace('**', '<strong>', $message); // Simple markdown replacement ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="role">Commission Type</label>
            <select id="role" name="role" required onchange="toggleAssignedHall(this.value)">
                <option value="">Select Role</option>
                <option value="central_commissioner" <?php echo (isset($_POST['role']) && $_POST['role'] == 'central_commissioner') ? 'selected' : ''; ?>>Central Commissioner</option>
                <option value="hall_commissioner" <?php echo (isset($_POST['role']) && $_POST['role'] == 'hall_commissioner') ? 'selected' : ''; ?>>Hall Commissioner</option>
            </select>
        </div>

        <div class="form-group">
            <label for="full_name">Full Name (e.g., Dr. Abdul Karim)</label>
            <input type="text" id="full_name" name="full_name" required value="<?php echo $_POST['full_name'] ?? ''; ?>">
        </div>
        <div class="form-group">
            <label for="university_id">University ID (Must be Unique)</label>
            <input type="text" id="university_id" name="university_id" required value="<?php echo $_POST['university_id'] ?? ''; ?>">
        </div>
        <div class="form-group">
            <label for="email">Email (Must be Unique)</label>
            <input type="email" id="email" name="email" required value="<?php echo $_POST['email'] ?? ''; ?>">
        </div>
        <div class="form-group">
            <label for="password">Temporary Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="department">Department</label>
            <select id="department" name="department" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (isset($_POST['department']) && $_POST['department'] == $dept) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="hall_name">Residential Hall</label>
            <select id="hall_name" name="hall_name" required>
                <option value="">Select Residential Hall</option>
                <?php foreach ($halls as $hall): ?>
                    <option value="<?php echo htmlspecialchars($hall); ?>" <?php echo (isset($_POST['hall_name']) && $_POST['hall_name'] == $hall) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($hall); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="">Select Gender</option>
                <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>

        <div class="form-group hidden-field" id="assigned_hall_group">
            <label for="assigned_hall">Hall to be Managed (REQUIRED for Hall Commissioner)</label>
            <select id="assigned_hall" name="assigned_hall">
                <option value="">Select Hall to Manage</option>
                <?php foreach ($halls as $hall): ?>
                    <option value="<?php echo htmlspecialchars($hall); ?>" <?php echo (isset($_POST['assigned_hall']) && $_POST['assigned_hall'] == $hall) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($hall); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit">Register Commissioner</button>
    </form>
</div>

<script>
    /**
     * Toggles the visibility and required attribute of the 'Assigned Hall' field
     * based on the selected commissioner role.
     */
    function toggleAssignedHall(role) {
        const group = document.getElementById('assigned_hall_group');
        const select = document.getElementById('assigned_hall');
        
        if (role === 'hall_commissioner') {
            group.classList.remove('hidden-field');
            select.setAttribute('required', 'required');
        } else {
            group.classList.add('hidden-field');
            select.removeAttribute('required');
            // Important: Clear the value for central commissioners so it submits as NULL
            select.value = ""; 
        }
    }

    // Initialize the state when the page loads, useful if there was a submission error
    document.addEventListener('DOMContentLoaded', function() {
        const initialRole = document.getElementById('role').value;
        toggleAssignedHall(initialRole);
    });
</script>

</body>
</html>