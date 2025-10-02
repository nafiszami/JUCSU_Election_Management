<?php
// voter/vote.php
$page_title = "Cast Your Vote";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
$voter_hall = $current_user['hall_name'];

$success = '';
$error = '';

// Get election type from URL or default to selection page
$election_type = $_GET['type'] ?? null;

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_votes'])) {
    $election_type = $_POST['election_type'];
    $votes = $_POST['votes'] ?? [];
    
    // Check if already voted for this election type
    if (($election_type === 'jucsu' && $current_user['has_voted_jucsu']) ||
        ($election_type === 'hall' && $current_user['has_voted_hall'])) {
        $error = "You have already voted in this election!";
    } elseif (empty($votes)) {
        $error = "Please select at least one candidate!";
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($votes as $position_id => $candidate_id) {
                if (!empty($candidate_id)) {
                    // Verify candidate is approved and belongs to correct election/hall
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
                    
                    if ($verify_stmt->fetch()) {
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
                    }
                }
            }
            
            // Update user's voting status (trigger will handle this, but we can do it manually too)
            if ($election_type === 'jucsu') {
                $update_stmt = $pdo->prepare("UPDATE users SET has_voted_jucsu = 1 WHERE id = ?");
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET has_voted_hall = 1 WHERE id = ?");
            }
            $update_stmt->execute([$current_user['id']]);
            
            $pdo->commit();
            
            $success = "Your votes have been recorded successfully!";
            
            // Refresh user data
            $current_user = getCurrentUser();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error recording votes: " . $e->getMessage();
        }
    }
}

// If no election type selected, show selection page
if (!$election_type) {
    include '../includes/header.php';
    ?>
    
    <div class="row">
        <div class="col-12 mb-4">
            <h2>Cast Your Vote</h2>
            <p class="text-muted">Select which election you want to vote in</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100 <?php echo $current_user['has_voted_jucsu'] ? 'border-success' : ''; ?>">
                <div class="card-body text-center p-4">
                    <?php if ($current_user['has_voted_jucsu']): ?>
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">JUCSU Election</h3>
                        <p class="text-success mb-3">You have already voted</p>
                        <a href="?type=jucsu" class="btn btn-outline-success">View Candidates</a>
                    <?php else: ?>
                        <i class="bi bi-building text-primary" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">JUCSU Election</h3>
                        <p class="text-muted mb-3">Central Student Union - 25 Positions</p>
                        <a href="?type=jucsu" class="btn btn-primary btn-lg">
                            <i class="bi bi-vote-fill"></i> Vote Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 <?php echo $current_user['has_voted_hall'] ? 'border-success' : ''; ?>">
                <div class="card-body text-center p-4">
                    <?php if ($current_user['has_voted_hall']): ?>
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">Hall Union Election</h3>
                        <p class="text-success mb-3">You have already voted</p>
                        <a href="?type=hall" class="btn btn-outline-success">View Candidates</a>
                    <?php else: ?>
                        <i class="bi bi-house-door text-warning" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">Hall Union Election</h3>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($voter_hall); ?> - 15 Positions</p>
                        <a href="?type=hall" class="btn btn-warning btn-lg">
                            <i class="bi bi-vote-fill"></i> Vote Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    include '../includes/footer.php';
    exit;
}

// Fetch positions for selected election type
$positions_stmt = $pdo->prepare("
    SELECT id, position_name, position_order 
    FROM positions 
    WHERE election_type = ? AND is_active = 1
    ORDER BY position_order
");
$positions_stmt->execute([$election_type]);
$positions = $positions_stmt->fetchAll();

// Fetch candidates for selected election type
if ($election_type === 'jucsu') {
    $candidates_stmt = $pdo->prepare("
        SELECT c.*, u.full_name, u.university_id, u.department, u.hall_name as candidate_hall,
               p.position_name,
               proposer.full_name as proposer_name,
               seconder.full_name as seconder_name
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        JOIN users proposer ON c.proposer_id = proposer.id
        JOIN users seconder ON c.seconder_id = seconder.id
        WHERE c.election_type = 'jucsu' AND c.status = 'approved'
        ORDER BY p.position_order, u.full_name
    ");
    $candidates_stmt->execute();
} else {
    $candidates_stmt = $pdo->prepare("
        SELECT c.*, u.full_name, u.university_id, u.department, u.hall_name as candidate_hall,
               p.position_name,
               proposer.full_name as proposer_name,
               seconder.full_name as seconder_name
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        JOIN users proposer ON c.proposer_id = proposer.id
        JOIN users seconder ON c.seconder_id = seconder.id
        WHERE c.election_type = 'hall' AND c.hall_name = ? AND c.status = 'approved'
        ORDER BY p.position_order, u.full_name
    ");
    $candidates_stmt->execute([$voter_hall]);
}

$all_candidates = $candidates_stmt->fetchAll();

// Group candidates by position
$candidates_by_position = [];
foreach ($all_candidates as $candidate) {
    $candidates_by_position[$candidate['position_id']][] = $candidate;
}

// Check if already voted
$has_voted = ($election_type === 'jucsu' && $current_user['has_voted_jucsu']) ||
             ($election_type === 'hall' && $current_user['has_voted_hall']);

include '../includes/header.php';
?>

<style>
.position-sidebar {
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}
.position-link {
    display: block;
    padding: 0.75rem 1rem;
    color: #333;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.3s;
}
.position-link:hover {
    background: #f8f9fa;
    border-left-color: #28a745;
}
.position-link.active {
    background: #e8f5e9;
    border-left-color: #28a745;
    font-weight: bold;
}
.candidate-card {
    border: 2px solid #e0e0e0;
    transition: all 0.3s;
    cursor: pointer;
}
.candidate-card:hover {
    border-color: #28a745;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.candidate-card.selected {
    border-color: #28a745;
    background: #e8f5e9;
}
.candidate-photo {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 10px;
}
.manifesto-preview {
    max-height: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2>
                    <?php if ($election_type === 'jucsu'): ?>
                        <i class="bi bi-building text-primary"></i> JUCSU Election
                    <?php else: ?>
                        <i class="bi bi-house-door text-warning"></i> Hall Union Election
                    <?php endif; ?>
                </h2>
                <p class="text-muted mb-0">
                    <?php echo $election_type === 'hall' ? htmlspecialchars($voter_hall) : 'University-wide'; ?>
                </p>
            </div>
            <div>
                <a href="vote.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
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

<?php if ($has_voted): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> You have already voted in this election. You can view candidates but cannot vote again.
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left Sidebar - Positions -->
    <div class="col-md-3">
        <div class="card position-sidebar">
            <div class="card-header bg-<?php echo $election_type === 'jucsu' ? 'primary' : 'warning'; ?> text-white">
                <h6 class="mb-0">Positions</h6>
            </div>
            <div class="card-body p-0">
                <?php foreach ($positions as $position): ?>
                    <a href="#position-<?php echo $position['id']; ?>" 
                       class="position-link" 
                       data-position="<?php echo $position['id']; ?>">
                        <?php echo htmlspecialchars($position['position_name']); ?>
                        <?php if (isset($candidates_by_position[$position['id']])): ?>
                            <span class="badge bg-secondary float-end">
                                <?php echo count($candidates_by_position[$position['id']]); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Content - Candidates -->
    <div class="col-md-9">
        <form method="POST" id="votingForm">
            <input type="hidden" name="election_type" value="<?php echo $election_type; ?>">
            
            <?php foreach ($positions as $position): ?>
                <div id="position-<?php echo $position['id']; ?>" class="position-section mb-5">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h4 class="mb-0"><?php echo htmlspecialchars($position['position_name']); ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($candidates_by_position[$position['id']])): ?>
                                <div class="row">
                                    <?php foreach ($candidates_by_position[$position['id']] as $candidate): ?>
                                        <div class="col-12 mb-3">
                                            <div class="card candidate-card" 
                                                 data-position="<?php echo $position['id']; ?>"
                                                 data-candidate="<?php echo $candidate['id']; ?>">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-auto">
                                                            <?php if ($candidate['photo_path']): ?>
                                                                <img src="/JUCSU_Election_Management/<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                                     class="candidate-photo" 
                                                                     alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                                            <?php else: ?>
                                                                <div class="candidate-photo bg-secondary d-flex align-items-center justify-content-center text-white">
                                                                    <i class="bi bi-person fs-1"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($candidate['full_name']); ?></h5>
                                                            <p class="text-muted mb-2">
                                                                <small>
                                                                    <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($candidate['university_id']); ?> |
                                                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($candidate['department']); ?> |
                                                                    <i class="bi bi-house"></i> <?php echo htmlspecialchars($candidate['candidate_hall']); ?>
                                                                </small>
                                                            </p>
                                                            
                                                            <div class="mb-2">
                                                                <small>
                                                                    <strong>Proposed by:</strong> <?php echo htmlspecialchars($candidate['proposer_name']); ?> | 
                                                                    <strong>Seconded by:</strong> <?php echo htmlspecialchars($candidate['seconder_name']); ?>
                                                                </small>
                                                            </div>
                                                            
                                                            <?php if ($candidate['manifesto']): ?>
                                                                <div class="manifesto-preview">
                                                                    <small class="text-muted">
                                                                        <?php echo nl2br(htmlspecialchars(substr($candidate['manifesto'], 0, 200))); ?>
                                                                        <?php if (strlen($candidate['manifesto']) > 200): ?>...<?php endif; ?>
                                                                    </small>
                                                                </div>
                                                                <button type="button" class="btn btn-link btn-sm p-0" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#manifestoModal<?php echo $candidate['id']; ?>">
                                                                    Read Full Manifesto
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-auto d-flex align-items-center">
                                                            <?php if (!$has_voted): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input vote-radio" 
                                                                           type="radio" 
                                                                           name="votes[<?php echo $position['id']; ?>]" 
                                                                           value="<?php echo $candidate['id']; ?>"
                                                                           id="vote_<?php echo $candidate['id']; ?>">
                                                                    <label class="form-check-label" for="vote_<?php echo $candidate['id']; ?>">
                                                                        <strong>Vote</strong>
                                                                    </label>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Manifesto Modal -->
                                        <div class="modal fade" id="manifestoModal<?php echo $candidate['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Manifesto - <?php echo htmlspecialchars($candidate['full_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center mb-3">
                                                            <?php if ($candidate['photo_path']): ?>
                                                                <img src="/JUCSU_Election_Management/<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                                     style="width: 150px; height: 150px; object-fit: cover; border-radius: 10px;"
                                                                     alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                                            <?php endif; ?>
                                                            <h5 class="mt-2"><?php echo htmlspecialchars($candidate['full_name']); ?></h5>
                                                            <p class="text-muted">
                                                                <?php echo htmlspecialchars($candidate['position_name']); ?>
                                                            </p>
                                                        </div>
                                                        <hr>
                                                        <div style="white-space: pre-wrap;">
                                                            <?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p>No candidates for this position</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (!$has_voted): ?>
                <div class="card bg-light sticky-bottom">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">Review Your Votes</h5>
                                <small class="text-muted">Selected: <span id="voteCount">0</span> / <?php echo count($positions); ?> positions</small>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="submit_votes" class="btn btn-success btn-lg" 
                                        onclick="return confirm('Are you sure you want to submit your votes? This action cannot be undone.');">
                                    <i class="bi bi-check-circle"></i> Submit Votes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
// Update vote count
function updateVoteCount() {
    const count = document.querySelectorAll('.vote-radio:checked').length;
    document.getElementById('voteCount').textContent = count;
}

// Highlight selected candidate card
document.querySelectorAll('.vote-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const positionId = this.name.match(/\[(\d+)\]/)[1];
        
        // Remove selected class from all cards in this position
        document.querySelectorAll(`.candidate-card[data-position="${positionId}"]`).forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to chosen card
        if (this.checked) {
            this.closest('.candidate-card').classList.add('selected');
        }
        
        updateVoteCount();
    });
});

// Smooth scroll for position links
document.querySelectorAll('.position-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Update active state
        document.querySelectorAll('.position-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
    });
});

// Update active position on scroll
window.addEventListener('scroll', function() {
    let current = '';
    document.querySelectorAll('.position-section').forEach(section => {
        const sectionTop = section.offsetTop;
        if (window.pageYOffset >= sectionTop - 100) {
            current = section.getAttribute('id');
        }
    });
    
    document.querySelectorAll('.position-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('active');
        }
    });
});

// Click on card to select
document.querySelectorAll('.candidate-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (!e.target.closest('.btn') && !e.target.closest('.form-check-input')) {
            const radio = this.querySelector('.vote-radio');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>