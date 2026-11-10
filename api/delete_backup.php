<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/security.php';

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

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit();
}

$filename = (string)($_POST['filename'] ?? '');
if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid backup filename']);
    exit();
}

$backup_path = dirname(__DIR__) . '/backups/private/' . $filename;

if (!is_file($backup_path)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Backup file not found']);
    exit();
}

if (unlink($backup_path)) {
    echo json_encode(['success' => true, 'message' => 'Backup deleted']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
}