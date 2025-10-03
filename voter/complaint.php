<?php
// voter/complaint.php
$page_title = "Submit a Complaint";
require_once '../includes/check_auth.php';
requireRole('voter');

$current_user = getCurrentUser();
$voter_hall = $current_user['hall_name'];

$success = '';
$error = '';

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $election_type = $_POST['election_type'] ?? '';
    $complaint_type = $_POST['complaint_type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $evidence_files = null;

    // Validate inputs
    if (empty($election_type) || empty($complaint_type) || empty($subject) || empty($description)) {
        $error = "All fields are required!";
    } elseif (!in_array($election_type, ['jucsu', 'hall']) || !in_array($complaint_type, ['procedural', 'candidate_conduct', 'voting_irregularity', 'result_dispute'])) {
        $error = "Invalid election or complaint type!";
    } else {
        try {
            $pdo->beginTransaction();

            // Handle file upload if present
            if (!empty($_FILES['evidence_files']['name'][0])) {
                $evidence_paths = [];
                $upload_dir = '../uploads/complaints/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['evidence_files']['tmp_name'] as $key => $tmp_name) {
                    $file_name = basename($_FILES['evidence_files']['name'][$key]);
                    $target_file = $upload_dir . uniqid() . '_' . $file_name;
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $evidence_paths[] = $target_file;
                    }
                }
                $evidence_files = implode(',', $evidence_paths);
            }

            // Insert complaint
            $stmt = $pdo->prepare("
                INSERT INTO complaints (complainant_id, election_type, complaint_type, subject, description, evidence_files)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_user['id'],
                $election_type,
                $complaint_type,
                $subject,
                $description,
                $evidence_files
            ]);

            $pdo->commit();
            $success = "Complaint submitted successfully! It is now pending review.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error submitting complaint: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<style>
    .complaint-form {
        max-width: 600px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .evidence-preview {
        margin-top: 1rem;
    }
</style>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="bi bi-exclamation-circle"></i> Submit a Complaint</h2>
                <p class="text-muted mb-0">Report issues related to the election process</p>
            </div>
            <div>
                <a href="vote.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Voting
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

<div class="row">
    <div class="col-12">
        <div class="card complaint-form">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="complaintForm">
                    <div class="form-group">
                        <label for="election_type" class="form-label">Election Type *</label>
                        <select class="form-select" id="election_type" name="election_type" required>
                            <option value="jucsu">JUCSU Election</option>
                            <option value="hall">Hall Union Election</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="complaint_type" class="form-label">Complaint Type *</label>
                        <select class="form-select" id="complaint_type" name="complaint_type" required>
                            <option value="procedural">Procedural</option>
                            <option value="candidate_conduct">Candidate Conduct</option>
                            <option value="voting_irregularity">Voting Irregularity</option>
                            <option value="result_dispute">Result Dispute</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" required maxlength="200">
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidence_files" class="form-label">Evidence Files (Optional)</label>
                        <input type="file" class="form-control" id="evidence_files" name="evidence_files[]" multiple>
                        <div class="evidence-preview" id="evidencePreview"></div>
                    </div>

                    <button type="submit" name="submit_complaint" class="btn btn-primary btn-lg">
                        <i class="bi bi-send"></i> Submit Complaint
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('evidence_files').addEventListener('change', function(e) {
    const preview = document.getElementById('evidencePreview');
    preview.innerHTML = '';
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.maxWidth = '100px';
            img.style.marginRight = '10px';
            preview.appendChild(img);
        } else {
            const p = document.createElement('p');
            p.textContent = file.name;
            preview.appendChild(p);
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>