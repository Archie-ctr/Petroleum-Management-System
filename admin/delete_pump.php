<?php
// delete_pump.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is admin or manager
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid pump ID';
    header('Location: pumps.php');
    exit;
}

$pump_id = intval($_GET['id']);

// Check if pump exists
$check_sql = "SELECT p.*, b.branch_name FROM pumps p 
              JOIN branches b ON p.branch_id = b.id 
              WHERE p.id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $pump_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['error'] = 'Pump not found';
    header('Location: pumps.php');
    exit;
}

$pump_data = $check_result->fetch_assoc();

// Delete the pump
$delete_sql = "DELETE FROM pumps WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $pump_id);

if ($delete_stmt->execute()) {
    // Log the deletion
    $log_sql = "INSERT INTO system_logs (log_level, module, user_id, message) 
                VALUES ('INFO', 'pumps', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_message = "Deleted pump: " . $pump_data['pump_name'] . " (" . $pump_data['pump_number'] . 
                   ") from branch: " . $pump_data['branch_name'];
    $log_stmt->bind_param("is", $_SESSION['user_id'], $log_message);
    $log_stmt->execute();
    
    $_SESSION['success'] = 'Pump deleted successfully';
} else {
    $_SESSION['error'] = 'Error deleting pump: ' . $conn->error;
}

header('Location: pumps.php');
exit;
?>