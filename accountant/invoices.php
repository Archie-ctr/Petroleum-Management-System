<?php
require_once '../includes/header.php';
require_once '../includes/security.php';

$csrf_token = generateCSRFToken();
$branch_id = $accountant_branch_id ?? null;
$branch_sql_filter = $branch_id !== null ? ' AND e.branch_id = ' . (int)$branch_id : '';

// Handle invoice actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $invoice_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    $submitted_csrf_token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!validateCSRFToken($submitted_csrf_token)) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } elseif (!$invoice_id) {
        $error = 'The selected invoice is invalid.';
    } else {
    switch ($action) {
        case 'approve':
            $sql = "UPDATE expenses SET status = 'approved' WHERE id = ? AND status = 'pending'"
                . ($branch_id !== null ? ' AND branch_id = ?' : '');
            $stmt = $conn->prepare($sql);
            if ($branch_id !== null) {
                $stmt->bind_param("ii", $invoice_id, $branch_id);
            } else {
                $stmt->bind_param("i", $invoice_id);
            }
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $success = "Invoice approved successfully!";
            } else {
                $error = "This invoice is no longer pending approval.";
            }
            break;
            
        case 'reject':
            $sql = "UPDATE expenses SET status = 'rejected' WHERE id = ? AND status = 'pending'"
                . ($branch_id !== null ? ' AND branch_id = ?' : '');
            $stmt = $conn->prepare($sql);
            if ($branch_id !== null) {
                $stmt->bind_param("ii", $invoice_id, $branch_id);
            } else {
                $stmt->bind_param("i", $invoice_id);
            }
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $success = "Invoice rejected!";
            } else {
                $error = "This invoice is no longer pending approval.";
            }
            break;
            
        case 'delete':
            $sql = "DELETE FROM expenses
                    WHERE id = ?
                    " . ($branch_id !== null ? "AND branch_id = " . (int)$branch_id . ' ' : '') . "
                    AND NOT EXISTS (
                        SELECT 1 FROM expense_payments ep WHERE ep.expense_id = expenses.id
                    )";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $success = "Invoice deleted successfully!";
            } else {
                $error = "This invoice could not be deleted. Paid invoices are retained for audit history.";
            }
            break;
        default:
            $error = "Unknown invoice action.";
            break;
    }
    }
}

// Get all invoices/expenses
$filter = is_string($_GET['filter'] ?? null) ? $_GET['filter'] : 'all';
$where_clause = "1=1";

switch ($filter) {
    case 'pending':
        $where_clause = "e.status = 'pending'";
        break;
    case 'approved':
        $where_clause = "e.status = 'approved' AND ep.id IS NULL";
        break;
    case 'paid':
        $where_clause = "ep.id IS NOT NULL";
        break;
    case 'rejected':
        $where_clause = "e.status = 'rejected'";
        break;
    default:
        $filter = 'all';
}

$invoices_sql = "SELECT e.*, b.branch_name, u.full_name as recorded_by_name,
                        u2.full_name as manager_name, ep.id as payment_id
                 FROM expenses e
                 JOIN branches b ON e.branch_id = b.id
                 LEFT JOIN users u ON e.recorded_by = u.id
                 LEFT JOIN users u2 ON b.manager_id = u2.id
                 LEFT JOIN expense_payments ep ON ep.expense_id = e.id
                 WHERE $where_clause
                 $branch_sql_filter
                 ORDER BY e.created_at DESC";
$invoices = $conn->query($invoices_sql);

// Statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
              SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
              SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_total
              FROM expenses"
              . ($branch_id !== null ? " WHERE branch_id = " . (int)$branch_id : '');
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$render_action_form = static function (string $action, int $invoice_id, string $label, string $button_class, string $icon) use ($csrf_token): void {
    $confirm = [
        'approve' => 'Approve this invoice?',
        'reject' => 'Reject this invoice?',
        'delete' => 'Delete this invoice permanently?',
    ][$action] ?? '';
    ?>
    <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars($confirm, ENT_QUOTES, 'UTF-8'); ?>')">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo $invoice_id; ?>">
        <button type="submit" class="btn <?php echo htmlspecialchars($button_class, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
        </button>
    </form>
    <?php
};
?>
<style>
    .invoice-management-header {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .invoice-filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .invoice-filter-group .btn {
        border-radius: var(--bs-btn-border-radius) !important;
    }

    @media (max-width: 575.98px) {
        .invoice-management-header {
            align-items: stretch !important;
        }

        .invoice-filter-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        .invoice-filter-group .btn {
            white-space: normal;
        }
    }
</style>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header invoice-management-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-invoice-dollar"></i> Invoice Management
                </h5>
                <div class="invoice-filter-group">
                    <a href="?filter=all" class="btn btn-sm <?php echo $filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="?filter=pending" class="btn btn-sm <?php echo $filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                        Pending (<?php echo number_format($stats['pending_total']); ?>)
                    </a>
                    <a href="?filter=approved" class="btn btn-sm <?php echo $filter == 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">Approved</a>
                    <a href="?filter=paid" class="btn btn-sm <?php echo $filter == 'paid' ? 'btn-info' : 'btn-outline-info'; ?>">Paid</a>
                    <a href="?filter=rejected" class="btn btn-sm <?php echo $filter == 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">Rejected</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
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
                                <p class="card-text">Approved invoices, including paid</p>
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
                            <?php if ($invoices->num_rows === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No invoices match this filter.</td></tr>
                            <?php else: while($invoice = $invoices->fetch_assoc()): ?>
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
                                    <?php
                                    $invoice_status = $invoice['payment_id'] ? 'paid' : $invoice['status'];
                                    $status_color = ['approved' => 'success', 'paid' => 'info', 'pending' => 'warning', 'rejected' => 'danger'][$invoice_status] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars(ucfirst($invoice_status), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($invoice['status'] == 'pending'): ?>
                                            <?php $render_action_form('approve', (int)$invoice['id'], 'Approve invoice', 'btn-sm btn-success', 'fa-check'); ?>
                                            <?php $render_action_form('reject', (int)$invoice['id'], 'Reject invoice', 'btn-sm btn-danger', 'fa-times'); ?>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?php echo $invoice['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$invoice['payment_id']): ?>
                                            <?php $render_action_form('delete', (int)$invoice['id'], 'Delete invoice', 'btn-sm btn-danger', 'fa-trash'); ?>
                                        <?php endif; ?>
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
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($invoice_status), ENT_QUOTES, 'UTF-8'); ?>
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
                                                <?php $render_action_form('approve', (int)$invoice['id'], 'Approve invoice', 'btn-success', 'fa-check'); ?>
                                                <?php $render_action_form('reject', (int)$invoice['id'], 'Reject invoice', 'btn-danger', 'fa-times'); ?>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; endif; ?>
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