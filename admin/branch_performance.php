<?php
require_once '../includes/header.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Date range handling
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// First, let's check what columns exist in the sales table
$check_sales_columns = false;
$sales_columns = [];
$has_currency_column = false;
$has_amount_column = false;
$has_quantity_column = false;
$has_status_column = false;
$has_total_amount_column = false;
$has_fuel_type_column = false;
$has_product_type_column = false;
$has_item_name_column = false;

// Check if sales table exists
$check_table_sql = "SHOW TABLES LIKE 'sales'";
$table_result = $conn->query($check_table_sql);
$sales_table_exists = $table_result->num_rows > 0;

if ($sales_table_exists) {
    // Get columns from sales table
    $columns_sql = "SHOW COLUMNS FROM sales";
    $columns_result = $conn->query($columns_sql);
    while ($column = $columns_result->fetch_assoc()) {
        $sales_columns[] = $column['Field'];
        if ($column['Field'] == 'currency') $has_currency_column = true;
        if ($column['Field'] == 'amount' || $column['Field'] == 'total_amount') $has_amount_column = true;
        if ($column['Field'] == 'quantity' || $column['Field'] == 'liters') $has_quantity_column = true;
        if ($column['Field'] == 'status') $has_status_column = true;
        if ($column['Field'] == 'total_amount') $has_total_amount_column = true;
        if ($column['Field'] == 'fuel_type') $has_fuel_type_column = true;
        if ($column['Field'] == 'product_type') $has_product_type_column = true;
        if ($column['Field'] == 'item_name') $has_item_name_column = true;
    }
    $check_sales_columns = true;
}

// Check if required tables exist
$tables_exist = true;
$missing_tables = [];

// List of required tables for branch performance
$required_tables = ['branches', 'transactions'];
if (!$sales_table_exists) {
    $missing_tables[] = 'sales';
}

foreach ($required_tables as $table) {
    $check_sql = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($check_sql);
    if ($result->num_rows == 0) {
        $tables_exist = false;
        $missing_tables[] = $table;
    }
}

// Get all branches for filter
$branches = [];
if ($tables_exist || !in_array('branches', $missing_tables)) {
    $branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
    $branches_result = $conn->query($branches_sql);
    if ($branches_result) {
        $branches = $branches_result;
    }
}

// Currency conversion rate (LRD to USD - adjust as needed)
$exchange_rate = 200; // 1 USD = 200 LRD (Liberian Dollar)
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Branch Performance & Sales Report
                </h5>
                <div>
                    <?php if ($branch_id > 0): ?>
                        <a href="branch_performance.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to All Branches
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$tables_exist): ?>
                    <!-- Show missing tables alert -->
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle"></i> Missing Database Tables</h5>
                        <p>The following required tables are missing from your database:</p>
                        <ul>
                            <?php foreach($missing_tables as $table): ?>
                                <li><strong><?php echo $table; ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                        <hr>
                        <p>Please run the following SQL queries to create the missing tables:</p>
                        
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">SQL Queries to Create Tables</h6>
                            </div>
                            <div class="card-body">
                                <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 14px;">
<?php
// Generate SQL for missing tables
$sql_queries = [
    'transactions' => "CREATE TABLE IF NOT EXISTS transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        branch_id INT,
        transaction_type ENUM('income', 'expense') DEFAULT 'income',
        amount DECIMAL(10,2) DEFAULT 0.00,
        currency ENUM('USD', 'LRD') DEFAULT 'USD',
        description TEXT,
        transaction_date DATE,
        status VARCHAR(50) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_branch_id (branch_id),
        INDEX idx_transaction_date (transaction_date),
        INDEX idx_currency (currency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'sales' => "CREATE TABLE IF NOT EXISTS sales (
        id INT PRIMARY KEY AUTO_INCREMENT,
        branch_id INT,
        pump_id INT,
        fuel_type VARCHAR(50),
        quantity DECIMAL(10,2) DEFAULT 0.00,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        currency ENUM('USD', 'LRD') DEFAULT 'USD',
        customer_name VARCHAR(255),
        vehicle_plate VARCHAR(50),
        payment_method ENUM('cash', 'card', 'mobile_money') DEFAULT 'cash',
        attendant_id INT,
        sale_date DATE,
        status ENUM('completed', 'cancelled', 'pending') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_branch_id (branch_id),
        INDEX idx_sale_date (sale_date),
        INDEX idx_currency (currency),
        INDEX idx_fuel_type (fuel_type),
        INDEX idx_attendant_id (attendant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($missing_tables as $table) {
    if (isset($sql_queries[$table])) {
        echo htmlspecialchars($sql_queries[$table]) . "\n\n";
    }
}

// Check if branches table exists but might be missing columns
if (!in_array('branches', $missing_tables)) {
    echo "-- Add missing columns to branches table if needed\n";
    echo "ALTER TABLE branches 
          ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active',
          ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;\n";
}
?>
                                </pre>
                                <div class="mt-3">
                                    <p class="text-muted small">
                                        <i class="fas fa-info-circle"></i> You can run these queries in your database management tool (like phpMyAdmin) or use a MySQL client.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="branch_performance.php?refresh=1" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Refresh Page After Creating Tables
                            </a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Show sales table structure warning if needed -->
                    <?php if ($check_sales_columns && (!$has_currency_column || !$has_status_column || !$has_total_amount_column)): ?>
                        <div class="alert alert-warning mb-3">
                            <h6><i class="fas fa-exclamation-triangle"></i> Sales Table Structure Notice</h6>
                            <p>The sales table exists but doesn't have the expected structure. Found columns:</p>
                            <code><?php echo implode(', ', $sales_columns); ?></code>
                            <p class="mb-0 mt-2">The report will show basic sales data but some features may be limited.</p>
                            <ul class="mt-1">
                                <?php if (!$has_currency_column): ?>
                                <li>Missing currency column - assuming all sales are in USD</li>
                                <?php endif; ?>
                                <?php if (!$has_status_column): ?>
                                <li>Missing status column - including all sales records</li>
                                <?php endif; ?>
                                <?php if (!$has_total_amount_column): ?>
                                <li>Missing total_amount column - sales amount may not be accurate</li>
                                <?php endif; ?>
                                <?php if (!$has_fuel_type_column && !$has_product_type_column && !$has_item_name_column): ?>
                                <li>No product type column found - product breakdown will not be available</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filter Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="branch_id" class="form-label">Branch</label>
                                    <select class="form-control" id="branch_id" name="branch_id">
                                        <option value="0">All Branches</option>
                                        <?php 
                                        if ($branches && $branches->num_rows > 0):
                                            $branches->data_seek(0);
                                            while($branch = $branches->fetch_assoc()): ?>
                                                <option value="<?php echo $branch['id']; ?>" 
                                                    <?php echo ($branch_id == $branch['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $branch['branch_name']; ?>
                                                </option>
                                            <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php
                    // Fetch performance data
                    $performance_data = [];
                    $total_summary = [
                        'total_income_usd' => 0,
                        'total_income_lrd' => 0,
                        'total_expenses_usd' => 0,
                        'total_expenses_lrd' => 0,
                        'total_sales_usd' => 0,
                        'total_sales_lrd' => 0,
                        'net_profit_usd' => 0,
                        'net_profit_lrd' => 0,
                        'total_transactions' => 0,
                        'total_sales_count' => 0,
                        'total_sales_volume' => 0,
                        'total_customers' => 0
                    ];

                    if ($branch_id > 0) {
                        // Get specific branch performance
                        $branch_sql = "SELECT 
                                        b.id,
                                        b.branch_name,
                                        b.location,
                                        b.address,
                                        u.full_name as manager_name,
                                        b.phone,
                                        b.email,
                                        b.created_at
                                       FROM branches b
                                       LEFT JOIN users u ON b.manager_id = u.id
                                       WHERE b.id = ? AND b.status = 'active'";
                        $stmt = $conn->prepare($branch_sql);
                        $stmt->bind_param("i", $branch_id);
                        $stmt->execute();
                        $branch_info = $stmt->get_result()->fetch_assoc();
                        
                        if (!$branch_info) {
                            echo '<div class="alert alert-warning">Branch not found or inactive.</div>';
                        } else {
                            // Get financial data for the branch by currency
                            $finance_sql = "SELECT 
                                              currency,
                                              SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
                                              SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                                              COUNT(*) as transaction_count
                                             FROM transactions
                                             WHERE branch_id = ?
                                             AND transaction_date BETWEEN ? AND ?
                                             AND status = 'completed'
                                             GROUP BY currency";
                            
                            $stmt = $conn->prepare($finance_sql);
                            $stmt->bind_param("iss", $branch_id, $start_date, $end_date);
                            $stmt->execute();
                            $finance_result = $stmt->get_result();
                            
                            $finance_data = [
                                'USD' => ['income' => 0, 'expenses' => 0, 'transactions' => 0],
                                'LRD' => ['income' => 0, 'expenses' => 0, 'transactions' => 0]
                            ];
                            
                            while($row = $finance_result->fetch_assoc()) {
                                $currency = $row['currency'];
                                if (isset($finance_data[$currency])) {
                                    $finance_data[$currency]['income'] = $row['total_income'] ?? 0;
                                    $finance_data[$currency]['expenses'] = $row['total_expenses'] ?? 0;
                                    $finance_data[$currency]['transactions'] = $row['transaction_count'] ?? 0;
                                }
                            }
                            
                            // Get sales data - ADAPTED based on actual table structure
                            if ($sales_table_exists) {
                                // Determine the amount column to use
                                $amount_column = 'total_amount';
                                if (!$has_total_amount_column && in_array('amount', $sales_columns)) {
                                    $amount_column = 'amount';
                                } elseif (!$has_total_amount_column && !in_array('amount', $sales_columns)) {
                                    $amount_column = '0'; // No amount column found
                                }
                                
                                // Determine the quantity column to use
                                $quantity_column = 'quantity';
                                if (!$has_quantity_column && in_array('liters', $sales_columns)) {
                                    $quantity_column = 'liters';
                                } elseif (!$has_quantity_column && !in_array('liters', $sales_columns)) {
                                    $quantity_column = '0'; // No quantity column found
                                }
                                
                                if ($has_currency_column) {
                                    // If currency column exists, use it
                                    $sales_sql = "SELECT 
                                                    currency,
                                                    COUNT(*) as sales_count,
                                                    SUM($quantity_column) as total_quantity,
                                                    SUM($amount_column) as total_sales,
                                                    COUNT(DISTINCT customer_name) as total_customers,
                                                    AVG($amount_column) as average_sale
                                                   FROM sales
                                                   WHERE branch_id = ?
                                                   AND sale_date BETWEEN ? AND ?";
                                    
                                    // Add status condition only if status column exists
                                    if ($has_status_column) {
                                        $sales_sql .= " AND status = 'completed'";
                                    }
                                    
                                    $sales_sql .= " GROUP BY currency";
                                } else {
                                    // If no currency column, assume all sales are in USD
                                    $sales_sql = "SELECT 
                                                    COUNT(*) as sales_count,
                                                    SUM($quantity_column) as total_quantity,
                                                    SUM($amount_column) as total_sales,
                                                    COUNT(DISTINCT customer_name) as total_customers,
                                                    AVG($amount_column) as average_sale
                                                   FROM sales
                                                   WHERE branch_id = ?
                                                   AND sale_date BETWEEN ? AND ?";
                                    
                                    // Add status condition only if status column exists
                                    if ($has_status_column) {
                                        $sales_sql .= " AND status = 'completed'";
                                    }
                                }
                                
                                $stmt = $conn->prepare($sales_sql);
                                $stmt->bind_param("iss", $branch_id, $start_date, $end_date);
                                $stmt->execute();
                                $sales_result = $stmt->get_result();
                                
                                $sales_data = [
                                    'USD' => ['count' => 0, 'amount' => 0, 'quantity' => 0, 'customers' => 0, 'average' => 0],
                                    'LRD' => ['count' => 0, 'amount' => 0, 'quantity' => 0, 'customers' => 0, 'average' => 0]
                                ];
                                
                                if ($has_currency_column) {
                                    while($row = $sales_result->fetch_assoc()) {
                                        $currency = $row['currency'] ?? 'USD';
                                        if (isset($sales_data[$currency])) {
                                            $sales_data[$currency]['count'] = $row['sales_count'] ?? 0;
                                            $sales_data[$currency]['amount'] = $row['total_sales'] ?? 0;
                                            $sales_data[$currency]['quantity'] = $row['total_quantity'] ?? 0;
                                            $sales_data[$currency]['customers'] = $row['total_customers'] ?? 0;
                                            $sales_data[$currency]['average'] = $row['average_sale'] ?? 0;
                                        }
                                    }
                                } else {
                                    // All sales assumed to be in USD if no currency column
                                    if ($row = $sales_result->fetch_assoc()) {
                                        $sales_data['USD']['count'] = $row['sales_count'] ?? 0;
                                        $sales_data['USD']['amount'] = $row['total_sales'] ?? 0;
                                        $sales_data['USD']['quantity'] = $row['total_quantity'] ?? 0;
                                        $sales_data['USD']['customers'] = $row['total_customers'] ?? 0;
                                        $sales_data['USD']['average'] = $row['average_sale'] ?? 0;
                                    }
                                }
                                
                                // Get product type breakdown - FIXED: Check which column actually exists
                                $product_column = '';
                                if ($has_fuel_type_column) {
                                    $product_column = 'fuel_type';
                                } elseif ($has_product_type_column) {
                                    $product_column = 'product_type';
                                } elseif ($has_item_name_column) {
                                    $product_column = 'item_name';
                                }
                                
                                $product_breakdown = [];
                                if (!empty($product_column)) {
                                    if ($has_currency_column) {
                                        $product_sql = "SELECT 
                                                        $product_column as product_type,
                                                        currency,
                                                        SUM($quantity_column) as total_quantity,
                                                        SUM($amount_column) as total_amount,
                                                        COUNT(*) as sale_count
                                                       FROM sales
                                                       WHERE branch_id = ?
                                                       AND sale_date BETWEEN ? AND ?";
                                        
                                        // Add status condition only if status column exists
                                        if ($has_status_column) {
                                            $product_sql .= " AND status = 'completed'";
                                        }
                                        
                                        $product_sql .= " GROUP BY $product_column, currency
                                                       ORDER BY SUM($amount_column) DESC";
                                    } else {
                                        $product_sql = "SELECT 
                                                        $product_column as product_type,
                                                        SUM($quantity_column) as total_quantity,
                                                        SUM($amount_column) as total_amount,
                                                        COUNT(*) as sale_count
                                                       FROM sales
                                                       WHERE branch_id = ?
                                                       AND sale_date BETWEEN ? AND ?";
                                        
                                        // Add status condition only if status column exists
                                        if ($has_status_column) {
                                            $product_sql .= " AND status = 'completed'";
                                        }
                                        
                                        $product_sql .= " GROUP BY $product_column
                                                       ORDER BY SUM($amount_column) DESC";
                                    }
                                    
                                    $stmt = $conn->prepare($product_sql);
                                    $stmt->bind_param("iss", $branch_id, $start_date, $end_date);
                                    $stmt->execute();
                                    $product_result = $stmt->get_result();
                                    while($row = $product_result->fetch_assoc()) {
                                        $product_breakdown[] = $row;
                                    }
                                }
                                
                                // Get payment method breakdown if column exists
                                $payment_methods = [];
                                if (in_array('payment_method', $sales_columns) && $has_currency_column) {
                                    $payment_sql = "SELECT 
                                                      payment_method,
                                                      currency,
                                                      COUNT(*) as transaction_count,
                                                      SUM($amount_column) as total_amount
                                                     FROM sales
                                                     WHERE branch_id = ?
                                                     AND sale_date BETWEEN ? AND ?";
                                    
                                    // Add status condition only if status column exists
                                    if ($has_status_column) {
                                        $payment_sql .= " AND status = 'completed'";
                                    }
                                    
                                    $payment_sql .= " GROUP BY payment_method, currency";
                                    
                                    $stmt = $conn->prepare($payment_sql);
                                    $stmt->bind_param("iss", $branch_id, $start_date, $end_date);
                                    $stmt->execute();
                                    $payment_result = $stmt->get_result();
                                    while($row = $payment_result->fetch_assoc()) {
                                        $payment_methods[] = $row;
                                    }
                                }
                            } else {
                                // No sales table
                                $sales_data = [
                                    'USD' => ['count' => 0, 'amount' => 0, 'quantity' => 0, 'customers' => 0, 'average' => 0],
                                    'LRD' => ['count' => 0, 'amount' => 0, 'quantity' => 0, 'customers' => 0, 'average' => 0]
                                ];
                                $product_breakdown = [];
                                $payment_methods = [];
                            }
                            
                            // Compile performance data
                            $performance_data = [
                                'branch_info' => $branch_info,
                                'finance' => $finance_data,
                                'sales' => $sales_data,
                                'product_breakdown' => $product_breakdown,
                                'payment_methods' => $payment_methods,
                                'has_currency_column' => $has_currency_column,
                                'has_status_column' => $has_status_column,
                                'sales_table_exists' => $sales_table_exists,
                                'has_product_column' => !empty($product_column)
                            ];
                        }
                        
                    } else {
                        // Get all branches performance summary - FIXED ORDER BY clause
                        if ($sales_table_exists && $has_currency_column) {
                            // Determine the amount column to use
                            $amount_column = 's.total_amount';
                            if (!$has_total_amount_column && in_array('amount', $sales_columns)) {
                                $amount_column = 's.amount';
                            } elseif (!$has_total_amount_column && !in_array('amount', $sales_columns)) {
                                $amount_column = '0';
                            }
                            
                            // Determine the quantity column to use
                            $quantity_column = 's.quantity';
                            if (!$has_quantity_column && in_array('liters', $sales_columns)) {
                                $quantity_column = 's.liters';
                            } elseif (!$has_quantity_column && !in_array('liters', $sales_columns)) {
                                $quantity_column = '0';
                            }
                            
                            $summary_sql = "SELECT 
                                             b.id,
                                             b.branch_name,
                                             b.location,
                                             COUNT(DISTINCT t.id) as total_transactions,
                                             COUNT(DISTINCT s.id) as total_sales,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_lrd,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_lrd,
                                             SUM(CASE WHEN s.currency = 'USD' THEN $amount_column ELSE 0 END) as sales_usd,
                                             SUM(CASE WHEN s.currency = 'LRD' THEN $amount_column ELSE 0 END) as sales_lrd,
                                             SUM(CASE WHEN s.currency = 'USD' THEN $quantity_column ELSE 0 END) as volume_usd,
                                             SUM(CASE WHEN s.currency = 'LRD' THEN $quantity_column ELSE 0 END) as volume_lrd
                                            FROM branches b
                                            LEFT JOIN transactions t ON b.id = t.branch_id 
                                              AND t.transaction_date BETWEEN ? AND ?
                                              AND t.status = 'completed'
                                            LEFT JOIN sales s ON b.id = s.branch_id
                                              AND s.sale_date BETWEEN ? AND ?";
                            
                            // Add status condition for sales only if status column exists
                            if ($has_status_column) {
                                $summary_sql .= " AND s.status = 'completed'";
                            }
                            
                            $summary_sql .= " WHERE b.status = 'active'
                                            GROUP BY b.id, b.branch_name, b.location
                                            ORDER BY (SUM(CASE WHEN s.currency = 'USD' THEN $amount_column ELSE 0 END) + 
                                                     (SUM(CASE WHEN s.currency = 'LRD' THEN $amount_column ELSE 0 END) / ?)) DESC";
                            
                            $stmt = $conn->prepare($summary_sql);
                            $stmt->bind_param("ssssi", $start_date, $end_date, $start_date, $end_date, $exchange_rate);
                        } else if ($sales_table_exists) {
                            // Simplified query without currency breakdown for sales
                            // Determine the amount column to use
                            $amount_column = 's.total_amount';
                            if (!$has_total_amount_column && in_array('amount', $sales_columns)) {
                                $amount_column = 's.amount';
                            } elseif (!$has_total_amount_column && !in_array('amount', $sales_columns)) {
                                $amount_column = '0';
                            }
                            
                            // Determine the quantity column to use
                            $quantity_column = 's.quantity';
                            if (!$has_quantity_column && in_array('liters', $sales_columns)) {
                                $quantity_column = 's.liters';
                            } elseif (!$has_quantity_column && !in_array('liters', $sales_columns)) {
                                $quantity_column = '0';
                            }
                            
                            $summary_sql = "SELECT 
                                             b.id,
                                             b.branch_name,
                                             b.location,
                                             COUNT(DISTINCT t.id) as total_transactions,
                                             COUNT(DISTINCT s.id) as total_sales,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_lrd,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_lrd,
                                             SUM($amount_column) as sales_total,
                                             SUM($quantity_column) as sales_volume
                                            FROM branches b
                                            LEFT JOIN transactions t ON b.id = t.branch_id 
                                              AND t.transaction_date BETWEEN ? AND ?
                                              AND t.status = 'completed'
                                            LEFT JOIN sales s ON b.id = s.branch_id
                                              AND s.sale_date BETWEEN ? AND ?";
                            
                            // Add status condition for sales only if status column exists
                            if ($has_status_column) {
                                $summary_sql .= " AND s.status = 'completed'";
                            }
                            
                            $summary_sql .= " WHERE b.status = 'active'
                                            GROUP BY b.id, b.branch_name, b.location
                                            ORDER BY SUM($amount_column) DESC";
                            
                            $stmt = $conn->prepare($summary_sql);
                            $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
                        } else {
                            // No sales table, only transactions
                            $summary_sql = "SELECT 
                                             b.id,
                                             b.branch_name,
                                             b.location,
                                             COUNT(DISTINCT t.id) as total_transactions,
                                             0 as total_sales,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income_lrd,
                                             SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_usd,
                                             SUM(CASE WHEN t.currency = 'LRD' AND t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expenses_lrd,
                                             0 as sales_total,
                                             0 as sales_volume
                                            FROM branches b
                                            LEFT JOIN transactions t ON b.id = t.branch_id 
                                              AND t.transaction_date BETWEEN ? AND ?
                                              AND t.status = 'completed'
                                            WHERE b.status = 'active'
                                            GROUP BY b.id, b.branch_name, b.location
                                            ORDER BY SUM(CASE WHEN t.currency = 'USD' AND t.transaction_type = 'income' THEN t.amount ELSE 0 END) DESC";
                            
                            $stmt = $conn->prepare($summary_sql);
                            $stmt->bind_param("ss", $start_date, $end_date);
                        }
                        
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        while($row = $result->fetch_assoc()) {
                            $performance_data[] = $row;
                            
                            // Calculate totals
                            $total_summary['total_income_usd'] += $row['income_usd'] ?? 0;
                            $total_summary['total_income_lrd'] += $row['income_lrd'] ?? 0;
                            $total_summary['total_expenses_usd'] += $row['expenses_usd'] ?? 0;
                            $total_summary['total_expenses_lrd'] += $row['expenses_lrd'] ?? 0;
                            
                            if ($sales_table_exists) {
                                if ($has_currency_column) {
                                    $total_summary['total_sales_usd'] += $row['sales_usd'] ?? 0;
                                    $total_summary['total_sales_lrd'] += $row['sales_lrd'] ?? 0;
                                    $total_summary['total_sales_volume'] += ($row['volume_usd'] ?? 0) + ($row['volume_lrd'] ?? 0);
                                } else {
                                    $total_summary['total_sales_usd'] += $row['sales_total'] ?? 0;
                                    $total_summary['total_sales_volume'] += $row['sales_volume'] ?? 0;
                                }
                                
                                $total_summary['total_sales_count'] += $row['total_sales'] ?? 0;
                            }
                            
                            $total_summary['total_transactions'] += $row['total_transactions'] ?? 0;
                        }
                        
                        // Calculate net profit
                        $total_summary['net_profit_usd'] = $total_summary['total_income_usd'] - $total_summary['total_expenses_usd'];
                        $total_summary['net_profit_lrd'] = $total_summary['total_income_lrd'] - $total_summary['total_expenses_lrd'];
                    }
                    ?>

                    <?php if ($branch_id > 0 && isset($performance_data['branch_info'])): ?>
                        <!-- Single Branch Performance View -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Branch Information</h6>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 text-center">
                                                <div class="mb-3">
                                                    <i class="fas fa-building fa-3x text-primary"></i>
                                                    <h5 class="mt-2"><?php echo $performance_data['branch_info']['branch_name']; ?></h5>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-map-marker-alt"></i> Location:</strong><br>
                                                    <?php echo $performance_data['branch_info']['location']; ?>
                                                </p>
                                                <?php if (!empty($performance_data['branch_info']['manager_name'])): ?>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-user-tie"></i> Manager:</strong><br>
                                                    <?php echo $performance_data['branch_info']['manager_name']; ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php if (!empty($performance_data['branch_info']['address'])): ?>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-home"></i> Address:</strong><br>
                                                    <?php echo $performance_data['branch_info']['address']; ?>
                                                </p>
                                                <?php endif; ?>
                                                <?php if (!empty($performance_data['branch_info']['phone'])): ?>
                                                <p class="mb-0">
                                                    <strong><i class="fas fa-phone"></i> Phone:</strong><br>
                                                    <?php echo $performance_data['branch_info']['phone']; ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php if (!empty($performance_data['branch_info']['email'])): ?>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-envelope"></i> Email:</strong><br>
                                                    <?php echo $performance_data['branch_info']['email']; ?>
                                                </p>
                                                <?php endif; ?>
                                                <?php if (!empty($performance_data['branch_info']['created_at'])): ?>
                                                <p class="mb-0">
                                                    <strong><i class="fas fa-calendar"></i> Established:</strong><br>
                                                    <?php echo date('F j, Y', strtotime($performance_data['branch_info']['created_at'])); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SALES PERFORMANCE CARDS -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-gas-pump"></i> Sales Performance</h5>
                            </div>
                            
                            <!-- USD Sales Cards -->
                            <div class="col-md-3">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    $<?php echo number_format($performance_data['sales']['USD']['amount'] ?? 0, 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-primary">
                                                        <?php echo number_format($performance_data['sales']['USD']['count'] ?? 0); ?> transactions
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Average Sale
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    $<?php echo number_format($performance_data['sales']['USD']['average'] ?? 0, 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-success">
                                                        <?php echo number_format($performance_data['sales']['USD']['quantity'] ?? 0, 2); ?> Liters
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($performance_data['has_currency_column']): ?>
                            <!-- LRD Sales Card -->
                            <div class="col-md-3">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    LRD Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    LD<?php echo number_format($performance_data['sales']['LRD']['amount'] ?? 0, 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-warning">
                                                        <?php echo number_format($performance_data['sales']['LRD']['count'] ?? 0); ?> transactions
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Total Customers
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?php echo number_format(($performance_data['sales']['USD']['customers'] ?? 0) + ($performance_data['sales']['LRD']['customers'] ?? 0)); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-info">
                                                        Unique customers
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-users fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FINANCIAL PERFORMANCE CARDS -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-chart-pie"></i> Financial Performance</h5>
                            </div>
                            
                            <!-- USD Financial Cards -->
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title">USD Income</h6>
                                                <h3 class="mb-0">$<?php echo number_format($performance_data['finance']['USD']['income'] ?? 0, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-money-bill-wave fa-2x"></i>
                                        </div>
                                        <p class="mb-0 mt-2 small">From <?php echo $performance_data['finance']['USD']['transactions'] ?? 0; ?> transactions</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title">USD Expenses</h6>
                                                <h3 class="mb-0">$<?php echo number_format($performance_data['finance']['USD']['expenses'] ?? 0, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-money-bill-alt fa-2x"></i>
                                        </div>
                                        <p class="mb-0 mt-2 small">From <?php echo $performance_data['finance']['USD']['transactions'] ?? 0; ?> transactions</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title">USD Net Profit</h6>
                                                <?php 
                                                $net_profit_usd = ($performance_data['finance']['USD']['income'] ?? 0) - ($performance_data['finance']['USD']['expenses'] ?? 0);
                                                ?>
                                                <h3 class="mb-0">$<?php echo number_format($net_profit_usd, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-chart-line fa-2x"></i>
                                        </div>
                                        <p class="mb-0 mt-2 small">
                                            Profit Margin: 
                                            <?php 
                                            $margin_usd = ($performance_data['finance']['USD']['income'] ?? 0) > 0 ? 
                                                ($net_profit_usd / ($performance_data['finance']['USD']['income'] ?? 1)) * 100 : 0;
                                            echo number_format($margin_usd, 1); ?>%
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- LRD Financial Card -->
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title">LRD Net Profit</h6>
                                                <?php 
                                                $net_profit_lrd = ($performance_data['finance']['LRD']['income'] ?? 0) - ($performance_data['finance']['LRD']['expenses'] ?? 0);
                                                ?>
                                                <h3 class="mb-0">LD<?php echo number_format($net_profit_lrd, 2); ?></h3>
                                            </div>
                                            <i class="fas fa-chart-line fa-2x"></i>
                                        </div>
                                        <p class="mb-0 mt-2 small">
                                            <?php echo number_format($performance_data['finance']['LRD']['transactions'] ?? 0); ?> transactions
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Analysis -->
                        <div class="row">
                            <?php if (!empty($performance_data['product_breakdown'])): ?>
                            <!-- Product Type Breakdown -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-gas-pump"></i> Product Breakdown</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Product Type</th>
                                                        <?php if ($performance_data['has_currency_column']): ?>
                                                        <th>Currency</th>
                                                        <?php endif; ?>
                                                        <th>Quantity</th>
                                                        <th>Amount</th>
                                                        <th>Sales</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($performance_data['product_breakdown'] as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo htmlspecialchars($product['product_type'] ?? 'Unknown'); ?>
                                                            </span>
                                                        </td>
                                                        <?php if ($performance_data['has_currency_column']): ?>
                                                        <td>
                                                            <span class="badge bg-<?php echo ($product['currency'] ?? 'USD') == 'USD' ? 'success' : 'primary'; ?>">
                                                                <?php echo $product['currency'] ?? 'USD'; ?>
                                                            </span>
                                                        </td>
                                                        <?php endif; ?>
                                                        <td><?php echo number_format($product['total_quantity'] ?? 0, 2); ?> L</td>
                                                        <td class="text-success">
                                                            <strong>
                                                                $<?php echo number_format($product['total_amount'] ?? 0, 2); ?>
                                                            </strong>
                                                        </td>
                                                        <td><?php echo number_format($product['sale_count'] ?? 0); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Payment Methods -->
                            <?php if (!empty($performance_data['payment_methods'])): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-credit-card"></i> Payment Methods</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Method</th>
                                                        <?php if ($performance_data['has_currency_column']): ?>
                                                        <th>Currency</th>
                                                        <?php endif; ?>
                                                        <th>Transactions</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($performance_data['payment_methods'] as $payment): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'cash')); ?>
                                                            </span>
                                                        </td>
                                                        <?php if ($performance_data['has_currency_column']): ?>
                                                        <td>
                                                            <span class="badge bg-<?php echo ($payment['currency'] ?? 'USD') == 'USD' ? 'success' : 'primary'; ?>">
                                                                <?php echo $payment['currency'] ?? 'USD'; ?>
                                                            </span>
                                                        </td>
                                                        <?php endif; ?>
                                                        <td><?php echo number_format($payment['transaction_count'] ?? 0); ?></td>
                                                        <td class="text-success">
                                                            <strong>
                                                                $<?php echo number_format($payment['total_amount'] ?? 0, 2); ?>
                                                            </strong>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- All Branches Comparison View -->
                        <div class="row mb-4">
                            <!-- Summary Cards -->
                            <div class="col-md-12 mb-3">
                                <h5><i class="fas fa-chart-bar"></i> Overall Summary</h5>
                            </div>
                            
                            <!-- Sales Summary -->
                            <div class="col-md-3">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    $<?php echo number_format($total_summary['total_sales_usd'], 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-primary">
                                                        <?php echo number_format($total_summary['total_sales_count']); ?> sales
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($has_currency_column): ?>
                            <div class="col-md-3">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    LRD Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    LD<?php echo number_format($total_summary['total_sales_lrd'], 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-success">
                                                        Volume: <?php echo number_format($total_summary['total_sales_volume'], 2); ?> L
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-3">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Sales Volume
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?php echo number_format($total_summary['total_sales_volume'], 2); ?> L
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-success">
                                                        Total liters sold
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-gas-pump fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <div class="card border-left-danger shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    Net Profit (USD)
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    $<?php echo number_format($total_summary['net_profit_usd'], 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-danger">
                                                        <?php echo number_format($total_summary['total_transactions']); ?> transactions
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Net Profit (LRD)
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    LD<?php echo number_format($total_summary['net_profit_lrd'], 2); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-warning">
                                                        Overall performance
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Branches Performance Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Branches Performance Comparison</h6>
                                        <small class="text-muted">Sorted by total sales</small>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($performance_data)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Branch</th>
                                                            <th>Location</th>
                                                            <th>Sales</th>
                                                            <?php if ($has_currency_column): ?>
                                                            <th>LRD Sales</th>
                                                            <?php endif; ?>
                                                            <th>Volume</th>
                                                            <th>Income (USD)</th>
                                                            <th>Expenses (USD)</th>
                                                            <th>Net Profit</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($performance_data as $branch): 
                                                            $net_profit_usd = ($branch['income_usd'] ?? 0) - ($branch['expenses_usd'] ?? 0);
                                                            if ($sales_table_exists && $has_currency_column) {
                                                                $total_sales = ($branch['sales_usd'] ?? 0) + (($branch['sales_lrd'] ?? 0) / $exchange_rate);
                                                                $total_volume = ($branch['volume_usd'] ?? 0) + ($branch['volume_lrd'] ?? 0);
                                                            } else if ($sales_table_exists) {
                                                                $total_sales = $branch['sales_total'] ?? 0;
                                                                $total_volume = $branch['sales_volume'] ?? 0;
                                                            } else {
                                                                $total_sales = 0;
                                                                $total_volume = 0;
                                                            }
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo $branch['branch_name']; ?></strong>
                                                            </td>
                                                            <td><?php echo $branch['location']; ?></td>
                                                            <td class="text-success">
                                                                <strong>$<?php echo number_format($total_sales, 2); ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo number_format($branch['total_sales'] ?? 0); ?> sales
                                                                </small>
                                                            </td>
                                                            <?php if ($sales_table_exists && $has_currency_column): ?>
                                                            <td class="text-primary">
                                                                <strong>LD<?php echo number_format($branch['sales_lrd'] ?? 0, 2); ?></strong>
                                                            </td>
                                                            <?php endif; ?>
                                                            <td>
                                                                <span class="badge bg-info">
                                                                    <?php echo number_format($total_volume, 2); ?> L
                                                                </span>
                                                            </td>
                                                            <td class="text-success">
                                                                $<?php echo number_format($branch['income_usd'] ?? 0, 2); ?>
                                                            </td>
                                                            <td class="text-danger">
                                                                $<?php echo number_format($branch['expenses_usd'] ?? 0, 2); ?>
                                                            </td>
                                                            <td class="<?php echo $net_profit_usd >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                <strong>$<?php echo number_format($net_profit_usd, 2); ?></strong>
                                                                <br>
                                                                <small>
                                                                    <?php echo $branch['total_transactions'] ?? 0; ?> transactions
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <a href="branch_performance.php?branch_id=<?php echo $branch['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                                                   class="btn btn-sm btn-info">
                                                                    <i class="fas fa-chart-bar"></i> Details
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle fa-2x mb-3"></i>
                                                <h5>No Data Available</h5>
                                                <p>No branch performance data found for the selected period.</p>
                                                <p class="small text-muted">Try selecting a different date range or check if branches have active sales.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Date range validation
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        // Set max date to today
        const today = new Date().toISOString().split('T')[0];
        startDate.max = today;
        endDate.max = today;
        
        // Set end date min to start date
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
        
        // Initialize end date min
        if (startDate.value) {
            endDate.min = startDate.value;
        }
        
        // Validate date range
        const form = document.querySelector('form[method="GET"]');
        form.addEventListener('submit', function(e) {
            if (new Date(startDate.value) > new Date(endDate.value)) {
                e.preventDefault();
                alert('Start date cannot be later than end date!');
                startDate.focus();
                return false;
            }
            
            // Limit date range to maximum 1 year
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays > 365) {
                e.preventDefault();
                alert('Date range cannot exceed 365 days! Please select a shorter period.');
                return false;
            }
        });
    }
    
    // Auto-submit on branch change when in single branch view
    const branchSelect = document.getElementById('branch_id');
    if (branchSelect && <?php echo $branch_id; ?> > 0) {
        branchSelect.addEventListener('change', function() {
            if (this.value === "0") {
                // Going back to all branches
                window.location.href = 'branch_performance.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>';
            }
        });
    }
});
</script>

<style>
/* Print styles */
@media print {
    .card-header button,
    .btn,
    form,
    .alert {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    h1, h2, h3, h4, h5, h6 {
        color: #000 !important;
    }
    
    .table {
        border: 1px solid #000 !important;
    }
    
    .table th {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background-color: transparent !important;
    }
}

/* Custom styling */
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid #e3e6f0;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}

.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
    padding: 1rem 1.25rem;
}

.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
    transition: all 0.2s ease;
}

.table-bordered {
    border: 1px solid #e3e6f0;
}

.table-bordered th,
.table-bordered td {
    border: 1px solid #e3e6f0;
}

.thead-light th {
    background-color: #f8f9fa;
    color: #5a5c69;
    font-weight: 600;
}

/* Color coding for financial data */
.text-success { color: #1cc88a !important; }
.text-danger { color: #e74a3b !important; }
.text-primary { color: #4e73df !important; }
.text-warning { color: #f6c23e !important; }
.text-info { color: #36b9cc !important; }

.bg-success { background-color: #1cc88a !important; }
.bg-danger { background-color: #e74a3b !important; }
.bg-primary { background-color: #4e73df !important; }
.bg-warning { background-color: #f6c23e !important; }
.bg-info { background-color: #36b9cc !important; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>