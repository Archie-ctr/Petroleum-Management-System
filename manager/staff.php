<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'manager') {
    header('Location: ../public/login.php?error=unauthorized');
    exit;
}

require_once '../config/db.php';
require_once '../includes/employee_id.php';

$branchId = filter_var($_SESSION['branch_id'] ?? null, FILTER_VALIDATE_INT);
$managerId = (int)$_SESSION['user_id'];
if (!$branchId || $branchId < 1) {
    http_response_code(403);
    exit('Your manager account is not assigned to a branch.');
}

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $_SESSION['staff_flash'] = ['type' => 'danger', 'message' => 'Your request could not be verified. Please try again.'];
        header('Location: staff.php');
        exit;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $role = (string)($_POST['role'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 100
        || $fullName === '' || strlen($fullName) > 100
        || strlen($phone) > 30
        || !in_array($role, ['accountant', 'attendant'], true)
        || strlen($password) < 8 || strlen($password) > 255) {
        $_SESSION['staff_flash'] = [
            'type' => 'danger',
            'message' => 'Enter a valid username, name, email, role, and password (at least 8 characters).'
        ];
        header('Location: staff.php');
        exit;
    }

    $branchStmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $branchStmt->bind_param('i', $branchId);
    $branchStmt->execute();
    $branchExists = $branchStmt->get_result()->fetch_assoc();
    $branchStmt->close();
    if (!$branchExists) {
        $_SESSION['staff_flash'] = ['type' => 'danger', 'message' => 'Your branch is inactive; staff accounts cannot be added.'];
        header('Location: staff.php');
        exit;
    }

    $transactionStarted = false;
    try {
        $duplicateStmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $duplicateStmt->bind_param('ss', $username, $email);
        $duplicateStmt->execute();
        $duplicate = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();
        if ($duplicate) {
            throw new RuntimeException('That username or email address is already in use.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $phoneValue = $phone !== '' ? $phone : null;
        $pinCode = $role === 'attendant' ? (string)random_int(1000, 9999) : null;
        $status = 'active';

        $conn->begin_transaction();
        $transactionStarted = true;
        $insertStmt = $conn->prepare(
            'INSERT INTO users (username, password, email, full_name, role, branch_id, phone, pin_code, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStmt->bind_param(
            'sssssisssi',
            $username,
            $passwordHash,
            $email,
            $fullName,
            $role,
            $branchId,
            $phoneValue,
            $pinCode,
            $status,
            $managerId
        );
        $insertStmt->execute();
        $staffId = (int)$conn->insert_id;
        $employeeId = assignEmployeeIdIfMissing($conn, $staffId);
        $conn->commit();
        $transactionStarted = false;

        $message = ucfirst($role) . ' account created. Employee ID: ' . ($employeeId ?? 'not assigned') . '.';
        if ($pinCode !== null) {
            $message .= ' Initial attendant PIN: ' . $pinCode . '.';
        }
        $_SESSION['staff_flash'] = ['type' => 'success', 'message' => $message];
    } catch (Throwable $error) {
        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log('Manager staff creation rollback failed: ' . $rollbackError->getMessage());
            }
        }
        if ($error instanceof RuntimeException) {
            $_SESSION['staff_flash'] = ['type' => 'danger', 'message' => $error->getMessage()];
        } else {
            error_log('Manager staff creation failed: ' . $error->getMessage());
            $_SESSION['staff_flash'] = ['type' => 'danger', 'message' => 'Could not create the staff account. Check the details and try again.'];
        }
    }

    header('Location: staff.php');
    exit;
}

$staffStmt = $conn->prepare(
    "SELECT id, username, email, full_name, role, phone, employee_id, status, created_at
     FROM users
     WHERE branch_id = ? AND role IN ('accountant', 'attendant') AND deleted_at IS NULL
     ORDER BY role, full_name"
);
$staffStmt->bind_param('i', $branchId);
$staffStmt->execute();
$staffMembers = $staffStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$staffStmt->close();

$flash = $_SESSION['staff_flash'] ?? null;
unset($_SESSION['staff_flash']);

require_once '../includes/manager_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Branch Staff</h1>
            <p class="text-muted mb-0">Create and view accountant and attendant accounts for <?php echo htmlspecialchars($branch['branch_name'] ?? 'your branch', ENT_QUOTES, 'UTF-8'); ?>.</p>
        </div>
        <span class="badge text-bg-success"><?php echo count($staffMembers); ?> staff accounts</span>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>" role="alert">
            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0"><i class="fas fa-user-plus me-2 text-success"></i>Create Staff Account</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="staff.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full name</label>
                            <input class="form-control" id="full_name" name="full_name" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input class="form-control" id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required>
                            <div class="form-text">Use letters, numbers, periods, underscores, or hyphens.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="100" autocomplete="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" maxlength="30">
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select role</option>
                                <option value="accountant">Accountant</option>
                                <option value="attendant">Attendant</option>
                            </select>
                            <div class="form-text">Managers can create staff accounts, but cannot create administrators or other managers.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Temporary password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required>
                            <div class="form-text">At least 8 characters. Give the password to the employee securely.</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-user-plus me-2"></i>Create Account for This Branch
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Staff in This Branch</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Employee ID</th>
                                <th>Role</th>
                                <th>Username</th>
                                <th>Email / Phone</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$staffMembers): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No accountant or attendant accounts are assigned to this branch yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($staffMembers as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($member['employee_id'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($member['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if (!empty($member['phone'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($member['phone'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge text-bg-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($member['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
