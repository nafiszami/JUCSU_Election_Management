<?php
// voter/candidates.php
$page_title = "Approved Candidates";
require_once '../includes/check_auth.php';
requireRole('voter');

try {
    // Fetch approved JUCSU candidates with department
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.full_name, u.university_id, u.department, p.position_name, c.photo_path
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'jucsu' AND c.status = 'approved'
        ORDER BY p.position_order
    ");
    $stmt->execute();
    $jucsu_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch approved hall candidates with department, grouped by hall
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.full_name, u.university_id, u.department, p.position_name, c.hall_name, c.photo_path
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        JOIN positions p ON c.position_id = p.id
        WHERE c.election_type = 'hall' AND c.status = 'approved'
        ORDER BY c.hall_name, p.position_order
    ");
    $stmt->execute();
    $hall_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group hall candidates by hall
    $hall_groups = [];
    foreach ($hall_candidates as $candidate) {
        $hall_groups[$candidate['hall_name']][] = $candidate;
    }
} catch (PDOException $e) {
    $error = "Error loading candidates: " . htmlspecialchars($e->getMessage());
}

include '../includes/header.php';
?>

<div class="container py-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
        <h2 class="mb-4">Approved Candidates</h2>
        <h4>Approved Candidates for JUCSU (Central)</h4>
        <?php if (empty($jucsu_candidates)): ?>
            <p>No approved candidates for JUCSU yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>University ID</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jucsu_candidates as $candidate): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['university_id']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['department'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($candidate['position_name']); ?></td>
                                <td>
                                    <?php if ($candidate['photo_path']): ?>
                                        <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" alt="Candidate Photo" style="max-width: 100px; border-radius: 8px;">
                                    <?php else: ?>
                                        No Photo
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h4 class="mt-4">Approved Candidates for Hall Elections</h4>
        <?php if (empty($hall_groups)): ?>
            <p>No approved candidates for hall elections yet.</p>
        <?php else: ?>
            <?php foreach ($hall_groups as $hall_name => $candidates): ?>
                <h5 class="mt-3"><?php echo htmlspecialchars($hall_name); ?></h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>University ID</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Photo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['university_id']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['department'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['position_name']); ?></td>
                                    <td>
                                        <?php if ($candidate['photo_path']): ?>
                                            <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" alt="Candidate Photo" style="max-width: 100px; border-radius: 8px;">
                                        <?php else: ?>
                                            No Photo
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>