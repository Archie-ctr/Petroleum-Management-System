<?php
session_start();

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$filename = (string)($_GET['file'] ?? '');
if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
    http_response_code(400);
    exit('Invalid backup file.');
}

$backup_path = dirname(__DIR__) . '/backups/private/' . $filename;
if (!is_file($backup_path) || !is_readable($backup_path)) {
    http_response_code(404);
    exit('Backup file not found.');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($backup_path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($backup_path);
