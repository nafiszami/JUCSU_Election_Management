<?php
// Ensure $pdo is available from the included dashboard.php
if (!isset($pdo)) {
    echo '<div class="alert alert-danger">FATAL ERROR: Database connection missing. Cannot load candidate list.</div>';
    return;
}

// CSRF token is assumed to be handled in the main dashboard or this file is standalone. 
// We will keep the CSRF logic here for security.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash message (Check and unset)
$message = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Cancel Nomination logic for JUCSU candidates only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Prevent script execution on CSRF failure
        die('<div class="alert alert-danger">Invalid CSRF token. Please refresh the page.</div>');
    }
    
    $id = intval($_POST['candidate_id']);
    $reason = trim($_POST['rejection_reason']);
    
    // Verify it's a JUCSU candidate before processing
    $verify_stmt = $pdo->prepare("SELECT id FROM candidates c JOIN positions p ON c.position_id = p.id WHERE c.id = ? AND p.election_type = 'jucsu'");
    $verify_stmt->execute([$id]);
    if (!$verify_stmt->fetch()) {
        $_SESSION['flash_message'] = "Invalid candidate selection.";
        header("Location: dashboard.php?page=cancel_nomination");
        exit;
    }
    
    if ($reason && $id > 0) {
        // Use prepared statement to update status and reason
        $stmt = $pdo->prepare("UPDATE candidates SET status='rejected', rejection_reason=? WHERE id=?");
        $stmt->execute([$reason, $id]);
        
        // Use a session flash message for the main dashboard to display after redirect
        $_SESSION['flash_message'] = "Nomination for ID {$id} canceled successfully.";
        
        // Redirect back to the same page to show updated list and clear POST data
        header("Location: dashboard.php?page=cancel_nomination");
        exit;
    } else {
        $_SESSION['flash_message'] = "Rejection reason is required.";
        header("Location: dashboard.php?page=cancel_nomination");
        exit;
    }
}

// Fetch approved JUCSU candidates only
$approved_stmt = $pdo->prepare(
    "SELECT 
        c.id, u.full_name, u.enrollment_year, u.department, u.hall_name, p.position_name
     FROM candidates c
     JOIN users u ON c.user_id = u.id
     JOIN positions p ON c.position_id = p.id
     WHERE c.status = 'approved' AND p.election_type = 'jucsu'
     ORDER BY p.position_order, u.full_name"
);
$approved_stmt->execute();
$approved_candidates = $approved_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
<div class="mx-auto" style="max-width: 800px;">
    <h3 class="mb-4 bg-warning text-white text-center p-3">
        ❌ Cancel JUCSU Nomination
    </h3>
</div>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($approved_candidates)): ?>
        <div class="alert alert-success mt-4">
            No JUCSU candidates are currently marked as 'approved'.
        </div>
    <?php else: ?>
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
                        <td><strong><?=htmlspecialchars($a['full_name'])?></strong></td> 
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
    <?php endif; ?>
</div>

<script>
function promptRejection(id) {
    let reason = prompt("Enter rejection reason for canceling nomination (Required):");
    // If the user clicks cancel or leaves it blank, return false
    if (!reason || reason.trim() === "") {
        alert("Rejection reason cannot be empty.");
        return false;
    }
    // Set the value in the hidden input field
    document.getElementById('rejection_reason-'+id).value = reason.trim();
    return true; // Submit the form
}
</script>