<?php
require_once '../connection.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// Flash message
$message = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}



// Authentication check
if (!isset($_SESSION['commission_authenticated'])) {
    header("Location: dashboard.php");
    exit;
}

// Cancel Nomination logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $id = intval($_POST['candidate_id']);
    $reason = trim($_POST['rejection_reason']);
    if ($reason) {
    $stmt = $pdo->prepare("UPDATE candidates SET status='rejected', rejection_reason=? WHERE id=?");
    $stmt->execute([$reason, $id]);
    $_SESSION['flash_message'] = "Nomination canceled successfully.";
    
}

}

// Fetch approved candidates
$approved_stmt = $pdo->query(
    "SELECT c.id, u.full_name, u.enrollment_year, u.department, u.hall_name, p.position_name
     FROM candidates c
     JOIN users u ON c.user_id = u.id
     JOIN positions p ON c.position_id = p.id
     WHERE c.status = 'approved'"
);
$approved_candidates = $approved_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cancel Nomination</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        td.text-wrap { white-space: normal; word-break: break-word; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-warning">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold mx-auto">Cancel Nomination</span>
        
    </div>
</nav>
<div class="container py-5">

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-warning">
                <tr>
                    <th>Name</th>
                    <th>Enrollment Year</th>
                    <th>Department</th>
                    <th>Hall</th>
                    <th>Position</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($approved_candidates as $a): ?>
                <tr>
                    <td><?=htmlspecialchars($a['full_name'])?></td>
                    <td><?=htmlspecialchars($a['enrollment_year'])?></td>
                    <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($a['department']))?></td>
                    <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($a['hall_name']))?></td>
                    <td><?=htmlspecialchars($a['position_name'])?></td>
                    <td>
                        <form method="post" onsubmit="return promptRejection(<?= $a['id'] ?>);">
                            <input type="hidden" name="candidate_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="rejection_reason" id="rejection_reason-<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Cancel Nomination</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function promptRejection(id) {
    let reason = prompt("Enter rejection reason for canceling nomination:");
    if (!reason) return false;
    document.getElementById('rejection_reason-'+id).value = reason;
    return true;
}
</script>
</body>
</html>
