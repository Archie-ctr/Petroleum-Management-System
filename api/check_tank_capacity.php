<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tank_id = intval($_POST['tank_id']);
    $quantity = floatval($_POST['quantity']);
    
    $sql = "SELECT current_quantity, capacity FROM tanks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['current_quantity'] >= $quantity) {
            echo json_encode(['success' => true, 'available' => true]);
        } else {
            echo json_encode([
                'success' => true, 
                'available' => false,
                'available_quantity' => $row['current_quantity']
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tank not found']);
    }
}
?>