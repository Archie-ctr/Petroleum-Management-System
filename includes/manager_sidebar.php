<?php
// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Manager Sidebar Styles - Simplified */
    .manager-sidebar {
        position: fixed;
        top: var(--topbar-height);
        left: 0;
        height: calc(100vh - var(--topbar-height));
        width: var(--sidebar-width);
        background: white;
        z-index: 1020;
        transition: all var(--transition-speed) ease;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        border-right: 1px solid #e5e7eb;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    
    /* Sidebar Header */
    .manager-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        text-align: center;
        background: linear-gradient(135deg, var(--manager-primary) 0%, var(--manager-dark) 100%);
        color: white;
    }
    
    .manager-branch-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    .manager-branch-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .manager-branch-name {
        font-weight: 700;
        font-size: 1.1rem;
        color: white;
        text-align: center;
    }
    
    .manager-branch-location {
        font-size: 0.8rem;
        opacity: 0.9;
        text-align: center;
    }
    
    /* Sidebar Navigation - Clean & Simple */
    .manager-sidebar-nav {
        padding: 20px 0;
        flex: 1;
    }
    
    .manager-nav-section {
        margin-bottom: 25px;
    }
    
    .manager-nav-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        padding: 0 20px 8px;
        margin-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .manager-nav-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .manager-nav-item {
        margin-bottom: 5px;
    }
    
    .manager-nav-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: #374151;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        position: relative;
    }
    
    .manager-nav-link:hover {
        background: #f3f4f6;
        color: var(--manager-primary);
        padding-left: 24px;
    }
    
    .manager-nav-link.active {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
        color: var(--manager-primary);
        border-left-color: var(--manager-primary);
        font-weight: 600;
    }
    
    .manager-nav-icon {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        margin-right: 12px;
        color: inherit;
    }
    
    .manager-nav-text {
        flex: 1;
        font-size: 0.95rem;
    }
    
    .manager-nav-badge {
        background: var(--manager-primary);
        color: white;
        font-size: 0.7rem;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 600;
    }
    
    /* Sidebar Footer */
    .manager-sidebar-footer {
        padding: 20px;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }
    
    .manager-quick-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .manager-action-btn {
        background: linear-gradient(135deg, var(--manager-primary) 0%, var(--manager-dark) 100%);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
    }
    
    .manager-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
    }
    
    .manager-action-btn i {
        font-size: 1rem;
    }
    
    .manager-system-status {
        font-size: 0.8rem;
        color: #6b7280;
        text-align: center;
        padding: 10px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }
    
    .manager-status-online {
        color: var(--manager-primary);
        font-weight: 600;
    }
    
    /* Mobile Sidebar */
    .manager-mobile-sidebar {
        position: fixed;
        top: var(--topbar-height);
        left: -280px;
        width: 280px;
        height: calc(100vh - var(--topbar-height));
        background: white;
        z-index: 1050;
        transition: left 0.3s ease;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
    }
    
    .manager-mobile-sidebar.show {
        left: 0;
    }
    
    .manager-mobile-overlay {
        display: none;
        position: fixed;
        top: var(--topbar-height);
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        backdrop-filter: blur(2px);
    }
    
    .manager-mobile-overlay.show {
        display: block;
    }
    
    /* Responsive Styles */
    @media (max-width: 768px) {
        .manager-sidebar {
            display: none;
        }
        
        .manager-content-wrapper {
            margin-left: 0 !important;
        }
    }
    
    @media (min-width: 769px) {
        .manager-mobile-sidebar,
        .manager-mobile-overlay {
            display: none !important;
        }
    }

    .manager-sidebar,
    .manager-mobile-sidebar {
        background: #fff;
        border-right: 1px solid #e8ecef;
        box-shadow: 2px 0 12px rgba(15, 23, 42, 0.05);
    }

    .manager-sidebar-header {
        padding: 14px 16px;
        text-align: left;
        background: #fff;
        color: #334155;
        border-bottom: 1px solid #eef0f2;
    }

    .manager-branch-info {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .manager-branch-icon {
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 8px;
        background: #eaf4ee;
        color: var(--manager-primary);
        font-size: 1rem;
        flex: 0 0 36px;
    }

    .manager-branch-name {
        color: #334155;
        text-align: left;
        font-size: 0.9rem;
    }

    .manager-branch-location {
        color: #84909b;
        text-align: left;
        font-size: 0.74rem;
    }

    .manager-sidebar-nav {
        padding: 8px 0;
    }

    .manager-nav-section {
        margin-bottom: 8px;
    }

    .manager-nav-label {
        padding: 14px 16px 6px;
        margin: 0 0 2px;
        border: 0;
        color: #84909b;
        font-size: 0.66rem;
        letter-spacing: 0.08em;
    }

    .manager-nav-item {
        margin: 2px 8px;
    }

    .manager-nav-link {
        min-height: 40px;
        padding: 9px 10px;
        border: 0;
        border-radius: 6px;
        color: #475569;
        font-size: 0.88rem;
    }

    .manager-nav-link:hover {
        padding-left: 10px;
        background: #f4f7f5;
    }

    .manager-nav-link.active {
        background: #eaf4ee;
        border: 0;
        color: var(--manager-primary);
    }

    .manager-nav-icon {
        width: 20px;
        margin-right: 10px;
        font-size: 0.95rem;
    }

    .manager-sidebar-footer {
        display: none;
    }

    @media (max-width: 768px) {
        .manager-mobile-sidebar {
            top: var(--topbar-height);
            height: calc(100vh - var(--topbar-height));
        }

        .manager-mobile-overlay {
            top: var(--topbar-height);
            backdrop-filter: none;
        }
    }
</style>

<!-- Desktop Sidebar -->
<aside class="manager-sidebar" id="managerSidebar">
    <!-- Sidebar Header -->
    <div class="manager-sidebar-header">
        <div class="manager-branch-info">
            <div class="manager-branch-icon">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div class="manager-branch-name"><?php echo $branch['branch_name'] ?? 'Branch'; ?></div>
                <div class="manager-branch-location"><?php echo $branch['location'] ?? 'Location'; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Navigation Menu - Only Essential Items -->
    <nav class="manager-sidebar-nav">
        <!-- Dashboard -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Overview</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="dashboard.php" class="manager-nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-tachometer-alt"></i>
                        <span class="manager-nav-text">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Core Operations -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Branch Operations</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="sales.php" class="manager-nav-link <?php echo $current_page == 'sales.php' && ($_GET['action'] ?? '') !== 'new' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-shopping-cart"></i>
                        <span class="manager-nav-text">Sales</span>
                        <?php if ($quick_stats['today_transactions'] > 0): ?>
                            <span class="manager-nav-badge"><?php echo $quick_stats['today_transactions']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="expenses.php" class="manager-nav-link <?php echo $current_page == 'expenses.php' && ($_GET['action'] ?? '') !== 'add' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-file-invoice-dollar"></i>
                        <span class="manager-nav-text">Expenses</span>
                        <?php if ($quick_stats['pending_expenses'] > 0): ?>
                            <span class="manager-nav-badge" style="background: #f59e0b;"><?php echo $quick_stats['pending_expenses']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="staff.php" class="manager-nav-link <?php echo $current_page == 'staff.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-users"></i>
                        <span class="manager-nav-text">Branch Staff</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="manager-nav-section">
            <div class="manager-nav-label">Inventory & Equipment</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="dashboard.php#fuel-inventory" class="manager-nav-link" data-dashboard-section="fuel-inventory">
                        <i class="manager-nav-icon fas fa-gas-pump"></i>
                        <span class="manager-nav-text">Fuel Inventory</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="dashboard.php#equipment-alerts" class="manager-nav-link" data-dashboard-section="equipment-alerts">
                        <i class="manager-nav-icon fas fa-tools"></i>
                        <span class="manager-nav-text">Equipment Alerts</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Reports -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Performance</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="reports.php" class="manager-nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-chart-bar"></i>
                        <span class="manager-nav-text">Reports</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="manager-nav-section">
            <div class="manager-nav-label">Quick Actions</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="sales.php?action=new" class="manager-nav-link <?php echo $current_page == 'sales.php' && ($_GET['action'] ?? '') === 'new' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-plus-circle"></i>
                        <span class="manager-nav-text">New Sale</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="expenses.php?action=add" class="manager-nav-link <?php echo $current_page == 'expenses.php' && ($_GET['action'] ?? '') === 'add' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-file-invoice-dollar"></i>
                        <span class="manager-nav-text">Add Expense</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="../public/logout.php" class="manager-nav-link text-danger">
                        <i class="manager-nav-icon fas fa-sign-out-alt"></i>
                        <span class="manager-nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="manager-sidebar-footer">
        <div class="manager-quick-actions">
            <button class="manager-action-btn" onclick="window.location.href='sales.php?action=new'">
                <i class="fas fa-shopping-cart"></i>
                <span>New Sale</span>
            </button>
            <button class="manager-action-btn" onclick="window.location.href='expenses.php?action=add'">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Add Expense</span>
            </button>
        </div>
        <div class="manager-system-status">
            <i class="fas fa-circle text-success me-1"></i>
            <span class="manager-status-online">System Online</span>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar -->
<aside class="manager-mobile-sidebar" id="mobileManagerSidebar">
    <!-- Same content as desktop sidebar -->
    <div class="manager-sidebar-header">
        <div class="manager-branch-info">
            <div class="manager-branch-icon">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div class="manager-branch-name"><?php echo $branch['branch_name'] ?? 'Branch'; ?></div>
                <div class="manager-branch-location"><?php echo $branch['location'] ?? 'Location'; ?></div>
            </div>
        </div>
    </div>
    
    <nav class="manager-sidebar-nav">
        <!-- Dashboard -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Overview</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="dashboard.php" class="manager-nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-tachometer-alt"></i>
                        <span class="manager-nav-text">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Core Operations -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Branch Operations</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="sales.php" class="manager-nav-link <?php echo $current_page == 'sales.php' && ($_GET['action'] ?? '') !== 'new' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-shopping-cart"></i>
                        <span class="manager-nav-text">Sales</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="expenses.php" class="manager-nav-link <?php echo $current_page == 'expenses.php' && ($_GET['action'] ?? '') !== 'add' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-file-invoice-dollar"></i>
                        <span class="manager-nav-text">Expenses</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="staff.php" class="manager-nav-link <?php echo $current_page == 'staff.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-users"></i>
                        <span class="manager-nav-text">Branch Staff</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="manager-nav-section">
            <div class="manager-nav-label">Inventory & Equipment</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="dashboard.php#fuel-inventory" class="manager-nav-link" data-dashboard-section="fuel-inventory">
                        <i class="manager-nav-icon fas fa-gas-pump"></i>
                        <span class="manager-nav-text">Fuel Inventory</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="dashboard.php#equipment-alerts" class="manager-nav-link" data-dashboard-section="equipment-alerts">
                        <i class="manager-nav-icon fas fa-tools"></i>
                        <span class="manager-nav-text">Equipment Alerts</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Reports -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Performance</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="reports.php" class="manager-nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-chart-bar"></i>
                        <span class="manager-nav-text">Reports</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Quick Actions -->
        <div class="manager-nav-section">
            <div class="manager-nav-label">Quick Actions & Account</div>
            <ul class="manager-nav-items">
                <li class="manager-nav-item">
                    <a href="sales.php?action=new" class="manager-nav-link <?php echo $current_page == 'sales.php' && ($_GET['action'] ?? '') === 'new' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-plus-circle"></i>
                        <span class="manager-nav-text">New Sale</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="expenses.php?action=add" class="manager-nav-link <?php echo $current_page == 'expenses.php' && ($_GET['action'] ?? '') === 'add' ? 'active' : ''; ?>">
                        <i class="manager-nav-icon fas fa-file-invoice-dollar"></i>
                        <span class="manager-nav-text">Add Expense</span>
                    </a>
                </li>
                <li class="manager-nav-item">
                    <a href="../public/logout.php" class="manager-nav-link text-danger">
                        <i class="manager-nav-icon fas fa-sign-out-alt"></i>
                        <span class="manager-nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>

    </nav>
</aside>

<!-- Mobile Overlay -->
<div class="manager-mobile-overlay" id="mobileOverlay"></div>

<script>
$(document).ready(function() {
    const dashboardSection = window.location.hash.slice(1);
    if (dashboardSection) {
        const sectionLink = $('.manager-nav-link[data-dashboard-section]').filter(function() {
            return this.dataset.dashboardSection === dashboardSection;
        });
        if (sectionLink.length) {
            $('.manager-nav-link.active').removeClass('active');
            sectionLink.addClass('active');
        }
    }

    // Mobile menu toggle
    $('#mobileMenuToggle').click(function() {
        $('#mobileManagerSidebar').addClass('show');
        $('#mobileOverlay').addClass('show');
        $('body').css('overflow', 'hidden');
    });
    
    // Close mobile menu
    $('#mobileOverlay').click(function() {
        $('#mobileManagerSidebar').removeClass('show');
        $('#mobileOverlay').removeClass('show');
        $('body').css('overflow', 'auto');
    });
    
    // Close mobile menu when clicking on a link
    $('.manager-mobile-sidebar .manager-nav-link').click(function() {
        $('#mobileManagerSidebar').removeClass('show');
        $('#mobileOverlay').removeClass('show');
        $('body').css('overflow', 'auto');
    });
    
    // User dropdown toggle
    $('#userDropdownToggle').click(function(e) {
        e.stopPropagation();
        $('#userDropdownMenu').toggleClass('show');
    });
    
    // Close user dropdown when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('.manager-user-profile').length) {
            $('#userDropdownMenu').removeClass('show');
        }
    });
    
    // Toggle quick stats
    let quickStatsVisible = true;
    $('#toggleQuickStats').click(function() {
        quickStatsVisible = !quickStatsVisible;
        if (quickStatsVisible) {
            $('#quickStatsBar').slideDown();
        } else {
            $('#quickStatsBar').slideUp();
        }
    });
    
    // Update stats periodically
    function updateManagerStats() {
        $.ajax({
            url: '../api/get_manager_stats.php',
            method: 'GET',
            data: { branch_id: <?php echo $branch_id; ?> },
            success: function(data) {
                if (data.success) {
                    // Update quick stats bar
                    $('.manager-stat:nth-child(1) .manager-stat-value').html(
                        '$' + parseFloat(data.today_sales).toFixed(2) +
                        '<span class="manager-stat-trend trend-' + (data.sales_growth >= 0 ? 'up' : 'down') + '">' +
                        '<i class="fas fa-arrow-' + (data.sales_growth >= 0 ? 'up' : 'down') + '"></i>' +
                        Math.abs(data.sales_growth) + '%</span>'
                    );
                    
                    $('.manager-stat:nth-child(2) .manager-stat-value').text(data.today_transactions);
                    $('.manager-stat:nth-child(3) .manager-stat-value').text(data.pending_expenses);
                    $('.manager-stat:nth-child(4) .manager-stat-value').text(data.low_stock + data.pending_expenses);
                    
                    // Update notification badge
                    const totalNotifications = parseInt(data.low_stock) + parseInt(data.pending_expenses);
                    if (totalNotifications > 0) {
                        $('#notificationsToggle .manager-notification-badge').text(totalNotifications).show();
                    } else {
                        $('#notificationsToggle .manager-notification-badge').hide();
                    }
                }
            },
            error: function() {
                console.log('Failed to update manager stats');
            }
        });
    }
    
    // Update stats every 30 seconds
    setInterval(updateManagerStats, 30000);
    
    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() > 768) {
            // Close mobile sidebar on resize to desktop
            $('#mobileManagerSidebar').removeClass('show');
            $('#mobileOverlay').removeClass('show');
            $('body').css('overflow', 'auto');
        }
    });
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Alt + S for new sale
        if (e.altKey && e.key === 's') {
            e.preventDefault();
            window.location.href = 'sales.php?action=new';
        }
        
        // Alt + E for add expense
        if (e.altKey && e.key === 'e') {
            e.preventDefault();
            window.location.href = 'expenses.php?action=add';
        }
        
        // Alt + D for dashboard
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'dashboard.php';
        }
        
        // Alt + R for reports
        if (e.altKey && e.key === 'r') {
            e.preventDefault();
            window.location.href = 'reports.php';
        }
        
        // Escape to close dropdowns
        if (e.key === 'Escape') {
            $('#userDropdownMenu').removeClass('show');
        }
    });
    
    // Initialize with current stats
    updateManagerStats();
});
</script>