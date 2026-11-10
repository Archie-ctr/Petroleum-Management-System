<?php
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check and fix database structure first
$tables_to_check = [
    'fuel_types' => [
        'columns' => [
            'price_per_liter' => 'DECIMAL(10,2) DEFAULT 0.00'
        ]
    ],
    'fuel_prices' => [
        'columns' => [
            'price_usd' => 'DECIMAL(10,2) DEFAULT 0.00',
            'price_lrd' => 'DECIMAL(10,2) DEFAULT 0.00'
        ]
    ],
    'branch_operational_settings',
    'branch_tax_settings',
    'branch_hours'
];

foreach ($tables_to_check as $table_name => $structure) {
    if (is_array($structure) && isset($structure['columns'])) {
        $check_sql = "SHOW TABLES LIKE '$table_name'";
        $result = $conn->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            // Check and fix columns
            foreach ($structure['columns'] as $column_name => $column_type) {
                $check_column_sql = "SHOW COLUMNS FROM $table_name LIKE '$column_name'";
                $column_result = $conn->query($check_column_sql);
                
                if (!$column_result || $column_result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alter_sql = "ALTER TABLE $table_name ADD COLUMN $column_name $column_type";
                    $conn->query($alter_sql);
                }
                if ($column_result) $column_result->free();
            }
        }
        if ($result) $result->free();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_branch_settings'])) {
        $branch_id = intval($_POST['branch_id']);
        $success = "";
        $error = "";
        
        // Update business hours
        if (isset($_POST['business_hours'])) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $day_map = [
                'monday' => 0,
                'tuesday' => 1,
                'wednesday' => 2,
                'thursday' => 3,
                'friday' => 4,
                'saturday' => 5,
                'sunday' => 6
            ];
            
            foreach ($days as $day) {
                if (isset($_POST['business_hours'][$day])) {
                    $hours = $_POST['business_hours'][$day];
                    $day_of_week = $day_map[$day];
                    $is_open = isset($hours['closed']) && $hours['closed'] == '1' ? 0 : 1;
                    $open_time = $is_open ? ($hours['open'] ?? '08:00') : NULL;
                    $close_time = $is_open ? ($hours['close'] ?? '20:00') : NULL;
                    
                    // Check if entry exists
                    $check_sql = "SELECT id FROM branch_hours WHERE branch_id = ? AND day_of_week = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    if ($check_stmt) {
                        $check_stmt->bind_param("ii", $branch_id, $day_of_week);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            // Update existing
                            $update_sql = "UPDATE branch_hours SET is_open = ?, open_time = ?, close_time = ?, updated_at = NOW() 
                                         WHERE branch_id = ? AND day_of_week = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("issii", $is_open, $open_time, $close_time, $branch_id, $day_of_week);
                                if (!$update_stmt->execute()) {
                                    $error = "Error updating business hours: " . $conn->error;
                                }
                                $update_stmt->close();
                            }
                        } else {
                            // Insert new
                            $insert_sql = "INSERT INTO branch_hours (branch_id, day_of_week, is_open, open_time, close_time) 
                                         VALUES (?, ?, ?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_sql);
                            if ($insert_stmt) {
                                $insert_stmt->bind_param("iiiss", $branch_id, $day_of_week, $is_open, $open_time, $close_time);
                                if (!$insert_stmt->execute()) {
                                    $error = "Error inserting business hours: " . $conn->error;
                                }
                                $insert_stmt->close();
                            }
                        }
                        $check_stmt->close();
                    }
                }
            }
            if (empty($error)) {
                $success = "Business hours updated successfully!";
            }
        }
        
        // Update fuel prices - FIXED
        if (isset($_POST['fuel_prices'])) {
            foreach ($_POST['fuel_prices'] as $fuel_name => $prices) {
                $fuel_name = $conn->real_escape_string($fuel_name);
                $price_usd = isset($prices['usd']) ? floatval($prices['usd']) : 0.00;
                $price_lrd = isset($prices['lrd']) ? floatval($prices['lrd']) : 0.00;
                
                // Validate prices
                if ($price_usd < 0) $price_usd = 0.00;
                if ($price_lrd < 0) $price_lrd = 0.00;
                
                // Check if price exists
                $check_sql = "SELECT id FROM fuel_prices WHERE branch_id = ? AND fuel_type = ?";
                $check_stmt = $conn->prepare($check_sql);
                if ($check_stmt) {
                    $check_stmt->bind_param("is", $branch_id, $fuel_name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update existing
                        $update_sql = "UPDATE fuel_prices SET price_usd = ?, price_lrd = ?, updated_at = NOW() 
                                     WHERE branch_id = ? AND fuel_type = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        if ($update_stmt) {
                            $update_stmt->bind_param("ddis", $price_usd, $price_lrd, $branch_id, $fuel_name);
                            if (!$update_stmt->execute()) {
                                $error = "Error updating price for $fuel_name: " . $conn->error;
                            }
                            $update_stmt->close();
                        }
                    } else {
                        // Insert new
                        $insert_sql = "INSERT INTO fuel_prices (branch_id, fuel_type, price_usd, price_lrd) 
                                     VALUES (?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_sql);
                        if ($insert_stmt) {
                            $insert_stmt->bind_param("isdd", $branch_id, $fuel_name, $price_usd, $price_lrd);
                            if (!$insert_stmt->execute()) {
                                $error = "Error inserting price for $fuel_name: " . $conn->error;
                            }
                            $insert_stmt->close();
                        }
                    }
                    $check_stmt->close();
                }
            }
            if (empty($error)) {
                $success = "Fuel prices updated successfully!";
            }
        }
        
        // Handle new fuel type
        if (isset($_POST['add_fuel_type'])) {
            $new_fuel_name = trim($_POST['new_fuel_type'] ?? '');
            $new_fuel_code = trim($_POST['new_fuel_code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $new_price_usd = isset($_POST['new_price_usd']) ? floatval($_POST['new_price_usd']) : 0.00;
            $new_price_lrd = isset($_POST['new_price_lrd']) ? floatval($_POST['new_price_lrd']) : 0.00;
            
            if (!empty($new_fuel_name) && !empty($new_fuel_code)) {
                // Use prepared statement to prevent SQL injection
                $insert_sql = "INSERT INTO fuel_types (fuel_name, fuel_code, price_per_liter, description) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                if ($stmt) {
                    // Use price_usd as the base price per liter
                    $stmt->bind_param("ssds", $new_fuel_name, $new_fuel_code, $new_price_usd, $description);
                    if ($stmt->execute()) {
                        // Add price for current branch
                        $price_sql = "INSERT INTO fuel_prices (branch_id, fuel_type, price_usd, price_lrd) 
                                    VALUES (?, ?, ?, ?)";
                        $price_stmt = $conn->prepare($price_sql);
                        if ($price_stmt) {
                            $price_stmt->bind_param("isdd", $branch_id, $new_fuel_name, $new_price_usd, $new_price_lrd);
                            $price_stmt->execute();
                            $price_stmt->close();
                        }
                        
                        $success = "New fuel type added successfully!";
                    } else {
                        $error = "Error adding fuel type: " . $conn->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = "Fuel type name and code are required!";
            }
        }
        
        // Update contact info
        if (isset($_POST['contact_info'])) {
            $phone = trim($_POST['contact_info']['phone'] ?? '');
            $email = trim($_POST['contact_info']['email'] ?? '');
            $address = trim($_POST['contact_info']['address'] ?? '');
            
            $sql = "UPDATE branches SET phone = ?, email = ?, address = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssi", $phone, $email, $address, $branch_id);
                if ($stmt->execute()) {
                    if (empty($success)) $success = "Contact information updated successfully!";
                } else {
                    $error = "Error updating contact information: " . $conn->error;
                }
                $stmt->close();
            }
        }
        
        // Update operational settings
        if (isset($_POST['operational_settings'])) {
            $max_daily_sales = isset($_POST['operational_settings']['max_daily_sales']) ? intval($_POST['operational_settings']['max_daily_sales']) : 10000;
            $low_stock_threshold = isset($_POST['operational_settings']['low_stock_threshold']) ? intval($_POST['operational_settings']['low_stock_threshold']) : 100;
            $auto_reorder_level = isset($_POST['operational_settings']['auto_reorder_level']) ? intval($_POST['operational_settings']['auto_reorder_level']) : 200;
            $allow_credit_sales = isset($_POST['operational_settings']['allow_credit_sales']) ? 1 : 0;
            $require_manager_approval = isset($_POST['operational_settings']['require_manager_approval']) ? 1 : 0;
            
            $check_sql = "SELECT id FROM branch_operational_settings WHERE branch_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param("i", $branch_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $update_sql = "UPDATE branch_operational_settings SET 
                                 max_daily_sales = ?, 
                                 low_stock_threshold = ?, 
                                 auto_reorder_level = ?, 
                                 allow_credit_sales = ?, 
                                 require_manager_approval = ?, 
                                 updated_at = NOW() 
                                 WHERE branch_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("iiiisi", $max_daily_sales, $low_stock_threshold, $auto_reorder_level, $allow_credit_sales, $require_manager_approval, $branch_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                } else {
                    $insert_sql = "INSERT INTO branch_operational_settings 
                                 (branch_id, max_daily_sales, low_stock_threshold, auto_reorder_level, allow_credit_sales, require_manager_approval) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("iiiiii", $branch_id, $max_daily_sales, $low_stock_threshold, $auto_reorder_level, $allow_credit_sales, $require_manager_approval);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                }
                $check_stmt->close();
                if (empty($error)) {
                    $success = "Operational settings updated successfully!";
                }
            }
        }
        
        // Update tax settings
        if (isset($_POST['tax_settings'])) {
            $tax_rate = isset($_POST['tax_settings']['tax_rate']) ? floatval($_POST['tax_settings']['tax_rate']) : 7.50;
            $tax_inclusive = isset($_POST['tax_settings']['tax_inclusive']) ? 1 : 0;
            $service_charge = isset($_POST['tax_settings']['service_charge']) ? floatval($_POST['tax_settings']['service_charge']) : 0.00;
            
            $check_sql = "SELECT id FROM branch_tax_settings WHERE branch_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param("i", $branch_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $update_sql = "UPDATE branch_tax_settings SET 
                                 tax_rate = ?, 
                                 tax_inclusive = ?, 
                                 service_charge = ?, 
                                 updated_at = NOW() 
                                 WHERE branch_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("didi", $tax_rate, $tax_inclusive, $service_charge, $branch_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                } else {
                    $insert_sql = "INSERT INTO branch_tax_settings 
                                 (branch_id, tax_rate, tax_inclusive, service_charge) 
                                 VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("idid", $branch_id, $tax_rate, $tax_inclusive, $service_charge);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                }
                $check_stmt->close();
                if (empty($error)) {
                    $success = "Tax settings updated successfully!";
                }
            }
        }
        
        // Redirect to clear POST data
        if (!empty($success)) {
            header("Location: branch_settings.php?branch_id=" . $branch_id . "&success=" . urlencode($success));
            exit();
        } else if (!empty($error)) {
            header("Location: branch_settings.php?branch_id=" . $branch_id . "&error=" . urlencode($error));
            exit();
        } else {
            header("Location: branch_settings.php?branch_id=" . $branch_id);
            exit();
        }
    }
}

// Get branch ID from GET parameter
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// Check for success/error message
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Get all active branches
$branches_sql = "SELECT id, branch_name, location FROM branches WHERE status = 'active' ORDER BY branch_name";
$branches_result = $conn->query($branches_sql);
$branches = [];
if ($branches_result && $branches_result->num_rows > 0) {
    while ($branch = $branches_result->fetch_assoc()) {
        $branches[] = $branch;
    }
    $branches_result->free();
} else {
    // Handle case where no branches exist
    $error = "No active branches found. Please create a branch first.";
}

// Select first branch if none selected
if ($branch_id == 0 && !empty($branches)) {
    $branch_id = $branches[0]['id'];
}

// Get branch details
$branch_details = null;
$business_hours = null;
$fuel_prices = [];
$available_fuel_types = [];
$operational_settings = null;
$tax_settings = null;

// Helper function to safely format numbers
function safe_number_format($value, $decimals = 2, $default = '0.00') {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }
    return number_format(floatval($value), $decimals);
}

if ($branch_id > 0 && !empty($branches)) {
    // Get branch info
    $branch_sql = "SELECT * FROM branches WHERE id = ?";
    $stmt = $conn->prepare($branch_sql);
    if ($stmt) {
        $stmt->bind_param("i", $branch_id);
        if ($stmt->execute()) {
            $branch_result = $stmt->get_result();
            $branch_details = $branch_result->fetch_assoc();
            $branch_result->free();
        }
        $stmt->close();
    }
    
    // Get business hours from branch_hours table
    $hours_sql = "SELECT * FROM branch_hours WHERE branch_id = ? ORDER BY day_of_week";
    $hours_stmt = $conn->prepare($hours_sql);
    if ($hours_stmt) {
        $hours_stmt->bind_param("i", $branch_id);
        if ($hours_stmt->execute()) {
            $hours_result = $hours_stmt->get_result();
            $day_map = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $business_hours = [];
            
            while ($hour = $hours_result->fetch_assoc()) {
                $day_name = $day_map[$hour['day_of_week']] ?? 'unknown';
                $business_hours[$day_name] = [
                    'open' => $hour['open_time'] ?? '08:00',
                    'close' => $hour['close_time'] ?? '20:00',
                    'closed' => !($hour['is_open'] ?? 0)
                ];
            }
            $hours_result->free();
        }
        $hours_stmt->close();
    }
    
    // Default business hours if none found
    if (!$business_hours || !is_array($business_hours)) {
        $business_hours = [
            'monday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            'tuesday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            'wednesday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            'thursday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            'friday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            'saturday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'sunday' => ['open' => '10:00', 'close' => '16:00', 'closed' => true]
        ];
    }
    
    // Get fuel types
    $fuel_types_sql = "SELECT id, fuel_name, fuel_code, price_per_liter, description, status FROM fuel_types ORDER BY fuel_name";
    $fuel_types_result = $conn->query($fuel_types_sql);
    if ($fuel_types_result && $fuel_types_result->num_rows > 0) {
        while ($fuel_type = $fuel_types_result->fetch_assoc()) {
            $available_fuel_types[$fuel_type['fuel_name']] = $fuel_type;
        }
        $fuel_types_result->free();
    } else {
        // If no fuel types exist, create some default ones
        $default_fuel_types = [
            ['fuel_name' => 'Petrol Super', 'fuel_code' => 'PS', 'price_per_liter' => 1.50, 'description' => 'Premium Petrol'],
            ['fuel_name' => 'Diesel', 'fuel_code' => 'DL', 'price_per_liter' => 1.30, 'description' => 'Regular Diesel'],
            ['fuel_name' => 'Kerosene', 'fuel_code' => 'KN', 'price_per_liter' => 1.10, 'description' => 'Household Kerosene']
        ];
        
        foreach ($default_fuel_types as $fuel) {
            $insert_sql = "INSERT IGNORE INTO fuel_types (fuel_name, fuel_code, price_per_liter, description) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param("ssds", $fuel[0], $fuel[1], $fuel[2], $fuel[3]);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Re-fetch fuel types
        $fuel_types_result = $conn->query($fuel_types_sql);
        if ($fuel_types_result && $fuel_types_result->num_rows > 0) {
            while ($fuel_type = $fuel_types_result->fetch_assoc()) {
                $available_fuel_types[$fuel_type['fuel_name']] = $fuel_type;
            }
            $fuel_types_result->free();
        }
    }
    
    // Get fuel prices
    if (!empty($available_fuel_types)) {
        $table_check = $conn->query("SHOW TABLES LIKE 'fuel_prices'");
        if ($table_check && $table_check->num_rows > 0) {
            $fuel_prices_sql = "SELECT fp.*, ft.fuel_name, ft.fuel_code 
                              FROM fuel_prices fp 
                              LEFT JOIN fuel_types ft ON fp.fuel_type = ft.fuel_name
                              WHERE fp.branch_id = ? 
                              ORDER BY ft.fuel_name";
            
            $stmt = $conn->prepare($fuel_prices_sql);
            if ($stmt) {
                $stmt->bind_param("i", $branch_id);
                if ($stmt->execute()) {
                    $fuel_prices_result = $stmt->get_result();
                    while ($price = $fuel_prices_result->fetch_assoc()) {
                        $fuel_prices[$price['fuel_type']] = $price;
                    }
                    $fuel_prices_result->free();
                }
                $stmt->close();
            }
        }
    }
    
    // Get operational settings
    $operational_sql = "SELECT * FROM branch_operational_settings WHERE branch_id = ?";
    $stmt = $conn->prepare($operational_sql);
    if ($stmt) {
        $stmt->bind_param("i", $branch_id);
        if ($stmt->execute()) {
            $operational_result = $stmt->get_result();
            $operational_settings = $operational_result->fetch_assoc();
            $operational_result->free();
        }
        $stmt->close();
    }
    
    if (!$operational_settings) {
        $operational_settings = [
            'max_daily_sales' => 10000,
            'low_stock_threshold' => 100,
            'auto_reorder_level' => 200,
            'allow_credit_sales' => 0,
            'require_manager_approval' => 1
        ];
    }
    
    // Get tax settings
    $tax_sql = "SELECT * FROM branch_tax_settings WHERE branch_id = ?";
    $stmt = $conn->prepare($tax_sql);
    if ($stmt) {
        $stmt->bind_param("i", $branch_id);
        if ($stmt->execute()) {
            $tax_result = $stmt->get_result();
            $tax_settings = $tax_result->fetch_assoc();
            $tax_result->free();
        }
        $stmt->close();
    }
    
    if (!$tax_settings) {
        $tax_settings = [
            'tax_rate' => 7.5,
            'tax_inclusive' => 1,
            'service_charge' => 0
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Settings - God Willing Business Center</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            color: #0d6efd;
        }
        .day-toggle:checked + .form-check-label::before {
            background-color: #0d6efd;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .input-group-text {
            min-width: 40px;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cog"></i> Branch Settings
                    </h5>
                    <?php if (!empty($branches)): ?>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-building"></i>
                                <?php
                                $current_branch_name = 'Select Branch';
                                foreach ($branches as $branch) {
                                    if ($branch['id'] == $branch_id) {
                                        $current_branch_name = $branch['branch_name'] . ' - ' . $branch['location'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($current_branch_name);
                                ?>
                            </button>
                            <ul class="dropdown-menu">
                                <?php foreach ($branches as $branch): ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $branch['id'] == $branch_id ? 'active' : ''; ?>"
                                           href="branch_settings.php?branch_id=<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($branch['location']); ?>)</small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($branches)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No active branches found. Please create a branch first.
                        </div>
                    <?php elseif (!$branch_details): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please select a branch.
                        </div>
                    <?php else: ?>
                        <!-- Tabs -->
                        <ul class="nav nav-tabs" id="settingsTabs">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-target="#hours" data-bs-toggle="tab">
                                    <i class="fas fa-clock"></i> Business Hours
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-target="#prices" data-bs-toggle="tab">
                                    <i class="fas fa-gas-pump"></i> Fuel Prices
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-target="#contact" data-bs-toggle="tab">
                                    <i class="fas fa-address-book"></i> Contact
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-target="#operational" data-bs-toggle="tab">
                                    <i class="fas fa-tasks"></i> Operational
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-target="#tax" data-bs-toggle="tab">
                                    <i class="fas fa-percentage"></i> Tax & Fees
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <!-- Business Hours -->
                            <div class="tab-pane fade show active" id="hours">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Business Hours</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Day</th>
                                                            <th>Open</th>
                                                            <th>Close</th>
                                                            <th>Closed</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $days = [
                                                            'monday' => 'Monday',
                                                            'tuesday' => 'Tuesday',
                                                            'wednesday' => 'Wednesday',
                                                            'thursday' => 'Thursday',
                                                            'friday' => 'Friday',
                                                            'saturday' => 'Saturday',
                                                            'sunday' => 'Sunday'
                                                        ];
                                                        
                                                        foreach ($days as $key => $day):
                                                            $hours = $business_hours[$key] ?? ['open' => '08:00', 'close' => '20:00', 'closed' => false];
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($day); ?></td>
                                                            <td>
                                                                <input type="time" class="form-control" 
                                                                       name="business_hours[<?php echo $key; ?>][open]" 
                                                                       value="<?php echo htmlspecialchars($hours['open'] ?? '08:00'); ?>"
                                                                       <?php echo ($hours['closed'] ?? false) ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <input type="time" class="form-control" 
                                                                       name="business_hours[<?php echo $key; ?>][close]" 
                                                                       value="<?php echo htmlspecialchars($hours['close'] ?? '20:00'); ?>"
                                                                       <?php echo ($hours['closed'] ?? false) ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <input type="checkbox" class="form-check-input day-toggle"
                                                                       name="business_hours[<?php echo $key; ?>][closed]" 
                                                                       value="1" data-day="<?php echo $key; ?>"
                                                                       <?php echo ($hours['closed'] ?? false) ? 'checked' : ''; ?>>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <button type="submit" name="update_branch_settings" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Hours
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Fuel Prices -->
                            <div class="tab-pane fade" id="prices">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6>Fuel Prices</h6>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addFuelTypeModal">
                                            <i class="fas fa-plus"></i> Add Fuel Type
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($available_fuel_types)): ?>
                                            <form method="POST">
                                                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                                                <div class="table-responsive">
                                                    <table class="table">
                                                        <thead>
                                                            <tr>
                                                                <th>Fuel Type</th>
                                                                <th>Code</th>
                                                                <th>Price (USD)</th>
                                                                <th>Price (LRD)</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($available_fuel_types as $fuel_name => $fuel): 
                                                                $price = $fuel_prices[$fuel_name] ?? null;
                                                                $price_usd = $price['price_usd'] ?? $fuel['price_per_liter'] ?? 0;
                                                                $price_lrd = $price['price_lrd'] ?? ($price_usd * 150);
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <?php echo htmlspecialchars($fuel['fuel_name']); ?>
                                                                    <?php if (!empty($fuel['description'])): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($fuel['description']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($fuel['fuel_code']); ?></span></td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text">$</span>
                                                                        <input type="number" step="0.01" class="form-control"
                                                                               name="fuel_prices[<?php echo htmlspecialchars($fuel_name); ?>][usd]"
                                                                               value="<?php echo safe_number_format($price_usd, 2); ?>"
                                                                               required>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text">LD</span>
                                                                        <input type="number" step="0.01" class="form-control"
                                                                               name="fuel_prices[<?php echo htmlspecialchars($fuel_name); ?>][lrd]"
                                                                               value="<?php echo safe_number_format($price_lrd, 2); ?>"
                                                                               required>
                                                                    </div>
                                                                    <small class="form-text text-muted">Default: $<?php echo safe_number_format($price_usd, 2); ?> × 150 = LD<?php echo safe_number_format($price_usd * 150, 2); ?></small>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo ($fuel['status'] ?? 'active') == 'active' ? 'success' : 'danger'; ?>">
                                                                        <?php echo ucfirst($fuel['status'] ?? 'active'); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <button type="submit" name="update_branch_settings" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Save Prices
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i> No fuel types found. Add some fuel types first.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Info -->
                            <div class="tab-pane fade" id="contact">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Contact Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Phone *</label>
                                                <input type="text" class="form-control" name="contact_info[phone]"
                                                       value="<?php echo htmlspecialchars($branch_details['phone'] ?? ''); ?>"
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Email *</label>
                                                <input type="email" class="form-control" name="contact_info[email]"
                                                       value="<?php echo htmlspecialchars($branch_details['email'] ?? ''); ?>"
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Address *</label>
                                                <textarea class="form-control" name="contact_info[address]" rows="3" required><?php echo htmlspecialchars($branch_details['address'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <button type="submit" name="update_branch_settings" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Contact Info
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Operational -->
                            <div class="tab-pane fade" id="operational">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Operational Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Max Daily Sales (USD) *</label>
                                                    <input type="number" class="form-control" 
                                                           name="operational_settings[max_daily_sales]"
                                                           value="<?php echo htmlspecialchars($operational_settings['max_daily_sales'] ?? 10000); ?>"
                                                           required min="0">
                                                    <small class="form-text text-muted">Maximum total sales allowed per day</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Low Stock Threshold (Liters) *</label>
                                                    <input type="number" class="form-control" 
                                                           name="operational_settings[low_stock_threshold]"
                                                           value="<?php echo htmlspecialchars($operational_settings['low_stock_threshold'] ?? 100); ?>"
                                                           required min="0">
                                                    <small class="form-text text-muted">Alert when fuel level drops below this</small>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Auto Reorder Level (Liters) *</label>
                                                    <input type="number" class="form-control" 
                                                           name="operational_settings[auto_reorder_level]"
                                                           value="<?php echo htmlspecialchars($operational_settings['auto_reorder_level'] ?? 200); ?>"
                                                           required min="0">
                                                    <small class="form-text text-muted">Automatically create reorder at this level</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check mt-4">
                                                        <input type="checkbox" class="form-check-input"
                                                               name="operational_settings[allow_credit_sales]" value="1"
                                                               <?php echo ($operational_settings['allow_credit_sales'] ?? 0) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Allow Credit Sales</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                               name="operational_settings[require_manager_approval]" value="1"
                                                               <?php echo ($operational_settings['require_manager_approval'] ?? 1) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Require Manager Approval</label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="update_branch_settings" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax & Fees -->
                            <div class="tab-pane fade" id="tax">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Tax & Service Charges</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Tax Rate (%) *</label>
                                                    <input type="number" step="0.01" class="form-control"
                                                           name="tax_settings[tax_rate]"
                                                           value="<?php echo safe_number_format($tax_settings['tax_rate'] ?? 7.5, 2); ?>"
                                                           required min="0" max="100">
                                                    <small class="form-text text-muted">Tax percentage to apply to sales</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Service Charge (%)</label>
                                                    <input type="number" step="0.01" class="form-control"
                                                           name="tax_settings[service_charge]"
                                                           value="<?php echo safe_number_format($tax_settings['service_charge'] ?? 0, 2); ?>"
                                                           min="0" max="100">
                                                    <small class="form-text text-muted">Optional service charge percentage</small>
                                                </div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input type="checkbox" class="form-check-input"
                                                       name="tax_settings[tax_inclusive]" value="1"
                                                       <?php echo ($tax_settings['tax_inclusive'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Prices are tax-inclusive</label>
                                                <small class="form-text text-muted">If checked, displayed prices include tax</small>
                                            </div>
                                            
                                            <button type="submit" name="update_branch_settings" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Tax Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Fuel Type Modal -->
<div class="modal fade" id="addFuelTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Fuel Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Fuel Type Name *</label>
                        <input type="text" class="form-control" name="new_fuel_type" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fuel Code *</label>
                        <input type="text" class="form-control" name="new_fuel_code" required maxlength="20" placeholder="e.g., PS, DL, KN">
                        <small class="form-text text-muted">Short code for the fuel type (2-5 characters)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (USD) *</label>
                        <input type="number" step="0.01" class="form-control" name="new_price_usd" required min="0" value="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (LRD) *</label>
                        <input type="number" step="0.01" class="form-control" name="new_price_lrd" required min="0" value="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fuel_type" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Business hours toggle
    document.querySelectorAll('.day-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const day = this.dataset.day;
            const openInput = document.querySelector(`input[name="business_hours[${day}][open]"]`);
            const closeInput = document.querySelector(`input[name="business_hours[${day}][close]"]`);
            
            if (this.checked) {
                openInput.disabled = true;
                closeInput.disabled = true;
            } else {
                openInput.disabled = false;
                closeInput.disabled = false;
            }
        });
        
        // Initialize on page load
        toggle.dispatchEvent(new Event('change'));
    });
    
    // Save tab state
    const tabEls = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
    tabEls.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            localStorage.setItem('lastTab', event.target.dataset.bsTarget);
        });
    });
    
    // Restore last tab
    const lastTab = localStorage.getItem('lastTab');
    if (lastTab) {
        const triggerEl = document.querySelector(`#settingsTabs button[data-bs-target="${lastTab}"]`);
        if (triggerEl) {
            new bootstrap.Tab(triggerEl).show();
        }
    }
    
    // Auto-calculate LRD price based on USD price
    document.querySelectorAll('input[name^="fuel_prices"][name$="[usd]"]').forEach(function(usdInput) {
        usdInput.addEventListener('input', function() {
            const usdValue = parseFloat(this.value) || 0;
            const name = this.name.replace('[usd]', '[lrd]');
            const lrdInput = document.querySelector(`input[name="${name}"]`);
            if (lrdInput && (!lrdInput.value || parseFloat(lrdInput.value) === usdValue * 150)) {
                lrdInput.value = (usdValue * 150).toFixed(2);
            }
        });
    });
    
    // Validate fuel prices
    document.querySelector('form').addEventListener('submit', function(e) {
        const usdInputs = this.querySelectorAll('input[name^="fuel_prices"][name$="[usd]"]');
        let hasError = false;
        
        usdInputs.forEach(function(input) {
            const value = parseFloat(input.value);
            if (isNaN(value) || value < 0) {
                input.classList.add('is-invalid');
                hasError = true;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Please enter valid fuel prices (non-negative numbers).');
        }
    });
});
</script>

</body>
</html>