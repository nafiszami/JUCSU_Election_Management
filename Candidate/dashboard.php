<?php
// candidate/dashboard.php
$page_title = "Candidate Dashboard";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();

// Fetch user's nominations
$stmt = $pdo->prepare("
    SELECT c.*, p.position_name
    FROM candidates c
    JOIN positions p ON c.position_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$current_user['id']]);
$nominations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <h2 class="mb-2 mb-md-0">Candidate Dashboard</h2>
                <span class="badge bg-primary fs-6">Candidate</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center p-4">
                    <h3 class="mb-0">Your Nominations</h3>
                    <p class="mt-2 mb-0">View the status of your candidacy for JUCSU or Hall elections.</p>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($nominations)): ?>
                        <div class="alert alert-warning">You have not applied for any positions yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Election Type</th>
                                        <th>Hall</th>
                                        <th>Position</th>
                                        <th>Manifesto</th>
                                        <th>Photo</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominations as $nomination): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(ucfirst($nomination['election_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($nomination['hall_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($nomination['position_name']); ?></td>
                                            <td>
                                                <?php
                                                $manifesto = $nomination['manifesto'] ?: 'No manifesto entered';
                                                echo htmlspecialchars(strlen($manifesto) > 100 ? substr($manifesto, 0, 100) . '...' : $manifesto);
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($nomination['photo_path']): ?>
                                                    <img src="<?php echo htmlspecialchars($nomination['photo_path']); ?>" alt="Candidate Photo" style="max-width: 100px; border-radius: 8px;">
                                                <?php else: ?>
                                                    No Photo
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $nomination['status'] === 'approved' ? 'success' :
                                                         ($nomination['status'] === 'pending' ? 'warning' :
                                                         ($nomination['status'] === 'withdrawn' ? 'info' : 'danger'));
                                                ?>">
                                                    <?php echo ucfirst($nomination['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($nomination['status'] === 'pending'): ?>
                                                    <span class="text-muted">Awaiting review</span>
                                                    <a href="withdraw.php?candidate_id=<?php echo $nomination['id']; ?>" class="btn btn-sm btn-danger mt-1">Withdraw Nomination</a>
                                                <?php elseif ($nomination['status'] === 'approved'): ?>
                                                    <a href="withdraw.php?candidate_id=<?php echo $nomination['id']; ?>" class="btn btn-sm btn-danger mt-1">Withdraw Nomination</a>
                                                <?php elseif ($nomination['status'] === 'rejected'): ?>
                                                    <span class="text-danger">Contact commissioner</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Withdrawn</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="../voter/dashboard.php" class="btn btn-outline-secondary">Back to Voter Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>