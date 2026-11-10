<?php
require_once '../includes/header.php';
require_once '../includes/security.php';

$csrf_token = generateCSRFToken();
$branch_id = $accountant_branch_id ?? null;
$branch_scope_e = $branch_id !== null ? ' AND e.branch_id = ' . (int)$branch_id : '';
$branch_scope_paid = $branch_id !== null ? ' AND paid_expense.branch_id = ' . (int)$branch_id : '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $expense_id = filter_input(INPUT_POST, 'expense_id', FILTER_VALIDATE_INT);
    $payment_method = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
    $payment_date = is_string($_POST['payment_date'] ?? null) ? $_POST['payment_date'] : '';
    $reference_number = is_string($_POST['reference_number'] ?? null) ? trim($_POST['reference_number']) : '';
    $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
    $submitted_csrf_token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    $allowed_methods = ['bank_transfer', 'check', 'cash', 'mobile_money', 'online'];
    if (!validateCSRFToken($submitted_csrf_token)) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } elseif (!$expense_id || !in_array($payment_method, $allowed_methods, true) || !isValidDate($payment_date)) {
        $error = 'Choose a valid approved invoice, payment method, and date.';
    } elseif (strlen($reference_number) > 100 || strlen($notes) > 2000) {
        $error = 'Reference numbers must be at most 100 characters and notes at most 2,000 characters.';
    } else {
        try {
            $conn->begin_transaction();
            $expense_stmt = $conn->prepare(
                "SELECT e.amount FROM expenses e
                 WHERE e.id = ? AND e.status = 'approved'$branch_scope_e
                 FOR UPDATE"
            );
            $expense_stmt->bind_param('i', $expense_id);
            $expense_stmt->execute();
            $expense = $expense_stmt->get_result()->fetch_assoc();
            $expense_stmt->close();

            if (!$expense) {
                throw new RuntimeException('This invoice is not approved or no longer exists.');
            }
            if (!is_numeric($expense['amount']) || (float)$expense['amount'] <= 0) {
                throw new RuntimeException('This invoice has an invalid payment amount.');
            }

            $payment_stmt = $conn->prepare(
                "INSERT INTO expense_payments
                    (expense_id, amount, payment_method, payment_date, reference_number, notes, paid_by)
                 SELECT ?, ?, ?, ?, ?, ?, ?
                 WHERE NOT EXISTS (
                    SELECT 1 FROM expense_payments WHERE expense_id = ?
                 )"
            );
            $amount = (float)$expense['amount'];
            $user_id = (int)$_SESSION['user_id'];
            $payment_stmt->bind_param(
                'idssssii',
                $expense_id,
                $amount,
                $payment_method,
                $payment_date,
                $reference_number,
                $notes,
                $user_id,
                $expense_id
            );
            $payment_stmt->execute();
            $payment_recorded = $payment_stmt->affected_rows === 1;
            $payment_stmt->close();

            if (!$payment_recorded) {
                throw new RuntimeException('This invoice has already been paid.');
            }

            $conn->commit();
            $success = 'Payment recorded successfully for $' . number_format($amount, 2) . '.';
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            $conn->rollback();
            if ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1146) {
                error_log('Accountant payments table is missing; apply database/migrations/2026-11-06-create-expense-payments.sql');
                $error = 'Payment recording is not set up yet. Please contact the system administrator.';
            } elseif ($exception instanceof mysqli_sql_exception) {
                error_log('Accountant payment recording failed: ' . $exception->getMessage());
                $error = $exception->getCode() === 1062
                    ? 'This invoice has already been paid.'
                    : 'Could not record this payment. Please try again or contact the system administrator.';
            } else {
                $error = $exception->getMessage();
            }
        }
    }
}

// Get approved expenses ready for payment
$approved_sql = "SELECT e.*, b.branch_name 
                 FROM expenses e
                 JOIN branches b ON e.branch_id = b.id
                 WHERE e.status = 'approved'
                 $branch_scope_e
                 AND NOT EXISTS (
                    SELECT 1 FROM expense_payments ep WHERE ep.expense_id = e.id
                 )
                 ORDER BY e.expense_date ASC";
$approved_expenses = null;
try {
    $approved_expenses = $conn->query($approved_sql);
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() !== 1146) {
        throw $exception;
    }
    error_log('Accountant payments page cannot load: expense_payments table is missing');
    $error = 'Payment recording is not set up yet. Please contact the system administrator.';
}

// Get recently recorded payments
$payments_sql = "SELECT ep.*, e.expense_number
                 FROM expense_payments ep
                 JOIN expenses e ON e.id = ep.expense_id
                 WHERE 1=1" . ($branch_id !== null ? " AND e.branch_id = " . (int)$branch_id : '') . "
                 ORDER BY ep.payment_date DESC, ep.id DESC
                 LIMIT 20";
$payments = null;
$stats = ['total_pending' => 0, 'pending_amount' => 0, 'total_paid' => 0, 'paid_amount' => 0];
try {
    $payments = $conn->query($payments_sql);
    $stats_sql = "SELECT
                    (SELECT COUNT(*) FROM expenses e
                     WHERE e.status = 'approved'$branch_scope_e
                     AND NOT EXISTS (SELECT 1 FROM expense_payments ep WHERE ep.expense_id = e.id)) AS total_pending,
                    (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e
                     WHERE e.status = 'approved'$branch_scope_e
                     AND NOT EXISTS (SELECT 1 FROM expense_payments ep WHERE ep.expense_id = e.id)) AS pending_amount,
                    (SELECT COUNT(*) FROM expense_payments ep
                     JOIN expenses paid_expense ON paid_expense.id = ep.expense_id
                     WHERE 1=1$branch_scope_paid) AS total_paid,
                    (SELECT COALESCE(SUM(ep.amount), 0) FROM expense_payments ep
                     JOIN expenses paid_expense ON paid_expense.id = ep.expense_id
                     WHERE 1=1$branch_scope_paid) AS paid_amount";
    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result->fetch_assoc();
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() !== 1146) {
        throw $exception;
    }
    error_log('Accountant payments statistics unavailable: expense_payments table is missing');
    $error = 'Payment recording is not set up yet. Please contact the system administrator.';
}

?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-check-alt"></i> Process Payment
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="expense_id" class="form-label">Select Invoice *</label>
                        <select class="form-control" id="expense_id" name="expense_id" required 
                                onchange="loadExpenseDetails()">
                            <option value="">-- Select Invoice --</option>
                            <?php 
                            if ($approved_expenses) $approved_expenses->data_seek(0);
                            if (!$approved_expenses || $approved_expenses->num_rows === 0):
                            ?>
                            <option value="" disabled>No approved invoices are awaiting payment.</option>
                            <?php else:
                                while($expense = $approved_expenses->fetch_assoc()):
                            ?>
                                <option value="<?php echo $expense['id']; ?>" 
                                        data-amount="<?php echo $expense['amount']; ?>"
                                        data-description="<?php echo htmlspecialchars($expense['description']); ?>">
                                    <?php echo $expense['expense_number']; ?> - 
                                    $<?php echo number_format($expense['amount'], 2); ?> - 
                                    <?php echo $expense['branch_name']; ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    
                    <!-- Expense Details -->
                    <div id="expenseDetails" style="display: none;">
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6>Selected Invoice:</h6>
                                <p id="selectedAmount" class="text-success fs-4 mb-1"></p>
                                <p id="selectedDescription" class="text-muted mb-0 small"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="">-- Select Method --</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" 
                               placeholder="e.g., Check #, Transaction ID">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Payment Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Additional payment details..."></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="process_payment" class="btn btn-success btn-lg">
                            <i class="fas fa-check-circle"></i> Process Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Payment Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Payment Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Pending Payments
                        <div class="text-end">
                            <strong><?php echo number_format($stats['total_pending']); ?></strong><br>
                            <span class="text-warning">$<?php echo number_format($stats['pending_amount'], 2); ?></span>
                        </div>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Processed Payments
                        <div class="text-end">
                            <strong><?php echo number_format($stats['total_paid']); ?></strong><br>
                            <span class="text-success">$<?php echo number_format($stats['paid_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Pending Payments -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock"></i> Pending Payments
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Branch</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($approved_expenses) $approved_expenses->data_seek(0);
                            while($approved_expenses && $expense = $approved_expenses->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $expense['expense_number']; ?></td>
                                <td><?php echo $expense['branch_name']; ?></td>
                                <td><?php echo $expense['expense_type']; ?></td>
                                <td class="text-success">
                                    <strong>$<?php echo number_format($expense['amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $due_date = date('Y-m-d', strtotime($expense['expense_date'] . ' + 30 days'));
                                    $due_class = strtotime($due_date) < strtotime('today') ? 'text-danger' : 'text-warning';
                                    ?>
                                    <span class="<?php echo $due_class; ?>">
                                        <?php echo date('M d, Y', strtotime($due_date)); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo substr($expense['description'], 0, 50); ?>...
                                    </small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="selectInvoice('<?php echo $expense['id']; ?>')">
                                        <i class="fas fa-money-check"></i> Pay Now
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Recent Payments
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$payments || $payments->num_rows === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments have been recorded yet.</td></tr>
                            <?php else: while($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $payment['expense_number']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td class="text-success">
                                    $<?php echo number_format($payment['amount'], 2); ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_method'])), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">Paid</span>
                                </td>
                                <td>
                                    <span class="text-muted"><?php echo htmlspecialchars($payment['reference_number'] ?: 'No reference', ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadExpenseDetails() {
    var select = document.getElementById('expense_id');
    var detailsDiv = document.getElementById('expenseDetails');
    var amountSpan = document.getElementById('selectedAmount');
    var descriptionSpan = document.getElementById('selectedDescription');
    
    if (select.value) {
        var selectedOption = select.options[select.selectedIndex];
        var amount = selectedOption.getAttribute('data-amount');
        var description = selectedOption.getAttribute('data-description');
        
        amountSpan.textContent = '$' + parseFloat(amount).toFixed(2);
        descriptionSpan.textContent = description;
        detailsDiv.style.display = 'block';
    } else {
        detailsDiv.style.display = 'none';
    }
}

function selectInvoice(expenseId) {
    document.getElementById('expense_id').value = expenseId;
    loadExpenseDetails();
    // Scroll to form
    document.getElementById('expense_id').scrollIntoView({ behavior: 'smooth' });
}

// Initialize form validation
$(document).ready(function() {
    $('#expense_id').on('change', loadExpenseDetails);
});
</script>
<?php
require_once '../includes/footer.php';
?>