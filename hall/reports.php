<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../connection.php';
requireRole(['hall_commissioner', 'central_commissioner']);

$page_title = "Election Reports";
$current_user = getCurrentUser();
$is_central_commissioner = $current_user['role'] === 'central_commissioner';
$hall_name = $is_central_commissioner ? null : $current_user['hall_name'];

// Remove debug logging in production
// file_put_contents(__DIR__ . '/../debug.txt', "Reports.php Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Handle filters (new: add election_type filter)
$election_type_filter = isset($_GET['election_type']) ? $_GET['election_type'] : null;
if (!in_array($election_type_filter, ['jucsu', 'hall', null])) {
    $election_type_filter = null;
}

// Helper function to add hall filter to queries (flexible for different tables/columns)
function addHallFilter($query, $params, $hall_name, $column, $allow_null = false) {
    if ($hall_name !== null) {
        $condition = $allow_null ? "($column = ? OR $column IS NULL)" : "$column = ?";
        $query .= " AND " . $condition;
        $params[] = $hall_name;
    }
    return [$query, $params];
}

// Helper function to add election type filter
function addElectionFilter($query, $params, $election_type_filter, $table_alias = 'p') {
    if ($election_type_filter !== null) {
        $query .= " AND {$table_alias}.election_type = ?";
        $params[] = $election_type_filter;
    }
    return [$query, $params];
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="election_report_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Use BOM for UTF-8 to handle special characters properly in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['Report Generated: ' . date('d M Y H:i:s')]);
    fputcsv($output, []);

    // Candidate Report
    fputcsv($output, ['Candidate Report']);
    fputcsv($output, ['Position', 'Election Type', 'Hall', 'Candidate Name', 'University ID', 'Votes', 'Status']);
    
    $query = "
        SELECT p.position_name, p.election_type, c.hall_name, u.full_name, u.university_id, 
               COALESCE(c.vote_count, 0) as vote_count, c.status
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE 1=1
    ";
    $params = [];
    list($query, $params) = addElectionFilter($query, $params, $election_type_filter, 'p');
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'c.hall_name', true);
    $query .= " ORDER BY p.election_type, c.hall_name, p.position_order";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['position_name'], $row['election_type'], $row['hall_name'] ?? 'N/A',
            $row['full_name'], $row['university_id'], $row['vote_count'], $row['status']
        ]);
    }

    // Voting Statistics (improved with more metrics)
    fputcsv($output, []);
    fputcsv($output, ['Voting Statistics']);
    fputcsv($output, ['Metric', 'Value']);
    
    $query = "SELECT COUNT(*) as total_voters FROM users WHERE role = 'voter' AND is_verified = 1";
    $params = [];
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $total_voters = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters'];
    fputcsv($output, ['Total Eligible Voters', $total_voters]);
    
    $query = "SELECT COUNT(DISTINCT voter_id) as total_voters_voted FROM votes WHERE 1=1";
    $params = [];
    list($query, $params) = addElectionFilter($query, $params, $election_type_filter, 'votes');
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'voter_hall', false);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $total_voters_voted = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters_voted'];
    fputcsv($output, ['Total Voters Voted', $total_voters_voted]);
    
    $turnout = $total_voters > 0 ? round(($total_voters_voted / $total_voters) * 100, 2) : 0;
    fputcsv($output, ['Voter Turnout (%)', $turnout]);

    // Voters List
    fputcsv($output, []);
    fputcsv($output, ['Voters List']);
    fputcsv($output, ['Full Name', 'University ID', 'Hall', 'Email', 'Enrollment Year', 'Verification Status']);
    $query = "
        SELECT full_name, university_id, hall_name, email, enrollment_year, is_verified
        FROM users
        WHERE role = 'voter'
    ";
    $params = [];
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
    $query .= " ORDER BY hall_name, full_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['full_name'], $row['university_id'], $row['hall_name'] ?? 'N/A',
            $row['email'], $row['enrollment_year'], $row['is_verified'] ? 'Yes' : 'No'
        ]);
    }

    // Approved Voters
    fputcsv($output, []);
    fputcsv($output, ['Approved Voters']);
    fputcsv($output, ['Full Name', 'University ID', 'Hall', 'Email', 'Enrollment Year']);
    $query = "
        SELECT full_name, university_id, hall_name, email, enrollment_year
        FROM users
        WHERE role = 'voter' AND is_verified = 1
    ";
    $params = [];
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
    $query .= " ORDER BY hall_name, full_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['full_name'], $row['university_id'], $row['hall_name'] ?? 'N/A',
            $row['email'], $row['enrollment_year']
        ]);
    }

    // Rejected Voters
    fputcsv($output, []);
    fputcsv($output, ['Rejected Voters']);
    fputcsv($output, ['Full Name', 'University ID', 'Hall', 'Email', 'Enrollment Year']);
    $query = "
        SELECT full_name, university_id, hall_name, email, enrollment_year
        FROM users
        WHERE role = 'voter' AND is_verified = 0
    ";
    $params = [];
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
    $query .= " ORDER BY hall_name, full_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['full_name'], $row['university_id'], $row['hall_name'] ?? 'N/A',
            $row['email'], $row['enrollment_year']
        ]);
    }

    // Audit Logs
    fputcsv($output, []);
    fputcsv($output, ['Audit Logs']);
    fputcsv($output, ['Timestamp', 'User ID', 'Action', 'Table', 'Record ID', 'Details']);
    $query = "
        SELECT a.created_at, u.university_id, a.action, a.table_name, a.record_id, a.new_values
        FROM audit_logs a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    list($query, $params) = addHallFilter($query, $params, $hall_name, 'u.hall_name', false);
    $query .= " ORDER BY a.created_at DESC LIMIT 100";  // Increased limit for CSV
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['created_at'], $row['university_id'], $row['action'], 
            $row['table_name'], $row['record_id'], $row['new_values']
        ]);
    }

    fclose($output);
    exit;
}

// Fetch data with filters (refactored)

// Candidate report
$query = "
    SELECT p.position_name, p.election_type, c.hall_name, u.full_name, u.university_id, 
           COALESCE(c.vote_count, 0) as vote_count, c.status
    FROM candidates c
    JOIN users u ON c.user_id = u.id
    JOIN positions p ON c.position_id = p.id
    WHERE 1=1
";
$params = [];
list($query, $params) = addElectionFilter($query, $params, $election_type_filter, 'p');
list($query, $params) = addHallFilter($query, $params, $hall_name, 'c.hall_name', true);
$query .= " ORDER BY p.election_type, c.hall_name, p.position_order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidate_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Voting statistics
$query = "SELECT COUNT(*) as total_voters FROM users WHERE role = 'voter' AND is_verified = 1";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$total_voters = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters'];

$query = "SELECT COUNT(DISTINCT voter_id) as total_voters_voted FROM votes WHERE 1=1";
$params = [];
list($query, $params) = addElectionFilter($query, $params, $election_type_filter, 'votes');
list($query, $params) = addHallFilter($query, $params, $hall_name, 'voter_hall', false);
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$total_voters_voted = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters_voted'];

$turnout_percentage = $total_voters > 0 ? ($total_voters_voted / $total_voters) * 100 : 0;

// New: Votes per position (fixed: use CASE for hall filter to include positions with 0 votes)
$query = "
    SELECT p.position_name, p.election_type, 
           SUM(CASE WHEN v.voter_hall = ? OR ? IS NULL THEN 1 ELSE 0 END) as total_votes
    FROM positions p
    LEFT JOIN votes v ON p.id = v.position_id
    WHERE 1=1
";
$params = [$hall_name, $hall_name];
list($query, $params) = addElectionFilter($query, $params, $election_type_filter, 'p');
$query .= " GROUP BY p.id ORDER BY p.position_order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$votes_per_position = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Voters list (with pagination - fixed: append LIMIT/OFFSET directly to avoid PDO quoting issue)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$query = "
    SELECT full_name, university_id, hall_name, email, enrollment_year, is_verified
    FROM users
    WHERE role = 'voter'
";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
$query .= " ORDER BY hall_name, full_name LIMIT {$per_page} OFFSET {$offset}";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$voters_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total for pagination
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'voter'";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$total_voters_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_voters_count / $per_page);

// Approved voters
$query = "
    SELECT full_name, university_id, hall_name, email, enrollment_year
    FROM users
    WHERE role = 'voter' AND is_verified = 1
";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
$query .= " ORDER BY hall_name, full_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$approved_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rejected voters
$query = "
    SELECT full_name, university_id, hall_name, email, enrollment_year
    FROM users
    WHERE role = 'voter' AND is_verified = 0
";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'hall_name', false);
$query .= " ORDER BY hall_name, full_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rejected_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Audit logs (increased limit)
$query = "
    SELECT a.created_at, u.university_id, a.action, a.table_name, a.record_id, a.new_values
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];
list($query, $params) = addHallFilter($query, $params, $hall_name, 'u.hall_name', false);
$query .= " ORDER BY a.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
    /* Sidebar Styles - Enhanced for Reports Navigation */
    .sidebar {
        min-width: 280px;
        max-width: 280px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
        border-right: 1px solid #dee2e6;
        overflow-y: auto;
        transition: all 0.3s ease;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }

    .sidebar-header {
        padding: 1.5rem 1rem;
        background: #28a745;
        color: white;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }

    .sidebar-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .sidebar-header small {
        opacity: 0.9;
        display: block;
        margin-top: 0.25rem;
    }

    .sidebar-nav {
        padding: 0;
    }

    .sidebar .nav-item {
        margin-bottom: 0.5rem;
    }

    .sidebar .nav-link {
        color: #495057;
        padding: 1rem 1.25rem;
        border-left: 3px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background-color: #28a745;
        color: white;
        border-left-color: #20c997;
        transform: translateX(5px);
    }

    .sidebar .nav-link i {
        margin-right: 0.75rem;
        width: 20px;
        font-size: 1.1rem;
        opacity: 0.8;
    }

    /* Category Headers */
    .category-header {
        padding: 0.75rem 1.25rem;
        background: #e9ecef;
        color: #6c757d;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #dee2e6;
    }

    .main-content {
        margin-left: 280px;
        min-height: 100vh;
        padding: 2rem 1rem;
        transition: all 0.3s ease;
    }

    .section {
        scroll-margin-top: 80px; /* Offset for fixed header if any */
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
            padding: 1rem;
        }

        .toggle-sidebar {
            display: block !important;
        }
    }

    .toggle-sidebar {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: #28a745;
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .toggle-sidebar:hover {
        background: #20c997;
    }
</style>

<!-- Toggle Button for Mobile -->
<button class="btn toggle-sidebar" onclick="toggleSidebar()">
    <i class="bi bi-list"></i> Reports Menu
</button>

<!-- Enhanced Sidebar for Reports Categories -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h5>
            <i class="bi bi-bar-chart-line text-white-50 me-2"></i>
            <?php echo $is_central_commissioner ? 'Central Reports' : htmlspecialchars($hall_name) . ' Reports'; ?>
        </h5>
        <small>Election Analytics Dashboard</small>
    </div>
    
    <div class="sidebar-nav">
        <!-- Candidates Category -->
        <div class="category-header">Candidates</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="#candidate-report">
                    <i class="bi bi-people-fill"></i>
                    Candidate Overview
                </a>
            </li>
        </ul>

        <!-- Voting Category -->
        <div class="category-header">Voting</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="#voting-statistics">
                    <i class="bi bi-graph-up"></i>
                    Voting Statistics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#votes-per-position">
                    <i class="bi bi-pie-chart"></i>
                    Votes per Position
                </a>
            </li>
        </ul>

        <!-- Voters Category -->
        <div class="category-header">Voters</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="#voters-list">
                    <i class="bi bi-list-ul"></i>
                    All Voters List
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#approved-voters">
                    <i class="bi bi-check-circle-fill"></i>
                    Approved Voters
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#rejected-voters">
                    <i class="bi bi-x-circle-fill"></i>
                    Rejected Voters
                </a>
            </li>
        </ul>

        <!-- Audit Category -->
        <div class="category-header">Audit & Logs</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="#audit-logs">
                    <i class="bi bi-journal-text"></i>
                    Audit Logs
                </a>
            </li>
        </ul>

        <!-- Quick Actions -->
        <div class="category-header mt-4">Quick Actions</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="?export=csv<?php echo $election_type_filter ? '&election_type=' . $election_type_filter : ''; ?>">
                    <i class="bi bi-download"></i>
                    Export CSV
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid">
        <!-- Filters (Global for all sections) -->
        <div class="row mb-4">
            <div class="col-12">
                <form method="GET" class="d-flex align-items-center gap-3">
                    <select name="election_type" class="form-select" style="width: auto; min-width: 200px;">
                        <option value="">All Election Types</option>
                        <option value="jucsu" <?php echo $election_type_filter === 'jucsu' ? 'selected' : ''; ?>>JUCSU</option>
                        <option value="hall" <?php echo $election_type_filter === 'hall' ? 'selected' : ''; ?>>Hall</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <div class="ms-auto">
                        <a href="?export=csv<?php echo $election_type_filter ? '&election_type=' . $election_type_filter : ''; ?>" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Export Full Report (CSV)
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Candidates Section -->
        <section id="candidate-report" class="section">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Candidate Report</h5>
                    <span class="badge bg-info"><?php echo count($candidate_report); ?> Candidates</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="candidateTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Position</th>
                                    <th>Election Type</th>
                                    <th>Hall</th>
                                    <th>Candidate Name</th>
                                    <th>University ID</th>
                                    <th>Votes</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidate_report as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                                        <td><span class="badge bg-<?php echo $row['election_type'] === 'jucsu' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($row['election_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['hall_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['university_id']); ?></td>
                                        <td><span class="fw-bold"><?php echo $row['vote_count']; ?></span></td>
                                        <td><span class="badge bg-<?php echo $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning'); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Voting Statistics Section -->
        <section id="voting-statistics" class="section">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Voting Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Eligible Voters:</span>
                                <span class="fw-bold text-primary"><?php echo number_format($total_voters); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Voters Who Voted:</span>
                                <span class="fw-bold text-success"><?php echo number_format($total_voters_voted); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-0">
                                <span>Voter Turnout:</span>
                                <span class="fw-bold text-info"><?php echo round($turnout_percentage, 2); ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Voter Turnout Chart</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="turnoutChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Votes Per Position Section -->
        <section id="votes-per-position" class="section">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Votes Per Position</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="votesPositionTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Position</th>
                                    <th>Election Type</th>
                                    <th>Total Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($votes_per_position as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                                        <td><span class="badge bg-<?php echo $row['election_type'] === 'jucsu' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($row['election_type']); ?></span></td>
                                        <td><span class="fw-bold"><?php echo $row['total_votes']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Voters List Section -->
        <section id="voters-list" class="section">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Voters List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="votersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>University ID</th>
                                    <th>Hall</th>
                                    <th>Email</th>
                                    <th>Enrollment Year</th>
                                    <th>Verification Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voters_list as $voter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['university_id']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['hall_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['enrollment_year']); ?></td>
                                        <td><span class="badge bg-<?php echo $voter['is_verified'] ? 'success' : 'warning'; ?>"><?php echo $voter['is_verified'] ? 'Verified' : 'Pending'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Voters pagination" class="d-flex justify-content-center mt-3">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $election_type_filter ? '&election_type=' . urlencode($election_type_filter) : ''; ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?><?php echo $election_type_filter ? '&election_type=' . urlencode($election_type_filter) : ''; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $election_type_filter ? '&election_type=' . urlencode($election_type_filter) : ''; ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Approved Voters Section -->
        <section id="approved-voters" class="section">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Approved Voters <span class="badge bg-success"><?php echo count($approved_voters); ?> Total</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="approvedTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>University ID</th>
                                    <th>Hall</th>
                                    <th>Email</th>
                                    <th>Enrollment Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_voters as $voter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['university_id']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['hall_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['enrollment_year']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Rejected Voters Section -->
        <section id="rejected-voters" class="section">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Rejected Voters <span class="badge bg-danger"><?php echo count($rejected_voters); ?> Total</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="rejectedTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>University ID</th>
                                    <th>Hall</th>
                                    <th>Email</th>
                                    <th>Enrollment Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_voters as $voter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['university_id']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['hall_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['enrollment_year']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Audit Logs Section -->
        <section id="audit-logs" class="section">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Audit Logs (Last 100) <span class="badge bg-secondary"><?php echo count($audit_logs); ?> Entries</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="auditTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User ID</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($log['university_id']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                        <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                        <td><?php echo htmlspecialchars($log['record_id']); ?></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($log['new_values']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- Include DataTables and Chart.js -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Smooth scrolling for sidebar links
document.querySelectorAll('.sidebar .nav-link[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
        // Close sidebar on mobile after click
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('show');
        }
    });
});

$(document).ready(function() {
    // Initialize DataTables for interactive tables with search, sort, and pagination
    $('#candidateTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "search": "Search Candidates:",
            "lengthMenu": "Show _MENU_ entries per page"
        }
    });

    $('#votesPositionTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "language": {
            "search": "Search Positions:"
        }
    });

    $('#votersTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "language": {
            "search": "Search Voters:",
            "lengthMenu": "Show _MENU_ entries per page"
        }
    });

    $('#approvedTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "language": {
            "search": "Search Approved Voters:"
        }
    });

    $('#rejectedTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "language": {
            "search": "Search Rejected Voters:"
        }
    });

    $('#auditTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "pageLength": 25,
        "language": {
            "search": "Search Audit Logs:",
            "lengthMenu": "Show _MENU_ entries per page"
        },
        "order": [[0, "desc"]]  // Default sort by timestamp descending
    });

    // Voter Turnout Chart
    var ctx = document.getElementById('turnoutChart').getContext('2d');
    var turnoutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Voted', 'Not Voted'],
            datasets: [{
                data: [<?php echo $total_voters_voted; ?>, <?php echo $total_voters - $total_voters_voted; ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                title: {
                    display: true,
                    text: 'Voter Turnout Overview',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            }
        }
    });

    // Active sidebar link based on current scroll position
    let sections = document.querySelectorAll('section[id]');
    let sidebarLinks = document.querySelectorAll('.sidebar .nav-link[href^="#"]');
    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (scrollY >= sectionTop - 200) {
                current = section.getAttribute('id');
            }
        });

        sidebarLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>