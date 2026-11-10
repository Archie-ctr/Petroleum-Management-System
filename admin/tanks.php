<?php
// tanks.php - Enhanced Tanks Management System
require_once '../includes/header.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Handle tank operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle refill operation
    if (isset($_POST['refill_tank'])) {
        $tank_id = intval($_POST['tank_id']);
        $refill_quantity = floatval($_POST['refill_quantity']);
        
        // Get tank details
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
                
                $success = "Tank refilled successfully! Added " . number_format($refill_quantity, 2) . " liters of " . 
                           $tank_info['fuel_name'] . " to Tank " . $tank_info['tank_number'] . " at " . 
                           $tank_info['branch_name'];
            } else {
                $error = "Error refilling tank: " . $conn->error;
            }
        }
    }
    
    // Handle status change
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
    
    // Handle tank edit
    if (isset($_POST['edit_tank'])) {
        $tank_id = intval($_POST['tank_id']);
        $tank_number = $conn->real_escape_string($_POST['tank_number']);
        $capacity = floatval($_POST['capacity']);
        $current_quantity = floatval($_POST['current_quantity']);
        $minimum_threshold = floatval($_POST['minimum_threshold']);
        
        if ($current_quantity > $capacity) {
            $error = "Error: Current quantity cannot exceed capacity!";
        } else {
            $sql = "UPDATE tanks SET 
                    tank_number = ?, 
                    capacity = ?, 
                    current_quantity = ?, 
                    minimum_threshold = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdddi", $tank_number, $capacity, $current_quantity, $minimum_threshold, $tank_id);
            
            if ($stmt->execute()) {
                $success = "Tank updated successfully!";
            } else {
                $error = "Error updating tank: " . $conn->error;
            }
        }
    }
}

// Handle delete via GET
if (isset($_GET['delete'])) {
    $tank_id = intval($_GET['delete']);
    
    // Check if tank has connected pumps
    $check_pumps = $conn->query("SELECT COUNT(*) as pump_count FROM pumps WHERE tank_id = $tank_id");
    $pump_data = $check_pumps->fetch_assoc();
    
    if ($pump_data['pump_count'] > 0) {
        $error = "Cannot delete tank. It has " . $pump_data['pump_count'] . " connected pumps.";
    } else {
        $sql = "DELETE FROM tanks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tank_id);
        
        if ($stmt->execute()) {
            $success = "Tank deleted successfully!";
        } else {
            $error = "Error deleting tank: " . $conn->error;
        }
    }
}

// Get filter parameters
$filter_branch = isset($_GET['branch']) ? intval($_GET['branch']) : 0;
$filter_fuel = isset($_GET['fuel']) ? intval($_GET['fuel']) : 0;
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build filter conditions
$filter_conditions = [];
if ($filter_branch > 0) {
    $filter_conditions[] = "t.branch_id = $filter_branch";
}
if ($filter_fuel > 0) {
    $filter_conditions[] = "t.fuel_type_id = $filter_fuel";
}
if (!empty($filter_status) && in_array($filter_status, ['active', 'maintenance', 'inactive'])) {
    $filter_conditions[] = "t.status = '$filter_status'";
}
$filter_sql = !empty($filter_conditions) ? 'WHERE ' . implode(' AND ', $filter_conditions) : '';

// Get all tanks with details
$tanks_sql = "SELECT t.*, b.branch_name, f.fuel_name, f.fuel_code, f.price_per_liter,
                     ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage,
                     (SELECT COUNT(p.id) FROM pumps p WHERE p.tank_id = t.id) as pump_count,
                     (t.current_quantity * f.price_per_liter) as total_value
              FROM tanks t
              LEFT JOIN branches b ON t.branch_id = b.id
              LEFT JOIN fuel_types f ON t.fuel_type_id = f.id
              $filter_sql
              ORDER BY t.branch_id, t.tank_number";
$tanks = $conn->query($tanks_sql);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_tanks,
                SUM(capacity) as total_capacity,
                SUM(current_quantity) as total_current,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tanks,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_tanks,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_tanks,
                COUNT(CASE WHEN (current_quantity / capacity) * 100 < 20 THEN 1 END) as low_tanks,
                COUNT(CASE WHEN (current_quantity / capacity) * 100 > 80 THEN 1 END) as full_tanks,
                COUNT(CASE WHEN current_quantity < minimum_threshold THEN 1 END) as below_threshold
              FROM tanks";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$overall_percentage = $stats['total_capacity'] > 0 ? 
    round(($stats['total_current'] / $stats['total_capacity']) * 100, 2) : 0;

// Get branches and fuel types for filters
$branches = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
$fuel_types = $conn->query("SELECT id, fuel_name, fuel_code FROM fuel_types WHERE status = 'active' ORDER BY fuel_name");
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-oil-can"></i> Manage Fuel Tanks
                </h5>
                <div>
                    <a href="add_tank.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Tank
                    </a>
                    <button class="btn btn-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Display Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Branch</label>
                                <select class="form-control" name="branch" onchange="this.form.submit()">
                                    <option value="">All Branches</option>
                                    <?php while($branch = $branches->fetch_assoc()): ?>
                                        <option value="<?php echo $branch['id']; ?>" <?php echo $filter_branch == $branch['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fuel Type</label>
                                <select class="form-control" name="fuel" onchange="this.form.submit()">
                                    <option value="">All Fuel Types</option>
                                    <?php 
                                    $fuel_types->data_seek(0);
                                    while($fuel = $fuel_types->fetch_assoc()): ?>
                                        <option value="<?php echo $fuel['id']; ?>" <?php echo $filter_fuel == $fuel['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fuel['fuel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="maintenance" <?php echo $filter_status == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="tanks.php" class="btn btn-secondary w-100">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
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
                                        <h2 class="mb-0"><?php echo $stats['below_threshold']; ?></h2>
                                        <small>Below threshold</small>
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
                
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="add_tank.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Tank
                            </a>
                            <a href="tank_transactions.php" class="btn btn-info">
                                <i class="fas fa-exchange-alt"></i> View Transactions
                            </a>
                            <a href="tank_reports.php" class="btn btn-success">
                                <i class="fas fa-chart-bar"></i> Generate Reports
                            </a>
                            <button class="btn btn-warning" onclick="printTankReport()">
                                <i class="fas fa-print"></i> Print Report
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tanks Grid View -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">Tanks Overview</h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" onclick="showGridView()">
                                <i class="fas fa-th"></i> Grid
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showListView()">
                                <i class="fas fa-list"></i> List
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="gridView">
                        <div class="row">
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
                                    $available_space = $tank['capacity'] - $tank['current_quantity'];
                                    $total_value = $tank['current_quantity'] * $tank['price_per_liter'];
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 <?php echo $low_stock ? 'border-danger' : ''; ?>">
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
                                            
                                            <div class="mb-2">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-dollar-sign"></i> Value: $<?php echo number_format($total_value, 2); ?>
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-arrows-alt-v"></i> Available: <?php echo number_format($available_space, 2); ?> L
                                                </small>
                                            </div>
                                            
                                            <?php if ($low_stock): ?>
                                                <div class="alert alert-warning py-1 mb-2">
                                                    <small><i class="fas fa-exclamation-triangle"></i> 
                                                        Below threshold (<?php echo number_format($tank['minimum_threshold'], 2); ?> L)
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
                                                        onclick="confirmDelete(<?php echo $tank['id']; ?>, '<?php echo htmlspecialchars($tank['tank_number']); ?>')"
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
                                                    <div class="alert alert-info">
                                                        <strong>Tank Details:</strong><br>
                                                        Tank: <strong><?php echo htmlspecialchars($tank['tank_number']); ?></strong><br>
                                                        Fuel: <?php echo htmlspecialchars($tank['fuel_name']); ?> (<?php echo $tank['fuel_code']; ?>)<br>
                                                        Price: $<?php echo number_format($tank['price_per_liter'], 2); ?>/L<br>
                                                        Current: <?php echo number_format($tank['current_quantity'], 2); ?> L<br>
                                                        Capacity: <?php echo number_format($tank['capacity'], 2); ?> L<br>
                                                        Available: <?php echo number_format($available_space, 2); ?> L
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="refill_quantity_<?php echo $tank['id']; ?>" class="form-label">Refill Quantity (Liters) *</label>
                                                        <input type="number" class="form-control" 
                                                               id="refill_quantity_<?php echo $tank['id']; ?>" 
                                                               name="refill_quantity" step="0.01" min="0.01" 
                                                               max="<?php echo number_format($available_space, 2); ?>" 
                                                               required oninput="calculateRefillCost(this.value, <?php echo $tank['price_per_liter']; ?>, <?php echo $tank['id']; ?>)">
                                                        <div class="form-text">
                                                            Available space: <?php echo number_format($available_space, 2); ?> L
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Estimated Cost</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="text" class="form-control" 
                                                                   id="estimated_cost_<?php echo $tank['id']; ?>" 
                                                                   value="0.00" readonly>
                                                        </div>
                                                    </div>
                                                    
                                                    <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
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
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="edit_tank" class="btn btn-primary">Update Tank</button>
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
                                                    <div class="alert alert-info">
                                                        <strong>Tank:</strong> <?php echo htmlspecialchars($tank['tank_number']); ?><br>
                                                        <strong>Fuel:</strong> <?php echo htmlspecialchars($tank['fuel_name']); ?><br>
                                                        <strong>Current Status:</strong> 
                                                        <span class="badge bg-<?php 
                                                            switch($tank['status']) {
                                                                case 'active': echo 'success'; break;
                                                                case 'maintenance': echo 'warning'; break;
                                                                case 'inactive': echo 'danger'; break;
                                                            }
                                                        ?>"><?php echo ucfirst($tank['status']); ?></span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">New Status</label>
                                                        <select class="form-control" name="status" required>
                                                            <option value="active" <?php echo $tank['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="maintenance" <?php echo $tank['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                            <option value="inactive" <?php echo $tank['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_tank_status" class="btn btn-warning">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                                        <h5>No tanks found</h5>
                                        <p class="mb-0"><?php echo !empty($filter_conditions) ? 'Try changing your filters.' : 'Add your first tank to get started.'; ?></p>
                                        <?php if (empty($filter_conditions)): ?>
                                            <a href="add_tank.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus"></i> Add First Tank
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- List View (Hidden by default) -->
                    <div class="card-body d-none" id="listView">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tanksTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tank Number</th>
                                        <th>Branch</th>
                                        <th>Fuel Type</th>
                                        <th>Capacity</th>
                                        <th>Current</th>
                                        <th>Fill %</th>
                                        <th>Value</th>
                                        <th>Status</th>
                                        <th>Pumps</th>
                                        <th>Last Refill</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $tanks->data_seek(0);
                                    while($tank = $tanks->fetch_assoc()): 
                                        $percentage = $tank['percentage'] ?? 0;
                                        $total_value = $tank['current_quantity'] * $tank['price_per_liter'];
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
                                            <strong>$<?php echo number_format($total_value, 2); ?></strong><br>
                                            <small class="text-muted">@ $<?php echo number_format($tank['price_per_liter'], 2); ?>/L</small>
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
                                        <td class="text-center">
                                            <?php if ($tank['pump_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $tank['pump_count']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $tank['last_refill'] ? 
                                                date('M d, Y H:i', strtotime($tank['last_refill'])) : 
                                                'Never';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#refillModal<?php echo $tank['id']; ?>"
                                                        title="Refill">
                                                    <i class="fas fa-gas-pump"></i>
                                                </button>
                                                <button type="button" class="btn btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editTankModal<?php echo $tank['id']; ?>"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $tank['id']; ?>"
                                                        title="Status">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger"
                                                        onclick="confirmDelete(<?php echo $tank['id']; ?>, '<?php echo htmlspecialchars($tank['tank_number']); ?>')"
                                                        title="Delete">
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
                
                <!-- Summary -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Tank Status Distribution</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="text-center">
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; margin: 0 auto;">
                                            <span class="h5 mb-0"><?php echo $stats['active_tanks']; ?></span>
                                        </div>
                                        <small class="d-block mt-1">Active</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; margin: 0 auto;">
                                            <span class="h5 mb-0"><?php echo $stats['maintenance_tanks']; ?></span>
                                        </div>
                                        <small class="d-block mt-1">Maintenance</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; margin: 0 auto;">
                                            <span class="h5 mb-0"><?php echo $stats['inactive_tanks']; ?></span>
                                        </div>
                                        <small class="d-block mt-1">Inactive</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Quick Stats</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Active Tanks: <?php echo $stats['active_tanks']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        Low Tanks (Below 20%): <?php echo $stats['low_tanks']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                        Tanks Below Threshold: <?php echo $stats['below_threshold']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Full Tanks (Above 80%): <?php echo $stats['full_tanks']; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// View switching
function showGridView() {
    document.getElementById('gridView').classList.remove('d-none');
    document.getElementById('listView').classList.add('d-none');
    document.querySelectorAll('.btn-group .btn')[0].classList.add('active');
    document.querySelectorAll('.btn-group .btn')[1].classList.remove('active');
}

function showListView() {
    document.getElementById('gridView').classList.add('d-none');
    document.getElementById('listView').classList.remove('d-none');
    document.querySelectorAll('.btn-group .btn')[0].classList.remove('active');
    document.querySelectorAll('.btn-group .btn')[1].classList.add('active');
}

// Calculate refill cost
function calculateRefillCost(quantity, pricePerLiter, tankId) {
    if (quantity && pricePerLiter) {
        const cost = parseFloat(quantity) * parseFloat(pricePerLiter);
        document.getElementById('estimated_cost_' + tankId).value = cost.toFixed(2);
    }
}

// Confirm delete
function confirmDelete(tankId, tankName) {
    if (confirm('Are you sure you want to delete tank "' + tankName + '"?\n\nThis action cannot be undone!')) {
        window.location.href = 'tanks.php?delete=' + tankId;
    }
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('tanksTable');
    const html = table.outerHTML;
    
    // Create a blob and download link
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tanks-report-' + new Date().toISOString().split('T')[0] + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Print report
function printTankReport() {
    const printContent = document.getElementById('listView').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Tank Inventory Report</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .progress { height: 5px; background: #eee; }
                    .progress-bar { height: 100%; }
                </style>
            </head>
            <body>
                <h2>Tank Inventory Report</h2>
                <p>Generated on: ${new Date().toLocaleDateString()}</p>
                ${printContent}
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}

// Initialize DataTable for list view
document.addEventListener('DOMContentLoaded', function() {
    $('#tanksTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>tip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search tanks..."
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>