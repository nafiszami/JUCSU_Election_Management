<?php
// central/approve_candidate.php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../connection.php';

// CSRF token setup
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

// Approve/Reject logic for pending JUCSU candidates only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $id = intval($_POST['candidate_id']);
    
    // Verify it's a JUCSU candidate before processing
    $verify_stmt = $pdo->prepare("SELECT id FROM candidates c JOIN positions p ON c.position_id = p.id WHERE c.id = ? AND p.election_type = 'jucsu'");
    $verify_stmt->execute([$id]);
    if (!$verify_stmt->fetch()) {
        $_SESSION['flash_message'] = "Invalid candidate selection.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    }
    
    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE candidates SET status='approved', rejection_reason=NULL WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['flash_message'] = "Candidate approved successfully.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    } elseif ($_POST['action'] === 'reject' && isset($_POST['rejection_reason'])) {
        $reason = trim($_POST['rejection_reason']);
        if (empty($reason)) {
            $_SESSION['flash_message'] = "Rejection reason is required.";
            header("Location: dashboard.php?page=approve_candidate");
            exit;
        }
        $stmt = $pdo->prepare("UPDATE candidates SET status='rejected', rejection_reason=? WHERE id=?");
        $stmt->execute([$reason, $id]);
        $_SESSION['flash_message'] = "Candidate rejected with reason.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    }
}

// Fetch pending JUCSU candidates only
$stmt = $pdo->prepare(
    "SELECT c.id, u.full_name, u.enrollment_year, u.department, u.hall_name, p.position_name
     FROM candidates c
     JOIN users u ON c.user_id = u.id
     JOIN positions p ON c.position_id = p.id
     WHERE c.status = 'pending' AND p.election_type = 'jucsu'
     ORDER BY p.position_order, u.full_name"
);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Approve JUCSU Candidates</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS.css">
    <style>
        .d-none { display: none; }
        td.text-wrap { white-space: normal; word-break: break-word; }
    </style>
</head>
<body>
<div class="container py-5">

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h2 class="mb-4">Approve JUCSU Candidates</h2>
    <?php if (empty($candidates)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-check-circle"></i> No pending JUCSU candidates to review.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-success">
                    <tr>
                        <th>Name</th>
                        <th>Enrollment Year</th>
                        <th>Department</th>
                        <th>Hall</th>
                        <th>Position</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($candidates as $c): ?>
                    <tr>
                        <td><?=htmlspecialchars($c['full_name'])?></td>
                        <td><?=htmlspecialchars($c['enrollment_year'])?></td>
                        <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($c['department']))?></td>
                        <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($c['hall_name']))?></td>
                        <td><?=htmlspecialchars($c['position_name'])?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <button type="button" id="reject-btn-<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="showRejectReason(<?= $c['id'] ?>)">Reject</button>
                            <form method="post" style="display:inline;" id="reject-reason-<?= $c['id'] ?>" class="d-none">
                                <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="text" name="rejection_reason" placeholder="Reason" required class="form-control form-control-sm d-inline-block" style="width:120px;">
                                <button type="submit" class="btn btn-danger btn-sm">Submit</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showRejectReason(id) {
    document.getElementById('reject-reason-'+id).classList.remove('d-none');
    document.getElementById('reject-btn-'+id).style.display = 'none';
}
</script>
</body>
</html>