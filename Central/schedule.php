<?php
// Ensure $pdo is available from the included dashboard.php
if (!isset($pdo)) {
    // This check should ideally be redundant if dashboard.php is correct
    echo '<div class="alert alert-danger">FATAL ERROR: Database connection missing. Cannot proceed.</div>';
    return;
}

// Initialize variables for status messages
$current_schedule = null;
$error_message = '';
$success_message = '';
$is_schedule_set = false;

// =======================================================
// 1. HANDLE FORM SUBMISSION (If this script was POSTed to itself)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? 'insert';
    $data = [
        'nomination_start'    => $_POST['nomination_start'] ?? null,
        'nomination_end'      => $_POST['nomination_end'] ?? null,
        'withdrawal_deadline' => $_POST['withdrawal_deadline'] ?? null,
        'voting_date'         => $_POST['voting_date'] ?? null,
        'result_declaration'  => !empty($_POST['result_declaration']) ? $_POST['result_declaration'] : null,
    ];

    // Server-Side Validation
    $errors = [];
    $nomStart = strtotime($data['nomination_start']);
    $nomEnd = strtotime($data['nomination_end']);
    $withdraw = strtotime($data['withdrawal_deadline']);
    $voting = strtotime($data['voting_date']);
    
    // Validation: nomination_end > nomination_start
    if ($nomEnd <= $nomStart) {
        $errors[] = "Nomination End date must be *after* Nomination Start date.";
    }
    // Validation: withdrawal_deadline > nomination_end
    if ($withdraw <= $nomEnd) {
        $errors[] = "Withdrawal Deadline must be *after* Nomination End date.";
    }
    // Validation: voting_date > withdrawal_deadline
    if ($voting <= $withdraw) {
        $errors[] = "Voting Date must be *after* Withdrawal Deadline.";
    }

    if (empty($errors)) {
        // Proceed with Database Operation
        try {
            $pdo->beginTransaction();

            // Deactivate the currently active schedule before inserting the new one
            $deactivate_stmt = $pdo->prepare("UPDATE election_schedule SET is_active = FALSE WHERE election_type = 'jucsu' AND is_active = TRUE");
            $deactivate_stmt->execute();
            
            // Always INSERT a new record to create a historical log and the new active schedule
            $sql = "INSERT INTO election_schedule (
                        election_type, academic_year, nomination_start, nomination_end, 
                        withdrawal_deadline, voting_date, result_declaration, is_active
                    ) VALUES (
                        'jucsu', :academic_year, :nom_start, :nom_end, 
                        :withdrawal, :voting, :result, TRUE
                    )";

            $stmt = $pdo->prepare($sql);
            
            // Determine academic year (using current year and next year)
            $current_academic_year = date('Y') . '-' . (date('y') + 1); 

            $stmt->execute([
                ':academic_year' => $current_academic_year,
                ':nom_start'     => $data['nomination_start'],
                ':nom_end'       => $data['nomination_end'],
                ':withdrawal'    => $data['withdrawal_deadline'],
                ':voting'        => $data['voting_date'],
                ':result'        => $data['result_declaration'],
            ]);

            $pdo->commit();
            $success_message = "Election schedule successfully " . ($action === 'update' ? "updated" : "set") . "!";
            
            // Set flag to force a re-fetch of the new data immediately
            $refetch_needed = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Schedule DB Error: " . $e->getMessage());
            $error_message = "A database error occurred. The schedule was not saved.";
        }
    } else {
        // Concatenate all validation errors
        $error_message = implode('<br>', $errors);
    }
}


// =======================================================
// 2. FETCH LATEST ACTIVE SCHEDULE (Executed on GET or after successful POST)
// =======================================================
try {
    // SQL to fetch the latest active JUCUSU election schedule.
    $stmt = $pdo->prepare("
        SELECT 
            nomination_start, nomination_end, withdrawal_deadline, voting_date, result_declaration, updated_at
        FROM 
            election_schedule 
        WHERE 
            election_type = 'jucsu' AND is_active = TRUE
        ORDER BY 
            created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $current_schedule = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Append database error if fetching failed
    $error_message .= (empty($error_message) ? "" : "<br>") . "Database error: Could not fetch schedule.";
}

$is_schedule_set = (bool)$current_schedule;
?>

<div class="container-fluid">
    <h3 class="mb-4 text-success">📅 JUCU Election Schedule Management</h3>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h3 class="mb-3 text-secondary">🗓️ Current Election Timeline</h3>

    <?php if ($is_schedule_set): ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <strong>Active Schedule (Last updated: <?= date('Y-m-d H:i', strtotime($current_schedule['updated_at'])) ?>)</strong>
            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#editScheduleForm" aria-expanded="false" aria-controls="editScheduleForm">
                <i class="bi bi-pencil-square"></i> Edit Schedule
            </button>
        </div>
        
        <div class="row g-4 mb-5">
            
            <div class="col-md-6">
                <div class="p-3 shadow rounded border-start border-5 border-warning bg-light-orange-custom">
                    <h5 class="text-warning">Nomination Period</h5>
                    <p class="mb-0"><strong>Start:</strong> <?= htmlspecialchars($current_schedule['nomination_start']) ?></p>
                    <p class="mb-0"><strong>End:</strong> <?= htmlspecialchars($current_schedule['nomination_end']) ?></p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="p-3 shadow rounded border-start border-5 border-primary bg-light-blue-custom">
                    <h5 class="text-primary">Withdrawal Deadline</h5>
                    <p class="mb-0"><strong>Date:</strong> <?= htmlspecialchars($current_schedule['withdrawal_deadline']) ?></p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="p-3 shadow rounded border-start border-5 border-success bg-light-green-custom">
                    <h5 class="text-success">Voting Date</h5>
                    <p class="mb-0"><strong>Date:</strong> <?= htmlspecialchars($current_schedule['voting_date']) ?></p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="p-3 shadow rounded border-start border-5 border-danger bg-light-red-custom">
                    <h5 class="text-danger">Result Declaration</h5>
                    <p class="mb-0"><strong>Date:</strong> <?= htmlspecialchars($current_schedule['result_declaration']) ?: 'Not yet set' ?></p>
                </div>
            </div>
            
        </div>
        
    <?php else: ?>
        <div class="alert alert-warning text-center d-flex justify-content-between align-items-center mb-5">
            <strong>The election schedule has not been set yet.</strong>
            <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#editScheduleForm" aria-expanded="true" aria-controls="editScheduleForm">
                <i class="bi bi-calendar-plus"></i> Set Schedule Now
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow border-info collapse <?= !$is_schedule_set ? 'show' : '' ?>" id="editScheduleForm">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><?= $is_schedule_set ? 'Edit Schedule' : 'Set New Election Dates' ?></h5>
        </div>
        <div class="card-body">
            <form id="schedule-form" method="POST" action="dashboard.php?page=schedule">
                
                <input type="hidden" name="action" value="<?= $is_schedule_set ? 'update' : 'insert' ?>">
                
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label for="nomination_start" class="form-label">Nomination Start Date</label>
                        <input type="date" class="form-control" id="nomination_start" name="nomination_start" 
                               value="<?= htmlspecialchars($current_schedule['nomination_start'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="nomination_end" class="form-label">Nomination End Date</label>
                        <input type="date" class="form-control" id="nomination_end" name="nomination_end"
                               value="<?= htmlspecialchars($current_schedule['nomination_end'] ?? '') ?>" required>
                        <div class="invalid-feedback" id="nomination-end-error">Nomination End must be after Nomination Start.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="withdrawal_deadline" class="form-label">Withdrawal Deadline</label>
                        <input type="date" class="form-control" id="withdrawal_deadline" name="withdrawal_deadline"
                               value="<?= htmlspecialchars($current_schedule['withdrawal_deadline'] ?? '') ?>" required>
                        <div class="invalid-feedback" id="withdrawal-error">Withdrawal must be after Nomination End.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="voting_date" class="form-label">Voting Date</label>
                        <input type="date" class="form-control" id="voting_date" name="voting_date"
                               value="<?= htmlspecialchars($current_schedule['voting_date'] ?? '') ?>" required>
                        <div class="invalid-feedback" id="voting-date-error">Voting Date must be after Withdrawal Deadline.</div>
                    </div>

                    <div class="col-12">
                        <label for="result_declaration" class="form-label">Result Declaration Date (Optional)</label>
                        <input type="date" class="form-control" id="result_declaration" name="result_declaration"
                               value="<?= htmlspecialchars($current_schedule['result_declaration'] ?? '') ?>">
                        <div class="form-text">Leave blank to declare results later.</div>
                    </div>

                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-calendar-check"></i> <?= $is_schedule_set ? 'Save Changes' : 'Set Schedule' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Custom CSS for the specific light background colors (Remains here) */
    .bg-light-orange-custom { background-color: #ffe5cc; }
    .bg-light-blue-custom   { background-color: #ccf3ff; }
    .bg-light-green-custom  { background-color: #ccffe5; }
    .bg-light-red-custom    { background-color: #ffcccc; }
</style>

<script>
    document.getElementById('schedule-form').addEventListener('submit', function(e) {
        
        let nomStart = document.getElementById('nomination_start').value;
        let nomEnd = document.getElementById('nomination_end').value;
        let withdraw = document.getElementById('withdrawal_deadline').value;
        let voting = document.getElementById('voting_date').value;

        let isValid = true;
        
        // Helper function to validate dates
        const validateDate = (elementId, compareValue, errorMessageId) => {
            const element = document.getElementById(elementId);
            const dateValue = element.value;
            // Condition for failure: Date must be STRICTLY AFTER the compare value
            const condition = new Date(dateValue) <= new Date(compareValue); 

            if (condition) {
                element.classList.add('is-invalid');
                element.classList.remove('is-valid');
                isValid = false;
            } else {
                element.classList.remove('is-invalid');
                if (dateValue) {
                    element.classList.add('is-valid');
                }
            }
        };

        // 1. Nomination End > Nomination Start
        validateDate('nomination_end', nomStart, 'nomination-end-error');

        // 2. Withdrawal Deadline > Nomination End
        validateDate('withdrawal_deadline', nomEnd, 'withdrawal-error');

        // 3. Voting Date > Withdrawal Deadline
        validateDate('voting_date', withdraw, 'voting-date-error');

        if (!isValid) {
            e.preventDefault(); // Stop form submission if validation fails
            // Scroll to the first error to guide the user
            document.querySelector('.is-invalid').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Note: The form submission will trigger a full page reload,
        // which runs the PHP logic at the top of this file.
    });
</script>