<?php
// fuel_prices.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    header('Location: ../public/login.php');
    exit();
}

$branch_id = (int)($_SESSION['branch_id'] ?? 0);

// Get branch name
$branch_name = 'Your Branch';
$branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
if ($branch_query) {
    $branch_query->bind_param("i", $branch_id);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    if ($branch_result->num_rows > 0) {
        $branch = $branch_result->fetch_assoc();
        $branch_name = $branch['branch_name'];
    }
    $branch_query->close();
}

// Branch-specific price records take precedence over the fuel type's standard USD price.
$branch_prices = [];
$latest_price_update = null;
$branch_query = $conn->prepare(
    'SELECT fuel_type, price_usd, price_lrd, updated_at FROM fuel_prices WHERE branch_id = ?'
);
if (!$branch_query) {
    throw new RuntimeException('Unable to prepare branch fuel price lookup: ' . $conn->error);
}
$branch_query->bind_param('i', $branch_id);
if (!$branch_query->execute()) {
    throw new RuntimeException('Unable to load branch fuel prices: ' . $branch_query->error);
}
$branch_result = $branch_query->get_result();
while ($row = $branch_result->fetch_assoc()) {
    $branch_prices[mb_strtolower(trim($row['fuel_type']))] = $row;
    if (!empty($row['updated_at']) && ($latest_price_update === null || $row['updated_at'] > $latest_price_update)) {
        $latest_price_update = $row['updated_at'];
    }
}
$branch_query->close();

$fuel_types_result = $conn->query(
    "SELECT id, fuel_name, fuel_code, price_per_liter, status
     FROM fuel_types WHERE status = 'active' ORDER BY fuel_name"
);
if (!$fuel_types_result) {
    throw new RuntimeException('Unable to load fuel types: ' . $conn->error);
}

$all_fuels = [];
while ($fuel = $fuel_types_result->fetch_assoc()) {
    $branch_price = $branch_prices[mb_strtolower(trim($fuel['fuel_name']))] ?? null;
    $price = $branch_price !== null
        ? (float)$branch_price['price_usd']
        : (float)$fuel['price_per_liter'];
    $price_lrd = $branch_price !== null && (float)$branch_price['price_lrd'] > 0
        ? (float)$branch_price['price_lrd']
        : null;
    $is_branch_price = $branch_price !== null && ($price > 0 || $price_lrd !== null);
    $has_price = $price > 0 || $price_lrd !== null;

    $all_fuels[] = [
        'id' => (int)$fuel['id'],
        'name' => $fuel['fuel_name'],
        'code' => $fuel['fuel_code'],
        'price' => $price > 0 ? $price : 0,
        'price_lrd' => $price_lrd,
        'price_type' => $is_branch_price ? 'Branch Price' : ($has_price ? 'Standard Price' : 'Price Not Set'),
        'price_badge' => $is_branch_price ? 'success' : ($has_price ? 'info' : 'warning'),
        'has_price' => $has_price
    ];
}

// Now include the header and sidebar components
require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';
?>

<div class="container-fluid py-4 px-4 fuel-prices-page">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tag me-2"></i> Fuel Prices
                        <small class="fuel-price-updated d-block text-muted mt-1">
                            <?php if ($latest_price_update !== null): ?>
                                Branch prices last updated <?php echo date('M d, Y g:i A', strtotime($latest_price_update)); ?>
                            <?php else: ?>
                                Standard fuel prices for this branch
                            <?php endif; ?>
                        </small>
                    </h5>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-sm" onclick="refreshPrices(this)">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                        <button class="btn btn-success btn-sm" onclick="printPrices()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Current Prices Grid -->
                    <div class="row mb-4">
                        <?php if (count($all_fuels) > 0): ?>
                            <?php foreach($all_fuels as $fuel): ?>
                                <div class="col-12 col-sm-6 col-xl-3 mb-3">
                                    <div class="card h-100 price-card border-<?php 
                                        echo $fuel['price_badge'] == 'success' ? 'success' : 
                                             ($fuel['price_badge'] == 'warning' ? 'warning' : 'secondary');
                                    ?>">
                                        <div class="card-header bg-<?php echo $fuel['price_badge']; ?> text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-gas-pump me-2"></i> 
                                                    <?php echo htmlspecialchars($fuel['name']); ?>
                                                </h6>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Current Price -->
                                            <div class="text-center mb-3">
                                                <h1 class="display-6 text-primary fw-bold mb-1">
                                                    <?php if ($fuel['price'] > 0): ?>
                                                        $<?php echo number_format($fuel['price'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </h1>
                                                <small class="text-muted">per liter</small>
                                                <?php if ($fuel['price_lrd'] !== null): ?>
                                                <div class="price-lrd-value mt-2 fw-semibold text-success">
                                                        L$<?php echo number_format($fuel['price_lrd'], 2); ?> <small>per liter</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Price Type Badge -->
                                            <div class="text-center mb-3">
                                                <span class="badge bg-<?php echo $fuel['price_badge']; ?>">
                                                    <?php echo $fuel['price_type']; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Additional Info -->
                                            <div class="price-details">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <div class="text-center p-2 border rounded">
                                                            <small class="text-muted d-block">Code</small>
                                                            <strong class="text-info">
                                                                <?php echo htmlspecialchars($fuel['code']); ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center p-2 border rounded">
                                                            <small class="text-muted d-block">Status</small>
                                                            <strong class="<?php 
                                                                echo $fuel['price'] > 0 ? 'text-success' : 'text-warning';
                                                            ?>">
                                                                <?php echo $fuel['has_price'] ? 'Active' : 'Not Set'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Branch Note -->
                                            <?php if ($fuel['price_badge'] == 'success'): ?>
                                                <div class="mt-3 p-2 bg-info bg-opacity-10 border border-info rounded">
                                                    <small class="text-info">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Branch-specific price for <?php echo htmlspecialchars($branch_name); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-grid gap-2">
                                                <?php if ($fuel['price'] > 0): ?>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="calculateFuel(<?php echo $fuel['id']; ?>, <?php echo json_encode($fuel['price']); ?>, <?php echo json_encode($fuel['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                                        <i class="fas fa-calculator me-1"></i> Calculate
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="fas fa-ban me-1"></i> Price Not Set
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center py-5">
                                    <i class="fas fa-tag fa-3x mb-3"></i>
                                    <h5>No Fuel Prices Available</h5>
                                    <p class="mb-0">Please contact your manager to set up fuel prices.</p>
                                    <a href="dashboard.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-chart-bar me-2"></i> Price Summary
                                    </h6>
                                    <div class="row text-center">
                                        <div class="col-md-3 mb-3">
                                            <div class="border rounded p-3 bg-light">
                                                <h3 class="text-primary mb-2">
                                                    <?php echo count($all_fuels); ?>
                                                </h3>
                                                <div class="text-muted">Total Fuels</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="border rounded p-3 bg-light">
                                                <h3 class="text-success mb-2">
                                                    <?php 
                                                    $available_prices = array_filter($all_fuels, function($fuel) {
                                                        return $fuel['has_price'];
                                                    });
                                                    echo count($available_prices);
                                                    ?>
                                                </h3>
                                                <div class="text-muted">With Prices</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="border rounded p-3 bg-light">
                                                <h3 class="text-info mb-2">
                                                    <?php 
                                                    $branch_prices_count = array_filter($all_fuels, function($fuel) {
                                                        return $fuel['price_type'] === 'Branch Price';
                                                    });
                                                    echo count($branch_prices_count);
                                                    ?>
                                                </h3>
                                                <div class="text-muted">Branch Prices</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="border rounded p-3 bg-light">
                                                <h3 class="<?php echo (count($all_fuels) - count($available_prices)) > 0 ? 'text-warning' : 'text-success'; ?> mb-2">
                                                    <?php echo count($all_fuels) - count($available_prices); ?>
                                                </h3>
                                                <div class="text-muted">Missing Prices</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-bolt text-warning me-2"></i> Quick Actions
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-3 col-6">
                                            <a href="calculator.php" class="btn btn-outline-primary w-100 py-3">
                                                <i class="fas fa-calculator fa-lg mb-2"></i><br>
                                                <small>Advanced Calculator</small>
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <button class="btn btn-outline-success w-100 py-3" onclick="downloadPriceList()">
                                                <i class="fas fa-download fa-lg mb-2"></i><br>
                                                <small>Export Prices</small>
                                            </button>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <a href="sales.php" class="btn btn-outline-info w-100 py-3">
                                                <i class="fas fa-gas-pump fa-lg mb-2"></i><br>
                                                <small>New Sale</small>
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <a href="dashboard.php" class="btn btn-outline-warning w-100 py-3">
                                                <i class="fas fa-home fa-lg mb-2"></i><br>
                                                <small>Dashboard</small>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center text-muted small">
                    <p class="mb-0">God Willing Business Center - Fuel Management System</p>
                    <p class="mb-0">Prices shown are for <?php echo htmlspecialchars($branch_name); ?> branch</p>
                    <?php if ($latest_price_update !== null): ?>
                        <p class="mb-0">Branch prices last updated: <?php echo date('F d, Y \a\t h:i A', strtotime($latest_price_update)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Calculator Modal -->
<div class="modal fade" id="quickCalculatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calculatorModalTitle">Quick Fuel Calculator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Fuel Type</label>
                    <input type="text" class="form-control" id="calcFuelType" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price per Liter</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="calcPrice" step="0.01" readonly>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity (Liters)</label>
                    <input type="number" class="form-control" id="calcQuantity" 
                           min="0.01" step="0.01" value="1" oninput="calculateQuickTotal()">
                </div>
                <div class="mb-3">
                    <label class="form-label">Total Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" id="calcTotal" readonly>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <button class="btn btn-outline-secondary w-100" onclick="setQuantity(5)">5L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-secondary w-100" onclick="setQuantity(10)">10L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-secondary w-100" onclick="setQuantity(20)">20L</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="goToCalculator()">
                    <i class="fas fa-calculator me-1"></i> Advanced Calculator
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.fuel-prices-page {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

.fuel-prices-page .card-header h5 {
    min-width: 0;
}

.fuel-prices-page .price-card {
    border-radius: 0.8rem;
    overflow: hidden;
}

.fuel-prices-page .price-card .card-header h6 {
    overflow-wrap: anywhere;
}

.price-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.price-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.price-details .border {
    transition: all 0.3s ease;
}

.price-details .border:hover {
    border-color: var(--primary-green) !important;
    transform: scale(1.02);
}

@media (max-width: 767px) {
    #mainContentWrapper {
        width: 100%;
        margin-left: 0 !important;
        transition: none !important;
    }

    .fuel-prices-page {
        padding: 1rem !important;
    }

    .fuel-prices-page > .row > [class*="col-"] {
        min-width: 0;
    }

    .fuel-prices-page > .row > [class*="col-"] > .card > .card-header {
        align-items: flex-start !important;
    }

    .fuel-prices-page .display-6 {
        font-size: clamp(1.6rem, 8vw, 2rem);
    }
}
</style>

<script>
// Quick calculator
function calculateFuel(fuelId, price, fuelName) {
    $('#calcFuelType').val(fuelName);
    $('#calcPrice').val(price);
    $('#calculatorModalTitle').text(`Calculate: ${fuelName}`);
    calculateQuickTotal();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('quickCalculatorModal')).show();
}

function calculateQuickTotal() {
    const price = parseFloat($('#calcPrice').val()) || 0;
    const quantity = parseFloat($('#calcQuantity').val()) || 0;
    const total = price * quantity;
    $('#calcTotal').val('$' + total.toFixed(2));
}

function setQuantity(liters) {
    $('#calcQuantity').val(liters);
    calculateQuickTotal();
}

function goToCalculator() {
    window.location.href = 'calculator.php';
}

function refreshPrices(button) {
    // Show loading indicator
    const refreshBtn = button;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

function printPrices() {
    window.print();
}

function downloadPriceList() {
    const data = [];
    data.push('Fuel Prices Report - <?php echo addslashes($branch_name); ?>');
    data.push('Generated on: ' + new Date().toLocaleString());
    data.push('');
    data.push('Fuel Type,Code,USD Price per Liter,LRD Price per Liter,Type,Status');
    
    document.querySelectorAll('.price-card').forEach(card => {
        const fuelName = card.querySelector('.card-title').textContent.trim();
        const priceElement = card.querySelector('.display-6');
        const lrdElement = card.querySelector('.price-lrd-value');
        const codeElement = card.querySelector('.text-info');
        const badgeElement = card.querySelector('.badge');
        const statusElement = card.querySelector('.text-success, .text-warning');
        
        let price = '0.00';
        let priceLrd = '';
        let code = 'N/A';
        let type = 'Standard';
        let status = 'Active';
        
        if (priceElement) {
            price = priceElement.textContent.replace('$', '').replace('N/A', '0.00').trim();
        }
        if (lrdElement) {
            priceLrd = lrdElement.textContent.replace('L$', '').replace('per liter', '').trim();
        }
        if (codeElement) {
            code = codeElement.textContent.trim();
        }
        if (badgeElement) {
            type = badgeElement.textContent.trim();
        }
        if (statusElement) {
            status = statusElement.textContent.trim();
        }
        
        data.push(`"${fuelName}","${code}","${price}","${priceLrd}","${type}","${status}"`);
    });
    
    data.push('');
    data.push('Summary:');
    data.push(`Total Fuels: ${document.querySelectorAll('.price-card').length}`);
    
    const csv = data.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `fuel_prices_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto-refresh every 5 minutes to check for price updates
setTimeout(() => {
    if (confirm('Prices may have been updated. Refresh now?')) {
        window.location.reload();
    }
}, 300000); // 5 minutes

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php 
// Include the footer
require_once 'attendant_footer.php'; 
?>