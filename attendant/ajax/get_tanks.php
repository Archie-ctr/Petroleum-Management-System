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

// Get tanks for this branch
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
$summary = [
    'total_tanks' => 0,
    'total_capacity' => 0,
    'current_stock' => 0,
    'low_stock_alerts' => 0
];

while($tank = $result->fetch_assoc()) {
    $tank['id'] = (int)$tank['id'];
    $tank['capacity'] = (float)$tank['capacity'];
    $tank['current_quantity'] = (float)$tank['current_quantity'];
    $tank['minimum_threshold'] = (float)$tank['minimum_threshold'];
    $tanks[] = $tank;
    $summary['total_tanks']++;
    $summary['total_capacity'] += $tank['capacity'];
    $summary['current_stock'] += $tank['current_quantity'];
    
    $percentage = ($tank['current_quantity'] / $tank['capacity']) * 100;
    if ($tank['current_quantity'] < $tank['minimum_threshold']) {
        $summary['low_stock_alerts']++;
    }
}

echo json_encode([
    'success' => true,
    'tanks' => $tanks,
    'summary' => $summary
]);
?>