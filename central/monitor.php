<?php
// central/monitor.php (Standalone version - can be included in dashboard.php?page=monitor)
// Ensure authentication and role check
require_once '../includes/check_auth.php';
requireRole(['hall_commissioner', 'central_commissioner']);

// Ensure $pdo is available
if (!isset($pdo)) {
    require_once '../connection.php';
}

// Initialize CSRF token (for future form submissions)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Initialize variables
$active_tab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : 'voters';
$error_message = '';
$selected_hall_filter = isset($_GET['hall_filter']) ? sanitizeInput($_GET['hall_filter']) : '';

// Fetch data for all sections (optimized with single queries where possible)
try {
    // 1. Voters (Hall-wise summary + details)
    $voters_stmt = $pdo->prepare("
        SELECT h.hall_name, h.hall_type,
               COUNT(u.id) as total_voters,
               SUM(CASE WHEN u.is_verified = 1 THEN 1 ELSE 0 END) as verified_voters,
               SUM(CASE WHEN u.has_voted_jucsu = 1 THEN 1 ELSE 0 END) as jucsu_voted,
               SUM(CASE WHEN u.has_voted_hall = 1 THEN 1 ELSE 0 END) as hall_voted
        FROM halls h
        LEFT JOIN users u ON h.hall_name = u.hall_name AND u.role = 'voter' AND u.is_active = 1
        GROUP BY h.hall_name, h.hall_type
        ORDER BY h.hall_name
    ");
    $voters_stmt->execute();
    $voters_summary = $voters_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Detailed voters (with hall filter if provided)
    $detailed_voters_query = "
        SELECT DISTINCT u.id, u.university_id, u.full_name, u.department, u.enrollment_year, 
               u.hall_name, u.is_verified, u.has_voted_jucsu, u.has_voted_hall
        FROM users u
        WHERE u.role = 'voter' AND u.is_active = 1
    ";
    $params = [];
    if (!empty($selected_hall_filter)) {
        $detailed_voters_query .= " AND u.hall_name = ?";
        $params[] = $selected_hall_filter;
    }
    $detailed_voters_query .= " ORDER BY u.hall_name, u.full_name";

    $detailed_voters_stmt = $pdo->prepare($detailed_voters_query);
    $detailed_voters_stmt->execute($params);
    $detailed_voters = $detailed_voters_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get halls for dropdown
    $halls_stmt = $pdo->query("SELECT DISTINCT hall_name FROM halls WHERE is_active = 1 ORDER BY hall_name");
    $halls = $halls_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. JUCSU Candidates
    $jucsu_candidates_stmt = $pdo->prepare("
        SELECT c.id, u.full_name, u.university_id, u.department, u.hall_name,
               p.position_name, c.status, c.vote_count, c.nominated_at
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'jucsu'
        ORDER BY p.position_order, c.nominated_at DESC
    ");
    $jucsu_candidates_stmt->execute();
    $jucsu_candidates = $jucsu_candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Hall Candidates (with hall filter if provided)
    $hall_candidates_query = "
        SELECT c.id, u.full_name, u.university_id, u.department,
               c.hall_name, p.position_name, c.status, c.vote_count, c.nominated_at
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'hall'
    ";
    $params = [];
    if (!empty($selected_hall_filter)) {
        $hall_candidates_query .= " AND c.hall_name = ?";
        $params[] = $selected_hall_filter;
    }
    $hall_candidates_query .= " ORDER BY c.hall_name, p.position_order, c.nominated_at DESC";

    $hall_candidates_stmt = $pdo->prepare($hall_candidates_query);
    $hall_candidates_stmt->execute($params);
    $hall_candidates = $hall_candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Voting Updates (Show voter info, with optional hall filter)
    $voting_updates_query = "
        SELECT v.id, v.voter_id, v.candidate_id, v.position_id, v.election_type, v.voter_hall,
               u.full_name as voter_name, u.university_id as voter_university_id,
               p.position_name, v.voted_at
        FROM votes v
        JOIN users u ON v.voter_id = u.id
        JOIN candidates c ON v.candidate_id = c.id
        JOIN positions p ON v.position_id = p.id
        WHERE v.election_type IN ('jucsu', 'hall')
    ";
    $params = [];
    if (!empty($selected_hall_filter)) {
        $voting_updates_query .= " AND v.voter_hall = ?";
        $params[] = $selected_hall_filter;
    }
    $voting_updates_query .= " ORDER BY v.voted_at DESC LIMIT 100";

    $voting_updates_stmt = $pdo->prepare($voting_updates_query);
    $voting_updates_stmt->execute($params);
    $voting_updates = $voting_updates_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
}

$page_title = "Central Election Monitor";

?>

<div class="container-fluid py-3">
    <h2 class="mb-4 text-primary">
        <i class="bi bi-eye"></i> Central Election Monitor
    </h2>
    <p class="text-muted mb-4">Monitor voters, candidates, and live voting for both JUCSU and Hall elections.</p>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="monitorTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab === 'voters' ? 'active' : ''; ?>" id="voters-tab" data-bs-toggle="tab" data-bs-target="#voters" type="button" role="tab">
                <i class="bi bi-people"></i> Voters
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab === 'jucsu-candidates' ? 'active' : ''; ?>" id="jucsu-tab" data-bs-toggle="tab" data-bs-target="#jucsu-candidates" type="button" role="tab">
                <i class="bi bi-person-check"></i> JUCSU Candidates
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab === 'hall-candidates' ? 'active' : ''; ?>" id="hall-tab" data-bs-toggle="tab" data-bs-target="#hall-candidates" type="button" role="tab">
                <i class="bi bi-building"></i> Hall Candidates
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab === 'voting' ? 'active' : ''; ?>" id="voting-tab" data-bs-toggle="tab" data-bs-target="#voting" type="button" role="tab">
                <i class="bi bi-graph-up"></i> Voting Updates
            </button>
        </li>
    </ul>

    <div class="tab-content" id="monitorTabContent">
        <!-- Tab 1: Voters -->
        <div class="tab-pane fade <?php echo $active_tab === 'voters' ? 'show active' : ''; ?>" id="voters" role="tabpanel">
            <?php if (empty($voters_summary)): ?>
                <div class="alert alert-warning">No voter data available.</div>
            <?php else: ?>
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo array_sum(array_column($voters_summary, 'total_voters')); ?></h5>
                                <p class="card-text">Total Voters</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo array_sum(array_column($voters_summary, 'verified_voters')); ?></h5>
                                <p class="card-text">Verified Voters</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-info"><?php echo array_sum(array_column($voters_summary, 'jucsu_voted')); ?></h5>
                                <p class="card-text">JUCSU Voted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-warning"><?php echo array_sum(array_column($voters_summary, 'hall_voted')); ?></h5>
                                <p class="card-text">Hall Voted</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hall-wise Summary Table -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Voter Summary by Hall</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('votersSummaryTable', 'voters-summary.csv')">Export CSV</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="votersSummaryTable">
                                <thead>
                                    <tr>
                                        <th>Hall Name</th>
                                        <th>Type</th>
                                        <th>Total Voters</th>
                                        <th>Verified</th>
                                        <th>JUCSU Voted</th>
                                        <th>Hall Voted</th>
                                        <th>Turnout %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voters_summary as $summary): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($summary['hall_name']); ?></td>
                                            <td><span class="badge bg-<?php echo $summary['hall_type'] === 'male' ? 'primary' : ($summary['hall_type'] === 'female' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($summary['hall_type']); ?></span></td>
                                            <td><strong><?php echo $summary['total_voters']; ?></strong></td>
                                            <td><span class="badge bg-<?php echo $summary['verified_voters'] == $summary['total_voters'] ? 'success' : 'warning'; ?>"><?php echo $summary['verified_voters']; ?></span></td>
                                            <td><?php echo $summary['jucsu_voted']; ?></td>
                                            <td><?php echo $summary['hall_voted']; ?></td>
                                            <td><strong><?php echo $summary['total_voters'] > 0 ? round(($summary['jucsu_voted'] / $summary['total_voters']) * 100, 1) : 0; ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detailed Voters Table with Hall Filter -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Detailed Voter List (<?php echo count($detailed_voters); ?> Total)</h5>
                        <div class="filter-section">
                            <label for="votersHallFilter" class="me-2">Filter by Hall:</label>
                            <select class="form-select d-inline-block w-auto me-2" id="votersHallFilter" onchange="filterVotersByHall(this.value)">
                                <option value="">All Halls</option>
                                <?php foreach ($halls as $hall): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hall_name']); ?>" <?php echo $selected_hall_filter === $hall['hall_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('detailedVotersTable', 'voters-detailed.csv')">Export CSV</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="detailedVotersTable">
                                <thead>
                                    <tr>
                                        <th>Hall</th>
                                        <th>University ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Year</th>
                                        <th>Verified</th>
                                        <th>Voted JUCSU</th>
                                        <th>Voted Hall</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailed_voters as $voter): ?>
                                        <tr data-hall="<?php echo htmlspecialchars(strtolower($voter['hall_name'])); ?>">
                                            <td><strong><?php echo htmlspecialchars($voter['hall_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($voter['university_id']); ?></td>
                                            <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($voter['department']); ?></td>
                                            <td><?php echo $voter['enrollment_year']; ?></td>
                                            <td><span class="badge bg-<?php echo $voter['is_verified'] ? 'success' : 'secondary'; ?>"><?php echo $voter['is_verified'] ? 'Yes' : 'No'; ?></span></td>
                                            <td><span class="badge bg-<?php echo $voter['has_voted_jucsu'] ? 'success' : 'secondary'; ?>"><?php echo $voter['has_voted_jucsu'] ? 'Yes' : 'No'; ?></span></td>
                                            <td><span class="badge bg-<?php echo $voter['has_voted_hall'] ? 'success' : 'secondary'; ?>"><?php echo $voter['has_voted_hall'] ? 'Yes' : 'No'; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 2: JUCSU Candidates -->
        <div class="tab-pane fade <?php echo $active_tab === 'jucsu-candidates' ? 'show active' : ''; ?>" id="jucsu-candidates" role="tabpanel">
            <?php if (empty($jucsu_candidates)): ?>
                <div class="alert alert-warning">No JUCSU candidates registered yet.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">JUCSU Candidates (<?php echo count($jucsu_candidates); ?> Total)</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('jucsuCandidatesTable', 'jucsu-candidates.csv')">Export CSV</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="jucsuCandidatesTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>University ID</th>
                                        <th>Department</th>
                                        <th>Hall</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Votes</th>
                                        <th>Nominated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jucsu_candidates as $candidate): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['university_id']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['department']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['hall_name']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($candidate['position_name']); ?></strong></td>
                                            <td><span class="badge bg-<?php echo $candidate['status'] === 'approved' ? 'success' : ($candidate['status'] === 'rejected' ? 'danger' : ($candidate['status'] === 'pending' ? 'warning' : 'secondary')); ?>"><?php echo ucfirst($candidate['status']); ?></span></td>
                                            <td><strong><?php echo $candidate['vote_count']; ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($candidate['nominated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 3: Hall Candidates -->
        <div class="tab-pane fade <?php echo $active_tab === 'hall-candidates' ? 'show active' : ''; ?>" id="hall-candidates" role="tabpanel">
            <?php if (empty($hall_candidates)): ?>
                <div class="alert alert-warning">No Hall candidates registered yet.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Hall Candidates (<?php echo count($hall_candidates); ?> Total)</h5>
                        <div class="filter-section">
                            <label for="hallCandidatesFilter" class="me-2">Filter by Hall:</label>
                            <select class="form-select d-inline-block w-auto me-2" id="hallCandidatesFilter" onchange="filterHallCandidatesByHall(this.value)">
                                <option value="">All Halls</option>
                                <?php foreach ($halls as $hall): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hall_name']); ?>" <?php echo $selected_hall_filter === $hall['hall_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('hallCandidatesTable', 'hall-candidates.csv')">Export CSV</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="hallCandidatesTable">
                                <thead>
                                    <tr>
                                        <th>Hall</th>
                                        <th>Name</th>
                                        <th>University ID</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Votes</th>
                                        <th>Nominated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hall_candidates as $candidate): ?>
                                        <tr data-hall="<?php echo htmlspecialchars(strtolower($candidate['hall_name'])); ?>">
                                            <td><strong><?php echo htmlspecialchars($candidate['hall_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['university_id']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['department']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($candidate['position_name']); ?></strong></td>
                                            <td><span class="badge bg-<?php echo $candidate['status'] === 'approved' ? 'success' : ($candidate['status'] === 'rejected' ? 'danger' : ($candidate['status'] === 'pending' ? 'warning' : 'secondary')); ?>"><?php echo ucfirst($candidate['status']); ?></span></td>
                                            <td><strong><?php echo $candidate['vote_count']; ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($candidate['nominated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 4: Voting Updates -->
        <div class="tab-pane fade <?php echo $active_tab === 'voting' ? 'show active' : ''; ?>" id="voting" role="tabpanel">
            <?php if (empty($voting_updates)): ?>
                <div class="alert alert-info">No votes cast yet.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Voting Updates (Last 100 Votes)</h5>
                        <div class="filter-section">
                            <label for="votingHallFilter" class="me-2">Filter by Hall:</label>
                            <select class="form-select d-inline-block w-auto me-2" id="votingHallFilter" onchange="filterVotingByHall(this.value)">
                                <option value="">All Halls</option>
                                <?php foreach ($halls as $hall): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hall_name']); ?>" <?php echo $selected_hall_filter === $hall['hall_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('votingUpdatesTable', 'voting-updates.csv')">Export CSV</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="votingUpdatesTable">
                                <thead>
                                    <tr>
                                        <th>Election Type</th>
                                        <th>Hall</th>
                                        <th>Voter University ID</th>
                                        <th>Voter Name</th>
                                        <th>Position</th>
                                        <th>Voted At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voting_updates as $update): ?>
                                        <tr data-hall="<?php echo htmlspecialchars(strtolower($update['voter_hall'])); ?>">
                                            <td><span class="badge bg-<?php echo $update['election_type'] === 'jucsu' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($update['election_type']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($update['voter_hall']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($update['voter_university_id']); ?></td>
                                            <td><?php echo htmlspecialchars($update['voter_name']); ?></td>
                                            <td><?php echo htmlspecialchars($update['position_name']); ?></td>
                                            <td><strong><?php echo date('M d, H:i', strtotime($update['voted_at'])); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<!-- jQuery (Required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
// Initialize DataTables with search, paging, sorting for all tables
$(document).ready(function() {
    // Common DataTables config
    const dtConfig = {
        paging: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        responsive: true,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries per page"
        },
        dom: 'Bfrtip',
        buttons: ['csv', 'excel']
    };

    // Voters Summary (no paging needed for summary)
    $('#votersSummaryTable').DataTable({
        paging: false,
        searching: false,
        ordering: true,
        info: false
    });

    // Detailed Voters
    $('#detailedVotersTable').DataTable(Object.assign({}, dtConfig, { 
        pageLength: 25,
        order: [[0, 'asc']]  // Default sort by Hall
    }));

    // JUCSU Candidates
    $('#jucsuCandidatesTable').DataTable(Object.assign({}, dtConfig, { pageLength: 10 }));

    // Hall Candidates
    $('#hallCandidatesTable').DataTable(Object.assign({}, dtConfig, { pageLength: 25 }));

    // Voting Updates
    $('#votingUpdatesTable').DataTable(Object.assign({}, dtConfig, { 
        pageLength: 25,
        order: [[5, 'desc']]  // Default sort by Voted At (newest first)
    }));
});

// Hall Filter Functions (Client-side for instant response)
function filterVotersByHall(selectedHall) {
    const table = $('#detailedVotersTable').DataTable();
    if (selectedHall === '') {
        table.search('').draw();
    } else {
        table.search(selectedHall).draw();
    }
    updateUrlFilter('voters', selectedHall);
}

function filterHallCandidatesByHall(selectedHall) {
    const table = $('#hallCandidatesTable').DataTable();
    if (selectedHall === '') {
        table.search('').draw();
    } else {
        table.search(selectedHall).draw();
    }
    updateUrlFilter('hall-candidates', selectedHall);
}

function filterVotingByHall(selectedHall) {
    const table = $('#votingUpdatesTable').DataTable();
    if (selectedHall === '') {
        table.search('').draw();
    } else {
        table.search(selectedHall).draw();
    }
    updateUrlFilter('voting', selectedHall);
}

// Update URL with filter (for bookmarking/refresh)
function updateUrlFilter(tab, hall) {
    const url = new URL(window.location);
    if (hall) {
        url.searchParams.set('tab', tab);
        url.searchParams.set('hall_filter', hall);
    } else {
        url.searchParams.set('tab', tab);
        url.searchParams.delete('hall_filter');
    }
    window.history.replaceState({}, '', url);
}

// CSV Export Function (Client-side)
function exportTable(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText.trim().replace(/"/g, '""');
            if (cols[j].querySelector('.badge')) {
                cellText = cols[j].querySelector('.badge').innerText.trim();
            }
            row.push('"' + cellText + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>
</body>
</html>