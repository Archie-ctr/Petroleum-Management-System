<?php
// ajax/get_tanks.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
    exit;
}

// Get active tanks for the selected branch
$sql = "SELECT t.*, f.fuel_name, f.fuel_code 
        FROM tanks t 
        LEFT JOIN fuel_types f ON t.fuel_type_id = f.id 
        WHERE t.branch_id = ? AND t.status = 'active' 
        ORDER BY t.tank_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$tanks = [];
while ($row = $result->fetch_assoc()) {
    $tanks[] = [
        'id' => $row['id'],
        'tank_number' => $row['tank_number'],
        'fuel_name' => $row['fuel_name'],
        'fuel_code' => $row['fuel_code'],
        'capacity' => $row['capacity'],
        'current_quantity' => $row['current_quantity'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'tanks' => $tanks,
    'count' => count($tanks)
]);
?>