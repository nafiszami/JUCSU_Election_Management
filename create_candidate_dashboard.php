```php
<?php
// create_candidate_dashboard.php
require_once 'connection.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $position_id = $_POST['position_id'];
    $hall_name = ($_POST['hall_name'] ?? null); // Only set if provided
    $manifesto = $_POST['manifesto'];
    $photo_path = $_POST['photo_path'];
    $proposer_id = $_POST['proposer_id'];
    $seconder_id = $_POST['seconder_id'];

    // Determine election type from position
    $position_stmt = $pdo->prepare("SELECT election_type FROM positions WHERE id = ? AND is_active = 1");
    $position_stmt->execute([$position_id]);
    $position = $position_stmt->fetch(PDO::FETCH_ASSOC);
    $election_type = $position['election_type'] ?? 'hall'; // Default to hall if not found

    try {
        $stmt = $pdo->prepare("
            INSERT INTO candidates (user_id, position_id, election_type, hall_name, manifesto, photo_path, proposer_id, seconder_id, status, nominated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $position_id, $election_type, $hall_name, $manifesto, $photo_path, $proposer_id, $seconder_id]);
        $success = "Candidate created successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch positions for dropdown
$positions_stmt = $pdo->query("SELECT id, position_name, election_type FROM positions WHERE is_active = 1");
$positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch halls for dropdown
$halls_stmt = $pdo->query("SELECT DISTINCT hall_name FROM halls WHERE is_active = 1");
$halls = $halls_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Candidate Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 10px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; font-weight: 500; }
        .form-label i { color: #007bff; }
        .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25); }
        @media (max-width: 768px) { .card-body { padding: 1rem; } }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-person-plus-fill"></i> Create Candidate Dashboard</h2>
                <p class="text-muted">Create test candidates for hall or JUCSU elections.</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Candidate</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-person"></i> User ID</label>
                                    <input type="number" class="form-control" name="user_id" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-trophy"></i> Position</label>
                                    <select class="form-select" name="position_id" id="position_id" required onchange="toggleHallField()">
                                        <option value="">Select Position</option>
                                        <?php foreach ($positions as $position): ?>
                                            <option value="<?php echo $position['id']; ?>" data-type="<?php echo $position['election_type']; ?>">
                                                <?php echo $position['position_name'] . ' (' . $position['election_type'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3" id="hall-field" style="display: none;">
                                    <label class="form-label"><i class="bi bi-house"></i> Hall Name</label>
                                    <select class="form-select" name="hall_name" required>
                                        <option value="">Select Hall</option>
                                        <?php foreach ($halls as $hall): ?>
                                            <option value="<?php echo $hall['hall_name']; ?>">
                                                <?php echo $hall['hall_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><i class="bi bi-card-text"></i> Manifesto</label>
                                    <textarea class="form-control" name="manifesto" rows="3" required></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-image"></i> Photo Path</label>
                                    <input type="text" class="form-control" name="photo_path" placeholder="e.g., photos/candidate1.jpg">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-person-check"></i> Proposer ID</label>
                                    <input type="number" class="form-control" name="proposer_id" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-person-check"></i> Seconder ID</label>
                                    <input type="number" class="form-control" name="seconder_id" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create Candidate</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleHallField() {
            const positionSelect = document.getElementById('position_id');
            const hallField = document.getElementById('hall-field');
            const selectedOption = positionSelect.options[positionSelect.selectedIndex];
            const electionType = selectedOption ? selectedOption.getAttribute('data-type') : 'hall';

            console.log('Selected Election Type:', electionType); // Debug log
            if (electionType === 'hall') {
                hallField.style.display = 'block';
                document.querySelector('#hall-field select').setAttribute('required', 'required');
            } else {
                hallField.style.display = 'none';
                document.querySelector('#hall-field select').removeAttribute('required');
            }
        }
        // Ensure toggle runs on page load and change
        document.addEventListener('DOMContentLoaded', toggleHallField);
        document.getElementById('position_id').addEventListener('change', toggleHallField);
    </script>
</body>
</html>