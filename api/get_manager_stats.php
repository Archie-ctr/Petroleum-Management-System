<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : $_SESSION['branch_id'];
$today = date('Y-m-d');

try {
    // Today's sales
    $sales_sql = "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
                  FROM sales 
                  WHERE branch_id = ? AND DATE(sale_date) = ?";
    $sales_stmt = $conn->prepare($sales_sql);
    $sales_stmt->bind_param("is", $branch_id, $today);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    $sales_data = $sales_result->fetch_assoc();
    $sales_stmt->close();
    
    // Active attendants
    $attendants_sql = "SELECT COUNT(*) as count FROM users 
                       WHERE branch_id = ? AND role = 'attendant' AND status = 'active'";
    $attendants_stmt = $conn->prepare($attendants_sql);
    $attendants_stmt->bind_param("i", $branch_id);
    $attendants_stmt->execute();
    $attendants_result = $attendants_stmt->get_result();
    $attendants_data = $attendants_result->fetch_assoc();
    $attendants_stmt->close();
    
    // Fuel sold today
    $fuel_sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM sales 
                 WHERE branch_id = ? AND DATE(sale_date) = ?";
    $fuel_stmt = $conn->prepare($fuel_sql);
    $fuel_stmt->bind_param("is", $branch_id, $today);
    $fuel_stmt->execute();
    $fuel_result = $fuel_stmt->get_result();
    $fuel_data = $fuel_result->fetch_assoc();
    $fuel_stmt->close();
    
    // Low stock tanks
    $low_stock_sql = "SELECT COUNT(*) as count FROM tanks 
                      WHERE branch_id = ? AND current_quantity < minimum_threshold AND status = 'active'";
    $low_stock_stmt = $conn->prepare($low_stock_sql);
    $low_stock_stmt->bind_param("i", $branch_id);
    $low_stock_stmt->execute();
    $low_stock_result = $low_stock_stmt->get_result();
    $low_stock_data = $low_stock_result->fetch_assoc();
    $low_stock_stmt->close();
    
    // Pending expenses
    $expenses_sql = "SELECT COUNT(*) as count FROM expenses 
                     WHERE branch_id = ? AND status = 'pending'";
    $expenses_stmt = $conn->prepare($expenses_sql);
    $expenses_stmt->bind_param("i", $branch_id);
    $expenses_stmt->execute();
    $expenses_result = $expenses_stmt->get_result();
    $expenses_data = $expenses_result->fetch_assoc();
    $expenses_stmt->close();
    
    // Pending tasks
    $pending_tasks = $low_stock_data['count'] + $expenses_data['count'];
    
    echo json_encode([
        'success' => true,
        'today_sales' => floatval($sales_data['total']),
        'today_transactions' => intval($sales_data['count']),
        'attendants' => intval($attendants_data['count']),
        'fuel_sold' => floatval($fuel_data['total']),
        'low_stock' => intval($low_stock_data['count']),
        'pending_expenses' => intval($expenses_data['count']),
        'pending_tasks' => $pending_tasks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching stats: ' . $e->getMessage()
    ]);
}
?>