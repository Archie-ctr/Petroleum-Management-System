<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'godwilling_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Timezone setting
date_default_timezone_set('Africa/Nairobi');
if (!$conn->query("SET time_zone = '" . date('P') . "'")) {
    die("Failed to set database timezone: " . $conn->error);
}
?>