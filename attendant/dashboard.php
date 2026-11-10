<?php
// dashboard.php
// Remove the old header include since we'll use our custom header
// require_once '../includes/header.php';

// Instead, include our custom attendant header
require_once 'attendant_header.php';

// Then include the sidebar
require_once 'attendant_sidebar.php';

$branch_id = $_SESSION['branch_id'];
$attendant_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get attendant's today statistics
$stats_sql = "SELECT 
              COUNT(*) as sales_count,
              COALESCE(SUM(total_amount), 0) as sales_total,
              COALESCE(SUM(quantity), 0) as fuel_sold
              FROM sales 
              WHERE attendant_id = $attendant_id 
              AND DATE(sale_date) = '$today'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<style>
    .attendant-dashboard {
        width: 100%;
        max-width: 1440px;
        margin: 0 auto;
        min-width: 0;
    }

    .attendant-dashboard-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .attendant-dashboard-header h1 {
        margin: 0;
        color: #1f2937;
        font-size: clamp(1.25rem, 3vw, 1.65rem);
        font-weight: 700;
    }

    .attendant-dashboard-date {
        color: #64748b;
        font-size: 0.9rem;
    }

    .attendant-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .attendant-stat-card {
        height: 100%;
        min-width: 0;
        margin: 0;
        border: 0;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.09);
    }

    .attendant-stat-card .card-body {
        display: flex;
        min-width: 0;
        min-height: 126px;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 7px;
        padding: 18px 12px;
        text-align: center;
    }

    .attendant-stat-card h2 {
        max-width: 100%;
        margin: 0;
        font-size: clamp(1.45rem, 4vw, 2.35rem);
        font-weight: 700;
        line-height: 1.15;
        overflow-wrap: anywhere;
    }

    .attendant-stat-card h3 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .attendant-dashboard-panel {
        min-width: 0;
        margin-bottom: 20px;
        border: 1px solid #e8ecef;
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
    }

    .attendant-dashboard-panel > .card-body {
        min-width: 0;
        padding: clamp(14px, 3vw, 22px);
    }

    .attendant-dashboard-panel .card-title {
        color: #25332b;
        font-size: 1.05rem;
        font-weight: 650;
    }

    .attendant-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .attendant-action {
        display: flex;
        min-width: 0;
        min-height: 94px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 8px;
        border-radius: 10px;
        font-size: 0.88rem;
        font-weight: 600;
        line-height: 1.3;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .attendant-action i {
        font-size: 1.35rem;
    }

    .attendant-sales-table {
        min-width: 0;
    }

    .attendant-sales-table .table-responsive {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .attendant-sales-table .dataTables_wrapper {
        max-width: 100%;
        min-width: 0;
    }

    .attendant-sales-table .dataTables_wrapper .row {
        margin-right: 0;
        margin-left: 0;
    }

    .attendant-sales-table .dataTables_wrapper .dataTables_length,
    .attendant-sales-table .dataTables_wrapper .dataTables_filter {
        max-width: 100%;
    }

    .attendant-sales-table .dataTables_filter input {
        max-width: min(100%, 180px);
    }

    @media (max-width: 767.98px) {
        .attendant-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }

        .attendant-stat-card .card-body {
            min-height: 104px;
            padding: 14px 8px;
        }

        .attendant-stat-card h3 {
            font-size: 0.78rem;
        }

        .attendant-actions-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .attendant-action {
            min-height: 82px;
            padding: 10px 6px;
            font-size: 0.82rem;
        }
    }

    @media (max-width: 359.98px) {
        .attendant-stats-grid {
            gap: 8px;
        }

        .attendant-stat-card .card-body {
            min-height: 96px;
            padding: 10px 5px;
        }

        .attendant-stat-card h2 {
            font-size: 1.35rem;
        }

        .attendant-dashboard-panel > .card-body {
            padding: 12px 10px;
        }
    }
</style>

<main class="attendant-dashboard">
    <header class="attendant-dashboard-header">
        <h1><i class="fas fa-gauge-high me-2 text-success"></i>Attendant Dashboard</h1>
        <div class="attendant-dashboard-date"><?php echo date('F d, Y'); ?></div>
    </header>

    <section class="attendant-stats-grid" aria-label="Today's sales summary">
        <article class="card bg-primary text-white attendant-stat-card">
            <div class="card-body">
                <h2><?php echo number_format((int)$stats['sales_count']); ?></h2>
                <h3>Sales Today</h3>
            </div>
        </article>
        <article class="card bg-success text-white attendant-stat-card">
            <div class="card-body">
                <h2>$<?php echo number_format((float)$stats['sales_total'], 2); ?></h2>
                <h3>Total Sales Amount</h3>
            </div>
        </article>
        <article class="card bg-info text-white attendant-stat-card">
            <div class="card-body">
                <h2><?php echo number_format((float)$stats['fuel_sold'], 2); ?> L</h2>
                <h3>Fuel Sold Today</h3>
            </div>
        </article>
    </section>

    <section class="card attendant-dashboard-panel">
        <div class="card-body">
            <h2 class="card-title mb-3">Quick Actions</h2>
            <div class="attendant-actions-grid">
                <a href="sales.php" class="btn btn-primary attendant-action">
                    <i class="fas fa-gas-pump"></i><span>New Fuel Sale</span>
                </a>
                <a href="receipt.php" class="btn btn-success attendant-action">
                    <i class="fas fa-receipt"></i><span>Print Receipt</span>
                </a>
                <a href="sales_history.php" class="btn btn-info attendant-action">
                    <i class="fas fa-history"></i><span>View Sales History</span>
                </a>
                <a href="calculator.php" class="btn btn-warning attendant-action">
                    <i class="fas fa-calculator"></i><span>Fuel Calculator</span>
                </a>
            </div>
        </div>
    </section>

    <section class="card attendant-dashboard-panel attendant-sales-table">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <h2 class="card-title mb-0">Your Recent Sales</h2>
        </div>
        <div class="card-body">
            <?php
            $recent_sales_sql = "SELECT s.*, f.fuel_name
                                FROM sales s
                                JOIN fuel_types f ON s.fuel_type_id = f.id
                                WHERE s.attendant_id = $attendant_id
                                ORDER BY s.sale_date DESC LIMIT 10";
            $recent_sales = $conn->query($recent_sales_sql);

            if ($recent_sales->num_rows > 0):
            ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Sale No.</th>
                                <th>Time</th>
                                <th>Fuel Type</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['sale_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo date('H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['fuel_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format((float)$sale['quantity'], 2); ?> L</td>
                                    <td>$<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $sale['payment_method'] === 'cash' ? 'success' : 'info'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($sale['payment_method']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-1"></i> No sales recorded yet.
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
// Now we need to close the HTML tags that were opened in attendant_header.php
// We'll use a custom footer that closes properly
require_once 'attendant_footer.php';
?>