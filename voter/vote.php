<?php
// voter/vote.php
$page_title = "Cast Your Vote";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
$voter_hall = $current_user['hall_name'];

// Handle AJAX request for candidates
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_candidates') {
    header('Content-Type: application/json');
    
    $position_id = $_GET['position_id'] ?? null;
    $election_type = $_GET['election_type'] ?? 'jucsu';
    
    if (!$position_id) {
        echo json_encode(['error' => false, 'candidates' => []]);
        exit;
    }
    
    try {
        if ($election_type === 'jucsu') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.user_id, c.manifesto, c.photo_path,
                       u.full_name, u.university_id, u.department, u.hall_name as candidate_hall
                FROM candidates c
                JOIN users u ON c.user_id = u.id
                WHERE c.position_id = ? AND c.election_type = 'jucsu' AND c.status = 'approved'
                ORDER BY u.full_name
            ");
            $stmt->execute([$position_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT c.id, c.user_id, c.manifesto, c.photo_path,
                       u.full_name, u.university_id, u.department, u.hall_name as candidate_hall
                FROM candidates c
                JOIN users u ON c.user_id = u.id
                WHERE c.position_id = ? AND c.election_type = 'hall' AND c.hall_name = ? AND c.status = 'approved'
                ORDER BY u.full_name
            ");
            $stmt->execute([$position_id, $current_user['hall_name']]);
        }
        
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['error' => false, 'candidates' => $candidates]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => false, 'candidates' => []]);
    }
    exit;
}

$success = '';
$error = '';

// Get election type from URL
$election_type = $_GET['type'] ?? 'jucsu';

if (!in_array($election_type, ['jucsu', 'hall'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    $position_id = $_POST['position_id'];
    $candidate_id = $_POST['candidate_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Check if already voted for this position
        $check_stmt = $pdo->prepare("SELECT id FROM votes WHERE voter_id = ? AND position_id = ? AND election_type = ?");
        $check_stmt->execute([$current_user['id'], $position_id, $election_type]);
        
        if ($check_stmt->fetch()) {
            throw new Exception("You have already voted for this position!");
        }
        
        // Verify candidate
        $verify_stmt = $pdo->prepare("
            SELECT id FROM candidates 
            WHERE id = ? AND position_id = ? AND status = 'approved' AND election_type = ?
            " . ($election_type === 'hall' ? "AND hall_name = ?" : "")
        );
        
        $params = [$candidate_id, $position_id, $election_type];
        if ($election_type === 'hall') {
            $params[] = $voter_hall;
        }
        
        $verify_stmt->execute($params);
        
        if (!$verify_stmt->fetch()) {
            throw new Exception("Invalid candidate selection!");
        }
        
        // Insert vote
        $vote_stmt = $pdo->prepare("
            INSERT INTO votes (voter_id, candidate_id, position_id, election_type, voter_hall, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $vote_stmt->execute([
            $current_user['id'],
            $candidate_id,
            $position_id,
            $election_type,
            $voter_hall,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Check if all positions voted
        $count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT position_id) FROM votes WHERE voter_id = ? AND election_type = ?");
        $count_stmt->execute([$current_user['id'], $election_type]);
        $voted_positions_count = $count_stmt->fetchColumn();
        
        $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_type = ? AND is_active = 1");
        $total_stmt->execute([$election_type]);
        $total_positions = $total_stmt->fetchColumn();
        
        if ($voted_positions_count >= $total_positions) {
            $update_stmt = $pdo->prepare("UPDATE users SET has_voted_" . $election_type . " = 1 WHERE id = ?");
            $update_stmt->execute([$current_user['id']]);
        }
        
        $pdo->commit();
        $success = "Vote recorded successfully!";
        
        // Refresh user data
        $current_user = getCurrentUser();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch positions
$positions_stmt = $pdo->prepare("
    SELECT id, position_name, position_order 
    FROM positions 
    WHERE election_type = ? AND is_active = 1
    ORDER BY position_order
");
$positions_stmt->execute([$election_type]);
$positions = $positions_stmt->fetchAll();

// Get voted positions
$voted_stmt = $pdo->prepare("SELECT DISTINCT position_id FROM votes WHERE voter_id = ? AND election_type = ?");
$voted_stmt->execute([$current_user['id'], $election_type]);
$voted_positions = $voted_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get voting statistics
$total_voters_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_verified = 1 AND role = 'voter'");
$total_voters_stmt->execute();
$total_voters = $total_voters_stmt->fetchColumn();

$voted_count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_type = ?");
$voted_count_stmt->execute([$election_type]);
$voters_participated = $voted_count_stmt->fetchColumn();

$participation_rate = $total_voters > 0 ? round(($voters_participated / $total_voters) * 100) : 0;

$total_positions = count($positions);
$voted_positions_count = count($voted_positions);
$pending_positions = $total_positions - $voted_positions_count;
$voting_percentage = $total_positions > 0 ? round(($voted_positions_count / $total_positions) * 100) : 0;

include '../includes/header.php';
?>

<style>
.voting-container {
    display: flex;
    gap: 20px;
    min-height: 80vh;
}

.left-section {
    flex: 0 0 280px;
    position: sticky;
    top: 20px;
    height: fit-content;
}

.right-section {
    flex: 1;
}

.position-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.position-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 20px;
    font-weight: 600;
}

.position-link {
    display: block;
    padding: 15px 20px;
    color: #333;
    text-decoration: none;
    border-left: 4px solid transparent;
    transition: all 0.3s;
    border-bottom: 1px solid #f0f0f0;
    position: relative;
}

.position-link:hover {
    background: #f8f9fa;
    border-left-color: #28a745;
}

.position-link.active {
    background: #e8f5e9;
    border-left-color: #28a745;
    font-weight: 600;
}

.position-link.voted {
    opacity: 0.6;
}

.voted-check {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #28a745;
    font-size: 1.2rem;
}

/* Dashboard View */
.dashboard-view {
    display: block;
}

.dashboard-view.hidden {
    display: none;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    backdrop-filter: blur(5px);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stat-card.green {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.stat-card.orange {
    background: linear-gradient(135deg, #ff6b6b, #ff8787);
    color: white;
}

.stat-card.purple {
    background: linear-gradient(135deg, #6c5ce7, #a29bfe);
    color: white;
}

.stat-icon {
    font-size: 3.5rem;
    margin-bottom: 15px;
    opacity: 0.9;
}

.stat-number {
    font-size: 2.8rem;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
}

.stat-label {
    font-size: 1.1rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.voting-status-card {
    background: rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 30px;
    transition: transform 0.3s, box-shadow 0.3s;
}

.voting-status-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
}

.voting-status-card h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 25px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.progress-ring {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 25px;
}

.progress-ring__circle-bg {
    fill: none;
    stroke: #e9ecef;
    stroke-width: 12;
    opacity: 0.5;
}

.progress-ring__circle-fg {
    fill: none;
    stroke: linear-gradient(135deg, #28a745, #20c997);
    stroke-width: 12;
    stroke-linecap: round;
    transform: rotate(-90deg);
    transform-origin: center;
    transition: stroke-dasharray 0.5s ease;
}

.progress-ring__text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2.2rem;
    font-weight: 700;
    color: #2c3e50;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.progress-ring__text small {
    display: block;
    font-size: 1rem;
    font-weight: 500;
    color: #666;
}

.progress-details {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    backdrop-filter: blur(5px);
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    transition: transform 0.3s;
}

.detail-item:hover {
    transform: scale(1.1);
}

.detail-item i {
    font-size: 1.5rem;
}

.detail-item.voted i {
    color: #28a745;
}

.detail-item.pending i {
    color: #ff6b6b;
}

/* Candidates View */
.candidates-view {
    display: none;
}

.candidates-view.active {
    display: block;
}

.position-title-bar {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 20px 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
}

.position-title-bar h3 {
    margin: 0;
    font-size: 1.8rem;
}

.candidates-grid {
    display: grid;
    gap: 25px;
}

.candidate-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    backdrop-filter: blur(5px);
    transition: all 0.3s;
    border: 2px solid transparent;
}

.candidate-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border-color: #28a745;
}

.candidate-content {
    display: flex;
    gap: 25px;
    align-items: start;
}

.candidate-photo-section {
    flex-shrink: 0;
}

.candidate-photo {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    object-fit: cover;
    border: 4px solid #28a745;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.candidate-photo-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    background: linear-gradient(135deg, #28a745, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    font-weight: bold;
    border: 4px solid #28a745;
}

.candidate-info {
    flex: 1;
}

.candidate-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
}

.candidate-details {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.8;
}

.candidate-details i {
    color: #28a745;
    margin-right: 5px;
}

.manifesto-preview {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #28a745;
    margin-top: 15px;
    max-height: 100px;
    overflow: hidden;
    backdrop-filter: blur(5px);
}

.vote-button {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.vote-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.vote-button:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.voted-badge {
    background: #28a745;
    color: white;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    backdrop-filter: blur(5px);
}

.empty-ballot-box {
    margin-bottom: 30px;
    opacity: 0.7;
}

.empty-title {
    font-size: 1.8rem;
    color: #2c3e50;
    margin-bottom: 15px;
    font-weight: 600;
}

.empty-text {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 0;
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>
                        <?php if ($election_type === 'jucsu'): ?>
                            <i class="bi bi-building"></i> JUCSU Election
                        <?php else: ?>
                            <i class="bi bi-house-door"></i> <?php echo htmlspecialchars($voter_hall); ?> Election
                        <?php endif; ?>
                    </h2>
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

    <div class="voting-container">
        <!-- Left Section - Positions -->
        <div class="left-section">
            <div class="position-sidebar">
                <div class="position-header">
                    <i class="bi bi-list-check"></i> Positions (<?php echo count($positions); ?>)
                </div>
                <?php foreach ($positions as $position): ?>
                    <?php $is_voted = in_array($position['id'], $voted_positions); ?>
                    <a href="?type=<?php echo $election_type; ?>&position_id=<?php echo $position['id']; ?>" 
                       class="position-link <?php echo $is_voted ? 'voted' : ''; ?>" 
                       data-position-id="<?php echo $position['id']; ?>"
                       data-position-name="<?php echo htmlspecialchars($position['position_name']); ?>">
                        <?php echo htmlspecialchars($position['position_name']); ?>
                        <?php if ($is_voted): ?>
                            <span class="voted-check">✓</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Section - Dashboard or Candidates -->
        <div class="right-section">
            <!-- Dashboard View -->
            <div id="dashboardView" class="dashboard-view">
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon">✓</div>
                        <div class="stat-number"><?php echo $participation_rate; ?>%</div>
                        <div class="stat-label">Participation Rate</div>
                        <small><?php echo $voters_participated; ?> voters</small>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-number"><?php echo number_format($total_voters); ?></div>
                        <div class="stat-label">Total Voters</div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-icon"><i class="bi bi-list-check"></i></div>
                        <div class="stat-number"><?php echo $total_positions; ?></div>
                        <div class="stat-label">Total Positions</div>
                    </div>
                </div>

                <div class="voting-status-card">
                    <h3>Voting Progress</h3>
                    <div class="progress-ring">
                        <svg class="progress-ring__circle-bg" width="200" height="200">
                            <circle cx="100" cy="100" r="90" />
                        </svg>
                        <svg class="progress-ring__circle" width="200" height="200">
                            <circle class="progress-ring__circle-fg" cx="100" cy="100" r="90" />
                        </svg>
                        <div class="progress-ring__text">
                            <?php echo $voting_percentage; ?><br><small>%</small>
                        </div>
                    </div>
                    <div class="progress-details">
                        <div class="detail-item voted">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Voted: <?php echo $voted_positions_count; ?></span>
                        </div>
                        <div class="detail-item pending">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <span>Pending: <?php echo $pending_positions; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Candidates View -->
            <div id="candidatesView" class="candidates-view">
                <div class="position-title-bar">
                    <h3 id="selectedPositionName">Select a position to view candidates</h3>
                </div>
                <div id="candidatesContainer" class="candidates-grid">
                    <!-- Candidates will be loaded here dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const totalPositions = <?php echo $total_positions; ?>;
const votedPositions = <?php echo $voted_positions_count; ?>;
const votingPercentage = <?php echo $voting_percentage; ?>;
const circumference = 2 * Math.PI * 90;
const circle = document.querySelector('.progress-ring__circle-fg');

circle.style.strokeDasharray = `${circumference} ${circumference}`;
circle.style.strokeDashoffset = circumference - (votingPercentage / 100) * circumference;

// Position click handler
document.querySelectorAll('.position-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        const positionId = this.dataset.positionId;
        const positionName = this.dataset.positionName;
        const isVoted = this.classList.contains('voted');
        
        // Update active state
        document.querySelectorAll('.position-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show candidates view
        document.getElementById('dashboardView').classList.add('hidden');
        document.getElementById('candidatesView').classList.add('active');
        document.getElementById('selectedPositionName').textContent = positionName;
        
        // Load candidates
        loadCandidates(positionId, isVoted);
    });
});

function loadCandidates(positionId, isVoted) {
    const container = document.getElementById('candidatesContainer');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div></div>';
    
    fetch(`?ajax=get_candidates&position_id=${positionId}&election_type=<?php echo $election_type; ?>`)
        .then(response => response.json())
        .then(data => {
            const candidates = data.candidates || data;
            
            if (!Array.isArray(candidates) || candidates.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-ballot-box">
                            <svg width="150" height="150" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="40" y="60" width="120" height="100" rx="5" stroke="#ccc" stroke-width="4" fill="#f8f9fa"/>
                                <rect x="60" y="40" width="80" height="30" rx="3" fill="#e9ecef" stroke="#ccc" stroke-width="2"/>
                                <line x1="70" y1="90" x2="130" y2="90" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                                <line x1="70" y1="110" x2="110" y2="110" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                                <line x1="70" y1="130" x2="120" y2="130" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h4 class="empty-title">No Candidates Available</h4>
                        <p class="empty-text">There are currently no approved candidates for this position.</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            candidates.forEach(candidate => {
                const card = createCandidateCard(candidate, positionId, isVoted);
                container.appendChild(card);
            });
        })
        .catch(error => {
            console.error('Error loading candidates:', error);
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-ballot-box">
                        <svg width="150" height="150" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="40" y="60" width="120" height="100" rx="5" stroke="#ccc" stroke-width="4" fill="#f8f9fa"/>
                            <rect x="60" y="40" width="80" height="30" rx="3" fill="#e9ecef" stroke="#ccc" stroke-width="2"/>
                            <line x1="70" y1="90" x2="130" y2="90" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                            <line x1="70" y1="110" x2="110" y2="110" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                            <line x1="70" y1="130" x2="120" y2="130" stroke="#ddd" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h4 class="empty-title">No Candidates Available</h4>
                    <p class="empty-text">There are currently no approved candidates for this position.</p>
                </div>
            `;
        });
}

function createCandidateCard(candidate, positionId, isVoted) {
    const card = document.createElement('div');
    card.className = 'candidate-card';
    
    const photoHtml = candidate.photo_path 
        ? `<img src="../${candidate.photo_path}" class="candidate-photo" alt="${candidate.full_name}">`
        : `<div class="candidate-photo-placeholder"><i class="bi bi-person-circle"></i></div>`;
    
    const voteButtonHtml = isVoted 
        ? '<span class="voted-badge"><i class="bi bi-check-circle"></i> Already Voted</span>'
        : `<form method="POST" onsubmit="return confirm('Confirm vote for ${candidate.full_name}?');">
            <input type="hidden" name="position_id" value="${positionId}">
            <input type="hidden" name="candidate_id" value="${candidate.id}">
            <button type="submit" class="vote-button">
                <i class="bi bi-hand-thumbs-up"></i> Vote
            </button>
           </form>`;
    
    card.innerHTML = `
        <div class="candidate-content">
            <div class="candidate-photo-section">
                ${photoHtml}
            </div>
            <div class="candidate-info">
                <h4 class="candidate-name">${candidate.full_name}</h4>
                <div class="candidate-details">
                    <div><i class="bi bi-person-badge"></i> <strong>ID:</strong> ${candidate.university_id}</div>
                    <div><i class="bi bi-building"></i> <strong>Department:</strong> ${candidate.department}</div>
                    <div><i class="bi bi-house"></i> <strong>Hall:</strong> ${candidate.candidate_hall}</div>
                </div>
                ${candidate.manifesto ? `<div class="manifesto-preview"><small>${candidate.manifesto.substring(0, 150)}...</small></div>` : ''}
            </div>
            <div class="vote-action">
                ${voteButtonHtml}
            </div>
        </div>
    `;
    
    return card;
}
</script>

<?php include '../includes/footer.php'; ?>