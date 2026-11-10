<?php
// add_tank.php - Add tank form and processing
require_once '../includes/header.php';

// Handle tank addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tank'])) {
    $branch_id = intval($_POST['branch_id']);
    $fuel_type_id = intval($_POST['fuel_type_id']);
    $tank_number = $conn->real_escape_string($_POST['tank_number']);
    $capacity = floatval($_POST['capacity']);
    $current_quantity = floatval($_POST['current_quantity']);
    $minimum_threshold = floatval($_POST['minimum_threshold']);
    
    // Validate current quantity doesn't exceed capacity
    if ($current_quantity > $capacity) {
        $error = "Error: Initial quantity cannot exceed tank capacity!";
    } else {
        // Check if tank number already exists in this branch
        $check_sql = "SELECT id FROM tanks WHERE branch_id = ? AND tank_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $branch_id, $tank_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Error: Tank number already exists in this branch!";
        } else {
            $sql = "INSERT INTO tanks (branch_id, fuel_type_id, tank_number, capacity, 
                                       current_quantity, minimum_threshold, status, last_refill) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisdddd", $branch_id, $fuel_type_id, $tank_number, $capacity, 
                              $current_quantity, $minimum_threshold);
            
            if ($stmt->execute()) {
                $tank_id = $stmt->insert_id;
                
                // Log the tank creation as inventory transaction
                $log_sql = "INSERT INTO inventory_transactions 
                            (transaction_type, branch_id, tank_id, fuel_type_id, 
                             quantity, unit_price, total_value, transaction_date, performed_by, notes) 
                            VALUES ('purchase', ?, ?, ?, ?, 
                                    (SELECT price_per_liter FROM fuel_types WHERE id = ?), 
                                    ? * (SELECT price_per_liter FROM fuel_types WHERE id = ?), 
                                    NOW(), ?, 'Initial tank setup')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("iiiidddi", 
                    $branch_id, 
                    $tank_id, 
                    $fuel_type_id,
                    $current_quantity,
                    $fuel_type_id,
                    $current_quantity,
                    $fuel_type_id,
                    $_SESSION['user_id']
                );
                $log_stmt->execute();
                
                $success = "Tank added successfully!";
                
                // Redirect to tanks page after 2 seconds
                echo '<meta http-equiv="refresh" content="2;url=tanks.php">';
            } else {
                $error = "Error adding tank: " . $conn->error;
            }
        }
    }
}

// Get branches and fuel types for dropdowns
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active'";
$branches = $conn->query($branches_sql);

$fuel_types_sql = "SELECT id, fuel_name, fuel_code FROM fuel_types WHERE status = 'active'";
$fuel_types = $conn->query($fuel_types_sql);
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus"></i> Add New Fuel Tank
                </h5>
                <a href="tanks.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tanks
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <p>Redirecting to tanks page...</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="" onsubmit="return validateTankForm()">
                                    <div class="mb-3">
                                        <label for="branch_id" class="form-label">Branch *</label>
                                        <select class="form-control" id="branch_id" name="branch_id" required>
                                            <option value="">Select Branch</option>
                                            <?php 
                                            while($branch = $branches->fetch_assoc()): ?>
                                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="fuel_type_id" class="form-label">Fuel Type *</label>
                                        <select class="form-control" id="fuel_type_id" name="fuel_type_id" required>
                                            <option value="">Select Fuel Type</option>
                                            <?php 
                                            $fuel_types->data_seek(0);
                                            while($fuel = $fuel_types->fetch_assoc()): ?>
                                                <option value="<?php echo $fuel['id']; ?>">
                                                    <?php echo htmlspecialchars($fuel['fuel_name']); ?> (<?php echo $fuel['fuel_code']; ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tank_number" class="form-label">Tank Number *</label>
                                        <input type="text" class="form-control" id="tank_number" name="tank_number" 
                                               required placeholder="e.g., TANK-001" pattern="[A-Za-z0-9\-_]+"
                                               title="Alphanumeric characters, hyphens, and underscores only">
                                        <div class="form-text">Unique identifier for this tank in the branch</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="capacity" class="form-label">Capacity (Liters) *</label>
                                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                                   step="0.01" min="100" required onchange="validateQuantity()">
                                            <div class="form-text">Maximum storage capacity</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="current_quantity" class="form-label">Initial Quantity (Liters) *</label>
                                            <input type="number" class="form-control" id="current_quantity" name="current_quantity" 
                                                   step="0.01" min="0" required onchange="validateQuantity()">
                                            <div class="form-text">Current fuel in tank</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="minimum_threshold" class="form-label">Minimum Threshold (Liters)</label>
                                        <input type="number" class="form-control" id="minimum_threshold" name="minimum_threshold" 
                                               step="0.01" min="0" value="100">
                                        <div class="form-text">Alert when quantity falls below this level</div>
                                    </div>
                                    
                                    <div class="alert alert-info py-2">
                                        <small>
                                            <i class="fas fa-info-circle"></i> 
                                            The initial quantity will be recorded as a purchase transaction.
                                        </small>
                                    </div>
                                    
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="tanks.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" name="add_tank" class="btn btn-primary">Add Tank</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validate tank form
function validateTankForm() {
    var capacity = parseFloat(document.getElementById('capacity').value);
    var currentQuantity = parseFloat(document.getElementById('current_quantity').value);
    
    if (currentQuantity > capacity) {
        alert('Error: Initial quantity cannot exceed tank capacity!');
        return false;
    }
    
    return true;
}

// Validate quantity when capacity or current quantity changes
function validateQuantity() {
    var capacity = parseFloat(document.getElementById('capacity').value) || 0;
    var currentQuantity = parseFloat(document.getElementById('current_quantity').value) || 0;
    
    if (currentQuantity > capacity) {
        document.getElementById('current_quantity').classList.add('is-invalid');
        if (document.getElementById('current_quantity').nextElementSibling) {
            document.getElementById('current_quantity').nextElementSibling.innerHTML = 
                'Quantity cannot exceed capacity of ' + capacity.toFixed(2) + ' L';
        }
    } else {
        document.getElementById('current_quantity').classList.remove('is-invalid');
        if (document.getElementById('current_quantity').nextElementSibling) {
            document.getElementById('current_quantity').nextElementSibling.innerHTML = 
                'Current fuel in tank';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    validateQuantity();
});
</script>

<?php
require_once '../includes/footer.php';
?>