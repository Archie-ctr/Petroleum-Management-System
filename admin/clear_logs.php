<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$csrf_token = is_array($input) ? ($input['csrf_token'] ?? '') : '';
if (!validateCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}
$days = filter_var($input['days'] ?? null, FILTER_VALIDATE_INT);

if (($input['confirm'] ?? false) !== true || $days === false || $days < 1 || $days > 3650) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid confirmation and retention days are required']);
    exit;
}

require_once '../config/db.php';

$cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
$stmt = $conn->prepare('DELETE FROM system_logs WHERE log_time < ?');
$stmt->bind_param('s', $cutoff);
if (!$stmt->execute()) {
    http_response_code(500);
    error_log('Unable to clear old system logs: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Unable to clear old logs']);
    exit;
}
$deleted = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => "$deleted log entries older than $days days were cleared.",
    'deleted' => $deleted
]);
