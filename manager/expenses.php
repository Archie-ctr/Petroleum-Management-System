<?php
require_once '../includes/manager_header.php';

$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

// Handle expense submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        $expense_number = 'EXP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $expense_type = $conn->real_escape_string($_POST['expense_type']);
        $amount = floatval($_POST['amount']);
        $description = $conn->real_escape_string($_POST['description']);
        $expense_date = $conn->real_escape_string($_POST['expense_date']);
        
        // Handle file upload
        $receipt_path = '';
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $upload_dir = '../uploads/expenses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $file_name = $expense_number . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_path)) {
                $receipt_path = 'expenses/' . $file_name;
            }
        }
        
        $sql = "INSERT INTO expenses (expense_number, branch_id, expense_type, amount, 
                                      description, expense_date, recorded_by, receipt_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisdssis", $expense_number, $branch_id, $expense_type, $amount, 
                          $description, $expense_date, $user_id, $receipt_path);
        
        if ($stmt->execute()) {
            $success = "Expense recorded successfully! Reference: $expense_number";
        } else {
            $error = "Error recording expense: " . $conn->error;
        }
    }
}

// Get branch expenses
$expenses_sql = "SELECT * FROM expenses 
                 WHERE branch_id = $branch_id 
                 ORDER BY expense_date DESC";
$expenses = $conn->query($expenses_sql);

// Expense statistics
$stats_sql = "SELECT 
              SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
              SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
              SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_total
              FROM expenses 
              WHERE branch_id = $branch_id 
              AND MONTH(expense_date) = MONTH(CURDATE())";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill-wave"></i> Record New Expense
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="expense_type" class="form-label">Expense Type *</label>
                        <select class="form-control" id="expense_type" name="expense_type" required>
                            <option value="">Select Type</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Fuel Purchase">Fuel Purchase</option>
                            <option value="Transport">Transport</option>
                            <option value="Advertising">Advertising</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount ($) *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_date" class="form-label">Expense Date *</label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" required placeholder="Enter expense details..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="receipt" class="form-label">Receipt (Optional)</label>
                        <input type="file" class="form-control" id="receipt" name="receipt" 
                               accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">Upload receipt image or PDF (Max: 5MB)</small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="add_expense" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Record Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Expense Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Month's Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Approved Expenses
                        <span class="badge bg-success rounded-pill">
                            $<?php echo number_format($stats['approved_total'], 2); ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Pending Approval
                        <span class="badge bg-warning rounded-pill">
                            $<?php echo number_format($stats['pending_total'], 2); ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Rejected Expenses
                        <span class="badge bg-danger rounded-pill">
                            $<?php echo number_format($stats['rejected_total'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Expense History
                </h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary">All</button>
                    <button type="button" class="btn btn-sm btn-outline-success">Approved</button>
                    <button type="button" class="btn btn-sm btn-outline-warning">Pending</button>
                    <button type="button" class="btn btn-sm btn-outline-danger">Rejected</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Ref No.</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($expense = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $expense['expense_number']; ?></td>
                                <td><?php echo $expense['expense_type']; ?></td>
                                <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                <td>
                                    <small class="text-muted"><?php echo substr($expense['description'], 0, 50); ?>...</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($expense['status']) {
                                            case 'approved': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'rejected': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($expense['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($expense['receipt_path']): ?>
                                        <a href="../uploads/<?php echo $expense['receipt_path']; ?>" 
                                           target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No receipt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once '../includes/footer.php';
?>