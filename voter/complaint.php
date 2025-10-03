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
    .complaint-container {
        padding: 40px 0;
        background: linear-gradient(135deg, #e6f0fa 0%, #d0e8e0 100%);
        min-height: 100vh;
    }

    .complaint-header {
        background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
        color: white;
        padding: 40px;
        border-radius: 15px 15px 0 0;
        text-align: center;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.2);
        position: relative;
        overflow: hidden;
    }

    .complaint-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
        animation: pulse 8s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(0); }
        100% { transform: scale(1.5); opacity: 0; }
    }

    .complaint-header h2 {
        font-size: 2.8rem;
        font-weight: 800;
        margin-bottom: 15px;
        text-transform: uppercase;
        position: relative;
        z-index: 1;
    }

    .complaint-header p {
        font-size: 1.4rem;
        font-weight: 600;
        color: #f1c40f; /* Gold-like yellow for contrast */
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .complaint-card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 50px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        width: 100%;
        max-width: 1000px;
        margin: 0 auto 50px;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .complaint-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(52, 152, 219, 0.15);
    }

    .form-group {
        margin-bottom: 2.5rem;
    }

    .form-label {
        font-size: 1.4rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.7rem;
    }

    .form-select {
        font-size: 1.3rem;
        padding: 15px 20px;
        border-radius: 12px;
        border: 2px solid #e0e8f0;
        background: rgba(255, 255, 255, 0.85);
        transition: border-color 0.3s, box-shadow 0.3s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%232c3e50' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
    }

    .form-select:focus {
        border-color: #3498db;
        box-shadow: 0 0 12px rgba(52, 152, 219, 0.3);
        outline: none;
    }

    .form-control, textarea {
        font-size: 1.3rem;
        padding: 15px 20px;
        border-radius: 12px;
        border: 2px solid #e0e8f0;
        background: rgba(255, 255, 255, 0.85);
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-control:focus, textarea:focus {
        border-color: #3498db;
        box-shadow: 0 0 12px rgba(52, 152, 219, 0.3);
        outline: none;
    }

    textarea {
        min-height: 180px;
        resize: vertical;
    }

    .evidence-preview {
        margin-top: 2rem;
    }

    .evidence-preview img {
        max-width: 140px;
        margin-right: 20px;
        border-radius: 12px;
        border: 2px solid #e0e8f0;
        transition: transform 0.3s;
    }

    .evidence-preview img:hover {
        transform: scale(1.05);
    }

    .evidence-preview p {
        font-size: 1.2rem;
        color: #666;
        margin: 8px 0;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3498db, #2ecc71);
        border: none;
        padding: 18px 50px;
        font-size: 1.4rem;
        font-weight: 700;
        border-radius: 12px;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .btn-primary:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
    }

    .btn-outline-secondary {
        border-color: #7f8c8d;
        color: #7f8c8d;
        padding: 12px 30px;
        font-size: 1.2rem;
    }

    .btn-outline-secondary:hover {
        background: #7f8c8d;
        color: white;
    }

    .alert {
        border-radius: 12px;
        font-size: 1.2rem;
        padding: 15px;
    }

    .back-button {
        margin-top: 20px;
        text-align: right;
    }
</style>

<div class="complaint-container">
    <div class="row mb-3">
        <div class="col-12">
            <div class="complaint-header">
                <h2><i class="bi bi-exclamation-circle-fill"></i> Submit a Complaint</h2>
                <p>Report issues related to the election process</p>
            </div>
        </div>
        <div class="col-12 back-button">
            <a href="vote.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Voting
            </a>
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
            <div class="complaint-card">
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
                        <textarea class="form-control" id="description" name="description" rows="6" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidence_files" class="form-label">Evidence Files (Optional)</label>
                        <input type="file" class="form-control" id="evidence_files" name="evidence_files[]" multiple>
                        <div class="evidence-preview" id="evidencePreview"></div>
                    </div>

                    <button type="submit" name="submit_complaint" class="btn btn-primary">
                        <i class="bi bi-send-fill"></i> Submit Complaint
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
            img.style.maxWidth = '140px';
            img.style.marginRight = '20px';
            img.style.borderRadius = '12px';
            img.style.border = '2px solid #e0e8f0';
            preview.appendChild(img);
        } else {
            const p = document.createElement('p');
            p.textContent = file.name;
            p.style.fontSize = '1.2rem';
            p.style.color = '#666';
            p.style.margin = '8px 0';
            preview.appendChild(p);
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>