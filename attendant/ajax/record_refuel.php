<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$tank_id = filter_input(INPUT_POST, 'tank_id', FILTER_VALIDATE_INT);
$refuel_amount = filter_input(INPUT_POST, 'refuel_amount', FILTER_VALIDATE_FLOAT);
$cost_input = $_POST['refuel_cost'] ?? '';
$refuel_cost = $cost_input === ''
    ? 0.0
    : (is_scalar($cost_input) ? filter_var((string)$cost_input, FILTER_VALIDATE_FLOAT) : false);
$notes_input = $_POST['notes'] ?? '';
$notes = is_string($notes_input) ? trim($notes_input) : false;

if (!$tank_id || $refuel_amount === false || $refuel_amount <= 0 ||
    !is_finite((float)$refuel_amount) || $refuel_cost === false ||
    !is_finite((float)$refuel_cost) || $refuel_cost < 0 || $notes === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid tank, amount, and cost']);
    exit;
}

require_once '../../config/db.php';

$conn->begin_transaction();

$tank_stmt = $conn->prepare(
    "SELECT id, branch_id, fuel_type_id, current_quantity, capacity
     FROM tanks
     WHERE id = ? AND branch_id = ? AND status = 'active'
     FOR UPDATE"
);
$tank_stmt->bind_param('ii', $tank_id, $_SESSION['branch_id']);
$tank_stmt->execute();
$tank = $tank_stmt->get_result()->fetch_assoc();
$tank_stmt->close();

if (!$tank) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tank not found']);
    exit;
}

$previous_quantity = (float)$tank['current_quantity'];
$new_quantity = $previous_quantity + $refuel_amount;

if ($new_quantity > (float)$tank['capacity']) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Refuel amount exceeds available tank capacity']);
    exit;
}

$update_stmt = $conn->prepare(
    'UPDATE tanks SET current_quantity = ?, last_refill = NOW() WHERE id = ? AND branch_id = ?'
);
$update_stmt->bind_param('dii', $new_quantity, $tank_id, $_SESSION['branch_id']);
$update_stmt->execute();
$updated = $update_stmt->affected_rows === 1;
$update_stmt->close();

if (!$updated) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tank level could not be updated']);
    exit;
}

$unit_price = $refuel_amount > 0 ? $refuel_cost / $refuel_amount : 0;
$log_stmt = $conn->prepare(
    "INSERT INTO inventory_logs
        (branch_id, tank_id, fuel_type_id, transaction_type, quantity,
         previous_quantity, new_quantity, unit_price, total_value, performed_by, notes)
     VALUES (?, ?, ?, 'refill', ?, ?, ?, ?, ?, ?, ?)"
);
$log_stmt->bind_param(
    'iiidddddis',
    $tank['branch_id'],
    $tank_id,
    $tank['fuel_type_id'],
    $refuel_amount,
    $previous_quantity,
    $new_quantity,
    $unit_price,
    $refuel_cost,
    $_SESSION['user_id'],
    $notes
);
$log_stmt->execute();
$log_stmt->close();
$conn->commit();

echo json_encode([
    'success' => true,
    'message' => 'Refuel recorded successfully',
    'current_quantity' => $new_quantity
]);
