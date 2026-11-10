<?php
session_start();
require_once '../config/db.php';
require_once '../includes/security.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

$csrf_token = generateCSRFToken();
// Get logs data with filters
$where_conditions = [];
$params = [];
$param_types = "";

// Filter by date range
$date_from_raw = $_GET['date_from'] ?? '';
$date_to_raw = $_GET['date_to'] ?? '';
$invalid_date_type = !is_string($date_from_raw) || !is_string($date_to_raw);
$date_from = is_string($date_from_raw) ? trim($date_from_raw) : '';
$date_to = is_string($date_to_raw) ? trim($date_to_raw) : '';
$date_error = null;
if ($invalid_date_type || ($date_from !== '' && !isValidDate($date_from)) || ($date_to !== '' && !isValidDate($date_to))) {
    $date_error = 'The date filter is invalid. Use YYYY-MM-DD values.';
} elseif (($date_from === '') !== ($date_to === '') || ($date_from !== '' && $date_from > $date_to)) {
    $date_error = 'Provide both dates and ensure the start date is not after the end date.';
}
if ($date_error !== null) {
    http_response_code(400);
    $date_from = '';
    $date_to = '';
}

require_once '../includes/header.php';
if ($date_from !== '' && $date_to !== '') {
    $where_conditions[] = "DATE(log_time) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= "ss";
}

// Filter by log level
$selected_level = is_string($_GET['level'] ?? null) ? trim($_GET['level']) : 'all';
$selected_module = is_string($_GET['module'] ?? null) ? trim($_GET['module']) : 'all';
$selected_user_id = is_string($_GET['user_id'] ?? null) ? trim($_GET['user_id']) : 'all';
$ip_filter = is_string($_GET['ip_address'] ?? null) ? trim(substr($_GET['ip_address'], 0, 64)) : '';
$search = is_string($_GET['search'] ?? null) ? trim(substr($_GET['search'], 0, 200)) : '';
if ($selected_level !== 'all' && in_array($selected_level, ['debug', 'info', 'warning', 'error', 'critical', 'security', 'audit', 'system'], true)) {
    $where_conditions[] = "log_level = ?";
    $params[] = $selected_level;
    $param_types .= "s";
}

// Filter by module
if ($selected_module !== 'all' && preg_match('/^[a-zA-Z0-9_. -]{1,100}$/', $selected_module)) {
    $where_conditions[] = "module = ?";
    $params[] = $selected_module;
    $param_types .= "s";
}

// Filter by user
if ($selected_user_id !== 'all' && ctype_digit($selected_user_id)) {
    $where_conditions[] = "user_id = ?";
    $params[] = (int)$selected_user_id;
    $param_types .= "i";
}

// Filter by IP
if ($ip_filter !== '') {
    $where_conditions[] = "ip_address LIKE ?";
    $params[] = '%' . $ip_filter . '%';
    $param_types .= "s";
}

// Filter by search
if ($search !== '') {
    $where_conditions[] = "(message LIKE ? OR user_agent LIKE ? OR additional_data LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $param_types .= "sss";
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM system_logs $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_logs = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Pagination
$per_page = 50;
$total_pages = max(1, (int)ceil($total_logs / $per_page));
$page = max(1, min($total_pages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $per_page;

// Get logs with pagination
$logs_sql = "SELECT l.*, u.full_name as user_name, u.username, u.role 
            FROM system_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            $where_clause 
            ORDER BY log_time DESC 
            LIMIT ? OFFSET ?";
$logs_stmt = $conn->prepare($logs_sql);

// Add pagination parameters
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

if (!empty($params)) {
    $logs_stmt->bind_param($param_types, ...$params);
}
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$logs = $logs_result->fetch_all(MYSQLI_ASSOC);
$logs_stmt->close();

// Get distinct modules for filter
$modules_sql = "SELECT DISTINCT module FROM system_logs WHERE module IS NOT NULL ORDER BY module";
$modules_result = $conn->query($modules_sql);
$modules = $modules_result->fetch_all(MYSQLI_ASSOC);

// Get distinct log levels
$levels = [
    'debug', 'info', 'warning', 'error', 'critical', 'security', 'audit', 'system'
];

// Get users for filter
$users_sql = "SELECT id, full_name, username, role FROM users WHERE status = 'active' ORDER BY full_name";
$users_result = $conn->query($users_sql);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total_logs,
    SUM(CASE WHEN log_level = 'error' THEN 1 ELSE 0 END) as error_logs,
    SUM(CASE WHEN log_level = 'warning' THEN 1 ELSE 0 END) as warning_logs,
    SUM(CASE WHEN log_level = 'security' THEN 1 ELSE 0 END) as security_logs,
    SUM(CASE WHEN log_level = 'audit' THEN 1 ELSE 0 END) as audit_logs,
    MIN(log_time) as earliest_log,
    MAX(log_time) as latest_log
    FROM system_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Recent critical logs
$critical_sql = "SELECT l.*, u.full_name 
                FROM system_logs l 
                LEFT JOIN users u ON l.user_id = u.id 
                WHERE log_level IN ('error', 'critical', 'security') 
                ORDER BY log_time DESC 
                LIMIT 10";
$critical_result = $conn->query($critical_sql);
$critical_logs = $critical_result->fetch_all(MYSQLI_ASSOC);

// Log file size
$log_file_size = 0;
$log_file_path = '../logs/system.log';
if (file_exists($log_file_path)) {
    $log_file_size = filesize($log_file_path);
}
?>

    <style>
        /* Logs Page Specific Styles */
        .logs-container {
            padding: 20px;
            background: #f8f9fa;
            min-height: calc(100vh - var(--topbar-height));
        }

        .logs-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .logs-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logs-title i {
            color: var(--primary-green);
            font-size: 2rem;
        }

        .logs-subtitle {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border-left: 5px solid;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card.total { border-left-color: var(--primary-green); }
        .stat-card.errors { border-left-color: #dc3545; }
        .stat-card.warnings { border-left-color: #ffc107; }
        .stat-card.security { border-left-color: #6f42c1; }
        .stat-card.audit { border-left-color: #0dcaf0; }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            margin-bottom: 15px;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }

        .filter-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
            outline: none;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .logs-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-green), #198754);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9rem;
        }

        .logs-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            font-size: 0.9rem;
        }

        .logs-table tr:hover {
            background-color: #f8f9fa;
        }

        .level-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .level-debug { background: #e3f2fd; color: #1976d2; }
        .level-info { background: #e8f5e9; color: #2e7d32; }
        .level-warning { background: #fff3e0; color: #f57c00; }
        .level-error { background: #ffebee; color: #c62828; }
        .level-critical { background: #fce4ec; color: #ad1457; }
        .level-security { background: #f3e5f5; color: #7b1fa2; }
        .level-audit { background: #e0f2f1; color: #00695c; }
        .level-system { background: #eceff1; color: #37474f; }

        .module-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .user-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .user-role {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .log-message {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .log-message.expanded {
            white-space: normal;
            max-width: none;
        }

        .expand-btn {
            background: none;
            border: none;
            color: var(--primary-green);
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0;
        }

        .details-row {
            display: none;
            background: #f8f9fa;
        }

        .details-row.show {
            display: table-row;
        }

        .details-content {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .detail-value {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #6c757d;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            word-break: break-all;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .page-link {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        .page-link.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        .page-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn.primary {
            background: var(--primary-green);
            color: white;
        }

        .action-btn.danger {
            background: #dc3545;
            color: white;
        }

        .action-btn.warning {
            background: #ffc107;
            color: #212529;
        }

        .action-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alerts-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .alerts-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-item {
            padding: 15px;
            border-left: 4px solid;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .alert-item.error { border-left-color: #dc3545; }
        .alert-item.warning { border-left-color: #ffc107; }
        .alert-item.security { border-left-color: #6f42c1; }

        .alert-message {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .alert-details {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            justify-content: space-between;
        }

        .no-logs {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .no-logs i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .export-options {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 200px;
        }

        .export-options.show {
            display: block;
        }

        .export-option {
            display: block;
            width: 100%;
            padding: 10px;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            color: #495057;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .export-option:hover {
            background: #f8f9fa;
        }

        .log-file-info {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .file-stats {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .file-size {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-green);
        }

        .file-path {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .logs-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
    <div class="logs-container">
        <?php if ($date_error !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($date_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <!-- Header -->
        <div class="logs-header">
            <h1 class="logs-title">
                <i class="fas fa-terminal"></i>
                System Logs Management
            </h1>
            <p class="logs-subtitle">Monitor and analyze system activities, errors, and security events</p>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_logs); ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                
                <div class="stat-card errors">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['error_logs']); ?></div>
                    <div class="stat-label">Error Logs</div>
                </div>
                
                <div class="stat-card warnings">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['warning_logs']); ?></div>
                    <div class="stat-label">Warning Logs</div>
                </div>
                
                <div class="stat-card security">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['security_logs']); ?></div>
                    <div class="stat-label">Security Logs</div>
                </div>
                
                <div class="stat-card audit">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['audit_logs']); ?></div>
                    <div class="stat-label">Audit Logs</div>
                </div>
            </div>
        </div>

        <!-- Critical Alerts Panel -->
        <?php if (!empty($critical_logs)): ?>
        <div class="alerts-panel">
            <h3 class="alerts-title">
                <i class="fas fa-bell text-danger"></i>
                Critical Logs (Last 24 Hours)
            </h3>
            <?php foreach ($critical_logs as $alert): ?>
                <div class="alert-item <?php echo htmlspecialchars($alert['log_level'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="alert-message">
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                    <div class="alert-details">
                        <span><?php echo htmlspecialchars($alert['module'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($alert['user_name'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo date('H:i', strtotime($alert['log_time'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Log File Information -->
        <div class="log-file-info">
            <div class="file-stats">
                <div>
                    <div class="file-size"><?php echo formatBytes($log_file_size); ?></div>
                    <div class="file-path">File: <?php echo basename($log_file_path); ?></div>
                </div>
                <div>
                    <small>Earliest: <?php echo $stats['earliest_log'] ? date('Y-m-d', strtotime($stats['earliest_log'])) : 'N/A'; ?></small><br>
                    <small>Latest: <?php echo $stats['latest_log'] ? date('Y-m-d H:i', strtotime($stats['latest_log'])) : 'N/A'; ?></small>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Logs
            </h3>
            
            <form method="GET" action="" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="date" name="date_from" class="filter-input" 
                                   value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="date" name="date_to" class="filter-input" 
                                   value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Log Level</label>
                        <select name="level" class="filter-input">
                            <option value="all">All Levels</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level; ?>" 
                                    <?php echo $selected_level === $level ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($level); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Module</label>
                        <select name="module" class="filter-input">
                            <option value="all">All Modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo htmlspecialchars($module['module'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    <?php echo $selected_module === $module['module'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['module'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">User</label>
                        <select name="user_id" class="filter-input">
                            <option value="all">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $selected_user_id === (string)$user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">IP Address</label>
                        <input type="text" name="ip_address" class="filter-input" 
                               value="<?php echo htmlspecialchars($ip_filter, ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="e.g., 192.168.1.1">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" 
                               value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Search in messages...">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="action-btn primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button type="button" onclick="resetFilters()" class="action-btn secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <div style="position: relative;">
                        <button type="button" onclick="toggleExportOptions()" class="action-btn warning">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="export-options" id="exportOptions">
                            <button type="button" onclick="exportLogs('csv')" class="export-option">
                                <i class="fas fa-file-csv"></i> Export as CSV
                            </button>
                            <button type="button" onclick="exportLogs('json')" class="export-option">
                                <i class="fas fa-file-code"></i> Export as JSON
                            </button>
                            <button type="button" onclick="exportLogs('pdf')" class="export-option">
                                <i class="fas fa-file-pdf"></i> Export as PDF
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-table-container">
            <div class="table-header">
                <div class="table-title">
                    System Logs (<?php echo number_format($total_logs); ?> records)
                </div>
                <div class="table-actions">
                    <div class="action-buttons">
                        <button onclick="clearOldLogs()" class="action-btn danger">
                            <i class="fas fa-trash"></i> Clear Old Logs
                        </button>
                        <button onclick="refreshLogs()" class="action-btn secondary">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($logs)): ?>
                <div class="no-logs">
                    <i class="fas fa-search"></i>
                    <h4>No logs found</h4>
                    <p>Try adjusting your filters or there are no logs in the system.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Level</th>
                            <th>Module</th>
                            <th>User</th>
                            <th>Message</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div><?php echo date('Y-m-d', strtotime($log['log_time'])); ?></div>
                                    <small><?php echo date('H:i:s', strtotime($log['log_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="level-badge level-<?php echo htmlspecialchars($log['log_level'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(strtoupper($log['log_level']), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="module-badge"><?php echo htmlspecialchars($log['module'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo $log['user_name'] ?? 'System'; ?></div>
                                        <?php if ($log['role']): ?>
                                            <div class="user-role"><?php echo $log['role']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="log-message" id="msg_<?php echo $log['id']; ?>">
                                        <?php echo htmlspecialchars(substr($log['message'], 0, 100), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (strlen($log['message']) > 100): ?>
                                            ... <button class="expand-btn" onclick="toggleMessage(<?php echo $log['id']; ?>)">Show more</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <button onclick="toggleDetails(<?php echo $log['id']; ?>)" 
                                            class="action-btn primary" style="padding: 5px 10px; font-size: 0.8rem;">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </td>
                            </tr>
                            <!-- Details Row -->
                            <tr class="details-row" id="details_<?php echo $log['id']; ?>">
                                <td colspan="7">
                                    <div class="details-content">
                                        <div class="details-grid">
                                            <div class="detail-item">
                                                <div class="detail-label">Log ID</div>
                                                <div class="detail-value"><?php echo $log['id']; ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Browser/Agent</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($log['user_agent'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Request URL</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($log['request_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Additional Data</div>
                                                <div class="detail-value">
                                                    <?php 
                                                    $data = json_decode($log['additional_data'], true);
                                                    if ($data) {
                                                        echo '<pre style="margin:0; font-size:12px;">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
                                                    } else {
                                                        echo $log['additional_data'] ? htmlspecialchars($log['additional_data']) : 'No additional data';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="page-info">
                        Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $per_page, $total_logs); ?> of <?php echo number_format($total_logs); ?> logs
                    </div>
                    
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle message expansion
        function toggleMessage(logId) {
            const messageDiv = document.getElementById('msg_' + logId);
            const button = messageDiv.querySelector('.expand-btn');
            
            if (messageDiv.classList.contains('expanded')) {
                messageDiv.classList.remove('expanded');
                button.textContent = 'Show more';
                // Truncate text
                const fullText = messageDiv.dataset.fullText;
                messageDiv.textContent = fullText.substring(0, 100) + '... ';
                messageDiv.appendChild(button);
            } else {
                // Store full text if not already stored
                if (!messageDiv.dataset.fullText) {
                    messageDiv.dataset.fullText = messageDiv.textContent.replace('... Show more', '');
                }
                messageDiv.classList.add('expanded');
                button.textContent = 'Show less';
                messageDiv.textContent = messageDiv.dataset.fullText + ' ';
                messageDiv.appendChild(button);
            }
        }

        // Toggle log details
        function toggleDetails(logId) {
            const detailsRow = document.getElementById('details_' + logId);
            detailsRow.classList.toggle('show');
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // Refresh logs
        function refreshLogs() {
            window.location.reload();
        }

        // Toggle export options
        function toggleExportOptions() {
            const options = document.getElementById('exportOptions');
            options.classList.toggle('show');
        }

        // Close export options when clicking outside
        document.addEventListener('click', function(event) {
            const exportOptions = document.getElementById('exportOptions');
            const exportBtn = event.target.closest('.action-btn.warning');
            
            if (!exportBtn && !exportOptions.contains(event.target)) {
                exportOptions.classList.remove('show');
            }
        });

        // Export logs
        function exportLogs(format) {
            if (format === 'pdf') {
                window.print();
                return;
            }

            // Build export URL with current filters
            const params = new URLSearchParams(window.location.search);
            params.append('export', format);
            
            // Close export options
            document.getElementById('exportOptions').classList.remove('show');
            
            // Show loading
            showNotification('Preparing export...', 'info');
            
            // Make export request
            window.location.href = 'export_logs.php?' + params.toString();
        }

        // Clear old logs
        function clearOldLogs() {
            if (confirm('Are you sure you want to clear logs older than 30 days? This action cannot be undone.')) {
                fetch('clear_logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        days: 30,
                        confirm: true,
                        csrf_token: <?php echo json_encode($csrf_token); ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message || 'Logs cleared successfully!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showNotification(data.message || data.error || 'Failed to clear logs', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
            `;
            notification.append(document.createTextNode(message));
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-close';
            closeButton.setAttribute('data-bs-dismiss', 'alert');
            notification.appendChild(closeButton);
            
            // Add to body
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Initialize tooltips
        $(document).ready(function() {
            document.querySelectorAll('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').forEach(function(element) {
                bootstrap.Tooltip.getOrCreateInstance(element);
            });
        });
    </script>

<?php
// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>