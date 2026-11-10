<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

require_once '../config/db.php';

$branch_id = null;
if (($_SESSION['user_role'] ?? '') === 'accountant') {
    $branch_id = filter_var($_SESSION['branch_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$branch_id || $branch_id < 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No branch is assigned to this account']);
        exit;
    }
}

$today = date('Y-m-d');

$sales_stmt = $conn->prepare(
    'SELECT COALESCE(SUM(total_amount), 0) AS today_sales,
            COALESCE(SUM(quantity), 0) AS fuel_sold
     FROM sales
     WHERE DATE(sale_date) = ?' . ($branch_id !== null ? ' AND branch_id = ?' : '')
);
if ($branch_id !== null) {
    $sales_stmt->bind_param('si', $today, $branch_id);
} else {
    $sales_stmt->bind_param('s', $today);
}
$sales_stmt->execute();
$sales = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();

$branches_result = $conn->query(
    "SELECT COUNT(*) AS count FROM branches WHERE status = 'active'"
    . ($branch_id !== null ? ' AND id = ' . (int)$branch_id : '')
);
$branches = $branches_result->fetch_assoc()['count'];

$low_stock_result = $conn->query(
    "SELECT COUNT(*) AS count
     FROM tanks
     WHERE current_quantity < minimum_threshold AND status = 'active'"
     . ($branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '')
);
$low_stock = (int)$low_stock_result->fetch_assoc()['count'];

$maintenance_result = $conn->query(
    "SELECT COUNT(*) AS count FROM pumps WHERE status = 'maintenance'"
    . ($branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '')
);
$maintenance = (int)$maintenance_result->fetch_assoc()['count'];

$pending_tasks = $low_stock + $maintenance;
if (in_array($_SESSION['user_role'] ?? '', ['admin', 'accountant'], true)) {
    $pending_result = $conn->query(
        "SELECT COUNT(*) AS count FROM expenses WHERE status = 'pending'"
        . ($branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '')
    );
    $pending_tasks += (int)$pending_result->fetch_assoc()['count'];
}

echo json_encode([
    'success' => true,
    'today_sales' => (float)$sales['today_sales'],
    'branches' => (int)$branches,
    'fuel_sold' => (float)$sales['fuel_sold'],
    'low_stock' => $low_stock,
    'pending_tasks' => $pending_tasks
]);
