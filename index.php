```php
<?php
// index.php
$page_title = "Home - JUCSU Election System";
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

include 'includes/header.php';
?>

<div class="row mb-5" style="margin-top: 30px;"> <!-- Inline margin-top for spacing -->
    <div class="col-12">
        <div class="card bg-success text-white">
            <div class="card-body text-center py-5">
                <h1 class="display-4">JUCSU Election System</h1>
                <p class="lead">Jahangirnagar University Central Students' Union</p>
                <div class="mt-4">
                    <a href="login.php" class="btn btn-light btn-lg me-2">Login</a>
                    <a href="register.php" class="btn btn-outline-light btn-lg">Register</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-calendar-check text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Current Status</h5>
                <h4 class="text-success">VOTING ACTIVE</h4>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-people text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Total Candidates</h5>
                <h4 class="text-primary">575</h4>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-person-check text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Registered Voters</h5>
                <h4 class="text-warning">12,450</h4>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
```