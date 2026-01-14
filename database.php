<?php
// Database Configuration
$db = new mysqli('localhost', 'root', 'G3P+JANpr2GK6fax', 'ipms_lgu');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Set charset to utf8mb4
$db->set_charset("utf8mb4");
?>
