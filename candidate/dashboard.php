<?php
// candidate/dashboard.php
$page_title = "Candidate Dashboard";
require_once '../includes/check_auth.php';
requireLogin();

$current_user = getCurrentUser();
include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2>Candidate Dashboard</h2>
        <p>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5>JUCSU Election</h5>
                <p>You haven't applied for any JUCSU position yet.</p>
                <a href="nominate.php?type=jucsu" class="btn btn-success">Apply Now</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5>Hall Union Election</h5>
                <p>You haven't applied for any Hall position yet.</p>
                <a href="nominate.php?type=hall" class="btn btn-warning">Apply Now</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>