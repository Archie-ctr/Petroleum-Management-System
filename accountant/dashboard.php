<?php
require_once '../includes/header.php';

// Financial statistics
$today = date('Y-m-d');
$month = date('Y-m');
$branch_id = $accountant_branch_id ?? null;
$branch_filter_sales = $branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '';
$branch_filter_expenses = $branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '';

// Today's summary
$today_sql = "SELECT 
              COALESCE(SUM(total_amount), 0) as sales,
              COUNT(*) as transactions
              FROM sales 
              WHERE DATE(sale_date) = '$today'$branch_filter_sales";
$today_result = $conn->query($today_sql);
$today_stats = $today_result->fetch_assoc();

// Month's summary
$month_sql = "SELECT 
              COALESCE(SUM(total_amount), 0) as sales,
              COUNT(*) as transactions
              FROM sales 
              WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$month'$branch_filter_sales";
$month_result = $conn->query($month_sql);
$month_stats = $month_result->fetch_assoc();

// Expenses summary
$expenses_sql = "SELECT 
                 COALESCE(SUM(amount), 0) as total,
                 COUNT(*) as count
                 FROM expenses 
                 WHERE status = 'approved'
                 AND DATE_FORMAT(expense_date, '%Y-%m') = '$month'$branch_filter_expenses";
$expenses_result = $conn->query($expenses_sql);
$expenses_stats = $expenses_result->fetch_assoc();
$payroll_sql = "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
                FROM staff_payments
                WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month'"
                . ($branch_id !== null ? ' AND branch_id = ' . (int)$branch_id : '');
$payroll_result = $conn->query($payroll_sql);
$payroll_stats = $payroll_result->fetch_assoc();
$expenses_stats['total'] += (float)$payroll_stats['total'];
$expenses_stats['count'] += (int)$payroll_stats['count'];

// Pending payments
$pending_sql = "SELECT COUNT(*) as count FROM expenses WHERE status = 'pending'$branch_filter_expenses";
$pending_result = $conn->query($pending_sql);
$pending_stats = $pending_result->fetch_assoc();
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tachometer-alt"></i> Accountant Dashboard
                </h5>
            </div>
            <div class="card-body">
                <!-- Financial Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Today's Sales</h6>
                                <h2 class="card-title">$<?php echo number_format($today_stats['sales'], 2); ?></h2>
                                <p class="card-text"><?php echo $today_stats['transactions']; ?> transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Month's Sales</h6>
                                <h2 class="card-title">$<?php echo number_format($month_stats['sales'], 2); ?></h2>
                                <p class="card-text"><?php echo $month_stats['transactions']; ?> transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Month's Expenses</h6>
                                <h2 class="card-title">$<?php echo number_format($expenses_stats['total'], 2); ?></h2>
                                <p class="card-text"><?php echo $expenses_stats['count']; ?> items</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Pending Approvals</h6>
                                <h2 class="card-title"><?php echo $pending_stats['count']; ?></h2>
                                <p class="card-text">Expenses awaiting approval</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Quick Financial Actions</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="invoices.php" class="btn btn-primary">
                                        <i class="fas fa-file-invoice"></i> Manage Invoices
                                    </a>
                                    <a href="payments.php" class="btn btn-success">
                                        <i class="fas fa-money-check-alt"></i> Process Payments
                                    </a>
                                    <a href="payroll.php" class="btn btn-warning">
                                        <i class="fas fa-users"></i> Staff Payroll
                                    </a>
                                    <a href="reports.php" class="btn btn-info">
                                        <i class="fas fa-chart-pie"></i> Financial Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Financial Activities -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Recent Sales</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $recent_sales_sql = "SELECT s.sale_number, s.total_amount, s.sale_date, 
                                                    b.branch_name, f.fuel_name
                                                    FROM sales s
                                                    JOIN branches b ON s.branch_id = b.id
                                                    JOIN fuel_types f ON s.fuel_type_id = f.id
                                                    WHERE 1=1" . ($branch_id !== null ? " AND s.branch_id = " . (int)$branch_id : '') . "
                                                    ORDER BY s.sale_date DESC LIMIT 5";
                                $recent_sales = $conn->query($recent_sales_sql);
                                ?>
                                <div class="list-group">
                                    <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $sale['sale_number']; ?></h6>
                                            <strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $sale['branch_name']; ?> - <?php echo $sale['fuel_name']; ?>
                                            <span class="float-end">
                                                <?php echo date('H:i', strtotime($sale['sale_date'])); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Pending Expenses</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $pending_expenses_sql = "SELECT e.*, b.branch_name, u.full_name as recorded_by_name
                                                        FROM expenses e
                                                        JOIN branches b ON e.branch_id = b.id
                                                        JOIN users u ON e.recorded_by = u.id
                                                        WHERE e.status = 'pending'
                                                        " . ($branch_id !== null ? "AND e.branch_id = " . (int)$branch_id : '') . "
                                                        ORDER BY e.created_at DESC LIMIT 5";
                                $pending_expenses = $conn->query($pending_expenses_sql);
                                ?>
                                <div class="list-group">
                                    <?php while($expense = $pending_expenses->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $expense['expense_type']; ?></h6>
                                            <strong class="text-warning">$<?php echo number_format($expense['amount'], 2); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $expense['branch_name']; ?> - Recorded by: <?php echo $expense['recorded_by_name']; ?>
                                            <span class="float-end">
                                                <?php echo date('M d', strtotime($expense['expense_date'])); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once '../includes/footer.php';
?>