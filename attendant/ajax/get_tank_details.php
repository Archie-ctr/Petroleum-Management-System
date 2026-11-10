<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';

$tank_id = filter_input(INPUT_GET, 'tank_id', FILTER_VALIDATE_INT);
if (!$tank_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid tank is required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT t.id, t.tank_number, t.capacity, t.current_quantity, t.minimum_threshold,
            t.last_refill, t.status, f.fuel_name
     FROM tanks t
     JOIN fuel_types f ON f.id = t.fuel_type_id
     WHERE t.id = ? AND t.branch_id = ? AND t.status = 'active'"
);
$stmt->bind_param('ii', $tank_id, $_SESSION['branch_id']);
$stmt->execute();
$tank = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tank) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tank not found']);
    exit;
}

$capacity = (float)$tank['capacity'];
$current_quantity = (float)$tank['current_quantity'];
$percentage = $capacity > 0 ? ($current_quantity / $capacity) * 100 : 0;
$status_class = $percentage < 20
    ? 'danger'
    : ($current_quantity < (float)$tank['minimum_threshold'] ? 'warning' : 'success');
$status_text = $status_class === 'danger'
    ? 'Critical'
    : ($status_class === 'warning' ? 'Low' : 'Good');
$status_icon = $status_class === 'danger'
    ? 'exclamation-triangle'
    : ($status_class === 'warning' ? 'exclamation-circle' : 'check-circle');

$activity_stmt = $conn->prepare(
    'SELECT il.transaction_type AS type, il.quantity, il.log_date AS timestamp,
            il.notes, u.full_name AS attendant_name
     FROM inventory_logs il
     LEFT JOIN users u ON u.id = il.performed_by
     WHERE il.tank_id = ? AND il.branch_id = ?
     ORDER BY il.log_date DESC
     LIMIT 10'
);
$activity_stmt->bind_param('ii', $tank_id, $_SESSION['branch_id']);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$activity = [];
while ($row = $activity_result->fetch_assoc()) {
    $row['quantity'] = (float)$row['quantity'];
    $activity[] = $row;
}
$activity_stmt->close();

echo json_encode([
    'success' => true,
    'current_quantity' => $current_quantity,
    'capacity' => $capacity,
    'tank' => [
        'id' => (int)$tank['id'],
        'tank_number' => $tank['tank_number'],
        'fuel_name' => $tank['fuel_name'],
        'capacity' => $capacity,
        'current_quantity' => $current_quantity,
        'minimum_threshold' => (float)$tank['minimum_threshold'],
        'last_refill' => $tank['last_refill'],
        'percentage' => $percentage,
        'status_class' => $status_class,
        'status_text' => $status_text,
        'status_icon' => $status_icon
    ],
    'activity' => $activity
]);
