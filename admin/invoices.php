<?php
require_once '../includes/header.php';

// Handle invoice actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    switch ($action) {
        case 'approve':
            $sql = "UPDATE expenses SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute()) {
                $success = "Invoice approved successfully!";
            }
            break;
            
        case 'reject':
            $sql = "UPDATE expenses SET status = 'rejected' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute()) {
                $success = "Invoice rejected!";
            }
            break;
            
        case 'delete':
            $sql = "DELETE FROM expenses WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute()) {
                $success = "Invoice deleted successfully!";
            }
            break;
    }
}

// Get all invoices/expenses
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "1=1";

switch ($filter) {
    case 'pending':
        $where_clause = "status = 'pending'";
        break;
    case 'approved':
        $where_clause = "status = 'approved'";
        break;
    case 'rejected':
        $where_clause = "status = 'rejected'";
        break;
}

$invoices_sql = "SELECT e.*, b.branch_name, u.full_name as recorded_by_name,
                        u2.full_name as manager_name
                 FROM expenses e
                 JOIN branches b ON e.branch_id = b.id
                 JOIN users u ON e.recorded_by = u.id
                 LEFT JOIN users u2 ON b.manager_id = u2.id
                 WHERE $where_clause
                 ORDER BY e.created_at DESC";
$invoices = $conn->query($invoices_sql);

// Statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
              SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
              SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_total
              FROM expenses";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-invoice-dollar"></i> Invoice Management
                </h5>
                <div class="btn-group">
                    <a href="?filter=all" class="btn btn-sm <?php echo $filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="?filter=pending" class="btn btn-sm <?php echo $filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                        Pending (<?php echo number_format($stats['pending_total']); ?>)
                    </a>
                    <a href="?filter=approved" class="btn btn-sm <?php echo $filter == 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">Approved</a>
                    <a href="?filter=rejected" class="btn btn-sm <?php echo $filter == 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">Rejected</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Total Invoices</h6>
                                <h2 class="card-title"><?php echo number_format($stats['total']); ?></h2>
                                <p class="card-text">All expense invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Pending Amount</h6>
                                <h2 class="card-title">$<?php echo number_format($stats['pending_total'], 2); ?></h2>
                                <p class="card-text">Awaiting approval</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Approved Amount</h6>
                                <h2 class="card-title">$<?php echo number_format($stats['approved_total'], 2); ?></h2>
                                <p class="card-text">Payment processed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Rejected Amount</h6>
                                <h2 class="card-title">$<?php echo number_format($stats['rejected_total'], 2); ?></h2>
                                <p class="card-text">Not approved</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Invoices Table -->
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Branch</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Recorded By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($invoice = $invoices->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $invoice['expense_number']; ?></strong><br>
                                    <small class="text-muted"><?php echo substr($invoice['description'], 0, 30); ?>...</small>
                                </td>
                                <td><?php echo $invoice['branch_name']; ?></td>
                                <td><?php echo $invoice['expense_type']; ?></td>
                                <td>
                                    <strong>$<?php echo number_format($invoice['amount'], 2); ?></strong><br>
                                    <?php if ($invoice['receipt_path']): ?>
                                        <a href="../uploads/<?php echo $invoice['receipt_path']; ?>" 
                                           target="_blank" class="badge bg-info text-decoration-none">
                                            View Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($invoice['expense_date'])); ?></td>
                                <td><?php echo $invoice['recorded_by_name']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($invoice['status']) {
                                            case 'approved': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'rejected': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($invoice['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($invoice['status'] == 'pending'): ?>
                                            <a href="?action=approve&id=<?php echo $invoice['id']; ?>" 
                                               class="btn btn-sm btn-success" 
                                               onclick="return confirm('Approve this invoice?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="?action=reject&id=<?php echo $invoice['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Reject this invoice?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?php echo $invoice['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this invoice permanently?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?php echo $invoice['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Invoice Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Invoice Number:</strong><br>
                                                    <?php echo $invoice['expense_number']; ?></p>
                                                    
                                                    <p><strong>Branch:</strong><br>
                                                    <?php echo $invoice['branch_name']; ?></p>
                                                    
                                                    <p><strong>Expense Type:</strong><br>
                                                    <?php echo $invoice['expense_type']; ?></p>
                                                    
                                                    <p><strong>Amount:</strong><br>
                                                    <span class="text-success">$<?php echo number_format($invoice['amount'], 2); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Date:</strong><br>
                                                    <?php echo date('F d, Y', strtotime($invoice['expense_date'])); ?></p>
                                                    
                                                    <p><strong>Recorded By:</strong><br>
                                                    <?php echo $invoice['recorded_by_name']; ?></p>
                                                    
                                                    <p><strong>Status:</strong><br>
                                                    <span class="badge bg-<?php 
                                                        switch($invoice['status']) {
                                                            case 'approved': echo 'success'; break;
                                                            case 'pending': echo 'warning'; break;
                                                            case 'rejected': echo 'danger'; break;
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($invoice['status']); ?>
                                                    </span></p>
                                                    
                                                    <p><strong>Created:</strong><br>
                                                    <?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <h6>Description:</h6>
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <?php echo nl2br(htmlspecialchars($invoice['description'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($invoice['receipt_path']): ?>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <h6>Receipt:</h6>
                                                    <a href="../uploads/<?php echo $invoice['receipt_path']; ?>" 
                                                       target="_blank" class="btn btn-primary">
                                                        <i class="fas fa-download"></i> Download Receipt
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <?php if ($invoice['status'] == 'pending'): ?>
                                                <a href="?action=approve&id=<?php echo $invoice['id']; ?>" 
                                                   class="btn btn-success"
                                                   onclick="return confirm('Approve this invoice?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="?action=reject&id=<?php echo $invoice['id']; ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Reject this invoice?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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