<?php
// Ensure $pdo is available from the included dashboard.php
if (!isset($pdo)) {
    echo '<div class="alert alert-danger">FATAL ERROR: Database connection missing. Cannot load results.</div></div>';
    return;
}

$results_data = [];
$chart_data = [];
$error_message = '';
$summary_winners = []; // Array for the final summary table

// --- 1. Fetch All Positions (No is_active filter) ---
try {
    $positions_stmt = $pdo->query("
        SELECT id, position_name, election_type, position_order
        FROM positions
        ORDER BY election_type, position_order ASC
    ");
    $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. Fetch Candidates and Votes for Each Position ---
    foreach ($positions as $position) {
        $position_id = $position['id'];

        $candidates_stmt = $pdo->prepare("
            SELECT 
                c.id, 
                u.full_name, 
                u.department, 
                c.vote_count,
                c.status
            FROM candidates c
            JOIN users u ON c.user_id = u.id
            WHERE c.position_id = :position_id AND c.status = 'approved'
            ORDER BY c.vote_count DESC, u.full_name ASC
        ");
        $candidates_stmt->execute([':position_id' => $position_id]);
        $candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_valid_votes = array_sum(array_column($candidates, 'vote_count'));
        $total_candidates = count($candidates);
        
        // Final Winner Determination
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
        
        // Add winner to summary table
        if ($winner) {
            $summary_winners[] = [
                'position' => $position['position_name'],
                'type' => $position['election_type'],
                'winner' => $winner['full_name'],
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

        // --- CHART LOGIC: Only compile data for contested positions (> 1 candidate) ---
        if ($total_candidates > 1) {
            $chart_data[$position['position_name']] = [
                'labels' => array_column($candidates, 'full_name'),
                'votes' => array_column($candidates, 'vote_count'),
                'position_id' => $position['id']
            ];
        }
    }

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch election results. " . $e->getMessage();
}

/**
 * Custom color palette for progress bars (lighter shades).
 */
$color_palette = [
    '#5bc0de', '#5cb85c', '#f0ad4e', '#d9534f', '#0d6efd', '#6610f2', '#6f42c1', '#d63384'
];

?>

<div class="container-fluid">
    <h3 class="mb-4 text-success">📊 Election Results Overview</h3>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if (empty($results_data)): ?>
        <div class="alert alert-info text-center mt-5">
            No positions found to display results.
        </div>
    <?php endif; ?>

    <h4 class="mt-5 mb-3 text-secondary">Contested Position Vote Breakdown (Pie Charts)</h4>
    <div class="row">
        <?php 
        if (!empty($chart_data)): 
        ?>
            <?php foreach ($chart_data as $position_name => $data): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><?= htmlspecialchars($position_name) ?></h5>
                            <small class="text-muted">Total Votes: <span class="count-up" data-target="<?= array_sum($data['votes']) ?>">0</span></small>
                        </div>
                        <div class="card-body text-center">
                            <div class="chart-wrapper" data-position-name="<?= htmlspecialchars($position_name) ?>" id="chartwrapper-<?= $data['position_id'] ?>">
                                <canvas id="chart-<?= $data['position_id'] ?>" style="max-height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info mt-3">No positions have multiple candidates to chart.</div>
        <?php endif; ?>
    </div>


    <h4 class="mt-5 mb-3 text-success">Detailed Candidate Progress</h4>
    
    <div class="row">
    <?php foreach ($results_data as $pos_data): ?>
        <div class="col-md-12 mb-5"> 
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($pos_data['position']['position_name']) ?> (<?= strtoupper($pos_data['position']['election_type']) ?>)</h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($pos_data['total_candidates'] === 0): ?>
                        <div class="alert alert-warning">No approved candidates registered for this position.</div>
                    <?php else: ?>

                        <div class="candidate-list mt-2">
                            <?php foreach ($pos_data['candidates'] as $index => $candidate): 
                                $percentage = $pos_data['total_votes'] > 0 
                                    ? round(($candidate['vote_count'] / $pos_data['total_votes']) * 100, 2)
                                    : ($pos_data['total_candidates'] === 1 ? 100.00 : 0.00); 
                                $bar_width = min(100, $percentage);
                                $bar_color = $color_palette[$index % count($color_palette)]; 
                            ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>
                                            <?= htmlspecialchars($candidate['full_name']) ?>
                                            <small class="text-muted">(<?= htmlspecialchars($candidate['department']) ?>)</small>
                                        </strong>
                                        <div>
                                            <span class="fw-bold me-2">
                                                <span class="count-up" data-target="<?= $candidate['vote_count'] ?>">0</span> votes
                                            </span>
                                            <span class="badge bg-secondary"><?= $percentage ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 25px;"> 
                                        <div class="progress-bar progress-bar-animated" role="progressbar" 
                                             data-percentage="<?= $bar_width ?>"
                                             style="width: 0%; background-color: <?= $bar_color ?>;" 
                                             aria-valuenow="<?= $bar_width ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    
    <h4 class="mt-5 mb-3 text-danger">Final Winner Declaration</h4>
    <div class="table-responsive shadow rounded mb-5">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Position</th>
                    <th>Election Type</th>
                    <th>Winner</th>
                    <th>Votes Received</th>
                    <th>Percentage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary_winners)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No winners declared yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($summary_winners as $winner_data): ?>
                    <tr class="<?= $winner_data['is_uncontested'] ? 'table-info' : 'table-success' ?>">
                        <td><strong><?= htmlspecialchars($winner_data['position']) ?></strong></td>
                        <td><?= strtoupper($winner_data['type']) ?></td>
                        <td><?= htmlspecialchars($winner_data['winner']) ?></td>
                        <td><span class="count-up" data-target="<?= $winner_data['votes'] ?>">0</span></td>
                        <td><?= $winner_data['percentage'] ?>%</td>
                        <td><span class="badge bg-<?= $winner_data['is_uncontested'] ? 'primary' : 'success' ?>">
                            <?= $winner_data['is_uncontested'] ? 'Uncontested' : 'Contested Winner' ?>
                        </span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?= json_encode($chart_data) ?>;
    
    // Function to generate colors for Pie Chart slices
    function getColors(count) {
        const colors = [
            'rgba(141, 196, 245, 0.8)', 'rgba(255, 179, 179, 0.8)',  
            'rgba(150, 255, 170, 0.8)', 'rgba(255, 230, 150, 0.8)', 
            'rgba(200, 160, 255, 0.8)', 'rgba(255, 200, 220, 0.8)'
        ];
        let finalColors = [];
        for (let i = 0; i < count; i++) {
            finalColors.push(colors[i % colors.length]);
        }
        return finalColors;
    }

    // ==============================================
    // 1. Counting Animation Function (Slower duration: 2.0s)
    // ==============================================
    function animateCounter(counter) {
        if (counter.getAttribute('data-animated') === 'true') return;

        const duration = 2000; // 2.0 seconds
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
    
    // ==============================================
    // 2. Chart Drawing Function
    // ==============================================
    function drawChart(positionId, data) {
        const canvas = document.getElementById('chart-' + positionId);
        if (canvas.getAttribute('data-drawn') === 'true') return; 

        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Percentage of Votes',
                    data: data.votes,
                    backgroundColor: getColors(data.votes.length),
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: false }
                }
            }
        });
        canvas.setAttribute('data-drawn', 'true');
    }
    
    // ==============================================
    // 3. Progress Bar Animation Function
    // ==============================================
    function animateProgressBar(bar) {
        if (bar.getAttribute('data-animated') === 'true') return;

        const targetWidth = bar.getAttribute('data-percentage') + '%';
        
        // Use a CSS transition for smooth animation
        bar.style.transition = 'width 2s ease-in-out';
        bar.style.width = targetWidth;

        bar.setAttribute('data-animated', 'true');
    }

    // ==============================================
    // 4. Intersection Observer Setup (Dynamic Trigger)
    // ==============================================
    
    const observerOptions = { threshold: 0.5 }; 

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;

                if (element.classList.contains('count-up')) {
                    animateCounter(element);
                } else if (element.classList.contains('progress-bar')) {
                    animateProgressBar(element);
                } else if (element.classList.contains('chart-wrapper')) {
                    const positionId = element.id.split('-')[1];
                    const positionName = element.getAttribute('data-position-name');
                    
                    if (chartData.hasOwnProperty(positionName)) {
                        drawChart(positionId, chartData[positionName]);
                        observer.unobserve(element); // Stop observing charts once drawn
                    }
                }
            }
        });
    }, observerOptions);

    


    document.querySelectorAll('.count-up').forEach(counter => {
        observer.observe(counter);
    });
    

    document.querySelectorAll('.progress-bar').forEach(bar => {
        observer.observe(bar);
    });


    document.querySelectorAll('.chart-wrapper').forEach(wrapper => {
        observer.observe(wrapper);
    });
});
</script>