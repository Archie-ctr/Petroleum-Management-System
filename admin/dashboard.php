<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include header first
require_once '../includes/header.php';

// Check admin access after header
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your connection settings.");
}

// Get dashboard statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

// Initialize stats array
$stats = [
    'today_sales' => 0,
    'today_transactions' => 0,
    'yesterday_sales' => 0,
    'month_sales' => 0,
    'month_transactions' => 0,
    'last_month_sales' => 0,
    'branches' => 0,
    'users' => 0,
    'attendants' => 0,
    'low_stock' => 0,
    'total_tanks' => 0,
    'total_capacity' => 0,
    'current_fuel' => 0,
    'fuel_percentage' => 0,
    'pending_expenses' => 0,
    'pending_amount' => 0,
    'sales_growth_today' => 0,
    'sales_growth_month' => 0,
    'total_customers' => 0,
    'avg_transaction' => 0
];

// Helper function for safe queries
function getSingleValue($conn, $sql, $default = 0) {
    try {
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_array()) {
            return $row[0];
        }
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
    }
    return $default;
}

// Get statistics
$stats['today_sales'] = getSingleValue($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = '$today'");
$stats['today_transactions'] = getSingleValue($conn, "SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = '$today'");
$stats['yesterday_sales'] = getSingleValue($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = '$yesterday'");
$stats['month_sales'] = getSingleValue($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$this_month'");
$stats['month_transactions'] = getSingleValue($conn, "SELECT COUNT(*) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$this_month'");
$stats['last_month_sales'] = getSingleValue($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$last_month'");
$stats['branches'] = getSingleValue($conn, "SELECT COUNT(*) FROM branches WHERE status = 'active'");
$stats['users'] = getSingleValue($conn, "SELECT COUNT(*) FROM users WHERE status = 'active'");
$stats['attendants'] = getSingleValue($conn, "SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'active'");
$stats['low_stock'] = getSingleValue($conn, "SELECT COUNT(*) FROM tanks WHERE current_quantity < minimum_threshold AND status = 'active'");
$stats['total_tanks'] = getSingleValue($conn, "SELECT COUNT(*) FROM tanks WHERE status = 'active'");
$stats['total_capacity'] = getSingleValue($conn, "SELECT COALESCE(SUM(capacity), 0) FROM tanks WHERE status = 'active'");
$stats['current_fuel'] = getSingleValue($conn, "SELECT COALESCE(SUM(current_quantity), 0) FROM tanks WHERE status = 'active'");
$stats['pending_expenses'] = getSingleValue($conn, "SELECT COUNT(*) FROM expenses WHERE status = 'pending'");
$stats['pending_amount'] = getSingleValue($conn, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'pending'");
$stats['total_customers'] = getSingleValue($conn, "SELECT COUNT(DISTINCT customer_name) FROM sales WHERE DATE(sale_date) = '$today' AND customer_name IS NOT NULL");

// Calculate percentages and averages
$stats['fuel_percentage'] = $stats['total_capacity'] > 0 ? round(($stats['current_fuel'] / $stats['total_capacity']) * 100, 1) : 0;
$stats['avg_transaction'] = $stats['today_transactions'] > 0 ? round($stats['today_sales'] / $stats['today_transactions'], 2) : 0;
$stats['sales_growth_today'] = $stats['yesterday_sales'] > 0 ? round((($stats['today_sales'] - $stats['yesterday_sales']) / $stats['yesterday_sales']) * 100, 1) : 0;
$stats['sales_growth_month'] = $stats['last_month_sales'] > 0 ? round((($stats['month_sales'] - $stats['last_month_sales']) / $stats['last_month_sales']) * 100, 1) : 0;

// Get recent sales
$recent_sales = [];
$recent_sales_result = $conn->query("SELECT s.*, b.branch_name, f.fuel_name, u.full_name as attendant_name FROM sales s JOIN branches b ON s.branch_id = b.id JOIN fuel_types f ON s.fuel_type_id = f.id JOIN users u ON s.attendant_id = u.id ORDER BY s.sale_date DESC LIMIT 5");
if ($recent_sales_result) {
    $recent_sales = $recent_sales_result;
}

// Get daily sales for chart
$daily_sales = [];
$daily_result = $conn->query("SELECT DATE(sale_date) as sale_day, COUNT(*) as transactions, SUM(total_amount) as daily_total FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(sale_date) ORDER BY sale_day");
if ($daily_result) {
    while($row = $daily_result->fetch_assoc()) {
        $daily_sales[] = $row;
    }
}

// Get fuel distribution
$fuel_distribution = [];
$fuel_result = $conn->query("SELECT f.fuel_name, COUNT(s.id) as sales_count, SUM(s.total_amount) as total_amount FROM sales s JOIN fuel_types f ON s.fuel_type_id = f.id WHERE DATE(s.sale_date) = '$today' GROUP BY f.id ORDER BY total_amount DESC LIMIT 4");
if ($fuel_result) {
    while($row = $fuel_result->fetch_assoc()) {
        $fuel_distribution[] = $row;
    }
}

// Get low stock details
$low_stock_details = [];
$low_stock_result = $conn->query("SELECT t.*, f.fuel_name, b.branch_name, ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage FROM tanks t JOIN fuel_types f ON t.fuel_type_id = f.id JOIN branches b ON t.branch_id = b.id WHERE t.current_quantity < t.minimum_threshold AND t.status = 'active' ORDER BY percentage ASC LIMIT 4");
if ($low_stock_result) {
    $low_stock_details = $low_stock_result;
}

// Get top branches
$top_branches = [];
$top_branches_result = $conn->query("SELECT b.branch_name, COUNT(s.id) as total_sales, SUM(s.total_amount) as total_revenue FROM sales s JOIN branches b ON s.branch_id = b.id WHERE DATE(s.sale_date) = '$today' GROUP BY b.id ORDER BY total_revenue DESC LIMIT 4");
if ($top_branches_result) {
    $top_branches = $top_branches_result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Dashboard - God Willing Business Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
            font-size: 14px;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        
        .dashboard-container {
            width: 100%;
            padding: 12px;
            max-width: 100%;
            padding-bottom: 20px;
        }
        
        /* Header Styles - Responsive */
        .dashboard-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            animation: slideDown 0.5s ease;
            gap: 12px;
            position: sticky;
            top: 10px;
            z-index: 100;
        }
        
        .header-content {
            flex: 1;
            min-width: 200px;
        }
        
        .header-content h1 {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 4px;
            line-height: 1.2;
        }
        
        .header-content p {
            color: var(--gray);
            font-size: clamp(0.75rem, 3vw, 0.85rem);
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: clamp(0.75rem, 3vw, 0.85rem);
            white-space: nowrap;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover,
        .btn-primary:active {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline-primary:hover,
        .btn-outline-primary.active {
            background: var(--primary);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        .btn-group {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        /* Stats Grid - Fully Responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        
        .stat-info {
            flex: 1;
            min-width: 0;
        }
        
        .stat-info h3 {
            font-size: clamp(0.7rem, 3vw, 0.8rem);
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-value {
            font-size: clamp(1.3rem, 4vw, 1.8rem);
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 4px;
            word-break: break-all;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: clamp(0.7rem, 3vw, 0.75rem);
            margin-top: 4px;
        }
        
        .stat-change.positive {
            color: var(--secondary);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        /* Charts Container - Responsive */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--card-shadow);
            animation: fadeInUp 0.6s ease 0.2s both;
            min-height: 280px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 8px;
        }
        
        .chart-header h3 {
            font-size: clamp(0.9rem, 3vw, 1rem);
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Tables Container - Responsive */
        .tables-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            animation: fadeInUp 0.6s ease 0.3s both;
            display: flex;
            flex-direction: column;
        }
        
        .table-header {
            padding: 16px 16px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table-header h3 {
            font-size: clamp(0.9rem, 3vw, 1rem);
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .table-body {
            flex: 1;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 300px;
        }
        
        .data-table thead {
            background: #f9fafb;
        }
        
        .data-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 500;
            color: var(--gray);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.8rem;
        }
        
        .data-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin: 6px 0;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            transition: width 1s ease;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-block;
            white-space: nowrap;
        }
        
        .status-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .status-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .status-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Quick Actions - Responsive */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            animation: fadeInUp 0.6s ease 0.4s both;
            border: 1px solid transparent;
            min-height: 80px;
            -webkit-tap-highlight-color: transparent;
        }
        
        .action-card:hover,
        .action-card:active {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .action-info {
            flex: 1;
            min-width: 0;
        }
        
        .action-info h4 {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .action-info p {
            font-size: 0.7rem;
            color: var(--gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Live Update Indicator */
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: var(--secondary);
            font-weight: 500;
        }
        
        .live-dot {
            width: 6px;
            height: 6px;
            background: var(--secondary);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        /* Fuel Level Indicator */
        .fuel-indicator {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .fuel-level {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        .fuel-low { background: linear-gradient(90deg, #ef4444, #f97316); }
        .fuel-medium { background: linear-gradient(90deg, #f59e0b, #eab308); }
        .fuel-high { background: linear-gradient(90deg, #10b981, #22c55e); }
        
        /* Badge styles */
        .badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .bg-danger {
            background: var(--danger);
            color: white;
        }
        
        .bg-success {
            background: var(--secondary);
            color: white;
        }
        
        .bg-warning {
            background: var(--warning);
            color: white;
        }
        
        /* Alert cards */
        .alert-card {
            border-left: 3px solid;
            margin-bottom: 10px;
            padding: 12px;
            border-radius: 8px;
        }
        
        /* Flex utilities */
        .d-flex {
            display: flex;
        }
        
        .justify-content-between {
            justify-content: space-between;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        /* Text utilities */
        .text-muted {
            color: var(--gray) !important;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
        }
        
        /* MOBILE OPTIMIZATIONS */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 8px;
            }
            
            .dashboard-header {
                padding: 12px;
                margin-bottom: 12px;
                position: relative;
                top: 0;
            }
            
            .header-content,
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .stat-card {
                padding: 14px;
                min-height: 110px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .chart-card {
                padding: 14px;
                min-height: 260px;
            }
            
            .table-header {
                padding: 14px 14px 8px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }
            
            .action-card {
                padding: 14px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr));
            }
        }
        
        /* SMALL MOBILE (Portrait) */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 6px;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                padding: 10px;
            }
            
            .header-content h1 {
                font-size: 1.1rem;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .stats-grid {
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
                min-height: 100px;
            }
            
            .stat-value {
                font-size: 1.2rem;
            }
            
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .btn-group {
                justify-content: center;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 0.7rem;
            }
            
            .action-card {
                padding: 12px;
                gap: 10px;
            }
            
            .action-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .table-card {
                border-radius: 10px;
            }
            
            .data-table {
                font-size: 0.75rem;
            }
            
            .data-table th {
                padding: 8px 6px;
                font-size: 0.65rem;
            }
            
            .data-table td {
                padding: 8px 6px;
            }
        }
        
        /* TABLET OPTIMIZATIONS */
        @media (min-width: 769px) and (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1.5fr 1fr;
            }
            
            .tables-container {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* LANDSCAPE MODE */
        @media (max-height: 600px) and (orientation: landscape) {
            .dashboard-container {
                padding: 8px;
            }
            
            .dashboard-header {
                margin-bottom: 10px;
                padding: 10px;
            }
            
            .stats-grid {
                gap: 8px;
                margin-bottom: 10px;
            }
            
            .stat-card {
                min-height: 100px;
                padding: 12px;
            }
            
            .chart-card {
                min-height: 220px;
            }
            
            .action-card {
                min-height: 70px;
            }
        }
        
        /* DARK MODE SUPPORT */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
                color: #f9fafb;
            }
            
            .dashboard-header,
            .stat-card,
            .chart-card,
            .table-card,
            .action-card {
                background: #374151;
                color: #f9fafb;
            }
            
            .stat-info h3,
            .header-content p,
            .action-info p {
                color: #d1d5db;
            }
            
            .stat-value {
                color: #f9fafb;
            }
            
            .data-table thead {
                background: #4b5563;
            }
            
            .data-table th {
                color: #d1d5db;
            }
            
            .data-table td {
                border-bottom-color: #4b5563;
            }
            
            .progress-bar {
                background: #4b5563;
            }
            
            .table-header {
                border-bottom-color: #4b5563;
            }
        }
        
        /* TOUCH DEVICE OPTIMIZATIONS */
        @media (hover: none) and (pointer: coarse) {
            .stat-card:hover,
            .action-card:hover {
                transform: none;
            }
            
            .stat-card:active,
            .action-card:active {
                transform: scale(0.98);
            }
            
            .btn:active {
                transform: scale(0.95);
            }
            
            /* Increase touch target sizes */
            .btn {
                min-height: 36px;
                min-width: 44px;
            }
            
            .data-table td,
            .data-table th {
                padding: 12px 8px;
            }
        }
        
        /* HIGH DPI SCREENS */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .stat-card,
            .chart-card,
            .table-card,
            .action-card {
                border: 0.5px solid rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>! Here's what's happening today.</p>
            </div>
            <div class="header-actions">
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    <span>LIVE</span>
                </div>
                <button class="btn btn-primary" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> <span class="btn-text">Refresh</span>
                </button>
            </div>
        </header>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="sales.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="action-info">
                    <h4>Sales Management</h4>
                    <p>View and manage all sales</p>
                </div>
            </a>
            
            <a href="tanks.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-oil-can"></i>
                </div>
                <div class="action-info">
                    <h4>Inventory</h4>
                    <p>Monitor fuel levels</p>
                </div>
            </a>
            
            <a href="branches.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                    <i class="fas fa-building"></i>
                </div>
                <div class="action-info">
                    <h4>Branches</h4>
                    <p>Manage locations</p>
                </div>
            </a>
            
            <a href="reports.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="action-info">
                    <h4>Reports</h4>
                    <p>Generate insights</p>
                </div>
            </a>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Today's Sales Card -->
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Today's Sales</h3>
                        <div class="stat-value">$<?php echo number_format($stats['today_sales'], 2); ?></div>
                        <div class="stat-change <?php echo $stats['sales_growth_today'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['sales_growth_today'] >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['sales_growth_today']); ?>%
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <p class="text-muted"><?php echo $stats['today_transactions']; ?> transactions today</p>
            </div>
            
            <!-- Monthly Sales Card -->
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Monthly Sales</h3>
                        <div class="stat-value">$<?php echo number_format($stats['month_sales'], 2); ?></div>
                        <div class="stat-change <?php echo $stats['sales_growth_month'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['sales_growth_month'] >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['sales_growth_month']); ?>%
                        </div>
                    </div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <p class="text-muted"><?php echo $stats['month_transactions']; ?> transactions this month</p>
            </div>
            
            <!-- Inventory Status Card -->
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Inventory Status</h3>
                        <div class="stat-value"><?php echo number_format($stats['current_fuel'], 2); ?> L</div>
                        <div class="stat-change positive">
                            <i class="fas fa-gas-pump"></i>
                            <?php echo $stats['fuel_percentage']; ?>%
                        </div>
                    </div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <i class="fas fa-oil-can"></i>
                    </div>
                </div>
                <div class="fuel-indicator">
                    <div class="fuel-level <?php 
                        if ($stats['fuel_percentage'] < 30) echo 'fuel-low';
                        elseif ($stats['fuel_percentage'] < 60) echo 'fuel-medium';
                        else echo 'fuel-high';
                    ?>" style="width: <?php echo $stats['fuel_percentage']; ?>%"></div>
                </div>
            </div>
            
            <!-- System Status Card -->
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>System Status</h3>
                        <div class="stat-value"><?php echo $stats['users']; ?> Users</div>
                        <div class="stat-change positive">
                            <i class="fas fa-users"></i>
                            <?php echo $stats['branches']; ?> Branches
                        </div>
                    </div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <i class="fas fa-server"></i>
                    </div>
                </div>
                <p class="text-muted"><?php echo $stats['attendants']; ?> attendants active</p>
            </div>
        </div>
        
        <!-- Charts Container -->
        <div class="charts-container">
            <!-- Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Sales Overview</h3>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary active" onclick="updateChart('7d')">7D</button>
                        <button class="btn btn-sm btn-outline-primary" onclick="updateChart('30d')">30D</button>
                        <button class="btn btn-sm btn-outline-primary" onclick="updateChart('90d')">90D</button>
                    </div>
                </div>
                <div style="flex: 1; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <!-- Alerts and Notifications -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-bell"></i> Alerts & Notifications</h3>
                </div>
                
                <div style="flex: 1; overflow-y: auto;">
                    <!-- Low Stock Alert -->
                    <?php if ($stats['low_stock'] > 0): ?>
                    <div class="alert-card" style="border-left: 4px solid #ef4444; margin-bottom: 12px; padding: 12px; background: rgba(239, 68, 68, 0.05); border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 style="color: #ef4444; font-size: 0.85rem; margin-bottom: 2px;">Low Stock Alert</h5>
                                <p style="font-size: 0.75rem; color: #6b7280;"><?php echo $stats['low_stock']; ?> tanks need refill</p>
                            </div>
                            <span class="badge bg-danger"><?php echo $stats['low_stock']; ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert-card" style="border-left: 4px solid #10b981; margin-bottom: 12px; padding: 12px; background: rgba(16, 185, 129, 0.05); border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 style="color: #10b981; font-size: 0.85rem; margin-bottom: 2px;">Inventory Status</h5>
                                <p style="font-size: 0.75rem; color: #6b7280;">All tanks at optimal levels</p>
                            </div>
                            <span class="badge bg-success"><i class="fas fa-check"></i></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pending Approvals -->
                    <?php if ($stats['pending_expenses'] > 0): ?>
                    <div class="alert-card" style="border-left: 4px solid #f59e0b; margin-bottom: 12px; padding: 12px; background: rgba(245, 158, 11, 0.05); border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 style="color: #f59e0b; font-size: 0.85rem; margin-bottom: 2px;">Pending Approvals</h5>
                                <p style="font-size: 0.75rem; color: #6b7280;"><?php echo $stats['pending_expenses']; ?> expenses awaiting review</p>
                            </div>
                            <span class="badge bg-warning">$<?php echo number_format($stats['pending_amount'], 2); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Performance Metrics -->
                    <div style="margin-top: 15px;">
                        <h6 style="font-size: 0.85rem; color: #6b7280; margin-bottom: 12px;">Performance Metrics</h6>
                        <div style="margin-bottom: 12px;">
                            <div class="d-flex justify-content-between" style="margin-bottom: 4px;">
                                <span style="font-size: 0.75rem;">Sales Growth</span>
                                <span style="font-size: 0.75rem; font-weight: 600; color: <?php echo $stats['sales_growth_today'] >= 0 ? '#10b981' : '#ef4444'; ?>">
                                    <?php echo $stats['sales_growth_today']; ?>%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(abs($stats['sales_growth_today']), 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 12px;">
                            <div class="d-flex justify-content-between" style="margin-bottom: 4px;">
                                <span style="font-size: 0.75rem;">Avg Transaction</span>
                                <span style="font-size: 0.75rem; font-weight: 600;">$<?php echo number_format($stats['avg_transaction'], 2); ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(($stats['avg_transaction'] / 100) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tables Container -->
        <div class="tables-container">
            <!-- Recent Sales Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-history"></i> Recent Sales</h3>
                </div>
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Branch</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                                <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; font-size: 0.8rem;"><?php echo htmlspecialchars($sale['sale_number']); ?></div>
                                        <div style="font-size: 0.7rem; color: #6b7280;"><?php echo date('M d, H:i', strtotime($sale['sale_date'])); ?></div>
                                    </td>
                                    <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($sale['branch_name']); ?></td>
                                    <td style="font-weight: 600; color: #10b981; font-size: 0.8rem;">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-success">Completed</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">
                                        <i class="fas fa-shopping-cart fa-lg mb-2"></i>
                                        <div style="font-size: 0.8rem;">No recent sales</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Low Stock Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Low Stock Alerts</h3>
                </div>
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fuel Type</th>
                                <th>Branch</th>
                                <th>Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock_details && $low_stock_details->num_rows > 0): ?>
                                <?php while($tank = $low_stock_details->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; font-size: 0.8rem;"><?php echo htmlspecialchars($tank['fuel_name']); ?></div>
                                        <div style="font-size: 0.7rem; color: #6b7280;">Tank: <?php echo htmlspecialchars($tank['tank_number']); ?></div>
                                    </td>
                                    <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($tank['branch_name']); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: #ef4444; font-size: 0.8rem;"><?php echo number_format($tank['percentage'], 1); ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $tank['percentage']; ?>%; background: #ef4444;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-danger">Critical</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">
                                        <i class="fas fa-check-circle fa-lg mb-2" style="color: #10b981;"></i>
                                        <div style="font-size: 0.8rem;">All tanks at good level</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Additional Info Section -->
        <div class="tables-container">
            <!-- Top Branches -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-trophy" style="color: #f59e0b;"></i> Top Performing Branches</h3>
                </div>
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_branches && $top_branches->num_rows > 0): ?>
                                <?php 
                                $rank = 1;
                                while($branch = $top_branches->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; display: flex; align-items: center; gap: 6px; font-size: 0.8rem;">
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; text-align: center; line-height: 18px; border-radius: 4px; 
                                                  background: <?php 
                                                    if($rank == 1) echo '#FFD700'; 
                                                    elseif($rank == 2) echo '#C0C0C0'; 
                                                    elseif($rank == 3) echo '#CD7F32'; 
                                                    else echo '#6b7280'; 
                                                  ?>; 
                                                  color: white; font-size: 0.65rem; font-weight: 600;">
                                                <?php echo $rank; ?>
                                            </span>
                                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($branch['branch_name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.8rem;"><?php echo $branch['total_sales']; ?></td>
                                    <td style="font-weight: 600; color: #10b981; font-size: 0.8rem;">$<?php echo number_format($branch['total_revenue'], 2); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min(($branch['total_revenue'] / max($stats['today_sales'], 1)) * 100, 100); ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                $rank++;
                                endwhile; 
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">
                                        <i class="fas fa-chart-line fa-lg mb-2"></i>
                                        <div style="font-size: 0.8rem;">No sales data available</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-chart-pie"></i> Quick Statistics</h3>
                </div>
                <div class="table-body">
                    <div style="padding: 16px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px;">
                            <div>
                                <div style="font-size: 0.7rem; color: #6b7280; margin-bottom: 4px;">Active Users</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #6366f1;"><?php echo $stats['users']; ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; color: #6b7280; margin-bottom: 4px;">Total Tanks</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #10b981;"><?php echo $stats['total_tanks']; ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; color: #6b7280; margin-bottom: 4px;">Avg Transaction</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #f59e0b;">$<?php echo number_format($stats['avg_transaction'], 2); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; color: #6b7280; margin-bottom: 4px;">Today's Customers</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #8b5cf6;"><?php echo $stats['total_customers']; ?></div>
                            </div>
                        </div>
                        
                        <!-- Fuel Distribution -->
                        <div>
                            <h6 style="font-size: 0.8rem; color: #6b7280; margin-bottom: 12px;">Fuel Distribution Today</h6>
                            <?php if (!empty($fuel_distribution)): ?>
                                <?php foreach($fuel_distribution as $fuel): ?>
                                <div style="margin-bottom: 10px;">
                                    <div class="d-flex justify-content-between" style="margin-bottom: 4px;">
                                        <span style="font-size: 0.7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($fuel['fuel_name']); ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600;">$<?php echo number_format($fuel['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php 
                                            $total = array_sum(array_column($fuel_distribution, 'total_amount'));
                                            echo $total > 0 ? ($fuel['total_amount'] / $total * 100) : 0;
                                        ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 15px; color: #6b7280;">
                                    <i class="fas fa-gas-pump fa-lg mb-2"></i>
                                    <div style="font-size: 0.8rem;">No fuel sales today</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Note -->
        <div style="text-align: center; padding: 12px; color: #6b7280; font-size: 0.75rem;">
            <p style="margin-bottom: 4px;">Last updated: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>God Willing Business Center © <?php echo date('Y'); ?> - All rights reserved</p>
        </div>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            initLiveUpdates();
            setupTouchOptimizations();
            
            // Add animation to cards on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.stat-card, .chart-card, .table-card, .action-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    if (salesChart) {
                        salesChart.resize();
                    }
                }, 300);
            });
        });
        
        // Setup touch optimizations
        function setupTouchOptimizations() {
            // Increase touch target for buttons on mobile
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn').forEach(btn => {
                    btn.style.minHeight = '44px';
                    btn.style.minWidth = '44px';
                });
                
                // Prevent zoom on double-tap
                let lastTouchEnd = 0;
                document.addEventListener('touchend', function(event) {
                    const now = (new Date()).getTime();
                    if (now - lastTouchEnd <= 300) {
                        event.preventDefault();
                    }
                    lastTouchEnd = now;
                }, false);
            }
        }
        
        // Sales Chart
        let salesChart = null;
        
        function initCharts() {
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const dailySalesData = <?php echo json_encode($daily_sales); ?>;
            
            // Prepare data for last 7 days
            const labels = [];
            const revenueData = [];
            const transactionData = [];
            
            // Generate last 7 days
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                const dayLabel = date.toLocaleDateString('en-US', { weekday: 'short' }).substring(0, 3);
                const dateNum = date.getDate();
                
                // Shorten labels on mobile
                if (window.innerWidth < 768) {
                    labels.push(`${dayLabel}`);
                } else {
                    labels.push(`${dayLabel} ${dateNum}`);
                }
                
                // Find matching data
                const dayData = dailySalesData.find(d => d.sale_day === dateStr);
                revenueData.push(dayData ? parseFloat(dayData.daily_total) : 0);
                transactionData.push(dayData ? parseInt(dayData.transactions) : 0);
            }
            
            // Destroy existing chart
            if (salesChart) {
                salesChart.destroy();
            }
            
            // Adjust font sizes based on screen size
            const isMobile = window.innerWidth < 768;
            const baseFontSize = isMobile ? 9 : 11;
            
            salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue ($)',
                            data: revenueData,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: isMobile ? 3 : 4,
                            pointHoverRadius: isMobile ? 5 : 6
                        },
                        {
                            label: 'Transactions',
                            data: transactionData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 1,
                            tension: 0.4,
                            fill: false,
                            borderDash: [3, 3],
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: isMobile ? 2 : 3,
                            pointHoverRadius: isMobile ? 4 : 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: isMobile ? 10 : 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: baseFontSize
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: isMobile ? 6 : 8,
                            titleFont: { 
                                size: baseFontSize - 1
                            },
                            bodyFont: { 
                                size: baseFontSize - 1
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        if (context.datasetIndex === 0) {
                                            label += ': $' + context.parsed.y.toLocaleString();
                                        } else {
                                            label += ': ' + context.parsed.y;
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: baseFontSize - 1
                                },
                                maxRotation: isMobile ? 0 : 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: {
                                    size: baseFontSize - 1
                                },
                                callback: function(value) {
                                    if (value >= 1000) {
                                        return '$' + (value / 1000).toFixed(0) + 'k';
                                    }
                                    return '$' + value;
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }
        
        // Update chart period
        function updateChart(period) {
            const buttons = event.target.closest('.btn-group').querySelectorAll('.btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show loading state
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Simulate API call
            setTimeout(() => {
                event.target.innerHTML = period.toUpperCase();
                console.log('Updating chart for period:', period);
                // In real implementation, you would fetch new data here
                // and update the chart
            }, 300);
        }
        
        // Refresh dashboard
        function refreshDashboard() {
            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            // Add refresh animation to cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.transition = 'transform 0.3s ease';
                card.style.transform = 'scale(0.98)';
            });
            
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
        
        // Live updates
        function initLiveUpdates() {
            // Simulate live data updates
            setInterval(() => {
                // Animate live indicator
                const liveDot = document.querySelector('.live-dot');
                if (liveDot) {
                    liveDot.style.animation = 'none';
                    setTimeout(() => {
                        liveDot.style.animation = 'pulse 2s infinite';
                    }, 10);
                }
            }, 5000);
        }
        
        // Window resize handler with debounce
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (salesChart) {
                    salesChart.resize();
                    salesChart.update();
                }
            }, 250);
        });
        
        // Handle back/forward cache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>