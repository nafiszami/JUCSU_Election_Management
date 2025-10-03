<?php
// candidate/withdraw.php
$page_title = "Withdraw Nomination";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $candidate_id = (int)$_POST['candidate_id'];

        // Verify nomination exists and belongs to the user
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ? AND user_id = ? AND status IN ('pending', 'approved')");
        $stmt->execute([$candidate_id, $current_user['id']]);
        $nomination = $stmt->fetch();

        if (!$nomination) {
            $error = "Invalid nomination or not eligible for withdrawal.";
        } else {
            // Check if within withdrawal deadline
            $stmt = $pdo->prepare("SELECT withdrawal_deadline FROM election_schedule WHERE election_type = ? AND is_active = 1");
            $stmt->execute([$nomination['election_type']]);
            $deadline = $stmt->fetchColumn();
            if (!$deadline || date('Y-m-d') > $deadline) {
                $error = "The withdrawal deadline has passed.";
            } else {
                // Update nomination status to withdrawn
                $stmt = $pdo->prepare("UPDATE candidates SET status = 'withdrawn', withdrawn_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$candidate_id]);

                // Notify user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $current_user['id'],
                    'Nomination Withdrawn',
                    'Your nomination for ' . ucfirst($nomination['election_type']) . ' has been withdrawn.',
                    'info'
                ]);

                // Notify commissioner
                $commissioner_role = $nomination['election_type'] === 'jucsu' ? 'central_commissioner' : 'hall_commissioner';
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
                $stmt->execute([$commissioner_role]);
                while ($commissioner = $stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $commissioner['id'],
                        'Candidate Withdrawal',
                        $current_user['full_name'] . ' has withdrawn their nomination for ' . ucfirst($nomination['election_type']) . '.',
                        'warning'
                    ]);
                }

                $success = "Nomination withdrawn successfully.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center p-4">
            <h2 class="mb-0">Withdraw Nomination</h2>
            <p class="mt-2 mb-0">Confirm withdrawal of your candidacy.</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Candidate Dashboard</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($_GET['candidate_id'] ?? ''); ?>">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Withdrawing your nomination is permanent and cannot be undone. You may re-apply if the nomination period is still open.
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Confirm Withdrawal</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>