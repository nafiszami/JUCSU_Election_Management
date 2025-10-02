<?php
// hall/dashboard.php
$page_title = "Hall Commissioner Dashboard";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2><?php echo htmlspecialchars($current_user['hall_name']); ?> - Hall Commissioner</h2>
        <p>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h3>580</h3>
                <p>Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h3>520</h3>
                <p>Verified Voters</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h3>28</h3>
                <p>Hall Candidates</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h3>65%</h3>
                <p>Voter Turnout</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>