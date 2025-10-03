<?php
// central/results.php
$page_title = "Election Results";
require_once '../includes/check_auth.php';
requireRole('central_commissioner');

$current_user = getCurrentUser();

$success = '';
$error = '';

// Handle result declaration
if (isset($_POST['declare_results'])) {
    $election_type = $_POST['election_type'];
    
    try {
        // Update election phase to completed
        $update_stmt = $pdo->prepare("
            UPDATE election_schedule 
            SET current_phase = 'completed', 
                result_declaration = CURDATE() 
            WHERE election_type = ? AND is_active = 1
        ");
        $update_stmt->execute([$election_type]);
        
        // Log action
        $log_stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, new_values) 
            VALUES (?, 'DECLARE_RESULTS', 'election_schedule', ?)
        ");
        $log_stmt->execute([
            $current_user['id'], 
            json_encode(['election_type' => $election_type, 'declared_at' => date('Y-m-d H:i:s')])
        ]);
        
        $success = ucfirst($election_type) . " election results declared successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get election status
$jucsu_schedule = $pdo->query("
    SELECT * FROM election_schedule 
    WHERE election_type = 'jucsu' AND is_active = 1 
    LIMIT 1
")->fetch();

$hall_schedule = $pdo->query("
    SELECT * FROM election_schedule 
    WHERE election_type = 'hall' AND is_active = 1 
    LIMIT 1
")->fetch();

// Get JUCSU results
$jucsu_results_stmt = $pdo->query("
    SELECT 
        p.id as position_id,
        p.position_name,
        p.position_order,
        c.id as candidate_id,
        u.full_name,
        u.university_id,
        u.department,
        u.hall_name,
        c.vote_count
    FROM positions p
    LEFT JOIN candidates c ON p.id = c.position_id AND c.election_type = 'jucsu' AND c.status = 'approved'
    LEFT JOIN users u ON c.user_id = u.id
    WHERE p.election_type = 'jucsu' AND p.is_active = 1
    ORDER BY p.position_order, c.vote_count DESC
");
$jucsu_results = $jucsu_results_stmt->fetchAll();

// Group by position and find winners
$jucsu_by_position = [];
$jucsu_winners = [];
foreach ($jucsu_results as $result) {
    $pos_id = $result['position_id'];
    if (!isset($jucsu_by_position[$pos_id])) {
        $jucsu_by_position[$pos_id] = [
            'position_name' => $result['position_name'],
            'candidates' => []
        ];
    }
    if ($result['candidate_id']) {
        $jucsu_by_position[$pos_id]['candidates'][] = $result;
        
        // Determine winner (highest vote count)
        if (empty($jucsu_winners[$pos_id]) || 
            $result['vote_count'] > $jucsu_winners[$pos_id]['vote_count']) {
            $jucsu_winners[$pos_id] = $result;
        }
    }
}

// Get Hall results summary
$hall_results_stmt = $pdo->query("
    SELECT 
        c.hall_name,
        COUNT(DISTINCT c.id) as total_candidates,
        SUM(c.vote_count) as total_votes
    FROM candidates c
    WHERE c.election_type = 'hall' AND c.status = 'approved'
    GROUP BY c.hall_name
    ORDER BY c.hall_name
");
$hall_summary = $hall_results_stmt->fetchAll();

// Get overall statistics
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN role = 'voter' AND is_verified = 1 THEN id END) as total_voters,
        COUNT(DISTINCT CASE WHEN has_voted_jucsu = 1 THEN id END) as jucsu_voters,
        COUNT(DISTINCT CASE WHEN has_voted_hall = 1 THEN id END) as hall_voters
    FROM users
");
$stats = $stats_stmt->fetch();

$jucsu_turnout = $stats['total_voters'] > 0 ? 
    round(($stats['jucsu_voters'] / $stats['total_voters']) * 100, 2) : 0;
$hall_turnout = $stats['total_voters'] > 0 ? 
    round(($stats['hall_voters'] / $stats['total_voters']) * 100, 2) : 0;

// Get hall-wise turnout for chart
$hall_turnout_stmt = $pdo->query("
    SELECT 
        h.hall_name,
        COUNT(DISTINCT u.id) as verified_voters,
        COUNT(DISTINCT CASE WHEN u.has_voted_jucsu = 1 THEN u.id END) as jucsu_votes,
        COUNT(DISTINCT CASE WHEN u.has_voted_hall = 1 THEN u.id END) as hall_votes
    FROM halls h
    LEFT JOIN users u ON h.hall_name = u.hall_name AND u.is_verified = 1
    WHERE h.is_active = 1
    GROUP BY h.hall_name
    ORDER BY h.hall_name
");
$hall_turnout_data = $hall_turnout_stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2>Election Results Dashboard</h2>
                <p class="text-muted mb-0">View and declare election results</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Overall Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h3><?php echo number_format($stats['total_voters']); ?></h3>
                <p class="mb-0">Total Verified Voters</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h3><?php echo number_format($stats['jucsu_voters']); ?></h3>
                <p class="mb-0">JUCSU Votes Cast</p>
                <small><?php echo $jucsu_turnout; ?>% turnout</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h3><?php echo number_format($stats['hall_voters']); ?></h3>
                <p class="mb-0">Hall Votes Cast</p>
                <small><?php echo $hall_turnout; ?>% turnout</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h3>21</h3>
                <p class="mb-0">Residential Halls</p>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for JUCSU and Hall Results -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#jucsu-results">
            <i class="bi bi-building"></i> JUCSU Results
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#hall-results">
            <i class="bi bi-house-door"></i> Hall Results Summary
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#charts">
            <i class="bi bi-bar-chart"></i> Analytics
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- JUCSU Results Tab -->
    <div class="tab-pane fade show active" id="jucsu-results">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">JUCSU Election Results</h5>
                <?php if ($jucsu_schedule && $jucsu_schedule['current_phase'] !== 'completed'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="election_type" value="jucsu">
                        <button type="submit" name="declare_results" class="btn btn-success"
                                onclick="return confirm('Are you sure you want to declare JUCSU results? This action cannot be undone.');">
                            <i class="bi bi-megaphone"></i> Declare Results Publicly
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-success">Results Declared</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Position</th>
                                <th>Candidate</th>
                                <th>University ID</th>
                                <th>Department</th>
                                <th>Hall</th>
                                <th>Votes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jucsu_by_position as $pos_id => $position): ?>
                                <?php if (!empty($position['candidates'])): ?>
                                    <?php foreach ($position['candidates'] as $index => $candidate): ?>
                                        <tr class="<?php echo isset($jucsu_winners[$pos_id]) && 
                                                            $jucsu_winners[$pos_id]['candidate_id'] === $candidate['candidate_id'] ? 
                                                            'table-success' : ''; ?>">
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo count($position['candidates']); ?>" 
                                                    class="align-middle">
                                                    <strong><?php echo htmlspecialchars($position['position_name']); ?></strong>
                                                </td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['university_id']); ?></td>
                                            <td><small><?php echo htmlspecialchars($candidate['department']); ?></small></td>
                                            <td><small><?php echo htmlspecialchars($candidate['hall_name']); ?></small></td>
                                            <td><strong><?php echo $candidate['vote_count']; ?></strong></td>
                                            <td>
                                                <?php if (isset($jucsu_winners[$pos_id]) && 
                                                          $jucsu_winners[$pos_id]['candidate_id'] === $candidate['candidate_id']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-trophy"></i> Winner
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($position['position_name']); ?></strong></td>
                                        <td colspan="6" class="text-muted">No candidates</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="card">
            <div class="card-body">
                <h6>Export Options</h6>
                <button class="btn btn-outline-success" onclick="exportToCSV('jucsu')">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export as CSV
                </button>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Results
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hall Results Summary Tab -->
    <div class="tab-pane fade" id="hall-results">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Hall Union Elections Summary</h5>
                <?php if ($hall_schedule && $hall_schedule['current_phase'] !== 'completed'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="election_type" value="hall">
                        <button type="submit" name="declare_results" class="btn btn-warning"
                                onclick="return confirm('Are you sure you want to declare Hall results? This action cannot be undone.');">
                            <i class="bi bi-megaphone"></i> Declare Results Publicly
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-success">Results Declared</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hall Name</th>
                                <th>Total Candidates</th>
                                <th>Total Votes Cast</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hall_summary as $hall): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($hall['hall_name']); ?></strong></td>
                                    <td><?php echo $hall['total_candidates']; ?></td>
                                    <td><?php echo $hall['total_votes']; ?></td>
                                    <td>
                                        <a href="hall_detailed_results.php?hall=<?php echo urlencode($hall['hall_name']); ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Analytics Tab -->
    <div class="tab-pane fade" id="charts">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Turnout by Election Type</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="turnoutChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Hall-wise Turnout Comparison</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="hallTurnoutChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Top 10 JUCSU Positions by Vote Count</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="topPositionsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Turnout Pie Chart
const turnoutCtx = document.getElementById('turnoutChart').getContext('2d');
new Chart(turnoutCtx, {
    type: 'pie',
    data: {
        labels: ['JUCSU Voted', 'Hall Voted', 'Not Voted'],
        datasets: [{
            data: [
                <?php echo $stats['jucsu_voters']; ?>,
                <?php echo $stats['hall_voters']; ?>,
                <?php echo $stats['total_voters'] - max($stats['jucsu_voters'], $stats['hall_voters']); ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Hall-wise Turnout Bar Chart
const hallTurnoutCtx = document.getElementById('hallTurnoutChart').getContext('2d');
new Chart(hallTurnoutCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($hall_turnout_data, 'hall_name')); ?>,
        datasets: [
            {
                label: 'JUCSU Votes',
                data: <?php echo json_encode(array_column($hall_turnout_data, 'jucsu_votes')); ?>,
                backgroundColor: '#28a745'
            },
            {
                label: 'Hall Votes',
                data: <?php echo json_encode(array_column($hall_turnout_data, 'hall_votes')); ?>,
                backgroundColor: '#ffc107'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        },
        plugins: {
            legend: { position: 'top' }
        }
    }
});

// Top Positions by Vote Count
<?php
$top_positions = [];
foreach ($jucsu_by_position as $pos_id => $position) {
    if (!empty($position['candidates'])) {
        $total_votes = array_sum(array_column($position['candidates'], 'vote_count'));
        $top_positions[] = [
            'name' => $position['position_name'],
            'votes' => $total_votes
        ];
    }
}
usort($top_positions, function($a, $b) { return $b['votes'] - $a['votes']; });
$top_positions = array_slice($top_positions, 0, 10);
?>

const topPositionsCtx = document.getElementById('topPositionsChart').getContext('2d');
new Chart(topPositionsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_positions, 'name')); ?>,
        datasets: [{
            label: 'Total Votes',
            data: <?php echo json_encode(array_column($top_positions, 'votes')); ?>,
            backgroundColor: '#667eea'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        scales: {
            x: { beginAtZero: true }
        }
    }
});

// Export to CSV function
function exportToCSV(type) {
    const filename = type + '_election_results.csv';
    let csv = 'Position,Candidate,University ID,Department,Hall,Votes\n';
    
    <?php foreach ($jucsu_by_position as $position): ?>
        <?php foreach ($position['candidates'] as $candidate): ?>
            csv += '<?php echo addslashes($position['position_name']); ?>,' +
                   '<?php echo addslashes($candidate['full_name']); ?>,' +
                   '<?php echo $candidate['university_id']; ?>,' +
                   '<?php echo addslashes($candidate['department']); ?>,' +
                   '<?php echo addslashes($candidate['hall_name']); ?>,' +
                   '<?php echo $candidate['vote_count']; ?>\n';
        <?php endforeach; ?>
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
}
</script>

<?php include '../includes/footer.php'; ?>