<?php
session_start();
require_once '../config/db.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit();
}

$type = $_GET['type'] ?? '';

$templates = [
    'users' => [
        'headers' => ['full_name', 'email', 'phone', 'role', 'branch_id'],
        'example' => ['John Doe', 'john@example.com', '0712345678', 'attendant', '1']
    ],
    'branches' => [
        'headers' => ['branch_name', 'location', 'contact_person', 'contact_phone', 'email'],
        'example' => ['Main Branch', 'Nairobi CBD', 'Manager Smith', '0723456789', 'main@branch.com']
    ],
    'tanks' => [
        'headers' => ['tank_number', 'fuel_type_id', 'capacity', 'current_quantity', 'minimum_threshold', 'branch_id'],
        'example' => ['TANK-001', '1', '10000', '5000', '1000', '1']
    ],
    'fuel_types' => [
        'headers' => ['fuel_name', 'price_per_liter', 'description'],
        'example' => ['Premium Unleaded', '150.50', 'High quality unleaded fuel']
    ],
    'sales' => [
        'headers' => ['branch_id', 'fuel_type_id', 'quantity', 'price_per_liter', 'total_amount', 'payment_method', 'attendant_id', 'sale_date'],
        'example' => ['1', '1', '20', '150.50', '3010.00', 'cash', '1', '2024-01-15 10:30:00']
    ],
    'expenses' => [
        'headers' => ['branch_id', 'expense_type', 'amount', 'description', 'expense_date', 'recorded_by'],
        'example' => ['1', 'maintenance', '5000', 'Pump maintenance', '2024-01-15', '1']
    ]
];

if (!isset($templates[$type])) {
    die("Invalid template type");
}

$template = $templates[$type];

// Create CSV content
$output = fopen('php://output', 'w');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $type . '_template.csv');

// Write headers
fputcsv($output, $template['headers']);

// Write example row
fputcsv($output, $template['example']);

fclose($output);
exit();
?>