<?php
/**
 * Script to fix employee password hash
 */

$db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

// Generate correct bcrypt hash for admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Generated hash for 'admin123': <br>";
echo "<code>" . $hash . "</code><br><br>";

// Update employee ID 1 with correct password
$stmt = $db->prepare("UPDATE employees SET password = ? WHERE id = 1");
$stmt->bind_param('s', $hash);

if ($stmt->execute()) {
    echo "<strong style='color: green;'>✓ Password updated successfully!</strong><br>";
    echo "Employee ID 1 password is now set to: <strong>admin123</strong>";
} else {
    echo "<strong style='color: red;'>✗ Error updating password: " . $stmt->error . "</strong>";
}

$stmt->close();
$db->close();
?>
