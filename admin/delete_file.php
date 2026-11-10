<?php
session_start();
require_once '../config/db.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$filename = $_POST['filename'] ?? '';
$type = $_POST['type'] ?? '';

if (empty($filename) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Validate file path
$base_dir = '';
switch ($type) {
    case 'export':
        $base_dir = '../exports/';
        break;
    case 'backup':
        $base_dir = '../backups/';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
}

$filepath = $base_dir . basename($filename);

// Security check: ensure file is within allowed directory
if (!file_exists($filepath) || strpos(realpath($filepath), realpath($base_dir)) !== 0) {
    echo json_encode(['success' => false, 'message' => 'File not found or invalid path']);
    exit();
}

// Delete file
if (unlink($filepath)) {
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
}
?>