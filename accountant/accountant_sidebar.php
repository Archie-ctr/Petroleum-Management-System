<?php
// accountant_sidebar.php
// Accountant-specific sidebar navigation

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'accountant') {
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Accountant Sidebar Styles */
    .accountant-sidebar {
        position: fixed;
        top: var(--topbar-height, 64px);
        left: 0;
        z-index: 1000;
        width: var(--sidebar-width, 232px);
        height: calc(100vh - var(--topbar-height, 64px));
        background: #fff;
        border-right: 1px solid #e8ecef;
        box-shadow: 2px 0 12px rgba(15, 23, 42, 0.05);
        overflow-y: auto;
    }

    .sidebar-collapsed .accountant-sidebar {
        width: var(--sidebar-collapsed, 70px);
    }

    .sidebar-collapsed .accountant-sidebar-section {
        padding: 8px 0;
    }

    .sidebar-collapsed .accountant-sidebar-title,
    .sidebar-collapsed .accountant-nav-link span:not(.accountant-nav-badge) {
        display: none;
    }

    .sidebar-collapsed .accountant-nav-link {
        justify-content: center;
        padding: 10px;
    }
    
    .accountant-sidebar-section {
        padding: 12px 0;
        border-bottom: 1px solid #eef0f2;
    }
    
    .accountant-sidebar-title {
        font-size: 0.66rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #84909b;
        font-weight: 600;
        margin: 0 16px 5px;
        padding: 0;
    }
    
    .accountant-nav-item {
        margin: 2px 8px;
    }
    
    .accountant-nav-link {
        display: flex;
        align-items: center;
        min-height: 40px;
        padding: 9px 10px;
        color: #475569;
        text-decoration: none;
        border-radius: 6px;
        transition: background-color 0.15s ease, color 0.15s ease;
        font-size: 0.88rem;
    }
    
    .accountant-nav-link:hover {
        background: #f4f7f5;
        color: #198754;
    }
    
    .accountant-nav-link.active {
        background: #eaf4ee;
        color: #198754;
        font-weight: 600;
        border-left: 0;
    }
    
    .accountant-nav-icon {
        width: 20px;
        text-align: center;
        margin-right: 10px;
        font-size: 1rem;
    }
    
    .accountant-nav-badge {
        margin-left: auto;
        background: #198754;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
    }
    
    .accountant-quick-stats {
        padding: 15px;
        background: rgba(25, 135, 84, 0.05);
        border-radius: 8px;
        margin: 15px;
    }
    
    .accountant-stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.85rem;
    }
    
    .accountant-stat-label {
        color: #666;
    }
    
    .accountant-stat-value {
        font-weight: 600;
        color: #198754;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .accountant-sidebar {
            top: var(--topbar-height, 64px);
            width: 280px;
            height: calc(100vh - var(--topbar-height, 64px));
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .accountant-sidebar.show {
            transform: translateX(0);
        }
        
        .accountant-sidebar-overlay {
            display: none;
            position: fixed;
            top: var(--topbar-height, 64px);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .accountant-sidebar-overlay.show {
            display: block;
        }
    }
</style>

<aside class="accountant-sidebar" id="accountantSidebar" aria-label="Accountant navigation">
    <div class="accountant-sidebar-section">
        <div class="accountant-sidebar-title">Workspace</div>
        <div class="accountant-nav-item">
            <a href="dashboard.php" class="accountant-nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt accountant-nav-icon"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="accountant-sidebar-section">
        <div class="accountant-sidebar-title">Financial Operations</div>
        <div class="accountant-nav-item">
            <a href="invoices.php" class="accountant-nav-link <?php echo $current_page == 'invoices.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice accountant-nav-icon"></i>
                <span>Invoices</span>
            </a>
        </div>
        <div class="accountant-nav-item">
            <a href="payments.php" class="accountant-nav-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-check-alt accountant-nav-icon"></i>
                <span>Payments</span>
            </a>
        </div>
        <div class="accountant-nav-item">
            <a href="payroll.php" class="accountant-nav-link <?php echo $current_page == 'payroll.php' ? 'active' : ''; ?>">
                <i class="fas fa-users accountant-nav-icon"></i>
                <span>Staff Payroll</span>
            </a>
        </div>
    </div>
    
    <div class="accountant-sidebar-section">
        <div class="accountant-sidebar-title">Reporting</div>
        <div class="accountant-nav-item">
            <a href="reports.php?report_type=financial_summary" class="accountant-nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" aria-current="<?php echo $current_page == 'reports.php' ? 'page' : 'false'; ?>">
                <i class="fas fa-chart-pie accountant-nav-icon"></i>
                <span>Financial Reports</span>
            </a>
        </div>
    </div>
    <div class="accountant-sidebar-section accountant-sidebar-account">
        <a href="../public/logout.php" class="accountant-nav-link text-danger">
            <i class="fas fa-sign-out-alt accountant-nav-icon"></i>
            <span>Sign out</span>
        </a>
    </div>
</aside>

<!-- Mobile overlay for sidebar -->
<div class="accountant-sidebar-overlay" id="accountantSidebarOverlay"></div>

<script>
// Accountant sidebar mobile functionality
$(document).ready(function() {
    const sidebar = $('#accountantSidebar');
    const overlay = $('#accountantSidebarOverlay');

    // Close sidebar when clicking overlay
    overlay.click(function() {
        sidebar.removeClass('show');
        overlay.removeClass('show');
        $('body').removeClass('no-scroll');
    });
    
    $('.accountant-nav-link').click(function() {
        if ($(window).width() <= 768) {
            sidebar.removeClass('show');
            overlay.removeClass('show');
            $('body').removeClass('menu-open no-scroll');
            $('#sidebarToggle').find('i').removeClass('fa-times').addClass('fa-bars');
        }
    });
    
    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() > 768) {
            // Always show sidebar on desktop
            sidebar.addClass('show');
            overlay.removeClass('show');
            $('body').removeClass('menu-open no-scroll');
            $('#sidebarToggle').find('i').removeClass('fa-times').addClass('fa-bars');
        } else {
            // Hide sidebar on mobile by default
            sidebar.removeClass('show');
            overlay.removeClass('show');
            $('body').removeClass('menu-open no-scroll');
        }
    }).resize(); // Trigger initially
    
    // Set active nav items
    $('.accountant-nav-link').each(function() {
        if ($(this).hasClass('active')) {
            // Scroll to active item
            this.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
});
</script>