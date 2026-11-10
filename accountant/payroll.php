<?php
require_once '../includes/header.php';
require_once '../includes/security.php';

$branch_id = $accountant_branch_id ?? null;
$csrf_token = generateCSRFToken();
$current_period = date('Y-m');
$period = is_string($_REQUEST['pay_period'] ?? null) ? $_REQUEST['pay_period'] : $current_period;
$period_has_valid_format = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) === 1;
$period_date = $period_has_valid_format ? DateTime::createFromFormat('!Y-m', $period) : false;
$period_valid = $period_date && $period_date->format('Y-m') === $period && $period <= $current_period;
$error = '';
$success = '';

if (!$period_valid) {
    $period = $current_period;
    $error = 'Choose a valid payroll month that is not in the future.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_salary_payment'])) {
    $submitted_token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $staff_id = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);
    $payment_period = is_string($_POST['pay_period'] ?? null) ? $_POST['pay_period'] : '';
    $payment_date = is_string($_POST['payment_date'] ?? null) ? $_POST['payment_date'] : '';
    $payment_method = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
    $reference_number = is_string($_POST['reference_number'] ?? null) ? trim($_POST['reference_number']) : '';
    $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
    $allowed_methods = ['bank_transfer', 'check', 'cash', 'mobile_money', 'online'];
    $posted_period_has_valid_format = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $payment_period) === 1;
    $posted_period_date = $posted_period_has_valid_format
        ? DateTime::createFromFormat('!Y-m', $payment_period)
        : false;
    $valid_period = $posted_period_date && $posted_period_date->format('Y-m') === $payment_period
        && $payment_period <= $current_period;

    if (!validateCSRFToken($submitted_token)) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } elseif (!$staff_id || !$valid_period
        || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $payment_date) !== 1
        || !isValidDate($payment_date)
        || substr($payment_date, 0, 7) !== $payment_period
        || $payment_date > date('Y-m-d')
        || !in_array($payment_method, $allowed_methods, true)
        || strlen($reference_number) > 100 || strlen($notes) > 2000) {
        $error = 'Enter a valid staff member, payroll month, payment date, method, and reference.';
    } else {
        try {
            $conn->begin_transaction();
            $staff_sql = "SELECT id, branch_id, monthly_salary, role
                          FROM users
                          WHERE id = ? AND status = 'active' AND deleted_at IS NULL
                            AND role IN ('manager', 'accountant', 'attendant')"
                . ($branch_id !== null ? ' AND branch_id = ?' : '')
                . ' FOR UPDATE';
            $staff_stmt = $conn->prepare($staff_sql);
            if ($branch_id !== null) {
                $staff_stmt->bind_param('ii', $staff_id, $branch_id);
            } else {
                $staff_stmt->bind_param('i', $staff_id);
            }
            $staff_stmt->execute();
            $staff = $staff_stmt->get_result()->fetch_assoc();
            $staff_stmt->close();

            if (!$staff) {
                throw new RuntimeException('This staff member is not active in your branch.');
            }
            $salary_amount = round((float)$staff['monthly_salary'], 2);
            if ($salary_amount <= 0) {
                throw new RuntimeException('This staff member does not have a monthly salary configured. Ask an administrator to set it.');
            }

            $branch_for_payment = (int)$staff['branch_id'];
            $paid_by = (int)$_SESSION['user_id'];
            $insert_stmt = $conn->prepare(
                'INSERT INTO staff_payments
                    (staff_id, branch_id, pay_period, amount, payment_method, payment_date,
                     reference_number, notes, paid_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert_stmt->bind_param(
                'iisdssssi',
                $staff_id,
                $branch_for_payment,
                $payment_period,
                $salary_amount,
                $payment_method,
                $payment_date,
                $reference_number,
                $notes,
                $paid_by
            );
            $insert_stmt->execute();
            $insert_stmt->close();
            $conn->commit();

            $period = $payment_period;
            $success = 'Salary payment recorded: $' . number_format($salary_amount, 2)
                . ' for ' . htmlspecialchars($payment_period, ENT_QUOTES, 'UTF-8') . '.';
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            $conn->rollback();
            if ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1062) {
                $error = 'This staff member has already been paid for the selected month.';
            } elseif ($exception instanceof mysqli_sql_exception) {
                error_log('Staff payroll payment failed: ' . $exception->getMessage());
                $error = 'Could not record this salary payment. Please try again or contact the system administrator.';
            } else {
                $error = $exception->getMessage();
            }
        }
    }
}

$branch_condition = $branch_id !== null ? ' AND u.branch_id = ' . (int)$branch_id : '';
$staff_sql = "SELECT u.id, u.full_name, u.role, u.employee_id, u.monthly_salary,
                     sp.id AS payment_id, sp.payment_date, sp.payment_method, sp.amount AS paid_amount
              FROM users u
              LEFT JOIN staff_payments sp
                ON sp.staff_id = u.id AND sp.pay_period = ?
              WHERE u.status = 'active' AND u.deleted_at IS NULL
                AND u.role IN ('manager', 'accountant', 'attendant')
                $branch_condition
              ORDER BY u.full_name";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->bind_param('s', $period);
$staff_stmt->execute();
$staff_result = $staff_stmt->get_result();
$staff_rows = $staff_result->fetch_all(MYSQLI_ASSOC);
$staff_stmt->close();

$eligible_staff = array_values(array_filter(
    $staff_rows,
    static fn(array $staff): bool => (float)$staff['monthly_salary'] > 0 && empty($staff['payment_id'])
));
$configured_staff_count = count(array_filter(
    $staff_rows,
    static fn(array $staff): bool => (float)$staff['monthly_salary'] > 0
));
$paid_count = count(array_filter(
    $staff_rows,
    static fn(array $staff): bool => !empty($staff['payment_id'])
));
$payroll_total = 0.0;
foreach ($staff_rows as $staff_row) {
    if (!empty($staff_row['payment_id'])) {
        $payroll_total += (float)$staff_row['paid_amount'];
    }
}

$payments_sql = "SELECT sp.*, u.full_name, u.role, u.employee_id, payer.full_name AS paid_by_name
                 FROM staff_payments sp
                 JOIN users u ON u.id = sp.staff_id
                 JOIN users payer ON payer.id = sp.paid_by
                 WHERE sp.pay_period = ?"
    . ($branch_id !== null ? ' AND sp.branch_id = ' . (int)$branch_id : '')
    . ' ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 50';
$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param('s', $period);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments = $payments_result->fetch_all(MYSQLI_ASSOC);
$payments_stmt->close();
?>
<style>
    .payroll-period-form {
        min-width: 0;
    }

    @media (max-width: 575.98px) {
        .payroll-period-form {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto;
            width: 100%;
        }

        .payroll-period-form label {
            grid-column: 1 / -1;
        }

        .payroll-period-form input {
            min-width: 0;
            width: 100%;
        }
    }
</style>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>Staff Payroll</h5>
                <form method="GET" class="payroll-period-form d-flex align-items-center gap-2">
                    <label for="pay_period" class="form-label mb-0">Payroll month</label>
                    <input type="month" class="form-control" id="pay_period" name="pay_period"
                           max="<?php echo htmlspecialchars($current_period, ENT_QUOTES, 'UTF-8'); ?>"
                           value="<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-outline-primary" type="submit">View</button>
                </form>
            </div>
            <div class="card-body">
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success" role="status"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div class="alert alert-info">
                    Payroll uses monthly salaries configured by an administrator. Payments are limited to
                    active non-admin staff in the assigned branch and each employee can be paid once per month.
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-4">
                        <div class="card bg-primary text-white h-100 mb-0">
                            <div class="card-body">
                                <div class="small">Configured Staff</div>
                                <div class="h3 mb-0"><?php echo number_format($configured_staff_count); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="card bg-success text-white h-100 mb-0">
                            <div class="card-body">
                                <div class="small">Paid This Month</div>
                                <div class="h3 mb-0"><?php echo number_format($paid_count); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card bg-info text-white h-100 mb-0">
                            <div class="card-body">
                                <div class="small">Payroll Recorded</div>
                                <div class="h3 mb-0">$<?php echo number_format($payroll_total, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header"><h6 class="mb-0">Record Salary Payment</h6></div>
                            <div class="card-body">
                                <?php if (!$eligible_staff): ?>
                                    <p class="text-muted mb-0">
                                        No configured, unpaid staff salaries are available for this month.
                                        Ask an administrator to configure salaries in User Management.
                                    </p>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="pay_period" value="<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="mb-3">
                                            <label for="staff_id" class="form-label">Staff member *</label>
                                            <select class="form-select" name="staff_id" id="staff_id" required>
                                                <option value="">Select staff member</option>
                                                <?php foreach ($eligible_staff as $staff): ?>
                                                    <option value="<?php echo (int)$staff['id']; ?>">
                                                        <?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        (<?php echo htmlspecialchars(ucfirst($staff['role']), ENT_QUOTES, 'UTF-8'); ?>)
                                                        — $<?php echo number_format((float)$staff['monthly_salary'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">Payment date *</label>
                                            <input type="date" class="form-control" name="payment_date" id="payment_date"
                                                   value="<?php echo date('Y-m-d'); ?>"
                                                   max="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment method *</label>
                                            <select class="form-select" name="payment_method" id="payment_method" required>
                                                <option value="">Select method</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="check">Check</option>
                                                <option value="cash">Cash</option>
                                                <option value="mobile_money">Mobile Money</option>
                                                <option value="online">Online Payment</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="reference_number" class="form-label">Reference number</label>
                                            <input type="text" class="form-control" name="reference_number"
                                                   id="reference_number" maxlength="100">
                                        </div>
                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" name="notes" id="notes" rows="2" maxlength="2000"></textarea>
                                        </div>
                                        <button class="btn btn-success w-100" type="submit" name="record_salary_payment">
                                            <i class="fas fa-check-circle me-1"></i> Record Salary Payment
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header"><h6 class="mb-0">Branch Staff — <?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?></h6></div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr><th>Staff</th><th>Role</th><th>Monthly Salary</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$staff_rows): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No active non-admin staff found in this branch.</td></tr>
                                        <?php else: foreach ($staff_rows as $staff): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if (!empty($staff['employee_id'])): ?>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($staff['employee_id'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(ucfirst($staff['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (float)$staff['monthly_salary'] > 0
                                                        ? '$' . number_format((float)$staff['monthly_salary'], 2)
                                                        : '<span class="text-warning">Not configured</span>'; ?></td>
                                                <td>
                                                    <?php if (!empty($staff['payment_id'])): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($staff['payment_date'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <?php elseif ((float)$staff['monthly_salary'] > 0): ?>
                                                        <span class="badge bg-warning text-dark">Unpaid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Needs salary setup</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Salary Payments — <?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?></h6></div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr><th>Staff</th><th>Amount</th><th>Date</th><th>Method</th><th>Reference</th><th>Recorded By</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$payments): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No salary payments recorded for this month.</td></tr>
                                        <?php else: foreach ($payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($payment['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars(ucfirst($payment['role']), ENT_QUOTES, 'UTF-8'); ?></small>
                                                </td>
                                                <td>$<?php echo number_format((float)$payment['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_method'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference_number'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['paid_by_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
