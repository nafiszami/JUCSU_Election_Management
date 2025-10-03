<?php
// Election_Commission/dashboard.php
require_once '../connection.php';
session_start();

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: dashboard.php");
    exit;
}

// Simple password check (replace with better auth in production)
if (!isset($_SESSION['commission_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && $_POST['password'] === '1234') {
        $_SESSION['commission_authenticated'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Election Commission Login</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        </head>
        <body class="bg-light d-flex align-items-center" style="height:100vh;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body">
                            <h3 class="mb-4 text-center text-success fw-bold">Election Commission</h3>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button class="btn btn-success w-100">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php exit;
    }
}

// Approve/Reject logic with CSRF check and feedback message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $id = intval($_POST['candidate_id']);
    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE candidates SET status='approved', rejection_reason=NULL WHERE id=?");
        $stmt->execute([$id]);
        $message = "Candidate approved successfully.";
    } elseif ($_POST['action'] === 'reject') {
        $reason = trim($_POST['rejection_reason']);
        $stmt = $pdo->prepare("UPDATE candidates SET status='rejected', rejection_reason=? WHERE id=?");
        $stmt->execute([$reason, $id]);
        $message = "Candidate rejected with reason.";
    }
}

// Fetch pending candidates with user and position details
$stmt = $pdo->query(
    "SELECT c.id, u.full_name, u.email, p.position_name
     FROM candidates c
     JOIN users u ON c.user_id = u.id
     JOIN positions p ON c.position_id = p.id
     WHERE c.status = 'pending'"
);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Election Commission Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold">Election Commission</span>
        <div class="d-flex">
            <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="container py-5">
    <h2 class="mb-4">Approve Candidates</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-success">
                <tr>
                    <th>Name</th><th>Email</th><th>Position</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $c): ?>
                <tr>
                    <td><?=htmlspecialchars($c['full_name'])?></td>
                    <td><?=htmlspecialchars($c['email'])?></td>
                    <td><?=htmlspecialchars($c['position_name'])?></td>
                    <td>
                        <!-- Approve Form -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <button type="submit" class="btn btn-success btn-sm" aria-label="Approve candidate">Approve</button>
                        </form>
                        <!-- Reject Form (with reason) -->
                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to reject this candidate?');">
                            <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="text" name="rejection_reason" placeholder="Reason" required class="form-control form-control-sm d-inline-block" style="width:120px;">
                            <button type="submit" class="btn btn-danger btn-sm" aria-label="Reject candidate">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>