<?php
session_start();
require_once '../config/db.php';

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Handle filters (similar to main page)
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$whereClause = "WHERE u.role = 'attendant' AND u.deleted_at IS NULL";
$params = [];
$types = "";

if ($filter == 'active') {
    $whereClause .= " AND u.status = 'active'";
} elseif ($filter == 'inactive') {
    $whereClause .= " AND u.status = 'inactive'";
} elseif ($filter == 'suspended') {
    $whereClause .= " AND u.status = 'suspended'";
}

if (!empty($search)) {
    $whereClause .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.employee_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_fill(0, 4, $searchTerm);
    $types = "ssss";
}

// Get all attendants for export
$sql = "SELECT u.id, u.employee_id, u.full_name, u.email, u.phone, u.shift_time,
               u.hourly_rate, u.pin_code, u.status, u.created_at, b.branch_name
        FROM users u 
        LEFT JOIN branches b ON u.branch_id = b.id 
        $whereClause 
        ORDER BY u.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendants_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, ['ID', 'Employee ID', 'Full Name', 'Email', 'Phone', 'Branch', 'Shift Time', 'Hourly Rate', 'PIN', 'Status', 'Created At']);

// Add data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['employee_id'],
        $row['full_name'],
        $row['email'],
        $row['phone'],
        $row['branch_name'] ?: 'Not Assigned',
        $row['shift_time'],
        '$' . number_format($row['hourly_rate'], 2),
        $row['pin_code'],
        ucfirst($row['status']),
        $row['created_at']
    ]);
}

fclose($output);
?>