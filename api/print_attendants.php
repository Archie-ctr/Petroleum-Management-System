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

// Get all attendants for printing
$sql = "SELECT u.id, u.employee_id, u.full_name, u.email, u.phone, u.shift_time,
               u.hourly_rate, u.status, u.created_at, b.branch_name
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

// Get statistics for print
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'active' AND deleted_at IS NULL) as active_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'inactive' AND deleted_at IS NULL) as inactive_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'suspended' AND deleted_at IS NULL) as suspended_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND deleted_at IS NULL) as total_attendants";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Get filter display text
$filterText = 'All Attendants';
if ($filter == 'active') $filterText = 'Active Attendants';
if ($filter == 'inactive') $filterText = 'Inactive Attendants';
if ($filter == 'suspended') $filterText = 'Suspended Attendants';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendants Report - <?php echo date('Y-m-d'); ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 20px; }
            .stats { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .stat-box { border: 1px solid #ddd; padding: 10px; text-align: center; flex: 1; margin: 0 5px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendants Report</h1>
        <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
        <p><strong>Filter:</strong> <?php echo htmlspecialchars($filterText, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($search)): ?>
            <p><strong>Search:</strong> "<?php echo htmlspecialchars($search); ?>"</p>
        <?php endif; ?>
    </div>
    
    <div class="stats">
        <div class="stat-box">
            <h3><?php echo $stats['active_attendants']; ?></h3>
            <p>Active</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $stats['inactive_attendants']; ?></h3>
            <p>Inactive</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $stats['suspended_attendants']; ?></h3>
            <p>Suspended</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $stats['total_attendants']; ?></h3>
            <p>Total</p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Employee ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Branch</th>
                <th>Shift Time</th>
                <th>Hourly Rate</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php while($attendant = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $attendant['id']; ?></td>
                <td><?php echo htmlspecialchars($attendant['employee_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($attendant['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($attendant['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($attendant['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($attendant['branch_name'] ?: 'Not Assigned', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($attendant['shift_time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>$<?php echo number_format($attendant['hourly_rate'], 2); ?></td>
                <td><?php echo ucfirst($attendant['status']); ?></td>
                <td><?php echo date('Y-m-d', strtotime($attendant['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Printed on: <?php echo date('Y-m-d H:i:s'); ?></p>
        <p>Printed by: <?php echo $_SESSION['full_name'] ?? 'System'; ?></p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Print Report</button>
        <button onclick="window.close()">Close</button>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>