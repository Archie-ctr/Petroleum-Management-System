<?php
session_start();
require_once '../config/db.php';
require_once '../includes/employee_id.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

function redirectToAttendants(): void
{
    header('Location: attendance');
    exit;
}

function validateAttendantCsrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $_SESSION['error'] = 'Your request could not be verified. Please try again.';
        redirectToAttendants();
    }
}

function saveAttendantPhoto(string $field): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('The profile photo must be smaller than 2 MB.');
    }

    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    if (!isset($mimeToExtension[$mime])) {
        throw new RuntimeException('Choose a JPG, PNG, or GIF profile photo.');
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/profile_photos/';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The profile photo directory could not be created.');
    }

    $filename = 'attendant_' . bin2hex(random_bytes(12)) . '.' . $mimeToExtension[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDirectory . $filename)) {
        throw new RuntimeException('The profile photo could not be saved.');
    }

    return $filename;
}

function attendantUsername(mysqli $conn, string $employeeId, string $email): string
{
    $base = strtolower((string)preg_replace('/[^a-z0-9._-]/i', '', $employeeId));
    if ($base === '') {
        $base = strtolower((string)strstr($email, '@', true));
    }
    $base = substr($base, 0, 40) ?: 'attendant';
    $username = $base;
    $suffix = 1;
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    do {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        if ($exists) {
            $username = substr($base, 0, 44) . $suffix++;
        }
    } while ($exists);
    $stmt->close();
    return $username;
}

function validateAttendantInput(mysqli $conn, array $input, ?int $excludeId = null): array
{
    $fullName = trim((string)($input['full_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $employeeId = trim((string)($input['employee_id'] ?? ''));
    if ($excludeId !== null) {
        $phone = $phone !== '' ? $phone : null;
        $employeeId = $employeeId !== '' ? $employeeId : null;
    }
    $branchId = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT);
    $shiftTime = trim((string)($input['shift_time'] ?? ''));
    $hourlyRate = filter_var($input['hourly_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $status = (string)($input['status'] ?? 'active');
    $allowedShifts = ['Morning (6AM-2PM)', 'Afternoon (2PM-10PM)', 'Night (10PM-6AM)', 'Flexible'];

    if ($fullName === '' || strlen($fullName) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 100
        || ($excludeId === null && $phone === '')
        || ($phone !== null && strlen($phone) > 30)
        || ($employeeId !== null && strlen($employeeId) > 50)
        || !$branchId || $hourlyRate === false || $hourlyRate < 0
        || !in_array($shiftTime, $allowedShifts, true)
        || !in_array($status, ['active', 'inactive', 'suspended'], true)) {
        throw new RuntimeException('Please complete all required attendant fields with valid values.');
    }

    $branchStmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $branchStmt->bind_param('i', $branchId);
    $branchStmt->execute();
    if (!$branchStmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('Select an active branch.');
    }
    $branchStmt->close();

    $duplicateSql = 'SELECT id FROM users WHERE (email = ?';
    if ($employeeId !== null && $employeeId !== '') {
        $duplicateSql .= ' OR employee_id = ?';
    }
    $duplicateSql .= ')';
    if ($excludeId !== null) {
        $duplicateSql .= ' AND id != ?';
    }
    $duplicateStmt = $conn->prepare($duplicateSql);
    if ($employeeId !== null && $employeeId !== '' && $excludeId !== null) {
        $duplicateStmt->bind_param('ssi', $email, $employeeId, $excludeId);
    } elseif ($employeeId !== null && $employeeId !== '') {
        $duplicateStmt->bind_param('ss', $email, $employeeId);
    } elseif ($excludeId !== null) {
        $duplicateStmt->bind_param('si', $email, $excludeId);
    } else {
        $duplicateStmt->bind_param('s', $email);
    }
    $duplicateStmt->execute();
    if ($duplicateStmt->get_result()->num_rows > 0) {
        throw new RuntimeException('An attendant already uses this email address or employee ID.');
    }
    $duplicateStmt->close();

    return [$fullName, $email, $phone, $employeeId, (int)$branchId, (float)$hourlyRate, $shiftTime, $status];
}

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateAttendantCsrf();
    if ($action === 'add') {
        handleAddAttendant();
    } elseif ($action === 'edit') {
        handleEditAttendant();
    } elseif (in_array($action, ['delete', 'activate', 'deactivate'], true)) {
        handleAttendantStatusAction($action);
    } else {
        $_SESSION['error'] = 'Unknown attendant action.';
        redirectToAttendants();
    }
}

require_once '../includes/header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filter = in_array($_GET['filter'] ?? '', ['active', 'inactive', 'suspended'], true)
    ? (string)$_GET['filter']
    : '';
$search = trim((string)($_GET['search'] ?? ''));

$whereClause = "WHERE u.role = 'attendant' AND u.deleted_at IS NULL";
$params = [];
$types = "";

if ($filter == 'active') {
    $whereClause .= " AND u.status = 'active'";
} elseif ($filter == 'inactive') {
    $whereClause .= " AND u.status = 'inactive'";
} elseif ($filter == 'suspended') {
    $whereClause .= " AND u.status = 'suspended'";
}

if (!empty($search)) {
    $whereClause .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.employee_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_fill(0, 4, $searchTerm);
    $types = "ssss";
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalAttendants = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Calculate total pages
$totalPages = (int)ceil($totalAttendants / $limit);

// Get attendants with pagination
$attendantsSql = "SELECT u.id, u.full_name, u.email, u.phone, u.employee_id, u.branch_id,
                         u.hourly_rate, u.shift_time, u.pin_code, u.status, u.profile_photo,
                         u.created_at, u.updated_at, u.created_by, b.branch_name
                  FROM users u 
                  LEFT JOIN branches b ON u.branch_id = b.id 
                  $whereClause 
                  ORDER BY u.created_at DESC 
                  LIMIT ? OFFSET ?";
$attendantsStmt = $conn->prepare($attendantsSql);

// Add pagination parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $attendantsStmt->bind_param($types, ...$params);
}
$attendantsStmt->execute();
$attendantsResult = $attendantsStmt->get_result();

// Get branches for dropdown
$branchesSql = "SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
$branchesResult = $conn->query($branchesSql);

// Get statistics
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'active' AND deleted_at IS NULL) as active_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'inactive' AND deleted_at IS NULL) as inactive_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND status = 'suspended' AND deleted_at IS NULL) as suspended_attendants,
    (SELECT COUNT(*) FROM users WHERE role = 'attendant' AND DATE(created_at) = CURDATE() AND deleted_at IS NULL) as new_today";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

function handleAddAttendant(): void
{
    global $conn;
    $profilePhoto = null;
    $transactionStarted = false;

    try {
        [$fullName, $email, $phone, $employeeId, $branchId, $hourlyRate, $shiftTime] =
            validateAttendantInput($conn, $_POST);
        $profilePhoto = saveAttendantPhoto('profile_photo') ?? 'default-avatar.png';
        $username = attendantUsername($conn, $employeeId, $email);
        $pinCode = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $password = password_hash('attendant123', PASSWORD_DEFAULT);
        $status = 'active';
        $createdBy = (int)$_SESSION['user_id'];

        $conn->begin_transaction();
        $transactionStarted = true;
        $employeeIdValue = $employeeId !== '' ? $employeeId : null;
        $stmt = $conn->prepare(
            'INSERT INTO users (username, password, email, full_name, role, branch_id, phone, employee_id,
                                hourly_rate, shift_time, pin_code, status, created_by, profile_photo)
             VALUES (?, ?, ?, ?, \'attendant\', ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssissdsssis',
            $username,
            $password,
            $email,
            $fullName,
            $branchId,
            $phone,
            $employeeIdValue,
            $hourlyRate,
            $shiftTime,
            $pinCode,
            $status,
            $createdBy,
            $profilePhoto
        );
        $stmt->execute();
        $newUserId = (int)$conn->insert_id;
        $assignedEmployeeId = assignEmployeeIdIfMissing($conn, $newUserId);
        $conn->commit();
        $transactionStarted = false;
        $_SESSION['success'] = 'Attendant added successfully. Employee ID: '
            . ($assignedEmployeeId ?? 'not assigned') . '. Initial PIN: ' . $pinCode;
    } catch (Throwable $error) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        if ($profilePhoto !== null && $profilePhoto !== 'default-avatar.png'
            && is_file(dirname(__DIR__) . '/uploads/profile_photos/' . $profilePhoto)) {
            unlink(dirname(__DIR__) . '/uploads/profile_photos/' . $profilePhoto);
        }
        $_SESSION['error'] = $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Could not add attendant. Please check the details and try again.';
    }

    redirectToAttendants();
}

function handleEditAttendant(): void
{
    global $conn;

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        $_SESSION['error'] = 'Invalid attendant selected.';
        redirectToAttendants();
    }

    $newPhoto = null;
    try {
        [$fullName, $email, $phone, $employeeId, $branchId, $hourlyRate, $shiftTime, $status] =
            validateAttendantInput($conn, $_POST, (int)$id);
        $newPhoto = saveAttendantPhoto('profile_photo');

        $existingStmt = $conn->prepare(
            "SELECT profile_photo FROM users WHERE id = ? AND role = 'attendant' AND deleted_at IS NULL"
        );
        $existingStmt->bind_param('i', $id);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        if (!$existing) {
            throw new RuntimeException('Attendant not found.');
        }

        $sql = 'UPDATE users
                SET email = ?, full_name = ?, phone = ?, employee_id = ?, branch_id = ?,
                    hourly_rate = ?, shift_time = ?, status = ?, updated_at = NOW()';
        $sql .= $newPhoto !== null ? ', profile_photo = ?' : '';
        $sql .= " WHERE id = ? AND role = 'attendant' AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        if ($newPhoto !== null) {
            $stmt->bind_param(
                'ssssidsssi',
                $email,
                $fullName,
                $phone,
                $employeeId,
                $branchId,
                $hourlyRate,
                $shiftTime,
                $status,
                $newPhoto,
                $id
            );
        } else {
            $stmt->bind_param(
                'ssssidssi',
                $email,
                $fullName,
                $phone,
                $employeeId,
                $branchId,
                $hourlyRate,
                $shiftTime,
                $status,
                $id
            );
        }
        $stmt->execute();
        assignEmployeeIdIfMissing($conn, (int)$id);

        if ($newPhoto !== null && !empty($existing['profile_photo']) && $existing['profile_photo'] !== 'default-avatar.png') {
            $oldPhotoPath = dirname(__DIR__) . '/uploads/profile_photos/' . basename($existing['profile_photo']);
            if (is_file($oldPhotoPath)) {
                unlink($oldPhotoPath);
            }
        }
        $_SESSION['success'] = 'Attendant updated successfully.';
    } catch (Throwable $error) {
        if ($newPhoto !== null && is_file(dirname(__DIR__) . '/uploads/profile_photos/' . $newPhoto)) {
            unlink(dirname(__DIR__) . '/uploads/profile_photos/' . $newPhoto);
        }
        $_SESSION['error'] = $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Could not update attendant. Please try again.';
    }

    redirectToAttendants();
}

function handleAttendantStatusAction(string $action): void
{
    global $conn;

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        $_SESSION['error'] = 'Invalid attendant selected.';
        redirectToAttendants();
    }

    if ($action === 'delete') {
        $sql = "UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW()
                WHERE id = ? AND role = 'attendant' AND deleted_at IS NULL";
        $message = 'Attendant removed successfully.';
    } else {
        $status = $action === 'activate' ? 'active' : 'inactive';
        $sql = 'UPDATE users SET status = ?, updated_at = NOW()
                WHERE id = ? AND role = \'attendant\' AND deleted_at IS NULL';
        $message = $action === 'activate' ? 'Attendant activated successfully.' : 'Attendant deactivated successfully.';
    }

    $stmt = $conn->prepare($sql);
    if ($action === 'delete') {
        $stmt->bind_param('i', $id);
    } else {
        $stmt->bind_param('si', $status, $id);
    }
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $_SESSION['error'] = 'Attendant was not found or no change was needed.';
    } else {
        $_SESSION['success'] = $message;
    }
    redirectToAttendants();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendants - God Willing Business Center</title>
    
    <!-- Add necessary CSS and JS links -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-green);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }
        
        .btn-action {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .attendant-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-green);
        }
        
        .attendant-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .attendant-avatar {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin-right: 15px;
            overflow: hidden;
        }
        
        .attendant-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .attendant-avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .status-inactive {
            background: rgba(148, 163, 184, 0.1);
            color: #64748b;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        
        .status-suspended {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
            border: none;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .profile-photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-photo-preview:hover {
            border-color: var(--primary-green);
            transform: scale(1.05);
        }
        
        .photo-upload-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .photo-upload-label {
            cursor: pointer;
            display: inline-block;
        }
        
        .photo-upload-input {
            display: none;
        }
        
        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 8px;
            border-radius: 0 0 50% 50%;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .photo-upload-container:hover .photo-overlay {
            opacity: 1;
        }
        
        .photo-preview-container {
            position: relative;
            display: inline-block;
        }
        
        .pagination .page-link {
            border: none;
            color: var(--primary-green);
            border-radius: 8px;
            margin: 0 3px;
            transition: all 0.3s ease;
        }
        
        .pagination .page-link:hover {
            background: rgba(34, 197, 94, 0.1);
            color: var(--primary-green);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-green);
            color: white;
        }
        
        @media (max-width: 768px) {
            .attendant-card {
                padding: 15px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
            
            .btn-action {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
            
            .profile-photo-preview {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="dashboard-title mb-2">Manage Attendants</h1>
                <p class="text-muted mb-0">Manage and monitor all fuel attendants across branches</p>
            </div>
            <div>
                <button class="btn btn-primary" id="addAttendantBtn" onclick="showAddModal()">
                    <i class="fas fa-user-plus me-2"></i> Add New Attendant
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['active_attendants']; ?></div>
                    <div class="stat-label">Active Attendants</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['new_today']; ?></div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['inactive_attendants']; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['suspended_attendants']; ?></div>
                    <div class="stat-label">Suspended</div>
                </div>
            </div>
        </div>
        
        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="filter" class="form-select" onchange="this.form.submit()">
                            <option value="">All Attendants</option>
                            <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            <option value="suspended" <?php echo $filter == 'suspended' ? 'selected' : ''; ?>>Suspended Only</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone or employee ID..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search) || !empty($filter)): ?>
                                <a href="attendance" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="button" class="btn btn-outline-primary" onclick="exportToExcel()">
                            <i class="fas fa-file-export me-2"></i> Export
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="printReport()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Attendants List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Attendants List</h5>
                <p class="text-muted mb-0">Showing <?php echo $attendantsResult->num_rows; ?> of <?php echo $totalAttendants; ?> attendants</p>
            </div>
            <div class="card-body">
                <?php if ($attendantsResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Employee ID</th>
                                    <th>Attendant</th>
                                    <th>Branch</th>
                                    <th>Contact</th>
                                    <th>Shift Time</th>
                                    <th>Rate/Hour</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($attendant = $attendantsResult->fetch_assoc()): 
                                    $initials = strtoupper(substr((string)$attendant['full_name'], 0, 2));
                                    $statusClass = "status-" . $attendant['status'];
                                    $photoPath = !empty($attendant['profile_photo']) && $attendant['profile_photo'] !== 'default-avatar.png'
                                        ? '../uploads/profile_photos/' . rawurlencode(basename($attendant['profile_photo']))
                                        : '../assets/images/default-avatar.svg';
                                ?>
                                <tr>
                                    <td>
                                        <div class="attendant-avatar">
                                            <?php if ($attendant['profile_photo'] && $attendant['profile_photo'] !== 'default-avatar.png'): ?>
                                                <img src="<?php echo $photoPath; ?>" 
                                                     alt="<?php echo htmlspecialchars($attendant['full_name']); ?>"
                                                     class="attendant-avatar-img"
                                                     onerror="this.onerror=null; this.src='../assets/images/default-avatar.svg';">
                                            <?php else: ?>
                                                <div class="attendant-avatar-initials">
                                                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($attendant['employee_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($attendant['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($attendant['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($attendant['branch_name'] ?? 'Not Assigned', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($attendant['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted">PIN: <?php echo htmlspecialchars($attendant['pin_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($attendant['shift_time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">$<?php echo number_format($attendant['hourly_rate'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($attendant['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editAttendant(<?php echo $attendant['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($attendant['status'] == 'active'): ?>
                                                <button class="btn btn-sm btn-outline-warning"
                                                        onclick="deactivateAttendant(<?php echo $attendant['id']; ?>)"
                                                        title="Deactivate">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-success"
                                                        onclick="activateAttendant(<?php echo $attendant['id']; ?>)"
                                                        title="Activate">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteAttendant(<?php echo $attendant['id']; ?>)"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-outline-info"
                                                    onclick="viewAttendantDetails(<?php echo $attendant['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo rawurlencode($filter); ?>&search=<?php echo rawurlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo rawurlencode($filter); ?>&search=<?php echo rawurlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo rawurlencode($filter); ?>&search=<?php echo rawurlencode($search); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Attendants Found</h5>
                        <p class="text-muted mb-4">No attendants match your search criteria.</p>
                        <button class="btn btn-primary" onclick="showAddModal()">
                            <i class="fas fa-user-plus me-2"></i> Add Your First Attendant
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Attendant Modal -->
    <div class="modal fade" id="addAttendantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Attendant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAttendantForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-body">
                        <!-- Profile Photo Upload -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="photo-upload-container">
                                    <label for="profile_photo" class="photo-upload-label">
                                        <div class="photo-preview-container">
                                            <img id="addPhotoPreview" src="../assets/images/default-avatar.svg" 
                                                 alt="Profile Preview" 
                                                 class="profile-photo-preview">
                                            <div class="photo-overlay">
                                                <i class="fas fa-camera me-1"></i> Change Photo
                                            </div>
                                        </div>
                                    </label>
                                    <input type="file" id="profile_photo" name="profile_photo" 
                                           class="photo-upload-input" accept="image/*" 
                                           onchange="previewImage('addPhotoPreview', this)">
                                    <div class="mt-2">
                                        <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" class="form-control">
                                <small class="text-muted">Leave blank to assign an ID using the branch initial and registration year.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch *</label>
                                <select name="branch_id" class="form-select" required>
                                    <option value="">Select Branch</option>
                                    <?php 
                                    $branchesResult->data_seek(0);
                                    while($branch = $branchesResult->fetch_assoc()): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift Time *</label>
                                <select name="shift_time" class="form-select" required>
                                    <option value="Morning (6AM-2PM)">Morning (6AM-2PM)</option>
                                    <option value="Afternoon (2PM-10PM)">Afternoon (2PM-10PM)</option>
                                    <option value="Night (10PM-6AM)">Night (10PM-6AM)</option>
                                    <option value="Flexible">Flexible</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hourly Rate ($) *</label>
                                <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0" value="10.00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Initial Password</label>
                                <input type="text" class="form-control" value="attendant123" disabled>
                                <small class="text-muted">Default password for attendant login</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Attendant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Attendant Modal -->
    <div class="modal fade" id="editAttendantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Attendant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editAttendantForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-body">
                        <!-- Profile Photo Upload -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="photo-upload-container">
                                    <label for="edit_profile_photo" class="photo-upload-label">
                                        <div class="photo-preview-container">
                                            <img id="editPhotoPreview" src="../assets/images/default-avatar.svg" 
                                                 alt="Profile Preview" 
                                                 class="profile-photo-preview">
                                            <div class="photo-overlay">
                                                <i class="fas fa-camera me-1"></i> Change Photo
                                            </div>
                                        </div>
                                    </label>
                                    <input type="file" id="edit_profile_photo" name="profile_photo" 
                                           class="photo-upload-input" accept="image/*" 
                                           onchange="previewImage('editPhotoPreview', this)">
                                    <div class="mt-2">
                                        <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF. Leave empty to keep current.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" id="edit_phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" id="edit_employee_id" class="form-control">
                                <small class="text-muted">Leave blank to assign an ID using the branch initial and registration year.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch *</label>
                                <select name="branch_id" id="edit_branch_id" class="form-select" required>
                                    <option value="">Select Branch</option>
                                    <?php 
                                    // Reset branches pointer
                                    $branchesResult->data_seek(0);
                                    while($branch = $branchesResult->fetch_assoc()): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift Time *</label>
                                <select name="shift_time" id="edit_shift_time" class="form-select" required>
                                    <option value="Morning (6AM-2PM)">Morning (6AM-2PM)</option>
                                    <option value="Afternoon (2PM-10PM)">Afternoon (2PM-10PM)</option>
                                    <option value="Night (10PM-6AM)">Night (10PM-6AM)</option>
                                    <option value="Flexible">Flexible</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hourly Rate ($) *</label>
                                <input type="number" name="hourly_rate" id="edit_hourly_rate" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">PIN Code</label>
                                <input type="text" id="edit_pin_code" class="form-control" disabled>
                                <small class="text-muted">Cannot be changed. Reset password to generate new PIN.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Attendant</button>
                        <button type="button" class="btn btn-warning" onclick="resetPassword()">
                            <i class="fas fa-key me-1"></i> Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div class="modal fade" id="viewAttendantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendant Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="attendantDetailsContent">
                    <!-- Details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show messages
        <?php if (isset($_SESSION['success'])): ?>
            showNotification(<?php echo json_encode($_SESSION['success']); ?>, 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            showNotification(<?php echo json_encode($_SESSION['error']); ?>, 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        function showNotification(message, type) {
            Swal.fire({
                icon: type,
                title: type === 'success' ? 'Success!' : 'Error!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
        
        // Show add modal
        function showAddModal() {
            const modal = new bootstrap.Modal(document.getElementById('addAttendantModal'));
            modal.show();
        }
        
        // Edit attendant
        function editAttendant(id) {
            // AJAX request to get attendant data
            $.ajax({
                url: '../api/get_attendant.php',
                method: 'GET',
                dataType: 'json',
                data: { id: id },
                success: function(response) {
                    try {
                        const data = response;
                        if (data.success) {
                            const attendant = data.attendant;
                            
                            // Populate form fields
                            $('#edit_id').val(attendant.id);
                            $('#edit_full_name').val(attendant.full_name);
                            $('#edit_email').val(attendant.email);
                            $('#edit_phone').val(attendant.phone);
                            $('#edit_employee_id').val(attendant.employee_id);
                            $('#edit_branch_id').val(attendant.branch_id);
                            $('#edit_shift_time').val(attendant.shift_time);
                            $('#edit_hourly_rate').val(attendant.hourly_rate);
                            $('#edit_status').val(attendant.status);
                            $('#edit_pin_code').val(attendant.pin_code);
                            
                            // Set profile photo preview
                            const photoPreview = $('#editPhotoPreview');
                            if (attendant.profile_photo && attendant.profile_photo !== 'default-avatar.png') {
                                photoPreview.attr('src', '../uploads/profile_photos/' + encodeURIComponent(attendant.profile_photo));
                            } else {
                                photoPreview.attr('src', '../assets/images/default-avatar.svg');
                            }
                            
                            // Show modal
                            const modal = new bootstrap.Modal(document.getElementById('editAttendantModal'));
                            modal.show();
                        } else {
                            showNotification(data.message || 'Failed to load attendant data', 'error');
                        }
                    } catch (e) {
                        showNotification('Error parsing attendant data', 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to load attendant details', 'error');
                }
            });
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[character]);
        }
        
        // View attendant details
        function viewAttendantDetails(id) {
            $.ajax({
                url: '../api/get_attendant_details.php',
                method: 'GET',
                dataType: 'json',
                data: { id: id },
                success: function(response) {
                    try {
                        const data = response;
                        if (data.success) {
                            const attendant = data.attendant;
                            
                            // Create HTML for details
                            const detailsHtml = `
                                <div class="row">
                                    <div class="col-md-4 text-center mb-4">
                                        <div class="mb-3">
                                            ${attendant.profile_photo && attendant.profile_photo !== 'default-avatar.png' ? 
                                                `<img src="../uploads/profile_photos/${encodeURIComponent(attendant.profile_photo)}" 
                                                      class="img-fluid rounded-circle" 
                                                      style="width: 150px; height: 150px; object-fit: cover;"
                                                      onerror="this.src='../assets/images/default-avatar.svg'">` :
                                                `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                                      style="width: 150px; height: 150px; font-size: 48px;">
                                                    ${escapeHtml(attendant.full_name ? attendant.full_name.substring(0, 2).toUpperCase() : 'NA')}
                                                </div>`
                                            }
                                        </div>
                                        <h4>${escapeHtml(attendant.full_name || 'N/A')}</h4>
                                        <span class="badge bg-${attendant.status === 'active' ? 'success' : attendant.status === 'inactive' ? 'warning' : 'danger'}">
                                            ${escapeHtml(attendant.status ? attendant.status.charAt(0).toUpperCase() + attendant.status.slice(1) : 'N/A')}
                                        </span>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted">Personal Information</h6>
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td width="40%"><strong>Employee ID:</strong></td>
                                                        <td>${escapeHtml(attendant.employee_id || 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Email:</strong></td>
                                                        <td>${escapeHtml(attendant.email || 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Phone:</strong></td>
                                                        <td>${escapeHtml(attendant.phone || 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>PIN Code:</strong></td>
                                                        <td><code class="bg-light p-1 rounded">${escapeHtml(attendant.pin_code || 'N/A')}</code></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted">Employment Details</h6>
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td width="40%"><strong>Branch:</strong></td>
                                                        <td>${escapeHtml(attendant.branch_name || 'Not Assigned')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Shift Time:</strong></td>
                                                        <td>${escapeHtml(attendant.shift_time || 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Hourly Rate:</strong></td>
                                                        <td>$${(Number(attendant.hourly_rate) || 0).toFixed(2)}/hour</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Role:</strong></td>
                                                        <td>${escapeHtml(attendant.role ? attendant.role.charAt(0).toUpperCase() + attendant.role.slice(1) : 'N/A')}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                            
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted">Account Information</h6>
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td width="40%"><strong>Created On:</strong></td>
                                                        <td>${attendant.created_at ? new Date(attendant.created_at).toLocaleDateString() : 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Last Updated:</strong></td>
                                                        <td>${attendant.updated_at ? new Date(attendant.updated_at).toLocaleDateString() : 'Never'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Created By:</strong></td>
                                                        <td>${escapeHtml(attendant.created_by_name || 'System')}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            $('#attendantDetailsContent').html(detailsHtml);
                            const modal = new bootstrap.Modal(document.getElementById('viewAttendantModal'));
                            modal.show();
                        } else {
                            showNotification(data.message || 'Failed to load attendant details', 'error');
                        }
                    } catch (e) {
                        showNotification('Error parsing attendant details', 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to load attendant details', 'error');
                }
            });
        }
        
        // Delete attendant with confirmation
        function deleteAttendant(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will remove the attendant from the active list while preserving historical records.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitAttendantAction('delete', id);
                }
            });
        }
        
        // Activate attendant with confirmation
        function activateAttendant(id) {
            Swal.fire({
                title: 'Activate Attendant?',
                text: "This attendant will be activated and can log in.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, activate!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitAttendantAction('activate', id);
                }
            });
        }
        
        // Deactivate attendant with confirmation
        function deactivateAttendant(id) {
            Swal.fire({
                title: 'Deactivate Attendant?',
                text: "This attendant will be deactivated and cannot log in.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, deactivate!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitAttendantAction('deactivate', id);
                }
            });
        }

        function submitAttendantAction(action, id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'attendance';
            [[ 'action', action ], [ 'id', id ], [ 'csrf_token', <?php echo json_encode($csrfToken); ?> ]].forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
        
        // Reset password
        function resetPassword() {
            const id = $('#edit_id').val();
            if (id) {
                Swal.fire({
                    title: 'Reset Password?',
                    text: "This will generate a new PIN code for the attendant.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, reset!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '../api/reset_password.php',
                            method: 'POST',
                            dataType: 'json',
                            data: { id: id, csrf_token: <?php echo json_encode($csrfToken); ?> },
                            success: function(response) {
                                try {
                                    const data = response;
                                    if (data.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Password Reset!',
                                            html: `New PIN: <strong>${data.new_pin}</strong><br><br>The attendant will use this PIN to log in.`,
                                            confirmButtonText: 'OK'
                                        });
                                        $('#edit_pin_code').val(data.new_pin);
                                    } else {
                                        showNotification(data.message || 'Failed to reset password', 'error');
                                    }
                                } catch (e) {
                                    showNotification('Error resetting password', 'error');
                                }
                            },
                            error: function() {
                                showNotification('Failed to reset password', 'error');
                            }
                        });
                    }
                });
            }
        }
        
        // Export to Excel
        function exportToExcel() {
            // Build export URL with current filters
            let exportUrl = '../api/export_attendants.php';
            let params = [];
            
            if (<?php echo !empty($filter) ? 'true' : 'false'; ?>) {
                params.push('filter=' + encodeURIComponent(<?php echo json_encode($filter); ?>));
            }
            
            if (<?php echo !empty($search) ? 'true' : 'false'; ?>) {
                params.push('search=' + encodeURIComponent(<?php echo json_encode($search); ?>));
            }
            
            if (params.length > 0) {
                exportUrl += '?' + params.join('&');
            }
            
            window.location.href = exportUrl;
        }
        
        // Print report
        function printReport() {
            let printUrl = '../api/print_attendants.php';
            let params = [];
            
            if (<?php echo !empty($filter) ? 'true' : 'false'; ?>) {
                params.push('filter=' + encodeURIComponent(<?php echo json_encode($filter); ?>));
            }
            
            if (<?php echo !empty($search) ? 'true' : 'false'; ?>) {
                params.push('search=' + encodeURIComponent(<?php echo json_encode($search); ?>));
            }
            
            if (params.length > 0) {
                printUrl += '?' + params.join('&');
            }
            
            window.open(printUrl, '_blank');
        }
        
        // Image preview function
        function previewImage(previewId, input) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case 'n': // Add new attendant
                        e.preventDefault();
                        showAddModal();
                        break;
                    case 'e': // Export
                        e.preventDefault();
                        exportToExcel();
                        break;
                    case 'p': // Print
                        e.preventDefault();
                        printReport();
                        break;
                }
            }
        });
        
        // Auto-refresh every 2 minutes
        setInterval(function() {
            // Only refresh if no modal is open
            if (!$('.modal.show').length) {
                window.location.reload();
            }
        }, 120000);
    </script>
</body>
</html>