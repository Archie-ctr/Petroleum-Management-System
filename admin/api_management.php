<?php
require_once '../includes/header.php';
require_once '../includes/security.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Handle API operations
$success_message = '';
$error_message = '';
$csrf_token = generateCSRFToken();

// Generate new API key
if (isset($_POST['generate_key']) && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $api_name = trim((string)($_POST['api_name'] ?? ''));
    $api_purpose = trim((string)($_POST['api_purpose'] ?? ''));
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    $allowed_permissions = ['read_sales', 'create_sales', 'read_inventory', 'read_reports', 'read_users', 'read_branches'];
    $invalid_permissions = !is_array($permissions) || !$permissions;
    if (!$invalid_permissions) {
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !in_array($permission, $allowed_permissions, true)) {
                $invalid_permissions = true;
                break;
            }
        }
    }
    if ($api_name === '' || strlen($api_name) > 100 || $api_purpose === '' || strlen($api_purpose) > 2000 || $invalid_permissions) {
        $error_message = 'Enter a name and purpose, and select at least one valid permission.';
    } else {
    
    // Generate API key
    $api_key = 'gwbc_' . bin2hex(random_bytes(32));
    $secret_key = 'gwbc_secret_' . bin2hex(random_bytes(64));
    $key_id = bin2hex(random_bytes(8));
    
    // Hash keys for storage
    $hashed_api_key = password_hash($api_key, PASSWORD_DEFAULT);
    $hashed_secret = password_hash($secret_key, PASSWORD_DEFAULT);
    
    // Permissions JSON
    $permissions_json = json_encode($permissions);
    
    try {
        $stmt = $conn->prepare("INSERT INTO api_keys (key_id, api_name, api_purpose, api_key_hash, secret_key_hash, permissions, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssssssi", $key_id, $api_name, $api_purpose, $hashed_api_key, $hashed_secret, $permissions_json, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = "API Key generated successfully!<br>
                               <strong>API Key:</strong> $api_key<br>
                               <strong>Secret Key:</strong> $secret_key<br>
                               <strong class='text-danger'>IMPORTANT: Save these keys now. They won't be shown again!</strong>";
        } else {
            $error_message = "Failed to generate API key: " . $conn->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Unable to generate API credentials. Please check the application logs.";
        logError('API key generation failed: ' . $e->getMessage());
    }
    }
} elseif (isset($_POST['generate_key'])) {
    $error_message = 'Your session security token is invalid. Refresh the page and try again.';
}

// Revoke API key
if (($_POST['action'] ?? '') === 'revoke' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $api_id = filter_var($_POST['api_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$api_id) {
        $error_message = 'Invalid API key.';
    } else {
    $stmt = $conn->prepare("UPDATE api_keys SET status = 'revoked', revoked_at = NOW(), revoked_by = ? WHERE id = ? AND status != 'revoked'");
    $stmt->bind_param("ii", $_SESSION['user_id'], $api_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "API Key revoked successfully!";
    } else {
        $error_message = "API key was already revoked or does not exist.";
    }
    $stmt->close();
    }
}

// Enable/Disable API key
if (($_POST['action'] ?? '') === 'toggle' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $api_id = filter_var($_POST['api_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = $_POST['status'] ?? '';
    if (!$api_id || !in_array($status, ['active', 'inactive'], true)) {
        $error_message = 'Invalid API key action.';
    } else {
    $stmt = $conn->prepare("UPDATE api_keys SET status = ? WHERE id = ? AND status IN ('active', 'inactive')");
    $stmt->bind_param("si", $status, $api_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "API key status updated.";
    } else {
        $error_message = "API key was already in that state or does not exist.";
    }
    $stmt->close();
    }
}

// Regenerate secret key
if (($_POST['action'] ?? '') === 'regenerate' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $api_id = filter_var($_POST['api_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$api_id) {
        $error_message = 'Invalid API key.';
    } else {
    
    // Generate new secret key
    $new_secret = 'gwbc_secret_' . bin2hex(random_bytes(64));
    $hashed_secret = password_hash($new_secret, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE api_keys SET secret_key_hash = ?, last_rotated = NOW() WHERE id = ? AND status IN ('active', 'inactive')");
    $stmt->bind_param("si", $hashed_secret, $api_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "Secret Key regenerated successfully!<br>
                           <strong>New Secret Key:</strong> " . htmlspecialchars($new_secret, ENT_QUOTES, 'UTF-8') . "<br>
                           <strong class='text-danger'>IMPORTANT: Save this key now. It won't be shown again!</strong>";
    } elseif ($stmt->errno === 0) {
        $error_message = "This API key is revoked or no longer exists.";
    } else {
        $error_message = "Failed to regenerate secret key";
    }
    $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error_message = 'Your session security token is invalid. Refresh the page and try again.';
}

// Get all API keys
$api_keys_query = "SELECT ak.*, u.full_name as creator_name 
                   FROM api_keys ak 
                   LEFT JOIN users u ON ak.created_by = u.id 
                   ORDER BY ak.created_at DESC";
$api_keys_result = $conn->query($api_keys_query);

// Get API usage statistics
$usage_stats = [];
// Total API calls today
$today_calls_sql = "SELECT COUNT(*) as count FROM api_logs WHERE timestamp >= CURDATE() AND timestamp < CURDATE() + INTERVAL 1 DAY";
$today_calls_result = $conn->query($today_calls_sql);
$usage_stats['today_calls'] = $today_calls_result->fetch_assoc()['count'];

// Total API calls this month
$month_calls_sql = "SELECT COUNT(*) as count FROM api_logs WHERE timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND timestamp < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')";
$month_calls_result = $conn->query($month_calls_sql);
$usage_stats['month_calls'] = $month_calls_result->fetch_assoc()['count'];

// Active API keys
$active_keys_sql = "SELECT COUNT(*) as count FROM api_keys WHERE status = 'active'";
$active_keys_result = $conn->query($active_keys_sql);
$usage_stats['active_keys'] = $active_keys_result->fetch_assoc()['count'];

// API error rate
$error_rate_sql = "SELECT 
                   (SELECT COUNT(*) FROM api_logs WHERE response_code >= 400 AND timestamp >= CURDATE() AND timestamp < CURDATE() + INTERVAL 1 DAY) as errors,
                   (SELECT COUNT(*) FROM api_logs WHERE timestamp >= CURDATE() AND timestamp < CURDATE() + INTERVAL 1 DAY) as total";
$error_rate_result = $conn->query($error_rate_sql);
$error_rate_data = $error_rate_result->fetch_assoc();
$usage_stats['error_rate'] = $error_rate_data['total'] > 0 ? 
    round(($error_rate_data['errors'] / $error_rate_data['total']) * 100, 2) : 0;

// Get recent API calls
$recent_calls_sql = "SELECT al.*, ak.api_name 
                     FROM api_logs al 
                     LEFT JOIN api_keys ak ON al.api_key_id = ak.id 
                     ORDER BY al.timestamp DESC LIMIT 10";
$recent_calls_result = $conn->query($recent_calls_sql);
?>

<style>
    /* API Management Specific Styles */
    .api-stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        transition: transform 0.3s ease;
    }

    .api-stats-card:hover {
        transform: translateY(-5px);
    }

    .api-stats-icon {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .api-stats-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 5px;
    }

    .api-stats-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .api-key-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .api-key-card:hover {
        border-color: #667eea;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
        transform: translateY(-3px);
    }

    .api-key-card.active {
        border-left: 4px solid #10b981;
    }

    .api-key-card.inactive {
        border-left: 4px solid #ef4444;
        opacity: 0.8;
    }

    .api-key-card.revoked {
        border-left: 4px solid #6b7280;
        background: #f9fafb;
    }

    .api-key-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }

    .api-key-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .api-key-status {
        font-size: 0.8rem;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-revoked {
        background: #f3f4f6;
        color: #374151;
    }

    .api-key-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .api-info-item {
        background: #f8fafc;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .api-info-label {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .api-info-value {
        font-size: 0.95rem;
        color: #1e293b;
        font-family: 'Courier New', monospace;
        word-break: break-all;
    }

    .api-permissions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .api-permission-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid #bae6fd;
    }

    .api-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .api-action-btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .api-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-enable {
        background: #d1fae5;
        color: #065f46;
    }

    .btn-disable {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-revoke {
        background: #f3f4f6;
        color: #374151;
    }

    .btn-regenerate {
        background: #fef3c7;
        color: #92400e;
    }

    .btn-view {
        background: #dbeafe;
        color: #1e40af;
    }

    /* API Documentation Styles */
    .api-doc-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .api-endpoint {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        position: relative;
    }

    .endpoint-method {
        position: absolute;
        top: -12px;
        left: 20px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .method-get {
        background: #d1fae5;
        color: #065f46;
    }

    .method-post {
        background: #dbeafe;
        color: #1e40af;
    }

    .method-put {
        background: #fef3c7;
        color: #92400e;
    }

    .method-delete {
        background: #fee2e2;
        color: #991b1b;
    }

    .endpoint-url {
        font-family: 'Courier New', monospace;
        font-size: 1.1rem;
        color: #1e293b;
        margin: 15px 0;
        padding: 10px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .endpoint-description {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }

    .endpoint-params {
        margin-top: 15px;
    }

    .param-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .param-table th {
        background: #f1f5f9;
        padding: 10px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .param-table td {
        padding: 10px;
        border: 1px solid #e2e8f0;
        color: #334155;
    }

    /* Test API Form */
    .api-test-form {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .test-response {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        overflow-x: auto;
        display: none;
    }

    .test-response.show {
        display: block;
    }

    /* Tab Navigation */
    .api-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e2e8f0;
    }

    .api-tab {
        padding: 15px 30px;
        background: none;
        border: none;
        font-size: 1rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: all 0.3s ease;
    }

    .api-tab:hover {
        color: #334155;
    }

    .api-tab.active {
        color: #667eea;
    }

    .api-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 3px 3px 0 0;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Generate API Key Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.show {
        display: flex;
        animation: fadeInOverlay 0.3s ease;
    }

    @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: white;
        border-radius: 15px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
        animation: slideInModal 0.3s ease;
    }

    @keyframes slideInModal {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
        margin-bottom: 25px;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #64748b;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .modal-close:hover {
        color: #ef4444;
    }

    /* Loading Spinner */
    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(102, 126, 234, 0.3);
        border-radius: 50%;
        border-top-color: #667eea;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .api-key-info {
            grid-template-columns: 1fr;
        }

        .api-actions {
            flex-wrap: wrap;
        }

        .api-tabs {
            flex-direction: column;
            gap: 10px;
        }

        .api-tab {
            padding: 12px 20px;
            text-align: left;
            border-radius: 8px;
            background: #f8fafc;
        }

        .api-tab.active {
            background: #667eea;
            color: white;
        }

        .api-tab.active::after {
            display: none;
        }
    }

    /* Custom Scrollbar */
    .modal-content::-webkit-scrollbar {
        width: 8px;
    }

    .modal-content::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .modal-content::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 4px;
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="dashboard-title">API Management</h1>
            <p class="text-muted mb-0">Manage API keys, monitor usage, and integrate with external systems</p>
        </div>
        <div>
            <button class="btn btn-primary" id="generateKeyBtn">
                <i class="fas fa-key me-2"></i> Generate New API Key
            </button>
            <button class="btn btn-outline-info ms-2" id="refreshBtn">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- API Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="api-stats-card">
                <div class="api-stats-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="api-stats-value"><?php echo $usage_stats['active_keys']; ?></div>
                <div class="api-stats-label">Active API Keys</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="api-stats-card">
                <div class="api-stats-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="api-stats-value"><?php echo $usage_stats['today_calls']; ?></div>
                <div class="api-stats-label">API Calls Today</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="api-stats-card">
                <div class="api-stats-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="api-stats-value"><?php echo $usage_stats['month_calls']; ?></div>
                <div class="api-stats-label">This Month</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="api-stats-card">
                <div class="api-stats-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="api-stats-value"><?php echo $usage_stats['error_rate']; ?>%</div>
                <div class="api-stats-label">Error Rate</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="api-tabs">
        <button class="api-tab active" data-tab="keys">API Keys</button>
        <button class="api-tab" data-tab="documentation">Documentation</button>
        <button class="api-tab" data-tab="test">Test API</button>
        <button class="api-tab" data-tab="logs">API Logs</button>
    </div>

    <!-- API Keys Tab -->
    <div class="tab-content active" id="keysTab">
        <?php if ($api_keys_result->num_rows > 0): ?>
            <div class="row">
                <?php while($api_key = $api_keys_result->fetch_assoc()): 
                    $permissions = json_decode($api_key['permissions'], true) ?: [];
                    $status_class = $api_key['status'];
                ?>
                    <div class="col-lg-6 mb-4">
                        <div class="api-key-card <?php echo $status_class; ?>">
                            <div class="api-key-header">
                                <div class="api-key-name">
                                    <?php echo htmlspecialchars($api_key['api_name']); ?>
                                    <span class="api-key-status status-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($api_key['status']); ?>
                                    </span>
                                </div>
                                <div class="text-muted">
                                    <small>Created: <?php echo date('M d, Y', strtotime($api_key['created_at'])); ?></small>
                                </div>
                            </div>

                            <div class="api-key-info">
                                <div class="api-info-item">
                                    <div class="api-info-label">API Key ID</div>
                                    <div class="api-info-value"><?php echo htmlspecialchars($api_key['key_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="api-info-item">
                                    <div class="api-info-label">Created By</div>
                                    <div class="api-info-value">                                    <?php echo htmlspecialchars($api_key['creator_name'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="api-info-item">
                                    <div class="api-info-label">Purpose</div>
                                    <div class="api-info-value"><?php echo htmlspecialchars($api_key['api_purpose']); ?></div>
                                </div>
                                <div class="api-info-item">
                                    <div class="api-info-label">Last Used</div>
                                    <div class="api-info-value">
                                        <?php echo $api_key['last_used'] ? date('M d, H:i', strtotime($api_key['last_used'])) : 'Never'; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($permissions)): ?>
                                <div class="api-info-item">
                                    <div class="api-info-label">Permissions</div>
                                    <div class="api-permissions">
                                        <?php foreach($permissions as $permission): ?>
                                            <span class="api-permission-badge"><?php echo htmlspecialchars($permission, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="api-actions">
                                <?php if ($api_key['status'] == 'active'): ?>
                                    <button class="api-action-btn btn-disable" onclick="toggleApiKey(<?php echo $api_key['id']; ?>, 'disable')">
                                        <i class="fas fa-pause"></i> Disable
                                    </button>
                                    <button class="api-action-btn btn-revoke" onclick="revokeApiKey(<?php echo $api_key['id']; ?>)">
                                        <i class="fas fa-ban"></i> Revoke
                                    </button>
                                <?php elseif ($api_key['status'] == 'inactive'): ?>
                                    <button class="api-action-btn btn-enable" onclick="toggleApiKey(<?php echo $api_key['id']; ?>, 'enable')">
                                        <i class="fas fa-play"></i> Enable
                                    </button>
                                <?php endif; ?>
                                
                                <button class="api-action-btn btn-regenerate" onclick="regenerateSecret(<?php echo $api_key['id']; ?>)">
                                    <i class="fas fa-redo"></i> Regenerate Secret
                                </button>
                                
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No API keys found. Generate your first API key to get started.
            </div>
        <?php endif; ?>
    </div>

    <!-- Documentation Tab -->
    <div class="tab-content" id="documentationTab">
        <div class="api-doc-section">
            <h3 class="mb-4">API Documentation</h3>
            <div class="alert alert-warning">
                These endpoint URLs are integration examples only. This project currently provides API key management and an outbound request tester, but does not include public API routes for these examples.
            </div>
            
            <!-- Authentication -->
            <div class="api-endpoint">
                <span class="endpoint-method method-post">POST</span>
                <h5>Authentication</h5>
                <div class="endpoint-url">https://api.example.com/v1/auth/login</div>
                <p class="endpoint-description">Authenticate and get access token for API requests.</p>
                
                <div class="endpoint-params">
                    <h6>Request Body</h6>
                    <table class="param-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>api_key</td>
                                <td>string</td>
                                <td>Yes</td>
                                <td>Your API Key</td>
                            </tr>
                            <tr>
                                <td>secret</td>
                                <td>string</td>
                                <td>Yes</td>
                                <td>Your Secret Key</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Get Sales -->
            <div class="api-endpoint">
                <span class="endpoint-method method-get">GET</span>
                <h5>Get Sales</h5>
                <div class="endpoint-url">https://api.example.com/v1/sales?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD</div>
                <p class="endpoint-description">Retrieve sales data for a specific date range.</p>
                
                <div class="endpoint-params">
                    <h6>Query Parameters</h6>
                    <table class="param-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>start_date</td>
                                <td>date</td>
                                <td>Yes</td>
                                <td>Start date (YYYY-MM-DD)</td>
                            </tr>
                            <tr>
                                <td>end_date</td>
                                <td>date</td>
                                <td>Yes</td>
                                <td>End date (YYYY-MM-DD)</td>
                            </tr>
                            <tr>
                                <td>branch_id</td>
                                <td>integer</td>
                                <td>No</td>
                                <td>Filter by branch ID</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create Sale -->
            <div class="api-endpoint">
                <span class="endpoint-method method-post">POST</span>
                <h5>Create Sale</h5>
                <div class="endpoint-url">https://api.example.com/v1/sales/create</div>
                <p class="endpoint-description">Create a new sale record.</p>
                
                <div class="endpoint-params">
                    <h6>Request Body</h6>
                    <table class="param-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>branch_id</td>
                                <td>integer</td>
                                <td>Yes</td>
                                <td>Branch ID</td>
                            </tr>
                            <tr>
                                <td>fuel_type_id</td>
                                <td>integer</td>
                                <td>Yes</td>
                                <td>Fuel type ID</td>
                            </tr>
                            <tr>
                                <td>quantity</td>
                                <td>decimal</td>
                                <td>Yes</td>
                                <td>Quantity in liters</td>
                            </tr>
                            <tr>
                                <td>price_per_liter</td>
                                <td>decimal</td>
                                <td>Yes</td>
                                <td>Price per liter</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Get Inventory -->
            <div class="api-endpoint">
                <span class="endpoint-method method-get">GET</span>
                <h5>Get Inventory</h5>
                <div class="endpoint-url">https://api.example.com/v1/inventory</div>
                <p class="endpoint-description">Retrieve current inventory levels.</p>
                
                <div class="endpoint-params">
                    <h6>Response Example</h6>
                    <pre><code>{
    "success": true,
    "data": [
        {
            "tank_id": 1,
            "fuel_type": "Petrol",
            "current_quantity": 5000,
            "capacity": 10000,
            "percentage": 50
        }
    ]
}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Test API Tab -->
    <div class="tab-content" id="testTab">
        <div class="api-test-form">
            <h3 class="mb-4">Test API Endpoint</h3>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">HTTP Method</label>
                    <select class="form-select" id="httpMethod">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Endpoint URL</label>
                    <input type="text" class="form-control" id="endpointUrl" 
                           placeholder="https://your-api-host.example/v1/resource">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Headers</label>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" placeholder="Header Key" id="headerKey" value="Authorization">
                    <input type="text" class="form-control" placeholder="Header Value" id="headerValue" value="Bearer YOUR_API_KEY">
                    <button class="btn btn-outline-secondary" type="button" onclick="addHeader()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div id="headersList"></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Request Body (JSON)</label>
                <textarea class="form-control" id="requestBody" rows="6" placeholder='{"key": "value"}'></textarea>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="testApiRequest()">
                    <i class="fas fa-play me-2"></i> Send Request
                </button>
                <button class="btn btn-outline-secondary" onclick="clearTestForm()">
                    <i class="fas fa-trash me-2"></i> Clear
                </button>
            </div>

            <div class="test-response" id="testResponse"></div>
        </div>
    </div>

    <!-- API Logs Tab -->
    <div class="tab-content" id="logsTab">
        <div class="api-doc-section">
            <h3 class="mb-4">Recent API Calls</h3>
            
            <?php if ($recent_calls_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>API Key</th>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($log = $recent_calls_result->fetch_assoc()): 
                                $status_class = $log['response_code'] >= 400 ? 'text-danger' : 
                                               ($log['response_code'] >= 300 ? 'text-warning' : 'text-success');
                            ?>
                                <tr>
                                    <td><?php echo date('M d, H:i:s', strtotime($log['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['api_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($log['endpoint']); ?></td>
                                    <td><?php echo htmlspecialchars($log['http_method'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="<?php echo $status_class; ?> fw-bold">
                                        <?php echo $log['response_code']; ?>
                                    </td>
                                    <td><?php echo $log['response_time']; ?>ms</td>
                                    <td><?php echo htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No API calls logged yet. Start making API requests to see logs here.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Generate API Key Modal -->
<div class="modal-overlay" id="generateModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        
        <div class="modal-header">
            <h3 class="modal-title">Generate New API Key</h3>
            <p class="text-muted">Create a new API key for external system integration</p>
        </div>

        <form method="POST" id="apiKeyForm">
            <input type="hidden" name="csrf_token" id="apiCsrfToken" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
                <label class="form-label">API Name *</label>
                <input type="text" class="form-control" name="api_name" required 
                       placeholder="e.g., Mobile App Integration" maxlength="100">
                <div class="form-text">Give a descriptive name for this API key</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Purpose *</label>
                <textarea class="form-control" name="api_purpose" rows="3" required 
                          placeholder="Describe what this API key will be used for..."></textarea>
                <div class="form-text">Be specific about the integration purpose</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Permissions *</label>
                <div class="permissions-grid">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read_sales" id="perm1">
                        <label class="form-check-label" for="perm1">
                            Read Sales Data
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="create_sales" id="perm2">
                        <label class="form-check-label" for="perm2">
                            Create Sales
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read_inventory" id="perm3">
                        <label class="form-check-label" for="perm3">
                            Read Inventory
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read_reports" id="perm4">
                        <label class="form-check-label" for="perm4">
                            Read Reports
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read_users" id="perm5">
                        <label class="form-check-label" for="perm5">
                            Read User Data
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read_branches" id="perm6">
                        <label class="form-check-label" for="perm6">
                            Read Branch Data
                        </label>
                    </div>
                </div>
                <div class="form-text">Select the permissions this API key should have</div>
            </div>

            <div class="mb-4">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> API keys will be shown only once. Please save them securely.
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" name="generate_key" class="btn btn-primary">
                    <i class="fas fa-key me-2"></i> Generate API Key
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab Navigation
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tabs
        const tabs = document.querySelectorAll('.api-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(`${tabId}Tab`).classList.add('active');
            });
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', function() {
            this.innerHTML = '<span class="spinner"></span>';
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });

        // Generate key button
        document.getElementById('generateKeyBtn').addEventListener('click', function() {
            document.getElementById('generateModal').classList.add('show');
        });
    });

    // Close modal
    function closeModal() {
        document.getElementById('generateModal').classList.remove('show');
    }

    // Toggle API key status
    function toggleApiKey(apiId, action) {
        if (confirm(`Are you sure you want to ${action} this API key?`)) {
            submitKeyAction('toggle', apiId, action === 'enable' ? 'active' : 'inactive');
        }
    }

    // Revoke API key
    function revokeApiKey(apiId) {
        if (confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
            submitKeyAction('revoke', apiId);
        }
    }

    // Regenerate secret key
    function regenerateSecret(apiId) {
        if (confirm('Are you sure you want to regenerate the secret key? The old secret will stop working immediately.')) {
            submitKeyAction('regenerate', apiId);
        }
    }

    function submitKeyAction(action, apiId, status = '') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api_management.php';
        const fields = {
            action,
            api_id: String(apiId),
            csrf_token: document.getElementById('apiCsrfToken').value
        };
        if (status) fields.status = status;
        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    // Test API functionality
    const headers = {};

    function addHeader() {
        const key = document.getElementById('headerKey').value.trim();
        const value = document.getElementById('headerValue').value.trim();
        
        if (key && value) {
            headers[key] = value;
            updateHeadersList();
            
            // Clear inputs
            document.getElementById('headerKey').value = '';
            document.getElementById('headerValue').value = 'Bearer YOUR_API_KEY';
        }
    }

    function updateHeadersList() {
        const headersList = document.getElementById('headersList');
        headersList.innerHTML = '';
        
        for (const [key, value] of Object.entries(headers)) {
            const headerDiv = document.createElement('div');
            headerDiv.className = 'input-group mb-2';
            [key, value].forEach(text => {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control';
                input.value = text;
                input.readOnly = true;
                headerDiv.appendChild(input);
            });
            const removeButton = document.createElement('button');
            removeButton.className = 'btn btn-outline-danger';
            removeButton.type = 'button';
            removeButton.textContent = 'Remove';
            removeButton.addEventListener('click', () => removeHeader(key));
            headerDiv.appendChild(removeButton);
            headersList.appendChild(headerDiv);
        }
    }

    function removeHeader(key) {
        delete headers[key];
        updateHeadersList();
    }

    async function testApiRequest() {
        const method = document.getElementById('httpMethod').value;
        const url = document.getElementById('endpointUrl').value.trim();
        const body = document.getElementById('requestBody').value.trim();
        const responseDiv = document.getElementById('testResponse');
        
        let parsedUrl;
        try {
            parsedUrl = new URL(url);
        } catch {
            alert('Enter a valid absolute URL.');
            return;
        }
        if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
            alert('Only HTTP and HTTPS endpoints can be tested.');
            return;
        }

        // Show loading
        responseDiv.textContent = 'Loading...';
        responseDiv.classList.add('show');

        try {
            const options = {
                method: method,
                credentials: 'omit',
                headers: {
                    'Content-Type': 'application/json',
                    ...headers
                }
            };

            if (method !== 'GET' && body) {
                options.body = body;
            }

            const response = await fetch(parsedUrl, options);
            const responseData = (await response.text()).slice(0, 20000);

            // Format and display response
            let formattedResponse;
            try {
                const jsonResponse = JSON.parse(responseData);
                formattedResponse = JSON.stringify(jsonResponse, null, 2);
            } catch {
                formattedResponse = responseData;
            }

            responseDiv.textContent = `Status: ${response.status} ${response.statusText}\n\n${formattedResponse}`;
        } catch (error) {
            responseDiv.textContent = `Request failed: ${error.message}`;
        }
    }

    function clearTestForm() {
        document.getElementById('endpointUrl').value = '';
        document.getElementById('requestBody').value = '';
        document.getElementById('testResponse').innerHTML = '';
        document.getElementById('testResponse').classList.remove('show');
        
        // Clear headers
        for (const key in headers) {
            delete headers[key];
        }
        updateHeadersList();
    }

    // Close modal when clicking outside
    document.getElementById('generateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Prevent form submission from closing modal
    document.getElementById('apiKeyForm').addEventListener('submit', function(e) {
        e.stopPropagation();
    });
</script>

<?php
require_once '../includes/footer.php';
?>