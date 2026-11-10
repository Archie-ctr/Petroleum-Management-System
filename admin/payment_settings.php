<?php
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Get current payment settings
$payment_settings = [];
try {
    $settings_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'payment_%'";
    $settings_result = $conn->query($settings_sql);
    while($row = $settings_result->fetch_assoc()) {
        $payment_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = "Error loading payment settings: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Process general payment settings
        $general_settings = [
            'payment_enable_cash' => isset($_POST['enable_cash']) ? '1' : '0',
            'payment_enable_card' => isset($_POST['enable_card']) ? '1' : '0',
            'payment_enable_mobile' => isset($_POST['enable_mobile']) ? '1' : '0',
            'payment_enable_credit' => isset($_POST['enable_credit']) ? '1' : '0',
            'payment_default_method' => $_POST['default_method'] ?? 'cash',
            'payment_currency' => $_POST['currency'] ?? 'USD',
            'payment_decimal_places' => $_POST['decimal_places'] ?? '2',
            'payment_receipt_template' => $_POST['receipt_template'] ?? 'standard',
            'payment_auto_print_receipt' => isset($_POST['auto_print_receipt']) ? '1' : '0',
            'payment_send_sms_receipt' => isset($_POST['send_sms_receipt']) ? '1' : '0',
            'payment_send_email_receipt' => isset($_POST['send_email_receipt']) ? '1' : '0',
        ];
        
        // Process card payment settings
        $card_settings = [
            'payment_card_enable_pos' => isset($_POST['card_enable_pos']) ? '1' : '0',
            'payment_card_provider' => $_POST['card_provider'] ?? 'manual',
            'payment_card_commission_rate' => $_POST['card_commission_rate'] ?? '0',
            'payment_card_commission_type' => $_POST['card_commission_type'] ?? 'percentage',
            'payment_card_min_amount' => $_POST['card_min_amount'] ?? '0',
            'payment_card_max_amount' => $_POST['card_max_amount'] ?? '10000',
        ];
        
        // Process mobile money settings
        $mobile_settings = [
            'payment_mobile_providers' => $_POST['mobile_providers'] ?? '',
            'payment_mobile_commission_rate' => $_POST['mobile_commission_rate'] ?? '0',
            'payment_mobile_commission_type' => $_POST['mobile_commission_type'] ?? 'percentage',
            'payment_mobile_auto_confirm' => isset($_POST['mobile_auto_confirm']) ? '1' : '0',
            'payment_mobile_notification_number' => $_POST['mobile_notification_number'] ?? '',
        ];
        
        // Process credit settings
        $credit_settings = [
            'payment_credit_max_limit' => $_POST['credit_max_limit'] ?? '1000',
            'payment_credit_max_days' => $_POST['credit_max_days'] ?? '30',
            'payment_credit_interest_rate' => $_POST['credit_interest_rate'] ?? '0',
            'payment_credit_require_approval' => isset($_POST['credit_require_approval']) ? '1' : '0',
            'payment_credit_allowed_roles' => $_POST['credit_allowed_roles'] ?? 'admin,manager',
        ];
        
        // Combine all settings
        $all_settings = array_merge($general_settings, $card_settings, $mobile_settings, $credit_settings);
        
        // Update settings in database
        foreach ($all_settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Payment settings updated successfully!";
        
        // Refresh settings
        $settings_result = $conn->query($settings_sql);
        $payment_settings = [];
        while($row = $settings_result->fetch_assoc()) {
            $payment_settings[$row['setting_key']] = $row['setting_value'];
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating payment settings: " . $e->getMessage();
    }
}
?>

<style>
    .payment-settings-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .settings-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .settings-card-header {
        background: linear-gradient(135deg, var(--primary-green), #198754);
        color: white;
        padding: 20px 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .settings-card-header h4 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-card-header h4 i {
        font-size: 1.2rem;
    }
    
    .settings-card-body {
        padding: 25px;
    }
    
    .payment-method-card {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .payment-method-card.active {
        border-color: var(--primary-green);
        background: rgba(25, 135, 84, 0.05);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.1);
    }
    
    .payment-method-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    
    .method-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    
    .method-cash {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }
    
    .method-card {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
    }
    
    .method-mobile {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
    }
    
    .method-credit {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
    }
    
    .form-control-sm {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .form-check-input {
        width: 20px;
        height: 20px;
        margin-right: 10px;
        cursor: pointer;
    }
    
    .form-check-label {
        cursor: pointer;
        font-size: 0.95rem;
        color: #4b5563;
    }
    
    .commission-input-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .commission-input {
        flex: 1;
    }
    
    .commission-type {
        width: 120px;
    }
    
    .badge-status {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .badge-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .badge-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    .test-mode-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    }
    
    .settings-actions {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 20px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }
    
    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.95rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-green), #198754);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
    }
    
    .btn-secondary {
        background: #f3f4f6;
        color: #4b5563;
    }
    
    .btn-secondary:hover {
        background: #e5e7eb;
    }
    
    .btn-test {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        border: 1px solid transparent;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border-color: #10b981;
        color: #065f46;
    }
    
    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #7f1d1d;
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .provider-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    
    .provider-tag {
        background: #e5e7eb;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .provider-tag.active {
        background: rgba(25, 135, 84, 0.1);
        color: var(--primary-green);
    }
    
    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .settings-card-body {
            padding: 20px;
        }
        
        .commission-input-group {
            flex-direction: column;
        }
        
        .commission-type {
            width: 100%;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="dashboard-title">Payment Settings</h1>
            <p class="text-muted mb-0">Configure payment methods, commissions, and transaction settings</p>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="testPaymentIntegration()">
                <i class="fas fa-bolt me-2"></i> Test Integration
            </button>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="payment-settings-container">
        <!-- General Payment Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h4><i class="fas fa-cog"></i> General Payment Settings</h4>
            </div>
            <div class="settings-card-body">
                <div class="section-title">
                    <i class="fas fa-toggle-on"></i> Payment Methods
                </div>
                
                <div class="settings-grid">
                    <!-- Cash Payments -->
                    <div class="payment-method-card <?php echo ($payment_settings['payment_enable_cash'] ?? '1') === '1' ? 'active' : ''; ?>">
                        <div class="method-icon method-cash">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_cash" name="enable_cash" 
                                   <?php echo ($payment_settings['payment_enable_cash'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_cash">
                                <strong>Cash Payments</strong>
                                <span class="badge-status <?php echo ($payment_settings['payment_enable_cash'] ?? '1') === '1' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ($payment_settings['payment_enable_cash'] ?? '1') === '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </label>
                        </div>
                        <p class="text-muted small mb-0">Accept cash payments at all branches</p>
                    </div>
                    
                    <!-- Card Payments -->
                    <div class="payment-method-card <?php echo ($payment_settings['payment_enable_card'] ?? '1') === '1' ? 'active' : ''; ?>">
                        <div class="method-icon method-card">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_card" name="enable_card"
                                   <?php echo ($payment_settings['payment_enable_card'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_card">
                                <strong>Card Payments</strong>
                                <span class="badge-status <?php echo ($payment_settings['payment_enable_card'] ?? '1') === '1' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ($payment_settings['payment_enable_card'] ?? '1') === '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </label>
                        </div>
                        <p class="text-muted small mb-0">Accept debit/credit card payments</p>
                    </div>
                    
                    <!-- Mobile Money -->
                    <div class="payment-method-card <?php echo ($payment_settings['payment_enable_mobile'] ?? '1') === '1' ? 'active' : ''; ?>">
                        <div class="method-icon method-mobile">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_mobile" name="enable_mobile"
                                   <?php echo ($payment_settings['payment_enable_mobile'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_mobile">
                                <strong>Mobile Money</strong>
                                <span class="badge-status <?php echo ($payment_settings['payment_enable_mobile'] ?? '1') === '1' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ($payment_settings['payment_enable_mobile'] ?? '1') === '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </label>
                        </div>
                        <p class="text-muted small mb-0">Accept mobile money payments</p>
                    </div>
                    
                    <!-- Credit Payments -->
                    <div class="payment-method-card <?php echo ($payment_settings['payment_enable_credit'] ?? '0') === '1' ? 'active' : ''; ?>">
                        <div class="method-icon method-credit">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_credit" name="enable_credit"
                                   <?php echo ($payment_settings['payment_enable_credit'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_credit">
                                <strong>Credit Payments</strong>
                                <span class="badge-status <?php echo ($payment_settings['payment_enable_credit'] ?? '0') === '1' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ($payment_settings['payment_enable_credit'] ?? '0') === '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </label>
                        </div>
                        <p class="text-muted small mb-0">Allow credit sales with approval</p>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="default_method"><i class="fas fa-star me-2"></i> Default Payment Method</label>
                            <select class="form-control" id="default_method" name="default_method">
                                <option value="cash" <?php echo ($payment_settings['payment_default_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="card" <?php echo ($payment_settings['payment_default_method'] ?? 'cash') === 'card' ? 'selected' : ''; ?>>Card</option>
                                <option value="mobile_money" <?php echo ($payment_settings['payment_default_method'] ?? 'cash') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="credit" <?php echo ($payment_settings['payment_default_method'] ?? 'cash') === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="currency"><i class="fas fa-dollar-sign me-2"></i> Default Currency</label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo ($payment_settings['payment_currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                <option value="GHS" <?php echo ($payment_settings['payment_currency'] ?? 'USD') === 'GHS' ? 'selected' : ''; ?>>GHS (¢)</option>
                                <option value="EUR" <?php echo ($payment_settings['payment_currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                <option value="GBP" <?php echo ($payment_settings['payment_currency'] ?? 'USD') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="section-title mt-4">
                    <i class="fas fa-receipt"></i> Receipt Settings
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="auto_print_receipt" name="auto_print_receipt"
                                   <?php echo ($payment_settings['payment_auto_print_receipt'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_print_receipt">
                                Auto-print receipts
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="send_sms_receipt" name="send_sms_receipt"
                                   <?php echo ($payment_settings['payment_send_sms_receipt'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="send_sms_receipt">
                                Send SMS receipts
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="send_email_receipt" name="send_email_receipt"
                                   <?php echo ($payment_settings['payment_send_email_receipt'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="send_email_receipt">
                                Send Email receipts
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card Payment Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h4><i class="fas fa-credit-card"></i> Card Payment Settings</h4>
            </div>
            <div class="settings-card-body">
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="card_enable_pos" name="card_enable_pos"
                           <?php echo ($payment_settings['payment_card_enable_pos'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="card_enable_pos">
                        <strong>Enable POS Integration</strong>
                    </label>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="card_provider"><i class="fas fa-network-wired me-2"></i> Card Provider</label>
                            <select class="form-control" id="card_provider" name="card_provider">
                                <option value="manual" <?php echo ($payment_settings['payment_card_provider'] ?? 'manual') === 'manual' ? 'selected' : ''; ?>>Manual Entry</option>
                                <option value="stripe" <?php echo ($payment_settings['payment_card_provider'] ?? 'manual') === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                <option value="paystack" <?php echo ($payment_settings['payment_card_provider'] ?? 'manual') === 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                                <option value="flutterwave" <?php echo ($payment_settings['payment_card_provider'] ?? 'manual') === 'flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-percentage me-2"></i> Commission Rate</label>
                            <div class="commission-input-group">
                                <input type="number" class="form-control commission-input" step="0.01" min="0" max="50" 
                                       id="card_commission_rate" name="card_commission_rate" 
                                       value="<?php echo $payment_settings['payment_card_commission_rate'] ?? '2.5'; ?>">
                                <select class="form-control commission-type" id="card_commission_type" name="card_commission_type">
                                    <option value="percentage" <?php echo ($payment_settings['payment_card_commission_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>%</option>
                                    <option value="fixed" <?php echo ($payment_settings['payment_card_commission_type'] ?? 'percentage') === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="card_min_amount"><i class="fas fa-arrow-down me-2"></i> Minimum Amount</label>
                            <input type="number" class="form-control" step="0.01" min="0" 
                                   id="card_min_amount" name="card_min_amount" 
                                   value="<?php echo $payment_settings['payment_card_min_amount'] ?? '0'; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="card_max_amount"><i class="fas fa-arrow-up me-2"></i> Maximum Amount</label>
                            <input type="number" class="form-control" step="0.01" min="0" 
                                   id="card_max_amount" name="card_max_amount" 
                                   value="<?php echo $payment_settings['payment_card_max_amount'] ?? '10000'; ?>">
                        </div>
                    </div>
                </div>
                
                <?php if (($payment_settings['payment_card_provider'] ?? 'manual') !== 'manual'): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    API configuration for <strong><?php echo ucfirst($payment_settings['payment_card_provider'] ?? 'manual'); ?></strong> 
                    can be found in <a href="api_settings.php">API Settings</a>.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Mobile Money Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h4><i class="fas fa-mobile-alt"></i> Mobile Money Settings</h4>
            </div>
            <div class="settings-card-body">
                <div class="form-group">
                    <label><i class="fas fa-sim-card me-2"></i> Mobile Money Providers</label>
                    <input type="text" class="form-control" id="mobile_providers" name="mobile_providers" 
                           value="<?php echo $payment_settings['payment_mobile_providers'] ?? 'MTN, Vodafone, AirtelTigo'; ?>" 
                           placeholder="Enter providers separated by commas">
                    <small class="text-muted">List all mobile money providers accepted (comma separated)</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-percentage me-2"></i> Commission Rate</label>
                            <div class="commission-input-group">
                                <input type="number" class="form-control commission-input" step="0.01" min="0" max="10" 
                                       id="mobile_commission_rate" name="mobile_commission_rate" 
                                       value="<?php echo $payment_settings['payment_mobile_commission_rate'] ?? '1.5'; ?>">
                                <select class="form-control commission-type" id="mobile_commission_type" name="mobile_commission_type">
                                    <option value="percentage" <?php echo ($payment_settings['payment_mobile_commission_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>%</option>
                                    <option value="fixed" <?php echo ($payment_settings['payment_mobile_commission_type'] ?? 'percentage') === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mobile_notification_number"><i class="fas fa-phone me-2"></i> Notification Number</label>
                            <input type="text" class="form-control" id="mobile_notification_number" name="mobile_notification_number" 
                                   value="<?php echo $payment_settings['payment_mobile_notification_number'] ?? ''; ?>" 
                                   placeholder="e.g., 0551234567">
                            <small class="text-muted">Number to receive payment notifications</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" id="mobile_auto_confirm" name="mobile_auto_confirm"
                           <?php echo ($payment_settings['payment_mobile_auto_confirm'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mobile_auto_confirm">
                        Auto-confirm mobile money payments
                    </label>
                    <small class="text-muted d-block">Automatically mark mobile money payments as confirmed</small>
                </div>
            </div>
        </div>
        
        <!-- Credit Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h4><i class="fas fa-file-invoice-dollar"></i> Credit Sales Settings</h4>
            </div>
            <div class="settings-card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="credit_max_limit"><i class="fas fa-wallet me-2"></i> Maximum Credit Limit</label>
                            <input type="number" class="form-control" step="0.01" min="0" 
                                   id="credit_max_limit" name="credit_max_limit" 
                                   value="<?php echo $payment_settings['payment_credit_max_limit'] ?? '1000'; ?>">
                            <small class="text-muted">Maximum credit amount per customer</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="credit_max_days"><i class="fas fa-calendar-alt me-2"></i> Maximum Credit Days</label>
                            <input type="number" class="form-control" min="1" max="365" 
                                   id="credit_max_days" name="credit_max_days" 
                                   value="<?php echo $payment_settings['payment_credit_max_days'] ?? '30'; ?>">
                            <small class="text-muted">Maximum days before payment is due</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="credit_interest_rate"><i class="fas fa-percentage me-2"></i> Interest Rate (%)</label>
                            <input type="number" class="form-control" step="0.01" min="0" max="100" 
                                   id="credit_interest_rate" name="credit_interest_rate" 
                                   value="<?php echo $payment_settings['payment_credit_interest_rate'] ?? '0'; ?>">
                            <small class="text-muted">Annual interest rate for credit sales</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="credit_require_approval" name="credit_require_approval"
                           <?php echo ($payment_settings['payment_credit_require_approval'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="credit_require_approval">
                        Require approval for credit sales
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="credit_allowed_roles"><i class="fas fa-user-shield me-2"></i> Roles Allowed to Approve Credit</label>
                    <input type="text" class="form-control" id="credit_allowed_roles" name="credit_allowed_roles" 
                           value="<?php echo $payment_settings['payment_credit_allowed_roles'] ?? 'admin,manager'; ?>" 
                           placeholder="Enter roles separated by commas">
                    <small class="text-muted">Roles that can approve credit sales (comma separated)</small>
                </div>
            </div>
        </div>
        
        <!-- Advanced Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h4><i class="fas fa-sliders-h"></i> Advanced Settings</h4>
            </div>
            <div class="settings-card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="decimal_places"><i class="fas fa-calculator me-2"></i> Decimal Places</label>
                            <select class="form-control" id="decimal_places" name="decimal_places">
                                <option value="0" <?php echo ($payment_settings['payment_decimal_places'] ?? '2') === '0' ? 'selected' : ''; ?>>0 (No decimals)</option>
                                <option value="1" <?php echo ($payment_settings['payment_decimal_places'] ?? '2') === '1' ? 'selected' : ''; ?>>1 Decimal</option>
                                <option value="2" <?php echo ($payment_settings['payment_decimal_places'] ?? '2') === '2' ? 'selected' : ''; ?>>2 Decimals</option>
                                <option value="3" <?php echo ($payment_settings['payment_decimal_places'] ?? '2') === '3' ? 'selected' : ''; ?>>3 Decimals</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="receipt_template"><i class="fas fa-receipt me-2"></i> Receipt Template</label>
                            <select class="form-control" id="receipt_template" name="receipt_template">
                                <option value="standard" <?php echo ($payment_settings['payment_receipt_template'] ?? 'standard') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="modern" <?php echo ($payment_settings['payment_receipt_template'] ?? 'standard') === 'modern' ? 'selected' : ''; ?>>Modern</option>
                                <option value="compact" <?php echo ($payment_settings['payment_receipt_template'] ?? 'standard') === 'compact' ? 'selected' : ''; ?>>Compact</option>
                                <option value="detailed" <?php echo ($payment_settings['payment_receipt_template'] ?? 'standard') === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Changes to these settings may affect all branches and users. 
                    Test changes in a staging environment before applying to production.
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="settings-actions">
            <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                <i class="fas fa-undo me-2"></i> Reset to Defaults
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<script>
    function testPaymentIntegration() {
        Swal.fire({
            title: 'Testing Payment Integration',
            text: 'Checking all payment method connections...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        
        // Simulate API testing
        setTimeout(() => {
            $.ajax({
                url: '../api/test_payment_integration.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            html: `
                                <div class="text-start">
                                    <p><i class="fas fa-check-circle text-success me-2"></i> <strong>All systems operational</strong></p>
                                    <div class="mt-3">
                                        ${response.results.map(result => `
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>${result.method}</span>
                                                <span class="badge ${result.status === 'success' ? 'bg-success' : 'bg-danger'}">
                                                    ${result.status === 'success' ? '✓ Connected' : '✗ Failed'}
                                                </span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to test payment integration', 'error');
                }
            });
        }, 1000);
    }
    
    function resetToDefaults() {
        Swal.fire({
            title: 'Reset to Defaults?',
            text: 'This will reset all payment settings to factory defaults.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reset!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to reset endpoint
                window.location.href = 'payments_settings.php?action=reset';
            }
        });
    }
    
    // Live validation for card settings
    document.getElementById('card_min_amount').addEventListener('change', function() {
        const min = parseFloat(this.value) || 0;
        const max = parseFloat(document.getElementById('card_max_amount').value) || 0;
        
        if (min > max) {
            Swal.fire('Warning', 'Minimum amount cannot be greater than maximum amount', 'warning');
            this.value = max;
        }
    });
    
    document.getElementById('card_max_amount').addEventListener('change', function() {
        const max = parseFloat(this.value) || 0;
        const min = parseFloat(document.getElementById('card_min_amount').value) || 0;
        
        if (max < min) {
            Swal.fire('Warning', 'Maximum amount cannot be less than minimum amount', 'warning');
            this.value = min;
        }
    });
    
    // Toggle card provider settings visibility
    document.getElementById('card_provider').addEventListener('change', function() {
        const provider = this.value;
        const apiAlert = document.querySelector('.alert-info');
        
        if (provider === 'manual') {
            if (apiAlert) {
                apiAlert.style.display = 'none';
            }
        } else {
            if (apiAlert) {
                apiAlert.style.display = 'block';
                apiAlert.querySelector('strong').textContent = ucfirst(provider);
            }
        }
    });
    
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Auto-save indicator
    let isSaving = false;
    const form = document.querySelector('form');
    
    form.addEventListener('input', function() {
        if (!isSaving) {
            isSaving = true;
            setTimeout(() => {
                // Show auto-save indicator
                const saveBtn = document.querySelector('.btn-primary');
                const originalHTML = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                
                // Simulate auto-save
                setTimeout(() => {
                    saveBtn.innerHTML = originalHTML;
                    isSaving = false;
                }, 500);
            }, 2000);
        }
    });
</script>

<?php
require_once '../includes/footer.php';
?>