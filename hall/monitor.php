<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../connection.php';
requireRole(['hall_commissioner', 'central_commissioner']);

$page_title = "Live Voting Monitor";
$current_user = getCurrentUser();
$is_central_commissioner = $current_user['role'] === 'central_commissioner';
$hall_name = $is_central_commissioner ? null : $current_user['hall_name'];

// Debug: Log session data (for development only)
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
        LEFT JOIN candidates c ON p.id = c.position_id AND c.status = 'approved'
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN votes v ON p.id = v.position_id
        WHERE p.id = :position_id
        " . ($is_central_commissioner ? "" : "AND (c.hall_name = :hall_name OR c.hall_name IS NULL)") . "
        GROUP BY p.id, c.id
        ORDER BY p.position_order, c.hall_name
    ";
    $stmt = $pdo->prepare($query);
    $params = [':position_id' => $selected_position_id];
    if (!$is_central_commissioner) {
        $params[':hall_name'] = $hall_name;
    }
    $stmt->execute($params);
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

<style>
    /* Sidebar Styles - Consistent with Reports and Verify Voters Pages */
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
        text-decoration: none;
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
    <i class="bi bi-list"></i> Menu
</button>

<!-- Enhanced Sidebar - Consistent with Other Pages -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h5>
            <i class="bi bi-graph-up-arrow text-white-50 me-2"></i>
            <?php echo $is_central_commissioner ? 'Central Monitor' : htmlspecialchars($hall_name) . ' Monitor'; ?>
        </h5>
        <small>Live Voting Dashboard</small>
    </div>
    
    <div class="sidebar-nav">
        <!-- Navigation Category -->
        <div class="category-header">Navigation</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
            </li>
        </ul>

        <!-- Positions Category -->
        <div class="category-header">Election Positions</div>
        <ul class="nav flex-column">
            <?php foreach ($positions as $position): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $position['id'] == $selected_position_id ? 'active' : ''; ?>" 
                       href="?position_id=<?php echo $position['id']; ?>">
                        <i class="bi bi-check2-square"></i>
                        <?php echo htmlspecialchars($position['position_name']); ?>
                        <small class="text-muted d-block">
                            (<?php echo $position['election_type'] === 'jucsu' ? 'JUCSU' : 'Hall'; ?>)
                        </small>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($positions)): ?>
                <li class="nav-item">
                    <span class="nav-link text-muted">
                        <i class="bi bi-exclamation-triangle"></i>
                        No positions available
                    </span>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Quick Actions -->
        <div class="category-header mt-4">Quick Actions</div>
        <ul class="nav flex-column">
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
            <?php elseif (empty($voting_data) || empty(array_filter($voting_data, function($row) { return !empty($row['full_name']); }))): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-info-circle fs-1"></i>
                    <p class="mt-2 mb-0">No approved candidates available for this position</p>
                    <small>Encourage students to register as candidates or check nomination status.</small>
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
                                <?php if ($row['full_name']): // Only show rows with valid candidates ?>
                                    <tr>
                                        <td class="col-candidate"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td class="col-hall"><?php echo htmlspecialchars($row['candidate_hall'] ?? 'N/A'); ?></td>
                                        <td class="col-votes text-right"><?php echo $row['candidate_votes']; ?> (<?php echo $row['vote_count'] ? round(($row['candidate_votes'] / $row['vote_count']) * 100, 2) : 0; ?>%)</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Toggle sidebar functionality
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Smooth scrolling for sidebar links (if needed)
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

setTimeout(() => location.reload(), 30000); // Auto-refresh every 30 seconds
</script>

<?php include '../includes/footer.php'; ?>