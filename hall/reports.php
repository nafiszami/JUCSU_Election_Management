```php
<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../connection.php';
requireRole(['hall_commissioner', 'central_commissioner']);

$page_title = "Election Reports";
$current_user = getCurrentUser();
$is_central_commissioner = $current_user['role'] === 'central_commissioner';
$hall_name = $is_central_commissioner ? null : $current_user['hall_name'];

// Debug: Log session data
file_put_contents(__DIR__ . '/../debug.txt', "Reports.php Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="election_report_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Generated: ' . date('d M Y H:i:s')]);
    fputcsv($output, []);

    // Candidate Report
    fputcsv($output, ['Candidate Report']);
    fputcsv($output, ['Position', 'Election Type', 'Hall', 'Candidate Name', 'University ID', 'Votes', 'Status']);
    $stmt = $pdo->prepare("
        SELECT p.position_name, p.election_type, c.hall_name, u.full_name, u.university_id, 
               COALESCE(c.vote_count, 0) as vote_count, c.status
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE (? IS NULL OR c.hall_name = ?)
        ORDER BY p.election_type, c.hall_name, p.position_order
    ");
    $stmt->execute([$hall_name, $hall_name]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['position_name'], $row['election_type'], $row['hall_name'] ?? 'N/A',
            $row['full_name'], $row['university_id'], $row['vote_count'], $row['status']
        ]);
    }

    // Voting Statistics
    fputcsv($output, []);
    fputcsv($output, ['Voting Statistics']);
    fputcsv($output, ['Metric', 'Value']);
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters FROM users WHERE role = 'voter' AND is_verified = 1 AND (? IS NULL OR hall_name = ?)");
    $stmt->execute([$hall_name, $hall_name]);
    fputcsv($output, ['Total Eligible Voters', $stmt->fetch(PDO::FETCH_ASSOC)['total_voters']]);
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT voter_id) as total_voters_voted FROM votes WHERE (? IS NULL OR voter_hall = ?)");
    $stmt->execute([$hall_name, $hall_name]);
    $total_voters_voted = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters_voted'];
    fputcsv($output, ['Total Voters Voted', $total_voters_voted]);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters FROM users WHERE role = 'voter' AND is_verified = 1 AND (? IS NULL OR hall_name = ?)");
    $stmt->execute([$hall_name, $hall_name]);
    $total_voters = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters'];
    $turnout = $total_voters > 0 ? round(($total_voters_voted / $total_voters) * 100, 2) : 0;
    fputcsv($output, ['Voter Turnout (%)', $turnout]);

    // Audit Logs
    fputcsv($output, []);
    fputcsv($output, ['Audit Logs']);
    fputcsv($output, ['Timestamp', 'User ID', 'Action', 'Table', 'Record ID', 'Details']);
    $stmt = $pdo->prepare("
        SELECT a.created_at, u.university_id, a.action, a.table_name, a.record_id, a.new_values
        FROM audit_logs a
        JOIN users u ON a.user_id = u.id
        WHERE (? IS NULL OR u.hall_name = ?)
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$hall_name, $hall_name]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['created_at'], $row['university_id'], $row['action'], 
            $row['table_name'], $row['record_id'], $row['new_values']
        ]);
    }

    fclose($output);
    exit;
}

// Fetch candidate report
$stmt = $pdo->prepare("
    SELECT p.position_name, p.election_type, c.hall_name, u.full_name, u.university_id, 
           COALESCE(c.vote_count, 0) as vote_count, c.status
    FROM candidates c
    JOIN users u ON c.user_id = u.id
    JOIN positions p ON c.position_id = p.id
    WHERE (? IS NULL OR c.hall_name = ?)
    ORDER BY p.election_type, c.hall_name, p.position_order
");
$stmt->execute([$hall_name, $hall_name]);
$candidate_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch voting statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_voters FROM users WHERE role = 'voter' AND is_verified = 1 AND (? IS NULL OR hall_name = ?)");
$stmt->execute([$hall_name, $hall_name]);
$total_voters = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT voter_id) as total_voters_voted FROM votes WHERE (? IS NULL OR voter_hall = ?)");
$stmt->execute([$hall_name, $hall_name]);
$total_voters_voted = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters_voted'];

$turnout_percentage = $total_voters > 0 ? ($total_voters_voted / $total_voters) * 100 : 0;

// Fetch audit logs
$stmt = $pdo->prepare("
    SELECT a.created_at, u.university_id, a.action, a.table_name, a.record_id, a.new_values
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE (? IS NULL OR u.hall_name = ?)
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute([$hall_name, $hall_name]);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Election Reports <?php echo $hall_name ? ' - ' . htmlspecialchars($hall_name) : ''; ?></h2>
    <a href="?export=csv" class="btn btn-primary mb-3">Export as CSV</a>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Candidate Report</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
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
                                <td><?php echo htmlspecialchars($row['election_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['hall_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['university_id']); ?></td>
                                <td><?php echo $row['vote_count']; ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Voting Statistics</h5>
        </div>
        <div class="card-body">
            <p><strong>Total Eligible Voters:</strong> <?php echo $total_voters; ?></p>
            <p><strong>Voters Who Voted:</strong> <?php echo $total_voters_voted; ?></p>
            <p><strong>Voter Turnout:</strong> <?php echo round($turnout_percentage, 2); ?>%</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Recent Audit Logs (Last 50)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
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
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['record_id']); ?></td>
                                <td><?php echo htmlspecialchars($log['new_values']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
```