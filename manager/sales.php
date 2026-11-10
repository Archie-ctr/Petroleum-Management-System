<?php
require_once '../includes/manager_header.php';

$branch_id = $_SESSION['branch_id'];

// Handle new sale
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_sale'])) {
    $sale_number = 'SALE-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $pump_id = intval($_POST['pump_id']);
    $fuel_type_id = intval($_POST['fuel_type_id']);
    $quantity = floatval($_POST['quantity']);
    $price_per_liter = floatval($_POST['price_per_liter']);
    $total_amount = $quantity * $price_per_liter;
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $customer_name = $conn->real_escape_string($_POST['customer_name']);
    $vehicle_number = $conn->real_escape_string($_POST['vehicle_number']);
    $attendant_id = $_SESSION['user_id'];
    
    $sql = "INSERT INTO sales (sale_number, branch_id, pump_id, fuel_type_id, attendant_id, 
                               quantity, price_per_liter, total_amount, payment_method, 
                               customer_name, vehicle_number, sale_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiidddsss", $sale_number, $branch_id, $pump_id, $fuel_type_id, 
                      $attendant_id, $quantity, $price_per_liter, $total_amount, 
                      $payment_method, $customer_name, $vehicle_number);
    
    if ($stmt->execute()) {
        $success = "Sale recorded successfully! Sale Number: $sale_number";
        // Update tank quantity (in real system, this would be more complex)
    } else {
        $error = "Error recording sale: " . $conn->error;
    }
}

// Get fuel types
$fuel_types_sql = "SELECT * FROM fuel_types WHERE status = 'active'";
$fuel_types = $conn->query($fuel_types_sql);

// Get pumps for this branch
$pumps_sql = "SELECT * FROM pumps WHERE branch_id = $branch_id AND status = 'active'";
$pumps = $conn->query($pumps_sql);

// Get today's sales
$today = date('Y-m-d');
$today_sales_sql = "SELECT s.*, f.fuel_name, p.pump_number 
                    FROM sales s
                    JOIN fuel_types f ON s.fuel_type_id = f.id
                    JOIN pumps p ON s.pump_id = p.id
                    WHERE s.branch_id = $branch_id
                    AND DATE(s.sale_date) = '$today'
                    ORDER BY s.sale_date DESC";
$today_sales = $conn->query($today_sales_sql);
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-shopping-cart"></i> New Sale
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="fuel_type_id" class="form-label">Fuel Type *</label>
                        <select class="form-control" id="fuel_type_id" name="fuel_type_id" required>
                            <option value="">Select Fuel Type</option>
                            <?php while($fuel = $fuel_types->fetch_assoc()): ?>
                                <option value="<?php echo $fuel['id']; ?>" 
                                        data-price="<?php echo $fuel['price_per_liter']; ?>">
                                    <?php echo $fuel['fuel_name']; ?> - $<?php echo $fuel['price_per_liter']; ?>/L
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pump_id" class="form-label">Pump Number *</label>
                        <select class="form-control" id="pump_id" name="pump_id" required>
                            <option value="">Select Pump</option>
                            <?php while($pump = $pumps->fetch_assoc()): ?>
                                <option value="<?php echo $pump['id']; ?>">
                                    <?php echo $pump['pump_name']; ?> (<?php echo $pump['pump_number']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity (Liters) *</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" 
                               step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price_per_liter" class="form-label">Price per Liter ($)</label>
                        <input type="number" class="form-control" id="price_per_liter" name="price_per_liter" 
                               step="0.01" min="0" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="total_amount" class="form-label">Total Amount ($)</label>
                        <input type="text" class="form-control" id="total_amount" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="vehicle_number" class="form-label">Vehicle Number (Optional)</label>
                        <input type="text" class="form-control" id="vehicle_number" name="vehicle_number">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="new_sale" class="btn btn-primary btn-lg">
                            <i class="fas fa-check"></i> Complete Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Today's Sales
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sale No.</th>
                                <th>Time</th>
                                <th>Fuel</th>
                                <th>Pump</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($sale = $today_sales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $sale['sale_number']; ?></td>
                                <td><?php echo date('H:i', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo $sale['fuel_name']; ?></td>
                                <td><?php echo $sale['pump_number']; ?></td>
                                <td><?php echo number_format($sale['quantity'], 2); ?> L</td>
                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($sale['payment_method']) {
                                            case 'cash': echo 'success'; break;
                                            case 'card': echo 'info'; break;
                                            case 'mobile_money': echo 'warning'; break;
                                            case 'credit': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($sale['payment_method']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Calculate total amount
$('#fuel_type_id, #quantity').on('change input', function() {
    var fuelSelect = $('#fuel_type_id');
    var priceInput = $('#price_per_liter');
    var quantityInput = $('#quantity');
    var totalInput = $('#total_amount');
    
    if (fuelSelect.val() && quantityInput.val()) {
        var price = fuelSelect.find(':selected').data('price');
        var quantity = parseFloat(quantityInput.val());
        var total = Math.round((price * quantity + Number.EPSILON) * 100) / 100;
        
        priceInput.val(price);
        totalInput.val('$' + total.toFixed(2));
    }
});
</script>
<?php
require_once '../includes/footer.php';
?>