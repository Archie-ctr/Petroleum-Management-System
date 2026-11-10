<?php
session_start();
require_once '../config/db.php';
require_once '../includes/security.php';

// Check if user has admin access
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Handle maintenance actions
$message = '';
$message_type = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Refresh the page and try again.';
        $message_type = 'danger';
    } elseif (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'clear_cache':
                    $cache_dir = '../cache/';
                    $cleared_files = 0;
                    if (is_dir($cache_dir)) {
                        $files = glob($cache_dir . '*.cache') ?: [];
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                if (!unlink($file)) {
                                    throw new RuntimeException('Unable to remove a cache file.');
                                }
                                $cleared_files++;
                            }
                        }
                    }
                    $message = "Cache cleared successfully ($cleared_files files removed).";
                    $message_type = 'success';
                    break;
                    
                case 'optimize_tables':
                    // Optimize database tables
                    $tables_result = $conn->query('SHOW TABLES');
                    $optimized = 0;
                    while ($table_row = $tables_result->fetch_array()) {
                        $table = $table_row[0];
                        $optimize_result = $conn->query("OPTIMIZE TABLE `" . $conn->real_escape_string($table) . "`");
                        if (!$optimize_result) {
                            throw new RuntimeException("Unable to optimize table $table.");
                        }
                        $optimize_result->free();
                        $optimized++;
                    }
                    $message = "Database tables optimized successfully ($optimized tables).";
                    $message_type = 'success';
                    break;
                    
                case 'clear_logs':
                    // Clear old logs (older than 30 days)
                    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
                    $clear_logs_sql = "DELETE FROM system_logs WHERE log_time < ?";
                    $stmt = $conn->prepare($clear_logs_sql);
                    $stmt->bind_param("s", $thirty_days_ago);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Unable to clear old logs: ' . $stmt->error);
                    }
                    $affected_rows = $stmt->affected_rows;
                    $stmt->close();
                    
                    $message = "Cleared $affected_rows log entries older than 30 days!";
                    $message_type = 'success';
                    break;
                    
                case 'check_integrity':
                    // Check database integrity
                    $integrity_report = [];
                    $tables_sql = "SHOW TABLES";
                    $tables_result = $conn->query($tables_sql);
                    
                    while ($table_row = $tables_result->fetch_array()) {
                        $table = $table_row[0];
                        $check_sql = "CHECK TABLE `$table`";
                        $check_result = $conn->query($check_sql);
                        $integrity_report[$table] = $check_result->fetch_assoc();
                    }
                    
                    $_SESSION['integrity_report'] = $integrity_report;
                    $message = 'Database integrity check completed!';
                    $message_type = 'info';
                    break;
                    
                case 'run_backup':
                    // Create database backup
                    $backup_dir = dirname(__DIR__) . '/backups/private';
                    $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
                    
                    if (!is_dir($backup_dir) && !mkdir($backup_dir, 0750, true) && !is_dir($backup_dir)) {
                        throw new RuntimeException('Unable to create the backup directory.');
                    }
                    if (!chmod($backup_dir, 0700)) {
                        throw new RuntimeException('Unable to secure the backup directory.');
                    }
                    
                    // Get all tables
                    $tables = [];
                    $result = $conn->query('SHOW TABLES');
                    while ($row = $result->fetch_row()) {
                        $tables[] = $row[0];
                    }
                    
                    $handle = fopen($backup_file, 'xb');
                    if (!$handle) {
                        throw new RuntimeException('Unable to create the backup file.');
                    }
                    try {
                        $write_backup = static function ($content) use ($handle) {
                            if (fwrite($handle, $content) !== strlen($content)) {
                                throw new RuntimeException('Unable to write the complete database backup.');
                            }
                        };

                        $write_backup("-- God Willing Business Center Database Backup\n");
                        $write_backup("-- Generated: " . date('Y-m-d H:i:s') . "\n");
                        $database_name = $conn->query('SELECT DATABASE()')->fetch_row()[0];
                        $write_backup("-- Database: " . $conn->real_escape_string($database_name) . "\n");
                        $write_backup("SET FOREIGN_KEY_CHECKS=0;\n\n");

                        foreach ($tables as $table) {
                            $quoted_table = '`' . str_replace('`', '``', $table) . '`';
                            $result = $conn->query("SHOW CREATE TABLE $quoted_table");
                            $row = $result->fetch_row();
                            $write_backup($row[1] . ";\n\n");

                            $result = $conn->query("SELECT * FROM $quoted_table");
                            while ($row = $result->fetch_assoc()) {
                                $keys = array_keys($row);
                                $quoted_columns = array_map(function ($column) {
                                    return '`' . str_replace('`', '``', $column) . '`';
                                }, $keys);
                                $values = array_map(function ($value) use ($conn) {
                                    return $value === null ? 'NULL' : "'" . $conn->real_escape_string((string)$value) . "'";
                                }, array_values($row));
                                $write_backup(
                                    "INSERT INTO $quoted_table (" . implode(',', $quoted_columns) .
                                    ") VALUES (" . implode(',', $values) . ");\n"
                                );
                            }
                            $write_backup("\n");
                        }
                        $write_backup("SET FOREIGN_KEY_CHECKS=1;\n");
                    } catch (Throwable $e) {
                        fclose($handle);
                        unlink($backup_file);
                        throw $e;
                    }
                    
                    if (!fclose($handle)) {
                        unlink($backup_file);
                        throw new RuntimeException('Unable to finish writing the backup.');
                    }
                    if (!chmod($backup_file, 0600)) {
                        unlink($backup_file);
                        throw new RuntimeException('Unable to secure the backup file.');
                    }
                    
                    $message = "Backup created successfully: " . basename($backup_file);
                    $message_type = 'success';
                    
                    // Log backup action
                    $log_sql = "INSERT INTO system_logs (user_id, log_level, module, message, ip_address) VALUES (?, 'audit', 'maintenance', ?, ?)";
                    $stmt = $conn->prepare($log_sql);
                    $description = "Database backup created: " . basename($backup_file);
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $stmt->bind_param("iss", $_SESSION['user_id'], $description, $ip_address);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Backup was created, but its audit log could not be saved.');
                    }
                    $stmt->close();
                    break;
            }
        } catch (Throwable $e) {
                $message = 'Unable to complete the maintenance action. Check the application error log for details.';
                error_log('Maintenance action failed: ' . $e->getMessage());
            $message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['maintenance_message'] = $message;
    $_SESSION['maintenance_message_type'] = $message_type;
    header('Location: maintenance.php', true, 303);
    exit();
}

$message = $_SESSION['maintenance_message'] ?? '';
$message_type = $_SESSION['maintenance_message_type'] ?? '';
unset($_SESSION['maintenance_message'], $_SESSION['maintenance_message_type']);
require_once '../includes/header.php';

// Get system statistics
$system_stats = [];
$maintenance_stats_error = null;
try {
    // Database size
    $db_size_sql = "SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()";
    $db_size_result = $conn->query($db_size_sql);
    $system_stats['db_size'] = $db_size_result->fetch_assoc()['size_mb'];
    
    // Total records
    $tables_sql = "SHOW TABLES";
    $tables_result = $conn->query($tables_sql);
    $total_records = 0;
    while ($table_row = $tables_result->fetch_array()) {
        $table = $table_row[0];
        $count_sql = "SELECT COUNT(*) as count FROM `$table`";
        $count_result = $conn->query($count_sql);
        $total_records += $count_result->fetch_assoc()['count'];
    }
    $system_stats['total_records'] = number_format($total_records);
    
    // Cache size
    $cache_size = 0;
    $cache_dir = '../cache/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $cache_size += filesize($file);
            }
        }
    }
    $system_stats['cache_size'] = round($cache_size / 1024, 2); // KB
    
    // Log count
    $log_sql = "SELECT COUNT(*) as count FROM system_logs";
    $log_result = $conn->query($log_sql);
    $system_stats['log_count'] = number_format($log_result->fetch_assoc()['count']);
    
    // Backup count
    $backup_dir = dirname(__DIR__) . '/backups/private/';
    $backup_count = 0;
    if (is_dir($backup_dir)) {
        $backup_count = count(glob($backup_dir . '*.sql') ?: []);
    }
    $system_stats['backup_count'] = $backup_count;
    
    // Server info
    $system_stats['php_version'] = phpversion();
    $system_stats['server_software'] = $_SERVER['SERVER_SOFTWARE'];
    $system_stats['max_execution_time'] = ini_get('max_execution_time');
    $system_stats['memory_limit'] = ini_get('memory_limit');
    
} catch (Throwable $e) {
    error_log("System stats error: " . $e->getMessage());
    $maintenance_stats_error = 'Some system statistics could not be loaded.';
}

// Get recent backups
$recent_backups = [];
$backup_dir = dirname(__DIR__) . '/backups/private/';
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . 'backup_*.sql') ?: [];
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach (array_slice($files, 0, 5) as $file) {
        $recent_backups[] = [
            'name' => basename($file),
            'size' => round(filesize($file) / 1024 / 1024, 2), // MB
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
        ];
    }
}

// Get system logs
$recent_logs = [];
$logs_sql = "SELECT l.*, u.full_name, u.username 
             FROM system_logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             ORDER BY l.log_time DESC 
             LIMIT 10";
$logs_result = $conn->query($logs_sql);
while ($log = $logs_result->fetch_assoc()) {
    $recent_logs[] = $log;
}
?>

<style>
    /* Maintenance Page Styles */
    .maintenance-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .maintenance-header {
        background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
        border-radius: 15px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 8px 30px rgba(25, 135, 84, 0.2);
    }
    
    .maintenance-title {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .maintenance-subtitle {
        font-size: 1rem;
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        color: white;
        font-size: 1.5rem;
    }
    
    .stat-title {
        font-size: 0.9rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .stat-unit {
        font-size: 0.9rem;
        color: #64748b;
    }
    
    /* Maintenance Actions */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }
    
    .action-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .action-card:hover {
        border-color: var(--primary-green);
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(34, 197, 94, 0.15);
    }
    
    .action-card.danger:hover {
        border-color: #ef4444;
        box-shadow: 0 8px 30px rgba(239, 68, 68, 0.15);
    }
    
    .action-card.warning:hover {
        border-color: #f59e0b;
        box-shadow: 0 8px 30px rgba(245, 158, 11, 0.15);
    }
    
    .action-icon {
        width: 70px;
        height: 70px;
        border-radius: 15px;
        background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 25px;
        color: white;
        font-size: 1.8rem;
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
    }
    
    .action-card.danger .action-icon {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }
    
    .action-card.warning .action-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
    }
    
    .action-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 12px;
    }
    
    .action-description {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 25px;
    }
    
    .action-btn {
        background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
    }
    
    .action-btn.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .action-btn.danger:hover {
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }
    
    .action-btn.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .action-btn.warning:hover {
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
    }
    
    .action-form {
        margin-bottom: 0;
    }
    
    /* Recent Backups */
    .backups-table, .logs-table {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 40px;
    }
    
    .table-header {
        background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .table-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .table-content {
        padding: 25px;
    }
    
    .backup-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .backup-item:last-child {
        border-bottom: none;
    }
    
    .backup-item:hover {
        background: #f8fafc;
        border-radius: 8px;
    }
    
    .backup-info {
        flex: 1;
    }
    
    .backup-name {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 5px;
        font-size: 1rem;
    }
    
    .backup-meta {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        gap: 15px;
    }
    
    .backup-actions {
        display: flex;
        gap: 10px;
    }
    
    .backup-btn {
        padding: 8px 15px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
    }
    
    .backup-btn.download {
        background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
        color: white;
    }
    
    .backup-btn.delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .backup-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Server Info */
    .server-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .info-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .info-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-green);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .info-value {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.95rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 0;
    }
    
    /* Modal for confirmation */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1100;
        backdrop-filter: blur(3px);
    }
    
    .modal-content {
        background: white;
        border-radius: 15px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 15px;
    }
    
    .modal-message {
        color: #64748b;
        margin-bottom: 25px;
        line-height: 1.6;
    }
    
    .modal-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }
    
    .modal-btn {
        padding: 10px 25px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.95rem;
    }
    
    .modal-btn.confirm {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .modal-btn.confirm:hover {
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        transform: translateY(-2px);
    }
    
    .modal-btn.cancel {
        background: #e2e8f0;
        color: #64748b;
    }
    
    .modal-btn.cancel:hover {
        background: #cbd5e1;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .maintenance-header {
            padding: 20px;
        }
        
        .maintenance-title {
            font-size: 1.8rem;
        }
        
        .actions-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .backup-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .backup-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .table-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
        
        .server-info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="maintenance-container">
    <?php if ($maintenance_stats_error !== null): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($maintenance_stats_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <!-- Header -->
    <div class="maintenance-header">
        <h1 class="maintenance-title">
            <i class="fas fa-tools"></i>
            System Maintenance
        </h1>
        <p class="maintenance-subtitle">
            Manage system health, backups, and optimization tasks
        </p>
    </div>
    
    <!-- System Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-title">Database Size</div>
            <div class="stat-value"><?php echo $system_stats['db_size'] ?? '0.00'; ?></div>
            <div class="stat-unit">MB</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-title">Total Records</div>
            <div class="stat-value"><?php echo $system_stats['total_records'] ?? '0'; ?></div>
            <div class="stat-unit">Records</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="stat-title">Cache Size</div>
            <div class="stat-value"><?php echo $system_stats['cache_size'] ?? '0'; ?></div>
            <div class="stat-unit">KB</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-title">System Logs</div>
            <div class="stat-value"><?php echo $system_stats['log_count'] ?? '0'; ?></div>
            <div class="stat-unit">Entries</div>
        </div>
    </div>
    
    <!-- Maintenance Actions -->
    <h2 class="mb-4" style="color: #1e293b; font-weight: 700;">Maintenance Actions</h2>
    <div class="actions-grid">
        <!-- Database Backup -->
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-database"></i>
            </div>
            <h3 class="action-title">Database Backup</h3>
            <p class="action-description">
                Create a complete backup of your database. This will generate a SQL file containing all your data and structure.
            </p>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="run_backup">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="action-btn">
                    <i class="fas fa-download"></i>
                    Create Backup
                </button>
            </form>
        </div>
        
        <!-- Clear Cache -->
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-broom"></i>
            </div>
            <h3 class="action-title">Clear Cache</h3>
            <p class="action-description">
                Remove temporary cache files to free up disk space and ensure you're viewing the latest data.
            </p>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="clear_cache">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="action-btn">
                    <i class="fas fa-trash"></i>
                    Clear Cache
                </button>
            </form>
        </div>
        
        <!-- Optimize Database -->
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <h3 class="action-title">Optimize Database</h3>
            <p class="action-description">
                Optimize database tables to improve performance and reclaim unused space.
            </p>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="optimize_tables">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="action-btn">
                    <i class="fas fa-cogs"></i>
                    Optimize Now
                </button>
            </form>
        </div>
        
        <!-- Check Integrity -->
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="action-title">Check Database Integrity</h3>
            <p class="action-description">
                Verify the integrity of your database tables and identify any potential issues.
            </p>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="check_integrity">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="action-btn">
                    <i class="fas fa-search"></i>
                    Check Integrity
                </button>
            </form>
        </div>
        
        <!-- Clear Logs -->
        <div class="action-card danger">
            <div class="action-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3 class="action-title">Clear Old Logs</h3>
            <p class="action-description">
                Remove system log entries older than 30 days. This action cannot be undone.
            </p>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="clear_logs">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="action-btn danger">
                    <i class="fas fa-trash-alt"></i>
                    Clear Old Logs
                </button>
            </form>
        </div>
        
        <!-- System Diagnostics -->
        <div class="action-card warning">
            <div class="action-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <h3 class="action-title">System Diagnostics</h3>
            <p class="action-description">
                Run comprehensive system diagnostics to identify potential issues and bottlenecks.
            </p>
            <button type="button" class="action-btn warning" onclick="runDiagnostics()">
                <i class="fas fa-play"></i>
                Run Diagnostics
            </button>
        </div>
    </div>
    
    <!-- Recent Backups -->
    <div class="backups-table">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-history"></i>
                Recent Backups
            </h3>
            <span class="badge bg-light text-dark">
                <?php echo $system_stats['backup_count'] ?? 0; ?> backups available
            </span>
        </div>
        <div class="table-content">
            <?php if (!empty($recent_backups)): ?>
                <?php foreach ($recent_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="backup-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo $backup['modified']; ?></span>
                                <span><i class="fas fa-hdd"></i> <?php echo $backup['size']; ?> MB</span>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <a href="../api/download_backup.php?file=<?php echo rawurlencode($backup['name']); ?>" class="backup-btn download">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button type="button" class="backup-btn delete" onclick="deleteBackup(<?php echo htmlspecialchars(json_encode($backup['name']), ENT_QUOTES, 'UTF-8'); ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <p>No backups found. Create your first backup now.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Server Information -->
    <div class="server-info-grid">
        <div class="info-card">
            <h3 class="info-title">
                <i class="fas fa-server"></i>
                Server Information
            </h3>
            <ul class="info-list">
                <li class="info-item">
                    <span class="info-label">PHP Version</span>
                    <span class="info-value"><?php echo $system_stats['php_version'] ?? 'Unknown'; ?></span>
                </li>
                <li class="info-item">
                    <span class="info-label">Server Software</span>
                    <span class="info-value"><?php echo $system_stats['server_software'] ?? 'Unknown'; ?></span>
                </li>
                <li class="info-item">
                    <span class="info-label">Max Execution Time</span>
                    <span class="info-value"><?php echo $system_stats['max_execution_time'] ?? 'Unknown'; ?> seconds</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Memory Limit</span>
                    <span class="info-value"><?php echo $system_stats['memory_limit'] ?? 'Unknown'; ?></span>
                </li>
            </ul>
        </div>
        
        <div class="info-card">
            <h3 class="info-title">
                <i class="fas fa-database"></i>
                Database Information
            </h3>
            <ul class="info-list">
                <li class="info-item">
                    <span class="info-label">Database Name</span>
                    <span class="info-value">godwilling_db</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Database Size</span>
                    <span class="info-value"><?php echo $system_stats['db_size'] ?? '0.00'; ?> MB</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Total Records</span>
                    <span class="info-value"><?php echo $system_stats['total_records'] ?? '0'; ?></span>
                </li>
                <li class="info-item">
                    <span class="info-label">Active Connections</span>
                    <span class="info-value"><?php echo $conn->thread_id ?? '1'; ?></span>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Recent System Logs -->
    <div class="logs-table">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-clipboard-list"></i>
                Recent System Logs
            </h3>
            <span class="badge bg-light text-dark">
                Last 10 entries
            </span>
        </div>
        <div class="table-content">
            <?php if (!empty($recent_logs)): ?>
                <?php foreach ($recent_logs as $log): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">
                                <?php echo htmlspecialchars($log['log_level'] . ' · ' . $log['module'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($log['full_name']): ?>
                                    <small class="text-muted">by <?php echo htmlspecialchars($log['full_name']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="backup-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($log['log_time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(substr($log['message'], 0, 100), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No system logs found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmationModal">
    <div class="modal-content">
        <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
        <p class="modal-message" id="modalMessage">Are you sure you want to perform this action?</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            <button type="button" class="modal-btn confirm" id="confirmActionBtn">Confirm</button>
        </div>
    </div>
</div>

<script>
    // Message display
    <?php if ($message): ?>
    $(document).ready(function() {
        showNotification('<?php echo $message; ?>', '<?php echo $message_type; ?>');
    });
    <?php endif; ?>
    
    // Modal functions
    let currentForm = null;
    let currentAction = '';
    
    function confirmAction(action) {
        currentAction = action;
        const modal = $('#confirmationModal');
        const title = $('#modalTitle');
        const message = $('#modalMessage');
        
        switch(action) {
            case 'backup':
                title.text('Create Database Backup');
                message.text('This will create a complete backup of your database. The process may take a few moments. Continue?');
                break;
            case 'clear_cache':
                title.text('Clear Cache');
                message.text('This will remove all temporary cache files. Continue?');
                break;
            case 'optimize':
                title.text('Optimize Database');
                message.text('This will optimize all database tables for better performance. Continue?');
                break;
            case 'integrity':
                title.text('Check Database Integrity');
                message.text('This will check the integrity of all database tables. Continue?');
                break;
            case 'clear_logs':
                title.text('Clear Old Logs');
                message.text('This will permanently delete all system logs older than 30 days. This action cannot be undone. Continue?');
                break;
        }
        
        modal.show();
        return false;
    }
    
    function closeModal() {
        $('#confirmationModal').hide();
        currentForm = null;
        currentAction = '';
    }
    
    // Confirm action button
    $('#confirmActionBtn').click(function() {
        if (currentForm) {
            $(currentForm).off('submit').submit();
        }
        closeModal();
    });
    
    // Update form submission to use modal
    $('.action-form').submit(function(e) {
        currentForm = this;
        e.preventDefault();
        
        const action = $(this).find('input[name="action"]').val();
        let modalAction = '';
        
        switch(action) {
            case 'run_backup': modalAction = 'backup'; break;
            case 'clear_cache': modalAction = 'clear_cache'; break;
            case 'optimize_tables': modalAction = 'optimize'; break;
            case 'check_integrity': modalAction = 'integrity'; break;
            case 'clear_logs': modalAction = 'clear_logs'; break;
        }
        
        confirmAction(modalAction);
    });
    
    // Delete backup function
    function deleteBackup(filename) {
        if (confirm('Are you sure you want to delete this backup file?\n\n' + filename)) {
            $.ajax({
                url: '../api/delete_backup.php',
                method: 'POST',
                data: { filename: filename, csrf_token: <?php echo json_encode($csrf_token); ?> },
                success: function(response) {
                    if (response.success) {
                        showNotification('Backup deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Error deleting backup: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showNotification('Error deleting backup. Please try again.', 'danger');
                }
            });
        }
    }
    
    // Run diagnostics
    function runDiagnostics() {
        const btn = event.target.closest('.action-btn');
        const originalHTML = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Diagnostics...';
        btn.disabled = true;
        
        $.ajax({
            url: '../api/run_diagnostics.php',
            method: 'POST',
            data: { csrf_token: <?php echo json_encode($csrf_token); ?> },
            success: function(response) {
                if (response.success) {
                    showNotification('Diagnostics completed successfully!', 'success');
                    
                    // Show results in modal
                    const modal = $('#confirmationModal');
                    $('#modalTitle').text('Diagnostics Results');
                    $('#modalMessage').html(
                        '<div style="max-height: 300px; overflow-y: auto;">' +
                        '<pre style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace;">' +
                        JSON.stringify(response.data, null, 2) +
                        '</pre>' +
                        '</div>'
                    );
                    $('#confirmActionBtn').hide();
                    modal.show();
                } else {
                    showNotification('Diagnostics failed: ' + response.message, 'danger');
                }
            },
            error: function() {
                showNotification('Error running diagnostics. Please try again.', 'danger');
            },
            complete: function() {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });
    }
    
    // Show notification function
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100;">
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        const toast = new bootstrap.Toast(notification.find('.toast')[0]);
        toast.show();
        
        notification.find('.toast').on('hidden.bs.toast', function() {
            notification.remove();
        });
    }
    
    // Close modal when clicking outside
    $(document).click(function(e) {
        const modal = $('#confirmationModal');
        if ($(e.target).hasClass('modal-overlay')) {
            closeModal();
        }
    });
    
    // Escape key closes modal
    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
</script>

<?php
require_once '../includes/footer.php';
?>