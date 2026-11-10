<?php
require_once '../includes/header.php';

// Get report parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// Build WHERE clause
$where_clause = "WHERE DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'";
if ($branch_id > 0) {
    $where_clause .= " AND s.branch_id = $branch_id";
}

// Sales Summary
$summary_sql = "SELECT 
                COUNT(s.id) as total_transactions,
                COALESCE(SUM(s.total_amount), 0) as total_sales,
                COALESCE(SUM(s.quantity), 0) as total_quantity,
                AVG(s.total_amount) as avg_transaction
                FROM sales s
                $where_clause";
$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();

// Sales by Fuel Type
$fuel_sales_sql = "SELECT 
                    f.fuel_name,
                    COUNT(s.id) as transactions,
                    SUM(s.quantity) as total_quantity,
                    SUM(s.total_amount) as total_amount
                    FROM sales s
                    JOIN fuel_types f ON s.fuel_type_id = f.id
                    $where_clause
                    GROUP BY f.id
                    ORDER BY total_amount DESC";
$fuel_sales = $conn->query($fuel_sales_sql);

// Get branches for filter
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active'";
$branches = $conn->query($branches_sql);
?>
<style>
    .sales-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .sales-summary-grid > .card {
        min-width: 0;
        margin: 0;
    }

    .sales-summary-grid .card-body {
        min-width: 0;
    }

    .sales-summary-grid .card-title {
        font-size: clamp(1.25rem, 2vw, 2rem);
        overflow-wrap: anywhere;
    }

    .sales-summary-grid .card-text {
        overflow-wrap: anywhere;
    }

    @media (max-width: 1199.98px) {
        .sales-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .sales-summary-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Sales Reports
                </h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="row g-3 mb-4">
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
                    <div class="col-md-3">
                        <label for="branch_id" class="form-label">Branch</label>
                        <select class="form-control" id="branch_id" name="branch_id">
                            <option value="0">All Branches</option>
                            <?php while($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['id']; ?>" 
                                    <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo $branch['branch_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="reports.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
                
                <!-- Summary Cards -->
                <div class="sales-summary-grid mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2">Total Sales</h6>
                            <h2 class="card-title">$<?php echo number_format($summary['total_sales'], 2); ?></h2>
                            <p class="card-text">Period: <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        </div>
                    </div>
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2">Total Transactions</h6>
                            <h2 class="card-title"><?php echo number_format($summary['total_transactions']); ?></h2>
                            <p class="card-text">Average: $<?php echo number_format($summary['avg_transaction'], 2); ?></p>
                        </div>
                    </div>
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2">Total Quantity</h6>
                            <h2 class="card-title"><?php echo number_format($summary['total_quantity'], 2); ?> L</h2>
                            <p class="card-text">Fuel sold</p>
                        </div>
                    </div>
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2">Export Report</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-light btn-sm">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn btn-light btn-sm">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sales by Fuel Type -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Sales by Fuel Type</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fuel Type</th>
                                        <th>Transactions</th>
                                        <th>Quantity (L)</th>
                                        <th>Total Amount</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_sales = $summary['total_sales'];
                                    while($fuel = $fuel_sales->fetch_assoc()):
                                        $percentage = $total_sales > 0 ? ($fuel['total_amount'] / $total_sales * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $fuel['fuel_name']; ?></td>
                                        <td><?php echo number_format($fuel['transactions']); ?></td>
                                        <td><?php echo number_format($fuel['total_quantity'], 2); ?> L</td>
                                        <td>$<?php echo number_format($fuel['total_amount'], 2); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $percentage; ?>%">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Chart Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Daily Sales Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Fuel Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sales Chart
var ctx = document.getElementById('salesChart').getContext('2d');
var salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Daily Sales ($)',
            data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Fuel Distribution Chart
var ctx2 = document.getElementById('fuelChart').getContext('2d');
var fuelChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Petrol Super', 'Diesel', 'Kerosene'],
        datasets: [{
            data: [45, 35, 20],
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true
    }
});
</script>
<?php
require_once '../includes/footer.php';
?>