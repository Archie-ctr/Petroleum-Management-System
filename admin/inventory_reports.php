<?php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit();
}

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$filter_branch = isset($_GET['branch']) ? intval($_GET['branch']) : 0;
$filter_fuel = isset($_GET['fuel']) ? intval($_GET['fuel']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : $start_date;
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $end_date;
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';

// Fetch branches for filter
$branches_sql = "SELECT id, branch_name, location FROM branches WHERE status = 'active' ORDER BY branch_name";
$branches = $conn->query($branches_sql);

// Fetch fuel types for filter
$fuels_sql = "SELECT id, fuel_name, price_per_liter FROM fuel_types WHERE status = 'active' ORDER BY fuel_name";
$fuels = $conn->query($fuels_sql);

// Initialize reports data
$reports_data = [];
$summary_stats = [];

// Get inventory overview report
if ($report_type === 'overview') {
    // Overall inventory status
    $overview_sql = "SELECT 
        COUNT(t.id) as total_tanks,
        SUM(t.capacity) as total_capacity,
        SUM(t.current_quantity) as total_current,
        SUM(CASE WHEN t.current_quantity < t.minimum_threshold THEN 1 ELSE 0 END) as low_stock_count,
        ROUND((SUM(t.current_quantity) / SUM(t.capacity)) * 100, 2) as overall_percentage
        FROM tanks t
        WHERE t.status = 'active'";
    
    if ($filter_branch > 0) {
        $overview_sql .= " AND t.branch_id = $filter_branch";
    }
    
    $overview_result = $conn->query($overview_sql);
    $overview = $overview_result->fetch_assoc();
    
    // Branch-wise inventory
    $branch_inventory_sql = "SELECT 
        b.branch_name,
        b.location,
        COUNT(t.id) as tank_count,
        SUM(t.capacity) as total_capacity,
        SUM(t.current_quantity) as total_current,
        ROUND((SUM(t.current_quantity) / SUM(t.capacity)) * 100, 2) as percentage,
        SUM(CASE WHEN t.current_quantity < t.minimum_threshold THEN 1 ELSE 0 END) as low_stock
        FROM branches b
        LEFT JOIN tanks t ON b.id = t.branch_id AND t.status = 'active'
        WHERE b.status = 'active'
        GROUP BY b.id
        ORDER BY b.branch_name";
    
    $branch_inventory = $conn->query($branch_inventory_sql);
    
    // Fuel type distribution
    $fuel_distribution_sql = "SELECT 
        f.fuel_name,
        COUNT(t.id) as tank_count,
        SUM(t.capacity) as total_capacity,
        SUM(t.current_quantity) as total_current,
        ROUND((SUM(t.current_quantity) / SUM(t.capacity)) * 100, 2) as percentage,
        AVG(t.current_quantity) as avg_quantity
        FROM fuel_types f
        LEFT JOIN tanks t ON f.id = t.fuel_type_id AND t.status = 'active'
        WHERE f.status = 'active'
        GROUP BY f.id
        ORDER BY f.fuel_name";
    
    $fuel_distribution = $conn->query($fuel_distribution_sql);
    
    $reports_data = [
        'overview' => $overview,
        'branch_inventory' => $branch_inventory,
        'fuel_distribution' => $fuel_distribution
    ];
}

// Get low stock report
elseif ($report_type === 'low_stock') {
    $low_stock_sql = "SELECT 
        t.*,
        f.fuel_name,
        f.price_per_liter,
        b.branch_name,
        b.location,
        ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage,
        ROUND((t.current_quantity - t.minimum_threshold), 2) as deficit_quantity,
        ROUND((t.minimum_threshold - t.current_quantity) * f.price_per_liter, 2) as estimated_refill_cost
        FROM tanks t
        JOIN fuel_types f ON t.fuel_type_id = f.id
        JOIN branches b ON t.branch_id = b.id
        WHERE t.current_quantity < t.minimum_threshold
        AND t.status = 'active'
        AND f.status = 'active'
        AND b.status = 'active'";
    
    if ($filter_branch > 0) {
        $low_stock_sql .= " AND t.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $low_stock_sql .= " AND t.fuel_type_id = $filter_fuel";
    }
    
    $low_stock_sql .= " ORDER BY percentage ASC, t.current_quantity ASC";
    
    $low_stock = $conn->query($low_stock_sql);
    
    // Low stock summary
    $low_stock_summary_sql = "SELECT 
        COUNT(*) as total_low_stock,
        SUM(t.capacity - t.current_quantity) as total_deficit,
        SUM((t.minimum_threshold - t.current_quantity) * f.price_per_liter) as total_refill_cost
        FROM tanks t
        JOIN fuel_types f ON t.fuel_type_id = f.id
        WHERE t.current_quantity < t.minimum_threshold
        AND t.status = 'active'";
    
    $low_stock_summary = $conn->query($low_stock_summary_sql)->fetch_assoc();
    
    $reports_data = [
        'low_stock' => $low_stock,
        'low_stock_summary' => $low_stock_summary
    ];
}

// Get consumption report
elseif ($report_type === 'consumption') {
    $consumption_sql = "SELECT 
        DATE(s.sale_date) as sale_date,
        f.fuel_name,
        b.branch_name,
        SUM(s.quantity) as total_quantity,
        SUM(s.total_amount) as total_amount,
        COUNT(s.id) as transaction_count,
        AVG(s.quantity) as avg_quantity,
        AVG(s.total_amount / s.quantity) as avg_price
        FROM sales s
        JOIN fuel_types f ON s.fuel_type_id = f.id
        JOIN branches b ON s.branch_id = b.id
        WHERE DATE(s.sale_date) BETWEEN '$filter_date_from' AND '$filter_date_to'
        AND s.status = 'completed'";
    
    if ($filter_branch > 0) {
        $consumption_sql .= " AND s.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $consumption_sql .= " AND s.fuel_type_id = $filter_fuel";
    }
    
    $consumption_sql .= " GROUP BY DATE(s.sale_date), f.id, b.id
        ORDER BY sale_date DESC, total_quantity DESC";
    
    $consumption = $conn->query($consumption_sql);
    
    // Daily consumption summary
    $daily_summary_sql = "SELECT 
        DATE(s.sale_date) as sale_date,
        SUM(s.quantity) as daily_quantity,
        SUM(s.total_amount) as daily_amount,
        COUNT(s.id) as daily_transactions
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN '$filter_date_from' AND '$filter_date_to'
        AND s.status = 'completed'";
    
    if ($filter_branch > 0) {
        $daily_summary_sql .= " AND s.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $daily_summary_sql .= " AND s.fuel_type_id = $filter_fuel";
    }
    
    $daily_summary_sql .= " GROUP BY DATE(s.sale_date)
        ORDER BY sale_date DESC";
    
    $daily_summary = $conn->query($daily_summary_sql);
    
    $reports_data = [
        'consumption' => $consumption,
        'daily_summary' => $daily_summary
    ];
}

// Get tank performance report
elseif ($report_type === 'tank_performance') {
    $tank_performance_sql = "SELECT 
        t.*,
        f.fuel_name,
        f.price_per_liter,
        b.branch_name,
        b.location,
        ROUND((t.current_quantity / t.capacity) * 100, 2) as current_percentage,
        (SELECT COUNT(*) FROM sales s WHERE s.tank_id = t.id AND DATE(s.sale_date) BETWEEN '$filter_date_from' AND '$filter_date_to') as sale_count,
        (SELECT COALESCE(SUM(quantity), 0) FROM sales s WHERE s.tank_id = t.id AND DATE(s.sale_date) BETWEEN '$filter_date_from' AND '$filter_date_to') as total_sold,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales s WHERE s.tank_id = t.id AND DATE(s.sale_date) BETWEEN '$filter_date_from' AND '$filter_date_to') as total_revenue
        FROM tanks t
        JOIN fuel_types f ON t.fuel_type_id = f.id
        JOIN branches b ON t.branch_id = b.id
        WHERE t.status = 'active'
        AND f.status = 'active'
        AND b.status = 'active'";
    
    if ($filter_branch > 0) {
        $tank_performance_sql .= " AND t.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $tank_performance_sql .= " AND t.fuel_type_id = $filter_fuel";
    }
    
    $tank_performance_sql .= " ORDER BY total_revenue DESC, total_sold DESC";
    
    $tank_performance = $conn->query($tank_performance_sql);
    
    $reports_data = ['tank_performance' => $tank_performance];
}

// Get refill history report
elseif ($report_type === 'refill_history') {
    $refill_sql = "SELECT 
        r.*,
        t.tank_number,
        f.fuel_name,
        b.branch_name,
        u.full_name as refilled_by,
        ROUND((r.quantity_added / t.capacity) * 100, 2) as refill_percentage,
        ROUND((t.current_quantity / t.capacity) * 100, 2) as current_percentage
        FROM tank_refills r
        JOIN tanks t ON r.tank_id = t.id
        JOIN fuel_types f ON t.fuel_type_id = f.id
        JOIN branches b ON t.branch_id = b.id
        LEFT JOIN users u ON r.refilled_by = u.id
        WHERE DATE(r.refill_date) BETWEEN '$filter_date_from' AND '$filter_date_to'";
    
    if ($filter_branch > 0) {
        $refill_sql .= " AND t.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $refill_sql .= " AND t.fuel_type_id = $filter_fuel";
    }
    
    $refill_sql .= " ORDER BY r.refill_date DESC";
    
    $refill_history = $conn->query($refill_sql);
    
    // Refill summary
    $refill_summary_sql = "SELECT 
        COUNT(*) as total_refills,
        SUM(r.quantity_added) as total_quantity_refilled,
        SUM(r.cost) as total_refill_cost,
        AVG(r.quantity_added) as avg_refill_quantity
        FROM tank_refills r
        JOIN tanks t ON r.tank_id = t.id
        WHERE DATE(r.refill_date) BETWEEN '$filter_date_from' AND '$filter_date_to'";
    
    if ($filter_branch > 0) {
        $refill_summary_sql .= " AND t.branch_id = $filter_branch";
    }
    if ($filter_fuel > 0) {
        $refill_summary_sql .= " AND t.fuel_type_id = $filter_fuel";
    }
    
    $refill_summary = $conn->query($refill_summary_sql)->fetch_assoc();
    
    $reports_data = [
        'refill_history' => $refill_history,
        'refill_summary' => $refill_summary
    ];
}

// Calculate summary statistics
$summary_stats['total_inventory_value'] = 0;
$summary_stats['low_stock_value'] = 0;
$summary_stats['average_utilization'] = 0;

// Get total inventory value
$inventory_value_sql = "SELECT SUM(t.current_quantity * f.price_per_liter) as total_value
    FROM tanks t
    JOIN fuel_types f ON t.fuel_type_id = f.id
    WHERE t.status = 'active'";
$inventory_value = $conn->query($inventory_value_sql)->fetch_assoc();
$summary_stats['total_inventory_value'] = $inventory_value['total_value'] ?? 0;

// Get today's consumption
$today = date('Y-m-d');
$today_consumption_sql = "SELECT SUM(quantity) as total_quantity, SUM(total_amount) as total_amount
    FROM sales WHERE DATE(sale_date) = '$today' AND status = 'completed'";
$today_consumption = $conn->query($today_consumption_sql)->fetch_assoc();
$summary_stats['today_consumption'] = $today_consumption['total_quantity'] ?? 0;
$summary_stats['today_revenue'] = $today_consumption['total_amount'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Reports - God Willing Business Center</title>
    
    <style>
        /* Inventory Reports Styles */
        .reports-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .reports-title {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .reports-subtitle {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .reports-filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
        }
        
        .filter-control {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .filter-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .report-tab {
            padding: 12px 24px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .report-tab:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
            transform: translateY(-2px);
        }
        
        .report-tab.active {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.2);
        }
        
        .report-tab i {
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-weight: 800;
            font-size: 1.8rem;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .stat-trend {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }
        
        .trend-up {
            background: rgba(34, 197, 94, 0.1);
            color: var(--primary-green);
        }
        
        .trend-down {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .report-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .report-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }
        
        .report-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--primary-green);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .badge-info {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
        }
        
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .progress-high {
            background: linear-gradient(to right, #10b981, #34d399);
        }
        
        .progress-medium {
            background: linear-gradient(to right, #f59e0b, #fbbf24);
        }
        
        .progress-low {
            background: linear-gradient(to right, #ef4444, #f87171);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
        }
        
        .btn-secondary {
            background: white;
            border: 2px solid #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .report-tabs {
                flex-direction: column;
            }
            
            .report-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Reports Header -->
    <div class="reports-header">
        <h1 class="reports-title">Inventory Reports</h1>
        <p class="reports-subtitle">Track, analyze, and manage your petroleum inventory across all branches</p>
    </div>

    <!-- Report Tabs -->
    <div class="report-tabs">
        <a href="?type=overview&branch=<?php echo $filter_branch; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
           class="report-tab <?php echo $report_type == 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i>
            Overview
        </a>
        <a href="?type=low_stock&branch=<?php echo $filter_branch; ?>&fuel=<?php echo $filter_fuel; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
           class="report-tab <?php echo $report_type == 'low_stock' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            Low Stock
            <?php if ($reports_data['low_stock_summary']['total_low_stock'] ?? 0 > 0): ?>
                <span class="badge badge-danger"><?php echo $reports_data['low_stock_summary']['total_low_stock'] ?? 0; ?></span>
            <?php endif; ?>
        </a>
        <a href="?type=consumption&branch=<?php echo $filter_branch; ?>&fuel=<?php echo $filter_fuel; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
           class="report-tab <?php echo $report_type == 'consumption' ? 'active' : ''; ?>">
            <i class="fas fa-gas-pump"></i>
            Consumption
        </a>
        <a href="?type=tank_performance&branch=<?php echo $filter_branch; ?>&fuel=<?php echo $filter_fuel; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
           class="report-tab <?php echo $report_type == 'tank_performance' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            Tank Performance
        </a>
        <a href="?type=refill_history&branch=<?php echo $filter_branch; ?>&fuel=<?php echo $filter_fuel; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
           class="report-tab <?php echo $report_type == 'refill_history' ? 'active' : ''; ?>">
            <i class="fas fa-truck-loading"></i>
            Refill History
        </a>
    </div>

    <!-- Filters -->
    <div class="reports-filter-card">
        <form method="GET" action="" id="reportFilters">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">Branch</label>
                    <select name="branch" class="filter-control" onchange="this.form.submit()">
                        <option value="0">All Branches</option>
                        <?php while($branch = $branches->fetch_assoc()): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $filter_branch == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo $branch['branch_name']; ?> - <?php echo $branch['location']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Fuel Type</label>
                    <select name="fuel" class="filter-control" onchange="this.form.submit()">
                        <option value="0">All Fuel Types</option>
                        <?php while($fuel = $fuels->fetch_assoc()): ?>
                            <option value="<?php echo $fuel['id']; ?>" <?php echo $filter_fuel == $fuel['id'] ? 'selected' : ''; ?>>
                                <?php echo $fuel['fuel_name']; ?> ($<?php echo number_format($fuel['price_per_liter'], 2); ?>/L)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date From</label>
                    <input type="date" name="date_from" class="filter-control" value="<?php echo $filter_date_from; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date To</label>
                    <input type="date" name="date_to" class="filter-control" value="<?php echo $filter_date_to; ?>" onchange="this.form.submit()">
                </div>
            </div>
            
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
        </form>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-oil-can"></i>
            </div>
            <div class="stat-value">$<?php echo number_format($summary_stats['total_inventory_value'], 2); ?></div>
            <div class="stat-label">Total Inventory Value</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-gas-pump"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary_stats['today_consumption'], 2); ?> L</div>
            <div class="stat-label">Today's Consumption</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value">$<?php echo number_format($summary_stats['today_revenue'], 2); ?></div>
            <div class="stat-label">Today's Revenue</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-value"><?php echo $reports_data['overview']['low_stock_count'] ?? 0; ?></div>
            <div class="stat-label">Low Stock Tanks</div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="report-content">
        <?php if ($report_type === 'overview'): ?>
            <!-- Overview Report -->
            <h3 style="margin-bottom: 25px; color: #1e293b; font-weight: 700;">Inventory Overview</h3>
            
            <!-- Overall Status -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                <h4 style="margin-bottom: 15px; color: #475569;">Overall Inventory Status</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <div class="stat-label">Total Tanks</div>
                        <div class="stat-value"><?php echo $reports_data['overview']['total_tanks']; ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Total Capacity</div>
                        <div class="stat-value"><?php echo number_format($reports_data['overview']['total_capacity'], 2); ?> L</div>
                    </div>
                    <div>
                        <div class="stat-label">Current Inventory</div>
                        <div class="stat-value"><?php echo number_format($reports_data['overview']['total_current'], 2); ?> L</div>
                    </div>
                    <div>
                        <div class="stat-label">Utilization</div>
                        <div class="stat-value"><?php echo $reports_data['overview']['overall_percentage']; ?>%</div>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $reports_data['overview']['overall_percentage'] > 70 ? 'progress-high' : ($reports_data['overview']['overall_percentage'] > 30 ? 'progress-medium' : 'progress-low'); ?>" 
                                 style="width: <?php echo $reports_data['overview']['overall_percentage']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Branch-wise Inventory -->
            <h4 style="margin-bottom: 20px; color: #475569;">Branch-wise Inventory</h4>
            <?php if ($reports_data['branch_inventory']->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Location</th>
                                <th>Tanks</th>
                                <th>Capacity (L)</th>
                                <th>Current (L)</th>
                                <th>Utilization</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($branch = $reports_data['branch_inventory']->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $branch['branch_name']; ?></strong></td>
                                    <td><?php echo $branch['location']; ?></td>
                                    <td><?php echo $branch['tank_count']; ?></td>
                                    <td><?php echo number_format($branch['total_capacity'], 2); ?></td>
                                    <td><?php echo number_format($branch['total_current'], 2); ?></td>
                                    <td>
                                        <div><?php echo $branch['percentage']; ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $branch['percentage'] > 70 ? 'progress-high' : ($branch['percentage'] > 30 ? 'progress-medium' : 'progress-low'); ?>" 
                                                 style="width: <?php echo $branch['percentage']; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($branch['low_stock'] > 0): ?>
                                            <span class="badge badge-warning">Low Stock (<?php echo $branch['low_stock']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Good</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-database"></i>
                    <p>No branch inventory data available</p>
                </div>
            <?php endif; ?>
            
            <!-- Fuel Distribution -->
            <h4 style="margin-top: 40px; margin-bottom: 20px; color: #475569;">Fuel Type Distribution</h4>
            <?php if ($reports_data['fuel_distribution']->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Fuel Type</th>
                                <th>Tanks</th>
                                <th>Capacity (L)</th>
                                <th>Current (L)</th>
                                <th>Utilization</th>
                                <th>Avg per Tank</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($fuel = $reports_data['fuel_distribution']->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $fuel['fuel_name']; ?></strong></td>
                                    <td><?php echo $fuel['tank_count']; ?></td>
                                    <td><?php echo number_format($fuel['total_capacity'], 2); ?></td>
                                    <td><?php echo number_format($fuel['total_current'], 2); ?></td>
                                    <td>
                                        <div><?php echo $fuel['percentage']; ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $fuel['percentage'] > 70 ? 'progress-high' : ($fuel['percentage'] > 30 ? 'progress-medium' : 'progress-low'); ?>" 
                                                 style="width: <?php echo $fuel['percentage']; ?>%"></div>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($fuel['avg_quantity'], 2); ?> L</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-gas-pump"></i>
                    <p>No fuel distribution data available</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($report_type === 'low_stock'): ?>
            <!-- Low Stock Report -->
            <h3 style="margin-bottom: 25px; color: #1e293b; font-weight: 700;">Low Stock Report</h3>
            
            <?php if ($reports_data['low_stock_summary']['total_low_stock'] > 0): ?>
                <!-- Low Stock Summary -->
                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                    <h4 style="margin-bottom: 15px; color: #7f1d1d;">⚠️ Immediate Action Required</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <div class="stat-label" style="color: #7f1d1d;">Total Low Stock</div>
                            <div class="stat-value" style="color: #dc2626;"><?php echo $reports_data['low_stock_summary']['total_low_stock']; ?></div>
                        </div>
                        <div>
                            <div class="stat-label" style="color: #7f1d1d;">Total Deficit</div>
                            <div class="stat-value" style="color: #dc2626;"><?php echo number_format($reports_data['low_stock_summary']['total_deficit'], 2); ?> L</div>
                        </div>
                        <div>
                            <div class="stat-label" style="color: #7f1d1d;">Estimated Refill Cost</div>
                            <div class="stat-value" style="color: #dc2626;">$<?php echo number_format($reports_data['low_stock_summary']['total_refill_cost'], 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Details -->
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Tank No.</th>
                                <th>Fuel Type</th>
                                <th>Branch</th>
                                <th>Current (L)</th>
                                <th>Capacity (L)</th>
                                <th>Threshold (L)</th>
                                <th>Deficit (L)</th>
                                <th>Status</th>
                                <th>Est. Refill Cost</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($tank = $reports_data['low_stock']->fetch_assoc()): ?>
                                <?php
                                $deficit = $tank['minimum_threshold'] - $tank['current_quantity'];
                                $percentage = $tank['percentage'];
                                $status_class = $percentage < 10 ? 'badge-danger' : ($percentage < 30 ? 'badge-warning' : 'badge-warning');
                                ?>
                                <tr>
                                    <td><strong><?php echo $tank['tank_number']; ?></strong></td>
                                    <td><?php echo $tank['fuel_name']; ?></td>
                                    <td><?php echo $tank['branch_name']; ?><br><small><?php echo $tank['location']; ?></small></td>
                                    <td><?php echo number_format($tank['current_quantity'], 2); ?></td>
                                    <td><?php echo number_format($tank['capacity'], 2); ?></td>
                                    <td><?php echo number_format($tank['minimum_threshold'], 2); ?></td>
                                    <td><span class="badge badge-danger">-<?php echo number_format($deficit, 2); ?> L</span></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo $percentage; ?>%</span></td>
                                    <td>$<?php echo number_format($tank['estimated_refill_cost'], 2); ?></td>
                                    <td>
                                        <a href="tanks.php?action=refill&id=<?php echo $tank['id']; ?>" 
                                           class="btn btn-primary" style="padding: 8px 16px; font-size: 0.8rem;">
                                            <i class="fas fa-gas-pump"></i> Refill
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <h4 style="color: #10b981; margin-bottom: 10px;">All Tanks at Good Levels!</h4>
                    <p>No low stock tanks found. All inventory levels are above minimum threshold.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($report_type === 'consumption'): ?>
            <!-- Consumption Report -->
            <h3 style="margin-bottom: 25px; color: #1e293b; font-weight: 700;">Fuel Consumption Report</h3>
            <p style="color: #64748b; margin-bottom: 25px;">
                Period: <?php echo date('M d, Y', strtotime($filter_date_from)); ?> to <?php echo date('M d, Y', strtotime($filter_date_to)); ?>
            </p>
            
            <?php if ($reports_data['consumption']->num_rows > 0): ?>
                <!-- Consumption Details -->
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Fuel Type</th>
                                <th>Branch</th>
                                <th>Quantity (L)</th>
                                <th>Amount ($)</th>
                                <th>Transactions</th>
                                <th>Avg Quantity</th>
                                <th>Avg Price/L</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $reports_data['consumption']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo $row['fuel_name']; ?></td>
                                    <td><?php echo $row['branch_name']; ?></td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?></td>
                                    <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td><?php echo $row['transaction_count']; ?></td>
                                    <td><?php echo number_format($row['avg_quantity'], 2); ?></td>
                                    <td>$<?php echo number_format($row['avg_price'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No consumption data found for the selected period</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($report_type === 'tank_performance'): ?>
            <!-- Tank Performance Report -->
            <h3 style="margin-bottom: 25px; color: #1e293b; font-weight: 700;">Tank Performance Report</h3>
            
            <?php if ($reports_data['tank_performance']->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Tank No.</th>
                                <th>Fuel Type</th>
                                <th>Branch</th>
                                <th>Capacity (L)</th>
                                <th>Current (L)</th>
                                <th>Utilization</th>
                                <th>Sales Count</th>
                                <th>Total Sold (L)</th>
                                <th>Total Revenue</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($tank = $reports_data['tank_performance']->fetch_assoc()): ?>
                                <?php
                                $percentage = $tank['current_percentage'];
                                $revenue = $tank['total_revenue'] ?? 0;
                                $sold = $tank['total_sold'] ?? 0;
                                
                                // Performance rating
                                if ($revenue > 10000) {
                                    $performance = 'Excellent';
                                    $performance_class = 'badge-success';
                                } elseif ($revenue > 5000) {
                                    $performance = 'Good';
                                    $performance_class = 'badge-info';
                                } elseif ($revenue > 1000) {
                                    $performance = 'Average';
                                    $performance_class = 'badge-warning';
                                } else {
                                    $performance = 'Low';
                                    $performance_class = 'badge-danger';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo $tank['tank_number']; ?></strong></td>
                                    <td><?php echo $tank['fuel_name']; ?></td>
                                    <td><?php echo $tank['branch_name']; ?><br><small><?php echo $tank['location']; ?></small></td>
                                    <td><?php echo number_format($tank['capacity'], 2); ?></td>
                                    <td><?php echo number_format($tank['current_quantity'], 2); ?></td>
                                    <td>
                                        <div><?php echo $percentage; ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $percentage > 70 ? 'progress-high' : ($percentage > 30 ? 'progress-medium' : 'progress-low'); ?>" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </td>
                                    <td><?php echo $tank['sale_count']; ?></td>
                                    <td><?php echo number_format($sold, 2); ?> L</td>
                                    <td>$<?php echo number_format($revenue, 2); ?></td>
                                    <td><span class="badge <?php echo $performance_class; ?>"><?php echo $performance; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No tank performance data available</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($report_type === 'refill_history'): ?>
            <!-- Refill History Report -->
            <h3 style="margin-bottom: 25px; color: #1e293b; font-weight: 700;">Refill History Report</h3>
            
            <?php if ($reports_data['refill_history']->num_rows > 0): ?>
                <!-- Refill Summary -->
                <div style="background: #f0f9ff; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                    <h4 style="margin-bottom: 15px; color: #0369a1;">Refill Summary</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <div class="stat-label" style="color: #0369a1;">Total Refills</div>
                            <div class="stat-value" style="color: #0ea5e9;"><?php echo $reports_data['refill_summary']['total_refills']; ?></div>
                        </div>
                        <div>
                            <div class="stat-label" style="color: #0369a1;">Total Quantity</div>
                            <div class="stat-value" style="color: #0ea5e9;"><?php echo number_format($reports_data['refill_summary']['total_quantity_refilled'], 2); ?> L</div>
                        </div>
                        <div>
                            <div class="stat-label" style="color: #0369a1;">Total Cost</div>
                            <div class="stat-value" style="color: #0ea5e9;">$<?php echo number_format($reports_data['refill_summary']['total_refill_cost'], 2); ?></div>
                        </div>
                        <div>
                            <div class="stat-label" style="color: #0369a1;">Average Refill</div>
                            <div class="stat-value" style="color: #0ea5e9;"><?php echo number_format($reports_data['refill_summary']['avg_refill_quantity'], 2); ?> L</div>
                        </div>
                    </div>
                </div>
                
                <!-- Refill Details -->
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tank No.</th>
                                <th>Fuel Type</th>
                                <th>Branch</th>
                                <th>Quantity Added</th>
                                <th>Previous Level</th>
                                <th>New Level</th>
                                <th>Cost</th>
                                <th>Refilled By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($refill = $reports_data['refill_history']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($refill['refill_date'])); ?></td>
                                    <td><strong><?php echo $refill['tank_number']; ?></strong></td>
                                    <td><?php echo $refill['fuel_name']; ?></td>
                                    <td><?php echo $refill['branch_name']; ?></td>
                                    <td><span class="badge badge-success">+<?php echo number_format($refill['quantity_added'], 2); ?> L</span></td>
                                    <td><?php echo $refill['refill_percentage']; ?>%</td>
                                    <td><?php echo $refill['current_percentage']; ?>%</td>
                                    <td>$<?php echo number_format($refill['cost'], 2); ?></td>
                                    <td><?php echo $refill['refilled_by'] ?? 'System'; ?></td>
                                    <td><?php echo substr($refill['notes'] ?? 'No notes', 0, 50); ?>...</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-truck-loading"></i>
                    <p>No refill history found for the selected period</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-secondary" onclick="exportToExcel()">
                <i class="fas fa-file-export"></i> Export to Excel
            </button>
            <button class="btn btn-secondary" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
        </div>
    </div>

    <script>
        // Function to print the report
        function printReport() {
            const printContent = document.querySelector('.report-content').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Inventory Report - God Willing Business Center</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1, h2, h3 { color: #333; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f5f5f5; }
                        .print-header { text-align: center; margin-bottom: 30px; }
                        .print-footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>God Willing Business Center</h1>
                        <h2>Inventory Report</h2>
                        <p>Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                    </div>
                    ${printContent}
                    <div class="print-footer">
                        <p>© <?php echo date('Y'); ?> God Willing Business Center - Petroleum Management System</p>
                    </div>
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Function to export to Excel
        function exportToExcel() {
            // Create a simple table export
            const table = document.querySelector('.report-table');
            if (!table) {
                alert('No table data to export');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Clean the data (remove HTML tags, badges, etc.)
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    row.push(data);
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "inventory_report_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Function to download PDF (simulated)
        function downloadPDF() {
            alert('PDF export functionality would be implemented with a PDF library. For now, please use Print Report and save as PDF.');
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(() => {
            // You can add AJAX call to refresh data without reloading
            console.log('Auto-refreshing inventory report data...');
        }, 300000);
        
        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            // Set max date for date pickers
            const today = new Date().toISOString().split('T')[0];
            dateFrom.max = today;
            dateTo.max = today;
            
            // Validate date range
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && dateFrom.value > dateTo.value) {
                    dateTo.value = dateFrom.value;
                }
            });
            
            dateTo.addEventListener('change', function() {
                if (dateFrom.value && dateTo.value < dateFrom.value) {
                    dateFrom.value = dateTo.value;
                }
            });
        });
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>