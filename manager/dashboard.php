<?php
require_once '../includes/manager_header.php';

// Get branch-specific statistics
$branch_id = $_SESSION['branch_id'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Sales Today
$sales_sql = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
              FROM sales 
              WHERE branch_id = $branch_id 
              AND DATE(sale_date) = '$today'";
$sales_result = $conn->query($sales_sql);
$today_sales = $sales_result->fetch_assoc();

// Sales Yesterday
$yesterday_sql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                  FROM sales 
                  WHERE branch_id = $branch_id 
                  AND DATE(sale_date) = '$yesterday'";
$yesterday_result = $conn->query($yesterday_sql);
$yesterday_total = $yesterday_result->fetch_assoc()['total'];

// Calculate growth
$sales_growth = 0;
if ($yesterday_total > 0) {
    $sales_growth = round((($today_sales['total'] - $yesterday_total) / $yesterday_total) * 100, 1);
}

// Fuel Inventory
$fuel_sql = "SELECT f.fuel_name, t.tank_number, t.current_quantity, t.capacity,
                    ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage
             FROM tanks t
             JOIN fuel_types f ON t.fuel_type_id = f.id
             WHERE t.branch_id = $branch_id
             AND t.status = 'active'
             ORDER BY percentage ASC";
$fuel_result = $conn->query($fuel_sql);

// Active attendants
$attendants_sql = "SELECT COUNT(*) as count FROM users 
                   WHERE branch_id = $branch_id 
                   AND role = 'attendant' 
                   AND status = 'active'";
$attendants_result = $conn->query($attendants_sql);
$active_attendants = $attendants_result->fetch_assoc()['count'];

// Pending expenses
$expenses_sql = "SELECT COUNT(*) as count FROM expenses 
                 WHERE branch_id = $branch_id 
                 AND status = 'pending'";
$expenses_result = $conn->query($expenses_sql);
$pending_expenses = $expenses_result->fetch_assoc()['count'];
?>
<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Branch Manager Dashboard</h4>
                    <p class="text-muted mb-0">Welcome back, <?php echo $_SESSION['full_name']; ?>! Here's your branch overview.</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary">Branch: <?php echo $branch['branch_name']; ?></span>
                    <span class="badge bg-success">Today: <?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Today's Sales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                $<?php echo number_format($today_sales['total'], 2); ?>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-<?php echo $sales_growth >= 0 ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo $sales_growth >= 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs($sales_growth); ?>%
                                </span>
                                <small class="text-muted ml-2">from yesterday</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Today's Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $today_sales['count']; ?>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">Average: $<?php echo $today_sales['count'] > 0 ? number_format($today_sales['total'] / $today_sales['count'], 2) : '0.00'; ?></small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Attendants</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $active_attendants; ?>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">Currently on duty</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Approvals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $pending_expenses; ?>
                            </div>
                            <div class="mt-2">
                                <a href="expenses.php?status=pending" class="btn btn-sm btn-warning">
                                    <i class="fas fa-eye"></i> Review
                                </a>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="sales.php?action=new" class="btn btn-primary btn-block py-3">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i><br>
                                New Sale
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="expenses.php?action=add" class="btn btn-success btn-block py-3">
                                <i class="fas fa-money-bill-wave fa-2x mb-2"></i><br>
                                Record Expense
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="#fuel-inventory" class="btn btn-info btn-block py-3">
                                <i class="fas fa-gas-pump fa-2x mb-2"></i><br>
                                Check Inventory
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="reports.php?report_type=sales" class="btn btn-warning btn-block py-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                Sales Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fuel Inventory Status -->
    <div class="row mb-4" id="fuel-inventory" style="scroll-margin-top: 84px;">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Fuel Inventory Status</h6>
                    <a href="#fuel-inventory" class="btn btn-sm btn-primary">View Inventory</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        if ($fuel_result->num_rows > 0):
                            while($tank = $fuel_result->fetch_assoc()): 
                                $percentage = $tank['percentage'];
                                $bg_color = $percentage > 50 ? 'bg-success' : ($percentage > 20 ? 'bg-warning' : 'bg-danger');
                                $text_color = $percentage > 50 ? 'text-success' : ($percentage > 20 ? 'text-warning' : 'text-danger');
                        ?>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-<?php echo $percentage > 50 ? 'success' : ($percentage > 20 ? 'warning' : 'danger'); ?> shadow h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                <?php echo $tank['fuel_name']; ?> (Tank <?php echo $tank['tank_number']; ?>)
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold <?php echo $text_color; ?>">
                                                <?php echo $percentage; ?>%
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?php echo number_format($tank['current_quantity'], 2); ?>L / <?php echo number_format($tank['capacity'], 2); ?>L
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-oil-can fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="progress mt-3" style="height: 10px;">
                                        <div class="progress-bar <?php echo $bg_color; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                        </div>
                                    </div>
                                    <?php if ($percentage < 20): ?>
                                    <div class="alert alert-danger alert-sm mt-3 mb-0 py-1">
                                        <i class="fas fa-exclamation-triangle"></i> Low stock! Please refill.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tanks found for this branch.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Sales & Alerts -->
    <div class="row">
        <!-- Recent Sales -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
                    <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sale No.</th>
                                    <th>Fuel Type</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Time</th>
                                    <th>Attendant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_sales_sql = "SELECT s.*, f.fuel_name, u.full_name as attendant_name 
                                                     FROM sales s
                                                     JOIN fuel_types f ON s.fuel_type_id = f.id
                                                     JOIN users u ON s.attendant_id = u.id
                                                     WHERE s.branch_id = $branch_id
                                                     ORDER BY s.sale_date DESC LIMIT 8";
                                $recent_sales = $conn->query($recent_sales_sql);
                                if ($recent_sales->num_rows > 0):
                                    while($sale = $recent_sales->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>
                                        <a href="sales.php?action=view&id=<?php echo $sale['id']; ?>" class="text-primary">
                                            <?php echo $sale['sale_number']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $sale['fuel_name']; ?></td>
                                    <td><?php echo number_format($sale['quantity'], 2); ?> L</td>
                                    <td class="font-weight-bold text-success">
                                        $<?php echo number_format($sale['total_amount'], 2); ?>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $sale['attendant_name']; ?></span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No sales today</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Notifications -->
        <div class="col-xl-4 col-lg-5 mb-4" id="equipment-alerts" style="scroll-margin-top: 84px;">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Alerts & Notifications</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Low stock alerts
                    $low_stock_sql = "SELECT t.*, f.fuel_name 
                                      FROM tanks t
                                      JOIN fuel_types f ON t.fuel_type_id = f.id
                                      WHERE t.branch_id = $branch_id
                                      AND t.current_quantity < t.minimum_threshold
                                      AND t.status = 'active'
                                      ORDER BY (t.current_quantity / t.capacity) ASC
                                      LIMIT 3";
                    $low_stock_result = $conn->query($low_stock_sql);
                    
                    // Maintenance pumps
                    $maintenance_sql = "SELECT p.* FROM pumps p
                                        WHERE p.branch_id = $branch_id
                                        AND p.status = 'maintenance'
                                        LIMIT 3";
                    $maintenance_result = $conn->query($maintenance_sql);
                    
                    // Pending expenses
                    $pending_expenses_sql = "SELECT e.* FROM expenses e
                                             WHERE e.branch_id = $branch_id
                                             AND e.status = 'pending'
                                             ORDER BY e.created_at DESC
                                             LIMIT 3";
                    $pending_expenses_result = $conn->query($pending_expenses_sql);
                    ?>
                    
                    <!-- Low Stock Alerts -->
                    <?php if ($low_stock_result->num_rows > 0): ?>
                    <div class="alert alert-danger mb-3">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                        </h6>
                        <div class="small">
                            <?php while($tank = $low_stock_result->fetch_assoc()): 
                                $percentage = round(($tank['current_quantity'] / $tank['capacity']) * 100, 1);
                            ?>
                            <div class="mb-1">
                                <strong><?php echo $tank['fuel_name']; ?></strong> - Tank <?php echo $tank['tank_number']; ?>: 
                                <?php echo $percentage; ?>%
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="#fuel-inventory" class="btn btn-sm btn-danger mt-2">Check Inventory</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Maintenance Alerts -->
                    <?php if ($maintenance_result->num_rows > 0): ?>
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading">
                            <i class="fas fa-tools"></i> Maintenance Required
                        </h6>
                        <div class="small">
                            <?php while($pump = $maintenance_result->fetch_assoc()): ?>
                            <div class="mb-1">
                                Pump <?php echo $pump['pump_number']; ?> needs maintenance
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="#equipment-alerts" class="btn btn-sm btn-warning mt-2">View Equipment Alerts</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pending Approvals -->
                    <?php if ($pending_expenses_result->num_rows > 0): ?>
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">
                            <i class="fas fa-clock"></i> Pending Approvals
                        </h6>
                        <div class="small">
                            <?php while($expense = $pending_expenses_result->fetch_assoc()): ?>
                            <div class="mb-1">
                                <strong>$<?php echo number_format($expense['amount'], 2); ?></strong> - 
                                <?php echo substr($expense['description'], 0, 50); ?>...
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="expenses.php?status=pending" class="btn btn-sm btn-info mt-2">Review</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- No Alerts -->
                    <?php if ($low_stock_result->num_rows == 0 && $maintenance_result->num_rows == 0 && $pending_expenses_result->num_rows == 0): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                        <p class="text-muted">No alerts or notifications</p>
                        <small class="text-muted">All systems are running smoothly</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>