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

// Select only profile fields available in the current users schema.
$sql = "SELECT u.id, u.full_name, u.email, u.role, u.branch_id, u.phone, u.employee_id,
               u.hourly_rate, u.shift_time, u.pin_code, u.status, u.profile_photo,
               u.created_at, u.updated_at, uc.full_name AS created_by_name, b.branch_name
        FROM users u 
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN users uc ON u.created_by = uc.id
        WHERE u.id = ? AND u.role = 'attendant' AND u.deleted_at IS NULL";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load attendant details']);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($attendant = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'attendant' => $attendant
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Attendant not found'
    ]);
}