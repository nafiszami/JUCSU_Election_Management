<?php
// central/dashboard.php
$page_title = "Central Commissioner Dashboard";
require_once '../includes/check_auth.php';
requireRole('central_commissioner');

$current_user = getCurrentUser();
include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2>Central Election Commission Dashboard</h2>
        <p>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h3>12,450</h3>
                <p>Total Voters</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h3>132</h3>
                <p>JUCSU Candidates</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h3>443</h3>
                <p>Hall Candidates</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h3>21</h3>
                <p>Halls</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>