<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    exit('Unauthorized');
}

require_once '../config/db.php';

$where = [];
$params = [];
$types = '';

if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
    $where[] = 'DATE(l.log_time) BETWEEN ? AND ?';
    $params[] = $_GET['date_from'];
    $params[] = $_GET['date_to'];
    $types .= 'ss';
}

if (!empty($_GET['level']) && $_GET['level'] !== 'all') {
    $where[] = 'l.log_level = ?';
    $params[] = $_GET['level'];
    $types .= 's';
}

if (!empty($_GET['module']) && $_GET['module'] !== 'all') {
    $where[] = 'l.module = ?';
    $params[] = $_GET['module'];
    $types .= 's';
}

if (!empty($_GET['user_id']) && $_GET['user_id'] !== 'all') {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$_GET['user_id'];
    $types .= 'i';
}

if (!empty($_GET['ip_address'])) {
    $where[] = 'l.ip_address LIKE ?';
    $params[] = '%' . $_GET['ip_address'] . '%';
    $types .= 's';
}

if (!empty($_GET['search'])) {
    $where[] = '(l.message LIKE ? OR l.user_agent LIKE ? OR l.additional_data LIKE ?)';
    $search = '%' . $_GET['search'] . '%';
    array_push($params, $search, $search, $search);
    $types .= 'sss';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT l.id, l.log_time, l.log_level, l.module, l.user_id, l.message,
               l.ip_address, l.user_agent, l.request_url, l.additional_data,
               u.full_name AS user_name, u.username, u.role AS user_role
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
$format = $_GET['export'] ?? 'csv';

if ($format === 'pdf') {
    header('Location: logs.php?' . http_build_query($_GET));
    exit;
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="system-logs-' . date('Y-m-d') . '.json"');
    echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
    exit;
}

if ($format !== 'csv') {
    http_response_code(400);
    exit('Unsupported export format');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="system-logs-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Timestamp', 'Level', 'Module', 'User', 'Username', 'Role', 'Message', 'IP Address', 'Request URL']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['log_time'],
        $row['log_level'],
        $row['module'],
        $row['user_name'],
        $row['username'],
        $row['user_role'],
        $row['message'],
        $row['ip_address'],
        $row['request_url']
    ]);
}

fclose($output);
