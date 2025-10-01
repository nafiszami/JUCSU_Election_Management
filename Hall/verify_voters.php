<?php
// hall/verify_voters.php
$page_title = "Hall Voter Verification";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

// Fetch pending voters
$stmt = $pdo->prepare("
    SELECT id, university_id, full_name, department, enrollment_year, gender, dues_paid, phone, created_at
    FROM users 
    WHERE hall_name = ? AND role = 'voter' AND is_verified = 0
    ORDER BY created_at DESC
");
$stmt->execute([$hall_name]);
$pending_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle bulk approval
if (isset($_POST['bulk_approve'])) {
    $voter_ids = $_POST['voter_ids'] ?? [];
    if (!empty($voter_ids)) {
        $placeholders = implode(',', array_fill(0, count($voter_ids), '?'));
        $update_stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id IN ($placeholders)");
        $update_stmt->execute($voter_ids);
        
        // Log action
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'VERIFY_VOTERS', 'users', ?, ?)");
        $log_stmt->execute([$current_user['id'], implode(',', $voter_ids), json_encode(['verified' => true])]);
        
        header('Location: verify_voters.php?success=bulk_approved');
        exit;
    }
}

// Handle single approval/rejection
if (isset($_POST['action']) && isset($_POST['voter_id'])) {
    $voter_id = $_POST['voter_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $update_stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND hall_name = ?");
        $update_stmt->execute([$voter_id, $hall_name]);
        
        // Log
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'APPROVE_VOTER', 'users', ?, ?)");
        $log_stmt->execute([$current_user['id'], $voter_id, json_encode(['verified' => true])]);
        
        header('Location: verify_voters.php?success=approved');
        exit;
    } elseif ($action === 'reject') {
        $reason = $_POST['rejection_reason'] ?? '';
        // Here you could add a rejection reason field if needed, but for simplicity, just set verified to false or delete
        // For now, we'll just keep it pending but log rejection
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'REJECT_VOTER', 'users', ?, ?)");
        $log_stmt->execute([$current_user['id'], $voter_id, json_encode(['reason' => $reason])]);
        
        header('Location: verify_voters.php?success=rejected');
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Voter Verification - <?php echo htmlspecialchars($hall_name); ?></h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php 
            switch($_GET['success']) {
                case 'bulk_approved': echo 'Selected voters approved successfully!'; break;
                case 'approved': echo 'Voter approved!'; break;
                case 'rejected': echo 'Voter rejected!'; break;
            }
            ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="bulkForm">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pending Voters (<?php echo count($pending_voters); ?>)</h5>
                <button type="submit" name="bulk_approve" class="btn btn-success btn-sm" id="bulkApproveBtn" disabled>
                    <i class="bi bi-check-all"></i> Approve Selected
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pending_voters)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1"></i>
                        <p>No pending verifications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>University ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Year</th>
                                    <th>Gender</th>
                                    <th>Dues</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_voters as $voter): ?>
                                <tr>
                                    <td><input type="checkbox" name="voter_ids[]" value="<?php echo $voter['id']; ?>" class="selectVoter"></td>
                                    <td><?php echo htmlspecialchars($voter['university_id']); ?></td>
                                    <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($voter['department']); ?></td>
                                    <td><?php echo $voter['enrollment_year']; ?></td>
                                    <td><?php echo ucfirst($voter['gender']); ?></td>
                                    <td>
                                        <?php if ($voter['dues_paid']): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="voter_id" value="<?php echo $voter['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i> Approve
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-voter-id="<?php echo $voter['id']; ?>">
                                            <i class="bi bi-x"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Voter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="voter_id" id="rejectVoterId">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function(e) {
    document.querySelectorAll('.selectVoter').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
    toggleBulkButton();
});

document.querySelectorAll('.selectVoter').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBulkButton);
});

function toggleBulkButton() {
    const checked = document.querySelectorAll('.selectVoter:checked').length > 0;
    document.getElementById('bulkApproveBtn').disabled = !checked;
}

const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const voterId = button.getAttribute('data-voter-id');
    document.getElementById('rejectVoterId').value = voterId;
});
</script>

<?php include '../includes/footer.php'; ?>