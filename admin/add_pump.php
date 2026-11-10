<?php
// add_pump.php - Complete working version with AJAX and fallback
require_once '../includes/header.php';

// Set page title
$page_title = "Add New Pump";

// Handle pump addition
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Handle pump addition
    if (isset($_POST['add_pump'])) {
        $branch_id = intval($_POST['branch_id']);
        $tank_id = intval($_POST['tank_id']);
        $pump_number = trim($_POST['pump_number']);
        $pump_name = trim($_POST['pump_name']);
        
        // Basic validation
        $errors = [];
        
        if (empty($pump_number)) {
            $errors[] = "Pump number is required!";
        }
        if (empty($pump_name)) {
            $errors[] = "Pump name is required!";
        }
        if ($branch_id <= 0) {
            $errors[] = "Please select a branch!";
        }
        if ($tank_id <= 0) {
            $errors[] = "Please select a tank!";
        }
        
        if (empty($errors)) {
            // Check if pump number already exists in this branch
            $check_sql = "SELECT id FROM pumps WHERE branch_id = ? AND pump_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $branch_id, $pump_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Error: Pump number '$pump_number' already exists in this branch!";
            } else {
                // Insert pump
                $sql = "INSERT INTO pumps (branch_id, tank_id, pump_number, pump_name, status, last_reading, current_reading) 
                        VALUES (?, ?, ?, ?, 'active', 0.00, 0.00)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $branch_id, $tank_id, $pump_number, $pump_name);
                
                if ($stmt->execute()) {
                    $success = "Pump added successfully! ID: " . $stmt->insert_id;
                    // Clear form
                    $_POST['pump_number'] = '';
                    $_POST['pump_name'] = '';
                    $tank_id = 0;
                } else {
                    $error = "Error adding pump: " . $conn->error;
                }
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    
    // Store branch selection for form persistence
    if (isset($_POST['branch_id']) && !isset($_POST['add_pump'])) {
        $selected_branch_id = intval($_POST['branch_id']);
    }
}

// Get selected branch ID
$selected_branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;

// Get branches for dropdown
$branches_sql = "SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
$branches = $conn->query($branches_sql);

// Get tanks for selected branch (for non-AJAX fallback)
$initial_tanks = [];
if ($selected_branch_id > 0) {
    $tanks_sql = "SELECT t.id, t.tank_number, t.current_quantity, f.fuel_name 
                  FROM tanks t 
                  LEFT JOIN fuel_types f ON t.fuel_type_id = f.id 
                  WHERE t.branch_id = ? AND t.status = 'active' 
                  ORDER BY t.tank_number";
    $tanks_stmt = $conn->prepare($tanks_sql);
    $tanks_stmt->bind_param("i", $selected_branch_id);
    $tanks_stmt->execute();
    $tanks_result = $tanks_stmt->get_result();
    
    while($tank = $tanks_result->fetch_assoc()) {
        $initial_tanks[] = $tank;
    }
}
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus"></i> Add New Pump
                </h5>
                <a href="pumps.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Pumps
                </a>
            </div>
            <div class="card-body">
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
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="" id="addPumpForm">
                                    <div class="mb-3">
                                        <label for="branch_id" class="form-label">Branch *</label>
                                        <select class="form-control" id="branch_id" name="branch_id" required 
                                                onchange="loadTanks(this.value)">
                                            <option value="">Select Branch</option>
                                            <?php 
                                            if ($branches && $branches->num_rows > 0) {
                                                while($branch = $branches->fetch_assoc()): ?>
                                                    <option value="<?php echo $branch['id']; ?>" 
                                                        <?php echo $selected_branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                    </option>
                                                <?php endwhile;
                                            } else {
                                                echo '<option value="">No active branches found</option>';
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">
                                            <small>If tanks don't load automatically, <a href="javascript:void(0)" onclick="reloadWithBranch()">click here to refresh</a></small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tank_id" class="form-label">Connected Tank *</label>
                                        <select class="form-control" id="tank_id" name="tank_id" required>
                                            <option value="">Select Tank</option>
                                            <?php 
                                            // Load tanks if branch is already selected (non-AJAX fallback)
                                            if (!empty($initial_tanks)) {
                                                foreach($initial_tanks as $tank): ?>
                                                    <option value="<?php echo $tank['id']; ?>" 
                                                        <?php echo isset($_POST['tank_id']) && $_POST['tank_id'] == $tank['id'] ? 'selected' : ''; ?>>
                                                        Tank <?php echo htmlspecialchars($tank['tank_number']); ?> - 
                                                        <?php echo htmlspecialchars($tank['fuel_name']); ?> 
                                                        (<?php echo number_format($tank['current_quantity'], 2); ?>L available)
                                                    </option>
                                                <?php endforeach;
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Select the tank that supplies fuel to this pump</div>
                                        <div id="tankLoading" class="mt-2" style="display:none;">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <small class="text-muted ms-2">Loading tanks...</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="pump_number" class="form-label">Pump Number *</label>
                                            <input type="text" class="form-control" id="pump_number" name="pump_number" 
                                                   required placeholder="e.g., PUMP-001"
                                                   pattern="[A-Za-z0-9\-_]+"
                                                   title="Alphanumeric characters, hyphens, and underscores only"
                                                   value="<?php echo isset($_POST['pump_number']) ? htmlspecialchars($_POST['pump_number']) : ''; ?>">
                                            <div class="form-text">Unique identifier for this pump (e.g., PUMP-001, PS-PUMP-01)</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="pump_name" class="form-label">Pump Name *</label>
                                            <input type="text" class="form-control" id="pump_name" name="pump_name" 
                                                   required placeholder="e.g., Petrol Pump 1"
                                                   value="<?php echo isset($_POST['pump_name']) ? htmlspecialchars($_POST['pump_name']) : ''; ?>">
                                            <div class="form-text">Display name for the pump</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="add_pump" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Pump
                                        </button>
                                        <a href="pumps.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <button type="button" class="btn btn-info" onclick="testAjax()">
                                            <i class="fas fa-bug"></i> Test AJAX
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-info-circle"></i> Instructions</h6>
                            </div>
                            <div class="card-body">
                                <ol>
                                    <li><strong>Select Branch:</strong> Choose the location where the pump is installed</li>
                                    <li><strong>Select Tank:</strong> Choose which fuel tank supplies this pump</li>
                                    <li><strong>Pump Number:</strong> Enter a unique identifier (e.g., PUMP-001, PS-PUMP-01)</li>
                                    <li><strong>Pump Name:</strong> Enter a descriptive name (e.g., Petrol Pump 1, Diesel Pump A)</li>
                                    <li><strong>Save:</strong> Click "Save Pump" to add it to the system</li>
                                </ol>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Pump readings will start at 0.00 liters. Update the reading after installation.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load tanks via AJAX
function loadTanks(branchId) {
    console.log("Loading tanks for branch ID:", branchId);
    
    if (!branchId || branchId === '') {
        document.getElementById('tank_id').innerHTML = '<option value="">Select Tank</option>';
        return;
    }
    
    // Show loading indicator
    document.getElementById('tankLoading').style.display = 'block';
    var tankSelect = document.getElementById('tank_id');
    tankSelect.disabled = true;
    tankSelect.innerHTML = '<option value="">Loading tanks...</option>';
    
    // Create AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/get_tanks.php', true);
    
    // Set timeout
    xhr.timeout = 10000; // 10 seconds
    
    xhr.onload = function() {
        // Hide loading indicator
        document.getElementById('tankLoading').style.display = 'none';
        tankSelect.disabled = false;
        
        console.log("HTTP Status:", xhr.status);
        console.log("Response:", xhr.responseText);
        
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    if (response.tanks && response.tanks.length > 0) {
                        // Build options
                        var options = '<option value="">Select Tank</option>';
                        response.tanks.forEach(function(tank) {
                            options += '<option value="' + tank.id + '">' +
                                      'Tank ' + tank.tank_number + ' - ' + tank.fuel_name + 
                                      ' (' + parseFloat(tank.current_quantity).toFixed(2) + 'L available)' +
                                      '</option>';
                        });
                        tankSelect.innerHTML = options;
                        
                        // Show success message
                        showAlert('Loaded ' + response.tanks.length + ' tanks', 'success');
                    } else {
                        tankSelect.innerHTML = '<option value="">No active tanks found in this branch</option>';
                        showAlert('No tanks found in selected branch. Please add tanks first.', 'warning');
                    }
                } else {
                    tankSelect.innerHTML = '<option value="">Error: ' + (response.message || 'Failed to load') + '</option>';
                    showAlert('Error: ' + (response.message || 'Failed to load tanks'), 'danger');
                }
            } catch(e) {
                console.error('JSON parse error:', e);
                tankSelect.innerHTML = '<option value="">Error parsing response</option>';
                showAlert('Error parsing server response. Please try the manual refresh.', 'danger');
            }
        } else if (xhr.status === 500) {
            tankSelect.innerHTML = '<option value="">Server error (500)</option>';
            showAlert('Server error 500. The AJAX endpoint may have an issue.', 'danger');
        } else {
            tankSelect.innerHTML = '<option value="">Error (HTTP ' + xhr.status + ')</option>';
            showAlert('Server returned error: HTTP ' + xhr.status, 'danger');
        }
    };
    
    xhr.onerror = function() {
        document.getElementById('tankLoading').style.display = 'none';
        tankSelect.disabled = false;
        tankSelect.innerHTML = '<option value="">Network error</option>';
        showAlert('Network error. Please check connection or use manual refresh.', 'danger');
    };
    
    xhr.ontimeout = function() {
        document.getElementById('tankLoading').style.display = 'none';
        tankSelect.disabled = false;
        tankSelect.innerHTML = '<option value="">Request timeout</option>';
        showAlert('Request timeout. Server is taking too long.', 'warning');
    };
    
    // Send the request
    var formData = new FormData();
    formData.append('branch_id', branchId);
    xhr.send(formData);
}

// Function to show alerts
function showAlert(message, type) {
    // Remove existing alert
    var existingAlert = document.getElementById('ajaxAlert');
    if (existingAlert) existingAlert.remove();
    
    // Create new alert
    var alertDiv = document.createElement('div');
    alertDiv.id = 'ajaxAlert';
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show mt-2';
    alertDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>' + 
                         message + 
                         '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    // Insert after the tank select
    var tankSelect = document.getElementById('tank_id');
    tankSelect.parentNode.insertBefore(alertDiv, tankSelect.nextSibling);
    
    // Auto-remove after 5 seconds (except for errors)
    if (type !== 'danger') {
        setTimeout(function() {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Manual refresh fallback
function reloadWithBranch() {
    var branchId = document.getElementById('branch_id').value;
    if (!branchId) {
        alert('Please select a branch first');
        return;
    }
    
    // Create a form and submit it to reload the page with branch selected
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'branch_id';
    input.value = branchId;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
}

// Test AJAX endpoint
function testAjax() {
    var branchId = document.getElementById('branch_id').value;
    if (!branchId) {
        alert('Please select a branch first');
        return;
    }
    
    console.log("Testing AJAX for branch:", branchId);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/get_tanks.php', true);
    xhr.timeout = 5000;
    
    xhr.onload = function() {
        console.log("Test - Status:", xhr.status);
        console.log("Test - Response:", xhr.responseText);
        
        var message = "Test Result:\n";
        message += "HTTP Status: " + xhr.status + "\n";
        message += "Response Length: " + xhr.responseText.length + " chars\n";
        
        try {
            var json = JSON.parse(xhr.responseText);
            message += "JSON Parsed: " + (json.success ? "SUCCESS" : "FAILED") + "\n";
            message += "Message: " + (json.message || 'No message') + "\n";
            message += "Tanks Found: " + (json.count || 0);
        } catch(e) {
            message += "JSON Parse Error: " + e.message + "\n";
            message += "First 100 chars: " + xhr.responseText.substring(0, 100);
        }
        
        alert(message);
    };
    
    xhr.onerror = function() {
        alert("Test Failed: Network error\nCheck if ajax/get_tanks.php exists");
    };
    
    xhr.ontimeout = function() {
        alert("Test Failed: Timeout\nServer is not responding");
    };
    
    var formData = new FormData();
    formData.append('branch_id', branchId);
    xhr.send(formData);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    var branchSelect = document.getElementById('branch_id');
    if (branchSelect && branchSelect.value) {
        console.log("Page loaded with branch selected:", branchSelect.value);
        // Load tanks for selected branch
        setTimeout(function() {
            loadTanks(branchSelect.value);
        }, 500);
    }
    
    // Form validation
    var form = document.getElementById('addPumpForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            var pumpNumber = document.getElementById('pump_number').value.trim();
            var pumpName = document.getElementById('pump_name').value.trim();
            var branchId = document.getElementById('branch_id').value;
            var tankId = document.getElementById('tank_id').value;
            
            var errors = [];
            
            if (!pumpNumber) errors.push('• Pump number is required');
            if (!pumpName) errors.push('• Pump name is required');
            if (!branchId) errors.push('• Please select a branch');
            if (!tankId) errors.push('• Please select a tank');
            
            // Validate pump number format
            if (pumpNumber && !/^[A-Za-z0-9\-_]+$/.test(pumpNumber)) {
                errors.push('• Pump number can only contain letters, numbers, hyphens, and underscores');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                showAlert('<strong>Please fix the following:</strong><br>' + errors.join('<br>'), 'danger');
                return false;
            }
            
            return true;
        });
    }
    
    // Real-time validation for pump number
    var pumpNumberInput = document.getElementById('pump_number');
    if (pumpNumberInput) {
        pumpNumberInput.addEventListener('input', function() {
            var value = this.value;
            var pattern = /^[A-Za-z0-9\-_]*$/; // Allow empty during typing
            
            if (value && !pattern.test(value)) {
                this.classList.add('is-invalid');
                var helpText = this.nextElementSibling;
                if (helpText) {
                    helpText.innerHTML = '<span class="text-danger">Only letters, numbers, hyphens, and underscores allowed</span>';
                }
            } else {
                this.classList.remove('is-invalid');
                var helpText = this.nextElementSibling;
                if (helpText) {
                    helpText.innerHTML = 'Unique identifier for this pump (e.g., PUMP-001, PS-PUMP-01)';
                }
            }
        });
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>