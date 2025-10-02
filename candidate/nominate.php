<?php
// candidate/nominate.php
$page_title = "Nominate as Candidate";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
$election_type = isset($_GET['type']) && in_array($_GET['type'], ['jucsu', 'hall']) ? $_GET['type'] : null;
$error = '';
$success = '';

if (!$election_type) {
    die("Invalid election type.");
}

// Check if nomination phase is active and within deadline
$stmt = $pdo->prepare("SELECT current_phase, nomination_end FROM election_schedule WHERE election_type = ? AND is_active = 1");
$stmt->execute([$election_type]);
$schedule = $stmt->fetch();
if (!$schedule || $schedule['current_phase'] !== 'nomination' || date('Y-m-d') > $schedule['nomination_end']) {
    $error = "Nominations are not currently open.";
}

// Check if user has already applied for this election type
$stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE user_id = ? AND election_type = ? AND status IN ('pending', 'approved')");
$stmt->execute([$current_user['id'], $election_type]);
if ($stmt->fetchColumn() > 0) {
    $error = "You have already applied for a position in this election type.";
}

$positions = $pdo->prepare("SELECT id, position_name FROM positions WHERE election_type = ? AND is_active = 1 ORDER BY position_order");
$positions->execute([$election_type]);
$positions = $positions->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $position_id = (int)$_POST['position_id'];
        $manifesto = sanitizeInput($_POST['manifesto'] ?? '');
        $proposer_id = sanitizeInput($_POST['proposer_id'] ?? '');
        $seconder_id = sanitizeInput($_POST['seconder_id'] ?? '');
        $hall_name = $election_type === 'hall' ? sanitizeInput($_POST['hall_name'] ?? '') : null;

        // Validate inputs
        if (empty($position_id) || empty($manifesto) || empty($proposer_id) || empty($seconder_id) || ($election_type === 'hall' && empty($hall_name))) {
            $error = "All fields are required.";
        } elseif (strlen($manifesto) > 5000) {
            $error = "Manifesto is too long (max 5000 characters).";
        } elseif ($proposer_id === $seconder_id || $proposer_id === $current_user['university_id'] || $seconder_id === $current_user['university_id']) {
            $error = "Proposer and seconder must be different and cannot be yourself.";
        } else {
            // Verify proposer
            $stmt = $pdo->prepare("SELECT id, hall_name FROM users WHERE university_id = ? AND is_active = 1");
            $stmt->execute([$proposer_id]);
            $proposer = $stmt->fetch();
            if (!$proposer) {
                $error = "Invalid or inactive proposer.";
            }

            // Verify seconder
            $stmt->execute([$seconder_id]);
            $seconder = $stmt->fetch();
            if (!$seconder) {
                $error = "Invalid or inactive seconder.";
            }

            // Validate hall for hall election
            if ($election_type === 'hall' && ($proposer['hall_name'] !== $hall_name || $seconder['hall_name'] !== $hall_name || $current_user['hall_name'] !== $hall_name)) {
                $error = "Candidate, proposer, and seconder must be from the same hall for hall elections.";
            }

            // Validate position
            $stmt = $pdo->prepare("SELECT id FROM positions WHERE id = ? AND election_type = ? AND is_active = 1");
            $stmt->execute([$position_id, $election_type]);
            if (!$stmt->fetch()) {
                $error = "Invalid position selected.";
            }

            // Handle file upload
            $photo_path = '';
            if (!$error && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 2 * 1024 * 1024; // 2MB
                $file_type = mime_content_type($_FILES['photo']['tmp_name']);
                $file_size = $_FILES['photo']['size'];
                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPEG or PNG files are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "Photo size exceeds 2MB.";
                } else {
                    $target_dir = "../Uploads/candidate_photos/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    $photo_path = $target_dir . time() . '_' . basename($_FILES['photo']['name']);
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                        $error = "Failed to upload photo.";
                    }
                }
            }

            if (!$error) {
                // Insert nomination
                $stmt = $pdo->prepare("
                    INSERT INTO candidates (user_id, position_id, election_type, hall_name, proposer_id, seconder_id, manifesto, photo_path, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $current_user['id'], $position_id, $election_type, $hall_name,
                    $proposer['id'], $seconder['id'], $manifesto, $photo_path
                ]);

                // Notify user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $current_user['id'],
                    'Nomination Submitted',
                    'Your nomination for ' . ucfirst($election_type) . ' has been submitted and is pending approval.',
                    'success'
                ]);

                $success = "Nomination submitted successfully! Awaiting approval.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center p-4">
            <h2 class="mb-0">Nominate for <?php echo ucfirst($election_type); ?> Election</h2>
            <p class="mt-2 mb-0">Submit your candidacy details below</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="mt-3">
                    <a href="../voter/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <form id="nominationForm" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="bi bi-person-vcard me-2"></i>Election Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($election_type); ?>" disabled>
                    </div>

                    <div class="mb-4">
                        <label for="position_id" class="form-label fw-bold"><i class="bi bi-briefcase me-2"></i>Position</label>
                        <select class="form-select" id="position_id" name="position_id" required>
                            <option value="" disabled selected>Select a position</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position['id']; ?>">
                                    <?php echo htmlspecialchars($position['position_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($election_type === 'hall'): ?>
                        <div class="mb-4">
                            <label for="hall_name" class="form-label fw-bold"><i class="bi bi-building me-2"></i>Hall</label>
                            <select class="form-select" id="hall_name" name="hall_name" required>
                                <option value="" disabled selected>Select your hall</option>
                                <?php
                                $halls = $pdo->query("SELECT hall_name FROM halls WHERE is_active = 1 ORDER BY hall_name")->fetchAll();
                                foreach ($halls as $hall):
                                ?>
                                    <option value="<?php echo htmlspecialchars($hall['hall_name']); ?>">
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="manifesto" class="form-label fw-bold"><i class="bi bi-file-text me-2"></i>Manifesto</label>
                        <textarea class="form-control" id="manifesto" name="manifesto" rows="5" required></textarea>
                        <div id="wordCount" class="form-text">0/500 words</div>
                    </div>

                    <div class="mb-4">
                        <label for="photo" class="form-label fw-bold"><i class="bi bi-image me-2"></i>Upload Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png">
                        <div class="form-text">JPEG or PNG, max 2MB</div>
                    </div>

                    <div class="mb-4">
                        <label for="proposer_id" class="form-label fw-bold"><i class="bi bi-person-check me-2"></i>Proposer University ID</label>
                        <input type="text" class="form-control" id="proposer_id" name="proposer_id" required>
                    </div>

                    <div class="mb-4">
                        <label for="seconder_id" class="form-label fw-bold"><i class="bi bi-person-check me-2"></i>Seconder University ID</label>
                        <input type="text" class="form-control" id="seconder_id" name="seconder_id" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../voter/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit Nomination</button>
                    </div>
                </form>

                <script>
                    const manifesto = document.getElementById('manifesto');
                    const wordCount = document.getElementById('wordCount');
                    manifesto.addEventListener('input', () => {
                        const text = manifesto.value.trim();
                        const words = text.split(/\s+/).filter(word => word.length > 0).length;
                        wordCount.textContent = `${words}/500 words`;
                        if (words > 500) {
                            wordCount.style.color = 'red';
                        } else {
                            wordCount.style.color = 'inherit';
                        }
                    });

                    document.getElementById('nominationForm').addEventListener('submit', (e) => {
                        const words = manifesto.value.trim().split(/\s+/).filter(word => word.length > 0).length;
                        const proposer = document.getElementById('proposer_id').value;
                        const seconder = document.getElementById('seconder_id').value;
                        if (words > 500) {
                            e.preventDefault();
                            alert('Manifesto exceeds 500 words.');
                        }
                        if (proposer === seconder) {
                            e.preventDefault();
                            alert('Proposer and seconder must be different.');
                        }
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>