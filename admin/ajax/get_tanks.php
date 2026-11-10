<?php
// /admin/ajax/get_tanks.php - WORKING VERSION WITH CORRECT PASSWORD

// Enable error reporting for debugging (remove in production)
error_reporting(0); // Set to 0 in production
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// DIRECT DATABASE CONNECTION WITH YOUR PASSWORD
$host = 'sql306.infinityfree.com';
$user = 'if0_40669260';
$pass = 'UEJqrmhOQ4'; // YOUR PASSWORD FROM INFINITYFREE
$db = 'if0_40669260_godwilling_db'; // Your database name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    // Return JSON error
    $response = [
        'success' => false,
        'message' => 'Database connection failed',
        'error' => 'Connection error'
    ];
    echo json_encode($response);
    exit;
}

// Get branch ID from POST
$branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;

if ($branch_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please select a branch first'
    ]);
    $conn->close();
    exit;
}

// Get active tanks for the selected branch
$sql = "SELECT t.id, t.tank_number, t.capacity, t.current_quantity, 
               COALESCE(f.fuel_name, 'Unknown') as fuel_name,
               COALESCE(f.fuel_code, 'N/A') as fuel_code
        FROM tanks t 
        LEFT JOIN fuel_types f ON t.fuel_type_id = f.id 
        WHERE t.branch_id = ? AND t.status = 'active' 
        ORDER BY t.tank_number";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database query error',
        'sql_error' => $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$tanks = [];
$total_capacity = 0;
$total_current = 0;

while ($row = $result->fetch_assoc()) {
    $tanks[] = [
        'id' => (int)$row['id'],
        'tank_number' => $row['tank_number'],
        'fuel_name' => $row['fuel_name'],
        'fuel_code' => $row['fuel_code'],
        'capacity' => (float)$row['capacity'],
        'current_quantity' => (float)$row['current_quantity'],
        'available' => (float)$row['current_quantity'],
        'percentage' => $row['capacity'] > 0 ? round(($row['current_quantity'] / $row['capacity']) * 100, 1) : 0
    ];
    
    $total_capacity += $row['capacity'];
    $total_current += $row['current_quantity'];
}

$stmt->close();
$conn->close();

// Return response
echo json_encode([
    'success' => true,
    'tanks' => $tanks,
    'count' => count($tanks),
    'stats' => [
        'total_capacity' => $total_capacity,
        'total_current' => $total_current,
        'total_percentage' => $total_capacity > 0 ? round(($total_current / $total_capacity) * 100, 1) : 0
    ],
    'message' => count($tanks) > 0 ? 
        'Found ' . count($tanks) . ' active tanks' : 
        'No active tanks found in this branch'
]);

exit;
?>