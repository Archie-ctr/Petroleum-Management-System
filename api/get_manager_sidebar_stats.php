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

$stats_stmt = $conn->prepare(
    "SELECT
        (SELECT COUNT(*) FROM users
         WHERE branch_id = ? AND role = 'attendant' AND status = 'active') AS active_attendants,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales
         WHERE branch_id = ? AND status = 'completed'
           AND DATE_FORMAT(sale_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) AS monthly_revenue,
        ((SELECT COUNT(*) FROM expenses WHERE branch_id = ? AND status = 'pending') +
         (SELECT COUNT(*) FROM tanks
          WHERE branch_id = ? AND current_quantity < minimum_threshold AND status = 'active') +
         (SELECT COUNT(*) FROM pumps WHERE branch_id = ? AND status = 'maintenance')) AS branch_alerts"
);
$stats_stmt->bind_param('iiiii', $branch_id, $branch_id, $branch_id, $branch_id, $branch_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

echo json_encode([
    'success' => true,
    'active_attendants' => (int)$stats['active_attendants'],
    'monthly_revenue' => (float)$stats['monthly_revenue'],
    'branch_alerts' => (int)$stats['branch_alerts']
]);
