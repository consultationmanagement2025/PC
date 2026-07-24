<?php
require __DIR__ . '/../db.php';

$id = 6;
$sql = "UPDATE users SET role = 'staff' WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . PHP_EOL;
    exit(1);
}
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    echo "Updated rows: " . $stmt->affected_rows . PHP_EOL;
} else {
    echo "Update failed: " . $stmt->error . PHP_EOL;
}
$stmt->close();

?>