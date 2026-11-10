<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is attendant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'attendant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$attendant_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'quick_stats':
        $stats_sql = "SELECT 
                      COUNT(*) as sales_today,
                      COALESCE(SUM(total_amount), 0) as revenue_today,
                      COALESCE(SUM(quantity), 0) as fuel_today
                      FROM sales 
                      WHERE attendant_id = ? 
                      AND DATE(sale_date) = ?";
        $stmt = $conn->prepare($stats_sql);
        $stmt->bind_param("is", $attendant_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'sales_today' => $stats['sales_today'] ?? 0,
            'revenue_today' => $stats['revenue_today'] ?? 0,
            'fuel_today' => $stats['fuel_today'] ?? 0
        ]);
        break;
        
    case 'refresh_stats':
        $stats_sql = "SELECT 
                      COUNT(*) as sales_count,
                      COALESCE(SUM(total_amount), 0) as sales_total,
                      COALESCE(SUM(quantity), 0) as fuel_sold,
                      COALESCE(AVG(total_amount), 0) as avg_transaction
                      FROM sales 
                      WHERE attendant_id = ? 
                      AND DATE(sale_date) = ?";
        $stmt = $conn->prepare($stats_sql);
        $stmt->bind_param("is", $attendant_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'sales_count' => $stats['sales_count'] ?? 0,
            'sales_total' => $stats['sales_total'] ?? 0,
            'fuel_sold' => $stats['fuel_sold'] ?? 0,
            'avg_transaction' => $stats['avg_transaction'] ?? 0
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>