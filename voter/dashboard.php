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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
            <h2 class="mb-2 mb-md-0">Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h2>
            <span class="badge bg-success fs-6">Voter</span>
        </div>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="card h-100">
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
    <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="card h-100">
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
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="vote.php?type=jucsu" class="btn btn-success btn-sm">
                        <i class="bi bi-vote-fill"></i> Vote JUCSU
                    </a>
                    <a href="vote.php?type=hall" class="btn btn-warning btn-sm">
                        <i class="bi bi-building"></i> Vote Hall
                    </a>
                    <a href="candidates.php" class="btn btn-info btn-sm">
                        <i class="bi bi-people"></i> Candidates
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="alert-heading">Welcome to JUCSU Election System!</h5>
            <p class="mb-0">This is your voter dashboard. You can cast your votes for both JUCSU and Hall Union elections here.</p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>