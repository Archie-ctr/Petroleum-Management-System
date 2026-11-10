<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid attendant ID']);
    exit;
}

require_once '../config/db.php';
$stmt = $conn->prepare(
    "SELECT id, username, email, full_name, role, branch_id, phone, employee_id,
            hourly_rate, shift_time, pin_code, status, profile_photo, created_at, updated_at
     FROM users
     WHERE id = ? AND role = 'attendant' AND deleted_at IS NULL"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load attendant']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$attendant = $stmt->get_result()->fetch_assoc();

if (!$attendant) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attendant not found']);
    exit;
}

echo json_encode(['success' => true, 'attendant' => $attendant]);
