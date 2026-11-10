<?php
// sales_history.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    header('Location: ../public/login.php');
    exit();
}

$attendant_id = (int)$_SESSION['user_id'];
$branch_id = (int)($_SESSION['branch_id'] ?? 0);

$valid_date = static function ($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
if (!$valid_date($start_date) || !$valid_date($end_date) || $start_date > $end_date) {
    http_response_code(400);
    exit('Invalid date range.');
}

$fuel_type = $_GET['fuel_type'] ?? 'all';
if ($fuel_type !== 'all' && (!is_string($fuel_type) || !ctype_digit($fuel_type) || (int)$fuel_type < 1)) {
    $fuel_type = 'all';
}
$fuel_type_id = $fuel_type === 'all' ? 0 : (int)$fuel_type;
$allowed_payment_methods = ['cash', 'card', 'mobile_money', 'credit'];
$payment_method = $_GET['payment_method'] ?? 'all';
if (!in_array($payment_method, $allowed_payment_methods, true)) {
    $payment_method = 'all';
}

$sales_sql = "SELECT s.*, f.fuel_name, b.branch_name
              FROM sales s
              JOIN fuel_types f ON s.fuel_type_id = f.id
              JOIN branches b ON s.branch_id = b.id
              WHERE s.attendant_id = ? AND s.branch_id = ?
                AND s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
                AND (? = 0 OR s.fuel_type_id = ?)
                AND (? = 'all' OR s.payment_method = ?)
              ORDER BY s.sale_date DESC";
$sales_stmt = $conn->prepare($sales_sql);
if (!$sales_stmt) {
    throw new RuntimeException('Unable to prepare sales history: ' . $conn->error);
}
$sales_stmt->bind_param(
    'iissiiss',
    $attendant_id,
    $branch_id,
    $start_date,
    $end_date,
    $fuel_type_id,
    $fuel_type_id,
    $payment_method,
    $payment_method
);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();

$summary_sql = "SELECT currency, COUNT(*) AS total_sales,
                       COALESCE(SUM(total_amount), 0) AS total_amount,
                       COALESCE(SUM(quantity), 0) AS total_quantity
                FROM sales s
                WHERE s.attendant_id = ? AND s.branch_id = ?
                  AND s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
                  AND (? = 0 OR s.fuel_type_id = ?)
                  AND (? = 'all' OR s.payment_method = ?)
                GROUP BY currency";
$summary_stmt = $conn->prepare($summary_sql);
if (!$summary_stmt) {
    throw new RuntimeException('Unable to prepare sales summary: ' . $conn->error);
}
$summary_stmt->bind_param(
    'iissiiss',
    $attendant_id,
    $branch_id,
    $start_date,
    $end_date,
    $fuel_type_id,
    $fuel_type_id,
    $payment_method,
    $payment_method
);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = ['total_sales' => 0, 'total_quantity' => 0];
$currency_totals = [];
while ($row = $summary_result->fetch_assoc()) {
    $summary['total_sales'] += (int)$row['total_sales'];
    $summary['total_quantity'] += (float)$row['total_quantity'];
    $currency_totals[$row['currency']] = (float)$row['total_amount'];
}

// Get fuel types for filter
$fuel_types_sql = "SELECT * FROM fuel_types WHERE status = 'active'";
$fuel_types = $conn->query($fuel_types_sql);

// Include custom header
require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header sales-history-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i> Sales History
                </h5>
                <button class="btn btn-primary btn-sm no-print" onclick="printReport()">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 sales-history-filters">
                            <div class="col-6 col-lg-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label">Fuel Type</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="all">All Fuel Types</option>
                                    <?php while($fuel = $fuel_types->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$fuel['id']; ?>" <?php echo $fuel_type_id === (int)$fuel['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fuel['fuel_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select" name="payment_method">
                                    <option value="all" <?php echo $payment_method == 'all' ? 'selected' : ''; ?>>All Methods</option>
                                    <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="mobile_money" <?php echo $payment_method === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                    <option value="credit" <?php echo $payment_method === 'credit' ? 'selected' : ''; ?>>Credit</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Apply Filters
                                    </button>
                                    <a href="sales_history.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-1"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card bg-primary text-white sales-history-summary-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-1">Total Sales</h6>
                                        <h3 class="card-title mb-0"><?php echo $summary['total_sales']; ?></h3>
                                    </div>
                                    <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card bg-success text-white sales-history-summary-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-1">Total Amount</h6>
                                        <h3 class="card-title mb-0">
                                            <?php if ($currency_totals): ?>
                                                <?php foreach ($currency_totals as $currency => $amount): ?>
                                                    <span class="d-block"><?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($amount, 2); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                USD 0.00
                                            <?php endif; ?>
                                        </h3>
                                    </div>
                                    <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card bg-info text-white sales-history-summary-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-1">Fuel Sold</h6>
                                        <h3 class="card-title mb-0"><?php echo number_format($summary['total_quantity'], 2); ?> L</h3>
                                    </div>
                                    <i class="fas fa-gas-pump fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date & Time</th>
                                        <th>Sale No.</th>
                                        <th>Fuel Type</th>
                                        <th>Quantity</th>
                                        <th>Price/L</th>
                                        <th>Currency</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sales_result->num_rows > 0): ?>
                                        <?php $counter = 1; ?>
                                        <?php while($sale = $sales_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                                <td><strong><?php echo $sale['sale_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($sale['fuel_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo number_format($sale['quantity'], 2); ?> L</td>
                                                <td><?php echo ($sale['currency'] ?? 'USD') === 'LRD' ? 'L$' : '$'; ?><?php echo number_format($sale['price_per_liter'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($sale['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><strong><?php echo ($sale['currency'] ?? 'USD') === 'LRD' ? 'L$' : '$'; ?><?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $sale['payment_method'] == 'cash' ? 'success' :
                                                             ($sale['payment_method'] == 'card' ? 'info' :
                                                             ($sale['payment_method'] == 'mobile_money' ? 'primary' : 'warning'));
                                                    ?>">
                                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $sale['payment_method'])), ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="receipt.php?sale_id=<?php echo (int)$sale['id']; ?>"
                                                           class="btn btn-outline-primary" title="View Receipt" aria-label="View receipt">
                                                            <i class="fas fa-receipt"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="viewSaleDetails(<?php echo (int)$sale['id']; ?>)" title="View Details" aria-label="View sale details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-history fa-3x mb-3"></i>
                                                    <h5>No sales found for the selected period</h5>
                                                    <p>Try adjusting your filters or start making sales!</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card mt-4 no-print">
                    <div class="card-body text-center">
                        <h6 class="card-title mb-3">Export Sales Data</h6>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button class="btn btn-outline-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i> Export to Excel
                            </button>
                            <button class="btn btn-outline-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> Export to PDF
                            </button>
                            <button class="btn btn-outline-dark" onclick="exportToCSV()">
                                <i class="fas fa-file-csv me-1"></i> Export to CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .sales-history-toolbar h5 {
        min-width: 0;
    }

    .sales-history-filters .form-label {
        font-weight: 600;
    }

    .sales-history-filters .form-control,
    .sales-history-filters .form-select {
        min-width: 0;
    }

    .sales-history-summary-card {
        height: 100%;
    }

    .sales-history-summary-card h3 {
        overflow-wrap: anywhere;
    }

    #salesTable {
        width: 100% !important;
    }

    #salesTable td,
    #salesTable th {
        vertical-align: middle;
        white-space: nowrap;
    }

    @media (max-width: 767px) {
        #mainContentWrapper {
            width: 100%;
            margin-left: 0 !important;
            transition: none !important;
        }

        #mainContentWrapper > .container-fluid {
            padding-right: 1rem !important;
            padding-left: 1rem !important;
        }

        .sales-history-toolbar {
            align-items: flex-start !important;
        }

        .sales-history-filters .col-6 {
            min-width: 0;
        }

        .sales-history-filters .form-control,
        .sales-history-filters .form-select {
            font-size: 0.9rem;
        }

        .sales-history-summary-card .card-body {
            padding: 1rem;
        }

        .sales-history-summary-card .card-title {
            font-size: clamp(1.05rem, 5vw, 1.35rem);
        }

        #salesTable_wrapper {
            min-width: 0;
        }

        #salesTable_wrapper .dataTables_filter input {
            max-width: 58vw;
        }
    }

    @media (max-width: 575px) {
        .sales-history-filters > .col-6 {
            flex: 0 0 100%;
            width: 100%;
            max-width: 100%;
        }

        .sales-history-filters .form-label {
            margin-bottom: 0.35rem;
        }

        .sales-history-filters .form-control,
        .sales-history-filters .form-select {
            min-height: 44px;
            font-size: 1rem;
        }

        .sales-history-filters > .col-12 > .d-flex {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr);
        }

        .sales-history-filters > .col-12 .btn {
            min-height: 44px;
            white-space: nowrap;
        }

        .sales-history-toolbar {
            padding: 1rem;
        }

        .sales-history-toolbar .btn {
            min-height: 42px;
        }

        #salesTable_wrapper .dataTables_length,
        #salesTable_wrapper .dataTables_filter {
            width: 100%;
            margin: 0 0 0.75rem;
            text-align: left;
        }

        #salesTable_wrapper .dataTables_length label,
        #salesTable_wrapper .dataTables_filter label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
        }

        #salesTable_wrapper .dataTables_filter input {
            flex: 1 1 auto;
            width: auto;
            max-width: none;
            margin-left: 0 !important;
        }

        #salesTable_wrapper .dataTables_length select {
            min-height: 40px;
            margin: 0 0.35rem !important;
        }

        .sales-history-summary-card .card-body {
            min-height: 88px;
        }
    }

    @media print {
        #attendantSidebar,
        .top-navbar,
        .no-print,
        .sales-history-filters,
        #salesTable_wrapper .dataTables_filter,
        #salesTable_wrapper .dataTables_length,
        #salesTable_wrapper .dataTables_info,
        #salesTable_wrapper .dataTables_paginate {
            display: none !important;
        }

        #mainContentWrapper {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body,
        #mainContentWrapper {
            background: #fff !important;
        }

        .card {
            break-inside: avoid;
            box-shadow: none !important;
        }

        #salesTable td,
        #salesTable th {
            white-space: normal;
        }
    }
</style>

<script>
function printReport() {
    window.print();
}

function viewSaleDetails(saleId) {
    $.ajax({
        url: 'ajax/get_receipt.php',
        method: 'GET',
        data: { sale_id: saleId },
        success: function(response) {
            $('#saleDetailsContent').html(response);
            $('#saleDetailsModal').data('sale-id', saleId);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('saleDetailsModal')).show();
        },
        error: function() {
            alert('Failed to load sale details. Please try again.');
        }
    });
}

function printReceipt() {
    const saleId = $('#saleDetailsModal').data('sale-id');
    window.open('receipt.php?sale_id=' + saleId + '&print=true', '_blank');
}

function exportToExcel() {
    exportToCSV();
}

function exportToPDF() {
    window.print();
}

function exportToCSV() {
    const table = $('#salesTable');
    const rows = $.fn.DataTable.isDataTable(table)
        ? table.DataTable().rows({ search: 'applied' }).nodes().toArray()
        : [...table.find('tbody tr')];
    const header = [...table.find('thead th')].slice(0, -1).map(cell => cell.innerText.trim());
    const records = rows.map(row => [...row.cells].slice(0, -1).map(cell => cell.innerText.trim()));
    const csvRows = [header, ...records].map(row =>
        row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')
    );
    const csv = '\ufeff' + csvRows.join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `sales-history-<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>-to-<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    // In production: window.location.href = 'export.php?type=csv&start_date=...';
}

</script>

<?php require_once 'attendant_footer.php'; ?>