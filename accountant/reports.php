<?php
require_once '../includes/header.php';
require_once '../includes/security.php';

// Report parameters
$report_types = [
    'financial_summary' => 'Financial Summary',
    'profit_loss' => 'Profit & Loss',
    'expense_analysis' => 'Expense Analysis',
    'sales_trends' => 'Sales Trends',
];
$report_type = is_string($_GET['report_type'] ?? null) ? $_GET['report_type'] : 'financial_summary';
$report_error = '';
if (!isset($report_types[$report_type])) {
    $report_type = 'financial_summary';
    $report_error = 'Unsupported report type. Showing the financial summary instead.';
}
$start_date = is_string($_GET['start_date'] ?? null) ? $_GET['start_date'] : date('Y-m-01');
$end_date = is_string($_GET['end_date'] ?? null) ? $_GET['end_date'] : date('Y-m-d');
if (!isValidDate($start_date) || !isValidDate($end_date) || $start_date > $end_date) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
    $report_error = 'The selected date range is invalid. Showing this month to date.';
}
$branch_id = $accountant_branch_id
    ?? (is_scalar($_GET['branch_id'] ?? null) ? max(0, intval($_GET['branch_id'])) : 0);

// Build WHERE clause
$where_clause = "WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$expense_where = "WHERE e.expense_date BETWEEN ? AND ? AND e.status = 'approved'";

if ($branch_id > 0) {
    $where_clause .= " AND s.branch_id = ?";
    $expense_where .= " AND e.branch_id = ?";
}

// Get financial summary
$sales_summary = "SELECT 
                  COALESCE(SUM(s.total_amount), 0) as total_sales,
                  COUNT(s.id) as transaction_count,
                  AVG(s.total_amount) as avg_transaction
                  FROM sales s $where_clause";
$sales_stmt = $conn->prepare($sales_summary);
if ($branch_id > 0) {
    $sales_stmt->bind_param("ssi", $start_date, $end_date, $branch_id);
} else {
    $sales_stmt->bind_param("ss", $start_date, $end_date);
}
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales = $sales_result->fetch_assoc();
$sales_stmt->close();

$expenses_summary = "SELECT 
                     COALESCE(SUM(e.amount), 0) as total_expenses,
                     COUNT(e.id) as expense_count
                     FROM expenses e $expense_where";
$expenses_stmt = $conn->prepare($expenses_summary);
if ($branch_id > 0) {
    $expenses_stmt->bind_param("ssi", $start_date, $end_date, $branch_id);
} else {
    $expenses_stmt->bind_param("ss", $start_date, $end_date);
}
$expenses_stmt->execute();
$expenses_result = $expenses_stmt->get_result();
$expenses = $expenses_result->fetch_assoc();
$expenses_stmt->close();

$payroll_where = 'WHERE DATE(p.payment_date) BETWEEN ? AND ?';
if ($branch_id > 0) {
    $payroll_where .= ' AND p.branch_id = ?';
}
$payroll_stmt = $conn->prepare(
    "SELECT COALESCE(SUM(p.amount), 0) AS payroll_total, COUNT(p.id) AS payroll_count
     FROM staff_payments p $payroll_where"
);
if ($branch_id > 0) {
    $payroll_stmt->bind_param('ssi', $start_date, $end_date, $branch_id);
} else {
    $payroll_stmt->bind_param('ss', $start_date, $end_date);
}
$payroll_stmt->execute();
$payroll = $payroll_stmt->get_result()->fetch_assoc();
$payroll_stmt->close();
$expenses['total_expenses'] += (float)$payroll['payroll_total'];
$expenses['expense_count'] += (int)$payroll['payroll_count'];

$net_income = $sales['total_sales'] - $expenses['total_expenses'];

// Get branches for filter
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active'"
    . ($accountant_branch_id !== null ? ' AND id = ' . (int)$accountant_branch_id : '');
$branches = $conn->query($branches_sql);

// Get monthly trends
$monthly_sql = "SELECT 
                DATE_FORMAT(sale_date, '%Y-%m') as month,
                SUM(total_amount) as monthly_sales,
                COUNT(id) as transactions
                FROM sales
                WHERE DATE(sale_date) BETWEEN ? AND ?";
$monthly_params = [$start_date, $end_date];
$monthly_types = 'ss';
if ($branch_id > 0) {
    $monthly_sql .= " AND branch_id = ?";
    $monthly_params[] = $branch_id;
    $monthly_types .= 'i';
}
$monthly_sql .= " GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
                ORDER BY month";
$monthly_stmt = $conn->prepare($monthly_sql);
if ($branch_id > 0) {
    $monthly_stmt->bind_param("ssi", $start_date, $end_date, $branch_id);
} else {
    $monthly_stmt->bind_param("ss", $start_date, $end_date);
}
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
$monthly_data = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
}
$monthly_stmt->close();

// Get expense breakdown
$expense_breakdown_sql = "SELECT 
                          e.expense_type,
                          SUM(e.amount) as total_amount,
                          COUNT(e.id) as count
                          FROM expenses e
                          $expense_where
                          GROUP BY e.expense_type
                          ORDER BY total_amount DESC";
$expense_breakdown_stmt = $conn->prepare($expense_breakdown_sql);
if ($branch_id > 0) {
    $expense_breakdown_stmt->bind_param("ssi", $start_date, $end_date, $branch_id);
} else {
    $expense_breakdown_stmt->bind_param("ss", $start_date, $end_date);
}
$expense_breakdown_stmt->execute();
$expense_breakdown = $expense_breakdown_stmt->get_result();
$expense_breakdown_rows = $expense_breakdown->fetch_all(MYSQLI_ASSOC);
$expense_breakdown_stmt->close();
if ((float)$payroll['payroll_total'] > 0) {
    $expense_breakdown_rows[] = [
        'expense_type' => 'Staff Payroll',
        'total_amount' => (float)$payroll['payroll_total'],
        'count' => (int)$payroll['payroll_count'],
    ];
}

$report_export_rows = [
    ['Metric', 'Value'],
    ['Report', $report_types[$report_type]],
    ['Start Date', $start_date],
    ['End Date', $end_date],
    ['Total Sales', number_format((float)$sales['total_sales'], 2, '.', '')],
    ['Sales Transactions', (int)$sales['transaction_count']],
    ['Average Sale', number_format((float)$sales['avg_transaction'], 2, '.', '')],
    ['Approved Expenses and Payroll', number_format((float)$expenses['total_expenses'], 2, '.', '')],
    ['Expense and Payroll Count', (int)$expenses['expense_count']],
    ['Net Income', number_format((float)$net_income, 2, '.', '')],
    [],
    ['Monthly Sales Trend'],
    ['Month', 'Sales', 'Transactions', 'Average Sale'],
];
foreach ($monthly_data as $month_row) {
    $report_export_rows[] = [
        $month_row['month'],
        number_format((float)$month_row['monthly_sales'], 2, '.', ''),
        (int)$month_row['transactions'],
        number_format((float)$month_row['monthly_sales'] / max((int)$month_row['transactions'], 1), 2, '.', ''),
    ];
}
$report_export_rows[] = [];
$report_export_rows[] = ['Expense Breakdown'];
$report_export_rows[] = ['Expense Type', 'Count', 'Total Amount'];
foreach ($expense_breakdown_rows as $expense_row) {
    $report_export_rows[] = [
        $expense_row['expense_type'],
        (int)$expense_row['count'],
        number_format((float)$expense_row['total_amount'], 2, '.', ''),
    ];
}
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars($report_types[$report_type], ENT_QUOTES, 'UTF-8'); ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($report_error !== ''): ?>
                    <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($report_error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <!-- Report Controls -->
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-control" id="report_type" name="report_type">
                            <?php foreach ($report_types as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $report_type === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="branch_id" class="form-label"><?php echo $accountant_branch_id !== null ? 'Assigned Branch' : 'Branch'; ?></label>
                        <select class="form-control" id="branch_id" <?php echo $accountant_branch_id !== null ? 'disabled' : 'name="branch_id"'; ?>>
                            <?php if ($accountant_branch_id === null): ?>
                                <option value="0">All Branches</option>
                            <?php endif; ?>
                            <?php while($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['id']; ?>" 
                                    <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($accountant_branch_id !== null): ?>
                            <input type="hidden" name="branch_id" value="<?php echo (int)$accountant_branch_id; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Generate
                        </button>
                        <button type="button" class="btn btn-success ms-2" onclick="exportReport()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </form>
                
                <!-- Financial Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Total Sales</h6>
                                <h2 class="card-title">$<?php echo number_format($sales['total_sales'], 2); ?></h2>
                                <p class="card-text">
                                    <?php echo number_format($sales['transaction_count']); ?> transactions<br>
                                    Avg: $<?php echo number_format($sales['avg_transaction'], 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Expenses &amp; Payroll</h6>
                                <h2 class="card-title">$<?php echo number_format($expenses['total_expenses'], 2); ?></h2>
                                <p class="card-text">
                                    <?php echo number_format($expenses['expense_count']); ?> items / payments<br>
                                    Avg: $<?php echo number_format($expenses['total_expenses'] / max($expenses['expense_count'], 1), 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Net Income</h6>
                                <h2 class="card-title">$<?php echo number_format($net_income, 2); ?></h2>
                                <p class="card-text">
                                    Profit Margin:<br>
                                    <?php echo $sales['total_sales'] > 0 ? number_format(($net_income / $sales['total_sales']) * 100, 2) : '0.00'; ?>%
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-light btn-sm" onclick="printReport()">
                                        <i class="fas fa-file-pdf"></i> Print / Save PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row mb-4">
                    <?php if (in_array($report_type, ['financial_summary', 'profit_loss'], true)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Income vs Expenses</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="incomeExpenseChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($report_type, ['financial_summary', 'expense_analysis'], true)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Expense Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="expenseChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detailed Tables -->
                <div class="row">
                    <?php if (in_array($report_type, ['financial_summary', 'sales_trends'], true)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Monthly Sales Trend</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Sales</th>
                                                <th>Transactions</th>
                                                <th>Avg. Sale</th>
                                                <th>Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$monthly_data): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No sales found for this period.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach($monthly_data as $month): ?>
                                            <tr>
                                                <td><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></td>
                                                <td>$<?php echo number_format($month['monthly_sales'], 2); ?></td>
                                                <td><?php echo number_format($month['transactions']); ?></td>
                                                <td>$<?php echo number_format($month['monthly_sales'] / max($month['transactions'], 1), 2); ?></td>
                                                <td>
                                                    <?php 
                                                    // Simple trend indicator
                                                    if ($month['monthly_sales'] > 0) {
                                                        echo '<i class="fas fa-arrow-up text-success"></i>';
                                                    } else {
                                                        echo '<i class="fas fa-minus text-muted"></i>';
                                                    }
                                                    ?>
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
                    <?php if (in_array($report_type, ['financial_summary', 'expense_analysis'], true)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Expense Analysis</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Expense Type</th>
                                                <th>Count</th>
                                                <th>Total Amount</th>
                                                <th>% of Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$expense_breakdown_rows): ?>
                                            <tr><td colspan="4" class="text-center text-muted">No approved expenses found for this period.</td></tr>
                                            <?php endif; ?>
                                            <?php 
                                            foreach ($expense_breakdown_rows as $expense):
                                                $percentage = $expenses['total_expenses'] > 0 ? 
                                                    ($expense['total_amount'] / $expenses['total_expenses'] * 100) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $expense['expense_type']; ?></td>
                                                <td><?php echo number_format($expense['count']); ?></td>
                                                <td>$<?php echo number_format($expense['total_amount'], 2); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%">
                                                            <?php echo number_format($percentage, 1); ?>%
                                                        </div>
                                                    </div>
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
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const accountantReportRows = <?php echo json_encode($report_export_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const expenseBreakdownRows = <?php echo json_encode($expense_breakdown_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

// Income vs Expenses Chart
const incomeExpenseCanvas = document.getElementById('incomeExpenseChart');
if (incomeExpenseCanvas) new Chart(incomeExpenseCanvas.getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Income', 'Expenses', 'Net'],
        datasets: [{
            label: 'Amount ($)',
            data: [
                <?php echo json_encode((float)$sales['total_sales']); ?>,
                <?php echo json_encode((float)$expenses['total_expenses']); ?>,
                <?php echo json_encode((float)$net_income); ?>
            ],
            backgroundColor: [
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(75, 192, 192, 0.7)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});

// Expense Breakdown Chart
const expenseCanvas = document.getElementById('expenseChart');
if (expenseCanvas) new Chart(expenseCanvas.getContext('2d'), {
    type: 'pie',
    data: {
        labels: expenseBreakdownRows.map(row => row.expense_type),
        datasets: [{
            data: expenseBreakdownRows.map(row => Number(row.total_amount)),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        var label = context.label || '';
                        var value = context.parsed || 0;
                        var total = context.dataset.data.reduce((a, b) => a + b, 0);
                        var percentage = Math.round((value / total) * 100);
                        return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

function exportReport() {
    const csv = accountantReportRows
        .map(row => row.map(value => '"' + String(value).replace(/"/g, '""') + '"').join(','))
        .join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = 'financial-report-<?php echo $start_date; ?>-to-<?php echo $end_date; ?>.csv';
    link.click();
    setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
}

function printReport() {
    window.print();
}

</script>
<?php
require_once '../includes/footer.php';
?>