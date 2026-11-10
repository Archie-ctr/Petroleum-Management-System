<?php

require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';


$branch_id = (int)($_SESSION['branch_id'] ?? 0);
$attendant_id = (int)($_SESSION['user_id'] ?? 0);
$usd_to_lrd_rate = (float)($settings['usd_to_lrd_rate'] ?? 0);
$branch_fuel_prices = [];
$branch_price_stmt = $conn->prepare(
    'SELECT fuel_type, price_usd, price_lrd FROM fuel_prices WHERE branch_id = ?'
);
$branch_price_stmt->bind_param('i', $branch_id);
$branch_price_stmt->execute();
$branch_price_result = $branch_price_stmt->get_result();
while ($row = $branch_price_result->fetch_assoc()) {
    $branch_fuel_prices[mb_strtolower(trim($row['fuel_type']))] = $row;
}
$branch_price_stmt->close();

// Handle sale recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
    $fuel_type_id = filter_input(INPUT_POST, 'fuel_type_id', FILTER_VALIDATE_INT);
    $pump_id = filter_input(INPUT_POST, 'pump_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $currency = $_POST['currency'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $customer_phone = trim((string)($_POST['customer_phone'] ?? ''));
    $vehicle_number = trim((string)($_POST['vehicle_number'] ?? ''));

    if (!$fuel_type_id || !$pump_id || $quantity === false || !is_finite((float)$quantity)
        || $quantity <= 0 || !in_array($currency, ['USD', 'LRD'], true)
        || !in_array($payment_method, ['cash', 'card', 'mobile_money', 'credit'], true)
        || strlen($customer_name) > 100 || strlen($customer_phone) > 30
        || ($customer_phone !== '' && !preg_match('/^[0-9+().\s-]+$/', $customer_phone))
        || strlen($vehicle_number) > 50) {
        $error = 'Enter valid sale details. The customer number may contain digits, spaces, +, parentheses, periods, and hyphens.';
    } else {
        $fuel_stmt = $conn->prepare(
            "SELECT id, fuel_name, price_per_liter FROM fuel_types WHERE id = ? AND status = 'active'"
        );
        $fuel_stmt->bind_param('i', $fuel_type_id);
        $fuel_stmt->execute();
        $fuel = $fuel_stmt->get_result()->fetch_assoc();
        $fuel_stmt->close();

        $pump_stmt = $conn->prepare(
            "SELECT p.id, p.tank_id, t.current_quantity, t.fuel_type_id
             FROM pumps p
             JOIN tanks t ON t.id = p.tank_id
             WHERE p.id = ? AND p.branch_id = ? AND p.status = 'active'
               AND t.branch_id = ? AND t.status = 'active'"
        );
        $pump_stmt->bind_param('iii', $pump_id, $branch_id, $branch_id);
        $pump_stmt->execute();
        $pump = $pump_stmt->get_result()->fetch_assoc();
        $pump_stmt->close();

        if (!$fuel || !$pump || (int)$pump['fuel_type_id'] !== $fuel_type_id) {
            $error = 'The selected fuel type or pump is not available at this branch.';
        } elseif ((float)$pump['current_quantity'] < $quantity) {
            $error = 'The selected tank does not have enough fuel for this sale.';
        } else {
            $branch_price = $branch_fuel_prices[mb_strtolower(trim($fuel['fuel_name']))] ?? null;
            $usd_price = $branch_price !== null && (float)$branch_price['price_usd'] > 0
                ? (float)$branch_price['price_usd']
                : (float)$fuel['price_per_liter'];
            $lrd_price = $branch_price !== null && (float)$branch_price['price_lrd'] > 0
                ? (float)$branch_price['price_lrd']
                : ($usd_to_lrd_rate > 0 ? $usd_price * $usd_to_lrd_rate : 0);
            $price_per_liter = round($currency === 'USD' ? $usd_price : $lrd_price, 2);

            if ($price_per_liter <= 0) {
                $error = $currency === 'LRD'
                    ? 'LRD pricing is not configured for this branch. Ask an administrator to set a branch LRD price or configure the USD to LRD rate in Financial Settings.'
                    : 'USD pricing is not configured for this fuel type. Ask an administrator to configure the fuel price.';
            } else {
                $total_amount = round((float)$quantity * $price_per_liter, 2);
                $sale_number = 'SALE-' . date('YmdHis') . '-' . random_int(10, 99);
                $conn->begin_transaction();
                try {
                    $sql = "INSERT INTO sales
                                (sale_number, branch_id, pump_id, fuel_type_id, attendant_id,
                                 quantity, price_per_liter, total_amount, currency, payment_method,
                                 customer_name, customer_phone, vehicle_number, sale_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new RuntimeException('Unable to prepare sale: ' . $conn->error);
                    }
                    $stmt->bind_param(
                        'siiiidddsssss',
                        $sale_number,
                        $branch_id,
                        $pump_id,
                        $fuel_type_id,
                        $attendant_id,
                        $quantity,
                        $price_per_liter,
                        $total_amount,
                        $currency,
                        $payment_method,
                        $customer_name,
                        $customer_phone,
                        $vehicle_number
                    );
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Unable to record sale: ' . $stmt->error);
                    }
                    $sale_id = $stmt->insert_id;
                    $stmt->close();

                    $update_stmt = $conn->prepare(
                        'UPDATE tanks SET current_quantity = current_quantity - ? WHERE id = ? AND current_quantity >= ?'
                    );
                    $tank_id = (int)$pump['tank_id'];
                    $update_stmt->bind_param('did', $quantity, $tank_id, $quantity);
                    if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                        throw new RuntimeException('Tank quantity could not be updated for this sale.');
                    }
                    $update_stmt->close();
                    $conn->commit();

                    $currency_symbol = $currency === 'LRD' ? 'L$' : '$';
                    $success = "Sale recorded successfully! Sale Number: <strong>"
                        . htmlspecialchars($sale_number, ENT_QUOTES, 'UTF-8') . '</strong>';
                    $success .= '<br>Amount: <strong>' . $currency_symbol . number_format($total_amount, 2)
                        . ' ' . $currency . '</strong>';
                    $success .= "<br><a href='receipt.php?sale_id=" . (int)$sale_id
                        . "' target='_blank' class='btn btn-sm btn-success mt-2'>View Receipt</a>";
                    $_POST = [];
                } catch (Throwable $exception) {
                    $conn->rollback();
                    error_log('Sale recording failed: ' . $exception->getMessage());
                    $error = 'The sale could not be completed. Please refresh and try again.';
                }
            }
        }
    }
}

// Get fuel types
$fuel_types_sql = "SELECT * FROM fuel_types WHERE status = 'active'";
$fuel_types = $conn->query($fuel_types_sql);

// Get pumps for this branch
$pumps_sql = "SELECT * FROM pumps WHERE branch_id = $branch_id AND status = 'active'";
$pumps = $conn->query($pumps_sql);

// Get tank quantities for display
$tanks_sql = "SELECT t.*, f.fuel_name 
              FROM tanks t
              JOIN fuel_types f ON t.fuel_type_id = f.id
              WHERE t.branch_id = $branch_id
              AND t.status = 'active'";
$tanks = $conn->query($tanks_sql);
?>
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-gas-pump"></i> Record Fuel Sale
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="saleForm">
                    <div class="mb-3">
                        <label for="fuel_type_id" class="form-label">Select Fuel Type *</label>
                        <select class="form-control" id="fuel_type_id" name="fuel_type_id" required 
                                onchange="updateFuelPrice()">
                            <option value="">-- Select Fuel --</option>
                            <?php while($fuel = $fuel_types->fetch_assoc()):
                                $branch_price = $branch_fuel_prices[mb_strtolower(trim($fuel['fuel_name']))] ?? null;
                                $fuel_usd_price = $branch_price !== null && (float)$branch_price['price_usd'] > 0
                                    ? (float)$branch_price['price_usd']
                                    : (float)$fuel['price_per_liter'];
                                $fuel_lrd_price = $branch_price !== null && (float)$branch_price['price_lrd'] > 0
                                    ? (float)$branch_price['price_lrd']
                                    : ($usd_to_lrd_rate > 0 ? $fuel_usd_price * $usd_to_lrd_rate : 0);
                            ?>
                                <option value="<?php echo (int)$fuel['id']; ?>"
                                        data-price-usd="<?php echo htmlspecialchars((string)$fuel_usd_price, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-price-lrd="<?php echo htmlspecialchars((string)$fuel_lrd_price, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo (int)($_POST['fuel_type_id'] ?? 0) === (int)$fuel['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fuel['fuel_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pump_id" class="form-label">Pump Number</label>
                        <select class="form-control" id="pump_id" name="pump_id" required>
                            <option value="">-- Select Pump --</option>
                            <?php 
                            $pumps->data_seek(0); // Reset pointer
                            while($pump = $pumps->fetch_assoc()): 
                            ?>
                                <option value="<?php echo (int)$pump['id']; ?>"
                                        <?php echo (int)($_POST['pump_id'] ?? 0) === (int)$pump['id'] ? 'selected' : ''; ?>>
                                    Pump <?php echo htmlspecialchars($pump['pump_number'], ENT_QUOTES, 'UTF-8'); ?>
                                    - <?php echo htmlspecialchars($pump['pump_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="currency" class="form-label">Customer Pays In *</label>
                        <select class="form-control" id="currency" name="currency" required onchange="updateFuelPrice()">
                            <option value="USD" <?php echo ($_POST['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="LRD" <?php echo ($_POST['currency'] ?? '') === 'LRD' ? 'selected' : ''; ?>>LRD (L$)</option>
                        </select>
                        <small class="form-text text-muted" id="currencyPricingNote">
                            Select the currency the customer is paying with. LRD requires a branch LRD price or a configured USD-to-LRD rate.
                        </small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity (Liters) *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="quantity" 
                                       name="quantity" step="0.01" min="0.01" required
                                       value="<?php echo htmlspecialchars((string)($_POST['quantity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                       oninput="calculateTotal()">
                                <span class="input-group-text">L</span>
                            </div>
                            <small class="text-muted">Enter quantity in liters</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="price_per_liter" class="form-label">Price/Liter</label>
                            <div class="input-group">
                                <span class="input-group-text" id="currencySymbol">$</span>
                                <input type="number" class="form-control" id="price_per_liter" 
                                       step="0.01" min="0" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="total_amount" class="form-label">Total Amount</label>
                        <div class="input-group">
                            <span class="input-group-text" id="totalCurrencySymbol">$</span>
                            <input type="text" class="form-control" id="total_amount" readonly 
                                   style="font-weight: bold; font-size: 1.2em;">
                        </div>
                        <small class="text-danger d-none" id="priceUnavailable">
                            LRD price is not available. Ask an administrator to configure branch LRD pricing or the USD-to-LRD rate.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="credit">Credit (Account)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="100"
                               value="<?php echo htmlspecialchars((string)($_POST['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_phone" class="form-label">Customer Number (Optional)</label>
                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone"
                               maxlength="30" inputmode="tel" autocomplete="tel"
                               placeholder="e.g. +231 77 123 4567"
                               value="<?php echo htmlspecialchars((string)($_POST['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="vehicle_number" class="form-label">Vehicle Number (Optional)</label>
                        <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" maxlength="50"
                               value="<?php echo htmlspecialchars((string)($_POST['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="record_sale" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle"></i> Complete Sale
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Calculator -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-calculator"></i> Quick Calculator
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(5)">5 L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(10)">10 L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(20)">20 L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(30)">30 L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(40)">40 L</button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" onclick="setQuantity(50)">50 L</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <!-- Tank Status -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-oil-can"></i> Fuel Tank Status
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php while($tank = $tanks->fetch_assoc()): 
                        $percentage = ($tank['current_quantity'] / $tank['capacity']) * 100;
                        $status_class = $percentage > 50 ? 'bg-success' : 
                                      ($percentage > 20 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <?php echo $tank['fuel_name']; ?>
                                    <small class="text-muted">(Tank <?php echo $tank['tank_number']; ?>)</small>
                                </h6>
                                <p class="card-text">
                                    <i class="fas fa-gas-pump"></i> 
                                    <?php echo number_format($tank['current_quantity'], 2); ?> L 
                                    of <?php echo number_format($tank['capacity'], 2); ?> L
                                </p>
                                <div class="progress" style="height: 15px;">
                                    <div class="progress-bar <?php echo $status_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <?php if ($tank['current_quantity'] < $tank['minimum_threshold']): ?>
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        Low Stock! Needs refill
                                    <?php else: ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                        Stock OK
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Your Recent Sales
                </h5>
            </div>
            <div class="card-body">
                <?php
                $recent_sales_sql = "SELECT s.*, f.fuel_name 
                                    FROM sales s
                                    JOIN fuel_types f ON s.fuel_type_id = f.id
                                    WHERE s.attendant_id = $attendant_id
                                    AND DATE(s.sale_date) = CURDATE()
                                    ORDER BY s.sale_date DESC LIMIT 10";
                $recent_sales = $conn->query($recent_sales_sql);
                ?>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Fuel</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_sales->num_rows > 0): ?>
                                <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo $sale['fuel_name']; ?></td>
                                    <td><?php echo number_format($sale['quantity'], 2); ?> L</td>
                                    <td><?php echo $sale['currency'] === 'LRD' ? 'L$' : '$'; ?><?php echo number_format($sale['total_amount'], 2); ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($sale['currency'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $sale['payment_method'] == 'cash' ? 'success' : 'info';
                                        ?>">
                                            <?php echo substr(ucfirst($sale['payment_method']), 0, 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="receipt.php?sale_id=<?php echo $sale['id']; ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <i class="fas fa-info-circle"></i> No sales recorded today
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <?php
                $summary_sql = "SELECT currency, COUNT(*) AS count, SUM(total_amount) AS total
                                FROM sales
                                WHERE attendant_id = $attendant_id
                                  AND DATE(sale_date) = CURDATE()
                                GROUP BY currency";
                $summary_result = $conn->query($summary_sql);
                $summary_by_currency = [];
                $daily_sale_count = 0;
                while ($summary = $summary_result->fetch_assoc()) {
                    $summary_by_currency[$summary['currency']] = (float)$summary['total'];
                    $daily_sale_count += (int)$summary['count'];
                }
                ?>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="alert alert-info mb-0">
                            <strong>Today's Summary:</strong><br>
                            Sales: <?php echo $daily_sale_count; ?> transactions<br>
                            <?php if (isset($summary_by_currency['USD'])): ?>
                                Total USD: $<?php echo number_format($summary_by_currency['USD'], 2); ?><br>
                            <?php endif; ?>
                            <?php if (isset($summary_by_currency['LRD'])): ?>
                                Total LRD: L$<?php echo number_format($summary_by_currency['LRD'], 2); ?>
                            <?php endif; ?>
                            <?php if (!$summary_by_currency): ?>
                                No sales recorded today.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success mb-0">
                            <strong>Last Sale:</strong><br>
                            <?php 
                            $last_sale_sql = "SELECT * FROM sales 
                                             WHERE attendant_id = $attendant_id 
                                             ORDER BY sale_date DESC LIMIT 1";
                            $last_sale_result = $conn->query($last_sale_sql);
                            if ($last_sale = $last_sale_result->fetch_assoc()) {
                                echo date('H:i', strtotime($last_sale['sale_date'])) . " - ";
                                echo ($last_sale['currency'] === 'LRD' ? 'L$' : '$')
                                    . number_format($last_sale['total_amount'], 2) . ' '
                                    . htmlspecialchars($last_sale['currency'], ENT_QUOTES, 'UTF-8');
                            } else {
                                echo "No sales yet";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFuelPrice() {
    const fuelSelect = document.getElementById('fuel_type_id');
    const currencySelect = document.getElementById('currency');
    const priceInput = document.getElementById('price_per_liter');
    const symbol = currencySelect.value === 'LRD' ? 'L$' : '$';
    const unavailableNote = document.getElementById('priceUnavailable');
    const pricingNote = document.getElementById('currencyPricingNote');
    const submitButton = document.querySelector('#saleForm button[name="record_sale"]');
    const selectedFuel = fuelSelect.options[fuelSelect.selectedIndex];

    document.getElementById('currencySymbol').textContent = symbol;
    document.getElementById('totalCurrencySymbol').textContent = symbol;
    unavailableNote.classList.add('d-none');
    pricingNote.classList.remove('text-danger');

    if (!fuelSelect.value) {
        priceInput.value = '';
        document.getElementById('total_amount').value = '';
        submitButton.disabled = false;
        return;
    }

    const priceAttribute = currencySelect.value === 'LRD' ? 'data-price-lrd' : 'data-price-usd';
    const price = parseFloat(selectedFuel.getAttribute(priceAttribute)) || 0;
    if (price <= 0) {
        priceInput.value = '';
        document.getElementById('total_amount').value = '';
        unavailableNote.textContent = currencySelect.value === 'LRD'
            ? 'LRD price is unavailable. Ask an administrator to configure branch LRD pricing or the USD-to-LRD rate.'
            : 'USD price is unavailable. Ask an administrator to configure the fuel price.';
        unavailableNote.classList.remove('d-none');
        submitButton.disabled = true;
        return;
    }

    submitButton.disabled = false;
    priceInput.value = price.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const price = parseFloat(document.getElementById('price_per_liter').value) || 0;
    const total = Math.round((quantity * price + Number.EPSILON) * 100) / 100;
    const symbol = document.getElementById('currency').value === 'LRD' ? 'L$' : '$';

    document.getElementById('total_amount').value = symbol + total.toFixed(2);
}

function setQuantity(qty) {
    document.getElementById('quantity').value = qty;
    calculateTotal();
}

document.getElementById('saleForm').addEventListener('reset', function() {
    window.setTimeout(updateFuelPrice, 0);
});
updateFuelPrice();
</script>
<?php
require_once '../includes/footer.php';
?>