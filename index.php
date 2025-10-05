<?php
// index.php
$page_title = "Home - JUCSU Election System";
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

include 'includes/header.php';
?>

<style>
    .nav-tabs-custom {
    border-top: 3px solid #28a745; /* green border on top */
    border-bottom: none; /* remove bottom border */
    margin-bottom: 30px;
    display: flex;
    justify-content: space-evenly; /* evenly space items */
}

.nav-tabs-custom .nav-link {
    border: none;
    color: #28a745; /* green font */
    padding: 20px 40px; /* bigger clickable area */
    font-size: 1.3rem; /* bigger text */
    font-weight: 700;
    text-align: center;
    flex: 1; /* make all tabs equal width */
    transition: all 0.3s ease-in-out;
}

.nav-tabs-custom .nav-link:hover {
    background: #f1f9f3;
    color: #20c997;
}

.nav-tabs-custom .nav-link.active {
    color: #fff;
    background: #28a745;
    border-radius: 8px;
}

.hero-section {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
    position: relative;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="none"/><circle cx="50" cy="50" r="40" fill="rgba(255,255,255,0.05)"/></svg>');
    opacity: 0.3;
}

.logo-box {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
    margin: 0 auto 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.nav-tabs-custom {
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 30px;
}

.nav-tabs-custom .nav-link {
    border: none;
    color: #666;
    padding: 15px 30px;
    font-weight: 600;
    transition: all 0.3s;
}

.nav-tabs-custom .nav-link:hover {
    color: #28a745;
    background: #f1f9f3;
}

.nav-tabs-custom .nav-link.active {
    color: #28a745;
    background: transparent;
    border-bottom: 3px solid #28a745;
}

.info-card {
    border-radius: 15px;
    transition: transform 0.3s, box-shadow 0.3s;
    border: none;
    height: 100%;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.icon-circle {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 2rem;
}

.countdown-box {
    background: #f1f9f3;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    margin: 5px;
}

.countdown-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #28a745;
}

.countdown-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
}

.quick-link-card {
    border-radius: 15px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}

.quick-link-card:hover {
    border-color: #28a745;
    background: #f1f9f3;
    transform: translateY(-3px);
    color: inherit;
}

.section-title {
    font-weight: 700;
    color: #333;
    margin-bottom: 30px;
    position: relative;
    padding-bottom: 15px;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 4px;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 2px;
}

.social-links a {
    display: inline-block;
    width: 45px;
    height: 45px;
    line-height: 45px;
    text-align: center;
    border-radius: 50%;
    background: white;
    color: #28a745;
    margin: 0 5px;
    transition: all 0.3s;
    text-decoration: none;
}

.social-links a:hover {
    background: #28a745;
    color: white;
    transform: translateY(-3px);
}

.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>

<!-- Hero Section -->
<div class="row mb-5">
    <div class="col-12">
        <div class="hero-section text-white position-relative">
            <div class="card-body text-center py-5 position-relative" style="z-index: 1;">
                <div class="logo-box pulse-animation">
                    <i class="bi bi-trophy-fill" ></i>
                </div>
                <h1 class="display-3 fw-bold mb-3">JUCSU Election</h1>
                <p class="lead fs-4 mb-4">Jahangirnagar University Central Student's Union</p>
                
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs nav-tabs-custom">
    <li class="nav-item">
        <a class="nav-link active" href="#home">
            <i class="bi bi-house-door me-2"></i>Home
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="login.php">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="register.php">
            <i class="bi bi-person-plus me-2"></i>Registration
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="statusDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bar-chart me-2"></i>Status
        </a>
        <ul class="dropdown-menu" aria-labelledby="statusDropdown">
            <li><a class="dropdown-item" href="#">Running</a></li>
        </ul>
    </li>
</ul>

<!-- Days Remaining Section -->
<div class="row mb-5">
    <div class="col-12">
        <div class="card info-card border-0 shadow-sm">
            <div class="card-body py-4">
                <h3 class="text-center mb-4 fw-bold">Days Remaining</h3>
                <div class="row justify-content-center">
                    <div class="col-md-2 col-4">
                        <div class="countdown-box">
                            <div class="countdown-number">100</div>
                            <div class="countdown-label">Days</div>
                        </div>
                    </div>
                    <div class="col-md-1 col-1 d-flex align-items-center justify-content-center">
                        <h2 class="mb-0">:</h2>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="countdown-box">
                            <div class="countdown-number">15</div>
                            <div class="countdown-label">Hours</div>
                        </div>
                    </div>
                    <div class="col-md-1 col-1 d-flex align-items-center justify-content-center">
                        <h2 class="mb-0">:</h2>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="countdown-box">
                            <div class="countdown-number">50</div>
                            <div class="countdown-label">Minutes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links Section -->
<div class="row mb-5">
    <div class="col-12">
        <h2 class="section-title">Quick Links</h2>
    </div>
    <div class="col-md-3 col-6 mb-4">
    <a href="assets/pdf/jucsu-constitution.pdf" class="quick-link-card" target="_blank">
    <div class="card-body text-center py-4">
        <div class="icon-circle mx-auto" style="background: #e3f2fd;">
            <i class="bi bi-file-text text-primary"></i>
        </div>
        <h5 class="fw-bold">JUCSU Constitution</h5>
        <p class="text-muted small mb-0">Open PDF</p>
    </div>
</a>
</div>
<div class="col-md-3 col-6 mb-4">
<a href="assets/pdf/hall-constitution.pdf" class="quick-link-card" target="_blank">
    <div class="card-body text-center py-4">
        <div class="icon-circle mx-auto" style="background: #f3e5f5;">
            <i class="bi bi-building text-purple"></i>
        </div>
        <h5 class="fw-bold">Hall Union Constitution</h5>
        <p class="text-muted small mb-0">Open PDF</p>
    </div>
</a>
</div>
    <div class="col-md-3 col-6 mb-4">
        <a href="view_candidates.php" class="quick-link-card">
            <div class="card-body text-center py-4">
                <div class="icon-circle mx-auto" style="background: #fff3e0;">
                    <i class="bi bi-people text-warning"></i>
                </div>
                <h5 class="fw-bold">View Candidates</h5>
                <p class="text-muted small mb-0">See all candidates</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6 mb-4">
        <a href="results.php" class="quick-link-card">
            <div class="card-body text-center py-4">
                <div class="icon-circle mx-auto" style="background: #e8f5e9;">
                    <i class="bi bi-trophy text-success"></i>
                </div>
                <h5 class="fw-bold">Election Results</h5>
                <p class="text-muted small mb-0">View live results</p>
            </div>
        </a>
    </div>
</div>

<!-- Statistics Section -->
<div class="row mb-5">
    <div class="col-12">
        <h2 class="section-title">Election Statistics</h2>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card info-card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="icon-circle mx-auto" style="background: #e8f5e9;">
                    <i class="bi bi-calendar-check text-success"></i>
                </div>
                <h5 class="fw-bold mt-3">Current Status</h5>
                <h3 class="text-success fw-bold mb-0">VOTING ACTIVE</h3>
                <p class="text-muted small mt-2 mb-0">Election is currently ongoing</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card info-card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="icon-circle mx-auto" style="background: #e3f2fd;">
                    <i class="bi bi-people text-primary"></i>
                </div>
                <h5 class="fw-bold mt-3">Total Candidates</h5>
                <h3 class="text-primary fw-bold mb-0">575</h3>
                <p class="text-muted small mt-2 mb-0">Registered candidates</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card info-card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="icon-circle mx-auto" style="background: #fff3e0;">
                    <i class="bi bi-person-check text-warning"></i>
                </div>
                <h5 class="fw-bold mt-3">Registered Voters</h5>
                <h3 class="text-warning fw-bold mb-0">12,450</h3>
                <p class="text-muted small mt-2 mb-0">Eligible voters</p>
            </div>
        </div>
    </div>
</div>

<!-- Footer Social Links -->
<div class="row mt-5 pt-4 border-top">
    <div class="col-12 text-center">
        <p class="text-muted mb-3">Follow Us</p>
        <div class="social-links mb-4">
            <a href="#" title="Logo"><i class="bi bi-trophy-fill"></i></a>
            <a href="#" title="Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" title="Twitter"><i class="bi bi-twitter"></i></a>
            <a href="#" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
        </div>
        <p class="text-muted small">© 2006 Jahangirnagar University | All Rights Reserved</p>
    </div>
</div>

<script>
// Live countdown timer
function updateCountdown() {
    const countdownDate = new Date("2026-01-20T00:00:00").getTime();
    const now = new Date().getTime();
    const distance = countdownDate - now;
    
    if (distance > 0) {
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        
        document.querySelectorAll('.countdown-number')[0].textContent = days;
        document.querySelectorAll('.countdown-number')[1].textContent = hours;
        document.querySelectorAll('.countdown-number')[2].textContent = minutes;
    }
}

// Update countdown every minute
setInterval(updateCountdown, 60000);
updateCountdown();

// Add smooth scroll for navigation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>