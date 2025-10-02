
<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../connection.php';
requireRole(['hall_commissioner', 'central_commissioner']);

$page_title = "Live Voting Monitor";
$current_user = getCurrentUser();
$is_central_commissioner = $current_user['role'] === 'central_commissioner';
$hall_name = $is_central_commissioner ? null : $current_user['hall_name'];

// Debug: Log session data
file_put_contents(__DIR__ . '/../debug.txt', "Monitor.php Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Fetch positions for sidebar
$positions_query = "
    SELECT id, position_name, election_type
    FROM positions
    WHERE election_type = :election_type
    ORDER BY election_type, position_order
";
$stmt = $pdo->prepare($positions_query);
$stmt->execute([':election_type' => $is_central_commissioner ? '%' : 'hall']);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch voting summary for selected position
$selected_position_id = $_GET['position_id'] ?? null;
$voting_data = [];
$selected_position = null;
if ($selected_position_id) {
    $query = "
        SELECT 
            p.position_name, p.election_type, p.id as position_id,
            COALESCE(COUNT(v.id), 0) as vote_count,
            c.id as candidate_id, c.user_id, c.hall_name as candidate_hall,
            u.full_name, COALESCE(c.vote_count, 0) as candidate_votes
        FROM positions p
        LEFT JOIN candidates c ON p.id = c.position_id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN votes v ON p.id = v.position_id
        WHERE p.id = :position_id
        " . ($is_central_commissioner ? "" : "AND (c.hall_name = :hall_name OR c.hall_name IS NULL)") . "
        GROUP BY p.id, c.id
        ORDER BY p.position_order, c.hall_name
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':position_id' => $selected_position_id,
        ':hall_name' => $hall_name
    ]);
    $voting_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $selected_position = $voting_data[0]['position_name'] ?? null;
}

// Fetch total eligible voters
$eligible_voters_query = "
    SELECT COUNT(*) as total_voters
    FROM users
    WHERE role = 'voter' AND is_verified = 1 AND is_active = 1
    " . ($is_central_commissioner ? "" : "AND hall_name = ?") . "
";
$stmt = $pdo->prepare($eligible_voters_query);
$stmt->execute($is_central_commissioner ? [] : [$hall_name]);
$total_voters = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters'];

// Fetch total votes cast
$total_votes_query = "
    SELECT COUNT(DISTINCT voter_id) as total_voters_voted
    FROM votes
    WHERE election_type = :election_type
    " . ($is_central_commissioner ? "" : "AND voter_hall = :hall_name") . "
";
$stmt = $pdo->prepare($total_votes_query);
$stmt->execute([
    ':election_type' => $is_central_commissioner ? '%' : 'hall',
    ':hall_name' => $hall_name
]);
$total_voters_voted = $stmt->fetch(PDO::FETCH_ASSOC)['total_voters_voted'];

$voting_percentage = $total_voters > 0 ? ($total_voters_voted / $total_voters) * 100 : 0;

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
            <div class="position-sticky pt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                    <?php echo $is_central_commissioner ? 'All Positions' : 'Hall Positions'; ?>
                </h6>
                <ul class="nav flex-column">
                    <?php foreach ($positions as $position): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $position['id'] == $selected_position_id ? 'active' : ''; ?>" 
                               href="?position_id=<?php echo $position['id']; ?>">
                                <i class="bi bi-check2-square"></i>
                                <?php echo htmlspecialchars($position['position_name']); ?>
                                <small class="text-muted">
                                    (<?php echo $position['election_type'] === 'jucsu' ? 'JUCSU' : 'Hall'; ?>)
                                </small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($positions)): ?>
                        <li class="nav-item">
                            <span class="nav-link text-muted">No positions available</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Live Voting Monitor <?php echo $hall_name ? ' - ' . htmlspecialchars($hall_name) : ''; ?></h2>
                    <p class="text-muted">Last updated: <?php echo date('d M Y, H:i:s'); ?> (Refreshes every 30 seconds)</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Voting Progress</h5>
                </div>
                <div class="card-body">
                    <p><strong>Total Eligible Voters:</strong> <?php echo $total_voters; ?></p>
                    <p><strong>Voters Who Voted:</strong> <?php echo $total_voters_voted; ?></p>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $voting_percentage; ?>%;" 
                             aria-valuenow="<?php echo $voting_percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($voting_percentage, 2); ?>%
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        Vote Counts <?php echo $selected_position ? 'for ' . htmlspecialchars($selected_position) : ''; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!$selected_position): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-info-circle fs-1"></i>
                            <p class="mt-2 mb-0">Select a position from the sidebar</p>
                            <small>Choose a position to view candidate vote counts.</small>
                        </div>
                    <?php elseif (empty($voting_data)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-info-circle fs-1"></i>
                            <p class="mt-2 mb-0">No candidates available for this position</p>
                            <small>Encourage students to register as candidates.</small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="col-candidate">Candidate</th>
                                        <th class="col-hall">Hall</th>
                                        <th class="col-votes text-right">Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voting_data as $row): ?>
                                        <tr>
                                            <td class="col-candidate"><?php echo $row['full_name'] ? htmlspecialchars($row['full_name']) : 'No Candidate'; ?></td>
                                            <td class="col-hall"><?php echo htmlspecialchars($row['candidate_hall'] ?? 'N/A'); ?></td>
                                            <td class="col-votes text-right"><?php echo $row['candidate_votes']; ?> (<?php echo $row['vote_count'] ? round(($row['candidate_votes'] / $row['vote_count']) * 100, 2) : 0; ?>%)</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
setTimeout(() => location.reload(), 30000); // Auto-refresh every 30 seconds
</script>

<?php include '../includes/footer.php'; ?>
```