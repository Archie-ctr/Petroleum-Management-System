<?php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Get date ranges
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_month_start = date('Y-m-01');
$this_year_start = date('Y-01-01');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Initialize analytics data
$analytics = [
    'overview' => [],
    'sales' => [],
    'inventory' => [],
    'performance' => [],
    'trends' => []
];

// Get overview statistics
try {
    // Total Revenue
    $revenue_sql = "SELECT 
        COALESCE(SUM(total_amount), 0) as total,
        COALESCE(SUM(CASE WHEN DATE(sale_date) = '$today' THEN total_amount ELSE 0 END), 0) as today,
        COALESCE(SUM(CASE WHEN DATE(sale_date) >= '$this_week_start' THEN total_amount ELSE 0 END), 0) as this_week,
        COALESCE(SUM(CASE WHEN DATE(sale_date) >= '$this_month_start' THEN total_amount ELSE 0 END), 0) as this_month,
        COALESCE(SUM(CASE WHEN DATE(sale_date) >= '$this_year_start' THEN total_amount ELSE 0 END), 0) as this_year
    FROM sales WHERE status = 'completed'";
    
    $revenue_result = $conn->query($revenue_sql);
    $analytics['overview']['revenue'] = $revenue_result->fetch_assoc();
    
    // Total Transactions
    $transactions_sql = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN DATE(sale_date) = '$today' THEN 1 END) as today,
        COUNT(CASE WHEN DATE(sale_date) >= '$this_week_start' THEN 1 END) as this_week,
        COUNT(CASE WHEN DATE(sale_date) >= '$this_month_start' THEN 1 END) as this_month
    FROM sales WHERE status = 'completed'";
    
    $transactions_result = $conn->query($transactions_sql);
    $analytics['overview']['transactions'] = $transactions_result->fetch_assoc();
    
    // Active Users
    $users_sql = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
        COUNT(CASE WHEN role = 'manager' THEN 1 END) as managers,
        COUNT(CASE WHEN role = 'attendant' THEN 1 END) as attendants,
        COUNT(CASE WHEN role = 'accountant' THEN 1 END) as accountants
    FROM users WHERE status = 'active'";
    
    $users_result = $conn->query($users_sql);
    $analytics['overview']['users'] = $users_result->fetch_assoc();
    
    // Active Branches
    $branches_sql = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
    FROM branches";
    
    $branches_result = $conn->query($branches_sql);
    $analytics['overview']['branches'] = $branches_result->fetch_assoc();
    
} catch (Exception $e) {
    error_log("Analytics overview error: " . $e->getMessage());
}

// Get sales analytics
try {
    // Sales by fuel type
    $fuel_sales_sql = "SELECT 
        f.fuel_name,
        COUNT(s.id) as transaction_count,
        SUM(s.quantity) as total_quantity,
        SUM(s.total_amount) as total_revenue,
        AVG(s.quantity) as avg_quantity,
        AVG(s.total_amount) as avg_amount
    FROM sales s
    JOIN fuel_types f ON s.fuel_type_id = f.id
    WHERE s.status = 'completed'
    GROUP BY f.id
    ORDER BY total_revenue DESC
    LIMIT 10";
    
    $fuel_sales_result = $conn->query($fuel_sales_sql);
    $analytics['sales']['by_fuel'] = [];
    while($row = $fuel_sales_result->fetch_assoc()) {
        $analytics['sales']['by_fuel'][] = $row;
    }
    
    // Sales by branch
    $branch_sales_sql = "SELECT 
        b.branch_name,
        COUNT(s.id) as transaction_count,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_transaction
    FROM sales s
    JOIN branches b ON s.branch_id = b.id
    WHERE s.status = 'completed'
    GROUP BY b.id
    ORDER BY total_revenue DESC
    LIMIT 10";
    
    $branch_sales_result = $conn->query($branch_sales_sql);
    $analytics['sales']['by_branch'] = [];
    while($row = $branch_sales_result->fetch_assoc()) {
        $analytics['sales']['by_branch'][] = $row;
    }
    
    // Sales by payment method
    $payment_sales_sql = "SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(total_amount) as total_revenue,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM sales WHERE status = 'completed')) as percentage
    FROM sales 
    WHERE status = 'completed'
    GROUP BY payment_method
    ORDER BY total_revenue DESC";
    
    $payment_sales_result = $conn->query($payment_sales_sql);
    $analytics['sales']['by_payment'] = [];
    while($row = $payment_sales_result->fetch_assoc()) {
        $analytics['sales']['by_payment'][] = $row;
    }
    
    // Daily sales trend (last 30 days)
    $daily_trend_sql = "SELECT 
        DATE(sale_date) as sale_day,
        COUNT(*) as transaction_count,
        SUM(total_amount) as daily_revenue,
        SUM(quantity) as daily_quantity
    FROM sales 
    WHERE status = 'completed' 
    AND sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(sale_date)
    ORDER BY sale_day";
    
    $daily_trend_result = $conn->query($daily_trend_sql);
    $analytics['trends']['daily'] = [];
    while($row = $daily_trend_result->fetch_assoc()) {
        $analytics['trends']['daily'][] = $row;
    }
    
    // Monthly sales trend (last 12 months)
    $monthly_trend_sql = "SELECT 
        DATE_FORMAT(sale_date, '%Y-%m') as sale_month,
        COUNT(*) as transaction_count,
        SUM(total_amount) as monthly_revenue,
        SUM(quantity) as monthly_quantity
    FROM sales 
    WHERE status = 'completed' 
    AND sale_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
    ORDER BY sale_month";
    
    $monthly_trend_result = $conn->query($monthly_trend_sql);
    $analytics['trends']['monthly'] = [];
    while($row = $monthly_trend_result->fetch_assoc()) {
        $analytics['trends']['monthly'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Analytics sales error: " . $e->getMessage());
}

// Get inventory analytics
try {
    // Fuel inventory status
    $inventory_sql = "SELECT 
        f.fuel_name,
        b.branch_name,
        t.tank_number,
        t.capacity,
        t.current_quantity,
        t.minimum_threshold,
        ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage,
        CASE 
            WHEN t.current_quantity < t.minimum_threshold THEN 'low'
            WHEN t.current_quantity < t.capacity * 0.3 THEN 'warning'
            ELSE 'good'
        END as status
    FROM tanks t
    JOIN fuel_types f ON t.fuel_type_id = f.id
    JOIN branches b ON t.branch_id = b.id
    WHERE t.status = 'active'
    ORDER BY percentage ASC";
    
    $inventory_result = $conn->query($inventory_sql);
    $analytics['inventory']['tanks'] = [];
    while($row = $inventory_result->fetch_assoc()) {
        $analytics['inventory']['tanks'][] = $row;
    }
    
    // Fuel consumption rate
    $consumption_sql = "SELECT 
        f.fuel_name,
        SUM(s.quantity) as total_sold,
        AVG(s.quantity) as avg_per_sale,
        MAX(s.quantity) as max_per_sale,
        MIN(s.quantity) as min_per_sale
    FROM sales s
    JOIN fuel_types f ON s.fuel_type_id = f.id
    WHERE s.status = 'completed'
    AND s.sale_date >= '$this_month_start'
    GROUP BY f.id
    ORDER BY total_sold DESC";
    
    $consumption_result = $conn->query($consumption_sql);
    $analytics['inventory']['consumption'] = [];
    while($row = $consumption_result->fetch_assoc()) {
        $analytics['inventory']['consumption'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Analytics inventory error: " . $e->getMessage());
}

// Get performance analytics
try {
    // Branch performance
    $performance_sql = "SELECT 
        b.branch_name,
        COUNT(DISTINCT s.attendant_id) as active_attendants,
        COUNT(s.id) as transaction_count,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_transaction,
        MAX(s.total_amount) as max_transaction,
        MIN(s.total_amount) as min_transaction
    FROM sales s
    JOIN branches b ON s.branch_id = b.id
    WHERE s.status = 'completed'
    AND s.sale_date >= '$this_month_start'
    GROUP BY b.id
    ORDER BY total_revenue DESC";
    
    $performance_result = $conn->query($performance_sql);
    $analytics['performance']['branches'] = [];
    while($row = $performance_result->fetch_assoc()) {
        $analytics['performance']['branches'][] = $row;
    }
    
    // Attendant performance
    $attendant_perf_sql = "SELECT 
        u.full_name,
        b.branch_name,
        COUNT(s.id) as transaction_count,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_transaction,
        MAX(s.total_amount) as max_transaction
    FROM sales s
    JOIN users u ON s.attendant_id = u.id
    JOIN branches b ON s.branch_id = b.id
    WHERE s.status = 'completed'
    AND s.sale_date >= '$this_month_start'
    AND u.role = 'attendant'
    GROUP BY u.id
    ORDER BY total_revenue DESC
    LIMIT 10";
    
    $attendant_perf_result = $conn->query($attendant_perf_sql);
    $analytics['performance']['attendants'] = [];
    while($row = $attendant_perf_result->fetch_assoc()) {
        $analytics['performance']['attendants'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Analytics performance error: " . $e->getMessage());
}

// Get growth metrics
try {
    // Revenue growth
    $revenue_growth_sql = "SELECT 
        (SELECT COALESCE(SUM(total_amount), 0) 
         FROM sales 
         WHERE status = 'completed' 
         AND DATE(sale_date) = '$today') as today,
        (SELECT COALESCE(SUM(total_amount), 0) 
         FROM sales 
         WHERE status = 'completed' 
         AND DATE(sale_date) = '$yesterday') as yesterday,
        (SELECT COALESCE(SUM(total_amount), 0) 
         FROM sales 
         WHERE status = 'completed' 
         AND sale_date >= '$this_month_start') as this_month,
        (SELECT COALESCE(SUM(total_amount), 0) 
         FROM sales 
         WHERE status = 'completed' 
         AND sale_date >= '$last_month_start' 
         AND sale_date <= '$last_month_end') as last_month";
    
    $growth_result = $conn->query($revenue_growth_sql);
    $growth_data = $growth_result->fetch_assoc();
    
    // Calculate growth percentages
    $analytics['growth'] = [
        'daily' => $growth_data['yesterday'] > 0 
            ? round((($growth_data['today'] - $growth_data['yesterday']) / $growth_data['yesterday']) * 100, 2)
            : ($growth_data['today'] > 0 ? 100 : 0),
        'monthly' => $growth_data['last_month'] > 0 
            ? round((($growth_data['this_month'] - $growth_data['last_month']) / $growth_data['last_month']) * 100, 2)
            : ($growth_data['this_month'] > 0 ? 100 : 0)
    ];
    
} catch (Exception $e) {
    error_log("Analytics growth error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - God Willing Business Center</title>
    
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Include Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
    <style>
        /* Analytics Dashboard Styles */
        .analytics-container {
            padding: 20px;
        }
        
        .analytics-header {
            margin-bottom: 30px;
        }
        
        .analytics-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .analytics-subtitle {
            color: #718096;
            font-size: 1rem;
        }
        
        /* Date Range Filter */
        .date-range-filter {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .filter-title {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-title i {
            color: #4299e1;
        }
        
        .date-range-container {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-range-btn {
            background: #edf2f7;
            border: 2px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .date-range-btn:hover {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }
        
        .date-range-btn.active {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }
        
        .date-range-input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 250px;
        }
        
        .apply-filter-btn {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .apply-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 153, 225, 0.3);
        }
        
        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        /* Analytics Card */
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .analytics-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .analytics-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .analytics-card-subtitle {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .analytics-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .analytics-card-content {
            margin-top: 20px;
        }
        
        /* Stat Cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-change.positive {
            color: #38a169;
        }
        
        .stat-change.negative {
            color: #e53e3e;
        }
        
        /* Tables */
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .analytics-table th {
            background: #f7fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
        }
        
        .analytics-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 0.9rem;
        }
        
        .analytics-table tr:hover {
            background: #f7fafc;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        /* Progress Bars */
        .progress-container {
            margin-top: 15px;
        }
        
        .progress-item {
            margin-bottom: 15px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-low {
            background: #fed7d7;
            color: #c53030;
        }
        
        .status-warning {
            background: #feebc8;
            color: #c05621;
        }
        
        .status-good {
            background: #c6f6d5;
            color: #276749;
        }
        
        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        .loading i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #4299e1;
        }
        
        /* Export Options */
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .export-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #4a5568;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-grid {
                grid-template-columns: 1fr;
            }
            
            .date-range-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-range-input {
                min-width: auto;
            }
        }
        
        /* Print Styles */
        @media print {
            .date-range-filter,
            .export-options {
                display: none;
            }
            
            .analytics-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        /* Animation for cards */
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
        
        .analytics-card {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .analytics-card:nth-child(1) { animation-delay: 0.1s; }
        .analytics-card:nth-child(2) { animation-delay: 0.2s; }
        .analytics-card:nth-child(3) { animation-delay: 0.3s; }
        .analytics-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="analytics-container">
        <!-- Header -->
        <div class="analytics-header">
            <h1 class="analytics-title">Analytics Dashboard</h1>
            <p class="analytics-subtitle">Comprehensive insights and performance metrics for God Willing Business Center</p>
        </div>
        
        <!-- Date Range Filter -->
        <div class="date-range-filter">
            <div class="filter-title">
                <i class="fas fa-calendar-alt"></i>
                Select Date Range
            </div>
            <div class="date-range-container">
                <button class="date-range-btn active" data-range="today">Today</button>
                <button class="date-range-btn" data-range="yesterday">Yesterday</button>
                <button class="date-range-btn" data-range="this_week">This Week</button>
                <button class="date-range-btn" data-range="this_month">This Month</button>
                <button class="date-range-btn" data-range="last_month">Last Month</button>
                <button class="date-range-btn" data-range="this_year">This Year</button>
                <input type="text" class="date-range-input" id="dateRangePicker" placeholder="Custom Range">
                <button class="apply-filter-btn" id="applyFilter">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </div>
        </div>
        
        <!-- Overview Statistics -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-dollar-sign text-blue-500"></i>
                    Total Revenue
                </div>
                <div class="stat-value">$<?php echo number_format($analytics['overview']['revenue']['total'] ?? 0, 2); ?></div>
                <div class="stat-change <?php echo ($analytics['growth']['daily'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo ($analytics['growth']['daily'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($analytics['growth']['daily'] ?? 0); ?>% today
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-shopping-cart text-green-500"></i>
                    Total Transactions
                </div>
                <div class="stat-value"><?php echo number_format($analytics['overview']['transactions']['total'] ?? 0); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo number_format($analytics['overview']['transactions']['today'] ?? 0); ?> today
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-users text-purple-500"></i>
                    Active Users
                </div>
                <div class="stat-value"><?php echo number_format($analytics['overview']['users']['total'] ?? 0); ?></div>
                <div class="stat-title" style="margin-top: 10px; font-size: 0.8rem;">
                    Admin: <?php echo $analytics['overview']['users']['admins'] ?? 0; ?> | 
                    Managers: <?php echo $analytics['overview']['users']['managers'] ?? 0; ?> | 
                    Attendants: <?php echo $analytics['overview']['users']['attendants'] ?? 0; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-building text-orange-500"></i>
                    Active Branches
                </div>
                <div class="stat-value"><?php echo number_format($analytics['overview']['branches']['active'] ?? 0); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $analytics['overview']['branches']['total'] ?? 0; ?> total branches
                </div>
            </div>
        </div>
        
        <!-- Analytics Grid -->
        <div class="analytics-grid">
            <!-- Revenue Trend Chart -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Revenue Trend</h3>
                        <p class="analytics-card-subtitle">Last 30 days revenue overview</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce); color: white;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <!-- Sales by Fuel Type -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Sales by Fuel Type</h3>
                        <p class="analytics-card-subtitle">Top performing fuel types</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #38a169, #2f855a); color: white;">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                </div>
                <div class="analytics-card-content">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Fuel Type</th>
                                <th>Revenue</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['sales']['by_fuel'] as $fuel): ?>
                            <tr>
                                <td><?php echo $fuel['fuel_name']; ?></td>
                                <td>$<?php echo number_format($fuel['total_revenue'], 2); ?></td>
                                <td><?php echo number_format($fuel['total_quantity'], 2); ?> L</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Branch Performance -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Branch Performance</h3>
                        <p class="analytics-card-subtitle">Top performing branches</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20); color: white;">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
                <div class="analytics-card-content">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Revenue</th>
                                <th>Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['performance']['branches'] as $branch): ?>
                            <tr>
                                <td><?php echo $branch['branch_name']; ?></td>
                                <td>$<?php echo number_format($branch['total_revenue'], 2); ?></td>
                                <td><?php echo number_format($branch['transaction_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Inventory Status -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Inventory Status</h3>
                        <p class="analytics-card-subtitle">Fuel tank levels overview</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #9f7aea, #805ad5); color: white;">
                        <i class="fas fa-oil-can"></i>
                    </div>
                </div>
                <div class="analytics-card-content">
                    <div class="progress-container">
                        <?php foreach ($analytics['inventory']['tanks'] as $tank): 
                            $status_class = '';
                            if ($tank['status'] == 'low') $status_class = 'status-low';
                            elseif ($tank['status'] == 'warning') $status_class = 'status-warning';
                            else $status_class = 'status-good';
                        ?>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span><?php echo $tank['fuel_name']; ?> - <?php echo $tank['branch_name']; ?></span>
                                <span><?php echo $tank['percentage']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" 
                                     style="width: <?php echo $tank['percentage']; ?>%; 
                                            background: <?php echo $tank['status'] == 'low' ? '#fc8181' : ($tank['status'] == 'warning' ? '#f6ad55' : '#68d391'); ?>;">
                                </div>
                            </div>
                            <div style="font-size: 0.8rem; color: #718096; margin-top: 5px;">
                                <?php echo number_format($tank['current_quantity'], 2); ?>L / <?php echo number_format($tank['capacity'], 2); ?>L
                                <span class="status-indicator <?php echo $status_class; ?>" style="float: right;">
                                    <i class="fas fa-<?php echo $tank['status'] == 'low' ? 'exclamation-triangle' : ($tank['status'] == 'warning' ? 'exclamation-circle' : 'check-circle'); ?>"></i>
                                    <?php echo ucfirst($tank['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method Distribution -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Payment Methods</h3>
                        <p class="analytics-card-subtitle">Distribution by payment type</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e); color: white;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
            
            <!-- Top Performing Attendants -->
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <div>
                        <h3 class="analytics-card-title">Top Attendants</h3>
                        <p class="analytics-card-subtitle">Highest performing attendants</p>
                    </div>
                    <div class="analytics-card-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce); color: white;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <div class="analytics-card-content">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Attendant</th>
                                <th>Branch</th>
                                <th>Revenue</th>
                                <th>Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['performance']['attendants'] as $attendant): ?>
                            <tr>
                                <td><?php echo $attendant['full_name']; ?></td>
                                <td><?php echo $attendant['branch_name']; ?></td>
                                <td>$<?php echo number_format($attendant['total_revenue'], 2); ?></td>
                                <td><?php echo number_format($attendant['transaction_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="export-options">
            <button class="export-btn" onclick="exportData('pdf')">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button class="export-btn" onclick="exportData('excel')">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="export-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
    
    <script>
        // Initialize date range picker
        $(document).ready(function() {
            // Date range picker
            $('#dateRangePicker').daterangepicker({
                opens: 'left',
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                startDate: moment(),
                endDate: moment(),
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
            
            // Date range button click handlers
            $('.date-range-btn').click(function() {
                $('.date-range-btn').removeClass('active');
                $(this).addClass('active');
                
                const range = $(this).data('range');
                let startDate, endDate;
                
                switch(range) {
                    case 'today':
                        startDate = moment();
                        endDate = moment();
                        break;
                    case 'yesterday':
                        startDate = moment().subtract(1, 'days');
                        endDate = moment().subtract(1, 'days');
                        break;
                    case 'this_week':
                        startDate = moment().startOf('week');
                        endDate = moment().endOf('week');
                        break;
                    case 'this_month':
                        startDate = moment().startOf('month');
                        endDate = moment().endOf('month');
                        break;
                    case 'last_month':
                        startDate = moment().subtract(1, 'month').startOf('month');
                        endDate = moment().subtract(1, 'month').endOf('month');
                        break;
                    case 'this_year':
                        startDate = moment().startOf('year');
                        endDate = moment().endOf('year');
                        break;
                }
                
                $('#dateRangePicker').data('daterangepicker').setStartDate(startDate);
                $('#dateRangePicker').data('daterangepicker').setEndDate(endDate);
            });
            
            // Apply filter button
            $('#applyFilter').click(function() {
                const dates = $('#dateRangePicker').val().split(' - ');
                loadAnalyticsData(dates[0], dates[1]);
            });
            
            // Initialize charts
            initializeCharts();
            
            // Auto-refresh every 5 minutes
            setInterval(() => {
                refreshAnalytics();
            }, 300000);
        });
        
        // Initialize charts
        function initializeCharts() {
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueData = <?php echo json_encode($analytics['trends']['daily']); ?>;
            
            const revenueLabels = revenueData.map(item => {
                const date = new Date(item.sale_day);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const revenueValues = revenueData.map(item => parseFloat(item.daily_revenue));
            
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: revenueValues,
                        borderColor: '#4299e1',
                        backgroundColor: 'rgba(66, 153, 225, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4299e1',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `$${context.parsed.y.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Payment Method Chart
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            const paymentData = <?php echo json_encode($analytics['sales']['by_payment']); ?>;
            
            const paymentLabels = paymentData.map(item => 
                item.payment_method.charAt(0).toUpperCase() + item.payment_method.slice(1)
            );
            
            const paymentValues = paymentData.map(item => parseFloat(item.percentage));
            const paymentColors = ['#4299e1', '#38a169', '#ed8936', '#f56565', '#9f7aea'];
            
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentLabels,
                    datasets: [{
                        data: paymentValues,
                        backgroundColor: paymentColors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${percentage}%`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Load analytics data for date range
        function loadAnalyticsData(startDate, endDate) {
            // Show loading state
            showLoading();
            
            // Simulate API call (in production, this would be a real AJAX call)
            setTimeout(() => {
                // For demo purposes, just reload the page with date parameters
                window.location.href = `analytics.php?start_date=${startDate}&end_date=${endDate}`;
            }, 1000);
        }
        
        // Refresh analytics data
        function refreshAnalytics() {
            // Simulate data refresh
            console.log('Refreshing analytics data...');
            
            // In production, this would fetch new data via AJAX
            // fetch('../api/get_analytics.php')
            //     .then(response => response.json())
            //     .then(data => {
            //         updateCharts(data);
            //     });
        }
        
        // Show loading state
        function showLoading() {
            const container = document.querySelector('.analytics-container');
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
            
            // Create loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading';
            loadingOverlay.innerHTML = `
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading analytics data...</p>
            `;
            loadingOverlay.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 1000;
            `;
            
            document.body.appendChild(loadingOverlay);
            
            // Remove loading state after 2 seconds
            setTimeout(() => {
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
                document.body.removeChild(loadingOverlay);
            }, 2000);
        }
        
        // Export data function
        function exportData(format) {
            alert(`Exporting analytics data as ${format.toUpperCase()}...\n\nIn production, this would generate and download a ${format} file.`);
            
            // In production, this would make an API call to generate the export
            // window.location.href = `../api/export_analytics.php?format=${format}&start_date=...&end_date=...`;
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Refresh with F5
            if (e.key === 'F5') {
                e.preventDefault();
                refreshAnalytics();
            }
            
            // Export with Ctrl+E
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportData('excel');
            }
            
            // Print with Ctrl+P
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
        
        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>