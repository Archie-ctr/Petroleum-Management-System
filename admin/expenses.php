<?php
require_once '../includes/header.php';

// Check if user has admin or accountant access
if (!in_array($_SESSION['user_role'], ['admin', 'accountant', 'manager'])) {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Check if user has permission to manage expenses
if ($_SESSION['user_role'] === 'manager') {
    // Managers can only view expenses for their branch
    $branch_access = true;
} else {
    // Admin and accountant can manage all expenses
    $branch_access = false;
}

// Get user's branch if manager
$user_branch_id = null;
if ($_SESSION['user_role'] === 'manager' && isset($_SESSION['branch_id'])) {
    $user_branch_id = $_SESSION['branch_id'];
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_branch = isset($_GET['branch']) ? intval($_GET['branch']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($form_action === 'add' || $form_action === 'edit') {
        // Process expense form
        $expense_data = [
            'branch_id' => $_POST['branch_id'] ?? ($user_branch_id ?? 0),
            'expense_type' => trim($_POST['expense_type']),
            'expense_category' => trim($_POST['expense_category']),
            'amount' => floatval($_POST['amount']),
            'description' => trim($_POST['description']),
            'receipt_number' => trim($_POST['receipt_number']),
            'payment_method' => $_POST['payment_method'],
            'payment_date' => $_POST['payment_date'],
            'vendor_name' => trim($_POST['vendor_name']),
            'vendor_phone' => trim($_POST['vendor_phone']),
            'recorded_by' => $_SESSION['user_id'],
            'status' => ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'accountant') ? 'approved' : 'pending'
        ];
        
        // Upload receipt if provided
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === 0) {
            $upload_result = uploadReceipt($_FILES['receipt_file']);
            if ($upload_result['success']) {
                $expense_data['receipt_file'] = $upload_result['filename'];
            } else {
                $_SESSION['error'] = $upload_result['error'];
                header('Location: expenses.php?action=' . $form_action);
                exit();
            }
        }
        
        if ($form_action === 'add') {
            // Add new expense
            $result = addExpense($expense_data);
            if ($result['success']) {
                $_SESSION['success'] = 'Expense added successfully!';
                if ($expense_data['status'] === 'pending') {
                    $_SESSION['info'] = 'Expense is pending approval';
                }
                header('Location: expenses.php');
                exit();
            } else {
                $_SESSION['error'] = $result['error'];
                header('Location: expenses.php?action=add');
                exit();
            }
        } else {
            // Edit existing expense
            $expense_data['id'] = $id;
            $result = updateExpense($expense_data);
            if ($result['success']) {
                $_SESSION['success'] = 'Expense updated successfully!';
                header('Location: expenses.php?view=' . $id);
                exit();
            } else {
                $_SESSION['error'] = $result['error'];
                header('Location: expenses.php?action=edit&id=' . $id);
                exit();
            }
        }
    } elseif ($form_action === 'delete') {
        // Delete expense
        $result = deleteExpense($id);
        if ($result['success']) {
            $_SESSION['success'] = 'Expense deleted successfully!';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        header('Location: expenses.php');
        exit();
    } elseif ($form_action === 'approve') {
        // Approve expense
        $result = updateExpenseStatus($id, 'approved', $_SESSION['user_id']);
        if ($result['success']) {
            $_SESSION['success'] = 'Expense approved successfully!';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        header('Location: expenses.php');
        exit();
    } elseif ($form_action === 'reject') {
        // Reject expense
        $reason = $_POST['rejection_reason'] ?? '';
        $result = updateExpenseStatus($id, 'rejected', $_SESSION['user_id'], $reason);
        if ($result['success']) {
            $_SESSION['success'] = 'Expense rejected successfully!';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        header('Location: expenses.php');
        exit();
    }
}

// Handle actions from GET requests
if ($action === 'delete' && $id > 0) {
    $result = deleteExpense($id);
    if ($result['success']) {
        $_SESSION['success'] = 'Expense deleted successfully!';
    } else {
        $_SESSION['error'] = $result['error'];
    }
    header('Location: expenses.php');
    exit();
} elseif ($action === 'approve' && $id > 0) {
    $result = updateExpenseStatus($id, 'approved', $_SESSION['user_id']);
    if ($result['success']) {
        $_SESSION['success'] = 'Expense approved successfully!';
    } else {
        $_SESSION['error'] = $result['error'];
    }
    header('Location: expenses.php');
    exit();
} elseif ($action === 'reject' && $id > 0) {
    $result = updateExpenseStatus($id, 'rejected', $_SESSION['user_id']);
    if ($result['success']) {
        $_SESSION['success'] = 'Expense rejected successfully!';
    } else {
        $_SESSION['error'] = $result['error'];
    }
    header('Location: expenses.php');
    exit();
}

// Get expense categories
$categories = getExpenseCategories();
$branches = getBranches();
$payment_methods = ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'credit_card'];

// Function to upload receipt
function uploadReceipt($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'receipt_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $upload_path = '../uploads/receipts/' . $filename;
    
    if (!is_dir('../uploads/receipts/')) {
        mkdir('../uploads/receipts/', 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file.'];
    }
}

// Function to add expense
function addExpense($data) {
    global $conn;
    
    try {
        $sql = "INSERT INTO expenses (
            branch_id, expense_type, expense_category, amount, description, 
            receipt_number, receipt_file, payment_method, payment_date, 
            vendor_name, vendor_phone, recorded_by, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issdsssssssss",
            $data['branch_id'],
            $data['expense_type'],
            $data['expense_category'],
            $data['amount'],
            $data['description'],
            $data['receipt_number'],
            $data['receipt_file'] ?? null,
            $data['payment_method'],
            $data['payment_date'],
            $data['vendor_name'],
            $data['vendor_phone'],
            $data['recorded_by'],
            $data['status']
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $stmt->insert_id];
        } else {
            return ['success' => false, 'error' => 'Failed to add expense: ' . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Function to update expense
function updateExpense($data) {
    global $conn;
    
    try {
        // Get current receipt file
        $current_receipt = null;
        if (isset($data['id'])) {
            $check_sql = "SELECT receipt_file FROM expenses WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $data['id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $current_receipt = $check_result->fetch_assoc()['receipt_file'] ?? null;
        }
        
        // If new receipt uploaded, delete old one
        if (isset($data['receipt_file']) && $current_receipt) {
            $old_file = '../uploads/receipts/' . $current_receipt;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        $sql = "UPDATE expenses SET 
            branch_id = ?, 
            expense_type = ?, 
            expense_category = ?, 
            amount = ?, 
            description = ?, 
            receipt_number = ?, 
            receipt_file = ?, 
            payment_method = ?, 
            payment_date = ?, 
            vendor_name = ?, 
            vendor_phone = ?, 
            status = ?,
            updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issdssssssssi",
            $data['branch_id'],
            $data['expense_type'],
            $data['expense_category'],
            $data['amount'],
            $data['description'],
            $data['receipt_number'],
            $data['receipt_file'] ?? $current_receipt,
            $data['payment_method'],
            $data['payment_date'],
            $data['vendor_name'],
            $data['vendor_phone'],
            $data['status'],
            $data['id']
        );
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to update expense: ' . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Function to delete expense
function deleteExpense($id) {
    global $conn;
    
    try {
        // Get receipt file first
        $sql = "SELECT receipt_file FROM expenses WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $expense = $result->fetch_assoc();
        
        // Delete receipt file if exists
        if ($expense && $expense['receipt_file']) {
            $file_path = '../uploads/receipts/' . $expense['receipt_file'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete expense record
        $sql = "DELETE FROM expenses WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to delete expense: ' . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Function to update expense status
function updateExpenseStatus($id, $status, $approved_by = null, $rejection_reason = null) {
    global $conn;
    
    try {
        $sql = "UPDATE expenses SET 
            status = ?, 
            approved_by = ?, 
            approved_at = NOW(),
            rejection_reason = ?
        WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $status, $approved_by, $rejection_reason, $id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to update expense status: ' . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Function to get expense categories
function getExpenseCategories() {
    global $conn;
    
    $categories = [
        'Fuel Purchase',
        'Vehicle Maintenance',
        'Employee Salaries',
        'Utilities',
        'Rent',
        'Office Supplies',
        'Marketing',
        'Insurance',
        'Taxes',
        'Repairs',
        'Security',
        'Cleaning',
        'Training',
        'Travel',
        'Equipment',
        'Other'
    ];
    
    return $categories;
}

// Function to get branches
function getBranches() {
    global $conn;
    
    try {
        $sql = "SELECT id, branch_name, location FROM branches WHERE status = 'active' ORDER BY branch_name";
        $result = $conn->query($sql);
        
        $branches = [];
        while ($row = $result->fetch_assoc()) {
            $branches[$row['id']] = $row;
        }
        
        return $branches;
    } catch (Exception $e) {
        return [];
    }
}

// Function to get expenses with filters
function getExpenses($filters = []) {
    global $conn, $user_branch_id, $branch_access;
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Add branch filter for managers
    if ($branch_access && $user_branch_id) {
        $where_conditions[] = "e.branch_id = ?";
        $params[] = $user_branch_id;
        $types .= "i";
    }
    
    // Add status filter
    if (!empty($filters['status'])) {
        $where_conditions[] = "e.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    // Add branch filter
    if (!empty($filters['branch'])) {
        $where_conditions[] = "e.branch_id = ?";
        $params[] = $filters['branch'];
        $types .= "i";
    }
    
    // Add date filter
    if (!empty($filters['date'])) {
        $where_conditions[] = "DATE(e.payment_date) = ?";
        $params[] = $filters['date'];
        $types .= "s";
    }
    
    // Add category filter
    if (!empty($filters['category'])) {
        $where_conditions[] = "e.expense_category = ?";
        $params[] = $filters['category'];
        $types .= "s";
    }
    
    // Build WHERE clause
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    try {
        $sql = "SELECT e.*, 
                b.branch_name, 
                b.location as branch_location,
                u.full_name as recorded_by_name,
                a.full_name as approved_by_name
               FROM expenses e
               LEFT JOIN branches b ON e.branch_id = b.id
               LEFT JOIN users u ON e.recorded_by = u.id
               LEFT JOIN users a ON e.approved_by = a.id
               $where_clause
               ORDER BY e.payment_date DESC, e.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $expenses = [];
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;
        }
        
        return $expenses;
    } catch (Exception $e) {
        return [];
    }
}

// Function to get expense summary
function getExpenseSummary($filters = []) {
    global $conn, $user_branch_id, $branch_access;
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Add branch filter for managers
    if ($branch_access && $user_branch_id) {
        $where_conditions[] = "branch_id = ?";
        $params[] = $user_branch_id;
        $types .= "i";
    }
    
    // Add other filters
    if (!empty($filters['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['branch'])) {
        $where_conditions[] = "branch_id = ?";
        $params[] = $filters['branch'];
        $types .= "i";
    }
    
    if (!empty($filters['date'])) {
        $where_conditions[] = "DATE(payment_date) = ?";
        $params[] = $filters['date'];
        $types .= "s";
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    try {
        // Get total expenses
        $sql = "SELECT 
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_amount
               FROM expenses $where_clause";
        
        $stmt = $conn->prepare($sql);
        
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    } catch (Exception $e) {
        return null;
    }
}

// Get expenses for display
$filters = [
    'status' => $filter_status,
    'branch' => $filter_branch,
    'date' => $filter_date,
    'category' => $filter_category
];

$expenses = getExpenses($filters);
$summary = getExpenseSummary($filters);

// Get expense for viewing/editing
$expense = null;
if ($id > 0 && ($action === 'view' || $action === 'edit')) {
    try {
        $sql = "SELECT e.*, b.branch_name, u.full_name as recorded_by_name 
                FROM expenses e
                LEFT JOIN branches b ON e.branch_id = b.id
                LEFT JOIN users u ON e.recorded_by = u.id
                WHERE e.id = ?";
        
        if ($branch_access && $user_branch_id) {
            $sql .= " AND e.branch_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_branch_id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $expense = $result->fetch_assoc();
        
        if (!$expense) {
            $_SESSION['error'] = 'Expense not found or you do not have permission to access it.';
            header('Location: expenses.php');
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error loading expense: ' . $e->getMessage();
        header('Location: expenses.php');
        exit();
    }
}
?>
<style>
    /* Expenses Page Specific Styles */
    .expense-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        background: linear-gradient(145deg, #ffffff, #f8f9fa);
        overflow: hidden;
    }
    
    .expense-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .expense-status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: #000;
    }
    
    .status-approved {
        background: linear-gradient(135deg, #198754 0%, #0d6efd 100%);
        color: white;
    }
    
    .status-rejected {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }
    
    .expense-amount {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--primary-green);
        line-height: 1;
    }
    
    .expense-meta {
        font-size: 0.85rem;
        color: #666;
    }
    
    .receipt-preview {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .receipt-preview:hover {
        transform: scale(1.05);
        border-color: var(--primary-green);
    }
    
    .filter-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    
    .summary-card {
        border-radius: 12px;
        padding: 25px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
    }
    
    .summary-card.total {
        background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
    }
    
    .summary-card.pending {
        background: linear-gradient(135deg, #ffc107 0%, #e6a700 100%);
    }
    
    .summary-card.approved {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    }
    
    .summary-card.rejected {
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
    }
    
    .summary-value {
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .summary-label {
        font-size: 0.9rem;
        opacity: 0.9;
        font-weight: 600;
    }
    
    .vendor-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border-left: 5px solid var(--primary-green);
        box-shadow: 0 3px 15px rgba(0,0,0,0.05);
    }
    
    .vendor-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 15px;
    }
    
    .payment-method-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: rgba(25, 135, 84, 0.1);
        color: var(--primary-green);
        border: 1px solid rgba(25, 135, 84, 0.2);
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .action-buttons .btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .action-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .timeline {
        position: relative;
        padding-left: 30px;
        border-left: 2px solid #e2e8f0;
        margin-left: 15px;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -36px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--primary-green);
        border: 3px solid white;
        box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.2);
    }
    
    .timeline-date {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 5px;
    }
    
    .timeline-content {
        font-size: 0.9rem;
        color: #333;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .export-buttons .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .export-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .summary-card {
            margin-bottom: 15px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-buttons .btn {
            width: 100%;
        }
        
        .export-buttons {
            flex-direction: column;
        }
        
        .export-buttons .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="dashboard-title">Expenses Management</h1>
            <p class="text-muted mb-0">Track and manage all business expenses</p>
        </div>
        <div>
            <?php if ($_SESSION['user_role'] !== 'manager' || !$branch_access): ?>
                <a href="expenses.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add New Expense
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> <?php echo $_SESSION['info']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>
    
    <?php if ($action === 'view' && $expense): ?>
        <!-- View Expense Details -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="expense-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h3 class="mb-2">Expense Details</h3>
                            <div class="d-flex align-items-center gap-3">
                                <span class="expense-status-badge status-<?php echo $expense['status']; ?>">
                                    <?php echo ucfirst($expense['status']); ?>
                                </span>
                                <span class="text-muted">#<?php echo $expense['id']; ?></span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="expense-amount">$<?php echo number_format($expense['amount'], 2); ?></div>
                            <div class="expense-meta"><?php echo date('M d, Y', strtotime($expense['payment_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Expense Type</label>
                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['expense_type']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Category</label>
                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['expense_category']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Branch</label>
                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['branch_name'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Payment Method</label>
                                <p class="mb-0 fw-semibold">
                                    <span class="payment-method-badge"><?php echo ucfirst($expense['payment_method']); ?></span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Receipt Number</label>
                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['receipt_number']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Recorded By</label>
                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['recorded_by_name']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small">Description</label>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($expense['description'])); ?></p>
                    </div>
                    
                    <?php if ($expense['rejection_reason']): ?>
                        <div class="alert alert-danger">
                            <h6 class="alert-heading mb-2"><i class="fas fa-times-circle me-2"></i> Rejection Reason</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($expense['rejection_reason'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                Created: <?php echo date('M d, Y H:i', strtotime($expense['created_at'])); ?>
                            </small>
                        </div>
                        <div class="action-buttons">
                            <?php if ($_SESSION['user_role'] !== 'manager' || !$branch_access): ?>
                                <a href="expenses.php?action=edit&id=<?php echo $expense['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                            <?php endif; ?>
                            
                            <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant']) && $expense['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="fas fa-check me-1"></i> Approve
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['user_role'] !== 'manager' || !$branch_access): ?>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            <?php endif; ?>
                            
                            <a href="expenses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Vendor Information -->
                <div class="vendor-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="vendor-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Vendor Information</h5>
                            <p class="text-muted mb-0">Supplier/Service Provider</p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small">Vendor Name</label>
                        <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['vendor_name']); ?></p>
                    </div>
                    
                    <?php if ($expense['vendor_phone']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Contact Phone</label>
                            <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($expense['vendor_phone']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Receipt Preview -->
                <?php if ($expense['receipt_file']): ?>
                    <div class="expense-card p-4 mb-4">
                        <h5 class="mb-3">Receipt</h5>
                        <?php
                        $file_ext = pathinfo($expense['receipt_file'], PATHINFO_EXTENSION);
                        $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        
                        <?php if ($is_image): ?>
                            <img src="../uploads/receipts/<?php echo htmlspecialchars($expense['receipt_file']); ?>" 
                                 class="receipt-preview mb-3" 
                                 alt="Receipt">
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-file-pdf fa-3x mb-3 text-danger"></i>
                                <p class="mb-2">PDF Receipt</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="../uploads/receipts/<?php echo htmlspecialchars($expense['receipt_file']); ?>" 
                               class="btn btn-outline-primary" 
                               target="_blank" 
                               download>
                                <i class="fas fa-download me-2"></i> Download Receipt
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Expense Timeline -->
                <div class="expense-card p-4">
                    <h5 class="mb-4">Expense Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo date('M d, Y H:i', strtotime($expense['created_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Expense Recorded</strong><br>
                                By: <?php echo htmlspecialchars($expense['recorded_by_name']); ?>
                            </div>
                        </div>
                        
                        <?php if ($expense['status'] === 'approved' && $expense['approved_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y H:i', strtotime($expense['approved_at'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <strong>Expense Approved</strong><br>
                                    By: <?php echo htmlspecialchars($expense['approved_by_name'] ?? 'System'); ?>
                                </div>
                            </div>
                        <?php elseif ($expense['status'] === 'rejected' && $expense['approved_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y H:i', strtotime($expense['approved_at'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <strong>Expense Rejected</strong><br>
                                    By: <?php echo htmlspecialchars($expense['approved_by_name'] ?? 'System'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modals -->
        <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveModalLabel">Approve Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <p>Are you sure you want to approve this expense?</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This action cannot be undone.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">Reject Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" 
                                          placeholder="Please provide a reason for rejecting this expense..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Delete Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <p>Are you sure you want to delete this expense?</p>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                This action cannot be undone. All data including the receipt will be permanently deleted.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Expense Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="expense-card p-4">
                    <h3 class="mb-4">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus-circle' : 'edit'; ?> me-2"></i>
                        <?php echo $action === 'add' ? 'Add New Expense' : 'Edit Expense'; ?>
                    </h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $action; ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php if ($_SESSION['user_role'] !== 'manager' || !$branch_access): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="branch_id" class="form-label">Branch <span class="text-danger">*</span></label>
                                    <select class="form-select" id="branch_id" name="branch_id" required>
                                        <option value="">Select Branch</option>
                                        <?php foreach ($branches as $branch_id => $branch): ?>
                                            <option value="<?php echo $branch_id; ?>"
                                                <?php echo ($action === 'edit' && $expense['branch_id'] == $branch_id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="branch_id" value="<?php echo $user_branch_id; ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-6 mb-3">
                                <label for="expense_category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="expense_category" name="expense_category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"
                                            <?php echo ($action === 'edit' && $expense['expense_category'] === $category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expense_type" class="form-label">Expense Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="expense_type" name="expense_type" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($expense['expense_type']) : ''; ?>" 
                                       required placeholder="e.g., Office Supplies Purchase">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Amount ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo $action === 'edit' ? number_format($expense['amount'], 2, '.', '') : ''; ?>" 
                                           step="0.01" min="0" required placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo $method; ?>"
                                            <?php echo ($action === 'edit' && $expense['payment_method'] === $method) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo $action === 'edit' ? $expense['payment_date'] : date('Y-m-d'); ?>" 
                                       required max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="receipt_number" class="form-label">Receipt Number</label>
                                <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($expense['receipt_number']) : ''; ?>" 
                                       placeholder="Enter receipt number if available">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="receipt_file" class="form-label">Receipt File</label>
                                <input type="file" class="form-control" id="receipt_file" name="receipt_file" 
                                       accept="image/*,.pdf">
                                <small class="text-muted">Max 5MB. Allowed: JPG, PNG, GIF, PDF</small>
                                
                                <?php if ($action === 'edit' && $expense['receipt_file']): ?>
                                    <div class="mt-2">
                                        <small>Current file: <?php echo htmlspecialchars($expense['receipt_file']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      required placeholder="Describe the expense..."><?php echo $action === 'edit' ? htmlspecialchars($expense['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vendor_name" class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($expense['vendor_name']) : ''; ?>" 
                                       required placeholder="Enter vendor/supplier name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="vendor_phone" class="form-label">Vendor Phone</label>
                                <input type="tel" class="form-control" id="vendor_phone" name="vendor_phone" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($expense['vendor_phone']) : ''; ?>" 
                                       placeholder="Enter vendor phone number">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="expenses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                <?php echo $action === 'add' ? 'Add Expense' : 'Update Expense'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Expenses List View -->
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="summary-card total">
                    <div class="summary-value">$<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></div>
                    <div class="summary-label">Total Expenses</div>
                    <small><?php echo $summary['total_count'] ?? 0; ?> records</small>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="summary-card pending">
                    <div class="summary-value">$<?php echo number_format($summary['pending_amount'] ?? 0, 2); ?></div>
                    <div class="summary-label">Pending Approval</div>
                    <small>Awaiting review</small>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="summary-card approved">
                    <div class="summary-value">$<?php echo number_format($summary['approved_amount'] ?? 0, 2); ?></div>
                    <div class="summary-label">Approved</div>
                    <small>Completed expenses</small>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="summary-card rejected">
                    <div class="summary-value">$<?php echo number_format($summary['rejected_amount'] ?? 0, 2); ?></div>
                    <div class="summary-label">Rejected</div>
                    <small>Declined expenses</small>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-card mb-4">
            <h5 class="mb-3">Filter Expenses</h5>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <?php if ($_SESSION['user_role'] !== 'manager' || !$branch_access): ?>
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" name="branch">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch_id => $branch): ?>
                                <option value="<?php echo $branch_id; ?>" 
                                    <?php echo $filter_branch == $branch_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"
                                <?php echo $filter_category === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="expenses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Clear Filters
                            </a>
                        </div>
                        
                        <div class="export-buttons">
                            <a href="export_expenses.php?format=pdf&<?php echo http_build_query($_GET); ?>" 
                               class="btn btn-danger" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i> Export PDF
                            </a>
                            <a href="export_expenses.php?format=excel&<?php echo http_build_query($_GET); ?>" 
                               class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Expenses Table -->
        <div class="expense-card p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Expense Details</th>
                            <th>Branch</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Expenses Found</h5>
                                        <p class="text-muted mb-0">Add your first expense to get started</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $exp): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-medium">#<?php echo $exp['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-<?php echo getExpenseIcon($exp['expense_category']); ?> fa-lg text-primary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($exp['expense_type']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($exp['expense_category']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($exp['branch_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($exp['branch_location'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success">$<?php echo number_format($exp['amount'], 2); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-<?php echo getPaymentMethodIcon($exp['payment_method']); ?> me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $exp['payment_method'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="expense-status-badge status-<?php echo $exp['status']; ?>">
                                            <?php echo ucfirst($exp['status']); ?>
                                        </span>
                                        <?php if ($exp['status'] === 'approved' && $exp['approved_by_name']): ?>
                                            <small class="d-block text-muted mt-1">
                                                By: <?php echo htmlspecialchars($exp['approved_by_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo date('M d, Y', strtotime($exp['payment_date'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($exp['created_at'])); ?></small>
                                    </td>
                                    <td class="pe-4">
                                        <div class="action-buttons">
                                            <a href="expenses.php?view=<?php echo $exp['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (($_SESSION['user_role'] !== 'manager' || !$branch_access) && $exp['status'] !== 'approved'): ?>
                                                <a href="expenses.php?action=edit&id=<?php echo $exp['id']; ?>" 
                                                   class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant']) && $exp['status'] === 'pending'): ?>
                                                <a href="expenses.php?action=approve&id=<?php echo $exp['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Approve"
                                                   onclick="return confirm('Are you sure you want to approve this expense?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="expenses.php?action=reject&id=<?php echo $exp['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   title="Reject"
                                                   onclick="return confirm('Are you sure you want to reject this expense?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if (!empty($expenses)): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <small class="text-muted">
                        Showing <?php echo count($expenses); ?> of <?php echo $summary['total_count'] ?? 0; ?> expenses
                    </small>
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Helper function to get expense icon
function getExpenseIcon($category) {
    $icons = [
        'Fuel Purchase' => 'gas-pump',
        'Vehicle Maintenance' => 'car',
        'Employee Salaries' => 'users',
        'Utilities' => 'bolt',
        'Rent' => 'building',
        'Office Supplies' => 'box',
        'Marketing' => 'bullhorn',
        'Insurance' => 'shield-alt',
        'Taxes' => 'file-invoice-dollar',
        'Repairs' => 'tools',
        'Security' => 'user-shield',
        'Cleaning' => 'broom',
        'Training' => 'graduation-cap',
        'Travel' => 'plane',
        'Equipment' => 'cogs',
        'Other' => 'file-alt'
    ];
    
    return $icons[$category] ?? 'file-invoice-dollar';
}

// Helper function to get payment method icon
function getPaymentMethodIcon($method) {
    $icons = [
        'cash' => 'money-bill-wave',
        'bank_transfer' => 'university',
        'mobile_money' => 'mobile-alt',
        'cheque' => 'money-check',
        'credit_card' => 'credit-card'
    ];
    
    return $icons[$method] ?? 'money-bill-wave';
}
?>

<?php
require_once '../includes/footer.php';
?>