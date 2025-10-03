<?php
// Ensure $pdo is available from the included dashboard.php
if (!isset($pdo)) {
    echo '<div class="alert alert-danger">FATAL ERROR: Database connection missing. Cannot load audit logs.</div>';
    return;
}

$logs = [];
$error_message = '';

// --- Fetch Audit Logs ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            user_id, action, old_values, new_values, created_at
        FROM 
            audit_logs 
        ORDER BY 
            created_at DESC
        LIMIT 50 
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch audit logs. Please check the 'audit_logs' table and column names.";
    error_log("Audit Log Fetch Error: " . $e->getMessage());
}

/**
 * Helper function to format JSON data from audit logs into readable lists.
 * @param string|null $json The JSON string from the database.
 * @return string Formatted HTML list.
 */
function formatAuditData($json) {
    if (!$json || $json === 'NULL') {
        return '<span class="text-muted">N/A</span>';
    }
    
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return '<span class="text-danger">Invalid JSON</span>';
    }

    $html = '<ul class="list-unstyled mb-0 small">';
    
    // Check if the status is 'approved' for special handling of the 'reason' key
    $is_approved = (isset($data['status']) && strtolower($data['status']) === 'approved');

    foreach ($data as $key => $value) {
        
        // 📢 FIX 1: If status is 'approved', skip 'reason' entirely or show NULL
        if ($is_approved && strtolower($key) === 'reason') {
            // Option 1: Display 'reason: NULL' as requested
            $html .= "<li><strong>" . htmlspecialchars(ucfirst($key)) . ":</strong> <span class=\"text-muted\">NULL</span></li>";
            continue; 
            
            // Option 2: To hide the reason completely for approved statuses, use:
            // continue;
        }


        $value_str = is_array($value) ? implode(', ', $value) : (string)$value;
        
        // Highlight status changes
        $display_value = $value_str;
        if (strtolower($key) === 'status') {
            $class = match(strtolower($value_str)) {
                'approved' => 'text-success fw-bold',
                'rejected' => 'text-danger fw-bold',
                'pending' => 'text-warning fw-bold',
                default => 'text-info'
            };
            $display_value = "<span class=\"{$class}\">" . ucfirst($value_str) . "</span>";
        }

        $html .= "<li><strong>" . htmlspecialchars(ucfirst($key)) . ":</strong> {$display_value}</li>";
    }
    $html .= '</ul>';
    return $html;
}
?>

<div class="container-fluid">
    <h3 class="mb-4 text-info">🔎 System Audit Logs</h3>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info text-center mt-5">
            The audit log is currently empty.
        </div>
    <?php else: ?>
        <p class="text-secondary">Showing the **<?= count($logs) ?>** most recent system actions.</p>

        <div class="table-responsive shadow rounded">
            <table class="table table-hover table-striped mb-0 align-middle">
                
                <thead class="table-primary">
                    <tr>
                        <th style="width: 10%;">Time</th>
                        <th style="width: 10%;">User ID</th>
                        <th style="width: 25%;">Action</th>
                        <th style="width: 25%;">Old Values</th>
                        <th style="width: 30%;">New Values</th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                        <td><?= htmlspecialchars($log['user_id'] ?? 'System') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span></td>
                        <td><?= formatAuditData($log['old_values']) ?></td>
                        <td><?= formatAuditData($log['new_values']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>