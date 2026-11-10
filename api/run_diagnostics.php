<?php
session_start();
require_once '../config/db.php';
require_once '../includes/security.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit();
}

try {
    $diagnostics = [];
    
    // Check database connection
    $diagnostics['database'] = [
        'connected' => $conn->ping(),
        'error' => $conn->connect_error,
        'version' => $conn->server_info
    ];
    
    // Check disk space
    $disk_free = disk_free_space(__DIR__);
    $disk_total = disk_total_space(__DIR__);
    if ($disk_free === false || $disk_total === false || $disk_total <= 0) {
        throw new RuntimeException('Disk space information is unavailable.');
    }
    $disk_used = $disk_total - $disk_free;
    
    $diagnostics['disk'] = [
        'free' => round($disk_free / 1024 / 1024 / 1024, 2) . ' GB',
        'total' => round($disk_total / 1024 / 1024 / 1024, 2) . ' GB',
        'used' => round($disk_used / 1024 / 1024 / 1024, 2) . ' GB',
        'percent_used' => round(($disk_used / $disk_total) * 100, 2) . '%'
    ];
    
    // Check PHP configuration
    $diagnostics['php'] = [
        'version' => phpversion(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
    
    // Check required extensions
    $required_extensions = ['mysqli', 'pdo_mysql', 'gd', 'mbstring', 'json'];
    $diagnostics['extensions'] = [];
    foreach ($required_extensions as $ext) {
        $diagnostics['extensions'][$ext] = extension_loaded($ext);
    }
    
    // Check directory permissions
    $directories = [
        '../uploads/' => 'Uploads directory',
        dirname(__DIR__) . '/backups/private/' => 'Private backups directory',
        '../cache/' => 'Cache directory'
    ];
    
    $diagnostics['permissions'] = [];
    foreach ($directories as $dir => $name) {
        $diagnostics['permissions'][$name] = [
            'exists' => is_dir($dir),
            'writable' => is_writable($dir),
            'readable' => is_readable($dir)
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $diagnostics]);
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log('System diagnostics failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Diagnostics could not be completed.']);
}