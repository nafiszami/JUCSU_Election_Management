<?php
// hall/scrutiny_hall.php
$page_title = "Hall Nomination Scrutiny";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

// Fixed query: No hall_position_id, use positions table
$stmt = $pdo->prepare("
    SELECT c.id, u.full_name, u.university_id, p.position_name, c.manifesto, c.photo_path,
           proposer.full_name as proposer_name, seconder.full_name as seconder_name,
           c.nominated_at
    FROM candidates c
    JOIN users u ON c.user_id = u.id
    JOIN positions p ON c.position_id = p.id
    JOIN users proposer ON c.proposer_id = proposer.id
    JOIN users seconder ON c.seconder_id = seconder.id
    WHERE u.hall_name = ? AND c.election_type = 'hall' AND c.status = 'pending'
    ORDER BY c.nominated_at DESC
");
$stmt->execute([$hall_name]);
$pending_nominations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle bulk approval
if (isset($_POST['bulk_approve'])) {
    $nom_ids = $_POST['nom_ids'] ?? [];
    if (!empty($nom_ids)) {
        $placeholders = implode(',', array_fill(0, count($nom_ids), '?'));
        $update_stmt = $pdo->prepare("UPDATE candidates SET status = 'approved', scrutiny_at = NOW() WHERE id IN ($placeholders) AND election_type = 'hall'");
        $update_stmt->execute($nom_ids);
        
        // Log action
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'APPROVE_NOMINATIONS', 'candidates', ?, ?)");
        $log_stmt->execute([$current_user['id'], implode(',', $nom_ids), json_encode(['status' => 'approved'])]);
        
        header('Location: scrutiny_hall.php?success=bulk_approved');
        exit;
    }
}

// Handle single approval/rejection
if (isset($_POST['action']) && isset($_POST['nom_id'])) {
    $nom_id = $_POST['nom_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $update_stmt = $pdo->prepare("UPDATE candidates SET status = 'approved', scrutiny_at = NOW() WHERE id = ? AND election_type = 'hall'");
        $update_stmt->execute([$nom_id]);
        
        // Log
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'APPROVE_NOMINATION', 'candidates', ?, ?)");
        $log_stmt->execute([$current_user['id'], $nom_id, json_encode(['status' => 'approved'])]);
        
        header('Location: scrutiny_hall.php?success=approved');
        exit;
    } elseif ($action === 'reject') {
        $reason = $_POST['rejection_reason'] ?? '';
        $update_stmt = $pdo->prepare("UPDATE candidates SET status = 'rejected', rejection_reason = ?, scrutiny_at = NOW() WHERE id = ? AND election_type = 'hall'");
        $update_stmt->execute([$reason, $nom_id]);
        
        // Log
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, 'REJECT_NOMINATION', 'candidates', ?, ?)");
        $log_stmt->execute([$current_user['id'], $nom_id, json_encode(['status' => 'rejected', 'reason' => $reason])]);
        
        header('Location: scrutiny_hall.php?success=rejected');
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Hall Nomination Scrutiny - <?php echo htmlspecialchars($hall_name); ?></h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php 
            switch($_GET['success']) {
                case 'bulk_approved': echo 'Selected nominations approved successfully!'; break;
                case 'approved': echo 'Nomination approved!'; break;
                case 'rejected': echo 'Nomination rejected!'; break;
            }
            ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="bulkForm">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pending Nominations (<?php echo count($pending_nominations); ?>)</h5>
                <button type="submit" name="bulk_approve" class="btn btn-success btn-sm" id="bulkApproveBtn" disabled>
                    <i class="bi bi-check-all"></i> Approve Selected
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pending_nominations)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-file-earmark-check fs-1"></i>
                        <p>No pending nominations</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Proposer/Seconder</th>
                                    <th>Manifesto</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_nominations as $nom): ?>
                                <tr>
                                    <td><input type="checkbox" name="nom_ids[]" value="<?php echo $nom['id']; ?>" class="selectNom"></td>
                                    <td><?php echo htmlspecialchars($nom['university_id']); ?></td>
                                    <td><?php echo htmlspecialchars($nom['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($nom['position_name']); ?></td>
                                    <td>
                                        P: <?php echo htmlspecialchars($nom['proposer_name']); ?>
                                        <br>S: <?php echo htmlspecialchars($nom['seconder_name']); ?>
                                    </td>
                                    <td>
                                        <?php if ($nom['manifesto']): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#manifestoModal" data-manifesto="<?php echo htmlspecialchars($nom['manifesto']); ?>" class="btn btn-sm btn-outline-info">View</a>
                                        <?php endif; ?>
                                        <?php if ($nom['photo_path']): ?>
                                            <a href="<?php echo $nom['photo_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Photo</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="nom_id" value="<?php echo $nom['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i> Approve
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-nom-id="<?php echo $nom['id']; ?>">
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

-- Manifesto Modal --
<div class="modal fade" id="manifestoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Candidate Manifesto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="manifestoContent"></p>
            </div>
        </div>
    </div>
</div>

-- Reject Modal --
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Nomination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="nom_id" id="rejectNomId">
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
    document.querySelectorAll('.selectNom').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
    toggleBulkButton();
});

document.querySelectorAll('.selectNom').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBulkButton);
});

function toggleBulkButton() {
    const checked = document.querySelectorAll('.selectNom:checked').length > 0;
    document.getElementById('bulkApproveBtn').disabled = !checked;
}

const manifestoModal = document.getElementById('manifestoModal');
manifestoModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const manifesto = button.getAttribute('data-manifesto');
    document.getElementById('manifestoContent').textContent = manifesto;
});

const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const nomId = button.getAttribute('data-nom-id');
    document.getElementById('rejectNomId').value = nomId;
});
</script>

<?php include '../includes/footer.php'; ?>