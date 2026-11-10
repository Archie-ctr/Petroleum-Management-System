<?php
// attendant_sidebar.php
// Get branch info for sidebar
if (isset($_SESSION['branch_id'])) {
    $branch_id = $_SESSION['branch_id'];
    $branch_sql = "SELECT branch_name, branch_code FROM branches WHERE id = $branch_id";
    $branch_result = $conn->query($branch_sql);
    $branch = $branch_result->fetch_assoc();
}

// Get current tank levels for attendant's branch
$tanks_sql = "SELECT t.*, f.fuel_name, 
              ROUND((t.current_quantity / t.capacity) * 100, 2) as percentage
              FROM tanks t
              JOIN fuel_types f ON t.fuel_type_id = f.id
              WHERE t.branch_id = $branch_id 
              AND t.status = 'active'
              ORDER BY t.current_quantity ASC";
$tanks_result = $conn->query($tanks_sql);
?>
<style>
    /* Attendant Sidebar Styles - RESPONSIVE */
    .attendant-sidebar {
        position: fixed;
        top: var(--topbar-height);
        left: 0;
        height: calc(100vh - var(--topbar-height));
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        z-index: 1000;
        transition: all var(--transition-speed) ease;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        border-right: 1px solid rgba(0, 0, 0, 0.05);
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }

    @media (max-width: 767px) {
        .attendant-sidebar {
            transform: translateX(-100%);
            width: 280px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            top: var(--topbar-height);
            height: calc(100vh - var(--topbar-height));
        }

        .attendant-sidebar.show {
            transform: translateX(0);
        }
    }

    /* Sidebar Header */
    .sidebar-header {
        padding: 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        text-align: center;
        background: white;
    }

    .branch-info {
        text-align: center;
        margin-bottom: 15px;
    }

    .branch-name {
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--primary-green);
        margin-bottom: 5px;
    }

    .branch-code {
        font-size: 0.75rem;
        color: #666;
        background: rgba(25, 135, 84, 0.1);
        padding: 3px 10px;
        border-radius: 15px;
        display: inline-block;
    }

    /* Tank Status */
    .tank-status {
        margin: 15px;
        padding: 12px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 10px;
    }

    .tank-status-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .tank-item {
        margin-bottom: 10px;
    }

    .tank-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
        font-size: 0.8rem;
    }

    .tank-name {
        font-weight: 600;
        color: #444;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tank-percentage {
        font-weight: 700;
        font-size: 0.9rem;
    }

    .tank-percentage.low {
        color: #dc3545;
    }

    .tank-percentage.medium {
        color: #ffc107;
    }

    .tank-percentage.high {
        color: #198754;
    }

    .progress {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar {
        border-radius: 3px;
    }

    /* Navigation Menu */
    .sidebar-nav {
        padding: 15px 0;
        flex: 1;
        overflow-y: auto;
    }

    .nav-section {
        margin-bottom: 15px;
    }

    .nav-section-title {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        padding: 10px 15px;
        margin-bottom: 5px;
        background: rgba(0, 0, 0, 0.02);
    }

    .nav-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .nav-link:hover {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.05), transparent);
        color: var(--primary-green);
        padding-left: 20px;
    }

    .nav-link.active {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
        color: var(--primary-green);
        border-left-color: var(--primary-green);
        font-weight: 600;
    }

    .nav-icon {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        margin-right: 12px;
        color: #666;
        transition: all 0.3s ease;
    }

    .nav-link:hover .nav-icon,
    .nav-link.active .nav-icon {
        color: var(--primary-green);
        transform: scale(1.1);
    }

    .nav-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    .nav-badge {
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

    .nav-badge.danger {
        background: #dc3545;
    }

    .nav-badge.warning {
        background: #ffc107;
        color: #000;
    }

    /* Quick Actions */
    .sidebar-quick-actions {
        padding: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background: white;
    }

    .quick-action-btn {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--green-dark) 100%);
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
        width: 100%;
        margin-bottom: 8px;
    }

    .quick-action-btn:last-child {
        margin-bottom: 0;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
    }

    .quick-action-btn i {
        font-size: 1rem;
    }

    /* Shift Info */
    .shift-info {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
    }

    .shift-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
        font-size: 0.8rem;
    }

    .shift-item:last-child {
        margin-bottom: 0;
    }

    .shift-label {
        color: #666;
    }

    .shift-value {
        font-weight: 600;
    }

    .shift-value.on-shift {
        color: var(--primary-green);
    }

    .shift-value.off-shift {
        color: #6c757d;
    }

    /* Scrollbar styling */
    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(25, 135, 84, 0.3);
        border-radius: 10px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(25, 135, 84, 0.5);
    }

    /* Mobile overlay when sidebar is open */
    @media (max-width: 767px) {
        body.menu-open::before {
            content: '';
            position: fixed;
            top: var(--topbar-height);
            left: 280px;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(3px);
        }
    }
</style>

<aside class="attendant-sidebar" id="attendantSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="branch-info">
            <div class="branch-name"><?php echo $branch['branch_name'] ?? 'Branch'; ?></div>
            <div class="branch-code"><?php echo $branch['branch_code'] ?? 'BR-001'; ?></div>
        </div>
    </div>

    <!-- Tank Status -->
    <div class="tank-status">
        <div class="tank-status-title">
            <span>Tank Levels</span>
            <span><i class="fas fa-oil-can"></i></span>
        </div>
        <?php if ($tanks_result->num_rows > 0): ?>
            <?php while($tank = $tanks_result->fetch_assoc()): ?>
                <?php
                $percentage_class = '';
                if ($tank['percentage'] < 20) {
                    $percentage_class = 'low';
                    $progress_class = 'bg-danger';
                } elseif ($tank['percentage'] < 50) {
                    $percentage_class = 'medium';
                    $progress_class = 'bg-warning';
                } else {
                    $percentage_class = 'high';
                    $progress_class = 'bg-success';
                }
                ?>
                <div class="tank-item">
                    <div class="tank-info">
                        <span class="tank-name"><?php echo $tank['fuel_name']; ?></span>
                        <span class="tank-percentage <?php echo $percentage_class; ?>">
                            <?php echo $tank['percentage']; ?>%
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar <?php echo $progress_class; ?>" 
                             style="width: <?php echo $tank['percentage']; ?>%"></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-warning py-2 mb-0">
                <small>No tanks found</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <!-- Dashboard Section -->
        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <ul class="nav-items">
                <li class="nav-item">
                    <a href="dashboard.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sales.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-gas-pump"></i>
                        <span class="nav-text">Fuel Sales</span>
                        <span class="nav-badge"><?php echo $today_stats['sales_count'] ?? 0; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="receipt.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'receipt.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-receipt"></i>
                        <span class="nav-text">Receipts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="history.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <span class="nav-text">Sales History</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Reports Section -->
        <div class="nav-section">
            <div class="nav-section-title">Reports & Analytics</div>
            <ul class="nav-items">
                <li class="nav-item">
                    <a href="daily_report.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'daily_report.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span class="nav-text">Daily Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="monthly_report.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'monthly_report.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Monthly Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="performance.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-trophy"></i>
                        <span class="nav-text">Performance</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Tools Section -->
        <div class="nav-section">
            <div class="nav-section-title">Tools</div>
            <ul class="nav-items">
                <li class="nav-item">
                    <a href="calculator.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calculator.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-calculator"></i>
                        <span class="nav-text">Fuel Calculator</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="converter.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'converter.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <span class="nav-text">Unit Converter</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="price_list.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'price_list.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <span class="nav-text">Price List</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Quick Actions & Shift Info -->
    <div class="sidebar-quick-actions">
        <button class="quick-action-btn" onclick="window.location.href='sales.php?action=add'">
            <i class="fas fa-plus"></i>
            <span>New Fuel Sale</span>
        </button>
        <button class="quick-action-btn" onclick="window.location.href='quick_sale.php'">
            <i class="fas fa-bolt"></i>
            <span>Quick Sale</span>
        </button>
        
        <!-- Shift Information -->
        <div class="shift-info">
            <div class="shift-item">
                <span class="shift-label">Shift:</span>
                <span class="shift-value on-shift">On Duty</span>
            </div>
            <div class="shift-item">
                <span class="shift-label">Start Time:</span>
                <span class="shift-value">08:00 AM</span>
            </div>
            <div class="shift-item">
                <span class="shift-label">Duration:</span>
                <span class="shift-value">8h 15m</span>
            </div>
        </div>
    </div>
</aside>

<!-- JavaScript for Attendant Sidebar -->
<script>
$(document).ready(function() {
    const body = $('body');
    const attendantSidebar = $('#attendantSidebar');
    const mobileMenuToggle = $('#mobileMenuToggle');
    const mobileMenuClose = $('#mobileMenuClose');
    const mobileMenu = $('#mobileMenu');
    
    // Toggle sidebar on mobile
    mobileMenuToggle.click(function() {
        if ($(window).width() <= 767) {
            body.toggleClass('menu-open');
            attendantSidebar.toggleClass('show');
        }
    });
    
    // Close sidebar when clicking outside on mobile
    $(document).click(function(e) {
        if ($(window).width() <= 767) {
            if (body.hasClass('menu-open') && 
                !$(e.target).closest('#attendantSidebar').length && 
                !$(e.target).closest('#mobileMenuToggle').length) {
                body.removeClass('menu-open');
                attendantSidebar.removeClass('show');
            }
        }
    });
    
    // Toggle mobile menu
    mobileMenuToggle.click(function() {
        mobileMenu.addClass('show');
        body.addClass('no-scroll');
    });
    
    mobileMenuClose.click(function() {
        mobileMenu.removeClass('show');
        body.removeClass('no-scroll');
    });
    
    // Close mobile menu when clicking outside
    $(document).click(function(e) {
        if (mobileMenu.hasClass('show') && 
            !$(e.target).closest('#mobileMenu').length && 
            !$(e.target).closest('#mobileMenuToggle').length) {
            mobileMenu.removeClass('show');
            body.removeClass('no-scroll');
        }
    });
    
    // User dropdown
    $('#userDropdownToggle').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#userDropdownMenu').toggleClass('show');
    });
    
    $(document).click(function(e) {
        if (!$(e.target).closest('.user-profile').length) {
            $('#userDropdownMenu').removeClass('show');
        }
    });
    
    // Update tank status every 5 minutes
    function updateTankStatus() {
        $.ajax({
            url: '../api/get_tank_status.php',
            method: 'GET',
            data: { branch_id: <?php echo $branch_id; ?> },
            success: function(data) {
                if (data.success) {
                    // Update tank levels in sidebar
                    $('.tank-item').each(function(index) {
                        if (data.tanks[index]) {
                            const tank = data.tanks[index];
                            const percentage = tank.percentage;
                            const $tankItem = $(this);
                            
                            // Update percentage
                            const $percentage = $tankItem.find('.tank-percentage');
                            $percentage.text(percentage + '%');
                            
                            // Update progress bar
                            const $progressBar = $tankItem.find('.progress-bar');
                            $progressBar.css('width', percentage + '%');
                            
                            // Update classes based on percentage
                            $percentage.removeClass('low medium high');
                            $progressBar.removeClass('bg-danger bg-warning bg-success');
                            
                            if (percentage < 20) {
                                $percentage.addClass('low');
                                $progressBar.addClass('bg-danger');
                            } else if (percentage < 50) {
                                $percentage.addClass('medium');
                                $progressBar.addClass('bg-warning');
                            } else {
                                $percentage.addClass('high');
                                $progressBar.addClass('bg-success');
                            }
                        }
                    });
                }
            }
        });
    }
    
    // Update stats every 2 minutes
    setInterval(updateTankStatus, 120000);
    
    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() > 767) {
            // Reset mobile states
            body.removeClass('menu-open');
            attendantSidebar.removeClass('show');
            mobileMenu.removeClass('show');
            body.removeClass('no-scroll');
        }
    });
    
    // Set active menu item
    function setActiveMenuItem() {
        const currentPage = window.location.pathname.split('/').pop();
        $('.nav-link.active').removeClass('active');
        $(`.nav-link[href*="${currentPage}"]`).addClass('active');
    }
    
    setActiveMenuItem();
});
</script>