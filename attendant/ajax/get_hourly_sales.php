<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

require_once '../../config/db.php';
$attendantId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT HOUR(sale_date) AS hour, COUNT(*) AS count
     FROM sales
     WHERE attendant_id = ? AND sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND status = 'completed'
     GROUP BY HOUR(sale_date)
     ORDER BY hour"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare hourly report']);
    exit;
}
$stmt->bind_param('iss', $attendantId, $date, $date);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load hourly report']);
    exit;
}

$hourlyData = [];
$hourlyResult = $stmt->get_result();
for ($hour = 0; $hour < 24; $hour++) {
    $hourlyData[$hour] = ['hour' => $hour, 'count' => 0];
}
while ($row = $hourlyResult->fetch_assoc()) {
    $hourlyData[(int)$row['hour']] = [
        'hour' => (int)$row['hour'],
        'count' => (int)$row['count']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'hourlyData' => array_values($hourlyData)]);
