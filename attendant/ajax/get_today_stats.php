<?php
session_start();
require_once '../../config/db.php';

$attendant_id = $_SESSION['user_id'];
$today = date('Y-m-d');

$stats_sql = "SELECT 
              COUNT(*) as sales_count,
              COALESCE(SUM(total_amount), 0) as sales_total,
              COALESCE(SUM(quantity), 0) as fuel_sold
              FROM sales 
              WHERE attendant_id = $attendant_id 
              AND DATE(sale_date) = '$today'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

echo json_encode([
    'success' => true,
    'sales_count' => $stats['sales_count'],
    'sales_total' => $stats['sales_total'],
    'fuel_sold' => $stats['fuel_sold']
]);
?>