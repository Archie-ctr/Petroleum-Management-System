<?php
// ajax/get_sales_summary.php
session_start();
require_once '../../config/db.php';

$attendant_id = $_SESSION['user_id'];
$dateFrom = $_GET['dateFrom'] ?? date('Y-m-d');
$dateTo = $_GET['dateTo'] ?? date('Y-m-d');
$fuelType = $_GET['fuelType'] ?? '';
$paymentMethod = $_GET['paymentMethod'] ?? '';

// Build the query
$sql = "SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total_amount), 0) as total_amount,
        COALESCE(SUM(quantity), 0) as total_fuel
        FROM sales 
        WHERE attendant_id = ? 
        AND DATE(sale_date) BETWEEN ? AND ?";
$params = [$attendant_id, $dateFrom, $dateTo];
$types = "iss";

if ($fuelType) {
    $sql .= " AND fuel_type_id = ?";
    $params[] = $fuelType;
    $types .= "i";
}

if ($paymentMethod) {
    $sql .= " AND payment_method = ?";
    $params[] = $paymentMethod;
    $types .= "s";
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$summary = $result->fetch_assoc();

// Calculate average sale
$avg_sale = $summary['total_sales'] > 0 
    ? $summary['total_amount'] / $summary['total_sales'] 
    : 0;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'total_sales' => (int)$summary['total_sales'],
    'total_amount' => (float)$summary['total_amount'],
    'total_fuel' => (float)$summary['total_fuel'],
    'avg_sale' => round($avg_sale, 2)
]);
?>