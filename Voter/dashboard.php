<?php
// voter/dashboard.php
$page_title = "Voter Dashboard";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();

// Check if user has active (pending or approved) nominations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE user_id = ? AND status IN ('pending', 'approved')");
$stmt->execute([$current_user['id']]);
$is_candidate = $stmt->fetchColumn() > 0;

// Fetch recent notifications
$stmt = $pdo->prepare("SELECT title, message, type, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$current_user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <h2 class="mb-2 mb-md-0">Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h2>
                <span class="badge bg-success fs-6">Voter</span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Card -->
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Your Profile</h5>
                    <p class="card-text">
                        <strong>University ID:</strong> <?php echo htmlspecialchars($current_user['university_id']); ?><br>
                        <strong>Hall:</strong> <?php echo htmlspecialchars($current_user['hall_name']); ?><br>
                        <strong>Department:</strong> <?php echo htmlspecialchars($current_user['department']); ?><br>
                        <strong>Year:</strong> <?php echo $current_user['enrollment_year']; ?><br>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($current_user['phone'] ?: 'N/A'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Voting Status -->
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Voting Status</h5>
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $current_user['has_voted_jucsu'] ? 'success' : 'warning'; ?>">
                            JUCSU: <?php echo $current_user['has_voted_jucsu'] ? 'Voted' : 'Not Voted'; ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $current_user['has_voted_hall'] ? 'success' : 'warning'; ?>">
                            Hall: <?php echo $current_user['has_voted_hall'] ? 'Voted' : 'Not Voted'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-12 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <?php if ($is_candidate): ?>
                            <a href="../candidate/dashboard.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-person-gear"></i> Open Candidate Dashboard
                            </a>
                        <?php else: ?>
                            <a href="#" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#applyModal">
                                <i class="bi bi-person-plus-fill"></i> Apply as Candidate
                            </a>
                        <?php endif; ?>
                        <a href="vote.php?type=jucsu" class="btn btn-success btn-sm">
                            <i class="bi bi-check-circle-fill"></i> Vote JUCSU
                        </a>
                        <a href="vote.php?type=hall" class="btn btn-warning btn-sm">
                            <i class="bi bi-building"></i> Vote Hall
                        </a>
                        <a href="candidates.php" class="btn btn-info btn-sm" target="_blank">
                            <i class="bi bi-people"></i> View Candidates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Recent Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">No new notifications.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-<?php echo $notification['type']; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </strong>
                                            <p class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <span class="badge bg-<?php echo $notification['type']; ?> text-white">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h5 class="alert-heading">Welcome to JUCSU Election System!</h5>
                <p class="mb-0">This is your voter dashboard. You can cast your votes for both JUCSU and Hall Union elections here.</p>
            </div>
        </div>
    </div>

    <!-- Apply as Candidate Modal -->
    <?php if (!$is_candidate): ?>
        <div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="applyModalLabel">Choose Election Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Select the type of election you want to apply as a candidate for:</p>
                        <div class="d-grid gap-2">
                            <a href="../candidate/nominate.php?type=jucsu" class="btn btn-primary">Apply for JUCSU Positions</a>
                            <a href="../candidate/nominate.php?type=hall" class="btn btn-secondary">Apply for Hall Positions</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>