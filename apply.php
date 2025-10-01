<?php
session_start();
require_once "db_connection.php"; // your PDO connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch positions for dropdown
$stmt = $pdo->query("SELECT position_id, position_name, election_type FROM positions ORDER BY election_type, sort_order");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $position_id = $_POST['position_id'];
    $proposer_id = $_POST['proposer_id'];
    $seconder_id = $_POST['seconder_id'];
    $manifesto = $_POST['manifesto'];

    // File upload (photo)
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/candidates/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $photo = $target_dir . basename($_FILES["photo"]["name"]);
        move_uploaded_file($_FILES["photo"]["tmp_name"], $photo);
    }

    // Insert candidate application
    $sql = "INSERT INTO candidates 
            (user_id, position_id, proposer_id, seconder_id, manifesto, photo, status) 
            VALUES (:user_id, :position_id, :proposer_id, :seconder_id, :manifesto, :photo, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':position_id' => $position_id,
        ':proposer_id' => $proposer_id,
        ':seconder_id' => $seconder_id,
        ':manifesto' => $manifesto,
        ':photo' => $photo
    ]);

    $message = "✅ Application submitted successfully! Waiting for approval.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Apply as Candidate</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Apply as Candidate</h2>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <form action="apply_candidate.php" method="POST" enctype="multipart/form-data">
        <label for="position_id">Select Position:</label><br>
        <select name="position_id" required>
            <?php foreach ($positions as $pos): ?>
                <option value="<?php echo $pos['position_id']; ?>">
                    <?php echo $pos['position_name'] . " (" . strtoupper($pos['election_type']) . ")"; ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="proposer_id">Proposer (User ID):</label><br>
        <input type="number" name="proposer_id" required><br><br>

        <label for="seconder_id">Seconder (User ID):</label><br>
        <input type="number" name="seconder_id" required><br><br>

        <label for="manifesto">Manifesto:</label><br>
        <textarea name="manifesto" rows="5" required></textarea><br><br>

        <label for="photo">Upload Photo:</label><br>
        <input type="file" name="photo" accept="image/*" required><br><br>

        <button type="submit">Submit Application</button>
    </form>
</body>
</html>
