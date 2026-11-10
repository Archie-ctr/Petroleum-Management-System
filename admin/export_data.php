<?php
// admin/export_data.php
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Include database connection
require_once '../config/db.php';

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_type = $_POST['export_type'] ?? 'sales';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $format = $_POST['format'] ?? 'csv';
    
    // Set headers based on format
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $export_type . '_export_' . date('Y-m-d') . '.csv"');
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $export_type . '_export_' . date('Y-m-d') . '.json"');
    }
    
    // Prepare data based on export type
    $data = [];
    
    switch ($export_type) {
        case 'sales':
            $sql = "SELECT s.*, b.branch_name, f.fuel_name, u.full_name as attendant_name
                    FROM sales s 
                    JOIN branches b ON s.branch_id = b.id 
                    JOIN fuel_types f ON s.fuel_type_id = f.id
                    JOIN users u ON s.attendant_id = u.id
                    WHERE s.sale_date BETWEEN ? AND ?
                    ORDER BY s.sale_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'users':
            $sql = "SELECT * FROM users ORDER BY created_at DESC";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'branches':
            $sql = "SELECT * FROM branches ORDER BY created_at DESC";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'inventory':
            $sql = "SELECT t.*, f.fuel_name, b.branch_name 
                    FROM tanks t
                    JOIN fuel_types f ON t.fuel_type_id = f.id
                    JOIN branches b ON t.branch_id = b.id
                    ORDER BY t.updated_at DESC";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
            
        case 'financial':
            $sql = "SELECT 'sales' as type, s.sale_date as date, s.total_amount as amount, 
                    b.branch_name, 'Revenue' as category
                    FROM sales s JOIN branches b ON s.branch_id = b.id
                    WHERE s.sale_date BETWEEN ? AND ?
                    
                    UNION ALL
                    
                    SELECT 'expenses' as type, e.expense_date as date, -e.amount as amount,
                    b.branch_name, e.expense_type as category
                    FROM expenses e JOIN branches b ON e.branch_id = b.id
                    WHERE e.expense_date BETWEEN ? AND ?
                    
                    ORDER BY date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            break;
    }
    
    // Output data in selected format
    if ($format === 'csv') {
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
    } elseif ($format === 'json') {
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    exit();
}

// If not POST request, redirect to backup page
header('Location: backup_restore.php');
exit();
?>