<?php
require_once '../includes/header.php';
require_once '../includes/employee_id.php';

// Handle user actions
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $conn->real_escape_string($_POST['email']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $role = $conn->real_escape_string($_POST['role']);
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $monthly_salary = filter_var($_POST['monthly_salary'] ?? '0', FILTER_VALIDATE_FLOAT);
        if ($monthly_salary === false || $monthly_salary < 0 || $monthly_salary > 9999999999.99) {
            $error = 'Enter a valid non-negative monthly salary.';
        } else {
        
        // Handle profile photo upload
        $profile_photo = 'default-avatar.png'; // Default avatar
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array(strtolower($file_ext), $allowed_ext) && $_FILES['profile_photo']['size'] <= $max_size) {
                $new_filename = uniqid('user_', true) . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $profile_photo = $new_filename;
                }
            }
        }
        
        $sql = "INSERT INTO users (username, password, email, full_name, role, branch_id, monthly_salary, status, profile_photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssids", $username, $password, $email, $full_name, $role, $branch_id, $monthly_salary, $profile_photo);
        
        try {
            $conn->begin_transaction();
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            assignEmployeeIdIfMissing($conn, (int)$stmt->insert_id);
            $conn->commit();
            $success = "User added successfully!";
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = "Error adding user: " . $exception->getMessage();
        }
        }
    }
    
    // Handle edit user
    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $role = $conn->real_escape_string($_POST['role']);
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $monthly_salary = filter_var($_POST['monthly_salary'] ?? '0', FILTER_VALIDATE_FLOAT);
        $status = $conn->real_escape_string($_POST['status']);
        if ($monthly_salary === false || $monthly_salary < 0 || $monthly_salary > 9999999999.99) {
            $error = 'Enter a valid non-negative monthly salary.';
        } else {
        
        // Handle profile photo upload for edit
        $profile_photo_update = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array(strtolower($file_ext), $allowed_ext) && $_FILES['profile_photo']['size'] <= $max_size) {
                $new_filename = uniqid('user_', true) . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $profile_photo_update = ", profile_photo = '" . $conn->real_escape_string($new_filename) . "'";
                    
                    // Delete old photo if not default
                    $old_sql = "SELECT profile_photo FROM users WHERE id = ?";
                    $old_stmt = $conn->prepare($old_sql);
                    $old_stmt->bind_param("i", $user_id);
                    $old_stmt->execute();
                    $old_result = $old_stmt->get_result();
                    if ($old_user = $old_result->fetch_assoc()) {
                        if ($old_user['profile_photo'] !== 'default-avatar.png' && file_exists($upload_dir . $old_user['profile_photo'])) {
                            unlink($upload_dir . $old_user['profile_photo']);
                        }
                    }
                }
            }
        }
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, password = ?, email = ?, full_name = ?, role = ?, branch_id = ?, monthly_salary = ?, status = ? $profile_photo_update WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($profile_photo_update) {
                $stmt->bind_param("sssssidsi", $username, $password, $email, $full_name, $role, $branch_id, $monthly_salary, $status, $user_id);
            } else {
                $stmt->bind_param("sssssidsi", $username, $password, $email, $full_name, $role, $branch_id, $monthly_salary, $status, $user_id);
            }
        } else {
            $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, branch_id = ?, monthly_salary = ?, status = ? $profile_photo_update WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($profile_photo_update) {
                $stmt->bind_param("ssssidsi", $username, $email, $full_name, $role, $branch_id, $monthly_salary, $status, $user_id);
            } else {
                $stmt->bind_param("ssssidsi", $username, $email, $full_name, $role, $branch_id, $monthly_salary, $status, $user_id);
            }
        }
        
        try {
            $conn->begin_transaction();
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            assignEmployeeIdIfMissing($conn, $user_id);
            $conn->commit();
            $success = "User updated successfully!";
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = "Error updating user: " . $exception->getMessage();
        }
        }
    }
}

// Fetch user data for editing if edit parameter is set
$edit_user = null;
if (isset($_GET['edit'])) {
    $user_id = intval($_GET['edit']);
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
}

$roleTabs = [
    'all' => 'All Users',
    'attendant' => 'Attendant',
    'manager' => 'Manager',
    'accountant' => 'Accountant',
    'admin' => 'CEO / Admin',
];
$roleFilter = (string)($_GET['role'] ?? 'all');
if (!array_key_exists($roleFilter, $roleTabs)) {
    $roleFilter = 'all';
}

$roleCounts = array_fill_keys(array_keys($roleTabs), 0);
$roleCountsResult = $conn->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
while ($roleCount = $roleCountsResult->fetch_assoc()) {
    if (isset($roleCounts[$roleCount['role']])) {
        $roleCounts[$roleCount['role']] = (int)$roleCount['total'];
        $roleCounts['all'] += (int)$roleCount['total'];
    }
}

$users_sql = "SELECT u.*, b.branch_name
              FROM users u
              LEFT JOIN branches b ON u.branch_id = b.id";
if ($roleFilter === 'all') {
    $users_sql .= ' ORDER BY u.created_at DESC';
    $users = $conn->query($users_sql);
} else {
    $users_sql .= ' WHERE u.role = ? ORDER BY u.created_at DESC';
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->bind_param('s', $roleFilter);
    $users_stmt->execute();
    $users = $users_stmt->get_result();
}

// Get branches for dropdown (re-fetch after potential edit operation)
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active'";
$branches = $conn->query($branches_sql);
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Manage Users
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <ul class="nav nav-tabs mb-4" role="tablist" aria-label="Filter users by role">
                    <?php foreach ($roleTabs as $roleKey => $roleLabel): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $roleFilter === $roleKey ? 'active' : ''; ?>"
                               href="users.php?role=<?php echo urlencode($roleKey); ?>"
                               role="tab"
                               aria-selected="<?php echo $roleFilter === $roleKey ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="badge bg-secondary ms-1"><?php echo $roleCounts[$roleKey]; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Employee ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Monthly Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="avatar-container">
                                        <img src="<?php echo !empty($user['profile_photo']) && $user['profile_photo'] !== 'default-avatar.png' ? '../uploads/profile_photos/' . $user['profile_photo'] : '../assets/images/default-avatar.svg'; ?>" 
                                             alt="<?php echo htmlspecialchars($user['full_name']); ?>"
                                             class="avatar-img rounded-circle"
                                             onerror="this.src='../assets/images/default-avatar.svg'"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $user['username']; ?></td>
                                <td><?php echo $user['full_name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($user['role']) {
                                            case 'admin': echo 'danger'; break;
                                            case 'manager': echo 'warning'; break;
                                            case 'attendant': echo 'info'; break;
                                            case 'accountant': echo 'success'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['branch_name'] ?: 'N/A'; ?></td>
                                <td><?php echo (float)($user['monthly_salary'] ?? 0) > 0
                                        ? '$' . number_format((float)$user['monthly_salary'], 2)
                                        : 'Not set'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                onclick="loadEditData(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?role=<?php echo urlencode($roleFilter); ?>&amp;delete=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to deactivate this user?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($users->num_rows === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No users found for this role.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="profile_photo" class="form-label">Profile Photo</label>
                            <div class="profile-photo-upload">
                                <div class="text-center mb-3">
                                    <img id="addPhotoPreview" src="../assets/images/default-avatar.svg" 
                                         alt="Profile Preview" 
                                         class="rounded-circle border"
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                </div>
                                <input type="file" class="form-control" id="profile_photo" name="profile_photo" 
                                       accept="image/*" onchange="previewImage('addPhotoPreview', this)">
                                <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="manager">Branch Manager</option>
                                <option value="attendant">Attendant</option>
                                <option value="accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branch_id" class="form-label">Branch</label>
                            <select class="form-control" id="branch_id" name="branch_id">
                                <option value="">Select Branch</option>
                                <?php 
                                $branches->data_seek(0);
                                while($branch = $branches->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo $branch['branch_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="monthly_salary" class="form-label">Monthly Salary (USD)</label>
                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary"
                               min="0" max="9999999999.99" step="0.01" value="0.00">
                        <small class="form-text text-muted">Set for staff members eligible for monthly payroll.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_profile_photo" class="form-label">Profile Photo</label>
                            <div class="profile-photo-upload">
                                <div class="text-center mb-3">
                                    <img id="editPhotoPreview" src="" 
                                         alt="Profile Preview" 
                                         class="rounded-circle border"
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                </div>
                                <input type="file" class="form-control" id="edit_profile_photo" name="profile_photo" 
                                       accept="image/*" onchange="previewImage('editPhotoPreview', this)">
                                <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF. Leave empty to keep current.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_password" class="form-label">Password (Leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="text-muted">Enter new password only if you want to change it</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_role" class="form-label">Role *</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="manager">Branch Manager</option>
                                <option value="attendant">Attendant</option>
                                <option value="accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_branch_id" class="form-label">Branch</label>
                            <select class="form-control" id="edit_branch_id" name="branch_id">
                                <option value="">Select Branch</option>
                                <?php 
                                $branches->data_seek(0);
                                while($branch = $branches->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo $branch['branch_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_monthly_salary" class="form-label">Monthly Salary (USD)</label>
                        <input type="number" class="form-control" id="edit_monthly_salary" name="monthly_salary"
                               min="0" max="9999999999.99" step="0.01" required>
                        <small class="form-text text-muted">Set for staff members eligible for monthly payroll.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadEditData(userData) {
    // Populate the edit form with user data
    document.getElementById('edit_user_id').value = userData.id;
    document.getElementById('edit_username').value = userData.username;
    document.getElementById('edit_full_name').value = userData.full_name;
    document.getElementById('edit_email').value = userData.email;
    document.getElementById('edit_role').value = userData.role;
    document.getElementById('edit_monthly_salary').value = Number(userData.monthly_salary || 0).toFixed(2);
    document.getElementById('edit_status').value = userData.status;
    
    // Set profile photo preview
    const photoPreview = document.getElementById('editPhotoPreview');
    if (userData.profile_photo) {
        photoPreview.src = '../uploads/profile_photos/' + userData.profile_photo;
    } else {
        photoPreview.src = '../assets/images/default-avatar.svg';
    }
    
    // Set branch if exists
    if (userData.branch_id) {
        document.getElementById('edit_branch_id').value = userData.branch_id;
    } else {
        document.getElementById('edit_branch_id').value = '';
    }
    
    // Clear password field
    document.getElementById('edit_password').value = '';
}

function previewImage(previewId, input) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>

<style>
.avatar-img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #dee2e6;
}

.profile-photo-upload .rounded-circle {
    border: 3px solid #dee2e6;
    background-color: #f8f9fa;
}

.avatar-container {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
<?php
require_once '../includes/footer.php';
?>