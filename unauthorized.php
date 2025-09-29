<?php
// unauthorized.php
$page_title = "Unauthorized Access";
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="alert alert-danger text-center">
            <h4>Unauthorized Access</h4>
            <p>You don't have permission to access this page.</p>
            <a href="index.php" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>