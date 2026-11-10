<?php
session_start();
require_once '../config/db.php';
require_once '../includes/employee_id.php';
require_once '../includes/header.php';
require_once '../includes/export_pdf.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$import_stats = [];
$export_file = '';

// Handle Import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_type'])) {
    $import_type = $_POST['import_type'];
    
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['import_file']['tmp_name'];
        $file_name = $_FILES['import_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['csv', 'xlsx', 'xls', 'json'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            try {
                switch ($import_type) {
                    case 'users':
                        $import_stats = importUsers($conn, $file_tmp_path, $file_ext);
                        break;
                    case 'branches':
                        $import_stats = importBranches($conn, $file_tmp_path, $file_ext);
                        break;
                    case 'tanks':
                        $import_stats = importTanks($conn, $file_tmp_path, $file_ext);
                        break;
                    case 'fuel_types':
                        $import_stats = importFuelTypes($conn, $file_tmp_path, $file_ext);
                        break;
                    case 'sales':
                        $import_stats = importSales($conn, $file_tmp_path, $file_ext);
                        break;
                    case 'expenses':
                        $import_stats = importExpenses($conn, $file_tmp_path, $file_ext);
                        break;
                    default:
                        throw new Exception("Invalid import type.");
                }
                
                $success_message = "Import completed successfully!";
                
            } catch (Exception $e) {
                $error_message = "Import failed: " . $e->getMessage();
            }
        } else {
            $error_message = "Invalid file format. Allowed formats: CSV, Excel (xlsx, xls), JSON";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Handle Export
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_type'])) {
    $export_type = $_POST['export_type'];
    $export_format = $_POST['export_format'] ?? 'csv';
    
    try {
        $export_file = exportData($conn, $export_type, $export_format);
        $success_message = "Export completed successfully! File: " . basename($export_file);
    } catch (Exception $e) {
        $error_message = "Export failed: " . $e->getMessage();
    }
}

// Handle Database Backup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['backup_database'])) {
    try {
        $backup_file = backupDatabase($conn);
        $success_message = "Database backup completed successfully! File: " . basename($backup_file);
    } catch (Exception $e) {
        $error_message = "Backup failed: " . $e->getMessage();
    }
}

// Import Functions
function importUsers($conn, $file_path, $file_ext) {
    $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
    
    $data = readFileData($file_path, $file_ext);
    
    foreach ($data as $row) {
        $stats['total']++;
        
        try {
            // Validate required fields
            $required_fields = ['full_name', 'email', 'phone', 'role', 'branch_id'];
            foreach ($required_fields as $field) {
                if (!isset($row[$field]) || empty($row[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Generate username from email
            $username = explode('@', $row['email'])[0];
            
            // Generate default password
            $password = password_hash('password123', PASSWORD_DEFAULT);
            $employeeId = trim((string)($row['employee_id'] ?? ''));
            $employeeId = $employeeId !== '' ? $employeeId : null;
            
            // Prepare SQL
            $sql = "INSERT INTO users (full_name, username, email, phone, role, branch_id, password, status, created_by, employee_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)";
            
            $conn->begin_transaction();
            $stmt = $conn->prepare($sql);
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param("sssssisis", 
                $row['full_name'],
                $username,
                $row['email'],
                $row['phone'],
                $row['role'],
                $row['branch_id'],
                $password,
                $created_by,
                $employeeId
            );
            
            if ($stmt->execute()) {
                assignEmployeeIdIfMissing($conn, (int)$stmt->insert_id);
                $conn->commit();
                $stats['success']++;
            } else {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $stats['failed']++;
            $stats['errors'][] = "Row {$stats['total']}: " . $e->getMessage();
        }
    }
    
    return $stats;
}

function importBranches($conn, $file_path, $file_ext) {
    $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
    
    $data = readFileData($file_path, $file_ext);
    
    foreach ($data as $row) {
        $stats['total']++;
        
        try {
            // Validate required fields
            $required_fields = ['branch_name', 'location', 'contact_person', 'contact_phone'];
            foreach ($required_fields as $field) {
                if (!isset($row[$field]) || empty($row[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Generate branch code
            $branch_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row['branch_name']), 0, 6));
            
            // Prepare SQL
            $sql = "INSERT INTO branches (branch_code, branch_name, location, contact_person, contact_phone, email, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
            
            $stmt = $conn->prepare($sql);
            $created_by = $_SESSION['user_id'];
            $email = $row['email'] ?? '';
            $stmt->bind_param("ssssssi", 
                $branch_code,
                $row['branch_name'],
                $row['location'],
                $row['contact_person'],
                $row['contact_phone'],
                $email,
                $created_by
            );
            
            if ($stmt->execute()) {
                $stats['success']++;
            } else {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = "Row {$stats['total']}: " . $e->getMessage();
        }
    }
    
    return $stats;
}

function importTanks($conn, $file_path, $file_ext) {
    $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
    
    $data = readFileData($file_path, $file_ext);
    
    foreach ($data as $row) {
        $stats['total']++;
        
        try {
            // Validate required fields
            $required_fields = ['tank_number', 'fuel_type_id', 'capacity', 'branch_id'];
            foreach ($required_fields as $field) {
                if (!isset($row[$field]) || empty($row[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Set default values
            $current_quantity = $row['current_quantity'] ?? 0;
            $minimum_threshold = $row['minimum_threshold'] ?? ($row['capacity'] * 0.1);
            
            // Prepare SQL
            $sql = "INSERT INTO tanks (tank_number, fuel_type_id, capacity, current_quantity, minimum_threshold, branch_id, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
            
            $stmt = $conn->prepare($sql);
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param("sidddis", 
                $row['tank_number'],
                $row['fuel_type_id'],
                $row['capacity'],
                $current_quantity,
                $minimum_threshold,
                $row['branch_id'],
                $created_by
            );
            
            if ($stmt->execute()) {
                $stats['success']++;
            } else {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = "Row {$stats['total']}: " . $e->getMessage();
        }
    }
    
    return $stats;
}

function importFuelTypes($conn, $file_path, $file_ext) {
    $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
    
    $data = readFileData($file_path, $file_ext);
    
    foreach ($data as $row) {
        $stats['total']++;
        
        try {
            // Validate required fields
            $required_fields = ['fuel_name', 'price_per_liter'];
            foreach ($required_fields as $field) {
                if (!isset($row[$field]) || empty($row[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Generate fuel code
            $fuel_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row['fuel_name']), 0, 4));
            
            // Prepare SQL
            $sql = "INSERT INTO fuel_types (fuel_code, fuel_name, price_per_liter, description, status, created_by) 
                    VALUES (?, ?, ?, ?, 'active', ?)";
            
            $stmt = $conn->prepare($sql);
            $created_by = $_SESSION['user_id'];
            $description = $row['description'] ?? '';
            $stmt->bind_param("ssdsi", 
                $fuel_code,
                $row['fuel_name'],
                $row['price_per_liter'],
                $description,
                $created_by
            );
            
            if ($stmt->execute()) {
                $stats['success']++;
            } else {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = "Row {$stats['total']}: " . $e->getMessage();
        }
    }
    
    return $stats;
}

function readFileData($file_path, $file_ext) {
    $data = [];
    
    switch ($file_ext) {
        case 'csv':
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== FALSE) {
                    $data[] = array_combine($headers, $row);
                }
                fclose($handle);
            }
            break;
            
        case 'xlsx':
        case 'xls':
            require_once '../vendor/autoload.php';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = [];
            $firstRow = true;
            
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $rowData = [];
                
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                
                if ($firstRow) {
                    $headers = $rowData;
                    $firstRow = false;
                } else {
                    $data[] = array_combine($headers, $rowData);
                }
            }
            break;
            
        case 'json':
            $json_data = file_get_contents($file_path);
            $data = json_decode($json_data, true);
            if ($data === null) {
                throw new Exception("Invalid JSON format");
            }
            break;
    }
    
    return $data;
}

// Export Functions
function exportData($conn, $export_type, $format = 'csv') {
    $availableTypes = array_keys(getAvailableExports());
    if (!in_array($export_type, $availableTypes, true)) {
        throw new Exception("Invalid export type");
    }
    if (!in_array($format, ['csv', 'json', 'excel', 'pdf'], true)) {
        throw new Exception("Invalid export format");
    }

    $export_dir = '../exports/';
    if (!is_dir($export_dir)) {
        if (!mkdir($export_dir, 0755, true) && !is_dir($export_dir)) {
            throw new RuntimeException('The exports directory could not be created.');
        }
    }
    
    $filename = $export_dir . $export_type . '_export_' . date('Y-m-d_H-i-s') . '.' . $format;
    
    switch ($export_type) {
        case 'users':
            $query = "SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.created_at DESC";
            break;
        case 'branches':
            $query = "SELECT * FROM branches ORDER BY created_at DESC";
            break;
        case 'tanks':
            $query = "SELECT t.*, f.fuel_name, b.branch_name FROM tanks t LEFT JOIN fuel_types f ON t.fuel_type_id = f.id LEFT JOIN branches b ON t.branch_id = b.id ORDER BY t.created_at DESC";
            break;
        case 'fuel_types':
            $query = "SELECT * FROM fuel_types ORDER BY created_at DESC";
            break;
        case 'sales':
            $query = "SELECT s.*, b.branch_name, f.fuel_name, u.full_name as attendant_name FROM sales s LEFT JOIN branches b ON s.branch_id = b.id LEFT JOIN fuel_types f ON s.fuel_type_id = f.id LEFT JOIN users u ON s.attendant_id = u.id ORDER BY s.sale_date DESC";
            break;
        case 'expenses':
            $query = "SELECT e.*, b.branch_name, u.full_name as recorded_by FROM expenses e LEFT JOIN branches b ON e.branch_id = b.id LEFT JOIN users u ON e.recorded_by = u.id ORDER BY e.created_at DESC";
            break;
        default:
            throw new Exception("Invalid export type");
    }
    
    $result = $conn->query($query);
    
    if ($format == 'csv') {
        exportToCSV($result, $filename);
    } elseif ($format == 'json') {
        exportToJSON($result, $filename);
    } elseif ($format == 'excel') {
        exportToExcel($result, $filename);
    } elseif ($format == 'pdf') {
        exportResultToPdf($result, $filename, getAvailableExports()[$export_type]);
    }
    
    return $filename;
}

function exportToCSV($result, $filename) {
    $output = fopen($filename, 'w');
    
    // Write headers
    $fields = $result->fetch_fields();
    $headers = [];
    foreach ($fields as $field) {
        $headers[] = $field->name;
    }
    fputcsv($output, $headers);
    
    // Write data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportToJSON($result, $filename) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
}

function exportToExcel($result, $filename) {
    require_once '../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Write headers
    $fields = $result->fetch_fields();
    $col = 1;
    foreach ($fields as $field) {
        $sheet->setCellValueByColumnAndRow($col, 1, $field->name);
        $col++;
    }
    
    // Write data
    $row = 2;
    while ($data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($data as $value) {
            $sheet->setCellValueByColumnAndRow($col, $row, $value);
            $col++;
        }
        $row++;
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filename);
}

// Database Backup Function
function backupDatabase($conn) {
    $backup_dir = '../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $filename = $backup_dir . 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $output = '';
    
    foreach ($tables as $table) {
        // Get table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $output .= "\n\n" . $row[1] . ";\n\n";
        
        // Get table data
        $result = $conn->query("SELECT * FROM `$table`");
        while ($row = $result->fetch_assoc()) {
            $values = array_map(function($value) use ($conn) {
                return is_null($value) ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
            }, array_values($row));
            
            $output .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
    }
    
    file_put_contents($filename, $output);
    
    // Create zip file
    $zip_filename = str_replace('.sql', '.zip', $filename);
    $zip = new ZipArchive();
    if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filename, basename($filename));
        $zip->close();
        unlink($filename); // Remove the SQL file
        return $zip_filename;
    }
    
    return $filename;
}

// Get available exports
function getAvailableExports() {
    return [
        'users' => 'Users',
        'branches' => 'Branches',
        'tanks' => 'Fuel Tanks',
        'fuel_types' => 'Fuel Types',
        'sales' => 'Sales Records',
        'expenses' => 'Expenses',
        'pumps' => 'Pumps',
        'transactions' => 'Transactions'
    ];
}

function getAvailableImports() {
    return [
        'users' => 'Users',
        'branches' => 'Branches',
        'tanks' => 'Fuel Tanks',
        'fuel_types' => 'Fuel Types',
        'sales' => 'Sales Records',
        'expenses' => 'Expenses'
    ];
}

// Get recent exports
$recent_exports = [];
$export_dir = '../exports/';
if (is_dir($export_dir)) {
    $files = scandir($export_dir, SCANDIR_SORT_DESCENDING);
    $files = array_filter($files, function($file) {
        return $file != '.' && $file != '..';
    });
    
    foreach ($files as $file) {
        $filepath = $export_dir . $file;
        $recent_exports[] = [
            'filename' => $file,
            'size' => filesize($filepath),
            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
        ];
    }
    $recent_exports = array_slice($recent_exports, 0, 10);
}

// Get recent backups
$recent_backups = [];
$backup_dir = '../backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    $files = array_filter($files, function($file) {
        return $file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'zip';
    });
    
    foreach ($files as $file) {
        $filepath = $backup_dir . $file;
        $recent_backups[] = [
            'filename' => $file,
            'size' => filesize($filepath),
            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
        ];
    }
    $recent_backups = array_slice($recent_backups, 0, 5);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import/Export - God Willing Business Center</title>
    <style>
        .import-export-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(25, 135, 84, 0.2);
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1.8rem;
        }
        
        .import-form, .export-form {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #475569;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
        }
        
        .file-input {
            padding: 40px;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .file-input:hover {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }
        
        .file-input i {
            font-size: 3rem;
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(25, 135, 84, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e2e8f0;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-green);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 8px;
        }
        
        .recent-files {
            display: grid;
            gap: 15px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .file-item:hover {
            background: #f0fdf4;
            border-color: var(--primary-green);
            transform: translateX(5px);
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .file-details {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .file-meta {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-success {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #7f1d1d;
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: var(--primary-green);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-green);
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .instructions {
            background: #f0fdf4;
            border: 2px solid #bbf7d0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .instructions-title {
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .instructions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .instructions-list li {
            padding: 8px 0;
            border-bottom: 1px solid #dcfce7;
        }
        
        .instructions-list li:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .import-export-container {
                padding: 10px;
            }
            
            .section-card {
                padding: 20px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                width: 100%;
                text-align: left;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .file-actions {
                width: 100%;
                display: flex;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="import-export-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-file-import"></i> Import & Export Management</h1>
            <p class="mb-0">Import data from files or export system data for backup and analysis</p>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success_message); ?></div>
                <?php if ($export_file !== ''): ?>
                    <a class="btn btn-success btn-sm ms-2"
                       href="../exports/<?php echo rawurlencode(basename($export_file)); ?>"
                       download>
                        <i class="fas fa-download"></i> Download Export
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Import Results -->
        <?php if (!empty($import_stats)): ?>
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-chart-bar"></i> Import Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $import_stats['total']; ?></div>
                        <div class="stat-label">Total Records</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #10b981;"><?php echo $import_stats['success']; ?></div>
                        <div class="stat-label">Successfully Imported</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #ef4444;"><?php echo $import_stats['failed']; ?></div>
                        <div class="stat-label">Failed Imports</div>
                    </div>
                </div>
                
                <?php if (!empty($import_stats['errors'])): ?>
                    <div class="instructions" style="background: #fee2e2; border-color: #fecaca;">
                        <div class="instructions-title" style="color: #ef4444;">
                            <i class="fas fa-exclamation-triangle"></i> Import Errors
                        </div>
                        <ul class="instructions-list">
                            <?php foreach ($import_stats['errors'] as $error): ?>
                                <li style="color: #7f1d1d;"><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('import')">
                <i class="fas fa-file-import"></i> Import Data
            </button>
            <button class="tab" onclick="switchTab('export')">
                <i class="fas fa-file-export"></i> Export Data
            </button>
            <button class="tab" onclick="switchTab('backup')">
                <i class="fas fa-database"></i> Database Backup
            </button>
            <button class="tab" onclick="switchTab('history')">
                <i class="fas fa-history"></i> History
            </button>
        </div>
        
        <!-- Import Tab -->
        <div id="import-tab" class="tab-content active">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-file-import"></i> Import Data from File</h3>
                <p class="text-muted mb-4">Upload CSV, Excel, or JSON files to import data into the system.</p>
                
                <form method="POST" enctype="multipart/form-data" class="import-form">
                    <div class="form-group">
                        <label class="form-label">Import Type</label>
                        <select name="import_type" class="form-control" required>
                            <option value="">Select data type to import</option>
                            <?php foreach (getAvailableImports() as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Upload File</label>
                        <div class="file-input" onclick="document.getElementById('import_file').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>
                                <strong>Click to upload file</strong>
                                <p class="text-muted mb-0">Supported formats: CSV, Excel (xlsx, xls), JSON</p>
                                <p class="text-muted mb-0">Max file size: 10MB</p>
                            </div>
                            <input type="file" id="import_file" name="import_file" accept=".csv,.xlsx,.xls,.json" 
                                   style="display: none;" onchange="updateFileName(this)">
                        </div>
                        <div id="file-name" class="mt-2 text-success" style="display: none;">
                            <i class="fas fa-check-circle"></i> <span id="selected-file">No file selected</span>
                        </div>
                    </div>
                    
                    <div class="instructions">
                        <div class="instructions-title">
                            <i class="fas fa-info-circle"></i> Import Instructions
                        </div>
                        <ul class="instructions-list">
                            <li>Ensure your file has proper column headers</li>
                            <li>Required fields must be filled in all rows</li>
                            <li>CSV files should use comma as delimiter</li>
                            <li>Excel files should have data in the first sheet</li>
                            <li>JSON files should be in array format with objects</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Start Import
                    </button>
                </form>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-download"></i> Download Template Files</h3>
                <p class="text-muted mb-4">Download empty template files with correct column structure for each data type.</p>
                
                <div class="stats-grid">
                    <?php foreach (getAvailableImports() as $value => $label): ?>
                        <a href="download_template.php?type=<?php echo $value; ?>" 
                           class="stat-card" style="text-decoration: none; cursor: pointer;">
                            <div class="stat-value">
                                <i class="fas fa-file-excel" style="color: #10b981;"></i>
                            </div>
                            <div class="stat-label"><?php echo $label; ?> Template</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Export Tab -->
        <div id="export-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-file-export"></i> Export System Data</h3>
                <p class="text-muted mb-4">Export system data in various formats for backup, analysis, or reporting.</p>
                
                <form method="POST" class="export-form">
                    <div class="form-group">
                        <label class="form-label">Export Type</label>
                        <select name="export_type" class="form-control" required>
                            <option value="">Select data type to export</option>
                            <?php foreach (getAvailableExports() as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Export Format</label>
                        <select name="export_format" class="form-control" required>
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <option value="json">JSON (JavaScript Object Notation)</option>
                            <option value="excel">Excel (Microsoft Excel)</option>
                            <option value="pdf">PDF (Portable Document Format)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date Range (Optional)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <input type="date" name="start_date" class="form-control" placeholder="Start Date">
                            <input type="date" name="end_date" class="form-control" placeholder="End Date">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Backup Tab -->
        <div id="backup-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-database"></i> Database Backup</h3>
                <p class="text-muted mb-4">Create a complete backup of the entire database for disaster recovery.</p>
                
                <div class="alert" style="background: #f0f9ff; border-color: #7dd3fc; color: #0369a1;">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Security Notice:</strong> Database backups contain sensitive information. 
                        Store backup files securely and limit access to authorized personnel only.
                    </div>
                </div>
                
                <form method="POST" class="export-form">
                    <div class="form-group">
                        <label class="form-label">Backup Options</label>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="include_data" id="include_data" checked>
                            <label class="form-check-label" for="include_data">Include all data records</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="include_schema" id="include_schema" checked>
                            <label class="form-check-label" for="include_schema">Include database structure</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="compress_backup" id="compress_backup" checked>
                            <label class="form-check-label" for="compress_backup">Compress backup (ZIP format)</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="backup_database" class="btn btn-danger">
                        <i class="fas fa-database"></i> Create Database Backup
                    </button>
                </form>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Backups</h3>
                
                <?php if (empty($recent_backups)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No backups found</p>
                    </div>
                <?php else: ?>
                    <div class="recent-files">
                        <?php foreach ($recent_backups as $backup): ?>
                            <div class="file-item">
                                <div class="file-info">
                                    <div class="file-icon">
                                        <i class="fas fa-file-archive"></i>
                                    </div>
                                    <div class="file-details">
                                        <div class="file-name"><?php echo htmlspecialchars($backup['filename']); ?></div>
                                        <div class="file-meta">
                                            <?php echo formatBytes($backup['size']); ?> • 
                                            <?php echo $backup['modified']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="../backups/<?php echo htmlspecialchars($backup['filename']); ?>" 
                                       class="btn btn-sm btn-success" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Exports</h3>
                
                <?php if (empty($recent_exports)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No exports found</p>
                    </div>
                <?php else: ?>
                    <div class="recent-files">
                        <?php foreach ($recent_exports as $export): ?>
                            <div class="file-item">
                                <div class="file-info">
                                    <div class="file-icon">
                                        <?php 
                                        $ext = pathinfo($export['filename'], PATHINFO_EXTENSION);
                                        if ($ext == 'csv') {
                                            echo '<i class="fas fa-file-csv"></i>';
                                        } elseif ($ext == 'json') {
                                            echo '<i class="fas fa-file-code"></i>';
                                        } elseif ($ext == 'xlsx' || $ext == 'xls') {
                                            echo '<i class="fas fa-file-excel"></i>';
                                        } else {
                                            echo '<i class="fas fa-file"></i>';
                                        }
                                        ?>
                                    </div>
                                    <div class="file-details">
                                        <div class="file-name"><?php echo htmlspecialchars($export['filename']); ?></div>
                                        <div class="file-meta">
                                            <?php echo formatBytes($export['size']); ?> • 
                                            <?php echo $export['modified']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="../exports/<?php echo htmlspecialchars($export['filename']); ?>" 
                                       class="btn btn-sm btn-success" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteFile('<?php echo $export['filename']; ?>', 'export')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate selected tab button
            event.currentTarget.classList.add('active');
        }
        
        function updateFileName(input) {
            const fileNameDisplay = document.getElementById('file-name');
            const selectedFileSpan = document.getElementById('selected-file');
            
            if (input.files.length > 0) {
                selectedFileSpan.textContent = input.files[0].name;
                fileNameDisplay.style.display = 'block';
            } else {
                fileNameDisplay.style.display = 'none';
            }
        }
        
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        function deleteFile(filename, type) {
            if (confirm('Are you sure you want to delete this file?')) {
                fetch('delete_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'filename=' + encodeURIComponent(filename) + '&type=' + type
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('File deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting file: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting file');
                });
            }
        }
        
        // Initialize file size formatting
        document.addEventListener('DOMContentLoaded', function() {
            // Format file sizes
            document.querySelectorAll('.file-meta').forEach(element => {
                const text = element.textContent;
                if (text.includes('Bytes')) {
                    const parts = text.split('•');
                    if (parts.length > 1) {
                        const sizePart = parts[0].trim();
                        const sizeMatch = sizePart.match(/(\d+(\.\d+)?)\s+Bytes/);
                        if (sizeMatch) {
                            const bytes = parseFloat(sizeMatch[1]);
                            element.textContent = formatBytes(bytes) + ' • ' + parts[1];
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to format bytes
function formatBytes($bytes, $decimals = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $decimals) . ' ' . $units[$pow];
}
?>