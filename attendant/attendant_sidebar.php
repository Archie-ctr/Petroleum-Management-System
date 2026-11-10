<?php
// attendant_sidebar.php
// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
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
        transform: translateX(-100%);
    }

    @media (min-width: 768px) {
        .attendant-sidebar {
            transform: translateX(0);
        }
        
        .sidebar-collapsed .attendant-sidebar {
            width: var(--sidebar-collapsed);
        }
    }

    .attendant-sidebar.show {
        transform: translateX(0);
    }

    /* Sidebar Header */
    .sidebar-header {
        padding: 20px 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        text-align: center;
        background: white;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }

    .sidebar-collapsed .sidebar-header {
        padding: 15px 10px;
    }

    /* Today's Stats */
    .today-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 10px;
    }

    .sidebar-collapsed .today-stats {
        display: none;
    }

    @media (max-width: 767px) {
        .today-stats {
            display: grid !important;
        }
    }

    .today-stat {
        text-align: center;
        padding: 8px;
        border-radius: 8px;
        background: rgba(25, 135, 84, 0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .today-stat:hover {
        background: rgba(25, 135, 84, 0.1);
        transform: translateY(-2px);
    }

    .today-stat-value {
        font-weight: 800;
        font-size: 1rem;
        color: var(--primary-green);
        line-height: 1;
    }

    .today-stat-label {
        font-size: 0.65rem;
        color: #666;
        margin-top: 3px;
        white-space: nowrap;
    }

    /* Navigation Menu */
    .attendant-nav {
        padding: 15px 0;
        flex: 1;
        overflow-y: auto;
    }

    .nav-label {
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

    .sidebar-collapsed .nav-label {
        display: none;
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
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        min-height: 44px;
    }

    .sidebar-collapsed .nav-link {
        padding: 12px 10px;
        justify-content: center;
    }

    .nav-link:hover {
        background: linear-gradient(to right, rgba(25, 135, 84, 0.05), transparent);
        color: var(--primary-green);
        padding-left: 25px;
    }

    .sidebar-collapsed .nav-link:hover {
        padding-left: 10px;
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

    .sidebar-collapsed .nav-icon {
        margin-right: 0;
        font-size: 1.2rem;
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

    .sidebar-collapsed .nav-text {
        display: none;
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

    .sidebar-collapsed .nav-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        margin-left: 0;
        font-size: 0.55rem;
        padding: 1px 4px;
        min-width: 16px;
    }

    /* Logout Section */
    .logout-section {
        margin-top: auto;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: #dc3545;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        min-height: 50px;
        background: rgba(220, 53, 69, 0.05);
        font-weight: 600;
    }

    .logout-btn:hover {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        padding-left: 25px;
    }

    .sidebar-collapsed .logout-btn {
        padding: 15px 10px;
        justify-content: center;
    }

    .sidebar-collapsed .logout-btn:hover {
        padding-left: 10px;
    }

    .logout-btn i {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        margin-right: 12px;
        color: #dc3545;
        transition: all 0.3s ease;
    }

    .sidebar-collapsed .logout-btn i {
        margin-right: 0;
        font-size: 1.2rem;
    }

    .logout-btn:hover i {
        transform: scale(1.1);
    }

    .logout-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    .sidebar-collapsed .logout-text {
        display: none;
    }

    /* Quick Actions */
    .quick-actions {
        padding: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .sidebar-collapsed .quick-actions {
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

    /* Shift Info */
    .shift-info {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
        font-size: 0.8rem;
    }

    .sidebar-collapsed .shift-info {
        display: none;
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

    /* Mobile Overlay */
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

        .nav-text, .logout-text {
            display: block !important;
        }

        .quick-action-btn span {
            display: inline !important;
        }

        .shift-info {
            display: block !important;
        }

        /* When sidebar is open on mobile */
        body.menu-open .attendant-sidebar {
            transform: translateX(0);
        }

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

    /* Scrollbar styling */
    .attendant-nav::-webkit-scrollbar {
        width: 4px;
    }

    .attendant-nav::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
    }

    .attendant-nav::-webkit-scrollbar-thumb {
        background: rgba(25, 135, 84, 0.3);
        border-radius: 10px;
    }

    .attendant-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(25, 135, 84, 0.5);
    }

    /* Tooltip for collapsed sidebar */
    .sidebar-collapsed .nav-link::after,
    .sidebar-collapsed .logout-btn::after {
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
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }

    .sidebar-collapsed .logout-btn::after {
        background: #dc3545;
    }

    .sidebar-collapsed .nav-link:hover::after,
    .sidebar-collapsed .logout-btn:hover::after {
        opacity: 1;
        visibility: visible;
        left: calc(100% + 15px);
    }

    .attendant-sidebar {
        background: #fff;
        border-right: 1px solid #e8ecef;
        box-shadow: 2px 0 12px rgba(15, 23, 42, 0.05);
    }

    .attendant-nav {
        padding: 8px 0;
    }

    .nav-label {
        padding: 14px 16px 6px;
        margin: 0;
        color: #84909b;
        background: transparent;
        border: 0;
        font-size: 0.66rem;
        letter-spacing: 0.08em;
    }

    .nav-link {
        margin: 2px 8px;
        min-height: 40px;
        padding: 9px 10px;
        color: #475569;
        border: 0;
        border-radius: 6px;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .nav-link:hover,
    .sidebar-collapsed .nav-link:hover {
        padding-left: 10px;
        background: #f4f7f5;
        color: var(--primary-green);
    }

    .nav-link.active {
        background: #eaf4ee;
        color: var(--primary-green);
        border: 0;
    }

    .nav-icon {
        width: 20px;
        margin-right: 10px;
        font-size: 0.95rem;
    }

    .quick-actions {
        padding: 10px 14px;
        border-top: 1px solid #eef0f2;
        background: #fff;
        border-bottom: 0;
    }

    .quick-action-grid {
        display: none;
    }

    .shift-info {
        margin: 0;
        padding: 8px 10px;
        background: #f7f8f8;
        border-radius: 6px;
        font-size: 0.78rem;
    }

    .shift-item {
        margin-bottom: 3px;
        font-size: 0.78rem;
    }

    .logout-section {
        border-top: 1px solid #eef0f2;
    }

    .logout-btn,
    .sidebar-collapsed .logout-btn {
        min-height: 42px;
        padding: 9px 18px;
        background: transparent;
        border: 0;
        font-weight: 500;
    }

    .logout-btn:hover,
    .sidebar-collapsed .logout-btn:hover {
        padding-left: 18px;
        background: #fff3f3;
    }

    .logout-btn i {
        width: 20px;
        margin-right: 10px;
        font-size: 0.95rem;
    }

    @media (max-width: 767px) {
        .attendant-sidebar {
            width: 250px;
            box-shadow: 4px 0 20px rgba(15, 23, 42, 0.12);
        }

        body.menu-open::before {
            left: 250px;
            backdrop-filter: none;
        }

        .shift-info {
            display: block !important;
        }
    }
</style>

<aside class="attendant-sidebar" id="attendantSidebar">
    <!-- Sidebar Header with Today's Stats -->
   

    <nav class="attendant-nav" aria-label="Attendant navigation">
        <div class="nav-label">Workspace</div>
        <ul class="nav-items">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="nav-label">Sales</div>
        <ul class="nav-items">
            <li class="nav-item">
                <a href="sales.php" class="nav-link <?php echo $current_page == 'sales.php' ? 'active' : ''; ?>" data-tooltip="New Sale">
                    <i class="nav-icon fas fa-gas-pump"></i>
                    <span class="nav-text">New Sale</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sales_history.php" class="nav-link <?php echo $current_page == 'sales_history.php' ? 'active' : ''; ?>" data-tooltip="Sales History">
                    <i class="nav-icon fas fa-history"></i>
                    <span class="nav-text">Sales History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="receipt.php" class="nav-link <?php echo $current_page == 'receipt.php' ? 'active' : ''; ?>" data-tooltip="Receipts">
                    <i class="nav-icon fas fa-receipt"></i>
                    <span class="nav-text">Receipts</span>
                </a>
            </li>
        </ul>

        <div class="nav-label">Inventory & Prices</div>
        <ul class="nav-items">
            <li class="nav-item">
                <a href="tanks.php" class="nav-link <?php echo $current_page == 'tanks.php' ? 'active' : ''; ?>" data-tooltip="Tank Levels">
                    <i class="nav-icon fas fa-oil-can"></i>
                    <span class="nav-text">Tank Levels</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="fuel_prices.php" class="nav-link <?php echo $current_page == 'fuel_prices.php' ? 'active' : ''; ?>" data-tooltip="Fuel Prices">
                    <i class="nav-icon fas fa-tags"></i>
                    <span class="nav-text">Fuel Prices</span>
                </a>
            </li>
        </ul>

        <div class="nav-label">Reports & Tools</div>
        <ul class="nav-items">
            <li class="nav-item">
                <a href="daily_report.php" class="nav-link <?php echo $current_page == 'daily_report.php' ? 'active' : ''; ?>" data-tooltip="Daily Report">
                    <i class="nav-icon fas fa-chart-bar"></i>
                    <span class="nav-text">Daily Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calculator.php" class="nav-link <?php echo $current_page == 'calculator.php' ? 'active' : ''; ?>" data-tooltip="Fuel Calculator">
                    <i class="nav-icon fas fa-calculator"></i>
                    <span class="nav-text">Fuel Calculator</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Shift Information -->
    <div class="quick-actions">
        <div class="shift-info">
            <div class="shift-item">
                <span class="shift-label">Shift:</span>
                <span class="shift-value" id="shiftStatus">Active</span>
            </div>
            <div class="shift-item">
                <span class="shift-label">Start Time:</span>
                <span class="shift-value" id="shiftStart">08:00 AM</span>
            </div>
            <div class="shift-item">
                <span class="shift-label">Duration:</span>
                <span class="shift-value" id="shiftDuration">8h 30m</span>
            </div>
        </div>
    </div>

    <!-- Logout Button -->
    <div class="logout-section">
        <a href="../public/logout.php" class="logout-btn" data-tooltip="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span class="logout-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Sidebar JavaScript -->
<script>
$(document).ready(function() {
    const body = $('body');
    const sidebarToggle = $('#sidebarToggle');
    const attendantSidebar = $('#attendantSidebar');
    const mobileMenuToggle = $('#mobileMenuToggle');
    
    // Toggle Sidebar
    sidebarToggle.click(function(e) {
        e.stopPropagation();
        
        if ($(window).width() <= 768) {
            // For mobile devices
            body.toggleClass('menu-open');
            attendantSidebar.toggleClass('show');
            $(this).find('i').toggleClass('fa-bars fa-times');
        } else {
            // For desktop and tablet
            body.toggleClass('sidebar-collapsed');
            $(this).find('i').toggleClass('fa-bars fa-times');
        }
    });

    // Mobile Menu Toggle
    mobileMenuToggle.click(function() {
        body.toggleClass('menu-open');
        attendantSidebar.toggleClass('show');
    });

    // Close sidebar when clicking outside on mobile
    $(document).click(function(e) {
        if ($(window).width() <= 768) {
            if (body.hasClass('menu-open') && 
                !$(e.target).closest('#attendantSidebar').length && 
                !$(e.target).closest('#sidebarToggle').length &&
                !$(e.target).closest('#mobileMenuToggle').length) {
                body.removeClass('menu-open');
                attendantSidebar.removeClass('show');
                sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
            }
        }
    });

    // Load today's stats
    function loadTodayStats() {
        $.ajax({
            url: 'ajax/get_today_stats.php',
            method: 'GET',
            success: function(data) {
                if (data.success) {
                    $('#salesCount').text(data.sales_count);
                    $('#salesAmount').text('$' + parseFloat(data.sales_total).toFixed(2));
                    $('#fuelSold').text(parseFloat(data.fuel_sold).toFixed(2) + 'L');
                }
            },
            error: function() {
                console.log('Failed to load today stats');
            }
        });
    }

    // Load shift info
    function loadShiftInfo() {
        $.ajax({
            url: 'ajax/get_shift_info.php',
            method: 'GET',
            success: function(data) {
                if (data.success) {
                    $('#shiftStatus').text(data.shift_status);
                    $('#shiftStart').text(data.start_time);
                    $('#shiftDuration').text(data.duration);
                }
            }
        });
    }

    // Initial loads
    loadTodayStats();
    loadShiftInfo();

    // Update stats every 30 seconds
    setInterval(loadTodayStats, 30000);

    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() > 768) {
            // Reset mobile menu state
            body.removeClass('menu-open');
            attendantSidebar.removeClass('show');
            sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
        } else {
            // Reset desktop collapse state
            body.removeClass('sidebar-collapsed');
            sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
        }
    });

    // Update sidebar tooltips
    function updateSidebarTooltips() {
        if (body.hasClass('sidebar-collapsed') || $(window).width() <= 768) {
            $('.nav-link').each(function() {
                const text = $(this).find('.nav-text').text() || $(this).attr('data-tooltip');
                $(this).attr('data-tooltip', text);
            });
            $('.logout-btn').each(function() {
                const text = $(this).find('.logout-text').text() || $(this).attr('data-tooltip');
                $(this).attr('data-tooltip', text);
            });
        }
    }

    // Initialize tooltips
    updateSidebarTooltips();

    // Logout confirmation
    $('.logout-btn').click(function(e) {
        e.preventDefault();
        const logoutUrl = $(this).attr('href');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out of your session.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Logout after 1 second (simulate)
                setTimeout(() => {
                    window.location.href = logoutUrl;
                }, 1000);
            }
        });
    });
});
</script>

<!-- SweetAlert2 for logout confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>