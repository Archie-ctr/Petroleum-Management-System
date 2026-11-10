<?php
// accountant_header.php
// This is a partial header specific for accountant role

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'accountant') {
    header('Location: ../public/login.php');
    exit();
}
$branch_id = filter_var($_SESSION['branch_id'] ?? null, FILTER_VALIDATE_INT);
if (!$branch_id || $branch_id < 1) {
    http_response_code(403);
    exit('Your account is not assigned to a branch. Contact an administrator.');
}

// Get accountant-specific stats
$accountant_stats = [];
$today = date('Y-m-d');
$month = date('Y-m');

try {
    // Today's sales
    $sales_sql = "SELECT COALESCE(SUM(total_amount), 0) as today_sales
                  FROM sales WHERE DATE(sale_date) = ? AND branch_id = ?";
    $sales_stmt = $conn->prepare($sales_sql);
    $sales_stmt->bind_param("si", $today, $branch_id);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    $accountant_stats['today_sales'] = (float)$sales_result->fetch_assoc()['today_sales'];
    $sales_stmt->close();
    
    // Pending expenses
    $pending_sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                    FROM expenses WHERE status = 'pending' AND branch_id = ?";
    $pending_stmt = $conn->prepare($pending_sql);
    $pending_stmt->bind_param("i", $branch_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $pending_data = $pending_result->fetch_assoc();
    $accountant_stats['pending_expenses'] = (int)$pending_data['count'];
    $accountant_stats['pending_amount'] = (float)$pending_data['total'];
    $pending_stmt->close();
    
    // Month's revenue
    $month_sql = "SELECT COALESCE(SUM(total_amount), 0) as month_sales
                  FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ? AND branch_id = ?";
    $month_stmt = $conn->prepare($month_sql);
    $month_stmt->bind_param("si", $month, $branch_id);
    $month_stmt->execute();
    $month_result = $month_stmt->get_result();
    $accountant_stats['month_sales'] = (float)$month_result->fetch_assoc()['month_sales'];
    $month_stmt->close();
    
    // Month's expenses
    $expense_sql = "SELECT COALESCE(SUM(amount), 0) as month_expenses
                    FROM expenses WHERE status = 'approved'
                    AND DATE_FORMAT(expense_date, '%Y-%m') = ? AND branch_id = ?";
    $expense_stmt = $conn->prepare($expense_sql);
    $expense_stmt->bind_param("si", $month, $branch_id);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result();
    $accountant_stats['month_expenses'] = (float)$expense_result->fetch_assoc()['month_expenses'];
    $expense_stmt->close();

    $payroll_stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS month_payroll
         FROM staff_payments
         WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND branch_id = ?"
    );
    $payroll_stmt->bind_param("si", $month, $branch_id);
    $payroll_stmt->execute();
    $accountant_stats['month_expenses'] += (float)$payroll_stmt->get_result()->fetch_assoc()['month_payroll'];
    $payroll_stmt->close();
    
} catch (Exception $e) {
    error_log("Accountant stats error: " . $e->getMessage());
    $accountant_stats = [
        'today_sales' => 0,
        'pending_expenses' => 0,
        'pending_amount' => 0,
        'month_sales' => 0,
        'month_expenses' => 0
    ];
}
?>

<!-- Accountant Header - Mobile Friendly -->
<style>
    /* Accountant Header Specific Styles */
    .accountant-header {
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .accountant-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    @media (max-width: 768px) {
        .accountant-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 576px) {
        .accountant-stats-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .accountant-stat-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 8px;
        padding: 15px;
        transition: all 0.3s ease;
        border-left: 4px solid #198754;
    }
    
    .accountant-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }
    
    .accountant-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        font-size: 1.2rem;
    }
    
    .accountant-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #198754;
        line-height: 1.2;
    }
    
    .accountant-stat-label {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
    }
    
    .accountant-quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    @media (max-width: 576px) {
        .accountant-quick-actions {
            justify-content: flex-start;
            overflow-x: auto;
            padding-bottom: 10px;
        }
    }
    
    .accountant-action-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .accountant-action-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
        text-decoration: none;
        color: white;
    }
    
    .accountant-header-title {
        color: white;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .accountant-header-title i {
        background: rgba(255, 255, 255, 0.2);
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="accountant-header">
    <!-- Header Title -->
    <div class="accountant-header-title">
        <i class="fas fa-calculator"></i>
        <div>
            <h3 class="h5 mb-0">Financial Dashboard</h3>
            <p class="small mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="accountant-stats-grid">
        <div class="accountant-stat-card">
            <div class="accountant-stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="accountant-stat-value">$<?php echo number_format($accountant_stats['today_sales'], 2); ?></div>
            <div class="accountant-stat-label">Today's Sales</div>
        </div>
        
        <div class="accountant-stat-card">
            <div class="accountant-stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="accountant-stat-value"><?php echo $accountant_stats['pending_expenses']; ?></div>
            <div class="accountant-stat-label">Pending Approvals</div>
            <div class="small text-muted">$<?php echo number_format($accountant_stats['pending_amount'], 2); ?></div>
        </div>
        
        <div class="accountant-stat-card">
            <div class="accountant-stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="accountant-stat-value">$<?php echo number_format($accountant_stats['month_sales'], 2); ?></div>
            <div class="accountant-stat-label">Month's Revenue</div>
        </div>
        
        <div class="accountant-stat-card">
            <div class="accountant-stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="accountant-stat-value">$<?php echo number_format($accountant_stats['month_expenses'], 2); ?></div>
            <div class="accountant-stat-label">Month's Expenses</div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="accountant-quick-actions">
        <a href="invoices.php" class="accountant-action-btn">
            <i class="fas fa-file-invoice"></i> Invoices
        </a>
        <a href="payments.php" class="accountant-action-btn">
            <i class="fas fa-money-check-alt"></i> Payments
        </a>
        <a href="expenses.php" class="accountant-action-btn">
            <i class="fas fa-receipt"></i> Expenses
        </a>
        <a href="reports.php?report_type=financial_summary" class="accountant-action-btn">
            <i class="fas fa-chart-pie"></i> Financial Reports
        </a>
        <a href="reports.php?type=tax" class="accountant-action-btn">
            <i class="fas fa-calculator"></i> Tax Reports
        </a>
        <a href="reports.php?type=balance" class="accountant-action-btn">
            <i class="fas fa-balance-scale"></i> Balance Sheet
        </a>
    </div>
</div>