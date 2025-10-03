<?php
// hall/verify_voters.php
$page_title = "Hall Voter Verification";
require_once '../includes/check_auth.php';
requireRole('hall_commissioner');

$current_user = getCurrentUser();
$hall_name = $current_user['hall_name'];

$success = '';
$error = '';

// Handle bulk approval
if (isset($_POST['bulk_approve'])) {
    $voter_ids = $_POST['voter_ids'] ?? [];
    if (!empty($voter_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($voter_ids), '?'));
            $params = array_merge($voter_ids, [$hall_name]);
            
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1 
                WHERE id IN ($placeholders) AND hall_name = ?
            ");
            $update_stmt->execute($params);
            
            $count = $update_stmt->rowCount();
            
            // Log action
            foreach ($voter_ids as $voter_id) {
                $log_stmt = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                    VALUES (?, 'VERIFY_VOTER', 'users', ?, ?)
                ");
                $log_stmt->execute([
                    $current_user['id'], 
                    $voter_id, 
                    json_encode(['verified' => true, 'verified_by' => $current_user['id']])
                ]);
            }
            
            $success = "Successfully verified $count voters!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
        
        // Refresh the page to update the list
        header("Refresh:0");
    }
}

// Handle single approval/rejection
if (isset($_POST['action']) && isset($_POST['voter_id'])) {
    $voter_id = (int)$_POST['voter_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1 
                WHERE id = ? AND hall_name = ?
            ");
            $update_stmt->execute([$voter_id, $hall_name]);
            
            // Log
            $log_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                VALUES (?, 'APPROVE_VOTER', 'users', ?, ?)
            ");
            $log_stmt->execute([
                $current_user['id'], 
                $voter_id, 
                json_encode(['verified' => true])
            ]);
            
            $success = 'Voter approved successfully!';
            
        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? 'No reason provided';
            
            // Mark as inactive instead of keeping pending
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET is_active = 0 
                WHERE id = ? AND hall_name = ?
            ");
            $update_stmt->execute([$voter_id, $hall_name]);
            
            // Log rejection
            $log_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                VALUES (?, 'REJECT_VOTER', 'users', ?, ?)
            ");
            $log_stmt->execute([
                $current_user['id'], 
                $voter_id, 
                json_encode(['rejected' => true, 'reason' => $reason])
            ]);
            
            $success = 'Voter rejected!';
        }
        
        // Refresh to show updated list
        header("Refresh:0");
        
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Pagination and search setup
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'pending';

$search_param = '%' . $search . '%';

// Fetch voters based on selected view
if ($view === 'pending') {
    $pending_stmt = $pdo->prepare("
        SELECT id, university_id, full_name, department, enrollment_year, gender, phone, created_at
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_verified = 0 AND is_active = 1
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $pending_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $pending_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $pending_stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $pending_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $pending_stmt->execute();
    $voters = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_verified = 0 AND is_active = 1
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
    ");
    $total_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $total_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $total_stmt->execute();
    $total_voters = $total_stmt->fetch()['total'];
    $total_pages = max(1, ceil($total_voters / $per_page));
} elseif ($view === 'approved') {
    $approved_stmt = $pdo->prepare("
        SELECT id, university_id, full_name, department, enrollment_year, gender, phone, created_at
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_verified = 1 AND is_active = 1
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $approved_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $approved_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $approved_stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $approved_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $approved_stmt->execute();
    $voters = $approved_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_verified = 1 AND is_active = 1
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
    ");
    $total_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $total_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $total_stmt->execute();
    $total_voters = $total_stmt->fetch()['total'];
    $total_pages = max(1, ceil($total_voters / $per_page));
} else { // rejected
    $rejected_stmt = $pdo->prepare("
        SELECT id, university_id, full_name, department, enrollment_year, gender, phone, created_at
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_active = 0
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $rejected_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $rejected_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $rejected_stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $rejected_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $rejected_stmt->execute();
    $voters = $rejected_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM users 
        WHERE hall_name = :hall_name AND role = 'voter' AND is_active = 0
        AND (full_name LIKE :search_param OR university_id LIKE :search_param)
    ");
    $total_stmt->bindParam(':hall_name', $hall_name, PDO::PARAM_STR);
    $total_stmt->bindParam(':search_param', $search_param, PDO::PARAM_STR);
    $total_stmt->execute();
    $total_voters = $total_stmt->fetch()['total'];
    $total_pages = max(1, ceil($total_voters / $per_page));
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Enhanced Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
            <div class="position-sticky pt-3">
                <h5 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Hall Commissioner</span>
                </h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'page' : ''; ?>">
                            <i class="bi bi-house-door me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $view === 'pending' ? 'active' : ''; ?>" href="?view=pending&search=<?php echo urlencode($search); ?>" aria-current="<?php echo $view === 'pending' ? 'page' : ''; ?>">
                            <i class="bi bi-person-check me-2"></i> Pending Voters
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $view === 'approved' ? 'active' : ''; ?>" href="?view=approved&search=<?php echo urlencode($search); ?>" aria-current="<?php echo $view === 'approved' ? 'page' : ''; ?>">
                            <i class="bi bi-person-check-fill me-2"></i> Approved Voters
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $view === 'rejected' ? 'active' : ''; ?>" href="?view=rejected&search=<?php echo urlencode($search); ?>" aria-current="<?php echo $view === 'rejected' ? 'page' : ''; ?>">
                            <i class="bi bi-person-x-fill me-2"></i> Rejected Voters
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Voter Verification</h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($hall_name); ?></p>
                </div>
                <button class="btn btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-expanded="false" aria-controls="sidebarMenu">
                    <i class="bi bi-list"></i> Menu
                </button>
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

            <form method="GET" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or university ID" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>

            <div class="card mb-4">
                <div class="card-body p-0">
                    <?php if (empty($voters)): ?>
                        <div class="text-center py-5 text-muted">
                            <?php if ($view === 'pending'): ?>
                                <i class="bi bi-check-circle fs-1 text-success"></i>
                                <p class="mt-2 mb-0">No pending verifications</p>
                                <small>All voters have been verified</small>
                            <?php elseif ($view === 'approved'): ?>
                                <i class="bi bi-info-circle fs-1"></i>
                                <p class="mt-2 mb-0">No approved voters</p>
                            <?php else: ?>
                                <i class="bi bi-info-circle fs-1"></i>
                                <p class="mt-2 mb-0">No rejected voters</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <?php if ($view === 'pending'): ?>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                        <?php endif; ?>
                                        <th>University ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Year</th>
                                        <th>Gender</th>
                                        <th>Registered</th>
                                        <?php if ($view === 'pending'): ?>
                                            <th style="width: 160px;">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voters as $voter): ?>
                                        <tr>
                                            <?php if ($view === 'pending'): ?>
                                                <td>
                                                    <input type="checkbox" name="voter_ids[]" 
                                                           value="<?php echo $voter['id']; ?>" 
                                                           class="selectVoter form-check-input">
                                                </td>
                                            <?php endif; ?>
                                            <td><strong><?php echo htmlspecialchars($voter['university_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($voter['department']); ?></small>
                                            </td>
                                            <td><?php echo $voter['enrollment_year']; ?></td>
                                            <td><?php echo ucfirst($voter['gender']); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($voter['created_at'])); ?>
                                                </small>
                                            </td>
                                            <?php if ($view === 'pending'): ?>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Approve this voter?');">
                                                        <input type="hidden" name="voter_id" value="<?php echo $voter['id']; ?>">
                                                        <button type="submit" name="action" value="approve" 
                                                                class="btn btn-sm btn-success" title="Approve">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejectModal" 
                                                            data-voter-id="<?php echo $voter['id']; ?>"
                                                            data-voter-name="<?php echo htmlspecialchars($voter['full_name']); ?>"
                                                            title="Reject">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Voters Pagination -->
            <nav aria-label="Voters Pagination">
                <ul class="pagination justify-content-center mt-2">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?view=<?php echo $view; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?view=<?php echo $view; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?view=<?php echo $view; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <?php if ($view === 'pending'): ?>
                <form method="POST" id="bulkForm">
                    <button type="submit" name="bulk_approve" class="btn btn-success btn-sm mb-4" id="bulkApproveBtn" disabled>
                        <i class="bi bi-check-all"></i> Approve Selected (<span id="selectedCount">0</span>)
                    </button>
                </form>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Reject Voter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="voter_id" id="rejectVoterId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Rejecting will deactivate this voter's account.
                    </div>
                    
                    <p>Voter: <strong id="rejectVoterName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" 
                                  placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Voter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom CSS for Sidebar -->
<style>
.sidebar {
    height: 100vh;
    border-right: 1px solid #dee2e6;
}
.sidebar-heading {
    font-size: 0.875rem;
    text-transform: uppercase;
}
.nav-link {
    color: #333;
    transition: background-color 0.2s, color 0.2s;
}
.nav-link:hover {
    background-color: #f8f9fa;
    color: #007bff;
}
.nav-link.active {
    background-color: #007bff;
    color: white !important;
    border-radius: 0.25rem;
}
.nav-link.active:hover {
    background-color: #0056b3;
}
@media (max-width: 767.98px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        width: 250px;
        height: 100%;
        overflow-y: auto;
    }
}
</style>

<script>
// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function(e) {
    document.querySelectorAll('.selectVoter').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
    toggleBulkButton();
});

// Individual checkbox change
document.querySelectorAll('.selectVoter').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBulkButton);
});

// Toggle bulk approve button
function toggleBulkButton() {
    const checkedCount = document.querySelectorAll('.selectVoter:checked').length;
    const bulkBtn = document.getElementById('bulkApproveBtn');
    const countSpan = document.getElementById('selectedCount');
    
    if (bulkBtn) {
        bulkBtn.disabled = checkedCount === 0;
        countSpan.textContent = checkedCount;
    }
}

// Reject modal - populate voter info
const rejectModal = document.getElementById('rejectModal');
rejectModal?.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const voterId = button.getAttribute('data-voter-id');
    const voterName = button.getAttribute('data-voter-name');
    
    document.getElementById('rejectVoterId').value = voterId;
    document.getElementById('rejectVoterName').textContent = voterName;
});

// Bulk approve confirmation
document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.selectVoter:checked').length;
    if (!confirm(`Are you sure you want to approve ${count} voter(s)?`)) {
        e.preventDefault();
    }
});
</script>

<?php include '../includes/footer.php'; ?>