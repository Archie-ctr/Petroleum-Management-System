<?php
// daily_report.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    header('Location: ../public/login.php');
    exit();
}

$attendant_id = (int)$_SESSION['user_id'];
$report_date = $_GET['date'] ?? date('Y-m-d');
if (!is_string($report_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
    http_response_code(400);
    exit('Invalid report date.');
}
$parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $report_date);
if (!$parsed_date || $parsed_date->format('Y-m-d') !== $report_date) {
    http_response_code(400);
    exit('Invalid report date.');
}

$summary_stmt = $conn->prepare(
    "SELECT currency, COUNT(*) AS total_sales, COALESCE(SUM(quantity), 0) AS total_quantity,
            MIN(sale_date) AS first_sale, MAX(sale_date) AS last_sale
     FROM sales
     WHERE attendant_id = ? AND sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND status = 'completed'
     GROUP BY currency"
);
$summary_stmt->bind_param('iss', $attendant_id, $report_date, $report_date);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = ['total_sales' => 0, 'total_quantity' => 0, 'first_sale' => null, 'last_sale' => null];
while ($row = $summary_result->fetch_assoc()) {
    $summary['total_sales'] += (int)$row['total_sales'];
    $summary['total_quantity'] += (float)$row['total_quantity'];
    if ($row['first_sale'] !== null && ($summary['first_sale'] === null || $row['first_sale'] < $summary['first_sale'])) {
        $summary['first_sale'] = $row['first_sale'];
    }
    if ($row['last_sale'] !== null && ($summary['last_sale'] === null || $row['last_sale'] > $summary['last_sale'])) {
        $summary['last_sale'] = $row['last_sale'];
    }
}

$amount_stmt = $conn->prepare(
    "SELECT currency, COALESCE(SUM(total_amount), 0) AS total_amount
     FROM sales
     WHERE attendant_id = ? AND sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND status = 'completed'
     GROUP BY currency"
);
$amount_stmt->bind_param('iss', $attendant_id, $report_date, $report_date);
$amount_stmt->execute();
$amount_result = $amount_stmt->get_result();
$currency_amounts = [];
while ($row = $amount_result->fetch_assoc()) {
    $currency_amounts[$row['currency']] = (float)$row['total_amount'];
}

$fuel_stmt = $conn->prepare(
    "SELECT f.fuel_name, s.currency, COUNT(s.id) AS sales_count,
            SUM(s.quantity) AS total_quantity, SUM(s.total_amount) AS total_amount
     FROM sales s
     JOIN fuel_types f ON s.fuel_type_id = f.id
     WHERE s.attendant_id = ? AND s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND s.status = 'completed'
     GROUP BY f.id, f.fuel_name, s.currency
     ORDER BY total_amount DESC"
);
$fuel_stmt->bind_param('iss', $attendant_id, $report_date, $report_date);
$fuel_stmt->execute();
$fuel_summary = $fuel_stmt->get_result();

$payment_stmt = $conn->prepare(
    "SELECT payment_method, currency, COUNT(*) AS count, SUM(total_amount) AS amount
     FROM sales
     WHERE attendant_id = ? AND sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND status = 'completed'
     GROUP BY payment_method, currency
     ORDER BY amount DESC"
);
$payment_stmt->bind_param('iss', $attendant_id, $report_date, $report_date);
$payment_stmt->execute();
$payment_summary = $payment_stmt->get_result();

// Include custom header
require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header report-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i> Daily Sales Report
                </h5>
                <div class="report-date-controls no-print d-flex flex-wrap gap-2">
                    <input type="date" class="form-control form-control-sm" 
                           id="reportDate" value="<?php echo htmlspecialchars($report_date, ENT_QUOTES, 'UTF-8'); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                    <button class="btn btn-primary btn-sm" onclick="loadReport()">
                        <i class="fas fa-search me-1"></i> Load
                    </button>
                    <button class="btn btn-success btn-sm no-print" onclick="printReport()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Date and Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-light report-summary-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 col-lg-3 text-center report-stat">
                                        <h6 class="text-muted mb-1">Report Date</h6>
                                        <h4 class="text-primary"><?php echo $parsed_date->format('F d, Y'); ?></h4>
                                    </div>
                                    <div class="col-6 col-lg-3 text-center report-stat">
                                        <h6 class="text-muted mb-1">Total Sales</h6>
                                        <h4 class="text-success"><?php echo $summary['total_sales']; ?></h4>
                                    </div>
                                    <div class="col-6 col-lg-3 text-center report-stat">
                                        <h6 class="text-muted mb-1">Total Amount</h6>
                                        <div class="report-currency-totals">
                                            <?php foreach ($currency_amounts as $currency => $amount): ?>
                                                <div class="text-info fw-bold"><?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($amount, 2); ?></div>
                                            <?php endforeach; ?>
                                            <?php if (!$currency_amounts): ?><div class="text-info fw-bold">$0.00</div><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3 text-center report-stat">
                                        <h6 class="text-muted mb-1">Fuel Sold</h6>
                                        <h4 class="text-warning"><?php echo number_format($summary['total_quantity'], 2); ?> L</h4>
                                    </div>
                                </div>
                                <?php if ($summary['first_sale']): ?>
                                    <div class="row mt-3">
                                        <div class="col-6 text-center">
                                            <small class="text-muted">First Sale:</small>
                                            <div><?php echo date('H:i', strtotime($summary['first_sale'])); ?></div>
                                        </div>
                                        <div class="col-6 text-center">
                                            <small class="text-muted">Last Sale:</small>
                                            <div><?php echo date('H:i', strtotime($summary['last_sale'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Reports -->
                <div class="row">
                    <!-- Fuel Type Breakdown -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Fuel Type Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($fuel_summary->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fuel Type</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-end">Quantity</th>
                                                    <th>Currency</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($fuel = $fuel_summary->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($fuel['fuel_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="text-end"><?php echo $fuel['sales_count']; ?></td>
                                                        <td class="text-end"><?php echo number_format($fuel['total_quantity'], 2); ?> L</td>
                                                        <td><?php echo htmlspecialchars($fuel['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="text-end"><?php echo number_format($fuel['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-gas-pump fa-2x mb-2"></i>
                                        <p>No sales recorded for this date</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Breakdown -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Payment Method Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($payment_summary->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Method</th>
                                                    <th class="text-end">Count</th>
                                                    <th>Currency</th>
                                                    <th class="text-end">Amount</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($payment = $payment_summary->fetch_assoc()): ?>
                                                    <?php 
                                                    $currencyTotal = $currency_amounts[$payment['currency']] ?? 0;
                                                    $percentage = $currencyTotal > 0
                                                        ? ($payment['amount'] / $currencyTotal * 100)
                                                        : 0;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-<?php
                                                                echo $payment['payment_method'] == 'cash' ? 'success' :
                                                                     ($payment['payment_method'] == 'card' ? 'info' :
                                                                     ($payment['payment_method'] == 'mobile_money' ? 'primary' : 'warning'));
                                                            ?>">
                                                                <?php echo ucfirst($payment['payment_method']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end"><?php echo $payment['count']; ?></td>
                                                        <td><?php echo htmlspecialchars($payment['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="text-end"><?php echo number_format($payment['amount'], 2); ?></td>
                                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-credit-card fa-2x mb-2"></i>
                                        <p>No payment data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hourly Sales Chart -->
                <div class="card mt-4 hourly-report-card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Hourly Sales Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="hourly-chart-wrap">
                            <canvas id="hourlyChart" role="img" aria-label="Completed sales count by hour"></canvas>
                        </div>
                        <p class="small text-muted mb-0 mt-2">Bars show the number of completed sales in each hour.</p>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card mt-4 no-print">
                    <div class="card-body text-center">
                        <h6 class="card-title mb-3">Export Options</h6>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button class="btn btn-outline-success" onclick="exportToExcel()">
                                <i class="fas fa-file-csv me-1"></i> Download CSV
                            </button>
                            <button class="btn btn-outline-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> Save as PDF
                            </button>
                            <button class="btn btn-outline-primary" onclick="emailReport()">
                                <i class="fas fa-envelope me-1"></i> Email Summary
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .report-toolbar h5 {
        min-width: 0;
    }

    .report-date-controls {
        align-items: center;
    }

    .report-date-controls input[type="date"] {
        width: min(100%, 190px);
    }

    .report-summary-card {
        height: 100%;
        border: 1px solid #e3e9e5;
        border-radius: 0.9rem;
    }

    .report-stat {
        min-width: 0;
        padding: 0.5rem;
    }

    .report-stat h4 {
        overflow-wrap: anywhere;
    }

    .report-currency-totals {
        min-height: 1.8rem;
        overflow-wrap: anywhere;
    }

    .hourly-chart-wrap {
        position: relative;
        width: 100%;
        height: clamp(220px, 35vw, 360px);
    }

    #hourlyChart {
        display: block;
        width: 100%;
        height: 100%;
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

        .report-toolbar {
            align-items: flex-start !important;
        }

        .report-date-controls {
            width: 100%;
        }

        .report-date-controls input[type="date"] {
            flex: 1 1 150px;
            max-width: none !important;
        }

        .report-date-controls .btn {
            flex: 1 1 auto;
        }

        .report-stat h4 {
            font-size: clamp(1rem, 5vw, 1.35rem);
        }

        .table-responsive {
            margin-right: -0.5rem;
            margin-left: -0.5rem;
        }
    }

    @media print {
        #attendantSidebar, .top-navbar, .no-print, .dataTables_filter,
        .dataTables_length, .dataTables_info, .dataTables_paginate {
            display: none !important;
        }

        #mainContentWrapper {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body, #mainContentWrapper {
            background: #fff !important;
        }

        .card {
            break-inside: avoid;
            box-shadow: none !important;
        }
    }
</style>

<script>
const reportDate = <?php echo json_encode($parsed_date->format('Y-m-d')); ?>;
const reportDateLabel = <?php echo json_encode($parsed_date->format('F d, Y')); ?>;
const reportAttendant = <?php echo json_encode($_SESSION['full_name'] ?? 'Attendant', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function loadReport() {
    const date = document.getElementById('reportDate').value;
    if (!date) {
        alert('Choose a report date first.');
        return;
    }
    window.location.href = 'daily_report.php?date=' + date;
}

function printReport() {
    window.print();
}

function exportToExcel() {
    const rows = [
        ['Daily Sales Report', reportDate],
        ['Attendant', reportAttendant],
        ['Total completed sales', '<?php echo (int)$summary['total_sales']; ?>'],
        ['Fuel sold (L)', '<?php echo number_format((float)$summary['total_quantity'], 2, '.', ''); ?>'],
        ['Currency', 'Sales amount']
        , ...Object.entries(<?php echo json_encode($currency_amounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)
    ];
    const csv = rows.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `daily-report-${reportDate}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function exportToPDF() {
    window.print();
}

function emailReport() {
    const totals = <?php echo json_encode($currency_amounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const amountText = Object.keys(totals).length
        ? Object.entries(totals).map(([currency, amount]) => `${currency} ${Number(amount).toFixed(2)}`).join(', ')
        : 'No completed sales';
    const subject = encodeURIComponent(`Daily Sales Report - ${reportDate}`);
    const body = encodeURIComponent(
        `Daily Sales Report\nDate: ${reportDateLabel}\n` +
        `Attendant: ${reportAttendant}\n` +
        `Completed sales: <?php echo (int)$summary['total_sales']; ?>\n` +
        `Fuel sold: <?php echo number_format((float)$summary['total_quantity'], 2); ?> L\n` +
        `Amounts: ${amountText}\n\nDownload the CSV or save this page as PDF to attach the detailed report.`
    );
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
}

// Load hourly chart
document.addEventListener('DOMContentLoaded', function() {
    $.ajax({
        url: 'ajax/get_hourly_sales.php',
        method: 'GET',
        data: { date: <?php echo json_encode($report_date); ?> },
        success: function(data) {
            if (data.success) {
                renderHourlyChart(data.hourlyData);
            } else {
                showChartMessage('Hourly sales data is unavailable.');
            }
        },
        error: function() {
            showChartMessage('Hourly sales data could not be loaded.');
        }
    });
});

function renderHourlyChart(hourlyData) {
    window.hourlySalesData = hourlyData;
    const ctx = document.getElementById('hourlyChart').getContext('2d');
    const canvas = ctx.canvas;
    const ratio = window.devicePixelRatio || 1;
    const width = canvas.clientWidth;
    const height = canvas.clientHeight;
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    ctx.scale(ratio, ratio);
    ctx.clearRect(0, 0, width, height);

    const values = hourlyData.map(item => Number(item.count) || 0);
    const maxValue = Math.max(1, ...values);
    const left = 36;
    const right = 10;
    const top = 12;
    const bottom = 34;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const step = plotWidth / 24;
    const barWidth = Math.max(3, step * 0.58);

    ctx.font = '12px Arial';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    ctx.strokeStyle = '#e3e9e5';
    ctx.fillStyle = '#68776e';
    for (let tick = 0; tick <= 3; tick++) {
        const value = Math.ceil(maxValue * tick / 3);
        const y = top + plotHeight - plotHeight * tick / 3;
        ctx.beginPath();
        ctx.moveTo(left, y);
        ctx.lineTo(width - right, y);
        ctx.stroke();
        ctx.fillText(String(value), left - 8, y);
    }

    values.forEach((value, hour) => {
        const barHeight = plotHeight * value / maxValue;
        const x = left + hour * step + (step - barWidth) / 2;
        const y = top + plotHeight - barHeight;
        ctx.fillStyle = value > 0 ? '#198754' : '#cbd5ce';
        ctx.fillRect(x, y, barWidth, Math.max(value > 0 ? 2 : 1, barHeight));
        if (hour % 2 === 0) {
            ctx.fillStyle = '#68776e';
            ctx.textAlign = 'center';
            ctx.fillText(String(hour).padStart(2, '0'), left + hour * step + step / 2, height - 14);
        }
    });
}

function showChartMessage(message) {
    const canvas = document.getElementById('hourlyChart');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const label = document.createElement('p');
    label.className = 'text-muted small text-center mt-3';
    label.textContent = message;
    canvas.replaceWith(label);
}

window.addEventListener('resize', () => {
    if (window.hourlySalesData) renderHourlyChart(window.hourlySalesData);
});
</script>

<?php require_once 'attendant_footer.php'; ?>