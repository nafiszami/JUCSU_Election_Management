<?php
// Ensure $pdo is available from the included dashboard.php
if (!isset($pdo)) {
    echo '<div class="alert alert-danger">FATAL ERROR: Database connection missing. Cannot load complaints.</div>';
    return;
}

// Initialize variables for status messages and data
$complaints = [];
$error_message = '';
$success_message = '';

// The $user_id is no longer strictly needed for the database query but is kept 
// initialized here for completeness if you decide to use it later.
$user_id = $_SESSION['commission_user_id'] ?? 0; 



$c_id = $_GET['id'] ?? 0;
$new_status = $_GET['status'] ?? '';


if (is_numeric($c_id) && $c_id > 0 && !empty($new_status)) {
    
    $allowed_statuses = ['resolved', 'under_review'];
    $new_status = strtolower($new_status);

    if (in_array($new_status, $allowed_statuses)) {
        try {
            // FIX APPLIED: Removed the reference to resolved_by column
            $stmt = $pdo->prepare("
                UPDATE complaints 
                SET 
                    status = :status, 
                    resolved_at = NOW() 
                WHERE 
                    id = :id AND status = 'pending'
            ");

            $stmt->execute([
                ':status' => $new_status,
                ':id' => $c_id
            ]);

            $rows_affected = $stmt->rowCount();

            if ($rows_affected > 0) {
                $message = ucfirst(str_replace('_', ' ', $new_status));
                $success_message = "Complaint #{$c_id} successfully marked as **{$message}** and removed from the pending list.";
            } else {
                $error_message = "Error: Complaint #{$c_id} status could not be updated. It may have already been processed.";
            }

        } catch (PDOException $e) {
            error_log("Complaint Status Update Error: " . $e->getMessage());
            $error_message = "A critical database error occurred while updating the complaint status.";
        }
    } else {
         $error_message = "Invalid status provided.";
    }
}



try {
    
    $stmt = $pdo->prepare("
        SELECT 
            id, complainant_id, election_type, subject, description, status, filed_at 
        FROM 
            complaints 
        WHERE 
            status = 'pending'
        ORDER BY 
            filed_at ASC
    ");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    
    $error_message .= (empty($error_message) ? "" : "<br>") . "Database Error: Could not fetch pending complaints. SQL State: " . $e->getCode();
}
?>

<div class="container-fluid">
    <h3 class="mb-4 text-primary">🗣️ Complaints Management (Pending)</h3>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($complaints)): ?>
        <div class="alert alert-info text-center mt-5">
            🎉 No pending complaints found! The commission is running smoothly.
        </div>
    <?php else: ?>
        <p class="text-secondary">Showing **<?= count($complaints) ?>** complaint(s) awaiting review.</p>

        <div class="table-responsive shadow rounded">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Complainant ID</th> 
                        <th>Subject</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Filed At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $complaint): ?>
                    <tr>
                        <td><?= htmlspecialchars($complaint['id']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($complaint['election_type'])) ?></span></td>
                        <td><?= htmlspecialchars($complaint['complainant_id']) ?></td>
                        <td><strong><?= htmlspecialchars($complaint['subject']) ?></strong></td>
                        <td><?= htmlspecialchars(substr($complaint['description'], 0, 80)) ?>...</td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars(str_replace('_', ' ', strtoupper($complaint['status']))) ?></span></td>
                        <td><?= date('Y-m-d H:i', strtotime($complaint['filed_at'])) ?></td>
                        <td>
                            <a href="dashboard.php?page=complaints&id=<?= $complaint['id'] ?>&status=resolved" 
                               class="btn btn-sm btn-success mb-1" 
                               onclick="return confirm('Mark as RESOLVED? This removes it from the pending list.');">
                                <i class="bi bi-check-circle"></i> Mark Resolved
                            </a>
                            
                            <a href="dashboard.php?page=complaints&id=<?= $complaint['id'] ?>&status=under_review" 
                               class="btn btn-sm btn-info mb-1"
                               onclick="return confirm('Mark as UNDER REVIEW? This removes it from the pending list.');">
                                <i class="bi bi-search"></i> Mark Under Review
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>