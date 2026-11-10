<?php
session_start();
require_once '../../config/db.php';

// In a real system, you would have a shift management system
// For now, we'll return mock data

$shift_start = '08:00 AM';
$current_time = date('H:i A');
$start_time = strtotime('08:00:00');
$now = time();
$duration_seconds = $now - $start_time;
$hours = floor($duration_seconds / 3600);
$minutes = floor(($duration_seconds % 3600) / 60);
$duration = $hours . 'h ' . $minutes . 'm';

echo json_encode([
    'success' => true,
    'shift_status' => 'Active',
    'start_time' => $shift_start,
    'duration' => $duration
]);
?>