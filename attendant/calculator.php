<?php
// calculator.php
require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';

// Get current fuel prices
$fuel_prices_sql = "SELECT f.*, t.current_quantity 
                   FROM fuel_types f 
                   LEFT JOIN tanks t ON f.id = t.fuel_type_id AND t.branch_id = " . $_SESSION['branch_id'] . "
                   WHERE f.status = 'active' 
                   ORDER BY f.fuel_name";
$fuel_prices = $conn->query($fuel_prices_sql);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calculator"></i> Fuel Calculator
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left Column: Calculator -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="fuelType" class="form-label">Fuel Type *</label>
                                            <select class="form-select" id="fuelType" required>
                                                <option value="">Select Fuel Type</option>
                                                <?php while($fuel = $fuel_prices->fetch_assoc()): ?>
                                                <option value="<?php echo $fuel['id']; ?>" 
                                                        data-price="<?php echo $fuel['price_per_liter']; ?>"
                                                        data-stock="<?php echo $fuel['current_quantity'] ?? 0; ?>">
                                                    <?php echo $fuel['fuel_name']; ?> - $<?php echo number_format($fuel['price_per_liter'], 2); ?>/L
                                                    <?php if($fuel['current_quantity'] > 0): ?>
                                                        (Stock: <?php echo number_format($fuel['current_quantity'], 2); ?>L)
                                                    <?php endif; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="unitPrice" class="form-label">Unit Price ($/L) *</label>
                                            <input type="number" class="form-control" id="unitPrice" 
                                                   step="0.01" min="0" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Calculate By</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="calculateBy" 
                                                       id="calculateQuantity" autocomplete="off" checked>
                                                <label class="btn btn-outline-primary" for="calculateQuantity">
                                                    Quantity (L)
                                                </label>
                                                
                                                <input type="radio" class="btn-check" name="calculateBy" 
                                                       id="calculateAmount" autocomplete="off">
                                                <label class="btn btn-outline-primary" for="calculateAmount">
                                                    Amount ($)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="availableStock" class="form-label">Available Stock</label>
                                            <input type="text" class="form-control" id="availableStock" 
                                                   readonly style="background-color: #f8f9fa;">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="quantity" class="form-label">Quantity (Liters) *</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="quantity" 
                                                       step="0.01" min="0" value="0.00" required>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="addQuantity(1)">
                                                    +1L
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="addQuantity(5)">
                                                    +5L
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="addQuantity(10)">
                                                    +10L
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Amount ($) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="amount" 
                                                       step="0.01" min="0" value="0.00" required>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="addAmount(10)">
                                                    +$10
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="addAmount(20)">
                                                    +$20
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Quantity Buttons -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Quick Quantity</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(1)">
                                                1 Liter
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(5)">
                                                5 Liters
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(10)">
                                                10 Liters
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(20)">
                                                20 Liters
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(50)">
                                                50 Liters
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="setQuantity(100)">
                                                100 Liters
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Amount Buttons -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Quick Amount</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(5)">
                                                $5
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(10)">
                                                $10
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(20)">
                                                $20
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(50)">
                                                $50
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(100)">
                                                $100
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="setAmount(200)">
                                                $200
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-secondary flex-fill" onclick="resetCalculator()">
                                                <i class="fas fa-redo"></i> Reset
                                            </button>
                                            <button type="button" class="btn btn-primary flex-fill" onclick="calculate()">
                                                <i class="fas fa-calculator"></i> Calculate
                                            </button>
                                            <button type="button" class="btn btn-success flex-fill" id="proceedSaleBtn" disabled>
                                                <i class="fas fa-shopping-cart"></i> Proceed to Sale
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Results -->
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="card-title mb-0">Calculation Results</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Fuel Type</label>
                                    <h5 id="resultFuelType" class="mb-0">-</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-muted">Quantity</label>
                                        <h4 id="resultQuantity" class="mb-0">0.00 L</h4>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted">Unit Price</label>
                                        <h4 id="resultUnitPrice" class="mb-0">$0.00</h4>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Subtotal</label>
                                    <h4 id="resultSubtotal" class="mb-0">$0.00</h4>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">Tax (%)</span>
                                        <input type="number" class="form-control" id="taxRate" 
                                               value="16" step="0.1" min="0" max="100">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Tax Amount</label>
                                    <h4 id="resultTax" class="mb-0">$0.00</h4>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Total Amount</label>
                                    <h2 id="resultTotal" class="text-success mb-0">$0.00</h2>
                                </div>
                                
                                <div class="alert alert-info" id="stockWarning" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span id="warningText"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Calculations -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Recent Calculations
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm" id="recentCalculations">
                                        <thead>
                                            <tr>
                                                <th>Fuel</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Recent calculations will be added here -->
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-2" onclick="clearRecent()">
                                    Clear History
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let recentCalculations = [];
const savedCalculations = localStorage.getItem('fuelCalculations');
if (savedCalculations) {
    try {
        const parsedCalculations = JSON.parse(savedCalculations);
        if (Array.isArray(parsedCalculations)) {
            recentCalculations = parsedCalculations;
        } else {
            console.error('Saved fuel calculation history is not an array.');
        }
    } catch (error) {
        console.error('Unable to parse saved fuel calculation history.', error);
    }
}

$(document).ready(function() {
    displayRecentCalculations();
    
    // Fuel type change handler
    $('#fuelType').change(function() {
        const selectedOption = $(this).find('option:selected');
        const price = selectedOption.data('price');
        const stock = selectedOption.data('stock') || 0;
        
        $('#unitPrice').val(price || '');
        $('#availableStock').val(stock > 0 ? stock.toFixed(2) + ' L' : 'Out of Stock');
        
        // Update result display
        $('#resultFuelType').text(selectedOption.text().split(' - ')[0]);
        $('#resultUnitPrice').text('$' + (price || '0.00'));
        
        // Enable/disable proceed button
        $('#proceedSaleBtn').prop('disabled', !price || stock <= 0);
        
        // Clear calculations
        clearResults();
    });
    
    // Calculate by quantity/amount toggle
    $('input[name="calculateBy"]').change(function() {
        const calculateByQuantity = $('#calculateQuantity').is(':checked');
        
        $('#quantity').prop('disabled', !calculateByQuantity);
        $('#amount').prop('disabled', calculateByQuantity);
        
        if (calculateByQuantity) {
            $('#quantity').focus();
            $('#amount').val('0.00');
        } else {
            $('#amount').focus();
            $('#quantity').val('0.00');
        }
        
        clearResults();
    });
    
    // Real-time calculation
    $('#quantity, #amount, #unitPrice, #taxRate').on('input', function() {
        calculate();
    });
    
    // Initialize
    $('#quantity').focus();
});

function calculate() {
    const unitPrice = parseFloat($('#unitPrice').val()) || 0;
    const quantity = parseFloat($('#quantity').val()) || 0;
    const amount = parseFloat($('#amount').val()) || 0;
    const taxRate = parseFloat($('#taxRate').val()) || 0;
    
    let calculatedQuantity = quantity;
    let calculatedAmount = amount;
    let calculatedTotal = 0;
    
    // Determine which field to calculate from
    if ($('#calculateQuantity').is(':checked')) {
        // Calculate from quantity
        calculatedAmount = quantity * unitPrice;
        $('#amount').val(calculatedAmount.toFixed(2));
    } else {
        // Calculate from amount
        calculatedQuantity = unitPrice > 0 ? amount / unitPrice : 0;
        $('#quantity').val(calculatedQuantity.toFixed(2));
    }
    
    // Calculate totals
    const subtotal = calculatedAmount;
    const taxAmount = subtotal * (taxRate / 100);
    calculatedTotal = subtotal + taxAmount;
    
    // Update results display
    $('#resultQuantity').text(calculatedQuantity.toFixed(2) + ' L');
    $('#resultSubtotal').text('$' + subtotal.toFixed(2));
    $('#resultTax').text('$' + taxAmount.toFixed(2));
    $('#resultTotal').text('$' + calculatedTotal.toFixed(2));
    
    // Check stock availability
    const selectedFuel = $('#fuelType option:selected');
    const stock = selectedFuel.data('stock') || 0;
    
    if (stock > 0 && calculatedQuantity > stock) {
        $('#stockWarning').show();
        $('#warningText').text(`Insufficient stock! Available: ${stock.toFixed(2)}L, Required: ${calculatedQuantity.toFixed(2)}L`);
        $('#proceedSaleBtn').prop('disabled', true);
    } else if (stock <= 0) {
        $('#stockWarning').show();
        $('#warningText').text('This fuel type is out of stock!');
        $('#proceedSaleBtn').prop('disabled', true);
    } else {
        $('#stockWarning').hide();
        $('#proceedSaleBtn').prop('disabled', false);
    }
}

function addQuantity(liters) {
    const current = parseFloat($('#quantity').val()) || 0;
    $('#quantity').val((current + liters).toFixed(2));
    $('#calculateQuantity').prop('checked', true).trigger('change');
    calculate();
}

function addAmount(dollars) {
    const current = parseFloat($('#amount').val()) || 0;
    $('#amount').val((current + dollars).toFixed(2));
    $('#calculateAmount').prop('checked', true).trigger('change');
    calculate();
}

function setQuantity(liters) {
    $('#quantity').val(liters.toFixed(2));
    $('#calculateQuantity').prop('checked', true).trigger('change');
    calculate();
}

function setAmount(dollars) {
    $('#amount').val(dollars.toFixed(2));
    $('#calculateAmount').prop('checked', true).trigger('change');
    calculate();
}

function resetCalculator() {
    $('#quantity').val('0.00');
    $('#amount').val('0.00');
    $('#taxRate').val('16');
    $('#calculateQuantity').prop('checked', true).trigger('change');
    clearResults();
    $('#stockWarning').hide();
    $('#proceedSaleBtn').prop('disabled', true);
    $('#quantity').focus();
}

function clearResults() {
    $('#resultQuantity').text('0.00 L');
    $('#resultSubtotal').text('$0.00');
    $('#resultTax').text('$0.00');
    $('#resultTotal').text('$0.00');
}

function saveToRecent() {
    const fuelType = $('#fuelType option:selected').text().split(' - ')[0];
    const quantity = parseFloat($('#quantity').val()) || 0;
    const total = $('#resultTotal').text().replace('$', '');
    
    if (!fuelType || quantity <= 0) return;
    
    const calculation = {
        fuel: fuelType,
        quantity: quantity.toFixed(2),
        total: total,
        timestamp: new Date().toLocaleTimeString()
    };
    
    recentCalculations.unshift(calculation);
    if (recentCalculations.length > 10) {
        recentCalculations = recentCalculations.slice(0, 10);
    }
    
    localStorage.setItem('fuelCalculations', JSON.stringify(recentCalculations));
    displayRecentCalculations();
}

function displayRecentCalculations() {
    const tbody = $('#recentCalculations tbody');
    tbody.empty();
    
    recentCalculations.forEach(calc => {
        tbody.append(`
            <tr>
                <td>${calc.fuel}</td>
                <td>${calc.quantity}L</td>
                <td>$${calc.total}</td>
            </tr>
        `);
    });
}

function clearRecent() {
    recentCalculations = [];
    localStorage.removeItem('fuelCalculations');
    displayRecentCalculations();
}

// Proceed to sale
$('#proceedSaleBtn').click(function() {
    if ($(this).prop('disabled')) return;
    
    const fuelId = $('#fuelType').val();
    const quantity = parseFloat($('#quantity').val()) || 0;
    const unitPrice = parseFloat($('#unitPrice').val()) || 0;
    
    if (!fuelId || quantity <= 0 || unitPrice <= 0) {
        alert('Please complete the calculation first');
        return;
    }
    
    // Save to session storage for the sales page
    sessionStorage.setItem('quickSale', JSON.stringify({
        fuel_type_id: fuelId,
        quantity: quantity,
        unit_price: unitPrice,
        total_amount: $('#resultTotal').text().replace('$', '')
    }));
    
    // Save to recent calculations
    saveToRecent();
    
    // Redirect to sales page
    window.location.href = 'sales.php?quick_sale=true';
});
</script>

<?php
require_once 'attendant_footer.php';
?>