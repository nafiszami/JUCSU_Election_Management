<?php
// voter/candidates_jucsu.php
$page_title = "JUCSU Candidates";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.full_name, u.university_id, u.department, p.position_name, c.photo_path, c.hall_name
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'jucsu' AND c.status = 'approved'
        ORDER BY p.position_order
    ");
    $stmt->execute();
    $jucsu_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $jucsu_positions = [
        "Vice-President",
        "General Secretary",
        "Joint General Secretary (Female)",
        "Joint General Secretary (Male)",
        "Education and Research Secretary",
        "Environment and Nature Conservation Secretary",
        "Literature and Publication Secretary",
        "Cultural Secretary",
        "Assistant Cultural Secretary",
        "Drama Secretary",
        "Sports Secretary",
        "Assistant Sports Secretary (Female)",
        "Assistant Sports Secretary (Male)",
        "Information Technology and Library Secretary",
        "Social Service and Human Resource Development Secretary",
        "Assistant Social Service and HRD Secretary (Female)",
        "Assistant Social Service and HRD Secretary (Male)",
        "Health and Food Safety Secretary",
        "Transport and Communication Secretary",
        "Executive Member 1",
        "Executive Member 2",
        "Executive Member 3",
        "Executive Member 4",
        "Executive Member 5",
        "Executive Member 6"
    ];

} catch (PDOException $e) {
    $error = "Error loading candidates: " . htmlspecialchars($e->getMessage());
}

$selected_position = isset($_GET['position']) ? $_GET['position'] : '';
$filtered_candidates = $selected_position ? array_filter($jucsu_candidates, fn($c) => $c['position_name'] === $selected_position) : [];

include '../includes/header.php';
?>

<style>
    .position-list {
        background: linear-gradient(135deg, #6c5ce7, #a29bfe);
        border-radius: 10px;
        padding: 15px;
        color: white;
    }

    .position-list .list-group-item {
        background: transparent;
        border: none;
        color: white;
        padding: 10px 15px;
        transition: background 0.3s;
    }

    .position-list .list-group-item:hover {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 5px;
    }

    .candidate-card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }

    .candidate-card:hover {
        transform: translateY(-5px);
    }

    .candidate-photo {
        width: 120px;
        height: 120px;
        border-radius: 10px;
        object-fit: cover;
        border: 3px solid #6c5ce7;
    }

    .candidate-photo-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 10px;
        background: linear-gradient(135deg, #6c5ce7, #a29bfe);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
    }
</style>

<div class="container py-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
        <div class="row">
            <div class="col-12 col-md-3">
                <h4>Positions</h4>
                <div class="position-list">
                    <ul class="list-group">
                        <?php foreach ($jucsu_positions as $position): ?>
                            <a href="?position=<?php echo urlencode($position); ?>" class="list-group-item list-group-item-action <?php echo $selected_position === $position ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($position); ?>
                            </a>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-12 col-md-9">
                <h4><?php echo htmlspecialchars($selected_position ?: 'Select a Position'); ?></h4>
                <?php if (empty($filtered_candidates)): ?>
                    <p>No candidates for this position.</p>
                <?php else: ?>
                    <?php foreach ($filtered_candidates as $candidate): ?>
                        <div class="candidate-card">
                            <div class="candidate-content">
                                <div class="candidate-photo-section">
                                    <?php if ($candidate['photo_path']): ?>
                                        <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" class="candidate-photo" alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                    <?php else: ?>
                                        <div class="candidate-photo-placeholder"><i class="bi bi-person-circle"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="candidate-info">
                                    <h4 class="candidate-name"><?php echo htmlspecialchars($candidate['full_name']); ?></h4>
                                    <div class="candidate-details">
                                        <div><i class="bi bi-person-badge"></i> <strong>ID:</strong> <?php echo htmlspecialchars($candidate['university_id']); ?></div>
                                        <div><i class="bi bi-building"></i> <strong>Department:</strong> <?php echo htmlspecialchars($candidate['department'] ?: 'N/A'); ?></div>
                                        <div><i class="bi bi-house"></i> <strong>Hall:</strong> <?php echo htmlspecialchars($candidate['hall_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="mt-4">
        <a href="candidates.php" class="btn btn-outline-secondary">Back to Candidates</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>