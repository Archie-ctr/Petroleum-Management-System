<?php
require_once '../includes/manager_header.php';

$branch_id = $_SESSION['branch_id'];

// Report parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Build query based on report type
switch ($report_type) {
    case 'sales':
        $report_sql = "SELECT 
                      DATE(s.sale_date) as sale_day,
                      COUNT(s.id) as transactions,
                      SUM(s.quantity) as total_quantity,
                      SUM(s.total_amount) as total_amount
                      FROM sales s
                      WHERE s.branch_id = $branch_id
                      AND DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(s.sale_date)
                      ORDER BY sale_day DESC";
        $report_title = "Sales Report";
        break;
        
    case 'expenses':
        $report_sql = "SELECT 
                      e.expense_type,
                      COUNT(e.id) as count,
                      SUM(e.amount) as total_amount
                      FROM expenses e
                      WHERE e.branch_id = $branch_id
                      AND e.expense_date BETWEEN '$start_date' AND '$end_date'
                      AND e.status = 'approved'
                      GROUP BY e.expense_type
                      ORDER BY total_amount DESC";
        $report_title = "Expenses Report";
        break;
        
    case 'fuel':
        $report_sql = "SELECT 
                      f.fuel_name,
                      COUNT(s.id) as sales_count,
                      SUM(s.quantity) as total_quantity,
                      SUM(s.total_amount) as total_amount
                      FROM sales s
                      JOIN fuel_types f ON s.fuel_type_id = f.id
                      WHERE s.branch_id = $branch_id
                      AND DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY f.id
                      ORDER BY total_amount DESC";
        $report_title = "Fuel Sales Report";
        break;
}

$report_result = $conn->query($report_sql);

// Total calculations
$total_sql = "SELECT 
              COALESCE(SUM(total_amount), 0) as sales_total
              FROM sales 
              WHERE branch_id = $branch_id
              AND DATE(sale_date) BETWEEN '$start_date' AND '$end_date'";
$total_result = $conn->query($total_sql);
$total_sales = $total_result->fetch_assoc()['sales_total'];
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Branch Reports
                </h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-control" id="report_type" name="report_type" required>
                            <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="expenses" <?php echo $report_type == 'expenses' ? 'selected' : ''; ?>>Expenses Report</option>
                            <option value="fuel" <?php echo $report_type == 'fuel' ? 'selected' : ''; ?>>Fuel Sales Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Generate Report
                        </button>
                        <button type="button" class="btn btn-success ms-2" onclick="exportReport()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </form>
                
                <!-- Report Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><?php echo $report_title; ?></h5>
                                        <p class="text-muted mb-0">
                                            Period: <?php echo date('M d, Y', strtotime($start_date)); ?> 
                                            to <?php echo date('M d, Y', strtotime($end_date)); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <?php if ($report_type == 'sales'): ?>
                                            <h3 class="text-success">$<?php echo number_format($total_sales, 2); ?></h3>
                                            <p class="text-muted mb-0">Total Sales</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Data -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <?php if ($report_type == 'sales'): ?>
                                            <th>Date</th>
                                            <th>Transactions</th>
                                            <th>Quantity (L)</th>
                                            <th>Total Amount</th>
                                            <th>Daily Average</th>
                                        <?php elseif ($report_type == 'expenses'): ?>
                                            <th>Expense Type</th>
                                            <th>Count</th>
                                            <th>Total Amount</th>
                                            <th>Percentage</th>
                                        <?php elseif ($report_type == 'fuel'): ?>
                                            <th>Fuel Type</th>
                                            <th>Sales Count</th>
                                            <th>Total Quantity</th>
                                            <th>Total Amount</th>
                                            <th>Market Share</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_amount = 0;
                                    while($row = $report_result->fetch_assoc()): 
                                        $total_amount += $row['total_amount'];
                                    ?>
                                    <tr>
                                        <?php if ($report_type == 'sales'): ?>
                                            <td><?php echo date('M d, Y', strtotime($row['sale_day'])); ?></td>
                                            <td><?php echo number_format($row['transactions']); ?></td>
                                            <td><?php echo number_format($row['total_quantity'], 2); ?></td>
                                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td>$<?php echo number_format($row['total_amount'] / $row['transactions'], 2); ?></td>
                                        <?php elseif ($report_type == 'expenses'): ?>
                                            <td><?php echo $row['expense_type']; ?></td>
                                            <td><?php echo number_format($row['count']); ?></td>
                                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                    $percentage = $total_amount > 0 ? ($row['total_amount'] / $total_amount * 100) : 0;
                                                    ?>
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        <?php elseif ($report_type == 'fuel'): ?>
                                            <td><?php echo $row['fuel_name']; ?></td>
                                            <td><?php echo number_format($row['sales_count']); ?></td>
                                            <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $percentage = $total_sales > 0 ? ($row['total_amount'] / $total_sales * 100) : 0;
                                                ?>
                                                <span class="badge bg-info">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportReport() {
    // Convert table to Excel
    var table = document.getElementById("reportTable");
    var workbook = XLSX.utils.table_to_book(table, {sheet: "Report"});
    var filename = "<?php echo $report_title; ?>_<?php echo date('Ymd'); ?>.xlsx";
    XLSX.writeFile(workbook, filename);
}

// Initialize DataTable for reports
$(document).ready(function() {
    $('#reportTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});
</script>
<?php
require_once '../includes/footer.php';
?>