<?php
session_start();
require_once '../../config/db.php';

// Check if user is attendant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'attendant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$attendant_id = $_SESSION['user_id'];
$sale_id = $_POST['sale_id'] ?? 0;
$reason = $_POST['reason'] ?? '';
$password = $_POST['password'] ?? '';

// Verify password
$user_sql = "SELECT password FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $attendant_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit();
}

// Verify the sale belongs to this attendant and is not already voided
$verify_sql = "SELECT id, status FROM sales WHERE id = ? AND attendant_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("ii", $sale_id, $attendant_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found or unauthorized']);
    exit();
}

$sale = $verify_result->fetch_assoc();
if ($sale['status'] == 'voided') {
    echo json_encode(['success' => false, 'message' => 'Receipt is already voided']);
    exit();
}

// Void the receipt
$void_sql = "UPDATE sales SET status = 'voided', void_reason = ?, voided_at = NOW(), voided_by = ? WHERE id = ?";
$void_stmt = $conn->prepare($void_sql);
$void_stmt->bind_param("sii", $reason, $attendant_id, $sale_id);

if ($void_stmt->execute()) {
    // Log the void action
    $log_sql = "INSERT INTO receipt_void_logs (sale_id, attendant_id, reason, created_at) VALUES (?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("iis", $sale_id, $attendant_id, $reason);
    $log_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Receipt voided successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to void receipt']);
}
?>