<?php
require_once '../includes/header.php';

// Handle pump update actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {    
    if (isset($_POST['update_pump'])) {
        $pump_id = intval($_POST['pump_id']);
        $current_reading = floatval($_POST['current_reading']);
        
        // Get current reading before update
        $get_sql = "SELECT current_reading, branch_id, tank_id FROM pumps WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $pump_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        
        if ($get_result->num_rows > 0) {
            $pump_data = $get_result->fetch_assoc();
            
            $sql = "UPDATE pumps 
                    SET last_reading = ?, 
                        current_reading = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ddi", $pump_data['current_reading'], $current_reading, $pump_id);
            
            if ($stmt->execute()) {
                $success = "Pump reading updated successfully!";
            } else {
                $error = "Error updating pump reading: " . $conn->error;
            }
        }
    }
    
    if (isset($_POST['update_pump_status'])) {
        $pump_id = intval($_POST['pump_id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE pumps SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $pump_id);
        
        if ($stmt->execute()) {
            $success = "Pump status updated successfully!";
        } else {
            $error = "Error updating pump status: " . $conn->error;
        }
    }
}

// Get all pumps with details
$pumps_sql = "SELECT p.*, 
                     b.branch_name, 
                     t.tank_number, 
                     t.capacity as tank_capacity,
                     t.current_quantity as tank_current,
                     f.fuel_name,
                     f.fuel_code
              FROM pumps p
              LEFT JOIN branches b ON p.branch_id = b.id
              LEFT JOIN tanks t ON p.tank_id = t.id
              LEFT JOIN fuel_types f ON t.fuel_type_id = f.id
              ORDER BY p.branch_id, p.pump_number";
$pumps = $conn->query($pumps_sql);

// Get total count for stats
$total_pumps = $pumps->num_rows;
$pumps->data_seek(0); // Reset pointer

// Count by status
$status_counts = ['active' => 0, 'maintenance' => 0, 'inactive' => 0];
while($pump = $pumps->fetch_assoc()) {
    $status_counts[$pump['status']] = ($status_counts[$pump['status']] ?? 0) + 1;
}
$pumps->data_seek(0); // Reset pointer again
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-gas-pump"></i> Manage Pumps
                </h5>
                <a href="add_pump.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Pump
                </a>
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
                
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Pumps</h6>
                                        <h2 class="mb-0"><?php echo $total_pumps; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-gas-pump fa-2x"></i>
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
                                        <h6 class="card-title">Active Pumps</h6>
                                        <h2 class="mb-0"><?php echo $status_counts['active']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
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
                                        <h6 class="card-title">Maintenance</h6>
                                        <h2 class="mb-0"><?php echo $status_counts['maintenance']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tools fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Inactive</h6>
                                        <h2 class="mb-0"><?php echo $status_counts['inactive']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-ban fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pumps Overview -->
                <div class="row mb-4">
                    <?php if ($total_pumps > 0): ?>
                        <?php while($pump = $pumps->fetch_assoc()): 
                            $status_class = $pump['status'] == 'active' ? 'bg-success' : 
                                          ($pump['status'] == 'maintenance' ? 'bg-warning' : 'bg-danger');
                            $tank_percentage = 0;
                            if ($pump['tank_capacity'] > 0) {
                                $tank_percentage = ($pump['tank_current'] / $pump['tank_capacity']) * 100;
                            }
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <div class="pump-icon mb-3">
                                        <i class="fas fa-gas-pump fa-3x 
                                            <?php echo $pump['status'] == 'active' ? 'text-success' : 
                                                  ($pump['status'] == 'maintenance' ? 'text-warning' : 'text-secondary'); ?>">
                                        </i>
                                    </div>
                                    
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($pump['pump_name']); ?></h5>
                                    <p class="text-muted small mb-2">No: <?php echo htmlspecialchars($pump['pump_number']); ?></p>
                                    
                                    <div class="mb-3">
                                        <span class="badge <?php echo $status_class; ?> mb-2">
                                            <?php echo ucfirst($pump['status']); ?>
                                        </span>
                                        <p class="mb-1 small">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($pump['branch_name']); ?>
                                        </p>
                                        <p class="mb-1 small">
                                            <i class="fas fa-oil-can"></i> <?php echo htmlspecialchars($pump['fuel_name']); ?> (<?php echo $pump['fuel_code']; ?>)
                                        </p>
                                        <p class="mb-1 small">
                                            <i class="fas fa-tachometer-alt"></i> 
                                            Reading: <?php echo number_format($pump['current_reading'], 2); ?> L
                                        </p>
                                        <?php if ($pump['tank_capacity'] > 0): ?>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar 
                                                <?php echo $tank_percentage < 20 ? 'bg-danger' : 
                                                      ($tank_percentage < 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 style="width: <?php echo $tank_percentage; ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            Tank: <?php echo number_format($pump['tank_current'], 2); ?>L / <?php echo number_format($pump['tank_capacity'], 2); ?>L
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updateReadingModal<?php echo $pump['id']; ?>"
                                                title="Update Reading">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPumpModal<?php echo $pump['id']; ?>"
                                                title="Edit Pump">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Reading Modal -->
                        <div class="modal fade" id="updateReadingModal<?php echo $pump['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Pump Reading</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <p>
                                                Pump: <strong><?php echo htmlspecialchars($pump['pump_name']); ?></strong><br>
                                                Number: <?php echo htmlspecialchars($pump['pump_number']); ?><br>
                                                Fuel: <?php echo htmlspecialchars($pump['fuel_name']); ?><br>
                                                Current Reading: <?php echo number_format($pump['current_reading'], 2); ?> L
                                            </p>
                                            
                                            <div class="mb-3">
                                                <label for="current_reading_<?php echo $pump['id']; ?>" class="form-label">New Reading (Liters)</label>
                                                <input type="number" class="form-control" 
                                                       id="current_reading_<?php echo $pump['id']; ?>" 
                                                       name="current_reading" step="0.01" min="0" 
                                                       value="<?php echo $pump['current_reading']; ?>" required>
                                                <div class="form-text">Enter the current meter reading from the pump</div>
                                            </div>
                                            
                                            <input type="hidden" name="pump_id" value="<?php echo $pump['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_pump" class="btn btn-primary">Update Reading</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Pump Modal -->
                        <div class="modal fade" id="editPumpModal<?php echo $pump['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Pump</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-control" name="status" required>
                                                    <option value="active" <?php echo $pump['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="maintenance" <?php echo $pump['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                    <option value="inactive" <?php echo $pump['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Current Reading (Liters)</label>
                                                <input type="number" class="form-control" name="current_reading" 
                                                       step="0.01" min="0" value="<?php echo $pump['current_reading']; ?>" required>
                                            </div>
                                            
                                            <input type="hidden" name="pump_id" value="<?php echo $pump['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_pump_status" class="btn btn-primary">Update Pump</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pumps found. <a href="add_pump.php">Add your first pump</a>.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detailed Pumps Table -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">All Pumps - Detailed View</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pump Details</th>
                                        <th>Branch</th>
                                        <th>Fuel</th>
                                        <th>Tank</th>
                                        <th>Readings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $pumps->data_seek(0);
                                    while($pump = $pumps->fetch_assoc()): 
                                        $tank_percentage = 0;
                                        if ($pump['tank_capacity'] > 0) {
                                            $tank_percentage = ($pump['tank_current'] / $pump['tank_capacity']) * 100;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $pump['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pump['pump_name']); ?></strong><br>
                                            <small class="text-muted">No: <?php echo htmlspecialchars($pump['pump_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($pump['branch_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($pump['fuel_name']); ?><br>
                                            <small class="text-muted"><?php echo $pump['fuel_code']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($pump['tank_number']); ?><br>
                                            <?php if ($pump['tank_capacity'] > 0): ?>
                                            <small class="text-muted">
                                                <?php echo number_format($pump['tank_current'], 2); ?>L / <?php echo number_format($pump['tank_capacity'], 2); ?>L
                                            </small>
                                            <div class="progress" style="height: 3px;">
                                                <div class="progress-bar 
                                                    <?php echo $tank_percentage < 20 ? 'bg-danger' : 
                                                          ($tank_percentage < 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                                     style="width: <?php echo $tank_percentage; ?>%">
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            Current: <strong><?php echo number_format($pump['current_reading'], 2); ?> L</strong><br>
                                            Last: <?php echo number_format($pump['last_reading'], 2); ?> L<br>
                                            <?php if ($pump['current_reading'] > $pump['last_reading']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-arrow-up"></i> 
                                                    <?php echo number_format($pump['current_reading'] - $pump['last_reading'], 2); ?> L
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($pump['status']) {
                                                    case 'active': echo 'success'; break;
                                                    case 'maintenance': echo 'warning'; break;
                                                    case 'inactive': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($pump['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateReadingModal<?php echo $pump['id']; ?>"
                                                        title="Update Reading">
                                                    <i class="fas fa-sync"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editPumpModal<?php echo $pump['id']; ?>"
                                                        title="Edit Pump">
                                                    <i class="fas fa-edit"></i>
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

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>