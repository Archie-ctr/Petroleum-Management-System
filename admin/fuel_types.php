<?php
require_once '../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_fuel'])) {
        $fuel_name = $conn->real_escape_string($_POST['fuel_name']);
        $fuel_code = $conn->real_escape_string($_POST['fuel_code']);
        $price_per_liter = floatval($_POST['price_per_liter']);
        $unit = $conn->real_escape_string($_POST['unit']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "INSERT INTO fuel_types (fuel_name, fuel_code, price_per_liter, unit, description, status) 
                VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdss", $fuel_name, $fuel_code, $price_per_liter, $unit, $description);
        
        if ($stmt->execute()) {
            $success = "Fuel type added successfully!";
        } else {
            $error = "Error adding fuel type: " . $conn->error;
        }
    }
}

// Get all fuel types
$fuel_types_sql = "SELECT * FROM fuel_types ORDER BY id DESC";
$fuel_types = $conn->query($fuel_types_sql);
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-oil-can"></i> Manage Fuel Types
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFuelModal">
                    <i class="fas fa-plus"></i> Add New Fuel Type
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fuel Name</th>
                                <th>Code</th>
                                <th>Price/Liter</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($fuel = $fuel_types->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $fuel['id']; ?></td>
                                <td>
                                    <strong><?php echo $fuel['fuel_name']; ?></strong><br>
                                    <small class="text-muted"><?php echo $fuel['description']; ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo $fuel['fuel_code']; ?></span></td>
                                <td>$<?php echo number_format($fuel['price_per_liter'], 2); ?></td>
                                <td><?php echo $fuel['unit']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $fuel['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($fuel['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning">
                                            <i class="fas fa-dollar-sign"></i>
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

<!-- Add Fuel Type Modal -->
<div class="modal fade" id="addFuelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Fuel Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="fuel_name" class="form-label">Fuel Name *</label>
                        <input type="text" class="form-control" id="fuel_name" name="fuel_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="fuel_code" class="form-label">Fuel Code *</label>
                        <input type="text" class="form-control" id="fuel_code" name="fuel_code" required maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="price_per_liter" class="form-label">Price per Liter *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price_per_liter" name="price_per_liter" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Unit *</label>
                        <select class="form-control" id="unit" name="unit" required>
                            <option value="Liter">Liter</option>
                            <option value="Gallon">Gallon</option>
                            <option value="Barrel">Barrel</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fuel" class="btn btn-primary">Add Fuel Type</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once '../includes/footer.php';
?>