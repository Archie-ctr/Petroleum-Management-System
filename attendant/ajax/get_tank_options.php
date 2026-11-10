<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$branch_id = $_SESSION['branch_id'];

$sql = "SELECT t.*, f.fuel_name 
        FROM tanks t
        JOIN fuel_types f ON t.fuel_type_id = f.id
        WHERE t.branch_id = ? AND t.status = 'active'
        ORDER BY t.tank_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$tanks = [];
while($tank = $result->fetch_assoc()) {
    $tanks[] = $tank;
}

echo json_encode($tanks);
?>