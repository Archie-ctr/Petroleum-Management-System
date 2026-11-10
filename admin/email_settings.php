<?php
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Initialize variables
$message = '';
$message_type = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_general_settings':
            $settings_to_save = [
                'email_provider' => $_POST['email_provider'] ?? '',
                'from_email' => $_POST['from_email'] ?? '',
                'from_name' => $_POST['from_name'] ?? '',
                'reply_to_email' => $_POST['reply_to_email'] ?? '',
                'email_verification_enabled' => isset($_POST['email_verification_enabled']) ? 1 : 0,
                'notifications_enabled' => isset($_POST['notifications_enabled']) ? 1 : 0,
            ];
            
            foreach ($settings_to_save as $key => $value) {
                $update_sql = "UPDATE email_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $message = 'General email settings saved successfully!';
            $message_type = 'success';
            $active_tab = 'general';
            break;
            
        case 'save_smtp_settings':
            // Encrypt password
            $password = $_POST['smtp_password'] ?? '';
            $is_encrypted = !empty($password) ? 1 : 0;
            
            $update_sql = "UPDATE email_settings SET 
                setting_value = CASE 
                    WHEN setting_key = 'smtp_password' AND ? != '' THEN AES_ENCRYPT(?, 'godwilling_secure_key_2024')
                    WHEN setting_key = 'smtp_password' AND ? = '' THEN setting_value
                    ELSE ?
                END,
                is_encrypted = CASE 
                    WHEN setting_key = 'smtp_password' AND ? != '' THEN 1
                    ELSE is_encrypted
                END
                WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password')";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssssss", 
                $password, $password, $password, $_POST['smtp_host'],
                $password, $password, $_POST['smtp_port'], $_POST['smtp_encryption'],
                $_POST['smtp_username']
            );
            $stmt->execute();
            
            // For other fields
            $other_settings = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_username' => $_POST['smtp_username'] ?? '',
            ];
            
            foreach ($other_settings as $key => $value) {
                $update_sql = "UPDATE email_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $message = 'SMTP settings saved successfully!';
            $message_type = 'success';
            $active_tab = 'smtp';
            break;
            
        case 'save_template':
            $template_id = $_POST['template_id'] ?? 0;
            $template_name = $_POST['template_name'] ?? '';
            $template_subject = $_POST['template_subject'] ?? '';
            $template_body = $_POST['template_body'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($template_id > 0) {
                // Update existing template
                $update_sql = "UPDATE email_templates SET 
                    template_name = ?, 
                    template_subject = ?, 
                    template_body = ?, 
                    is_active = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sssii", $template_name, $template_subject, $template_body, $is_active, $template_id);
            } else {
                // Insert new template
                $template_key = strtolower(str_replace(' ', '_', $template_name)) . '_' . time();
                $insert_sql = "INSERT INTO email_templates (template_name, template_key, template_subject, template_body, is_active) 
                              VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssssi", $template_name, $template_key, $template_subject, $template_body, $is_active);
            }
            
            if ($stmt->execute()) {
                $message = 'Email template saved successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error saving template: ' . $conn->error;
                $message_type = 'error';
            }
            $active_tab = 'templates';
            break;
            
        case 'test_email':
            $test_email = $_POST['test_email'] ?? '';
            $test_subject = $_POST['test_subject'] ?? 'Test Email from God Willing Business Center';
            $test_message = $_POST['test_message'] ?? 'This is a test email to verify your email settings.';
            
            if (empty($test_email)) {
                $message = 'Please enter a test email address';
                $message_type = 'error';
            } else {
                // Update test email recipient in settings
                $update_sql = "UPDATE email_settings SET setting_value = ? WHERE setting_key = 'test_email_recipient'";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("s", $test_email);
                $stmt->execute();
                
                // Send test email (simulated - in production, you would use actual email sending)
                $log_sql = "INSERT INTO email_logs (recipient_email, recipient_name, subject, message_type, status) 
                           VALUES (?, ?, ?, 'test', 'sent')";
                $stmt = $conn->prepare($log_sql);
                $recipient_name = 'Test Recipient';
                $stmt->bind_param("sss", $test_email, $recipient_name, $test_subject);
                
                if ($stmt->execute()) {
                    $message = 'Test email sent successfully! (This is a simulation)';
                    $message_type = 'success';
                } else {
                    $message = 'Error sending test email: ' . $conn->error;
                    $message_type = 'error';
                }
            }
            $active_tab = 'testing';
            break;
            
        case 'delete_template':
            $template_id = $_POST['template_id'] ?? 0;
            
            $delete_sql = "DELETE FROM email_templates WHERE id = ? AND template_type != 'system'";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $template_id);
            
            if ($stmt->execute()) {
                $message = 'Template deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting template: ' . $conn->error;
                $message_type = 'error';
            }
            $active_tab = 'templates';
            break;
    }
}

// Load email settings
$settings_sql = "SELECT setting_key, setting_value, setting_group, setting_type, setting_label, 
                CASE WHEN is_encrypted = 1 AND setting_key = 'smtp_password' 
                THEN AES_DECRYPT(setting_value, 'godwilling_secure_key_2024') 
                ELSE setting_value 
                END as decrypted_value
                FROM email_settings ORDER BY setting_group, setting_key";
$settings_result = $conn->query($settings_sql);
$email_settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $email_settings[$row['setting_key']] = $row['decrypted_value'] ?? $row['setting_value'];
}

// Load email templates
$templates_sql = "SELECT * FROM email_templates ORDER BY template_type, template_name";
$templates_result = $conn->query($templates_sql);
$email_templates = [];
while ($row = $templates_result->fetch_assoc()) {
    $email_templates[] = $row;
}

// Load email logs (last 50)
$logs_sql = "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 50";
$logs_result = $conn->query($logs_sql);
$email_logs = [];
while ($row = $logs_result->fetch_assoc()) {
    $email_logs[] = $row;
}

// Get stats for dashboard
$stats_sql = "SELECT 
    COUNT(*) as total_emails,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_emails,
    DATE(created_at) as email_date
    FROM email_logs 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY email_date";
$stats_result = $conn->query($stats_sql);
$email_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $email_stats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - God Willing Business Center</title>
    <style>
        :root {
            --primary-green: <?php echo $theme_primary; ?>;
            --primary-yellow: <?php echo $theme_secondary; ?>;
        }
        
        .email-settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .email-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .email-tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .email-tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .email-tab:hover {
            background: #f8f9fa;
        }
        
        .email-tab.active {
            border-bottom: 3px solid var(--primary-green);
            color: var(--primary-green);
            font-weight: 600;
        }
        
        .email-tab i {
            margin-right: 8px;
        }
        
        .email-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: #198754;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #198754;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .template-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .template-card:hover {
            border-color: var(--primary-green);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .template-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .template-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #842029;
        }
        
        .template-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .log-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .log-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .log-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-sent {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #842029;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #664d03;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .template-editor {
            min-height: 400px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .placeholders-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .placeholder-item {
            display: inline-block;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 4px 8px;
            margin: 4px;
            font-size: 12px;
            color: #495057;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d1e7dd;
            border-left-color: #0f5132;
            color: #0f5132;
        }
        
        .alert-error {
            background: #f8d7da;
            border-left-color: #842029;
            color: #842029;
        }
        
        .alert-info {
            background: #cff4fc;
            border-left-color: #055160;
            color: #055160;
        }
        
        .test-email-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .email-tabs {
                flex-wrap: wrap;
            }
            
            .email-tab {
                flex: 1 0 50%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="email-settings-container">
        <!-- Header -->
        <div class="email-header">
            <h1><i class="fas fa-envelope me-2"></i> Email Settings</h1>
            <p class="mb-0">Configure email notifications, SMTP settings, and manage email templates</p>
        </div>
        
        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : ($message_type === 'success' ? 'success' : 'info'); ?>">
                <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'success' ? 'check-circle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($email_logs); ?></div>
                <div class="stat-label">Total Emails Sent</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($email_templates); ?></div>
                <div class="stat-label">Email Templates</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $email_settings['notifications_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
                <div class="stat-label">Notifications Status</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo ucfirst($email_settings['email_provider']); ?></div>
                <div class="stat-label">Email Provider</div>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="email-tabs">
            <div class="email-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>" onclick="switchTab('general')">
                <i class="fas fa-cog"></i> General Settings
            </div>
            <div class="email-tab <?php echo $active_tab === 'smtp' ? 'active' : ''; ?>" onclick="switchTab('smtp')">
                <i class="fas fa-server"></i> SMTP Settings
            </div>
            <div class="email-tab <?php echo $active_tab === 'templates' ? 'active' : ''; ?>" onclick="switchTab('templates')">
                <i class="fas fa-file-alt"></i> Email Templates
            </div>
            <div class="email-tab <?php echo $active_tab === 'testing' ? 'active' : ''; ?>" onclick="switchTab('testing')">
                <i class="fas fa-vial"></i> Test Email
            </div>
            <div class="email-tab <?php echo $active_tab === 'logs' ? 'active' : ''; ?>" onclick="switchTab('logs')">
                <i class="fas fa-history"></i> Email Logs
            </div>
        </div>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <!-- General Settings Tab -->
            <div id="general-tab" class="email-content" style="<?php echo $active_tab !== 'general' ? 'display: none;' : ''; ?>">
                <h3><i class="fas fa-cog me-2"></i> General Email Settings</h3>
                <p class="text-muted mb-4">Configure basic email settings and notification preferences</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_general_settings">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email Provider</label>
                                <select class="form-select" name="email_provider">
                                    <option value="smtp" <?php echo ($email_settings['email_provider'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                                    <option value="sendmail" <?php echo ($email_settings['email_provider'] ?? '') === 'sendmail' ? 'selected' : ''; ?>>Sendmail</option>
                                    <option value="mail" <?php echo ($email_settings['email_provider'] ?? '') === 'mail' ? 'selected' : ''; ?>>PHP Mail</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">From Email Address</label>
                                <input type="email" class="form-control" name="from_email" 
                                       value="<?php echo htmlspecialchars($email_settings['from_email'] ?? ''); ?>" 
                                       placeholder="noreply@example.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">From Name</label>
                                <input type="text" class="form-control" name="from_name" 
                                       value="<?php echo htmlspecialchars($email_settings['from_name'] ?? ''); ?>" 
                                       placeholder="God Willing Business Center">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reply To Email</label>
                                <input type="email" class="form-control" name="reply_to_email" 
                                       value="<?php echo htmlspecialchars($email_settings['reply_to_email'] ?? ''); ?>" 
                                       placeholder="support@example.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="email_verification_enabled" 
                                       id="email_verification_enabled" value="1" 
                                       <?php echo ($email_settings['email_verification_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_verification_enabled">
                                    Enable Email Verification for New Users
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="notifications_enabled" 
                                       id="notifications_enabled" value="1" 
                                       <?php echo ($email_settings['notifications_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notifications_enabled">
                                    Enable System Email Notifications
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save General Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- SMTP Settings Tab -->
            <div id="smtp-tab" class="email-content" style="<?php echo $active_tab !== 'smtp' ? 'display: none;' : ''; ?>">
                <h3><i class="fas fa-server me-2"></i> SMTP Configuration</h3>
                <p class="text-muted mb-4">Configure your SMTP server settings for sending emails</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> For Gmail, you need to enable "Less secure app access" or use App Passwords.
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_smtp_settings">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" 
                                       value="<?php echo htmlspecialchars($email_settings['smtp_host'] ?? ''); ?>" 
                                       placeholder="smtp.gmail.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">SMTP Port</label>
                                <input type="text" class="form-control" name="smtp_port" 
                                       value="<?php echo htmlspecialchars($email_settings['smtp_port'] ?? ''); ?>" 
                                       placeholder="587">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Encryption Type</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="tls" <?php echo ($email_settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($email_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo empty($email_settings['smtp_encryption'] ?? '') ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" name="smtp_username" 
                                       value="<?php echo htmlspecialchars($email_settings['smtp_username'] ?? ''); ?>" 
                                       placeholder="your-email@gmail.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" name="smtp_password" 
                                       placeholder="Leave blank to keep current password">
                                <small class="text-muted">Leave blank if you don't want to change the password</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save SMTP Settings
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testSmtpConnection()">
                            <i class="fas fa-vial me-2"></i> Test Connection
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Email Templates Tab -->
            <div id="templates-tab" class="email-content" style="<?php echo $active_tab !== 'templates' ? 'display: none;' : ''; ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="fas fa-file-alt me-2"></i> Email Templates</h3>
                        <p class="text-muted mb-0">Manage email templates for various system notifications</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="showTemplateModal()">
                        <i class="fas fa-plus me-2"></i> Add New Template
                    </button>
                </div>
                
                <!-- Template List -->
                <div class="template-list">
                    <?php foreach ($email_templates as $template): ?>
                        <div class="template-card">
                            <div class="template-header">
                                <div>
                                    <div class="template-title"><?php echo htmlspecialchars($template['template_name']); ?></div>
                                    <small class="text-muted">Key: <?php echo $template['template_key']; ?></small>
                                </div>
                                <div>
                                    <span class="template-status <?php echo $template['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Subject:</strong> <?php echo htmlspecialchars($template['template_subject']); ?>
                            </div>
                            
                            <?php if (!empty($template['placeholders'])): ?>
                                <div class="placeholders-list">
                                    <strong>Available Placeholders:</strong><br>
                                    <?php 
                                    $placeholders = explode(',', $template['placeholders']);
                                    foreach ($placeholders as $placeholder):
                                        $placeholder = trim($placeholder);
                                        if (!empty($placeholder)):
                                    ?>
                                        <span class="placeholder-item"><?php echo htmlspecialchars($placeholder); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-sm btn-primary" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                
                                <?php if ($template['template_type'] !== 'system'): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_template">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this template?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Test Email Tab -->
            <div id="testing-tab" class="email-content" style="<?php echo $active_tab !== 'testing' ? 'display: none;' : ''; ?>">
                <h3><i class="fas fa-vial me-2"></i> Test Email Configuration</h3>
                <p class="text-muted mb-4">Send a test email to verify your email settings are working correctly</p>
                
                <div class="test-email-box">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="test_email">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Recipient Email Address</label>
                                    <input type="email" class="form-control" name="test_email" 
                                           value="<?php echo htmlspecialchars($email_settings['test_email_recipient'] ?? ''); ?>" 
                                           placeholder="test@example.com" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Test Subject</label>
                                    <input type="text" class="form-control" name="test_subject" 
                                           value="Test Email from God Willing Business Center">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Test Message</label>
                                    <textarea class="form-control" name="test_message" rows="4">This is a test email to verify your email settings are working correctly. If you receive this email, your configuration is properly set up.</textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane me-2"></i> Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Test Results -->
                <div class="mt-5">
                    <h4><i class="fas fa-history me-2"></i> Recent Test Results</h4>
                    <div class="table-responsive">
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $test_logs = array_filter($email_logs, function($log) {
                                    return $log['message_type'] === 'test';
                                });
                                $test_logs = array_slice($test_logs, 0, 10);
                                
                                foreach ($test_logs as $log): 
                                ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                        <td>
                                            <span class="log-status status-<?php echo $log['status']; ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($test_logs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No test emails sent yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Email Logs Tab -->
            <div id="logs-tab" class="email-content" style="<?php echo $active_tab !== 'logs' ? 'display: none;' : ''; ?>">
                <h3><i class="fas fa-history me-2"></i> Email Logs</h3>
                <p class="text-muted mb-4">View all email sending history and status</p>
                
                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count(array_filter($email_logs, function($log) { return $log['status'] === 'sent'; })); ?></div>
                            <div class="stat-label">Sent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count(array_filter($email_logs, function($log) { return $log['status'] === 'failed'; })); ?></div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count(array_filter($email_logs, function($log) { return $log['status'] === 'pending'; })); ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($email_logs); ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>
                </div>
                
                <!-- Logs Table -->
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($log['recipient_email']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['recipient_name'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($log['message_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                    <td>
                                        <span class="log-status status-<?php echo $log['status']; ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                        <?php if (!empty($log['error_message'])): ?>
                                            <br><small class="text-danger"><?php echo htmlspecialchars($log['error_message']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['error_message'])): ?>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="showError('<?php echo addslashes($log['error_message']); ?>')">
                                                <i class="fas fa-exclamation-triangle"></i> View Error
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($email_logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No email logs found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Template Modal -->
    <div id="templateModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; border-radius: 15px; padding: 30px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle">Add New Email Template</h3>
                <button type="button" onclick="closeTemplateModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <form id="templateForm" method="POST" action="">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_id" id="template_id" value="0">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Template Name</label>
                            <input type="text" class="form-control" name="template_name" id="template_name" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">Email Subject</label>
                            <input type="text" class="form-control" name="template_subject" id="template_subject" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">Email Body (HTML)</label>
                            <textarea class="form-control template-editor" name="template_body" id="template_body" rows="10" required></textarea>
                            <small class="text-muted">Use HTML to format your email template. Use {placeholder} for dynamic content.</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">Placeholders (comma-separated)</label>
                            <input type="text" class="form-control" name="placeholders" id="placeholders" 
                                   placeholder="e.g., {user_name}, {user_email}, {reset_link}">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Template is active</label>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Template
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeTemplateModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div id="errorModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 500px; border-radius: 15px; padding: 30px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Error Details</h3>
                <button type="button" onclick="closeErrorModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="errorContent" style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
            </div>
            <div class="mt-3 text-end">
                <button type="button" class="btn btn-secondary" onclick="closeErrorModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching
        function switchTab(tabName) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Hide all tabs
            document.querySelectorAll('.email-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.email-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to selected tab
            document.querySelectorAll('.email-tab').forEach(tab => {
                if (tab.textContent.includes(tabName.charAt(0).toUpperCase() + tabName.slice(1))) {
                    tab.classList.add('active');
                }
            });
        }
        
        // Template modal functions
        function showTemplateModal() {
            document.getElementById('templateModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Add New Email Template';
            document.getElementById('template_id').value = '0';
            document.getElementById('template_name').value = '';
            document.getElementById('template_subject').value = '';
            document.getElementById('template_body').value = '';
            document.getElementById('placeholders').value = '';
            document.getElementById('is_active').checked = true;
        }
        
        function editTemplate(templateId) {
            // In a real implementation, you would fetch the template data via AJAX
            // For now, we'll redirect to an edit page
            window.location.href = 'email_settings.php?tab=templates&edit=' + templateId;
        }
        
        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }
        
        // Error modal functions
        function showError(errorMessage) {
            document.getElementById('errorModal').style.display = 'block';
            document.getElementById('errorContent').textContent = errorMessage;
        }
        
        function closeErrorModal() {
            document.getElementById('errorModal').style.display = 'none';
        }
        
        // SMTP connection test
        function testSmtpConnection() {
            alert('This would test the SMTP connection in a real implementation. For now, please save your settings and send a test email.');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const templateModal = document.getElementById('templateModal');
            const errorModal = document.getElementById('errorModal');
            
            if (event.target === templateModal) {
                closeTemplateModal();
            }
            if (event.target === errorModal) {
                closeErrorModal();
            }
        }
        
        // Initialize based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            switchTab(tabParam);
        }
    </script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>