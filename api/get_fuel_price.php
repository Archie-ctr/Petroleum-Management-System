<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fuel_id = intval($_POST['fuel_id']);
    
    $sql = "SELECT price_per_liter FROM fuel_types WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $fuel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'price' => $row['price_per_liter']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fuel type not found']);
    }
}
?>