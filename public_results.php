<?php
// public_results.php or results.php
require_once 'connection.php';

$results_data = [];
$summary_winners = [];
$error_message = '';
$selected_type = isset($_GET['type']) ? strtolower($_GET['type']) : '';
$selected_hall = isset($_GET['hall']) ? $_GET['hall'] : '';

// Validate selected type
$valid_types = ['jucsu', 'hall'];
if (!in_array($selected_type, $valid_types)) {
    $selected_type = '';
}

// Fetch all halls for dropdown
try {
    $halls_stmt = $pdo->query("SELECT hall_name FROM halls WHERE is_active = 1 ORDER BY hall_name");
    $all_halls = $halls_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error_message = "Error fetching halls: " . $e->getMessage();
}

// --- Fetch Positions and Results ---
try {
    if ($selected_type === 'jucsu') {
        $positions_stmt = $pdo->query("
            SELECT id, position_name, election_type, position_order
            FROM positions
            WHERE election_type = 'jucsu' AND is_active = 1
            ORDER BY position_order ASC
        ");
        $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($positions as $position) {
            $position_id = $position['id'];

            $candidates_stmt = $pdo->prepare("
                SELECT 
                    c.id, 
                    u.full_name, 
                    u.department, 
                    u.hall_name, 
                    c.vote_count,
                    c.status
                FROM candidates c
                JOIN users u ON c.user_id = u.id
                WHERE c.position_id = :position_id AND c.status = 'approved' AND c.election_type = 'jucsu'
                ORDER BY c.vote_count DESC, u.full_name ASC
            ");
            $candidates_stmt->execute([':position_id' => $position_id]);
            $candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_valid_votes = array_sum(array_column($candidates, 'vote_count'));
            $total_candidates = count($candidates);
            
            $winner = null;
            if ($total_candidates > 0) {
                if ($total_valid_votes > 0) {
                    $winner = $candidates[0]; 
                    $winner['percentage'] = round(($winner['vote_count'] / $total_valid_votes) * 100, 2);
                } elseif ($total_candidates === 1) {
                    $winner = $candidates[0];
                    $winner['percentage'] = 100.00; 
                }
            }
            
            if ($winner) {
                $summary_winners[] = [
                    'position' => $position['position_name'],
                    'type' => $position['election_type'],
                    'winner' => $winner['full_name'],
                    'hall_name' => $winner['hall_name'] ?? 'N/A',
                    'department' => $winner['department'] ?? 'N/A',
                    'votes' => $winner['vote_count'],
                    'percentage' => $winner['percentage'],
                    'is_uncontested' => ($total_candidates === 1)
                ];
            }

            $results_data[] = [
                'position' => $position,
                'candidates' => $candidates,
                'total_votes' => $total_valid_votes,
                'total_candidates' => $total_candidates,
                'winner' => $winner,
            ];
        }

    } elseif ($selected_type === 'hall' && !empty($selected_hall)) {
        $positions_stmt = $pdo->query("
            SELECT id, position_name, election_type, position_order
            FROM positions
            WHERE election_type = 'hall' AND is_active = 1
            ORDER BY position_order ASC
        ");
        $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($positions as $position) {
            $position_id = $position['id'];

            $candidates_stmt = $pdo->prepare("
                SELECT 
                    c.id, 
                    u.full_name, 
                    u.department, 
                    u.hall_name, 
                    c.vote_count,
                    c.status
                FROM candidates c
                JOIN users u ON c.user_id = u.id
                WHERE c.position_id = :position_id 
                AND c.status = 'approved' 
                AND c.election_type = 'hall'
                AND c.hall_name = :hall_name
                ORDER BY c.vote_count DESC, u.full_name ASC
            ");
            $candidates_stmt->execute([
                ':position_id' => $position_id,
                ':hall_name' => $selected_hall
            ]);
            $candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_valid_votes = array_sum(array_column($candidates, 'vote_count'));
            $total_candidates = count($candidates);
            
            $winner = null;
            if ($total_candidates > 0) {
                if ($total_valid_votes > 0) {
                    $winner = $candidates[0]; 
                    $winner['percentage'] = round(($winner['vote_count'] / $total_valid_votes) * 100, 2);
                } elseif ($total_candidates === 1) {
                    $winner = $candidates[0];
                    $winner['percentage'] = 100.00; 
                }
            }
            
            if ($winner) {
                $summary_winners[] = [
                    'position' => $position['position_name'],
                    'type' => $position['election_type'],
                    'hall' => $selected_hall,
                    'winner' => $winner['full_name'],
                    'department' => $winner['department'] ?? 'N/A',
                    'votes' => $winner['vote_count'],
                    'percentage' => $winner['percentage'],
                    'is_uncontested' => ($total_candidates === 1)
                ];
            }

            $results_data[] = [
                'position' => $position,
                'candidates' => $candidates,
                'total_votes' => $total_valid_votes,
                'total_candidates' => $total_candidates,
                'winner' => $winner,
            ];
        }
    }

} catch (PDOException | Exception $e) {
    $error_message = "Database Error: Could not fetch final results. " . $e->getMessage();
}

$color_palette = [
    '#5bc0de', '#5cb85c', '#f0ad4e', '#d9534f', '#0d6efd', '#6610f2', '#6f42c1', '#d63384'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - JUCSU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #f1f8e9 100%);
            min-height: 100vh;
        }
        .results-header {
            background: linear-gradient(135deg, #1976d2 0%, #43a047 100%);
            color: white;
            padding: 50px 0;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 40px;
        }
        .progress { height: 30px; border-radius: 15px; }
        .progress-bar { 
            transition: width 2s ease-in-out; 
            font-weight: 600;
            font-size: 0.9rem;
        }
        .count-up { font-weight: bold; }
        .result-card { 
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 30px;
        }
        .result-card .card-header {
            border-radius: 20px 20px 0 0;
            padding: 20px;
        }
        .selection-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .type-btn {
            padding: 30px;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 700;
            transition: all 0.3s;
            border: 3px solid transparent;
        }
        .type-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hall-selector {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 35px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto 35px;
        }
        .hall-select {
            padding: 15px 20px;
            font-size: 1.1rem;
            border: 2px solid #1976d2;
            border-radius: 12px;
        }
        .winner-badge {
            font-size: 1.5rem;
            padding: 10px 20px;
            border-radius: 15px;
        }
        .candidate-row {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .candidate-row:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .winner-highlight {
            background: linear-gradient(135deg, #fff3cd 0%, #d4edda 100%);
            border-left: 5px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="results-header">
        <div class="container text-center">
            <h1><i class="bi bi-trophy-fill"></i> Election Results</h1>
            <p class="lead">Official election results for JUCSU and Hall Union elections</p>
        </div>
    </div>

    <div class="container py-4">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
            </div>
        <?php elseif (!$selected_type): ?>
            <!-- Type Selection -->
            <div class="selection-card">
                <h4 class="text-center mb-4">Select Election Type</h4>
                <div class="d-grid gap-3">
                    <a href="?type=jucsu" class="btn btn-success type-btn">
                        <i class="bi bi-building"></i> JUCSU Election Results
                    </a>
                    <a href="?type=hall" class="btn btn-warning type-btn">
                        <i class="bi bi-house-door"></i> Hall Union Results
                    </a>
                </div>
            </div>
        <?php elseif ($selected_type === 'hall' && empty($selected_hall)): ?>
            <!-- Hall Selection -->
            <div class="hall-selector">
                <h4 class="text-center mb-4"><i class="bi bi-building"></i> Select Hall</h4>
                <select class="form-select hall-select" onchange="window.location.href='?type=hall&hall=' + this.value">
                    <option value="">-- Choose a Hall --</option>
                    <?php foreach ($all_halls as $hall): ?>
                        <option value="<?= urlencode($hall) ?>">
                            <?= htmlspecialchars($hall) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-center mt-3">
                    <a href="?type=" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Selection
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Results Display -->
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <?php if ($selected_type === 'jucsu'): ?>
                        <i class="bi bi-building"></i> JUCSU Election Results
                    <?php else: ?>
                        <i class="bi bi-house-door"></i> <?= htmlspecialchars($selected_hall) ?> - Hall Union Results
                    <?php endif; ?>
                </h2>
                <a href="?type=<?= $selected_type === 'hall' ? 'hall' : '' ?>" class="btn btn-outline-secondary mt-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

            <!-- Winners Summary -->
            <?php if (!empty($summary_winners)): ?>
                <div class="result-card mb-5">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-trophy"></i> Winners Summary</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Position</th>
                                        <th>Winner</th>
                                        <th>Department</th>
                                        <th>Votes</th>
                                        <th>Percentage</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summary_winners as $winner_data): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($winner_data['position']) ?></strong></td>
                                        <td><?= htmlspecialchars($winner_data['winner']) ?></td>
                                        <td><?= htmlspecialchars($winner_data['department']) ?></td>
                                        <td><span class="count-up" data-target="<?= $winner_data['votes'] ?>">0</span></td>
                                        <td><strong><?= $winner_data['percentage'] ?>%</strong></td>
                                        <td>
                                            <span class="badge <?= $winner_data['is_uncontested'] ? 'bg-info' : 'bg-success' ?>">
                                                <?= $winner_data['is_uncontested'] ? 'Uncontested' : 'Winner' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Results -->
            <h4 class="text-center mb-4">Detailed Results by Position</h4>
            <?php foreach ($results_data as $pos_data): ?>
                <div class="result-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-award"></i> 
                            <?= htmlspecialchars($pos_data['position']['position_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pos_data['total_candidates'] === 0): ?>
                            <div class="alert alert-warning text-center">
                                <i class="bi bi-exclamation-circle"></i> 
                                No approved candidates for this position
                            </div>
                        <?php else: ?>
                            <?php foreach ($pos_data['candidates'] as $index => $candidate): 
                                $percentage = $pos_data['total_votes'] > 0 
                                    ? round(($candidate['vote_count'] / $pos_data['total_votes']) * 100, 2)
                                    : ($pos_data['total_candidates'] === 1 ? 100.00 : 0.00); 
                                $bar_width = min(100, $percentage);
                                $bar_color = $color_palette[$index % count($color_palette)]; 
                                $is_winner = ($index === 0 && $pos_data['winner']);
                            ?>
                                <div class="candidate-row <?= $is_winner ? 'winner-highlight' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($candidate['full_name']) ?>
                                                <?php if ($is_winner): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="bi bi-trophy-fill"></i> Winner
                                                    </span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="bi bi-building"></i> <?= htmlspecialchars($candidate['department']) ?>
                                                <?php if ($selected_type === 'jucsu'): ?>
                                                    | <i class="bi bi-house"></i> <?= htmlspecialchars($candidate['hall_name'] ?? 'N/A') ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-5 fw-bold text-primary">
                                                <span class="count-up" data-target="<?= $candidate['vote_count'] ?>">0</span> votes
                                            </div>
                                            <span class="badge bg-secondary"><?= $percentage ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             role="progressbar" 
                                             data-percentage="<?= $bar_width ?>"
                                             style="width: 0%; background-color: <?= $bar_color ?>;" 
                                             aria-valuenow="<?= $bar_width ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?= $percentage ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="mt-3 text-center text-muted">
                                <small>
                                    <i class="bi bi-info-circle"></i> 
                                    Total Votes Cast: <strong><?= $pos_data['total_votes'] ?></strong> | 
                                    Total Candidates: <strong><?= $pos_data['total_candidates'] ?></strong>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function animateCounter(counter) {
            if (counter.getAttribute('data-animated') === 'true') return;

            const duration = 2000;
            const finalValue = parseInt(counter.getAttribute('data-target'));
            if (isNaN(finalValue)) return;
            
            let startTimestamp = null;
            
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const easedProgress = 0.5 - Math.cos(progress * Math.PI) / 2;
                const currentValue = Math.floor(easedProgress * finalValue);

                counter.textContent = currentValue.toLocaleString();
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    counter.textContent = finalValue.toLocaleString();
                    counter.setAttribute('data-animated', 'true'); 
                }
            };
            window.requestAnimationFrame(step);
        }

        function animateProgressBar(bar) {
            if (bar.getAttribute('data-animated') === 'true') return;

            const targetWidth = bar.getAttribute('data-percentage') + '%';
            setTimeout(() => {
                bar.style.width = targetWidth;
                bar.setAttribute('data-animated', 'true');
            }, 100);
        }

        const observerOptions = { threshold: 0.3 }; 
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    if (element.classList.contains('count-up')) {
                        animateCounter(element);
                    } else if (element.classList.contains('progress-bar')) {
                        animateProgressBar(element);
                    }
                }
            });
        }, observerOptions);

        document.querySelectorAll('.count-up').forEach(counter => observer.observe(counter));
        document.querySelectorAll('.progress-bar').forEach(bar => observer.observe(bar));
    });
    </script>
</body>
</html>