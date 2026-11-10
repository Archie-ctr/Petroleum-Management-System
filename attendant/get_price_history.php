<?php
session_start();
require_once '../../config/db.php';

$fuel_id = $_GET['fuel_id'] ?? 0;

// Get detailed price history for specific fuel
$history_sql = "SELECT 
                price,
                effective_date,
                created_by,
                reason
                FROM price_history 
                WHERE fuel_type_id = ?
                AND effective_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ORDER BY effective_date DESC";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $fuel_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode([
    'success' => true,
    'history' => $history
]);
?>