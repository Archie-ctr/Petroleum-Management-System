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
$phone_number = $_POST['phone_number'] ?? '';

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

// Get sale details for SMS
$sale_sql = "SELECT s.*, f.fuel_name, b.branch_name FROM sales s
             JOIN fuel_types f ON s.fuel_type_id = f.id
             JOIN branches b ON s.branch_id = b.id
             WHERE s.id = ?";
$sale_stmt = $conn->prepare($sale_sql);
$sale_stmt->bind_param("i", $sale_id);
$sale_stmt->execute();
$sale = $sale_stmt->get_result()->fetch_assoc();

// Format SMS message
$company_name = "God Willing Business Center"; // Get from settings
$amount = number_format($sale['total_amount'], 2);
$sms_message = "Thank you for fueling at {$company_name}!\n";
$sms_message .= "Receipt: {$sale['sale_number']}\n";
$sms_message .= "Amount: \${$amount}\n";
$sms_message .= "Fuel: {$sale['fuel_name']}\n";
$sms_message .= "Thank you for your business!";

// In a real application, you would:
// 1. Use an SMS gateway API (Twilio, Nexmo, etc.)
// 2. Send the SMS
// 3. Log the SMS sending

// For now, we'll simulate success
// You should implement actual SMS sending here

echo json_encode([
    'success' => true,
    'message' => 'SMS sent successfully to ' . $phone_number
]);
?>