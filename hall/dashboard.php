<?php
// hall/dashboard.php
$page_title = "Hall Commissioner Dashboard";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

// Get hall statistics
$hall_stmt = $pdo->prepare("SELECT * FROM halls WHERE hall_name = ?");
$hall_stmt->execute([$hall_name]);
$hall_info = $hall_stmt->fetch();

// Count verified voters in this hall
$voters_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_voters,
           SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_voters,
           SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_voters
    FROM users 
    WHERE hall_name = ? AND role = 'voter'
");
$voters_stmt->execute([$hall_name]);
$voter_stats = $voters_stmt->fetch();

// Count hall candidates (pending and approved)
$candidates_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_candidates,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_candidates,
           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_candidates
    FROM candidates 
    WHERE user_id IN (SELECT id FROM users WHERE hall_name = ?) 
    AND election_type = 'hall'
");
$candidates_stmt->execute([$hall_name]);
$candidate_stats = $candidates_stmt->fetch();

// Calculate voting turnout
$turnout_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN election_type = 'jucsu' THEN voter_id END) as jucsu_voters,
        COUNT(DISTINCT CASE WHEN election_type = 'hall' THEN voter_id END) as hall_voters
    FROM votes v
    JOIN users u ON v.voter_id = u.id
    WHERE u.hall_name = ?
");
$turnout_stmt->execute([$hall_name]);
$turnout = $turnout_stmt->fetch();

// Calculate percentages
$jucsu_turnout = $voter_stats['verified_voters'] > 0 
    ? round(($turnout['jucsu_voters'] / $voter_stats['verified_voters']) * 100, 1) 
    : 0;
$hall_turnout = $voter_stats['verified_voters'] > 0 
    ? round(($turnout['hall_voters'] / $voter_stats['verified_voters']) * 100, 1) 
    : 0;

// Get recent activities (last 5 voters who registered)
$recent_stmt = $pdo->prepare("
    SELECT university_id, full_name, department, created_at, is_verified
    FROM users 
    WHERE hall_name = ? AND role = 'voter'
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_stmt->execute([$hall_name]);
$recent_users = $recent_stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
            <div>
                <h2><?php echo htmlspecialchars($hall_name); ?></h2>
                <p class="text-muted mb-0">Hall Election Commissioner Dashboard</p>
            </div>
            <span class="badge bg-warning text-dark fs-6 mt-2 mt-md-0">Hall Commissioner</span>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-0"><?php echo $voter_stats['total_voters']; ?></h3>
                        <small>Total Students</small>
                    </div>
                    <div class="ms-2">
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-0"><?php echo $voter_stats['verified_voters']; ?></h3>
                        <small>Verified Voters</small>
                    </div>
                    <div class="ms-2">
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-0"><?php echo $candidate_stats['total_candidates']; ?></h3>
                        <small>Hall Candidates</small>
                    </div>
                    <div class="ms-2">
                        <i class="bi bi-person-badge fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-0"><?php echo $hall_turnout; ?>%</h3>
                        <small>Hall Turnout</small>
                    </div>
                    <div class="ms-2">
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12 col-lg-8 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0 text-white">Hall Management</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-4 mb-3">
                        <a href="verify_voters.php" class="btn btn-success w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-person-check fs-2"></i>
                            <span class="mt-2">Verify Voters</span>
                            <?php if ($voter_stats['pending_voters'] > 0): ?>
                                <span class="badge bg-danger mt-1"><?php echo $voter_stats['pending_voters']; ?> pending</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="col-6 col-md-4 mb-3">
                        <a href="scrutiny_hall.php" class="btn btn-warning w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-search fs-2"></i>
                            <span class="mt-2">Hall Scrutiny</span>
                            <?php if ($candidate_stats['pending_candidates'] > 0): ?>
                                <span class="badge bg-danger mt-1"><?php echo $candidate_stats['pending_candidates']; ?> pending</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="col-6 col-md-4 mb-3">
                        <a href="monitor.php" class="btn btn-primary w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-activity fs-2"></i>
                            <span class="mt-2">Monitor Voting</span>
                        </a>
                    </div>
                    
                    <div class="col-6 col-md-4 mb-3">
                        <a href="reports.php" class="btn btn-info w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-file-earmark-bar-graph fs-2"></i>
                            <span class="mt-2">Hall Reports</span>
                        </a>
                    </div>
                    
                    <div class="col-6 col-md-4 mb-3">
                        <a href="../central/monitor.php" class="btn btn-secondary w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-arrow-up-circle fs-2"></i>
                            <span class="mt-2">Central View</span>
                        </a>
                    </div>
                    
                    <div class="col-6 col-md-4 mb-3">
                        <a href="../public_results.php" class="btn btn-dark w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-trophy fs-2"></i>
                            <span class="mt-2">Results</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Current Status -->
    <div class="col-12 col-lg-4 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Current Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3 text-center">
                    <span class="badge bg-success fs-6">VOTING ACTIVE</span>
                </div>
                
                <h6 class="mt-3">Voting Progress</h6>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>JUCSU Voting</small>
                        <small><?php echo $turnout['jucsu_voters']; ?>/<?php echo $voter_stats['verified_voters']; ?> (<?php echo $jucsu_turnout; ?>%)</small>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $jucsu_turnout; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Hall Voting</small>
                        <small><?php echo $turnout['hall_voters']; ?>/<?php echo $voter_stats['verified_voters']; ?> (<?php echo $hall_turnout; ?>%)</small>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo $hall_turnout; ?>%"></div>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <small class="text-muted">Last updated: <?php echo date('h:i A'); ?></small>
                    <br>
                    <a href="#" onclick="location.reload();" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Tasks -->
<div class="row">
    <div class="col-12 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Pending Verifications</h5>
            </div>
            <div class="card-body">
                <?php if ($voter_stats['pending_voters'] > 0 || $candidate_stats['pending_candidates'] > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($voter_stats['pending_voters'] > 0): ?>
                            <tr>
                                <td><i class="bi bi-person-check text-success"></i> Voter Verification</td>
                                <td><span class="badge bg-warning"><?php echo $voter_stats['pending_voters']; ?></span></td>
                                <td><a href="verify_voters.php" class="btn btn-sm btn-outline-primary">Review</a></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($candidate_stats['pending_candidates'] > 0): ?>
                            <tr>
                                <td><i class="bi bi-file-person text-warning"></i> Hall Candidates</td>
                                <td><span class="badge bg-warning"><?php echo $candidate_stats['pending_candidates']; ?></span></td>
                                <td><a href="scrutiny_hall.php" class="btn btn-sm btn-outline-primary">Review</a></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-check-circle fs-1"></i>
                    <p class="mb-0">No pending verifications</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Registrations -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Registrations</h5>
            </div>
            <div class="card-body">
                <?php if (count($recent_users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['university_id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['department']); ?></small>
                                </td>
                                <td>
                                    <?php if ($user['is_verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-2">
                    <a href="verify_voters.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <p class="mb-0">No recent registrations</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>