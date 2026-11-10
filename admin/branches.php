<?php
require_once '../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_branch'])) {
        $branch_name = $conn->real_escape_string($_POST['branch_name']);
        $location = $conn->real_escape_string($_POST['location']);
        $address = $conn->real_escape_string($_POST['address']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $email = $conn->real_escape_string($_POST['email']);
        $manager_id = max(0, (int)($_POST['manager_id'] ?? 0));
        
        $sql = "INSERT INTO branches (branch_name, location, address, phone, email, manager_id, status)
                VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $branch_name, $location, $address, $phone, $email, $manager_id);
        
        try {
            if ($stmt->execute()) {
                $success = "Branch added successfully!";
            } else {
                $error = "Error adding branch: " . $stmt->error;
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Branch creation failed: ' . $exception->getMessage());
            $error = 'Unable to add branch. Verify the branch details and selected manager.';
        }
    }
    
    // Handle edit branch
    if (isset($_POST['edit_branch'])) {
        $branch_id = intval($_POST['branch_id']);
        $branch_name = $conn->real_escape_string($_POST['branch_name']);
        $location = $conn->real_escape_string($_POST['location']);
        $address = $conn->real_escape_string($_POST['address']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $email = $conn->real_escape_string($_POST['email']);
        $manager_id = max(0, (int)($_POST['manager_id'] ?? 0));
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE branches SET branch_name = ?, location = ?, address = ?, phone = ?, email = ?, manager_id = NULLIF(?, 0), status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssisi", $branch_name, $location, $address, $phone, $email, $manager_id, $status, $branch_id);
        
        try {
            if ($stmt->execute()) {
                $success = "Branch updated successfully!";
            } else {
                $error = "Error updating branch: " . $stmt->error;
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Branch update failed: ' . $exception->getMessage());
            $error = 'Unable to update branch. Verify the branch details and selected manager.';
        }
    }
}

// Handle branch deletion
if (isset($_GET['delete'])) {
    $branch_id = intval($_GET['delete']);
    $sql = "UPDATE branches SET status = 'inactive' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
    
    try {
        if ($stmt->execute()) {
            $success = "Branch deactivated successfully!";
        } else {
            $error = "Error deactivating branch: " . $stmt->error;
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Branch deactivation failed: ' . $exception->getMessage());
        $error = 'Unable to deactivate this branch. Check that no dependent operations prevent the change.';
    }
}

// Get all branches
$branches_sql = "SELECT b.*, u.full_name as manager_name 
                 FROM branches b 
                 LEFT JOIN users u ON b.manager_id = u.id 
                 ORDER BY b.created_at DESC";
$branches = $conn->query($branches_sql);

// Get managers for dropdown
$managers_sql = "SELECT id, full_name FROM users WHERE role = 'manager' AND status = 'active'";
$managers = $conn->query($managers_sql);
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-building"></i> Manage Branches
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                    <i class="fas fa-plus"></i> Add New Branch
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
                                <th>Branch Name</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th>Manager</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $branches->data_seek(0);
                            while($branch = $branches->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo $branch['id']; ?></td>
                                <td>
                                    <strong><?php echo $branch['branch_name']; ?></strong><br>
                                    <small class="text-muted"><?php echo $branch['address']; ?></small>
                                </td>
                                <td><?php echo $branch['location']; ?></td>
                                <td>
                                    <i class="fas fa-phone"></i> <?php echo $branch['phone']; ?><br>
                                    <i class="fas fa-envelope"></i> <?php echo $branch['email']; ?>
                                </td>
                                <td><?php echo $branch['manager_name'] ?: 'Not Assigned'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $branch['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($branch['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editBranchModal<?php echo $branch['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $branch['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to deactivate this branch?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="branch_name" class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" id="branch_name" name="branch_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="manager_id" class="form-label">Branch Manager</label>
                        <select class="form-control" id="manager_id" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php 
                            $managers->data_seek(0);
                            while($manager = $managers->fetch_assoc()): ?>
                                <option value="<?php echo $manager['id']; ?>"><?php echo $manager['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_branch" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modals -->
<?php 
$branches->data_seek(0);
while($branch = $branches->fetch_assoc()): 
?>
<div class="modal fade" id="editBranchModal<?php echo $branch['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="branch_id" value="<?php echo $branch['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="edit_branch_name_<?php echo $branch['id']; ?>" class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" id="edit_branch_name_<?php echo $branch['id']; ?>" 
                               name="branch_name" value="<?php echo htmlspecialchars($branch['branch_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_location_<?php echo $branch['id']; ?>" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="edit_location_<?php echo $branch['id']; ?>" 
                               name="location" value="<?php echo htmlspecialchars($branch['location']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address_<?php echo $branch['id']; ?>" class="form-label">Address *</label>
                        <textarea class="form-control" id="edit_address_<?php echo $branch['id']; ?>" 
                                  name="address" rows="3" required><?php echo htmlspecialchars($branch['address']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone_<?php echo $branch['id']; ?>" class="form-label">Phone *</label>
                        <input type="tel" class="form-control" id="edit_phone_<?php echo $branch['id']; ?>" 
                               name="phone" value="<?php echo htmlspecialchars($branch['phone']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email_<?php echo $branch['id']; ?>" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="edit_email_<?php echo $branch['id']; ?>" 
                               name="email" value="<?php echo htmlspecialchars($branch['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_manager_id_<?php echo $branch['id']; ?>" class="form-label">Branch Manager</label>
                        <select class="form-control" id="edit_manager_id_<?php echo $branch['id']; ?>" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php 
                            $managers->data_seek(0);
                            while($manager = $managers->fetch_assoc()): ?>
                                <option value="<?php echo $manager['id']; ?>" 
                                    <?php echo ($manager['id'] == $branch['manager_id']) ? 'selected' : ''; ?>>
                                    <?php echo $manager['full_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status_<?php echo $branch['id']; ?>" class="form-label">Status *</label>
                        <select class="form-control" id="edit_status_<?php echo $branch['id']; ?>" name="status" required>
                            <option value="active" <?php echo ($branch['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($branch['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_branch" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endwhile; ?>

<script>
// Function to reset add modal when closed
document.getElementById('addBranchModal').addEventListener('hidden.bs.modal', function () {
    this.querySelector('form').reset();
});

// Function to validate phone number format (optional enhancement)
function validatePhone(input) {
    // Basic phone validation - allow only numbers, spaces, dashes, and parentheses
    input.value = input.value.replace(/[^0-9\s\-\(\)]/g, '');
}

// Attach validation to phone inputs
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            validatePhone(this);
        });
    });
    
    // Add confirm dialog for delete with more specific message
    const deleteLinks = document.querySelectorAll('a[href*="delete="]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to deactivate this branch? This will set its status to inactive.')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<style>
/* Optional styling enhancements */
.modal-body .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
}

.badge {
    font-size: 0.85em;
    padding: 0.4em 0.8em;
}

.btn-group .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>
<?php
require_once '../includes/footer.php';
?>