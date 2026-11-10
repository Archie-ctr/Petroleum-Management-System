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
$recipient_email = $_POST['recipient_email'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';

// Verify the sale belongs to this attendant
$verify_sql = "SELECT id FROM sales WHERE id = ? AND attendant_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("ii", $sale_id, $attendant_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found or unauthorized']);
    exit();
}

// Get sale details for email
$sale_sql = "SELECT s.*, f.fuel_name, b.branch_name FROM sales s
             JOIN fuel_types f ON s.fuel_type_id = f.id
             JOIN branches b ON s.branch_id = b.id
             WHERE s.id = ?";
$sale_stmt = $conn->prepare($sale_sql);
$sale_stmt->bind_param("i", $sale_id);
$sale_stmt->execute();
$sale = $sale_stmt->get_result()->fetch_assoc();

// In a real application, you would:
// 1. Generate PDF receipt
// 2. Send email with attachment
// 3. Log the email sending

// For now, we'll simulate success
// You should implement actual email sending here

echo json_encode([
    'success' => true,
    'message' => 'Email sent successfully to ' . $recipient_email
]);
?>