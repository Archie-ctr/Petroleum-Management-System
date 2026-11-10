<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

$backup_files = array_merge(
    glob(__DIR__ . '/../backups/*') ?: [],
    glob(__DIR__ . '/../database/backups/*') ?: []
);
$backup_files = array_filter($backup_files, 'is_file');
$last_backup_hours = null;

if ($backup_files) {
    $latest_backup = max(array_map('filemtime', $backup_files));
    $last_backup_hours = round(max(0, time() - $latest_backup) / 3600, 1);
}

echo json_encode([
    'success' => true,
    'system_online' => true,
    'database_connected' => $conn->ping(),
    'last_backup_hours' => $last_backup_hours ?? 999999
]);
