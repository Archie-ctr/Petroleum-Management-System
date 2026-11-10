<?php
// attendant_header.php
session_start();
require_once '../config/db.php';

// Get company settings
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$company_name = $settings['company_name'] ?? "God Willing Business Center";
$company_logo = $settings['company_logo'] ?? '';
$theme_primary = $settings['theme_primary'] ?? $settings['primary_color'] ?? '#198754';
$theme_secondary = $settings['theme_secondary'] ?? $settings['secondary_color'] ?? '#FFD700';

// Check if logo file exists
$logo_path = '';
if (!empty($company_logo)) {
    $full_logo_path = "../uploads/" . $company_logo;
    if (file_exists($full_logo_path)) {
        $logo_path = $full_logo_path;
    }
}

// Get user initials for avatar
$user_initials = '';
if (isset($_SESSION['full_name'])) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    if (count($name_parts) >= 2) {
        $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
    } else {
        $user_initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
    }
}

// Get attendant's today statistics for header
$today_stats = [];
if (isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    $attendant_id = $_SESSION['user_id'];
    
    $stats_sql = "SELECT 
                  COUNT(*) as sales_count,
                  COALESCE(SUM(total_amount), 0) as sales_total,
                  COALESCE(SUM(quantity), 0) as fuel_sold
                  FROM sales 
                  WHERE attendant_id = $attendant_id 
                  AND DATE(sale_date) = '$today'";
    $stats_result = $conn->query($stats_sql);
    $today_stats = $stats_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="<?php echo $company_name; ?> - Attendant Dashboard">
    <meta name="keywords" content="petroleum, fuel, attendant, dashboard">
    <meta name="author" content="<?php echo $company_name; ?>">
    
    <title><?php echo $company_name; ?> - Attendant Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo !empty($logo_path) ? $logo_path : '../assets/images/favicon.ico'; ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Header Styles -->
    <style>
        :root {
            --primary-green: <?php echo $theme_primary; ?>;
            --primary-yellow: <?php echo $theme_secondary; ?>;
            --green-light: #27AE60;
            --green-dark: #1E8449;
            --yellow-light: #FFED4E;
            --yellow-dark: #D4B106;
            --dark-bg: #1a252f;
            --light-bg: #f8f9fa;
            --topbar-height: 60px;
            --sidebar-width: 232px;
            --sidebar-collapsed: 70px;
            --transition-speed: 0.3s;
        }
        
        [data-bs-theme="dark"] {
            --primary-green: #2ECC71;
            --primary-yellow: #FFD700;
            --dark-bg: #0d1117;
            --light-bg: #161b22;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
            min-height: 100vh;
            transition: all var(--transition-speed);
        }
        
        /* Top Navigation Bar - RESPONSIVE */
        .top-navbar {
            background: linear-gradient(135deg, 
                color-mix(in srgb, var(--primary-green) 80%, black 20%) 0%, 
                var(--primary-green) 50%, 
                color-mix(in srgb, var(--primary-green) 80%, white 20%) 100%);
            height: var(--topbar-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            background-color: rgba(25, 135, 84, 0.9);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            padding: 0 15px;
            transition: all var(--transition-speed) ease;
            flex-wrap: nowrap;
        }
        
        @media (min-width: 768px) {
            :root {
                --topbar-height: 70px;
            }
            
            .top-navbar {
                padding: 0 25px;
            }
        }
        
        /* Brand Section */
        .brand-section {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            min-width: auto;
        }
        
        .brand-container {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 100%;
        }
        
        @media (min-width: 768px) {
            .brand-container {
                gap: 15px;
                padding: 10px 15px;
            }
        }
        
        .brand-container:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
        
        .brand-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--yellow-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .brand-logo {
                width: 50px;
                height: 50px;
                border-radius: 12px;
                border-width: 3px;
            }
        }
        
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: relative;
            z-index: 1;
        }
        
        .brand-logo i {
            font-size: 1.2rem;
            color: var(--green-dark);
            position: relative;
            z-index: 1;
        }
        
        @media (min-width: 768px) {
            .brand-logo i {
                font-size: 1.6rem;
            }
        }
        
        .brand-text {
            display: flex;
            flex-direction: column;
            color: white;
            overflow: hidden;
        }
        
        .brand-name {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 0.9rem;
            line-height: 1.1;
            letter-spacing: 0.3px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            background: linear-gradient(to right, white, var(--primary-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
        
        @media (min-width: 768px) {
            .brand-name {
                font-size: 1.3rem;
                max-width: none;
                letter-spacing: 0.5px;
            }
        }
        
        .brand-tagline {
            font-size: 0.6rem;
            opacity: 0.9;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .brand-tagline {
                font-size: 0.7rem;
                letter-spacing: 0.8px;
                margin-top: 2px;
            }
        }
        
        /* Header Stats */
        .header-stats {
            display: none;
            align-items: center;
            gap: 20px;
            margin: 0 20px;
            flex: 1;
            justify-content: center;
        }
        
        @media (min-width: 992px) {
            .header-stats {
                display: flex;
            }
        }
        
        .header-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            min-width: 120px;
            transition: all 0.3s ease;
        }
        
        .header-stat:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary-yellow);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }
        
        /* User Profile */
        .user-profile {
            position: relative;
        }
        
        .user-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            backdrop-filter: blur(10px);
            min-width: auto;
            max-width: 100%;
            overflow: hidden;
        }
        
        @media (min-width: 768px) {
            .user-toggle {
                gap: 15px;
                padding: 8px 15px;
                border-radius: 15px;
            }
        }
        
        .user-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            border-color: var(--primary-yellow);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--yellow-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--green-dark);
            font-weight: 800;
            font-size: 0.9rem;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .user-avatar {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
                border-width: 3px;
            }
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
            overflow: hidden;
            max-width: 120px;
        }
        
        @media (max-width: 400px) {
            .user-info {
                display: none;
            }
        }
        
        @media (min-width: 768px) {
            .user-info {
                max-width: none;
            }
        }
        
        .user-name {
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .user-name {
                font-size: 0.95rem;
            }
        }
        
        .user-role {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.2);
            color: var(--primary-yellow);
            display: inline-block;
            margin-top: 2px;
            text-align: center;
            letter-spacing: 0.3px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .user-role {
                font-size: 0.7rem;
                padding: 3px 10px;
                border-radius: 20px;
                margin-top: 3px;
                letter-spacing: 0.5px;
            }
        }
        
        /* User Dropdown */
        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            min-width: 280px;
            max-width: 90vw;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0;
            display: none;
            z-index: 1060;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }
        
        .user-dropdown-menu.show {
            display: block;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .user-dropdown-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--green-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dropdown-body {
            padding: 12px 0;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
            color: var(--primary-green);
            border-left-color: var(--primary-green);
            padding-left: 25px;
        }
        
        .dropdown-footer {
            padding: 12px 20px;
            background: rgba(0, 0, 0, 0.05);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .mobile-menu-toggle {
                display: none;
            }
        }
        
        .mobile-menu-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid transparent;
            color: white;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px) rotate(5deg);
            border-color: var(--primary-yellow);
        }
        
        .quick-action-btn i {
            font-size: 1.1rem;
        }
        
        /* Main Content Wrapper */
        .main-content-wrapper {
            margin-top: var(--topbar-height);
            margin-left: 0;
            transition: all var(--transition-speed) ease;
            min-height: calc(100vh - var(--topbar-height));
            background: var(--light-bg);
            padding: 15px;
        }
        
        @media (min-width: 768px) {
            .main-content-wrapper {
                margin-left: var(--sidebar-width);
                padding: 20px;
            }
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1060;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            padding: 15px;
            overflow-y: auto;
        }
        
        .mobile-menu.show {
            transform: translateX(0);
        }
        
        @media (min-width: 768px) {
            .mobile-menu {
                display: none;
            }
        }
        :root { --topbar-height: 64px; }
        .top-navbar {
            height: var(--topbar-height);
            padding: 0 20px;
            gap: 12px;
            background: #fff;
            border-bottom: 1px solid #e8ecef;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .06);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }
        .brand-section { min-width: 0; }
        .brand-container {
            gap: 10px;
            padding: 4px 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }
        .brand-container:hover { background: transparent; transform: none; box-shadow: none; }
        .brand-logo { width: 38px; height: 38px; border: 0; border-radius: 10px; background: #eaf4ee; box-shadow: none; }
        .brand-logo img { object-fit: contain; }
        .brand-logo i { color: var(--primary-green); }
        .brand-name {
            max-width: 220px;
            color: #1f2937;
            background: none;
            -webkit-text-fill-color: currentColor;
            font-size: .98rem;
            font-weight: 700;
            letter-spacing: 0;
            text-shadow: none;
        }
        .brand-tagline { color: #7b8794; font-size: .66rem; letter-spacing: .04em; }
        .header-stats { flex: 1; justify-content: center; gap: 0; margin: 0 12px; }
        .header-stat { flex-direction: row; gap: 7px; min-width: 0; padding: 5px 14px; border-right: 1px solid #e8ecef; border-radius: 0; background: transparent; }
        .header-stat:last-child { border-right: 0; }
        .header-stat:hover { transform: none; background: transparent; }
        .stat-value { color: #263b2e; font-size: .9rem; font-weight: 700; }
        .stat-label { margin: 0; color: #7b8794; font-size: .66rem; }
        .header-actions { gap: 6px; }
        .mobile-menu-toggle, .quick-action-btn {
            width: 40px;
            height: 40px;
            padding: 0;
            border: 1px solid #e7ece9;
            border-radius: 9px;
            background: #fff;
            color: #475569;
            box-shadow: none;
        }
        .mobile-menu-toggle:hover, .quick-action-btn:hover {
            transform: none;
            border-color: #cfe2d5;
            background: #f4f8f5;
            color: var(--primary-green);
        }
        .user-toggle { gap: 9px; padding: 4px 7px 4px 4px; border: 1px solid transparent; border-radius: 10px; background: transparent; -webkit-backdrop-filter: none; backdrop-filter: none; }
        .user-toggle:hover { transform: none; border-color: #e8ece9; background: #f7f9f8; box-shadow: none; }
        .user-avatar { width: 36px; height: 36px; border: 0; background: #eaf4ee; box-shadow: none; color: #287348; }
        .user-info .user-name { color: #27332c; }
        .user-toggle .fa-chevron-down { color: #7b8794; }
        @media (max-width: 1199px) { .header-stats { display: none; } }
        @media (max-width: 767px) {
            :root { --topbar-height: 60px; }
            .top-navbar { padding: 0 12px; gap: 7px; }
            .brand-container { gap: 8px; }
            .brand-logo { width: 34px; height: 34px; }
            .brand-name { max-width: 170px; font-size: .88rem; }
            .brand-tagline, .quick-action-btn[title="Notifications"] { display: none; }
            .header-actions { gap: 4px; margin-left: auto; }
            .mobile-menu-toggle, .quick-action-btn { width: 38px; height: 38px; min-width: 38px; }
            .user-toggle { padding: 2px; }
            .user-toggle .user-info, .user-toggle .fa-chevron-down { display: none; }
            .user-avatar { width: 34px; height: 34px; }
        }
        @media (max-width: 400px) { .brand-name { max-width: 132px; } }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navbar" id="topNavbar">
        <!-- Brand Section -->
        <div class="brand-section">
            <a href="dashboard.php" class="brand-container">
                <div class="brand-logo">
                    <?php if (!empty($logo_path)): ?>
                        <img src="<?php echo $logo_path; ?>" alt="<?php echo $company_name; ?>">
                    <?php else: ?>
                        <i class="fas fa-gas-pump"></i>
                    <?php endif; ?>
                </div>
                <div class="brand-text">
                    <span class="brand-name"><?php echo $company_name; ?></span>
                    <span class="brand-tagline">Attendant Dashboard</span>
                </div>
            </a>
        </div>
        
        <!-- Header Stats (Desktop only) -->
        <div class="header-stats">
            <div class="header-stat">
                <span class="stat-value"><?php echo $today_stats['sales_count'] ?? 0; ?></span>
                <span class="stat-label">Sales Today</span>
            </div>
            <div class="header-stat">
                <span class="stat-value">$<?php echo number_format($today_stats['sales_total'] ?? 0, 2); ?></span>
                <span class="stat-label">Revenue</span>
            </div>
            <div class="header-stat">
                <span class="stat-value"><?php echo number_format($today_stats['fuel_sold'] ?? 0, 2); ?>L</span>
                <span class="stat-label">Fuel Sold</span>
            </div>
        </div>
        
        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" title="Menu">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Quick Actions -->
            <a href="sales.php?action=add" class="quick-action-btn" title="New Sale">
                <i class="fas fa-plus"></i>
            </a>
            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-toggle" id="userDropdownToggle">
                    <div class="user-avatar">
                        <?php if (!empty($user_initials)): ?>
                            <?php echo $user_initials; ?>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo $_SESSION['full_name'] ?? 'Attendant'; ?></span>
                        <span class="user-role">Attendant</span>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                
                <!-- User Dropdown Menu -->
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="user-dropdown-header">
                        <div class="user-avatar">
                            <?php if (!empty($user_initials)): ?>
                                <?php echo $user_initials; ?>
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo $_SESSION['full_name'] ?? 'Attendant'; ?></span>
                            <span class="user-role" style="background: rgba(255, 255, 255, 0.2); color: white;">Attendant</span>
                        </div>
                    </div>
                    <div class="dropdown-body">
                        <a href="daily_report.php" class="dropdown-item">
                            <i class="fas fa-chart-bar"></i>
                            <span>Daily Report</span>
                        </a>
                        <a href="calculator.php" class="dropdown-item">
                            <i class="fas fa-calculator"></i>
                            <span>Fuel Calculator</span>
                        </a>
                    </div>
                    <div class="dropdown-footer">
                        <a href="../public/logout.php" class="btn btn-danger btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="user-avatar">
                    <?php if (!empty($user_initials)): ?>
                        <?php echo $user_initials; ?>
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name'] ?? 'Attendant'; ?></h6>
                    <small class="text-muted">Attendant</small>
                </div>
            </div>
            <button class="mobile-menu-close btn" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-menu-items p-3">
            <a href="dashboard.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="sales.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-gas-pump"></i>
                <span>Fuel Sales</span>
            </a>
            <a href="receipt.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-receipt"></i>
                <span>Receipts</span>
            </a>
            <a href="sales_history.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-history"></i>
                <span>Sales History</span>
            </a>
            <a href="tanks.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-oil-can"></i>
                <span>Tank Levels</span>
            </a>
            <a href="fuel_prices.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-tags"></i>
                <span>Fuel Prices</span>
            </a>
            <a href="daily_report.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-chart-bar"></i>
                <span>Daily Report</span>
            </a>
            <a href="../public/logout.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none text-danger mt-4">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper" id="mainContentWrapper">