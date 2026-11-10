<?php
session_start();
require_once '../config/db.php';

// Check if user has admin access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Initialize variables
$sms_settings = [];
$sms_providers = [
    'twilio' => 'Twilio',
    'africas_talking' => 'Africa\'s Talking',
    'nexmo' => 'Vonage (Nexmo)',
    'custom' => 'Custom API'
];

// Load existing SMS settings
$settings_sql = "SELECT * FROM sms_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
if ($settings_result->num_rows > 0) {
    $sms_settings = $settings_result->fetch_assoc();
} else {
    // Default SMS settings
    $sms_settings = [
        'provider' => 'africas_talking',
        'status' => 'inactive',
        'api_key' => '',
        'api_secret' => '',
        'sender_id' => 'GODWILLING',
        'test_mode' => 1,
        'test_phone' => '',
        'balance' => 0,
        'sms_rate' => 2.5,
        'daily_limit' => 100,
        'character_limit' => 160,
        'unicode_support' => 1,
        'delivery_reports' => 1,
        'auto_recharge' => 0,
        'recharge_threshold' => 10,
        'recharge_amount' => 50,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_settings':
            // Save SMS settings
            $provider = $conn->real_escape_string($_POST['provider']);
            $status = $conn->real_escape_string($_POST['status']);
            $api_key = $conn->real_escape_string($_POST['api_key']);
            $api_secret = $conn->real_escape_string($_POST['api_secret']);
            $sender_id = $conn->real_escape_string($_POST['sender_id']);
            $test_mode = isset($_POST['test_mode']) ? 1 : 0;
            $test_phone = $conn->real_escape_string($_POST['test_phone']);
            $sms_rate = floatval($_POST['sms_rate']);
            $daily_limit = intval($_POST['daily_limit']);
            $character_limit = intval($_POST['character_limit']);
            $unicode_support = isset($_POST['unicode_support']) ? 1 : 0;
            $delivery_reports = isset($_POST['delivery_reports']) ? 1 : 0;
            $auto_recharge = isset($_POST['auto_recharge']) ? 1 : 0;
            $recharge_threshold = floatval($_POST['recharge_threshold']);
            $recharge_amount = floatval($_POST['recharge_amount']);
            
            // Check if settings exist
            $check_sql = "SELECT id FROM sms_settings WHERE id = 1";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                // Update existing settings
                $update_sql = "UPDATE sms_settings SET 
                    provider = '$provider',
                    status = '$status',
                    api_key = '$api_key',
                    api_secret = '$api_secret',
                    sender_id = '$sender_id',
                    test_mode = $test_mode,
                    test_phone = '$test_phone',
                    sms_rate = $sms_rate,
                    daily_limit = $daily_limit,
                    character_limit = $character_limit,
                    unicode_support = $unicode_support,
                    delivery_reports = $delivery_reports,
                    auto_recharge = $auto_recharge,
                    recharge_threshold = $recharge_threshold,
                    recharge_amount = $recharge_amount,
                    updated_at = NOW()
                    WHERE id = 1";
            } else {
                // Insert new settings
                $update_sql = "INSERT INTO sms_settings SET 
                    provider = '$provider',
                    status = '$status',
                    api_key = '$api_key',
                    api_secret = '$api_secret',
                    sender_id = '$sender_id',
                    test_mode = $test_mode,
                    test_phone = '$test_phone',
                    sms_rate = $sms_rate,
                    daily_limit = $daily_limit,
                    character_limit = $character_limit,
                    unicode_support = $unicode_support,
                    delivery_reports = $delivery_reports,
                    auto_recharge = $auto_recharge,
                    recharge_threshold = $recharge_threshold,
                    recharge_amount = $recharge_amount,
                    created_at = NOW(),
                    updated_at = NOW()";
            }
            
            if ($conn->query($update_sql)) {
                $_SESSION['success_message'] = 'SMS settings updated successfully!';
                // Update local settings array
                $sms_settings = array_merge($sms_settings, $_POST);
                $sms_settings['id'] = 1;
            } else {
                $_SESSION['error_message'] = 'Failed to update SMS settings: ' . $conn->error;
            }
            break;
            
        case 'test_sms':
            // Send test SMS
            $test_number = $conn->real_escape_string($_POST['test_number']);
            $test_message = $conn->real_escape_string($_POST['test_message']);
            
            // Record test attempt
            $log_sql = "INSERT INTO sms_logs (phone_number, message, status, type, created_at) 
                        VALUES ('$test_number', '$test_message', 'pending', 'test', NOW())";
            $conn->query($log_sql);
            $log_id = $conn->insert_id;
            
            // Simulate SMS sending (in production, integrate with actual SMS gateway)
            $success = false;
            $status_msg = '';
            
            if ($sms_settings['status'] === 'active' && !empty($test_number)) {
                // In a real application, you would call the SMS provider's API here
                // For now, we'll simulate success
                $success = true;
                $status_msg = 'Test message sent successfully!';
                
                // Update log
                $update_log = "UPDATE sms_logs SET status = 'sent', response = 'Test successful' WHERE id = $log_id";
                $conn->query($update_log);
            } else {
                $status_msg = 'SMS service is not active or test number is invalid';
                $update_log = "UPDATE sms_logs SET status = 'failed', response = '$status_msg' WHERE id = $log_id";
                $conn->query($update_log);
            }
            
            if ($success) {
                $_SESSION['success_message'] = $status_msg;
            } else {
                $_SESSION['error_message'] = $status_msg;
            }
            break;
            
        case 'check_balance':
            // Check SMS balance (simulated)
            $balance = rand(50, 500); // Simulated balance
            $update_balance = "UPDATE sms_settings SET balance = $balance, updated_at = NOW() WHERE id = 1";
            if ($conn->query($update_balance)) {
                $sms_settings['balance'] = $balance;
                $_SESSION['success_message'] = "SMS Balance: $balance credits";
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: sms_settings.php');
    exit();
}

// Get SMS usage statistics
$today = date('Y-m-d');
$stats_sql = "
    SELECT 
        COUNT(*) as total_sent_today,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_today,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_today,
        COUNT(DISTINCT phone_number) as unique_recipients_today
    FROM sms_logs 
    WHERE DATE(created_at) = '$today'
    UNION ALL
    SELECT 
        COUNT(*) as total_sent_month,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_month,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_month,
        COUNT(DISTINCT phone_number) as unique_recipients_month
    FROM sms_logs 
    WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
";
$stats_result = $conn->query($stats_sql);
$today_stats = $stats_result->fetch_assoc();
$month_stats = $stats_result->fetch_assoc();

// Get recent SMS logs
$logs_sql = "SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 10";
$logs_result = $conn->query($logs_sql);

// Get SMS templates
$templates_sql = "SELECT * FROM sms_templates WHERE status = 'active' ORDER BY name";
$templates_result = $conn->query($templates_sql);

// Get SMS categories
$categories_sql = "SELECT category, COUNT(*) as count FROM sms_logs GROUP BY category ORDER BY count DESC";
$categories_result = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Settings - God Willing Business Center</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #198754;
            --primary-yellow: #FFD700;
        }
        
        .sms-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .sms-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .sms-card-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .sms-stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .sms-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .sms-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .sms-stat-value {
            font-weight: 800;
            font-size: 1.8rem;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .sms-stat-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }
        
        .provider-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .provider-active {
            background: rgba(25, 135, 84, 0.1);
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }
        
        .provider-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 2px solid #dc3545;
        }
        
        .sms-test-area {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            border: 2px dashed #dee2e6;
        }
        
        .sms-template-card {
            border-left: 4px solid var(--primary-green);
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        
        .sms-template-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .sms-log-item {
            padding: 12px 15px;
            border-left: 3px solid;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sms-log-item:hover {
            transform: translateX(3px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .log-status-sent {
            border-left-color: var(--primary-green);
            background: rgba(25, 135, 84, 0.05);
        }
        
        .log-status-failed {
            border-left-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }
        
        .log-status-pending {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }
        
        .sms-balance-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .sms-balance-value {
            font-weight: 800;
            font-size: 3rem;
            line-height: 1;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .sms-balance-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary-green);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .setting-row {
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .setting-row:hover {
            border-color: var(--primary-green);
            box-shadow: 0 3px 10px rgba(25, 135, 84, 0.1);
        }
        
        @media (max-width: 768px) {
            .sms-stat-card {
                margin-bottom: 15px;
            }
            
            .sms-balance-value {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-wrapper">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1"><i class="fas fa-sms me-2"></i> SMS Settings</h1>
                    <p class="text-muted mb-0">Configure and manage SMS notifications for God Willing Business Center</p>
                </div>
                <div>
                    <button class="btn btn-success" onclick="checkBalance()">
                        <i class="fas fa-sync-alt me-2"></i> Check Balance
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="sms-stat-card">
                        <div class="d-flex align-items-center">
                            <div class="sms-stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div>
                                <div class="sms-stat-value text-primary">
                                    <?php echo $today_stats['total_sent_today'] ?? 0; ?>
                                </div>
                                <div class="sms-stat-label">Sent Today</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="sms-stat-card">
                        <div class="d-flex align-items-center">
                            <div class="sms-stat-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div class="sms-stat-value text-success">
                                    <?php echo $today_stats['successful_today'] ?? 0; ?>
                                </div>
                                <div class="sms-stat-label">Successful Today</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="sms-stat-card">
                        <div class="d-flex align-items-center">
                            <div class="sms-stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="sms-stat-value text-warning">
                                    <?php echo $today_stats['unique_recipients_today'] ?? 0; ?>
                                </div>
                                <div class="sms-stat-label">Unique Recipients</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="sms-card">
                        <div class="sms-balance-card">
                            <div class="sms-balance-label">Available SMS Credits</div>
                            <div class="sms-balance-value"><?php echo $sms_settings['balance'] ?? 0; ?></div>
                            <div class="mb-3">
                                <span class="provider-badge <?php echo $sms_settings['status'] === 'active' ? 'provider-active' : 'provider-inactive'; ?>">
                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($sms_settings['status'] ?? 'inactive'); ?>
                                </span>
                            </div>
                            <button class="btn btn-light btn-sm" onclick="rechargeBalance()">
                                <i class="fas fa-coins me-1"></i> Recharge Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Left Column: Settings Form -->
                <div class="col-lg-8">
                    <div class="card sms-card">
                        <div class="card-header sms-card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-cog me-2"></i> SMS Gateway Configuration</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $_SESSION['success_message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['success_message']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $_SESSION['error_message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['error_message']); ?>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="smsSettingsForm">
                                <input type="hidden" name="action" value="save_settings">
                                
                                <!-- Provider Selection -->
                                <div class="setting-row">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">SMS Provider</label>
                                            <p class="text-muted small mb-0">Select your SMS service provider</p>
                                        </div>
                                        <div class="col-md-8">
                                            <select class="form-select" name="provider" id="providerSelect">
                                                <?php foreach ($sms_providers as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($sms_settings['provider'] ?? '') === $value ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Service Status -->
                                <div class="setting-row">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Service Status</label>
                                            <p class="text-muted small mb-0">Enable or disable SMS service</p>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="toggle-switch">
                                                    <input type="checkbox" name="status" value="active" 
                                                        id="statusToggle" <?php echo ($sms_settings['status'] ?? '') === 'active' ? 'checked' : ''; ?>>
                                                    <label class="toggle-slider" for="statusToggle"></label>
                                                </div>
                                                <span class="fw-medium">
                                                    <?php echo ($sms_settings['status'] ?? '') === 'active' ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- API Credentials -->
                                <div class="setting-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">API Credentials</label>
                                            <p class="text-muted small mb-0">Provider API keys and secrets</p>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">API Key / Username</label>
                                                <input type="password" class="form-control" name="api_key" 
                                                    value="<?php echo htmlspecialchars($sms_settings['api_key'] ?? ''); ?>"
                                                    placeholder="Enter API key or username">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">API Secret / Password</label>
                                                <input type="password" class="form-control" name="api_secret" 
                                                    value="<?php echo htmlspecialchars($sms_settings['api_secret'] ?? ''); ?>"
                                                    placeholder="Enter API secret or password">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Sender ID</label>
                                                <input type="text" class="form-control" name="sender_id" 
                                                    value="<?php echo htmlspecialchars($sms_settings['sender_id'] ?? ''); ?>"
                                                    placeholder="e.g., GODWILLING" maxlength="11">
                                                <small class="text-muted">Max 11 characters. Usually your business name</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Test Mode -->
                                <div class="setting-row">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Test Mode</label>
                                            <p class="text-muted small mb-0">Enable for testing without sending real SMS</p>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="toggle-switch">
                                                    <input type="checkbox" name="test_mode" value="1" 
                                                        id="testModeToggle" <?php echo ($sms_settings['test_mode'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="toggle-slider" for="testModeToggle"></label>
                                                </div>
                                                <span class="fw-medium">
                                                    <?php echo ($sms_settings['test_mode'] ?? 0) ? 'Enabled' : 'Disabled'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Test Phone Number -->
                                <div class="setting-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Test Phone Number</label>
                                            <p class="text-muted small mb-0">Phone number for test messages</p>
                                        </div>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" name="test_phone" 
                                                value="<?php echo htmlspecialchars($sms_settings['test_phone'] ?? ''); ?>"
                                                placeholder="e.g., +255123456789">
                                            <small class="text-muted">Include country code</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- SMS Settings -->
                                <div class="setting-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">SMS Settings</label>
                                            <p class="text-muted small mb-0">Configure SMS parameters</p>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">SMS Rate (per SMS)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="sms_rate" step="0.01"
                                                            value="<?php echo $sms_settings['sms_rate'] ?? 2.5; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Daily SMS Limit</label>
                                                    <input type="number" class="form-control" name="daily_limit"
                                                        value="<?php echo $sms_settings['daily_limit'] ?? 100; ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Character Limit</label>
                                                    <input type="number" class="form-control" name="character_limit"
                                                        value="<?php echo $sms_settings['character_limit'] ?? 160; ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Unicode Support</label>
                                                    <select class="form-select" name="unicode_support">
                                                        <option value="1" <?php echo ($sms_settings['unicode_support'] ?? 1) ? 'selected' : ''; ?>>Enabled</option>
                                                        <option value="0" <?php echo !($sms_settings['unicode_support'] ?? 1) ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Advanced Settings -->
                                <div class="setting-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Advanced Settings</label>
                                            <p class="text-muted small mb-0">Additional SMS configurations</p>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="delivery_reports" value="1"
                                                        id="deliveryReports" <?php echo ($sms_settings['delivery_reports'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="deliveryReports">
                                                        Enable Delivery Reports
                                                    </label>
                                                </div>
                                                <small class="text-muted">Receive delivery status for sent messages</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="auto_recharge" value="1"
                                                        id="autoRecharge" <?php echo ($sms_settings['auto_recharge'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="autoRecharge">
                                                        Auto Recharge Balance
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Recharge Threshold</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="recharge_threshold" step="0.01"
                                                            value="<?php echo $sms_settings['recharge_threshold'] ?? 10; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Recharge Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="recharge_amount" step="0.01"
                                                            value="<?php echo $sms_settings['recharge_amount'] ?? 50; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Form Actions -->
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-secondary me-2" onclick="testSMS()">
                                        <i class="fas fa-vial me-2"></i> Test SMS
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i> Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- SMS Templates -->
                    <div class="card sms-card mt-4">
                        <div class="card-header sms-card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-file-alt me-2"></i> SMS Templates</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if ($templates_result->num_rows > 0): ?>
                                    <?php while($template = $templates_result->fetch_assoc()): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="sms-template-card">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($template['name']); ?></h6>
                                                    <span class="badge bg-info"><?php echo ucfirst($template['category']); ?></span>
                                                </div>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($template['description']); ?></p>
                                                <pre class="bg-light p-2 rounded small mb-2"><?php echo htmlspecialchars($template['template']); ?></pre>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">Variables: <?php echo $template['variables']; ?></small>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="useTemplate('<?php echo addslashes($template['template']); ?>')">
                                                        <i class="fas fa-copy"></i> Use
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="text-center py-4">
                                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No SMS templates found</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                                                <i class="fas fa-plus me-2"></i> Add Template
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                                    <i class="fas fa-plus me-2"></i> Add New Template
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Test & Logs -->
                <div class="col-lg-4">
                    <!-- Test SMS -->
                    <div class="card sms-card">
                        <div class="card-header sms-card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-vial me-2"></i> Test SMS</h5>
                        </div>
                        <div class="card-body">
                            <div class="sms-test-area">
                                <form id="testSMSForm">
                                    <input type="hidden" name="action" value="test_sms">
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="test_number" 
                                            placeholder="e.g., +255123456789" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="test_message" rows="4" 
                                            placeholder="Enter test message here..." required>Test message from God Willing Business Center SMS system.</textarea>
                                        <small class="text-muted">
                                            Characters: <span id="charCount">0</span>/<span id="charLimit">160</span>
                                        </small>
                                    </div>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-primary w-100" onclick="submitTestSMS()">
                                            <i class="fas fa-paper-plane me-2"></i> Send Test SMS
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent SMS Logs -->
                    <div class="card sms-card mt-4">
                        <div class="card-header sms-card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i> Recent SMS Logs</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($logs_result->num_rows > 0): ?>
                                <?php while($log = $logs_result->fetch_assoc()): ?>
                                    <div class="sms-log-item log-status-<?php echo $log['status']; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($log['phone_number']); ?></strong>
                                                <?php if ($log['type'] === 'test'): ?>
                                                    <span class="badge bg-info ms-2">Test</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="small mb-2"><?php echo htmlspecialchars(substr($log['message'], 0, 60)); ?>...</p>
                                        <div class="d-flex justify-content-between">
                                            <small>
                                                <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                                <?php echo ucfirst($log['status']); ?>
                                            </small>
                                            <small class="text-muted"><?php echo ucfirst($log['category']); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <div class="text-center mt-3">
                                    <a href="sms_logs.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-list me-1"></i> View All Logs
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No SMS logs found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- SMS Categories -->
                    <div class="card sms-card mt-4">
                        <div class="card-header sms-card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-tags me-2"></i> SMS Categories</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if ($categories_result->num_rows > 0): ?>
                                    <?php while($category = $categories_result->fetch_assoc()): ?>
                                        <div class="col-6 mb-3">
                                            <div class="text-center">
                                                <div class="h4 mb-1"><?php echo $category['count']; ?></div>
                                                <small class="text-muted"><?php echo ucfirst($category['category']); ?></small>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="text-center py-3">
                                            <p class="text-muted">No category data available</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Test SMS Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test SMS Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="testResult"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Template Modal -->
    <div class="modal fade" id="addTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add SMS Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTemplateForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Template Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" required>
                                    <option value="notification">Notification</option>
                                    <option value="alert">Alert</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="promotion">Promotion</option>
                                    <option value="transaction">Transaction</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Template Content</label>
                                <textarea class="form-control" name="template" rows="4" required></textarea>
                                <small class="text-muted">
                                    Use variables like {name}, {amount}, {date} etc.
                                </small>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Variables</label>
                                <input type="text" class="form-control" name="variables" 
                                    placeholder="e.g., name, amount, date">
                                <small class="text-muted">Comma-separated list of variables used in template</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#providerSelect').select2({
                theme: 'bootstrap-5'
            });
            
            // Character counter for test message
            $('#testMessage').on('input', function() {
                const charCount = $(this).val().length;
                $('#charCount').text(charCount);
                
                // Update color based on limit
                if (charCount > 160) {
                    $('#charCount').addClass('text-danger');
                } else {
                    $('#charCount').removeClass('text-danger');
                }
            });
            
            // Toggle status label
            $('#statusToggle').on('change', function() {
                const label = $(this).closest('.row').find('.fw-medium');
                label.text(this.checked ? 'Active' : 'Inactive');
            });
            
            // Toggle test mode label
            $('#testModeToggle').on('change', function() {
                const label = $(this).closest('.row').find('.fw-medium');
                label.text(this.checked ? 'Enabled' : 'Disabled');
            });
        });
        
        function checkBalance() {
            $.ajax({
                url: 'sms_settings.php',
                type: 'POST',
                data: { action: 'check_balance' },
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert('Error checking balance. Please try again.');
                }
            });
        }
        
        function testSMS() {
            $('#testModal').modal('show');
        }
        
        function submitTestSMS() {
            const form = $('#testSMSForm');
            const formData = form.serialize();
            
            $.ajax({
                url: 'sms_settings.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert('Error sending test SMS. Please try again.');
                }
            });
        }
        
        function useTemplate(template) {
            $('#testMessage').val(template);
            $('#testMessage').trigger('input');
        }
        
        function rechargeBalance() {
            if (confirm('Recharge SMS balance? This will redirect to payment gateway.')) {
                // In production, redirect to payment gateway
                alert('Redirecting to payment gateway...');
                // window.location.href = 'payment_gateway.php?type=sms_recharge&amount=50';
            }
        }
        
        // Provider configuration help
        $('#providerSelect').on('change', function() {
            const provider = $(this).val();
            let helpText = '';
            
            switch(provider) {
                case 'twilio':
                    helpText = 'Twilio: Get credentials from https://www.twilio.com/console';
                    break;
                case 'africas_talking':
                    helpText = 'Africa\'s Talking: Get credentials from https://account.africastalking.com/';
                    break;
                case 'nexmo':
                    helpText = 'Vonage (Nexmo): Get credentials from https://dashboard.nexmo.com/';
                    break;
                case 'custom':
                    helpText = 'Custom API: Enter your custom API endpoint credentials';
                    break;
            }
            
            // Show help text
            $('#providerHelp').remove();
            if (helpText) {
                $(this).after('<small class="text-muted" id="providerHelp">' + helpText + '</small>');
            }
        });
        
        // Auto save form on change
        let saveTimeout;
        $('input, select, textarea').on('change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function() {
                // Optional: Auto-save functionality
                // $('#smsSettingsForm').submit();
            }, 2000);
        });
    </script>
</body>
</html>