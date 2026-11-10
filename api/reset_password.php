<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid attendant ID']);
    exit;
}

require_once '../config/db.php';
$pin = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$password = password_hash('attendant123', PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "UPDATE users SET pin_code = ?, password = ?, updated_at = NOW()
     WHERE id = ? AND role = 'attendant' AND deleted_at IS NULL"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not reset the attendant credentials']);
    exit;
}

$stmt->bind_param('ssi', $pin, $password, $id);
$stmt->execute();
if ($stmt->affected_rows !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attendant not found']);
    exit;
}

echo json_encode(['success' => true, 'new_pin' => $pin]);
