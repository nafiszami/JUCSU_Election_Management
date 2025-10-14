<?php
// admin/create_users_dashboard.php
// SECURITY WARNING: Delete this file after creating test users!
require_once 'connection.php';

$success = '';
$error = '';
$created_users = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_single') {
        // Create single user
        $university_id = $_POST['university_id'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $role = $_POST['role'];
        $department = $_POST['department'];
        $hall_name = $_POST['hall_name'];
        $enrollment_year = $_POST['enrollment_year'];
        $gender = $_POST['gender'];
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (university_id, email, password, full_name, role, department, 
                                 hall_name, enrollment_year, gender, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$university_id, $email, $password, $full_name, $role, 
                          $department, $hall_name, $enrollment_year, $gender, $is_verified]);
            
            $new_user_id = $pdo->lastInsertId();
            
            // If hall commissioner, update halls table
            if ($role === 'hall_commissioner') {
                $update_stmt = $pdo->prepare("
                    UPDATE halls SET commissioner_id = ? WHERE hall_name = ?
                ");
                $update_stmt->execute([$new_user_id, $hall_name]);
            }
            
            $success = "User created successfully: $university_id";
            $created_users[] = [
                'id' => $university_id,
                'name' => $full_name,
                'password' => $_POST['password'],
                'role' => $role
            ];
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'create_single_central') {
        // Create single central commissioner
        $university_id = $_POST['university_id'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $department = $_POST['department'];
        $hall_name = $_POST['hall_name'];
        $enrollment_year = $_POST['enrollment_year'];
        $gender = $_POST['gender'];
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        $role = 'central_commissioner';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (university_id, email, password, full_name, role, department, 
                                 hall_name, enrollment_year, gender, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$university_id, $email, $password, $full_name, $role, 
                          $department, $hall_name, $enrollment_year, $gender, $is_verified]);
            
            $success = "Central Commissioner created successfully: $university_id";
            $created_users[] = [
                'id' => $university_id,
                'name' => $full_name,
                'password' => $_POST['password'],
                'role' => $role
            ];
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'create_single_hall') {
        // Create single hall commissioner
        $university_id = $_POST['university_id'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $department = $_POST['department'];
        $hall_name = $_POST['hall_name'];
        $enrollment_year = $_POST['enrollment_year'];
        $gender = $_POST['gender'];
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        $role = 'hall_commissioner';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (university_id, email, password, full_name, role, department, 
                                 hall_name, enrollment_year, gender, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$university_id, $email, $password, $full_name, $role, 
                          $department, $hall_name, $enrollment_year, $gender, $is_verified]);
            
            $new_user_id = $pdo->lastInsertId();
            
            // Update halls table
            $update_stmt = $pdo->prepare("
                UPDATE halls SET commissioner_id = ? WHERE hall_name = ?
            ");
            $update_stmt->execute([$new_user_id, $hall_name]);
            
            $success = "Hall Commissioner created successfully: $university_id for $hall_name";
            $created_users[] = [
                'id' => $university_id,
                'name' => $full_name,
                'password' => $_POST['password'],
                'role' => $role,
                'hall' => $hall_name
            ];
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'bulk_voters') {
        // Create bulk voters
        $count = (int)$_POST['voter_count'];
        $hall = $_POST['bulk_hall'];
        $base_password = $_POST['bulk_password'];
        
        try {
            $departments = ['Department of Computer Science and Engineering', 'Department of Economics', 
                          'Department of English', 'Department of Mathematics', 'Department of Physics'];
            $years = [2020, 2021, 2022, 2023];
            $genders = ['male', 'female'];
            
            for ($i = 1; $i <= $count; $i++) {
                $university_id = 'TEST' . date('Y') . str_pad($i, 4, '0', STR_PAD_LEFT);
                $email = "voter{$i}@test.ju.ac.bd";
                $password = password_hash($base_password, PASSWORD_DEFAULT);
                $full_name = "Test Voter {$i}";
                $department = $departments[array_rand($departments)];
                $year = $years[array_rand($years)];
                $gender = $genders[array_rand($genders)];
                
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO users (university_id, email, password, full_name, role, department, 
                                     hall_name, enrollment_year, gender, is_verified, is_active)
                    VALUES (?, ?, ?, ?, 'voter', ?, ?, ?, ?, 1, 1)
                ");
                
                $stmt->execute([$university_id, $email, $password, $full_name, 
                              $department, $hall, $year, $gender]);
                
                $created_users[] = [
                    'id' => $university_id,
                    'name' => $full_name,
                    'password' => $base_password,
                    'role' => 'voter'
                ];
            }
            
            $success = "Created $count voters successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'create_commissioners') {
        // Create commissioners for all halls
        $password = password_hash($_POST['commissioner_password'], PASSWORD_DEFAULT);
        
        try {
            // Create central commissioner
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO users (university_id, email, password, full_name, role, department, 
                                 hall_name, enrollment_year, gender, is_verified, is_active)
                VALUES ('CENTRAL001', 'central@ju.ac.bd', ?, 'Dr. Central Commissioner', 
                       'central_commissioner', 'Department of Computer Science and Engineering', 
                       'Al Beruni Hall', 2020, 'male', 1, 1)
            ");
            $stmt->execute([$password]);
            $created_users[] = [
                'id' => 'CENTRAL001',
                'name' => 'Dr. Central Commissioner',
                'password' => $_POST['commissioner_password'],
                'role' => 'central_commissioner'
            ];
            
            // Get all halls
            $halls_stmt = $pdo->query("SELECT hall_name, hall_type FROM halls WHERE is_active = 1");
            $halls = $halls_stmt->fetchAll();
            
            $counter = 1;
            foreach ($halls as $hall) {
                $hall_id = 'HALL' . str_pad($counter, 3, '0', STR_PAD_LEFT);
                $email = strtolower(str_replace(' ', '', $hall['hall_name'])) . '@ju.ac.bd';
                $name = "Prof. Commissioner " . $hall['hall_name'];
                
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO users (university_id, email, password, full_name, role, department, 
                                     hall_name, enrollment_year, gender, is_verified, is_active)
                    VALUES (?, ?, ?, ?, 'hall_commissioner', 'Department of Economics', 
                           ?, 2018, ?, 1, 1)
                ");
                
                $gender = $hall['hall_type'] === 'female' ? 'female' : 'male';
                $stmt->execute([$hall_id, $email, $password, $name, $hall['hall_name'], $gender]);
                
                $new_user_id = $pdo->lastInsertId();
                if ($new_user_id) {
                    // Update halls table
                    $update_stmt = $pdo->prepare("
                        UPDATE halls SET commissioner_id = ? WHERE hall_name = ?
                    ");
                    $update_stmt->execute([$new_user_id, $hall['hall_name']]);
                }
                
                $created_users[] = [
                    'id' => $hall_id,
                    'name' => $name,
                    'password' => $_POST['commissioner_password'],
                    'role' => 'hall_commissioner',
                    'hall' => $hall['hall_name']
                ];
                
                $counter++;
            }
            
            $success = "Created central commissioner and " . count($halls) . " hall commissioners!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get halls and departments for dropdowns
$halls_stmt = $pdo->query("SELECT DISTINCT hall_name FROM halls ORDER BY hall_name");
$halls = $halls_stmt->fetchAll();

$dept_stmt = $pdo->query("SELECT DISTINCT department_name FROM departments ORDER BY department_name");
$departments = $dept_stmt->fetchAll();

// Get existing users count
$stats_stmt = $pdo->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    GROUP BY role
");
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test User Creation Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .card { margin-bottom: 1.5rem; border-radius: 10px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-people-fill"></i> Test User Creation Dashboard</h2>
                <div class="warning-box">
                    <strong><i class="bi bi-exclamation-triangle-fill"></i> SECURITY WARNING:</strong> 
                    Delete this file (admin/create_users_dashboard.php) after creating all test users!
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (count($created_users) > 0): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Created Users - Save These Credentials!</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>University ID</th>
                                    <th>Name</th>
                                    <th>Password</th>
                                    <th>Role</th>
                                    <th>Hall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($created_users as $user): ?>
                                <tr>
                                    <td><strong><?php echo $user['id']; ?></strong></td>
                                    <td><?php echo $user['name']; ?></td>
                                    <td><code><?php echo $user['password']; ?></code></td>
                                    <td><span class="badge bg-primary"><?php echo $user['role']; ?></span></td>
                                    <td><?php echo $user['hall'] ?? '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['voter'] ?? 0; ?></h3>
                        <p class="mb-0">Voters</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['central_commissioner'] ?? 0; ?></h3>
                        <p class="mb-0">Central Commissioners</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['hall_commissioner'] ?? 0; ?></h3>
                        <p class="mb-0">Hall Commissioners</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3><?php echo array_sum($stats); ?></h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <!-- Create All Commissioners -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-shield-check"></i> Create All Commissioners</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Creates 1 Central + 21 Hall Commissioners with same password</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_commissioners">
                            <div class="mb-3">
                                <label class="form-label">Master Password for All Commissioners</label>
                                <input type="password" class="form-control" name="commissioner_password" 
                                       value="commissioner123" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Create All Commissioners
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bulk Create Voters -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Bulk Create Voters</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Create multiple voters for testing</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="bulk_voters">
                            <div class="mb-3">
                                <label class="form-label">Number of Voters</label>
                                <input type="number" class="form-control" name="voter_count" 
                                       value="10" min="1" max="100" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hall</label>
                                <select class="form-select" name="bulk_hall" required>
                                    <?php foreach ($halls as $hall): ?>
                                        <option value="<?php echo $hall['hall_name']; ?>">
                                            <?php echo $hall['hall_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password for All</label>
                                <input type="password" class="form-control" name="bulk_password" 
                                       value="voter123" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i> Create Bulk Voters
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Single Central Commissioner -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Create Single Central Commissioner</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_single_central">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">University ID</label>
                                    <input type="text" class="form-control" name="university_id" 
                                           placeholder="e.g., CENTRAL001" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="central@ju.ac.bd" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           placeholder="Dr. Central Commissioner" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" 
                                           value="password123" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" required>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_name']; ?>">
                                                <?php echo $dept['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hall (Optional)</label>
                                    <select class="form-select" name="hall_name">
                                        <option value="">None</option>
                                        <?php foreach ($halls as $hall): ?>
                                            <option value="<?php echo $hall['hall_name']; ?>">
                                                <?php echo $hall['hall_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Enrollment Year</label>
                                    <select class="form-select" name="enrollment_year" required>
                                        <?php for ($year = 2024; $year >= 2018; $year--): ?>
                                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_verified" 
                                           id="is_verified_central" checked>
                                    <label class="form-check-label" for="is_verified_central">Verified</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info w-100">
                                <i class="bi bi-person-plus"></i> Create Central Commissioner
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Create Single Hall Commissioner -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Create Single Hall Commissioner</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_single_hall">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">University ID</label>
                                    <input type="text" class="form-control" name="university_id" 
                                           placeholder="e.g., HALL001" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="hall@ju.ac.bd" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           placeholder="Prof. Hall Commissioner" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" 
                                           value="password123" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" required>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_name']; ?>">
                                                <?php echo $dept['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hall</label>
                                    <select class="form-select" name="hall_name" required>
                                        <?php foreach ($halls as $hall): ?>
                                            <option value="<?php echo $hall['hall_name']; ?>">
                                                <?php echo $hall['hall_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Enrollment Year</label>
                                    <select class="form-select" name="enrollment_year" required>
                                        <?php for ($year = 2024; $year >= 2018; $year--): ?>
                                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_verified" 
                                           id="is_verified_hall" checked>
                                    <label class="form-check-label" for="is_verified_hall">Verified</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="bi bi-person-plus"></i> Create Hall Commissioner
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Single User (General, including Voters) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Create Single User (Voter or Other)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_single">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">University ID</label>
                                    <input type="text" class="form-control" name="university_id" 
                                           placeholder="e.g., 2022001" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="user@example.com" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           placeholder="Full Name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" 
                                           value="password123" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" required>
                                        <option value="voter">Voter</option>
                                        <option value="central_commissioner">Central Commissioner</option>
                                        <option value="hall_commissioner">Hall Commissioner</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" required>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_name']; ?>">
                                                <?php echo $dept['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Hall</label>
                                    <select class="form-select" name="hall_name" required>
                                        <?php foreach ($halls as $hall): ?>
                                            <option value="<?php echo $hall['hall_name']; ?>">
                                                <?php echo $hall['hall_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Enrollment Year</label>
                                    <select class="form-select" name="enrollment_year" required>
                                        <?php for ($year = 2024; $year >= 2018; $year--): ?>
                                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_verified" 
                                               id="is_verified" checked>
                                        <label class="form-check-label" for="is_verified">Verified</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-person-plus"></i> Create User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Quick Links</h5>
                        <a href="../index.php" class="btn btn-primary me-2">Go to Homepage</a>
                        <a href="../login.php" class="btn btn-success me-2">Test Login</a>
                        <a href="http://localhost/phpmyadmin" class="btn btn-info me-2" target="_blank">phpMyAdmin</a>
                        <a href="#" class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete this file?')) { window.location='delete_self.php'; }">
                            Delete This Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>