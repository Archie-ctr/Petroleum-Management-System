<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

$users_result = $conn->query("SELECT COUNT(*) AS count FROM users WHERE status = 'active'");
$total_users = (int)$users_result->fetch_assoc()['count'];

$today = date('Y-m-d');
$revenue_stmt = $conn->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM sales
     WHERE DATE(sale_date) = ? AND status = 'completed'"
);
$revenue_stmt->bind_param('s', $today);
$revenue_stmt->execute();
$today_revenue = (float)$revenue_stmt->get_result()->fetch_assoc()['total'];
$revenue_stmt->close();

$alerts_result = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM expenses WHERE status = 'pending') +
        (SELECT COUNT(*) FROM tanks WHERE current_quantity < minimum_threshold AND status = 'active') +
        (SELECT COUNT(*) FROM pumps WHERE status = 'maintenance') AS total"
);
$system_alerts = (int)$alerts_result->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'total_users' => $total_users,
    'today_revenue' => $today_revenue,
    'system_alerts' => $system_alerts
]);
