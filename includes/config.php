<?php
require_once '../includes/header.php';

// Handle tank actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_tank'])) {
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
                } else {
                    $error = "Error adding tank: " . $conn->error;
                }
            }
        }
    }
    
    if (isset($_POST['refill_tank'])) {
        $tank_id = intval($_POST['tank_id']);
        $refill_quantity = floatval($_POST['refill_quantity']);
        
        // Get tank details first
        $tank_sql = "SELECT t.*, f.fuel_name, f.price_per_liter, b.branch_name 
                     FROM tanks t 
                     JOIN fuel_types f ON t.fuel_type_id = f.id 
                     JOIN branches b ON t.branch_id = b.id 
                     WHERE t.id = ?";
        $tank_stmt = $conn->prepare($tank_sql);
        $tank_stmt->bind_param("i", $tank_id);
        $tank_stmt->execute();
        $tank_info = $tank_stmt->get_result()->fetch_assoc();
        
        if (!$tank_info) {
            $error = "Error: Tank not found!";
        } elseif ($tank_info['current_quantity'] + $refill_quantity > $tank_info['capacity']) {
            $error = "Error: Refill quantity would exceed tank capacity! Maximum: " . 
                     number_format($tank_info['capacity'] - $tank_info['current_quantity'], 2) . " L";
        } else {
            // Update tank quantity
            $sql = "UPDATE tanks 
                    SET current_quantity = current_quantity + ?, 
                        last_refill = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $refill_quantity, $tank_id);
            
            if ($stmt->execute()) {
                // Record inventory transaction
                $transaction_sql = "INSERT INTO inventory_transactions 
                                    (transaction_type, branch_id, tank_id, fuel_type_id, 
                                     quantity, unit_price, total_value, transaction_date, performed_by, notes) 
                                    VALUES ('purchase', ?, ?, ?, ?, ?, ?, NOW(), ?, 'Tank refill')";
                $transaction_stmt = $conn->prepare($transaction_sql);
                $total_value = $refill_quantity * $tank_info['price_per_liter'];
                $transaction_stmt->bind_param("iiiiddi", 
                    $tank_info['branch_id'], 
                    $tank_id, 
                    $tank_info['fuel_type_id'],
                    $refill_quantity,
                    $tank_info['price_per_liter'],
                    $total_value,
                    $_SESSION['user_id']
                );
                $transaction_stmt->execute();
                
                // Also log to inventory_logs
                $log_sql = "INSERT INTO inventory_logs 
                            (branch_id, tank_id, fuel_type_id, transaction_type, quantity, 
                             previous_quantity, new_quantity, unit_price, total_value, 
                             reference_type, performed_by, notes) 
                            VALUES (?, ?, ?, 'refill', ?, ?, ?, ?, ?, 'purchase', ?, 'Tank refill')";
                $log_stmt = $conn->prepare($log_sql);
                $new_quantity = $tank_info['current_quantity'] + $refill_quantity;
                $log_total_value = $refill_quantity * $tank_info['price_per_liter'];
                $log_stmt->bind_param("iiidddddi", 
                    $tank_info['branch_id'], 
                    $tank_id, 
                    $tank_info['fuel_type_id'],
                    $refill_quantity,
                    $tank_info['current_quantity'],
                    $new_quantity,
                    $tank_info['price_per_liter'],
                    $log_total_value,
                    $_SESSION['user_id']
                );
                $log_stmt->execute();
                
                $success = "Tank refilled successfully! Added " . number_format($refill_quantity, 2) . " liters of " . 
                           $tank_info['fuel_name'] . " to Tank " . $tank_info['tank_number'] . " at " . 
                           $tank_info['branch_name'];
            } else {
                $error = "Error refilling tank: " . $conn->error;
            }
        }
    }
    
    if (isset($_POST['update_tank_status'])) {
        $tank_id = intval($_POST['tank_id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE tanks SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $tank_id);
        
        if ($stmt->execute()) {
            $success = "Tank status updated successfully!";
        } else {
            $error = "Error updating tank status: " . $conn->error;
        }
    }
}

// Get all tanks with details
$tanks_sql = "SELECT t.*, b.branch_name, f.fuel_name, f.fuel_code,
                     ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage,
                     (SELECT COUNT(p.id) FROM pumps p WHERE p.tank_id = t.id) as pump_count
              FROM tanks t
              LEFT JOIN branches b ON t.branch_id = b.id
              LEFT JOIN fuel_types f ON t.fuel_type_id = f.id
              ORDER BY t.branch_id, t.tank_number";
$tanks = $conn->query($tanks_sql);

// Get branches and fuel types for dropdowns
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active'";
$branches = $conn->query($branches_sql);

$fuel_types_sql = "SELECT id, fuel_name, fuel_code FROM fuel_types WHERE status = 'active'";
$fuel_types = $conn->query($fuel_types_sql);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_tanks,
                SUM(capacity) as total_capacity,
                SUM(current_quantity) as total_current,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tanks,
                COUNT(CASE WHEN (current_quantity / capacity) * 100 < 20 THEN 1 END) as low_tanks,
                COUNT(CASE WHEN (current_quantity / capacity) * 100 > 80 THEN 1 END) as full_tanks
              FROM tanks";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$overall_percentage = $stats['total_capacity'] > 0 ? 
    round(($stats['total_current'] / $stats['total_capacity']) * 100, 2) : 0;
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-oil-can"></i> Manage Fuel Tanks
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTankModal">
                    <i class="fas fa-plus"></i> Add New Tank
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Tanks</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_tanks']; ?></h2>
                                        <small><?php echo $stats['active_tanks']; ?> active</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-oil-can fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Capacity</h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['total_capacity'], 0); ?> L</h2>
                                        <small><?php echo number_format($stats['total_current'], 0); ?> L current</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-weight fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Overall Fill</h6>
                                        <h2 class="mb-0"><?php echo $overall_percentage; ?>%</h2>
                                        <small><?php echo $stats['full_tanks']; ?> full tanks</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-percentage fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Low Tanks</h6>
                                        <h2 class="mb-0"><?php echo $stats['low_tanks']; ?></h2>
                                        <small>Below 20% capacity</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Progress -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Overall Inventory Status</h6>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar 
                                <?php echo $overall_percentage > 50 ? 'bg-success' : 
                                      ($overall_percentage > 20 ? 'bg-warning' : 'bg-danger'); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $overall_percentage; ?>%">
                                <?php echo $overall_percentage; ?>%
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small>0 L</small>
                            <small><?php echo number_format($stats['total_current'], 0); ?> L / <?php echo number_format($stats['total_capacity'], 0); ?> L</small>
                            <small><?php echo number_format($stats['total_capacity'], 0); ?> L</small>
                        </div>
                    </div>
                </div>
                
                <!-- Tanks Overview -->
                <div class="row mb-4">
                    <?php if ($tanks->num_rows > 0): ?>
                        <?php while($tank = $tanks->fetch_assoc()): 
                            $percentage = $tank['percentage'] ?? 0;
                            $status_class = '';
                            $status_text = '';
                            
                            if ($tank['status'] == 'maintenance') {
                                $status_class = 'bg-warning';
                                $status_text = 'Maintenance';
                            } elseif ($tank['status'] == 'inactive') {
                                $status_class = 'bg-danger';
                                $status_text = 'Inactive';
                            } elseif ($percentage > 80) {
                                $status_class = 'bg-success';
                                $status_text = 'Full';
                            } elseif ($percentage > 50) {
                                $status_class = 'bg-success';
                                $status_text = 'Good';
                            } elseif ($percentage > 20) {
                                $status_class = 'bg-info';
                                $status_text = 'Moderate';
                            } else {
                                $status_class = 'bg-danger';
                                $status_text = 'Low';
                            }
                            
                            $low_stock = $tank['current_quantity'] < $tank['minimum_threshold'];
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card <?php echo $low_stock ? 'border-danger' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="card-title mb-0">
                                                <?php echo htmlspecialchars($tank['fuel_name']); ?>
                                                <small class="text-muted">(Tank <?php echo htmlspecialchars($tank['tank_number']); ?>)</small>
                                            </h6>
                                            <p class="mb-0 small">
                                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($tank['branch_name']); ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $status_class; ?> mb-1">
                                                <?php echo $status_text; ?>
                                            </span>
                                            <?php if ($low_stock): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-circle"></i> Low Stock</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar <?php 
                                            echo $percentage > 50 ? 'bg-success' : 
                                                 ($percentage > 20 ? 'bg-warning' : 'bg-danger'); 
                                        ?>" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </div>
                                    </div>
                                    
                                    <div class="row text-center mb-2">
                                        <div class="col-4">
                                            <small class="text-muted">Current</small><br>
                                            <strong><?php echo number_format($tank['current_quantity'], 2); ?> L</strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Capacity</small><br>
                                            <strong><?php echo number_format($tank['capacity'], 2); ?> L</strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Pumps</small><br>
                                            <strong><?php echo $tank['pump_count']; ?></strong>
                                        </div>
                                    </div>
                                    
                                    <?php if ($low_stock): ?>
                                        <div class="alert alert-warning py-1 mb-2">
                                            <small><i class="fas fa-exclamation-triangle"></i> 
                                                Below minimum threshold (<?php echo number_format($tank['minimum_threshold'], 2); ?> L)
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#refillModal<?php echo $tank['id']; ?>"
                                                title="Refill Tank">
                                            <i class="fas fa-gas-pump"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editTankModal<?php echo $tank['id']; ?>"
                                                title="Edit Tank">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#statusModal<?php echo $tank['id']; ?>"
                                                title="Change Status">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteTankModal<?php echo $tank['id']; ?>"
                                                title="Delete Tank">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Refill Modal -->
                        <div class="modal fade" id="refillModal<?php echo $tank['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Refill Tank</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <p>
                                                Tank: <strong><?php echo htmlspecialchars($tank['tank_number']); ?></strong><br>
                                                Fuel: <?php echo htmlspecialchars($tank['fuel_name']); ?> (<?php echo $tank['fuel_code']; ?>)<br>
                                                Current: <?php echo number_format($tank['current_quantity'], 2); ?> L<br>
                                                Capacity: <?php echo number_format($tank['capacity'], 2); ?> L
                                            </p>
                                            
                                            <div class="mb-3">
                                                <label for="refill_quantity_<?php echo $tank['id']; ?>" class="form-label">Refill Quantity (Liters) *</label>
                                                <input type="number" class="form-control" 
                                                       id="refill_quantity_<?php echo $tank['id']; ?>" 
                                                       name="refill_quantity" step="0.01" min="0.01" 
                                                       max="<?php echo number_format($tank['capacity'] - $tank['current_quantity'], 2); ?>" 
                                                       required>
                                                <div class="form-text">
                                                    Available space: <?php echo number_format($tank['capacity'] - $tank['current_quantity'], 2); ?> L
                                                </div>
                                            </div>
                                            
                                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="refill_tank" class="btn btn-success">Refill Tank</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Tank Modal -->
                        <div class="modal fade" id="editTankModal<?php echo $tank['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Tank</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Tank Number</label>
                                                <input type="text" class="form-control" name="tank_number" 
                                                       value="<?php echo htmlspecialchars($tank['tank_number']); ?>" required>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Capacity (Liters)</label>
                                                    <input type="number" class="form-control" name="capacity" 
                                                           step="0.01" min="<?php echo $tank['current_quantity']; ?>" 
                                                           value="<?php echo $tank['capacity']; ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Current Quantity (Liters)</label>
                                                    <input type="number" class="form-control" name="current_quantity" 
                                                           step="0.01" min="0" max="<?php echo $tank['capacity']; ?>"
                                                           value="<?php echo $tank['current_quantity']; ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Minimum Threshold (Liters)</label>
                                                <input type="number" class="form-control" name="minimum_threshold" 
                                                       step="0.01" min="0" value="<?php echo $tank['minimum_threshold']; ?>">
                                            </div>
                                            
                                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_tank" class="btn btn-primary">Update Tank</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Change Modal -->
                        <div class="modal fade" id="statusModal<?php echo $tank['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Change Tank Status</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <p>
                                                Tank: <strong><?php echo htmlspecialchars($tank['tank_number']); ?></strong><br>
                                                Fuel: <?php echo htmlspecialchars($tank['fuel_name']); ?><br>
                                                Current Status: <span class="badge bg-<?php 
                                                    switch($tank['status']) {
                                                        case 'active': echo 'success'; break;
                                                        case 'maintenance': echo 'warning'; break;
                                                        case 'inactive': echo 'danger'; break;
                                                    }
                                                ?>"><?php echo ucfirst($tank['status']); ?></span>
                                            </p>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">New Status</label>
                                                <select class="form-control" name="status" required>
                                                    <option value="active" <?php echo $tank['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="maintenance" <?php echo $tank['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                    <option value="inactive" <?php echo $tank['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Notes (Optional)</label>
                                                <textarea class="form-control" name="notes" rows="2"></textarea>
                                            </div>
                                            
                                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_tank_status" class="btn btn-warning">Update Status</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Confirmation Modal -->
                        <div class="modal fade" id="deleteTankModal<?php echo $tank['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-danger">Delete Tank</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this tank?</p>
                                        <p><strong><?php echo htmlspecialchars($tank['tank_number']); ?></strong> - <?php echo htmlspecialchars($tank['fuel_name']); ?></p>
                                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Warning: This tank has <?php echo $tank['pump_count']; ?> connected pumps.</p>
                                        <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <a href="delete_tank.php?id=<?php echo $tank['id']; ?>" class="btn btn-danger">Delete</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tanks found. Add your first tank using the "Add New Tank" button.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detailed Tanks Table -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">All Tanks - Detailed View</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tank Number</th>
                                        <th>Branch</th>
                                        <th>Fuel Type</th>
                                        <th>Capacity</th>
                                        <th>Current</th>
                                        <th>Status</th>
                                        <th>Last Refill</th>
                                        <th>Pumps</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $tanks->data_seek(0);
                                    while($tank = $tanks->fetch_assoc()): 
                                        $percentage = $tank['percentage'] ?? 0;
                                    ?>
                                    <tr class="<?php echo $tank['current_quantity'] < $tank['minimum_threshold'] ? 'table-warning' : ''; ?>">
                                        <td><?php echo $tank['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($tank['tank_number']); ?></strong>
                                            <?php if ($tank['current_quantity'] < $tank['minimum_threshold']): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-circle"></i> Low Stock</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($tank['branch_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($tank['fuel_name']); ?><br>
                                            <small class="text-muted"><?php echo $tank['fuel_code']; ?></small>
                                        </td>
                                        <td><?php echo number_format($tank['capacity'], 2); ?> L</td>
                                        <td>
                                            <strong><?php echo number_format($tank['current_quantity'], 2); ?> L</strong><br>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar 
                                                    <?php echo $percentage > 50 ? 'bg-success' : 
                                                         ($percentage > 20 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                     style="width: <?php echo $percentage; ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($tank['status']) {
                                                    case 'active': echo 'success'; break;
                                                    case 'maintenance': echo 'warning'; break;
                                                    case 'inactive': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($tank['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $tank['last_refill'] ? 
                                                date('M d, Y H:i', strtotime($tank['last_refill'])) : 
                                                'Never';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($tank['pump_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $tank['pump_count']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#refillModal<?php echo $tank['id']; ?>"
                                                        title="Refill Tank">
                                                    <i class="fas fa-gas-pump"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editTankModal<?php echo $tank['id']; ?>"
                                                        title="Edit Tank">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-warning"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $tank['id']; ?>"
                                                        title="Change Status">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteTankModal<?php echo $tank['id']; ?>"
                                                        title="Delete Tank">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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
    </div>
</div>

<!-- Add Tank Modal -->
<div class="modal fade" id="addTankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Fuel Tank</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return validateTankForm()">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="branch_id" class="form-label">Branch *</label>
                        <select class="form-control" id="branch_id" name="branch_id" required>
                            <option value="">Select Branch</option>
                            <?php 
                            $branches->data_seek(0);
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
                                <option value="<?php echo $fuel['id']; ?>" 
                                        data-price="<?php 
                                            // Get price from fuel_types table
                                            $price_sql = "SELECT price_per_liter FROM fuel_types WHERE id = " . $fuel['id'];
                                            $price_result = $conn->query($price_sql);
                                            echo $price_result->fetch_assoc()['price_per_liter'] ?? '0.00';
                                        ?>">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_tank" class="btn btn-primary">Add Tank</button>
                </div>
            </form>
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
        document.getElementById('current_quantity').nextElementSibling.innerHTML = 
            'Quantity cannot exceed capacity of ' + capacity.toFixed(2) + ' L';
    } else {
        document.getElementById('current_quantity').classList.remove('is-invalid');
        document.getElementById('current_quantity').nextElementSibling.innerHTML = 
            'Current fuel in tank';
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Validate quantity on page load
    validateQuantity();
});
</script>
<?php
require_once '../includes/footer.php';
?>