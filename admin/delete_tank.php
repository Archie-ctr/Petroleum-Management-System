<?php
// delete_tank.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is admin or manager
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid tank ID';
    header('Location: tanks.php');
    exit;
}

$tank_id = intval($_GET['id']);

// Check if tank exists and has no connected pumps
$check_sql = "SELECT t.*, b.branch_name, f.fuel_name,
                     (SELECT COUNT(*) FROM pumps p WHERE p.tank_id = t.id) as pump_count
              FROM tanks t 
              JOIN branches b ON t.branch_id = b.id 
              JOIN fuel_types f ON t.fuel_type_id = f.id 
              WHERE t.id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $tank_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['error'] = 'Tank not found';
    header('Location: tanks.php');
    exit;
}

$tank_data = $check_result->fetch_assoc();

// Check if tank has connected pumps
if ($tank_data['pump_count'] > 0) {
    $_SESSION['error'] = 'Cannot delete tank with connected pumps. Please reassign or delete the pumps first.';
    header('Location: tanks.php');
    exit;
}

// Check if tank has fuel
if ($tank_data['current_quantity'] > 0) {
    $_SESSION['error'] = 'Cannot delete tank with fuel. Please transfer or sell the fuel first.';
    header('Location: tanks.php');
    exit;
}

// Delete the tank
$delete_sql = "DELETE FROM tanks WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $tank_id);

if ($delete_stmt->execute()) {
    // Log the deletion
    $log_sql = "INSERT INTO system_logs (log_level, module, user_id, message) 
                VALUES ('INFO', 'tanks', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_message = "Deleted tank: " . $tank_data['tank_number'] . " (" . $tank_data['fuel_name'] . 
                   ") from branch: " . $tank_data['branch_name'];
    $log_stmt->bind_param("is", $_SESSION['user_id'], $log_message);
    $log_stmt->execute();
    
    $_SESSION['success'] = 'Tank deleted successfully';
} else {
    $_SESSION['error'] = 'Error deleting tank: ' . $conn->error;
}

header('Location: tanks.php');
exit;
?>