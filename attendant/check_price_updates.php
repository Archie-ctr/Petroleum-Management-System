<?php
session_start();
require_once '../../config/db.php';

// Check if there are any price updates in the last 5 minutes
$check_sql = "SELECT COUNT(*) as update_count 
              FROM price_history 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

echo json_encode([
    'has_updates' => $row['update_count'] > 0,
    'update_count' => $row['update_count']
]);
?>