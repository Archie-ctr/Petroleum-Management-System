<?php
session_start();
require_once '../config/db.php';
require_once '../includes/security.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Initialize variables
$filter = is_string($_GET['filter'] ?? null) ? $_GET['filter'] : 'all';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'unread';
$search = is_string($_GET['search'] ?? null) ? trim(substr($_GET['search'], 0, 100)) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$csrf_token = generateCSRFToken();

// Get notification types
$notification_types = [
    'low_stock' => [
        'icon' => 'fa-exclamation-triangle',
        'color' => 'danger',
        'label' => 'Low Stock Alert',
        'priority' => 1
    ],
    'pending_expense' => [
        'icon' => 'fa-file-invoice-dollar',
        'color' => 'warning',
        'label' => 'Pending Expense',
        'priority' => 2
    ],
    'new_sale' => [
        'icon' => 'fa-shopping-cart',
        'color' => 'success',
        'label' => 'New Sale',
        'priority' => 3
    ],
    'new_user' => [
        'icon' => 'fa-user-plus',
        'color' => 'info',
        'label' => 'New User',
        'priority' => 4
    ],
    'system_alert' => [
        'icon' => 'fa-server',
        'color' => 'secondary',
        'label' => 'System Alert',
        'priority' => 5
    ],
    'maintenance' => [
        'icon' => 'fa-tools',
        'color' => 'primary',
        'label' => 'Maintenance',
        'priority' => 6
    ]
];

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Security validation failed. Refresh the page and try again.';
    } else {
        try {
            if (isset($_POST['mark_read'])) {
                $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
                if (!$notification_id) {
                    throw new InvalidArgumentException('Invalid notification.');
                }
                $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $notification_id);
                $stmt->execute();
                $_SESSION['success'] = 'Notification marked as read.';
            } elseif (isset($_POST['mark_all_read'])) {
                $conn->query("UPDATE notifications SET status = 'read', read_at = NOW() WHERE status = 'unread'");
                $_SESSION['success'] = 'All notifications marked as read.';
            } elseif (isset($_POST['delete'])) {
                $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
                if (!$notification_id) {
                    throw new InvalidArgumentException('Invalid notification.');
                }
                $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ?');
                $stmt->bind_param('i', $notification_id);
                $stmt->execute();
                $_SESSION['success'] = 'Notification deleted.';
            } elseif (isset($_POST['delete_all_read'])) {
                $conn->query("DELETE FROM notifications WHERE status = 'read'");
                $_SESSION['success'] = 'All read notifications deleted.';
            }
        } catch (Throwable $e) {
            logError('Notification action failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Unable to complete the notification action.';
        }
    }
    header("Location: notifications.php");
    exit();
}

$valid_filters = array_merge(['all'], array_keys($notification_types));
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}
if (!in_array($status, ['all', 'read', 'unread'], true)) {
    $status = 'unread';
}

require_once '../includes/header.php';

// Get notifications count
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE 1=1";
$count_params = [];
$count_types = "";

if ($filter !== 'all') {
    $count_sql .= " AND notification_type = ?";
    $count_params[] = $filter;
    $count_types .= "s";
}

if ($status !== 'all') {
    $count_sql .= " AND status = ?";
    $count_params[] = $status;
    $count_types .= "s";
}

if (!empty($search)) {
    $count_sql .= " AND (title LIKE ? OR message LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.id = notifications.created_by AND u.full_name LIKE ?))";
    $search_term = "%$search%";
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_types .= "sss";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_notifications = $count_result->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_notifications / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Get notifications with filters
$sql = "SELECT n.*, u.full_name as created_by_name 
        FROM notifications n 
        LEFT JOIN users u ON n.created_by = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($filter !== 'all') {
    $sql .= " AND n.notification_type = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($status !== 'all') {
    $sql .= " AND n.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (n.title LIKE ? OR n.message LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$sql .= " ORDER BY FIELD(n.priority, 'high', 'medium', 'low'), n.created_at DESC 
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// Get notification statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN notification_type = 'low_stock' THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN notification_type = 'pending_expense' THEN 1 ELSE 0 END) as pending_expense,
                SUM(CASE WHEN notification_type = 'new_sale' THEN 1 ELSE 0 END) as new_sale,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) AS `high_priority`
              FROM notifications";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
    <style>
        /* Notification Styles */
        .notification-card {
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #cbd5e0;
        }

        .notification-card.unread {
            border-left: 4px solid #3182ce;
            background: linear-gradient(to right, rgba(49, 130, 206, 0.05), white);
        }

        .notification-card.high-priority {
            border-left: 4px solid #e53e3e;
            background: linear-gradient(to right, rgba(229, 62, 62, 0.05), white);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }

        .notification-icon.danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #c53030;
        }

        .notification-icon.warning {
            background: linear-gradient(135deg, #feebc8, #fbd38d);
            color: #d69e2e;
        }

        .notification-icon.success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #38a169;
        }

        .notification-icon.info {
            background: linear-gradient(135deg, #bee3f8, #90cdf4);
            color: #3182ce;
        }

        .notification-icon.primary {
            background: linear-gradient(135deg, #e9d8fd, #d6bcfa);
            color: #6b46c1;
        }

        .notification-badge {
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-unread {
            background: #3182ce;
            color: white;
        }

        .badge-read {
            background: #a0aec0;
            color: white;
        }

        .badge-high {
            background: #e53e3e;
            color: white;
        }

        .badge-medium {
            background: #d69e2e;
            color: white;
        }

        .badge-low {
            background: #38a169;
            color: white;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .notification-card:hover .notification-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .action-btn.read:hover {
            background: #3182ce;
            color: white;
            border-color: #3182ce;
        }

        .action-btn.delete:hover {
            background: #e53e3e;
            color: white;
            border-color: #e53e3e;
        }

        .action-btn.view:hover {
            background: #38a169;
            color: white;
            border-color: #38a169;
        }

        .filter-btn {
            padding: 8px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #4a5568;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.2);
        }

        .filter-btn.active i {
            animation: bounce 0.5s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #2d3748;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #718096;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .notification-detail-modal {
            max-width: 600px;
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .notification-detail-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #22c55e 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px;
        }

        .notification-detail-body {
            padding: 30px;
            background: #f8fafc;
        }

        .notification-detail-footer {
            background: white;
            border-radius: 0 0 20px 20px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-link {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .page-link:hover, .page-link.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
            transform: translateY(-2px);
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-link.disabled:hover {
            transform: none;
            background: white;
            color: #4a5568;
            border-color: #e2e8f0;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .notification-card {
                padding: 15px !important;
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                margin-right: 10px;
            }

            .notification-actions {
                opacity: 1;
                margin-top: 10px;
                justify-content: center !important;
            }

            .filter-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }

            .stat-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }
        }

        /* Animation for new notifications */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .notification-card {
            animation: slideIn 0.5s ease forwards;
        }

        .notification-card:nth-child(1) { animation-delay: 0.1s; }
        .notification-card:nth-child(2) { animation-delay: 0.2s; }
        .notification-card:nth-child(3) { animation-delay: 0.3s; }
        .notification-card:nth-child(4) { animation-delay: 0.4s; }
        .notification-card:nth-child(5) { animation-delay: 0.5s; }
    </style>
        <div class="container-fluid py-4 px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="dashboard-title">
                        <i class="fas fa-bell me-2"></i> Notifications Management
                    </h1>
                    <p class="text-muted mb-0">Manage and monitor all system notifications and alerts</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="mark_all_read" class="btn btn-primary">
                            <i class="fas fa-check-double me-2"></i> Mark All as Read
                        </button>
                    </form>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="delete_all_read" class="btn btn-outline-danger" 
                                onclick="return confirm('Are you sure you want to delete all read notifications?')">
                            <i class="fas fa-trash me-2"></i> Delete Read
                        </button>
                    </form>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Total Notifications</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['unread'] ?? 0; ?></div>
                        <div class="stat-label">Unread Notifications</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['low_stock'] ?? 0; ?></div>
                        <div class="stat-label">Low Stock Alerts</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['high_priority'] ?? 0; ?></div>
                        <div class="stat-label">High Priority</div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a href="?filter=all&amp;status=<?php echo urlencode($status); ?>" 
                                   class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                                    <i class="fas fa-layer-group"></i> All Types
                                </a>
                                <?php foreach ($notification_types as $type => $info): ?>
                                    <a href="?filter=<?php echo urlencode($type); ?>&amp;status=<?php echo urlencode($status); ?>" 
                                       class="filter-btn <?php echo $filter == $type ? 'active' : ''; ?>">
                                        <i class="fas <?php echo $info['icon']; ?>"></i> <?php echo $info['label']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?filter=<?php echo urlencode($filter); ?>&amp;status=all" 
                                   class="filter-btn <?php echo $status == 'all' ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i> All Status
                                </a>
                                <a href="?filter=<?php echo urlencode($filter); ?>&amp;status=unread" 
                                   class="filter-btn <?php echo $status == 'unread' ? 'active' : ''; ?>">
                                    <i class="fas fa-envelope"></i> Unread
                                </a>
                                <a href="?filter=<?php echo urlencode($filter); ?>&amp;status=read" 
                                   class="filter-btn <?php echo $status == 'read' ? 'active' : ''; ?>">
                                    <i class="fas fa-envelope-open"></i> Read
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <form method="GET" class="d-flex">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="text" name="search" class="form-control me-2" 
                                       placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>" 
                                       class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h4 class="text-muted">No notifications found</h4>
                            <p class="text-muted mb-4">
                                <?php if (!empty($search)): ?>
                                    No notifications match your search criteria
                                <?php else: ?>
                                    All notifications are up to date
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($search)): ?>
                                <a href="notifications.php" class="btn btn-primary">
                                    <i class="fas fa-sync me-2"></i> Clear Search
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php 
                            $type_info = $notification_types[$notification['notification_type']] ?? $notification_types['system_alert'];
                            $priority_class = $notification['priority'] == 'high' ? 'high-priority' : '';
                            $status_class = $notification['status'] == 'unread' ? 'unread' : '';
                            ?>
                            
                            <div class="notification-card <?php echo $priority_class; ?> <?php echo $status_class; ?> p-3">
                                <div class="d-flex align-items-start">
                                    <!-- Notification Icon -->
                                    <div class="notification-icon <?php echo $type_info['color']; ?>">
                                        <i class="fas <?php echo $type_info['icon']; ?>"></i>
                                    </div>
                                    
                                    <!-- Notification Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                                <p class="text-muted mb-2">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </p>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <span class="notification-badge badge-<?php echo $notification['status']; ?>">
                                                    <?php echo ucfirst($notification['status']); ?>
                                                </span>
                                                <span class="notification-badge badge-<?php echo $notification['priority']; ?>">
                                                    <?php echo ucfirst($notification['priority']); ?> Priority
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="notification-time">
                                                <i class="far fa-clock"></i>
                                                <?php 
                                                $time_ago = time_elapsed_string($notification['created_at']);
                                                echo $time_ago;
                                                ?>
                                                <?php if ($notification['created_by_name']): ?>
                                                    <span class="ms-3">
                                                        <i class="fas fa-user"></i>
                                                        <?php echo htmlspecialchars($notification['created_by_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="notification-actions d-flex gap-2">
                                                <?php if ($notification['status'] == 'unread'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" name="mark_read" class="action-btn read" 
                                                                title="Mark as Read">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="action-btn view" 
                                                        onclick="viewNotificationDetail(<?php echo htmlspecialchars(json_encode($notification)); ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <form method="post" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="delete" class="action-btn delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-container">
                                <!-- First Page -->
                                <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=1" 
                                   class="page-link <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                
                                <!-- Previous Page -->
                                <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo max(1, $page - 1); ?>" 
                                   class="page-link <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                                
                                <!-- Page Numbers -->
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<span class="px-3">...</span>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor;
                                
                                if ($end_page < $total_pages) {
                                    echo '<span class="px-3">...</span>';
                                }
                                ?>
                                
                                <!-- Next Page -->
                                <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo min($total_pages, $page + 1); ?>" 
                                   class="page-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                
                                <!-- Last Page -->
                                <a href="?filter=<?php echo $filter; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>" 
                                   class="page-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4 text-muted">
                            Showing <?php echo count($notifications); ?> of <?php echo $total_notifications; ?> notifications
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Detail Modal -->
    <div class="modal fade" id="notificationDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered notification-detail-modal">
            <div class="modal-content">
                <div class="notification-detail-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="modal-title mb-0" id="notificationModalTitle"></h5>
                            <p class="mb-0 opacity-90" id="notificationModalType"></p>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="notification-detail-body">
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Message:</h6>
                        <p class="mb-0" id="notificationModalMessage"></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Priority:</h6>
                            <span class="badge" id="notificationModalPriority"></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Status:</h6>
                            <span class="badge" id="notificationModalStatus"></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Created By:</h6>
                            <p class="mb-0" id="notificationModalCreatedBy"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Created At:</h6>
                            <p class="mb-0" id="notificationModalCreatedAt"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Read At:</h6>
                            <p class="mb-0" id="notificationModalReadAt"></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Related To:</h6>
                        <p class="mb-0" id="notificationModalRelatedTo"></p>
                    </div>
                </div>
                <div class="notification-detail-footer">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Close
                        </button>
                        <form method="post" id="markReadForm" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="notification_id" id="markReadNotificationId">
                            <button type="submit" name="mark_read" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i> Mark as Read
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function viewNotificationDetail(notification) {
            // Set modal content
            document.getElementById('notificationModalTitle').textContent = notification.title;
            document.getElementById('notificationModalType').textContent = getNotificationTypeLabel(notification.notification_type);
            document.getElementById('notificationModalMessage').textContent = notification.message;
            
            // Set priority badge
            const priorityBadge = document.getElementById('notificationModalPriority');
            priorityBadge.textContent = notification.priority.charAt(0).toUpperCase() + notification.priority.slice(1);
            priorityBadge.className = 'badge badge-' + notification.priority;
            
            // Set status badge
            const statusBadge = document.getElementById('notificationModalStatus');
            statusBadge.textContent = notification.status.charAt(0).toUpperCase() + notification.status.slice(1);
            statusBadge.className = 'badge badge-' + notification.status;
            
            // Set other details
            document.getElementById('notificationModalCreatedBy').textContent = notification.created_by_name || 'System';
            document.getElementById('notificationModalCreatedAt').textContent = formatDateTime(notification.created_at);
            
            if (notification.read_at) {
                document.getElementById('notificationModalReadAt').textContent = formatDateTime(notification.read_at);
            }
            
            // Set related to info if exists
            if (notification.related_id && notification.related_type) {
                const relatedText = notification.related_type.charAt(0).toUpperCase() + 
                                   notification.related_type.slice(1) + ' #' + notification.related_id;
                document.getElementById('notificationModalRelatedTo').textContent = relatedText;
            }
            
            // Set mark read form
            document.getElementById('markReadNotificationId').value = notification.id;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('notificationDetailModal'));
            modal.show();
        }
        
        function getNotificationTypeLabel(type) {
            const types = {
                'low_stock': 'Low Stock Alert',
                'pending_expense': 'Pending Expense',
                'new_sale': 'New Sale',
                'new_user': 'New User',
                'system_alert': 'System Alert',
                'maintenance': 'Maintenance'
            };
            return types[type] || 'Notification';
        }
        
        function formatDateTime(dateTime) {
            const date = new Date(dateTime);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + R to mark all as read
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                document.querySelector('button[name="mark_all_read"]').click();
            }
            
            // Alt + D to delete all read
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                if (confirm('Are you sure you want to delete all read notifications?')) {
                    document.querySelector('button[name="delete_all_read"]').click();
                }
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('notificationDetailModal');
                if (modal.classList.contains('show')) {
                    bootstrap.Modal.getInstance(modal).hide();
                }
            }
        });
        
        // Mark notification as read when viewed in modal
        document.getElementById('markReadForm').addEventListener('submit', function(e) {
            const notificationId = document.getElementById('markReadNotificationId').value;
            
            // Submit via AJAX for better UX
            e.preventDefault();
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(this))
            })
            .then(response => {
                // Close modal and reload page
                bootstrap.Modal.getInstance(document.getElementById('notificationDetailModal')).hide();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback to normal form submission
                this.submit();
            });
        });
    </script>

<?php
// Function to format time ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>