<?php
// Admin sidebar
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Get admin-specific stats
$admin_stats = [];
try {
    // Total users count
    $users_sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    $admin_stats['total_users'] = (int)$users_result->fetch_assoc()['count'];
    $users_stmt->close();
    
    // Today's revenue
    $today = date('Y-m-d');
    $revenue_sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = ? AND status = 'completed'";
    $revenue_stmt = $conn->prepare($revenue_sql);
    $revenue_stmt->bind_param("s", $today);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
    $admin_stats['today_revenue'] = (float)$revenue_result->fetch_assoc()['total'];
    $revenue_stmt->close();
    
    // System alerts
    $alerts_sql = "SELECT (
        (SELECT COUNT(*) FROM expenses WHERE status = 'pending') +
        (SELECT COUNT(*) FROM tanks WHERE current_quantity < minimum_threshold AND status = 'active') +
        (SELECT COUNT(*) FROM pumps WHERE status = 'maintenance')
    ) as total_alerts";
    $alerts_stmt = $conn->prepare($alerts_sql);
    $alerts_stmt->execute();
    $alerts_result = $alerts_stmt->get_result();
    $admin_stats['system_alerts'] = (int)$alerts_result->fetch_assoc()['total_alerts'];
    $alerts_stmt->close();
    
} catch (Exception $e) {
    error_log("Admin sidebar stats error: " . $e->getMessage());
    $admin_stats = [
        'total_users' => 0,
        'today_revenue' => 0,
        'system_alerts' => 0
    ];
}
?>
<style>
    /* Admin Sidebar Specific Styles */
    .admin-sidebar {
        position: fixed;
        top: var(--topbar-height);
        left: 0;
        height: calc(100vh - var(--topbar-height));
        width: var(--sidebar-width);
        background: #fff;
        z-index: 1000;
        transition: all var(--transition-speed) ease;
        box-shadow: 2px 0 12px rgba(15, 23, 42, 0.05);
        border-right: 1px solid #e8ecef;
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }

    .admin-sidebar-header {
        min-height: 0;
        padding: 8px 12px;
        border-bottom: 1px solid #eef0f2;
    }

    .admin-sidebar-header .admin-stats,
    .admin-quick-actions {
        display: none;
    }

    .admin-nav {
        padding: 8px 0;
    }

    .admin-nav-section {
        margin-bottom: 8px;
    }

    .admin-nav-label {
        padding: 12px 16px 6px;
        margin: 0;
        border: 0;
        background: transparent;
        color: #84909b;
        font-size: 0.66rem;
        letter-spacing: 0.08em;
    }

    .admin-nav-link {
        margin: 2px 8px;
        min-height: 40px;
        padding: 9px 10px;
        border: 0;
        border-radius: 6px;
    }

    .admin-nav-link:hover {
        padding-left: 10px;
        background: #f4f7f5;
    }

    .admin-nav-link.active {
        background: #eaf4ee;
        color: var(--primary-green);
    }

    .admin-nav-icon {
        width: 20px;
        margin-right: 10px;
        font-size: 0.95rem;
    }

    .admin-submenu {
        margin-left: 18px;
        border-left: 1px solid #e8ecef;
        border-radius: 0;
        background: transparent;
    }

    .sidebar-collapsed .admin-sidebar {
        width: var(--sidebar-collapsed);
    }

    .admin-sidebar-header {
        padding: 20px 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        text-align: center;
        background: white;
        position: relative;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .sidebar-collapsed .admin-sidebar-header {
        padding: 15px 10px;
    }

    .admin-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 15px;
    }

    .sidebar-collapsed .admin-stats {
        display: none;
    }

    .admin-stat {
        text-align: center;
        padding: 8px;
        border-radius: 8px;
        background: rgba(25, 135, 84, 0.05);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .admin-stat:hover {
        background: rgba(25, 135, 84, 0.1);
        transform: translateY(-2px);
    }

    .admin-stat-value {
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--primary-green);
        line-height: 1;
    }

    .admin-stat-label {
        font-size: 0.65rem;
        color: #666;
        margin-top: 3px;
        white-space: nowrap;
    }

    /* Navigation Menu */
    .admin-nav {
        padding: 15px 0;
        flex: 1;
        overflow-y: auto;
    }

    .admin-nav-section {
        margin-bottom: 15px;
    }

    .admin-nav-label {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        padding: 10px 20px;
        margin-bottom: 5px;
        background: rgba(0, 0, 0, 0.02);
        border-left: 3px solid var(--primary-green);
    }

    .sidebar-collapsed .admin-nav-label {
        display: none;
    }

    .admin-nav-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .admin-nav-item {
        position: relative;
    }

    .admin-nav-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .sidebar-collapsed .admin-nav-link {
        padding: 12px 10px;
        justify-content: center;
    }

    .admin-nav-link:hover {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.05), transparent);
        color: var(--primary-green);
        padding-left: 25px;
    }

    .sidebar-collapsed .admin-nav-link:hover {
        padding-left: 10px;
    }

    .admin-nav-link.active {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
        color: var(--primary-green);
        border-left-color: var(--primary-green);
        font-weight: 600;
    }

    .admin-nav-icon {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        margin-right: 12px;
        color: #666;
        transition: all 0.3s ease;
    }

    .sidebar-collapsed .admin-nav-icon {
        margin-right: 0;
        font-size: 1.2rem;
    }

    .admin-nav-link:hover .admin-nav-icon,
    .admin-nav-link.active .admin-nav-icon {
        color: var(--primary-green);
        transform: scale(1.1);
    }

    .admin-nav-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    .sidebar-collapsed .admin-nav-text {
        display: none;
    }

    .admin-nav-badge {
        background: var(--primary-green);
        color: white;
        font-size: 0.65rem;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 5px;
        min-width: 18px;
        text-align: center;
        font-weight: 600;
    }

    .admin-nav-badge.danger {
        background: #dc3545;
    }

    .admin-nav-badge.warning {
        background: #ffc107;
        color: #000;
    }

    .admin-nav-badge.info {
        background: #0dcaf0;
    }

    .sidebar-collapsed .admin-nav-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        margin-left: 0;
        font-size: 0.55rem;
        padding: 1px 4px;
        min-width: 16px;
    }

    /* Submenu */
    .admin-submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: rgba(0, 0, 0, 0.02);
        border-left: 3px solid var(--primary-yellow);
        margin-left: 20px;
        border-radius: 0 0 8px 8px;
    }

    .admin-nav-item.has-dropdown.active .admin-submenu {
        max-height: 500px;
    }

    .sidebar-collapsed .admin-submenu {
        display: none !important;
    }

    .admin-submenu-item {
        list-style: none;
    }

    .admin-submenu-link {
        display: flex;
        align-items: center;
        padding: 10px 15px 10px 30px;
        color: #666;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .admin-submenu-link:hover,
    .admin-submenu-link.active {
        color: var(--primary-green);
        background: rgba(25, 135, 84, 0.05);
        padding-left: 35px;
    }

    .admin-submenu-link i {
        font-size: 0.8rem;
        margin-right: 8px;
        width: 16px;
        text-align: center;
    }

    /* Quick Admin Actions */
    .admin-quick-actions {
        padding: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background: white;
    }

    .sidebar-collapsed .admin-quick-actions {
        padding: 10px;
    }

    .quick-action-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-bottom: 10px;
    }

    .sidebar-collapsed .quick-action-grid {
        grid-template-columns: 1fr;
    }

    .quick-action-btn {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--green-dark) 100%);
        color: white;
        border: none;
        padding: 8px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-align: center;
        min-height: 40px;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
    }

    .quick-action-btn i {
        font-size: 0.9rem;
    }

    .sidebar-collapsed .quick-action-btn span {
        display: none;
    }

    /* System Status */
    .system-status {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
    }

    .sidebar-collapsed .system-status {
        display: none;
    }

    .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
        font-size: 0.8rem;
    }

    .status-item:last-child {
        margin-bottom: 0;
    }

    .status-label {
        color: #666;
    }

    .status-value {
        font-weight: 600;
    }

    .status-value.online {
        color: var(--primary-green);
    }

    .status-value.offline {
        color: #dc3545;
    }

    .status-value.warning {
        color: #ffc107;
    }

    /* Tooltip for collapsed sidebar */
    .sidebar-collapsed .admin-nav-link::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: var(--green-dark);
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        pointer-events: none;
        z-index: 1001;
        box-shadow: var(--shadow-md);
    }

    .sidebar-collapsed .admin-nav-link:hover::after {
        opacity: 1;
        visibility: visible;
        left: calc(100% + 15px);
    }

    /* Scrollbar styling */
    .admin-nav::-webkit-scrollbar {
        width: 4px;
    }

    .admin-nav::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
    }

    .admin-nav::-webkit-scrollbar-thumb {
        background: rgba(25, 135, 84, 0.3);
        border-radius: 10px;
    }

    .admin-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(25, 135, 84, 0.5);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .admin-sidebar {
            transform: translateX(-100%);
            width: 280px;
            box-shadow: var(--shadow-lg);
        }

        .admin-sidebar.show {
            transform: translateX(0);
        }

        .admin-stats {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr);
        }

        .admin-nav-text {
            display: block !important;
        }

        .quick-action-btn span {
            display: inline !important;
        }

        .system-status {
            display: block !important;
        }
    }

    /* Animation for sidebar collapse */
    @keyframes adminSlideIn {
        from {
            transform: translateX(-100%);
        }
        to {
            transform: translateX(0);
        }
    }

    @keyframes adminSlideOut {
        from {
            transform: translateX(0);
        }
        to {
            transform: translateX(-100%);
        }
    }

    .admin-sidebar {
        background: #fff;
        border-right: 1px solid #e8ecef;
        box-shadow: 2px 0 12px rgba(15, 23, 42, 0.05);
    }

    .admin-sidebar-header {
        min-height: 0;
        padding: 8px 12px;
        border-bottom: 1px solid #eef0f2;
    }

    .admin-sidebar-header .admin-stats,
    .admin-quick-actions {
        display: none !important;
    }

    .admin-nav {
        padding: 8px 0;
    }

    .admin-nav-section {
        margin-bottom: 8px;
    }

    .admin-nav-label {
        padding: 12px 16px 6px;
        margin: 0;
        border: 0;
        background: transparent;
        color: #84909b;
        font-size: 0.66rem;
        letter-spacing: 0.08em;
    }

    .admin-nav-link {
        margin: 2px 8px;
        min-height: 40px;
        padding: 9px 10px;
        border: 0;
        border-radius: 6px;
        color: #475569;
    }

    .admin-nav-link:hover {
        padding-left: 10px;
        background: #f4f7f5;
    }

    .admin-nav-link.active {
        background: #eaf4ee;
        color: var(--primary-green);
    }

    .admin-nav-icon {
        width: 20px;
        margin-right: 10px;
        font-size: 0.95rem;
    }

    .admin-submenu {
        margin-left: 18px;
        border-left: 1px solid #e8ecef;
        border-radius: 0;
        background: transparent;
    }

    .admin-submenu-link {
        padding: 8px 10px 8px 18px;
        font-size: 0.84rem;
    }

    .admin-submenu-link:hover,
    .admin-submenu-link.active {
        padding-left: 18px;
        background: #f4f7f5;
    }
</style>

<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Admin Sidebar Header -->
    <div class="admin-sidebar-header">
        <div class="d-flex align-items-center justify-content-center mb-3">
            <div class="admin-avatar bg-primary bg-opacity-10 rounded-circle p-3">
                <i class="fas fa-user-shield fa-2x text-primary"></i>
            </div>
        </div>
        <div class="admin-stats">
            <div class="admin-stat" onclick="window.location.href='users.php'" title="View Users">
                <div class="admin-stat-value"><?php echo $admin_stats['total_users']; ?></div>
                <div class="admin-stat-label">Users</div>
            </div>
            <div class="admin-stat" onclick="window.location.href='reports.php?type=revenue'" title="View Revenue">
                <div class="admin-stat-value">$<?php echo number_format($admin_stats['today_revenue'], 0); ?></div>
                <div class="admin-stat-label">Revenue</div>
            </div>
            <div class="admin-stat" title="System Alerts">
                <div class="admin-stat-value"><?php echo $admin_stats['system_alerts']; ?></div>
                <div class="admin-stat-label">Alerts</div>
            </div>
            <div class="admin-stat" title="System Status">
                <div class="admin-stat-value"><i class="fas fa-check-circle text-success"></i></div>
                <div class="admin-stat-label">System</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="admin-nav">
        <!-- Dashboard Section -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">Overview</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item">
                    <a href="dashboard.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       data-tooltip="Dashboard">
                        <i class="admin-nav-icon fas fa-tachometer-alt"></i>
                        <span class="admin-nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="analytics.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" 
                       data-tooltip="Analytics">
                        <i class="admin-nav-icon fas fa-chart-line"></i>
                        <span class="admin-nav-text">Analytics</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="reports.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                       data-tooltip="Reports">
                        <i class="admin-nav-icon fas fa-file-alt"></i>
                        <span class="admin-nav-text">Reports</span>
                        <span class="admin-nav-badge">5</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Management -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">People & Access</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item has-dropdown">
                    <a href="#" class="admin-nav-link" data-tooltip="Users">
                        <i class="admin-nav-icon fas fa-users"></i>
                        <span class="admin-nav-text">Users</span>
                        <i class="fas fa-chevron-down admin-dropdown-arrow"></i>
                        <span class="admin-nav-badge"><?php echo $admin_stats['total_users']; ?></span>
                    </a>
                    <ul class="admin-submenu">
                        <li class="admin-submenu-item">
                            <a href="users.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> All Users
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="users.php?action=add" class="admin-submenu-link">
                                <i class="fas fa-user-plus"></i> Add User
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="users.php?filter=active" class="admin-submenu-link">
                                <i class="fas fa-user-check"></i> Active Users
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="users.php?filter=inactive" class="admin-submenu-link">
                                <i class="fas fa-user-clock"></i> Inactive Users
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="admin-nav-item">
                    <a href="attendance.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>" 
                       data-tooltip="Attendance">
                        <i class="admin-nav-icon fas fa-user-clock"></i>
                        <span class="admin-nav-text">Attendance</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="audit_logs.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>" 
                       data-tooltip="Audit Logs">
                        <i class="admin-nav-icon fas fa-history"></i>
                        <span class="admin-nav-text">Audit Logs</span>
                        <span class="admin-nav-badge warning">24</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Branch Management -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">Branches & Inventory</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item has-dropdown">
                    <a href="#" class="admin-nav-link" data-tooltip="Branches">
                        <i class="admin-nav-icon fas fa-building"></i>
                        <span class="admin-nav-text">Branches</span>
                        <i class="fas fa-chevron-down admin-dropdown-arrow"></i>
                    </a>
                    <ul class="admin-submenu">
                        <li class="admin-submenu-item">
                            <a href="branches.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> All Branches
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="branches.php?action=add" class="admin-submenu-link">
                                <i class="fas fa-plus"></i> Add Branch
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="branch_performance.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'branch_performance.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar"></i> Performance
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="branch_settings.php" class="admin-submenu-link">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="admin-nav-item has-dropdown">
                    <a href="#" class="admin-nav-link" data-tooltip="Inventory">
                        <i class="admin-nav-icon fas fa-warehouse"></i>
                        <span class="admin-nav-text">Inventory</span>
                        <i class="fas fa-chevron-down admin-dropdown-arrow"></i>
                        <span class="admin-nav-badge danger"><?php echo $admin_stats['system_alerts']; ?></span>
                    </a>
                    <ul class="admin-submenu">
                        <li class="admin-submenu-item">
                            <a href="tanks.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'tanks.php' ? 'active' : ''; ?>">
                                <i class="fas fa-oil-can"></i> Tanks
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="pumps.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'pumps.php' ? 'active' : ''; ?>">
                                <i class="fas fa-pump"></i> Pumps
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="fuel_types.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'fuel_types.php' ? 'active' : ''; ?>">
                                <i class="fas fa-gas-pump"></i> Fuel Types
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="inventory_reports.php" class="admin-submenu-link">
                                <i class="fas fa-chart-pie"></i> Inventory Reports
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Financial Management -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">Finance & Operations</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item">
                    <a href="sales.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>" 
                       data-tooltip="Sales">
                        <i class="admin-nav-icon fas fa-cash-register"></i>
                        <span class="admin-nav-text">Sales</span>
                        <span class="admin-nav-badge info">12</span>
                    </a>
                </li>
                <li class="admin-nav-item has-dropdown">
                    <a href="#" class="admin-nav-link" data-tooltip="Expenses">
                        <i class="admin-nav-icon fas fa-file-invoice-dollar"></i>
                        <span class="admin-nav-text">Expenses</span>
                        <i class="fas fa-chevron-down admin-dropdown-arrow"></i>
                    </a>
                    <ul class="admin-submenu">
                        <li class="admin-submenu-item">
                            <a href="expenses.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> All Expenses
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="expenses.php?status=pending" class="admin-submenu-link">
                                <i class="fas fa-clock"></i> Pending Approval
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="expenses.php?action=add" class="admin-submenu-link">
                                <i class="fas fa-plus"></i> Add Expense
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="expense_categories.php" class="admin-submenu-link">
                                <i class="fas fa-folder"></i> Categories
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="admin-nav-item">
                    <a href="transactions.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" 
                       data-tooltip="Transactions">
                        <i class="admin-nav-icon fas fa-exchange-alt"></i>
                        <span class="admin-nav-text">Transactions</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="invoices.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>" 
                       data-tooltip="Invoices">
                        <i class="admin-nav-icon fas fa-receipt"></i>
                        <span class="admin-nav-text">Invoices</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- System Administration -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">System</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item has-dropdown">
                    <a href="#" class="admin-nav-link" data-tooltip="Settings">
                        <i class="admin-nav-icon fas fa-cogs"></i>
                        <span class="admin-nav-text">System Settings</span>
                        <i class="fas fa-chevron-down admin-dropdown-arrow"></i>
                    </a>
                    <ul class="admin-submenu">
                        <li class="admin-submenu-item">
                            <a href="settings.php" class="admin-submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                                <i class="fas fa-sliders-h"></i> General Settings
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="email_settings.php" class="admin-submenu-link">
                                <i class="fas fa-envelope"></i> Email Settings
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="sms_settings.php" class="admin-submenu-link">
                                <i class="fas fa-sms"></i> SMS Settings
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="payment_settings.php" class="admin-submenu-link">
                                <i class="fas fa-credit-card"></i> Payment Settings
                            </a>
                        </li>
                        <li class="admin-submenu-item">
                            <a href="backup_restore.php" class="admin-submenu-link">
                                <i class="fas fa-database"></i> Backup & Restore
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="admin-nav-item">
                    <a href="api_management.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'api_management.php' ? 'active' : ''; ?>" 
                       data-tooltip="API">
                        <i class="admin-nav-icon fas fa-code"></i>
                        <span class="admin-nav-text">API Management</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="logs.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" 
                       data-tooltip="System Logs">
                        <i class="admin-nav-icon fas fa-terminal"></i>
                        <span class="admin-nav-text">System Logs</span>
                        <span class="admin-nav-badge warning">120</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="notifications.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" 
                       data-tooltip="Notifications">
                        <i class="admin-nav-icon fas fa-bell"></i>
                        <span class="admin-nav-text">Notifications</span>
                        <span class="admin-nav-badge danger"><?php echo $admin_stats['system_alerts']; ?></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Tools & Utilities -->
        <div class="admin-nav-section">
            <div class="admin-nav-label">Utilities</div>
            <ul class="admin-nav-items">
                <li class="admin-nav-item">
                    <a href="import_export.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'import_export.php' ? 'active' : ''; ?>" 
                       data-tooltip="Import/Export">
                        <i class="admin-nav-icon fas fa-file-export"></i>
                        <span class="admin-nav-text">Import/Export</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="maintenance.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>" 
                       data-tooltip="Maintenance">
                        <i class="admin-nav-icon fas fa-tools"></i>
                        <span class="admin-nav-text">Maintenance</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="help_docs.php" 
                       class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'help_docs.php' ? 'active' : ''; ?>" 
                       data-tooltip="Help">
                        <i class="admin-nav-icon fas fa-question-circle"></i>
                        <span class="admin-nav-text">Help & Documentation</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Quick Admin Actions -->
    <div class="admin-quick-actions">
        <div class="quick-action-grid">
            <button class="quick-action-btn" onclick="window.location.href='users.php?action=add'" title="Add New User">
                <i class="fas fa-user-plus"></i>
                <span>Add User</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='branches.php?action=add'" title="Add New Branch">
                <i class="fas fa-building"></i>
                <span>Add Branch</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='reports.php?generate=quick'" title="Generate Quick Report">
                <i class="fas fa-chart-pie"></i>
                <span>Quick Report</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='backup_restore.php'" title="Backup System">
                <i class="fas fa-database"></i>
                <span>Backup</span>
            </button>
        </div>
        
        <!-- System Status -->
        <div class="system-status">
            <div class="status-item">
                <span class="status-label">System:</span>
                <span class="status-value online">Online</span>
            </div>
            <div class="status-item">
                <span class="status-label">Database:</span>
                <span class="status-value online">Connected</span>
            </div>
            <div class="status-item">
                <span class="status-label">Last Backup:</span>
                <span class="status-value warning">24h ago</span>
            </div>
        </div>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const adminSidebar = document.getElementById('adminSidebar');
        const adminNavItems = document.querySelectorAll('.admin-nav-item.has-dropdown');
        const currentPage = window.location.pathname.split('/').pop();
        
        // Initialize dropdown functionality
        adminNavItems.forEach(item => {
            const toggle = item.querySelector('.admin-nav-link');
            
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth > 768) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isActive = item.classList.contains('active');
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.admin-nav-item.has-dropdown.active').forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current dropdown
                    if (!isActive) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth > 768) {
                const isDropdownClick = e.target.closest('.admin-nav-item.has-dropdown');
                if (!isDropdownClick) {
                    document.querySelectorAll('.admin-nav-item.has-dropdown.active').forEach(item => {
                        item.classList.remove('active');
                    });
                }
            }
        });
        
        // Set active menu item based on current page
        function setActiveMenuItem() {
            // Remove all active classes
            document.querySelectorAll('.admin-nav-link.active, .admin-submenu-link.active').forEach(item => {
                item.classList.remove('active');
            });
            
            // Find and set active link
            const currentLink = document.querySelector(`.admin-nav-link[href*="${currentPage}"], .admin-submenu-link[href*="${currentPage}"]`);
            if (currentLink) {
                currentLink.classList.add('active');
                
                // Open parent dropdown if it's a submenu link
                const dropdownParent = currentLink.closest('.admin-nav-item.has-dropdown');
                if (dropdownParent) {
                    dropdownParent.classList.add('active');
                }
            }
        }
        
        setActiveMenuItem();
        
        // Update sidebar stats periodically
        function updateAdminStats() {
            fetch('../api/get_admin_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update user count
                        const userStat = document.querySelector('.admin-stat:nth-child(1) .admin-stat-value');
                        const userBadge = document.querySelector('.admin-nav-item.has-dropdown .admin-nav-badge');
                        if (userStat) userStat.textContent = data.total_users;
                        if (userBadge) userBadge.textContent = data.total_users;
                        
                        // Update revenue
                        const revenueStat = document.querySelector('.admin-stat:nth-child(2) .admin-stat-value');
                        if (revenueStat) revenueStat.textContent = '$' + parseFloat(data.today_revenue).toLocaleString();
                        
                        // Update alerts
                        const alertStat = document.querySelector('.admin-stat:nth-child(3) .admin-stat-value');
                        const alertBadges = document.querySelectorAll('.admin-nav-badge.danger, .admin-nav-badge.warning');
                        if (alertStat) alertStat.textContent = data.system_alerts;
                        alertBadges.forEach(badge => {
                            if (badge.textContent === '<?php echo $admin_stats["system_alerts"]; ?>') {
                                badge.textContent = data.system_alerts;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating admin stats:', error);
                });
        }
        
        // Update stats every 2 minutes
        setInterval(updateAdminStats, 120000);
        
        // Handle mobile sidebar toggle
        if (window.innerWidth <= 768) {
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                adminSidebar.classList.toggle('show');
            });
        }
        
        // Add tooltips for collapsed sidebar
        const adminNavLinks = document.querySelectorAll('.sidebar-collapsed .admin-nav-link');
        adminNavLinks.forEach(link => {
            const text = link.querySelector('.admin-nav-text')?.textContent || link.getAttribute('data-tooltip');
            link.setAttribute('data-tooltip', text);
        });
        
        // Quick action buttons functionality
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const action = this.getAttribute('onclick');
                if (action) {
                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Loading...</span>';
                    this.disabled = true;
                    
                    // Restore button after 2 seconds
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }, 2000);
                }
            });
        });
        
        // Keyboard shortcuts for admin
        document.addEventListener('keydown', function(e) {
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Alt + U for users
            if (e.altKey && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'users.php';
            }
            
            // Alt + R for reports
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports.php';
            }
            
            // Alt + S for settings
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'settings.php';
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                document.querySelectorAll('.admin-nav-item.has-dropdown.active').forEach(item => {
                    item.classList.remove('active');
                });
            }
        });
        
        // Initialize system status check
        function checkSystemStatus() {
            fetch('../api/check_system_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const systemStatus = document.querySelector('.status-item:nth-child(1) .status-value');
                        const dbStatus = document.querySelector('.status-item:nth-child(2) .status-value');
                        const backupStatus = document.querySelector('.status-item:nth-child(3) .status-value');
                        
                        if (systemStatus) {
                            systemStatus.className = 'status-value ' + (data.system_online ? 'online' : 'offline');
                            systemStatus.textContent = data.system_online ? 'Online' : 'Offline';
                        }
                        
                        if (dbStatus) {
                            dbStatus.className = 'status-value ' + (data.database_connected ? 'online' : 'offline');
                            dbStatus.textContent = data.database_connected ? 'Connected' : 'Disconnected';
                        }
                        
                        if (backupStatus) {
                            const hoursAgo = data.last_backup_hours;
                            backupStatus.className = 'status-value ' + 
                                (hoursAgo <= 24 ? 'online' : 
                                 hoursAgo <= 72 ? 'warning' : 'offline');
                            backupStatus.textContent = hoursAgo <= 24 ? 
                                hoursAgo + 'h ago' : 
                                Math.floor(hoursAgo / 24) + 'd ago';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking system status:', error);
                });
        }
        
        // Check system status every 5 minutes
        setInterval(checkSystemStatus, 300000);
        
        // Initial checks
        updateAdminStats();
        checkSystemStatus();
    });
</script>