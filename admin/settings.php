<?php
require_once '../includes/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function upsertSetting($conn, $key, $value, $type = 'text') {
    $stmt = $conn->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?) " .
        "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)"
    );
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param('sss', $key, $value, $type);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $valid_csrf = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if (!$valid_csrf) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $exchangeRateInput = trim((string)($_POST['settings']['usd_to_lrd_rate'] ?? ''));
        if ($exchangeRateInput !== ''
            && (!is_numeric($exchangeRateInput) || !is_finite((float)$exchangeRateInput)
                || (float)$exchangeRateInput <= 0)) {
            $error = 'Enter a valid USD to LRD conversion rate greater than zero, or leave it blank.';
        } else {
        // Handle file upload for logo
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
        $upload_dir = '../uploads/logo/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (in_array(strtolower($file_ext), $allowed_ext)) {
            $file_name = 'logo_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_path)) {
                $_POST['settings']['company_logo'] = 'logo/' . $file_name;
                
                // Delete old logo if exists
                if (!empty($settings['company_logo']['setting_value']) && file_exists('../uploads/' . $settings['company_logo']['setting_value'])) {
                    unlink('../uploads/' . $settings['company_logo']['setting_value']);
                }
            }
        }
    }
    
        // Handle text settings
        foreach ($_POST['settings'] ?? [] as $key => $value) {
            $value = trim((string)$value);
            if ($key === 'primary_color' || $key === 'secondary_color' || $key === 'dark_color') {
                continue;
            }
            upsertSetting($conn, $key, $value, 'text');
        }
        
        // Handle theme colors
        if (isset($_POST['theme_colors'])) {
            $theme_aliases = [
                'primary_color' => ['theme_primary', 'primary_color'],
                'secondary_color' => ['theme_secondary', 'secondary_color'],
                'dark_color' => ['theme_dark', 'dark_color'],
            ];

            foreach ($_POST['theme_colors'] as $key => $value) {
                $value = trim((string)$value);
                $keys = $theme_aliases[$key] ?? [$key];

                foreach ($keys as $setting_key) {
                    upsertSetting($conn, $setting_key, $value, 'color');
                }
            }
        }
        
        $success = "Settings updated successfully!";
        }
    }
}

// Get current settings
$settings_sql = "SELECT * FROM settings ORDER BY id";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}
?>
<style>
    :root {
        --primary-green: #2ECC71;
        --dark-green: #27AE60;
        --light-green: #D5F4E6;
        --primary-yellow: #FFD700;
        --gold-yellow: #FFC107;
        --light-yellow: #FFF9C4;
    }
    
    .settings-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        transition: transform 0.3s ease;
    }
    
    .settings-card:hover {
        transform: translateY(-5px);
    }
    
    .settings-card .card-header {
        background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        border: none;
        padding: 20px 25px;
    }
    
    .settings-card .card-header h6 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-card .card-body {
        padding: 30px;
    }
    
    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 0.25rem rgba(46, 204, 113, 0.25);
    }
    
    .form-check-input:checked {
        background-color: var(--primary-green);
        border-color: var(--primary-green);
    }
    
    .color-preview {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 2px solid #ddd;
        cursor: pointer;
    }
    
    .logo-preview {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 5px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
        margin: 0 auto 20px;
    }
    
    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .btn-save {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
        border: none;
        padding: 15px 40px;
        font-weight: 600;
        font-size: 1.1rem;
        border-radius: 50px;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
    }
    
    .btn-save:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(46, 204, 113, 0.4);
    }
    
    .btn-reset {
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        color: #6c757d;
        padding: 15px 40px;
        font-weight: 600;
        font-size: 1.1rem;
        border-radius: 50px;
        transition: all 0.3s ease;
    }
    
    .btn-reset:hover {
        background: #e9ecef;
        color: #495057;
    }
    
    .tab-content {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    
    .nav-tabs {
        border-bottom: 3px solid var(--light-green);
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #666;
        font-weight: 600;
        padding: 15px 30px;
        border-radius: 10px 10px 0 0;
        margin-right: 5px;
        transition: all 0.3s ease;
    }
    
    .nav-tabs .nav-link:hover {
        color: var(--dark-green);
        background: var(--light-green);
    }
    
    .nav-tabs .nav-link.active {
        background: var(--primary-green);
        color: white;
        border: none;
    }
    
    .setting-description {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
        font-style: italic;
    }
    
    .alert-success {
        background: linear-gradient(135deg, var(--light-green) 0%, #d4edda 100%);
        border: 1px solid #c3e6cb;
        color: var(--dark-green);
        border-radius: 10px;
        border-left: 5px solid var(--primary-green);
    }
    
    .color-picker-container {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    @media (max-width: 768px) {
        .settings-card .card-body {
            padding: 20px;
        }
        
        .nav-tabs .nav-link {
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        
        .btn-save, .btn-reset {
            padding: 12px 30px;
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card settings-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-cog"></i> System Settings
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                            <i class="fas fa-building me-2"></i> General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button">
                            <i class="fas fa-palette me-2"></i> Appearance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button">
                            <i class="fas fa-dollar-sign me-2"></i> Financial
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="receipt-tab" data-bs-toggle="tab" data-bs-target="#receipt" type="button">
                            <i class="fas fa-receipt me-2"></i> Receipts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                            <i class="fas fa-envelope me-2"></i> Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                            <i class="fas fa-laptop me-2"></i> System
                        </button>
                    </li>
                </ul>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="tab-content" id="settingsTabContent">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-12 text-center mb-4">
                                    <div class="logo-preview">
                                        <?php if (!empty($settings['company_logo']['setting_value'])): ?>
                                            <img src="../uploads/<?php echo $settings['company_logo']['setting_value']; ?>" 
                                                 alt="Company Logo" id="logoPreview">
                                        <?php else: ?>
                                            <i class="fas fa-building fa-4x" style="color: var(--primary-green);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company_logo" class="form-label">Upload Company Logo</label>
                                        <input type="file" class="form-control" id="company_logo" name="company_logo" 
                                               accept=".jpg,.jpeg,.png,.gif,.svg" onchange="previewLogo(event)">
                                        <div class="setting-description">
                                            Recommended size: 300x300 pixels. Max file size: 2MB
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="company_name" 
                                           name="settings[company_name]" required
                                           value="<?php echo htmlspecialchars($settings['company_name']['setting_value'] ?? "GOD'S WILLING INC"); ?>">
                                    <div class="setting-description">
                                        This name appears throughout the system
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="company_slogan" class="form-label">Company Slogan/Tagline</label>
                                    <input type="text" class="form-control" id="company_slogan" 
                                           name="settings[company_slogan]"
                                           value="<?php echo htmlspecialchars($settings['company_slogan']['setting_value'] ?? 'Petroleum Excellence & Innovation'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="company_address" class="form-label">Company Address</label>
                                    <textarea class="form-control" id="company_address" 
                                              name="settings[company_address]" rows="2"><?php echo htmlspecialchars($settings['company_address']['setting_value'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="company_phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="company_phone" 
                                           name="settings[company_phone]"
                                           value="<?php echo htmlspecialchars($settings['company_phone']['setting_value'] ?? '+254 712 345 678'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="company_email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="company_email" 
                                           name="settings[company_email]"
                                           value="<?php echo htmlspecialchars($settings['company_email']['setting_value'] ?? 'info@godswillinginc.com'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="company_website" class="form-label">Website</label>
                                    <input type="url" class="form-control" id="company_website" 
                                           name="settings[company_website]"
                                           value="<?php echo htmlspecialchars($settings['company_website']['setting_value'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ceo_name" class="form-label">CEO Name</label>
                                    <input type="text" class="form-control" id="ceo_name" 
                                           name="settings[ceo_name]"
                                           value="<?php echo htmlspecialchars($settings['ceo_name']['setting_value'] ?? 'Mr. Karnue'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="coo_name" class="form-label">COO Name</label>
                                    <input type="text" class="form-control" id="coo_name" 
                                           name="settings[coo_name]"
                                           value="<?php echo htmlspecialchars($settings['coo_name']['setting_value'] ?? 'Mrs. Mamie Karnue'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Appearance Settings Tab -->
                        <div class="tab-pane fade" id="appearance" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3" style="color: var(--dark-green);">Theme Colors</h5>
                                    <p class="setting-description mb-4">
                                        Customize the color scheme of your website
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="primary_color" class="form-label">Primary Color</label>
                                    <div class="color-picker-container">
                                        <input type="color" class="form-control form-control-color" id="primary_color" 
                                               name="theme_colors[primary_color]" 
                                               value="<?php echo $settings['primary_color']['setting_value'] ?? '#2ECC71'; ?>" 
                                               title="Choose primary color">
                                        <div class="color-preview" style="background-color: <?php echo $settings['primary_color']['setting_value'] ?? '#2ECC71'; ?>" 
                                             onclick="document.getElementById('primary_color').click();"></div>
                                    </div>
                                    <div class="setting-description">
                                        Main brand color (used for headers, buttons)
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="secondary_color" class="form-label">Secondary Color</label>
                                    <div class="color-picker-container">
                                        <input type="color" class="form-control form-control-color" id="secondary_color" 
                                               name="theme_colors[secondary_color]" 
                                               value="<?php echo $settings['secondary_color']['setting_value'] ?? '#FFD700'; ?>" 
                                               title="Choose secondary color">
                                        <div class="color-preview" style="background-color: <?php echo $settings['secondary_color']['setting_value'] ?? '#FFD700'; ?>" 
                                             onclick="document.getElementById('secondary_color').click();"></div>
                                    </div>
                                    <div class="setting-description">
                                        Accent color (used for highlights, accents)
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="dark_color" class="form-label">Dark Color</label>
                                    <div class="color-picker-container">
                                        <input type="color" class="form-control form-control-color" id="dark_color" 
                                               name="theme_colors[dark_color]" 
                                               value="<?php echo $settings['dark_color']['setting_value'] ?? '#27AE60'; ?>" 
                                               title="Choose dark color">
                                        <div class="color-preview" style="background-color: <?php echo $settings['dark_color']['setting_value'] ?? '#27AE60'; ?>" 
                                             onclick="document.getElementById('dark_color').click();"></div>
                                    </div>
                                    <div class="setting-description">
                                        Dark shade (used for text, borders)
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3">
                                    <label for="theme_mode" class="form-label">Theme Mode</label>
                                    <select class="form-select" id="theme_mode" name="settings[theme_mode]">
                                        <option value="light" <?php echo ($settings['theme_mode']['setting_value'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light Mode</option>
                                        <option value="dark" <?php echo ($settings['theme_mode']['setting_value'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                                        <option value="auto" <?php echo ($settings['theme_mode']['setting_value'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto (System Preference)</option>
                                    </select>
                                    <div class="setting-description">
                                        Choose the overall theme appearance
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sidebar_color" class="form-label">Sidebar Color</label>
                                    <select class="form-select" id="sidebar_color" name="settings[sidebar_color]">
                                        <option value="light" <?php echo ($settings['sidebar_color']['setting_value'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($settings['sidebar_color']['setting_value'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="primary" <?php echo ($settings['sidebar_color']['setting_value'] ?? 'light') == 'primary' ? 'selected' : ''; ?>>Primary Color</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_animations" 
                                               name="settings[enable_animations]" value="1"
                                               <?php echo ($settings['enable_animations']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_animations">
                                            Enable Animations & Transitions
                                        </label>
                                        <div class="setting-description">
                                            Toggle animations and hover effects throughout the system
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Financial Settings Tab -->
                        <div class="tab-pane fade" id="financial" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="currency" class="form-label">Default Currency *</label>
                                    <select class="form-select" id="currency" name="settings[currency]" required>
                                        <option value="KES" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                        <option value="USD" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                        <option value="LRD" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'LRD' ? 'selected' : ''; ?>>Liberian Dollar (LRD)</option>
                                        <option value="EUR" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                        <option value="GBP" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                        <option value="UGX" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'UGX' ? 'selected' : ''; ?>>Ugandan Shilling (UGX)</option>
                                        <option value="TZS" <?php echo ($settings['currency']['setting_value'] ?? 'KES') == 'TZS' ? 'selected' : ''; ?>>Tanzanian Shilling (TZS)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" id="currency_symbol" 
                                           name="settings[currency_symbol]"
                                           value="<?php echo htmlspecialchars($settings['currency_symbol']['setting_value'] ?? 'KSh'); ?>">
                                    <div class="setting-description">
                                        Symbol displayed before/after amounts
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="currency_position" class="form-label">Currency Position</label>
                                    <select class="form-select" id="currency_position" name="settings[currency_position]">
                                        <option value="before" <?php echo ($settings['currency_position']['setting_value'] ?? 'before') == 'before' ? 'selected' : ''; ?>>Before amount ($100)</option>
                                        <option value="after" <?php echo ($settings['currency_position']['setting_value'] ?? 'before') == 'after' ? 'selected' : ''; ?>>After amount (100$)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="usd_to_lrd_rate" class="form-label">USD to Liberian Dollar (LRD) Conversion Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">1 USD =</span>
                                        <input type="number" class="form-control" id="usd_to_lrd_rate"
                                               name="settings[usd_to_lrd_rate]" step="any" inputmode="decimal"
                                               value="<?php echo htmlspecialchars($settings['usd_to_lrd_rate']['setting_value'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="Enter current rate">
                                        <span class="input-group-text">LRD</span>
                                    </div>
                                    <div class="setting-description">
                                        Enter the rate your business uses; it is not fetched automatically. Leave blank if no rate is configured.
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="lrd_to_usd_rate" class="form-label">Reverse Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">1 LRD =</span>
                                        <input type="text" class="form-control" id="lrd_to_usd_rate"
                                               value="<?php echo !empty($settings['usd_to_lrd_rate']['setting_value']) && (float)$settings['usd_to_lrd_rate']['setting_value'] > 0
                                                   ? number_format(1 / (float)$settings['usd_to_lrd_rate']['setting_value'], 8, '.', '')
                                                   : ''; ?>"
                                               placeholder="Set the USD/LRD rate first" readonly>
                                        <span class="input-group-text">USD</span>
                                    </div>
                                    <div class="setting-description">This reciprocal value is calculated from the rate above.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="tax_rate" 
                                           name="settings[tax_rate]" step="0.01" min="0" max="100"
                                           value="<?php echo $settings['tax_rate']['setting_value'] ?? '16'; ?>">
                                    <div class="setting-description">
                                        Default VAT/Tax rate applied to sales
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="decimal_places" class="form-label">Decimal Places</label>
                                    <select class="form-select" id="decimal_places" name="settings[decimal_places]">
                                        <option value="0" <?php echo ($settings['decimal_places']['setting_value'] ?? '2') == '0' ? 'selected' : ''; ?>>0 decimals (100)</option>
                                        <option value="1" <?php echo ($settings['decimal_places']['setting_value'] ?? '2') == '1' ? 'selected' : ''; ?>>1 decimal (100.0)</option>
                                        <option value="2" <?php echo ($settings['decimal_places']['setting_value'] ?? '2') == '2' ? 'selected' : ''; ?>>2 decimals (100.00)</option>
                                        <option value="3" <?php echo ($settings['decimal_places']['setting_value'] ?? '2') == '3' ? 'selected' : ''; ?>>3 decimals (100.000)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="thousand_separator" class="form-label">Thousand Separator</label>
                                    <select class="form-select" id="thousand_separator" name="settings[thousand_separator]">
                                        <option value="," <?php echo ($settings['thousand_separator']['setting_value'] ?? ',') == ',' ? 'selected' : ''; ?>>Comma (1,000)</option>
                                        <option value="." <?php echo ($settings['thousand_separator']['setting_value'] ?? ',') == '.' ? 'selected' : ''; ?>>Period (1.000)</option>
                                        <option value=" " <?php echo ($settings['thousand_separator']['setting_value'] ?? ',') == ' ' ? 'selected' : ''; ?>>Space (1 000)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_tax" 
                                               name="settings[enable_tax]" value="1"
                                               <?php echo ($settings['enable_tax']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_tax">
                                            Enable Tax Calculation
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="round_tax" 
                                               name="settings[round_tax]" value="1"
                                               <?php echo ($settings['round_tax']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="round_tax">
                                            Round Tax Amounts
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Receipt Settings Tab -->
                        <div class="tab-pane fade" id="receipt" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3" style="color: var(--dark-green);">Receipt Configuration</h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="receipt_footer" class="form-label">Receipt Footer Text</label>
                                    <textarea class="form-control" id="receipt_footer" 
                                              name="settings[receipt_footer]" rows="3"><?php echo htmlspecialchars($settings['receipt_footer']['setting_value'] ?? 'Thank you for choosing GOD\'S WILLING INC!'); ?></textarea>
                                    <div class="setting-description">
                                        Text displayed at the bottom of receipts
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="receipt_message" class="form-label">Special Message</label>
                                    <textarea class="form-control" id="receipt_message" 
                                              name="settings[receipt_message]" rows="3"><?php echo htmlspecialchars($settings['receipt_message']['setting_value'] ?? ''); ?></textarea>
                                    <div class="setting-description">
                                        Optional message displayed on receipts
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="receipt_prefix" class="form-label">Receipt Prefix</label>
                                    <input type="text" class="form-control" id="receipt_prefix" 
                                           name="settings[receipt_prefix]"
                                           value="<?php echo htmlspecialchars($settings['receipt_prefix']['setting_value'] ?? 'GWI'); ?>">
                                    <div class="setting-description">
                                        Prefix for receipt numbers (e.g., GWI-001)
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="receipt_next_number" class="form-label">Next Receipt Number</label>
                                    <input type="number" class="form-control" id="receipt_next_number" 
                                           name="settings[receipt_next_number]" min="1"
                                           value="<?php echo $settings['receipt_next_number']['setting_value'] ?? '1'; ?>">
                                    <div class="setting-description">
                                        Starting number for new receipts
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="receipt_copy_count" class="form-label">Copies to Print</label>
                                    <select class="form-select" id="receipt_copy_count" name="settings[receipt_copy_count]">
                                        <option value="1" <?php echo ($settings['receipt_copy_count']['setting_value'] ?? '2') == '1' ? 'selected' : ''; ?>>1 Copy</option>
                                        <option value="2" <?php echo ($settings['receipt_copy_count']['setting_value'] ?? '2') == '2' ? 'selected' : ''; ?>>2 Copies</option>
                                        <option value="3" <?php echo ($settings['receipt_copy_count']['setting_value'] ?? '2') == '3' ? 'selected' : ''; ?>>3 Copies</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="show_tax" 
                                               name="settings[show_tax]" value="1"
                                               <?php echo ($settings['show_tax']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_tax">
                                            Show Tax Breakdown
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="print_barcode" 
                                               name="settings[print_barcode]" value="1"
                                               <?php echo ($settings['print_barcode']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="print_barcode">
                                            Print Barcode
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="print_attendant" 
                                               name="settings[print_attendant]" value="1"
                                               <?php echo ($settings['print_attendant']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="print_attendant">
                                            Print Attendant Name
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="print_datetime" 
                                               name="settings[print_datetime]" value="1"
                                               <?php echo ($settings['print_datetime']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="print_datetime">
                                            Print Date & Time
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="print_logo" 
                                               name="settings[print_logo]" value="1"
                                               <?php echo ($settings['print_logo']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="print_logo">
                                            Print Company Logo
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="auto_print" 
                                               name="settings[auto_print]" value="1"
                                               <?php echo ($settings['auto_print']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_print">
                                            Auto-Print Receipt
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email Settings Tab -->
                        <div class="tab-pane fade" id="email" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3" style="color: var(--dark-green);">Email Configuration</h5>
                                    <p class="setting-description mb-4">
                                        Configure email settings for system notifications and communications
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" 
                                           name="settings[smtp_host]" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host']['setting_value'] ?? 'smtp.gmail.com'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" 
                                           name="settings[smtp_port]" 
                                           value="<?php echo $settings['smtp_port']['setting_value'] ?? '587'; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_username" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp_username" 
                                           name="settings[smtp_username]" 
                                           value="<?php echo htmlspecialchars($settings['smtp_username']['setting_value'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_password" class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="smtp_password" 
                                           name="settings[smtp_password]"
                                           placeholder="Enter new password to change">
                                    <div class="setting-description">
                                        Leave blank to keep current password
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_encryption" class="form-label">Encryption</label>
                                    <select class="form-select" id="smtp_encryption" name="settings[smtp_encryption]">
                                        <option value="tls" <?php echo ($settings['smtp_encryption']['setting_value'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($settings['smtp_encryption']['setting_value'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($settings['smtp_encryption']['setting_value'] ?? 'tls') == 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_timeout" class="form-label">Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="smtp_timeout" 
                                           name="settings[smtp_timeout]" min="10" max="120"
                                           value="<?php echo $settings['smtp_timeout']['setting_value'] ?? '30'; ?>">
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3">
                                    <label for="from_email" class="form-label">From Email Address</label>
                                    <input type="email" class="form-control" id="from_email" 
                                           name="settings[from_email]" 
                                           value="<?php echo htmlspecialchars($settings['from_email']['setting_value'] ?? 'noreply@godswillinginc.com'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="from_name" class="form-label">From Name</label>
                                    <input type="text" class="form-control" id="from_name" 
                                           name="settings[from_name]" 
                                           value="<?php echo htmlspecialchars($settings['from_name']['setting_value'] ?? 'GOD\'S WILLING INC'); ?>">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" 
                                               name="settings[email_notifications]" value="1"
                                               <?php echo ($settings['email_notifications']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-outline-primary" onclick="testEmail()">
                                        <i class="fas fa-paper-plane me-2"></i> Test Email Configuration
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Settings Tab -->
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3" style="color: var(--dark-green);">System Configuration</h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="settings[timezone]">
                                        <option value="Africa/Nairobi" <?php echo ($settings['timezone']['setting_value'] ?? 'Africa/Nairobi') == 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                                        <option value="Africa/Kampala" <?php echo ($settings['timezone']['setting_value'] ?? 'Africa/Nairobi') == 'Africa/Kampala' ? 'selected' : ''; ?>>Africa/Kampala</option>
                                        <option value="Africa/Dar_es_Salaam" <?php echo ($settings['timezone']['setting_value'] ?? 'Africa/Nairobi') == 'Africa/Dar_es_Salaam' ? 'selected' : ''; ?>>Africa/Dar_es_Salaam</option>
                                        <option value="UTC" <?php echo ($settings['timezone']['setting_value'] ?? 'Africa/Nairobi') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_format" class="form-label">Date Format</label>
                                    <select class="form-select" id="date_format" name="settings[date_format]">
                                        <option value="Y-m-d" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                        <option value="d/m/Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="m/d/Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="d M Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'd M Y' ? 'selected' : ''; ?>>DD Mon YYYY</option>
                                        <option value="jS M Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'jS M Y' ? 'selected' : ''; ?>>1st Jan 2024</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="time_format" class="form-label">Time Format</label>
                                    <select class="form-select" id="time_format" name="settings[time_format]">
                                        <option value="H:i:s" <?php echo ($settings['time_format']['setting_value'] ?? 'H:i:s') == 'H:i:s' ? 'selected' : ''; ?>>24-hour (14:30:00)</option>
                                        <option value="h:i:s A" <?php echo ($settings['time_format']['setting_value'] ?? 'H:i:s') == 'h:i:s A' ? 'selected' : ''; ?>>12-hour (02:30:00 PM)</option>
                                        <option value="h:i A" <?php echo ($settings['time_format']['setting_value'] ?? 'H:i:s') == 'h:i A' ? 'selected' : ''; ?>>12-hour short (02:30 PM)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="items_per_page" class="form-label">Items Per Page</label>
                                    <select class="form-select" id="items_per_page" name="settings[items_per_page]">
                                        <option value="10" <?php echo ($settings['items_per_page']['setting_value'] ?? '25') == '10' ? 'selected' : ''; ?>>10 items</option>
                                        <option value="25" <?php echo ($settings['items_per_page']['setting_value'] ?? '25') == '25' ? 'selected' : ''; ?>>25 items</option>
                                        <option value="50" <?php echo ($settings['items_per_page']['setting_value'] ?? '25') == '50' ? 'selected' : ''; ?>>50 items</option>
                                        <option value="100" <?php echo ($settings['items_per_page']['setting_value'] ?? '25') == '100' ? 'selected' : ''; ?>>100 items</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="auto_logout" class="form-label">Auto Logout (minutes)</label>
                                    <input type="number" class="form-control" id="auto_logout" 
                                           name="settings[auto_logout]" min="5" max="240" 
                                           value="<?php echo $settings['auto_logout']['setting_value'] ?? '30'; ?>">
                                    <div class="setting-description">
                                        Inactivity time before automatic logout
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout (hours)</label>
                                    <input type="number" class="form-control" id="session_timeout" 
                                           name="settings[session_timeout]" min="1" max="24" 
                                           value="<?php echo $settings['session_timeout']['setting_value'] ?? '8'; ?>">
                                    <div class="setting-description">
                                        Maximum session duration
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" 
                                               name="settings[maintenance_mode]" value="1"
                                               <?php echo ($settings['maintenance_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            Enable Maintenance Mode
                                        </label>
                                        <div class="setting-description">
                                            System will be unavailable to users except admins
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="debug_mode" 
                                               name="settings[debug_mode]" value="1"
                                               <?php echo ($settings['debug_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="debug_mode">
                                            Enable Debug Mode
                                        </label>
                                        <div class="setting-description">
                                            Show detailed error messages (disable in production)
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> Changes to system settings may affect the application's behavior. 
                                        Some settings require a page refresh or logout/login to take effect.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5">
                        <button type="submit" class="btn btn-save me-3">
                            <i class="fas fa-save me-2"></i> Save All Settings
                        </button>
                        <button type="reset" class="btn btn-reset">
                            <i class="fas fa-undo me-2"></i> Reset Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Logo preview functionality
function previewLogo(event) {
    const input = event.target;
    const preview = document.getElementById('logoPreview');
    const logoPreview = document.querySelector('.logo-preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
            } else {
                // Create new image element if it doesn't exist
                const img = document.createElement('img');
                img.id = 'logoPreview';
                img.src = e.target.result;
                img.alt = 'Logo Preview';
                logoPreview.innerHTML = '';
                logoPreview.appendChild(img);
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Color picker preview update
function updateColorPreview(colorInput, previewId) {
    const color = colorInput.value;
    document.querySelector(previewId).style.backgroundColor = color;
}

// Initialize color pickers
document.addEventListener('DOMContentLoaded', function() {
    const usdToLrdInput = document.getElementById('usd_to_lrd_rate');
    const lrdToUsdInput = document.getElementById('lrd_to_usd_rate');
    const updateReverseRate = function() {
        const rate = Number(usdToLrdInput?.value);
        lrdToUsdInput.value = Number.isFinite(rate) && rate > 0
            ? (1 / rate).toFixed(8)
            : '';
    };
    usdToLrdInput?.addEventListener('input', updateReverseRate);

    // Update color previews when color changes
    document.getElementById('primary_color')?.addEventListener('input', function() {
        updateColorPreview(this, '.color-preview[style*="background-color"]:first-child');
    });
    
    document.getElementById('secondary_color')?.addEventListener('input', function() {
        updateColorPreview(this, '.color-preview[style*="background-color"]:nth-child(2)');
    });
    
    document.getElementById('dark_color')?.addEventListener('input', function() {
        updateColorPreview(this, '.color-preview[style*="background-color"]:nth-child(3)');
    });
    
    // Tab persistence
    const tabTriggers = document.querySelectorAll('#settingsTab button');
    tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            localStorage.setItem('activeSettingsTab', this.id);
        });
    });
    
    // Restore active tab
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        const tab = document.querySelector(`#${activeTab}`);
        if (tab) {
            const tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        let valid = true;
        
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
                
                // Create error message if not exists
                if (!field.nextElementSibling?.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required';
                    field.parentNode.appendChild(errorDiv);
                }
            } else {
                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) errorDiv.remove();
            }
        });
        
        if (!valid) {
            e.preventDefault();
            // Show first tab with error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                const tabId = firstError.closest('.tab-pane')?.id;
                if (tabId) {
                    const tabButton = document.querySelector(`button[data-bs-target="#${tabId}"]`);
                    if (tabButton) {
                        const tabInstance = new bootstrap.Tab(tabButton);
                        tabInstance.show();
                    }
                }
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
});

// Test email configuration
function testEmail() {
    // This would typically make an AJAX call to test email settings
    alert('Email test functionality would send a test email to the configured email address.\nThis feature requires backend implementation.');
}

// Reset form confirmation
document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to reset all changes? Any unsaved changes will be lost.')) {
        e.preventDefault();
    }
});
</script>

<?php
require_once '../includes/footer.php';
