<?php
// voter/candidates.php
$page_title = "Approved Candidates";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();

// Get election type and position filter from URL
$election_type = $_GET['type'] ?? 'jucsu';
$selected_position = $_GET['position'] ?? '';

if (!in_array($election_type, ['jucsu', 'hall'])) {
    $election_type = 'jucsu';
}

try {
    // Fetch JUCSU candidates
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.full_name, u.university_id, u.department, p.position_name, c.photo_path, u.hall_name, c.manifesto
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'jucsu' AND c.status = 'approved'
        ORDER BY p.position_order, u.full_name
    ");
    $stmt->execute();
    $jucsu_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ALL JUCSU positions (whether they have candidates or not)
    $stmt = $pdo->prepare("
        SELECT position_name 
        FROM positions 
        WHERE election_type = 'jucsu' AND is_active = 1
        ORDER BY position_order
    ");
    $stmt->execute();
    $jucsu_positions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Hall candidates for user's hall
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.full_name, u.university_id, u.department, p.position_name, c.photo_path, u.hall_name, c.manifesto
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'hall' AND c.status = 'approved' AND c.hall_name = ?
        ORDER BY p.position_order, u.full_name
    ");
    $stmt->execute([$current_user['hall_name']]);
    $hall_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ALL hall positions (whether they have candidates or not)
    $stmt = $pdo->prepare("
        SELECT position_name 
        FROM positions 
        WHERE election_type = 'hall' AND is_active = 1
        ORDER BY position_order
    ");
    $stmt->execute();
    $hall_positions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get statistics
    $jucsu_count = count($jucsu_candidates);
    $hall_count = count($hall_candidates);
    $jucsu_positions_count = count($jucsu_positions);
    $hall_positions_count = count($hall_positions);

} catch (PDOException $e) {
    $error = "Error loading candidates: " . htmlspecialchars($e->getMessage());
}

// Filter candidates based on selection
$current_candidates = $election_type === 'jucsu' ? $jucsu_candidates : $hall_candidates;
$current_positions = $election_type === 'jucsu' ? $jucsu_positions : $hall_positions;

if ($selected_position) {
    $current_candidates = array_filter($current_candidates, fn($c) => $c['position_name'] === $selected_position);
}

include '../includes/header.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #e3f2fd 0%, #f1f8e9 100%);
        min-height: 100vh;
    }

    .candidates-header {
        background: linear-gradient(135deg, #1976d2 0%, #43a047 100%);
        color: white;
        padding: 50px 0;
        border-radius: 0 0 30px 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .candidates-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .candidates-header h1 {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 15px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        position: relative;
        z-index: 1;
    }

    .candidates-header p {
        font-size: 1.3rem;
        opacity: 0.95;
        position: relative;
        z-index: 1;
    }

    .election-toggle {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin: 30px 0;
        flex-wrap: wrap;
    }

    .toggle-btn {
        background: white;
        border: 3px solid transparent;
        padding: 20px 40px;
        border-radius: 15px;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        text-decoration: none;
        color: #333;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 250px;
        justify-content: center;
    }

    .toggle-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .toggle-btn.active {
        background: linear-gradient(135deg, #1976d2, #43a047);
        color: white;
        border-color: #1976d2;
    }

    .toggle-btn .count {
        background: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .stats-bar {
        background: rgba(255,255,255,0.95);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 35px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
    }

    .stat-item {
        text-align: center;
        padding: 20px;
        background: linear-gradient(135deg, #f5f5f5, #fafafa);
        border-radius: 15px;
        transition: transform 0.3s;
    }

    .stat-item:hover {
        transform: scale(1.05);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: #1976d2;
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 1rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .positions-sidebar {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }

    .positions-sidebar h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1976d2;
        padding-bottom: 15px;
        border-bottom: 3px solid #1976d2;
    }

    .position-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .position-item {
        margin-bottom: 8px;
    }

    .position-link {
        display: block;
        padding: 15px 20px;
        background: #f8f9fa;
        border-radius: 12px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s;
        border-left: 4px solid transparent;
        font-weight: 500;
    }

    .position-link:hover {
        background: #e3f2fd;
        border-left-color: #1976d2;
        transform: translateX(5px);
    }

    .position-link.active {
        background: linear-gradient(135deg, #1976d2, #43a047);
        color: white;
        border-left-color: #0d47a1;
        font-weight: 700;
    }

    .position-link.all-positions {
        background: linear-gradient(135deg, #ff6b6b, #feca57);
        color: white;
        font-weight: 700;
    }

    .candidates-grid {
        display: grid;
        gap: 25px;
    }

    .candidate-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .candidate-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, #1976d2, #43a047);
    }

    .candidate-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        border-color: #1976d2;
    }

    .candidate-content {
        display: flex;
        gap: 30px;
        align-items: start;
    }

    .candidate-photo {
        width: 140px;
        height: 140px;
        border-radius: 15px;
        object-fit: cover;
        border: 4px solid #1976d2;
        box-shadow: 0 6px 20px rgba(25, 118, 210, 0.3);
        flex-shrink: 0;
    }

    .candidate-photo-placeholder {
        width: 140px;
        height: 140px;
        border-radius: 15px;
        background: linear-gradient(135deg, #1976d2, #43a047);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3.5rem;
        border: 4px solid #1976d2;
        flex-shrink: 0;
    }

    .candidate-info {
        flex: 1;
    }

    .candidate-name {
        font-size: 2rem;
        font-weight: 800;
        color: #1976d2;
        margin-bottom: 8px;
    }

    .candidate-position {
        display: inline-block;
        background: linear-gradient(135deg, #43a047, #66bb6a);
        color: white;
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .candidate-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 15px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #666;
        font-size: 1rem;
    }

    .detail-item i {
        color: #1976d2;
        font-size: 1.2rem;
    }

    .manifesto-section {
        background: linear-gradient(135deg, #f8f9fa, #e3f2fd);
        padding: 20px;
        border-radius: 15px;
        margin-top: 20px;
        border-left: 4px solid #1976d2;
    }

    .manifesto-section h5 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1976d2;
        margin-bottom: 10px;
    }

    .manifesto-text {
        color: #555;
        line-height: 1.8;
        font-size: 1rem;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }

    .empty-icon {
        font-size: 5rem;
        color: #ccc;
        margin-bottom: 20px;
    }

    .empty-title {
        font-size: 2rem;
        font-weight: 700;
        color: #666;
        margin-bottom: 10px;
    }

    .empty-text {
        font-size: 1.2rem;
        color: #999;
    }

    .back-button {
        margin-top: 40px;
    }

    @media (max-width: 768px) {
        .candidate-content {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .candidates-header h1 {
            font-size: 2rem;
        }

        .toggle-btn {
            min-width: 100%;
        }
    }
</style>

<div class="candidates-header">
    <div class="container text-center">
        <h1><i class="bi bi-people-fill"></i> Approved Candidates</h1>
        <p>Browse all approved candidates for JUCSU and Hall Union elections</p>
    </div>
</div>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php else: ?>
        
        <!-- Election Type Toggle -->
        <div class="election-toggle">
            <a href="?type=jucsu#mainContent" class="toggle-btn <?php echo $election_type === 'jucsu' ? 'active' : ''; ?>">
                <i class="bi bi-building"></i>
                <span>JUCSU Elections</span>
                <span class="count"><?php echo $jucsu_count; ?> Candidates</span>
            </a>
            <a href="?type=hall#mainContent" class="toggle-btn <?php echo $election_type === 'hall' ? 'active' : ''; ?>">
                <i class="bi bi-house-door"></i>
                <span>Hall Union (<?php echo htmlspecialchars($current_user['hall_name']); ?>)</span>
                <span class="count"><?php echo $hall_count; ?> Candidates</span>
            </a>
        </div>

        <!-- Statistics Bar -->
        <div class="stats-bar">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $election_type === 'jucsu' ? $jucsu_count : $hall_count; ?></div>
                    <div class="stat-label">Total Candidates</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $election_type === 'jucsu' ? $jucsu_positions_count : $hall_positions_count; ?></div>
                    <div class="stat-label">Positions Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $election_type === 'jucsu' ? 'Central' : 'Hall'; ?></div>
                    <div class="stat-label">Election Type</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row" id="mainContent">
            <!-- Positions Sidebar -->
            <div class="col-12 col-lg-3 mb-4">
                <div class="positions-sidebar">
                    <h4><i class="bi bi-list-check"></i> Positions</h4>
                    <ul class="position-list">
                        <?php foreach ($current_positions as $position): ?>
                            <li class="position-item">
                                <a href="?type=<?php echo $election_type; ?>&position=<?php echo urlencode($position); ?>#mainContent" 
                                   class="position-link <?php echo $selected_position === $position ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($position); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Candidates Grid -->
            <div class="col-12 col-lg-9">
                <?php if (empty($selected_position)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-hand-index"></i></div>
                        <h3 class="empty-title">Select a Position</h3>
                        <p class="empty-text">
                            Click on any position from the left sidebar to view candidates for that position.
                        </p>
                    </div>
                <?php elseif (empty($current_candidates)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                        <h3 class="empty-title">No Approved Candidates</h3>
                        <p class="empty-text">
                            There are currently no approved candidates for the position of "<strong><?php echo htmlspecialchars($selected_position); ?></strong>".
                        </p>
                        <p class="empty-text mt-3" style="font-size: 1rem; color: #999;">
                            Candidates may still be pending approval or no one has applied for this position yet.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="candidates-grid">
                        <?php foreach ($current_candidates as $candidate): ?>
                            <div class="candidate-card">
                                <div class="candidate-content">
                                    <div>
                                        <?php if ($candidate['photo_path']): ?>
                                            <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                 class="candidate-photo" 
                                                 alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                        <?php else: ?>
                                            <div class="candidate-photo-placeholder">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="candidate-info">
                                        <h3 class="candidate-name"><?php echo htmlspecialchars($candidate['full_name']); ?></h3>
                                        <span class="candidate-position">
                                            <i class="bi bi-award"></i> <?php echo htmlspecialchars($candidate['position_name']); ?>
                                        </span>
                                        
                                        <div class="candidate-details">
                                            <div class="detail-item">
                                                <i class="bi bi-person-badge"></i>
                                                <span><strong>ID:</strong> <?php echo htmlspecialchars($candidate['university_id']); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-building"></i>
                                                <span><strong>Dept:</strong> <?php echo htmlspecialchars($candidate['department'] ?: 'N/A'); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-house"></i>
                                                <span><strong>Hall:</strong> <?php echo htmlspecialchars($candidate['hall_name']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($candidate['manifesto'])): ?>
                                            <div class="manifesto-section">
                                                <h5><i class="bi bi-file-text"></i> Manifesto</h5>
                                                <p class="manifesto-text"><?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="back-button text-center">
            <a href="dashboard.php" class="btn btn-lg btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>