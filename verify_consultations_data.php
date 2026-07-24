<?php
/**
 * Verify Consultations Data
 */

require_once 'db.php';

// Query consultations
$result = $conn->query("SELECT id, title, status FROM consultations LIMIT 10");

if (!$result) {
    echo "Error: " . $conn->error;
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Verification</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
    <h1>Consultations in Database</h1>
    
    <?php if ($result->num_rows > 0): ?>
        <p>Found <strong><?php echo $result->num_rows; ?></strong> consultations:</p>
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['title']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p style="color: red; font-weight: bold;">No consultations found. <a href="create_sample_consultations.php">Create sample data</a></p>
    <?php endif; ?>
    
    <p><a href="public-portal.php">← Back to Portal</a></p>
</body>
</html>
