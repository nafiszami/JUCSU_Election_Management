<?php
require_once 'connection.php';
echo "✅ Database connected successfully!<br>";
echo "📊 Testing tables...<br>";

$tables = ['users', 'halls', 'positions', 'hall_positions', 'candidates', 'votes', 'election_schedule'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
    $result = $stmt->fetch();
    echo "✓ Table '$table': {$result['count']} records<br>";
}
?>