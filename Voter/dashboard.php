<?php
// voter/dashboard.php
$page_title = "Voter Dashboard";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h2>
            <span class="badge bg-success fs-6">Voter</span>
        </div>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Your Profile</h5>
                <p class="card-text">
                    <strong>University ID:</strong> <?php echo htmlspecialchars($current_user['university_id']); ?><br>
                    <strong>Hall:</strong> <?php echo htmlspecialchars($current_user['hall_name']); ?><br>
                    <strong>Department:</strong> <?php echo htmlspecialchars($current_user['department']); ?><br>
                    <strong>Year:</strong> <?php echo $current_user['enrollment_year']; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Voting Status -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Voting Status</h5>
                <div class="mb-2">
                    <span class="badge bg-<?php echo $current_user['has_voted_jucsu'] ? 'success' : 'warning'; ?>">
                        JUCSU: <?php echo $current_user['has_voted_jucsu'] ? 'Voted' : 'Not Voted'; ?>
                    </span>
                </div>
                <div class="mb-3">
                    <span class="badge bg-<?php echo $current_user['has_voted_hall'] ? 'success' : 'warning'; ?>">
                        Hall: <?php echo $current_user['has_voted_hall'] ? 'Voted' : 'Not Voted'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="vote.php?type=jucsu" class="btn btn-success">Vote JUCSU</a>
                    <a href="vote.php?type=hall" class="btn btn-warning">Vote Hall</a>
                    <a href="candidates.php" class="btn btn-info">View Candidates</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <h5>Welcome to JUCSU Election System!</h5>
            <p>This is your voter dashboard. You can cast your votes for both JUCSU and Hall Union elections here.</p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>