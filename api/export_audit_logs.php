<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';
parse_str(file_get_contents('php://input'), $filters);

$conditions = [];
$params = [];
$types = '';

if (!empty($filters['search'])) {
    $conditions[] = '(l.message LIKE ? OR u.full_name LIKE ?)';
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (!empty($filters['user_id'])) {
    $conditions[] = 'l.user_id = ?';
    $params[] = (int)$filters['user_id'];
    $types .= 'i';
}

if (!empty($filters['action_type'])) {
    $conditions[] = 'l.log_level = ?';
    $params[] = $filters['action_type'];
    $types .= 's';
}

if (!empty($filters['date_from'])) {
    $conditions[] = 'DATE(l.log_time) >= ?';
    $params[] = $filters['date_from'];
    $types .= 's';
}

if (!empty($filters['date_to'])) {
    $conditions[] = 'DATE(l.log_time) <= ?';
    $params[] = $filters['date_to'];
    $types .= 's';
}

$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql = "SELECT l.id, u.full_name AS user_name, u.role AS user_role, u.username,
               l.log_level AS action_type, l.message AS action_description,
               l.ip_address, l.module AS affected_table, l.request_url AS record_id,
               l.log_time AS created_at
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        $where_sql
        ORDER BY l.log_time DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'ID' => $row['id'],
        'User Name' => $row['user_name'] ?? 'System',
        'User Role' => $row['user_role'] ?? '',
        'Username' => $row['username'] ?? '',
        'Action Type' => $row['action_type'],
        'Description' => $row['action_description'],
        'IP Address' => $row['ip_address'],
        'Table' => $row['affected_table'] ?? '',
        'Record ID' => $row['record_id'] ?? '',
        'Timestamp' => $row['created_at'],
        'Date' => date('Y-m-d', strtotime($row['created_at'])),
        'Time' => date('H:i:s', strtotime($row['created_at']))
    ];
}

echo json_encode([
    'success' => true,
    'data' => $logs,
    'count' => count($logs),
    'exported_at' => date('Y-m-d H:i:s')
]);
