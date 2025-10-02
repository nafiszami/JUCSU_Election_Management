<?php
// hall/scrutiny_hall.php
$page_title = "Hall Candidate Scrutiny";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

// Fetch pending nominations
$stmt = $pdo->prepare("
    SELECT 
        c.id, c.user_id, c.position_id, c.election_type, c.hall_name, c.manifesto, c.photo_path, 
        c.status, c.rejection_reason, c.nominated_at, c.scrutiny_at,
        u.university_id, u.full_name, u.department, u.hall_name as candidate_hall,
        p.position_name,
        proposer.university_id as proposer_university_id, proposer.full_name as proposer_name,
        seconder.university_id as seconder_university_id, seconder.full_name as seconder_name
    FROM candidates c
    JOIN users u ON c.user_id = u.id
    JOIN positions p ON c.position_id = p.id
    JOIN users proposer ON c.proposer_id = proposer.id
    JOIN users seconder ON c.seconder_id = seconder.id
    WHERE c.election_type = 'hall' AND c.hall_name = ? AND c.status = 'pending'
    ORDER BY p.position_order, c.nominated_at
");
$stmt->execute([$hall_name]);
$pending_nominations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['candidate_id'])) {
    $candidate_id = $_POST['candidate_id'];
    $action = $_POST['action'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    $update_stmt = $pdo->prepare("
        UPDATE candidates 
        SET status = ?, rejection_reason = ?, scrutiny_at = NOW()
        WHERE id = ? AND election_type = 'hall' AND hall_name = ?
    ");
    
    if ($action === 'approve') {
        $update_stmt->execute(['approved', null, $candidate_id, $hall_name]);
        $action_log = 'APPROVE_CANDIDATE';
    } elseif ($action === 'reject') {
        $update_stmt->execute(['rejected', $rejection_reason, $candidate_id, $hall_name]);
        $action_log = 'REJECT_CANDIDATE';
    }
    
    // Log action
    $log_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values)
        VALUES (?, ?, 'candidates', ?, ?)
    ");
    $log_stmt->execute([
        $current_user['id'], 
        $action_log, 
        $candidate_id, 
        json_encode(['status' => $action === 'approve' ? 'approved' : 'rejected', 'rejection_reason' => $rejection_reason])
    ]);
    
    header('Location: scrutiny_hall.php?success=' . $action);
    exit;
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Candidate Scrutiny - <?php echo htmlspecialchars($hall_name); ?></h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Candidate <?php echo $_GET['success'] === 'approve' ? 'approved' : 'rejected'; ?> successfully!
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pending Nominations (<?php echo count($pending_nominations); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pending_nominations)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-check-circle fs-1"></i>
                    <p>No pending nominations</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Hall</th>
                                <th>Proposer</th>
                                <th>Seconder</th>
                                <th>Manifesto</th>
                                <th>Photo</th>
                                <th>Nominated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_nominations as $nomination): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($nomination['full_name']); ?><br>
                                    <small><?php echo htmlspecialchars($nomination['university_id']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($nomination['position_name']); ?></td>
                                <td><?php echo htmlspecialchars($nomination['department']); ?></td>
                                <td><?php echo htmlspecialchars($nomination['candidate_hall']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($nomination['proposer_name']); ?><br>
                                    <small><?php echo htmlspecialchars($nomination['proposer_university_id']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($nomination['seconder_name']); ?><br>
                                    <small><?php echo htmlspecialchars($nomination['seconder_university_id']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(substr($nomination['manifesto'], 0, 50)) . '...'; ?></td>
                                <td>
                                    <?php if ($nomination['photo_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($nomination['photo_path']); ?>" alt="Candidate Photo" width="50" class="img-thumbnail">
                                    <?php else: ?>
                                        No Photo
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($nomination['nominated_at'])); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="candidate_id" value="<?php echo $nomination['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                            <i class="bi bi-check"></i> Approve
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-candidate-id="<?php echo $nomination['id']; ?>">
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
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="candidate_id" id="rejectCandidateId">
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
const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const candidateId = button.getAttribute('data-candidate-id');
    document.getElementById('rejectCandidateId').value = candidateId;
});
</script>

<?php include '../includes/footer.php'; ?>