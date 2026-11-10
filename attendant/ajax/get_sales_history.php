<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

$attendant_id = $_SESSION['user_id'];
$dateFrom = $_GET['dateFrom'] ?? date('Y-m-d');
$dateTo = $_GET['dateTo'] ?? date('Y-m-d');
$fuelType = $_GET['fuelType'] ?? '';
$paymentMethod = $_GET['paymentMethod'] ?? '';

$sql = "SELECT s.*, f.fuel_name 
        FROM sales s
        JOIN fuel_types f ON s.fuel_type_id = f.id
        WHERE s.attendant_id = ? 
        AND DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$attendant_id, $dateFrom, $dateTo];

if ($fuelType) {
    $sql .= " AND s.fuel_type_id = ?";
    $params[] = $fuelType;
}

if ($paymentMethod) {
    $sql .= " AND s.payment_method = ?";
    $params[] = $paymentMethod;
}

$sql .= " ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($sql);

// Bind parameters based on count
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);
exit();
?>