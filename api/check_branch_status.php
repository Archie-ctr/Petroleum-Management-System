<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'manager') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$branch_id = filter_var($_SESSION['branch_id'] ?? null, FILTER_VALIDATE_INT);
if (!$branch_id || $branch_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Manager branch is not configured']);
    exit;
}

require_once '../config/db.php';

$low_stock_stmt = $conn->prepare(
    "SELECT COUNT(*) AS count
     FROM tanks
     WHERE branch_id = ? AND current_quantity < minimum_threshold AND status = 'active'"
);
$low_stock_stmt->bind_param('i', $branch_id);
$low_stock_stmt->execute();
$low_stock_count = (int)$low_stock_stmt->get_result()->fetch_assoc()['count'];
$low_stock_stmt->close();

$maintenance_stmt = $conn->prepare(
    "SELECT COUNT(*) AS count
     FROM pumps
     WHERE branch_id = ? AND status = 'maintenance'"
);
$maintenance_stmt->bind_param('i', $branch_id);
$maintenance_stmt->execute();
$maintenance_pumps = (int)$maintenance_stmt->get_result()->fetch_assoc()['count'];
$maintenance_stmt->close();

echo json_encode([
    'success' => true,
    'system_online' => $conn->ping(),
    'low_stock_count' => $low_stock_count,
    'maintenance_pumps' => $maintenance_pumps
]);
