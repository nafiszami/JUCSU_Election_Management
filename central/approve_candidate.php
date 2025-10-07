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
    $verify_stmt = $pdo->prepare("SELECT c.id FROM candidates c JOIN positions p ON c.position_id = p.id WHERE c.id = ? AND p.election_type = 'jucsu'");
    $verify_stmt->execute([$id]);
    if (!$verify_stmt->fetch()) {
        $_SESSION['flash_message'] = "Invalid candidate selection.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    }
    
    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE candidates SET status='approved', rejection_reason=NULL WHERE id=?");
        $stmt->execute([$id]);
        
        // Notify candidate
        $stmt = $pdo->prepare("SELECT user_id FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            'Nomination Approved',
            'Your nomination for JUCSU election has been approved.',
            'success'
        ]);
        
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
        
        // Notify candidate
        $stmt = $pdo->prepare("SELECT user_id FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            'Nomination Rejected',
            'Your nomination for JUCSU election has been rejected. Reason: ' . $reason,
            'error'
        ]);
        
        $_SESSION['flash_message'] = "Candidate rejected successfully.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    } else {
        $_SESSION['flash_message'] = "Invalid action or missing reason.";
        header("Location: dashboard.php?page=approve_candidate");
        exit;
    }
}

// Fetch pending JUCSU candidates
$stmt = $pdo->prepare("
    SELECT c.id, u.university_id, u.full_name, u.department, u.hall_name, p.position_name, c.manifesto, c.photo_path, c.nominated_at, c.proposer_id, c.seconder_id
    FROM candidates c
    JOIN users u ON c.user_id = u.id
    JOIN positions p ON c.position_id = p.id
    WHERE p.election_type = 'jucsu' AND c.status = 'pending'
    ORDER BY p.position_order, c.nominated_at DESC
");
$stmt->execute();
$pending_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2 class="text-info">Approve Candidates</h2>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pending_candidates)): ?>
        <div class="alert alert-info text-center mt-5">
            No pending candidates to approve.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow rounded">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>University ID</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Hall</th>
                        <th>Position</th>
                        <th>Manifesto</th>
                        <th>Photo</th>
                        <th>Nominated At</th>
                        <th>Proposer</th>
                        <th>Seconder</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_candidates as $c): ?>
                    <tr>
                        <td><?=htmlspecialchars($c['university_id'])?></td>
                        <td><strong><?=htmlspecialchars($c['full_name'])?></strong></td>
                        <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($c['department']))?></td>
                        <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars($c['hall_name']))?></td>
                        <td><?=htmlspecialchars($c['position_name'])?></td>
                        <td class="text-wrap" style="max-width:200px"><?=nl2br(htmlspecialchars(substr($c['manifesto'], 0, 100) . '...'))?></td>
                        <td>
                            <?php if ($c['photo_path']): ?>
                                <img src="../<?=htmlspecialchars($c['photo_path'])?>" alt="Candidate Photo" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                            <?php else: ?>
                                No Photo
                            <?php endif; ?>
                        </td>
                        <td><?=date('Y-m-d H:i', strtotime($c['nominated_at']))?></td>
                        <td><?=htmlspecialchars($c['proposer_id'])?></td>
                        <td><?=htmlspecialchars($c['seconder_id'])?></td>
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
                    <?php endforeach; ?>
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