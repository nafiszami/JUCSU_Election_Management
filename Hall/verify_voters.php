```php
<?php
// hall/verify_voters.php
$page_title = "Hall Voter Verification";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

$success = '';
$error = '';

// Handle bulk approval
if (isset($_POST['bulk_approve'])) {
    $voter_ids = $_POST['voter_ids'] ?? [];
    if (!empty($voter_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($voter_ids), '?'));
            $params = array_merge($voter_ids, [$hall_name]);
            
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1 
                WHERE id IN ($placeholders) AND hall_name = ?
            ");
            $update_stmt->execute($params);
            
            $count = $update_stmt->rowCount();
            
            // Log action
            foreach ($voter_ids as $voter_id) {
                $log_stmt = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                    VALUES (?, 'VERIFY_VOTER', 'users', ?, ?)
                ");
                $log_stmt->execute([
                    $current_user['id'], 
                    $voter_id, 
                    json_encode(['verified' => true, 'verified_by' => $current_user['id']])
                ]);
            }
            
            $success = "Successfully verified $count voters!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
        
        // Refresh the page to update the list
        header("Refresh:0");
    }
}

// Handle single approval/rejection
if (isset($_POST['action']) && isset($_POST['voter_id'])) {
    $voter_id = (int)$_POST['voter_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1 
                WHERE id = ? AND hall_name = ?
            ");
            $update_stmt->execute([$voter_id, $hall_name]);
            
            // Log
            $log_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                VALUES (?, 'APPROVE_VOTER', 'users', ?, ?)
            ");
            $log_stmt->execute([
                $current_user['id'], 
                $voter_id, 
                json_encode(['verified' => true])
            ]);
            
            $success = 'Voter approved successfully!';
            
        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? 'No reason provided';
            
            // Mark as inactive instead of keeping pending
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_active = 0 
                WHERE id = ? AND hall_name = ?
            ");
            $update_stmt->execute([$voter_id, $hall_name]);
            
            // Log rejection
            $log_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                VALUES (?, 'REJECT_VOTER', 'users', ?, ?)
            ");
            $log_stmt->execute([
                $current_user['id'], 
                $voter_id, 
                json_encode(['rejected' => true, 'reason' => $reason])
            ]);
            
            $success = 'Voter rejected!';
        }
        
        // Refresh to show updated list
        header("Refresh:0");
        
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch pending voters
$stmt = $pdo->prepare("
    SELECT id, university_id, full_name, department, enrollment_year, gender, phone, created_at
    FROM users 
    WHERE hall_name = ? AND role = 'voter' AND is_verified = 0 AND is_active = 1
    ORDER BY created_at DESC
");
$stmt->execute([$hall_name]);
$pending_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Voter Verification</h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($hall_name); ?></p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="bulkForm">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-person-check"></i> Pending Voters 
                <span class="badge bg-warning"><?php echo count($pending_voters); ?></span>
            </h5>
            <button type="submit" name="bulk_approve" class="btn btn-success btn-sm" id="bulkApproveBtn" disabled>
                <i class="bi bi-check-all"></i> Approve Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pending_voters)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <p class="mt-2 mb-0">No pending verifications</p>
                    <small>All voters have been verified</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>University ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Gender</th>
                                <th>Registered</th>
                                <th style="width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_voters as $voter): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="voter_ids[]" 
                                           value="<?php echo $voter['id']; ?>" 
                                           class="selectVoter form-check-input">
                                </td>
                                <td><strong><?php echo htmlspecialchars($voter['university_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($voter['department']); ?></small>
                                </td>
                                <td><?php echo $voter['enrollment_year']; ?></td>
                                <td><?php echo ucfirst($voter['gender']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($voter['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Approve this voter?');">
                                        <input type="hidden" name="voter_id" value="<?php echo $voter['id']; ?>">
                                        <button type="submit" name="action" value="approve" 
                                                class="btn btn-sm btn-success" title="Approve">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal" 
                                            data-voter-id="<?php echo $voter['id']; ?>"
                                            data-voter-name="<?php echo htmlspecialchars($voter['full_name']); ?>"
                                            title="Reject">
                                        <i class="bi bi-x"></i>
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

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i> Reject Voter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="voter_id" id="rejectVoterId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Rejecting will deactivate this voter's account.
                    </div>
                    
                    <p>Voter: <strong id="rejectVoterName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" 
                                  placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Voter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function(e) {
    document.querySelectorAll('.selectVoter').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
    toggleBulkButton();
});

// Individual checkbox change
document.querySelectorAll('.selectVoter').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBulkButton);
});

// Toggle bulk approve button
function toggleBulkButton() {
    const checkedCount = document.querySelectorAll('.selectVoter:checked').length;
    const bulkBtn = document.getElementById('bulkApproveBtn');
    const countSpan = document.getElementById('selectedCount');
    
    bulkBtn.disabled = checkedCount === 0;
    countSpan.textContent = checkedCount;
}

// Reject modal - populate voter info
const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const voterId = button.getAttribute('data-voter-id');
    const voterName = button.getAttribute('data-voter-name');
    
    document.getElementById('rejectVoterId').value = voterId;
    document.getElementById('rejectVoterName').textContent = voterName;
});

// Bulk approve confirmation
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.selectVoter:checked').length;
    if (!confirm(`Are you sure you want to approve ${count} voter(s)?`)) {
        e.preventDefault();
    }
});
</script>

<?php include '../includes/footer.php'; ?>