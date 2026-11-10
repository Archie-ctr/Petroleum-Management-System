<?php
// attendant_header.php
session_start();
require_once '../config/db.php';

// Get company settings including logo
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$company_name = $settings['company_name'] ?? "God Willing Business Center";
$company_logo = $settings['company_logo'] ?? '';
$theme_primary = $settings['theme_primary'] ?? '#198754';
$theme_secondary = $settings['theme_secondary'] ?? '#FFD700';

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

// Get attendant's branch info
$branch_name = 'Branch';
if (isset($_SESSION['branch_id'])) {
    $branch_sql = "SELECT branch_name FROM branches WHERE id = " . $_SESSION['branch_id'];
    $branch_result = $conn->query($branch_sql);
    if ($branch_result->num_rows > 0) {
        $branch = $branch_result->fetch_assoc();
        $branch_name = $branch['branch_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="God Willing Business Center - Attendant Dashboard">
    <meta name="keywords" content="petroleum, fuel, attendant, dashboard, god willing">
    <meta name="author" content="God Willing Business Center">
    
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
    
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Header Styles - RESPONSIVE -->
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
            --sidebar-width: 250px;
            --sidebar-collapsed: 70px;
            --topbar-height: 60px;
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
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
            min-height: 100vh;
            transition: all var(--transition-speed);
            font-size: 14px;
        }
        
        /* Responsive Top Navigation Bar */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            padding: 0 15px;
            transition: all var(--transition-speed) ease;
            flex-wrap: nowrap;
            min-height: 60px;
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
        
        @media (min-width: 576px) {
            .brand-name {
                font-size: 1rem;
                max-width: 150px;
            }
        }
        
        @media (min-width: 768px) {
            .brand-name {
                font-size: 1.3rem;
                max-width: none;
                letter-spacing: 0.5px;
            }
        }
        
        .brand-subtitle {
            font-size: 0.6rem;
            opacity: 0.9;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
        
        @media (min-width: 576px) {
            .brand-subtitle {
                font-size: 0.65rem;
                max-width: 150px;
            }
        }
        
        @media (min-width: 768px) {
            .brand-subtitle {
                font-size: 0.7rem;
                max-width: none;
                letter-spacing: 0.8px;
                margin-top: 2px;
            }
        }
        
        /* Navigation Controls */
        .nav-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .nav-controls {
                gap: 15px;
                margin-right: 30px;
            }
        }
        
        /* Toggle Buttons */
        .nav-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid transparent;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            min-width: 40px;
        }
        
        @media (min-width: 768px) {
            .nav-btn {
                font-size: 1.2rem;
                padding: 10px;
                width: 45px;
                height: 45px;
            }
        }
        
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-yellow);
        }
        
        /* Actions Section */
        .actions-section {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            flex-shrink: 0;
        }
        
        @media (min-width: 576px) {
            .actions-section {
                gap: 10px;
            }
        }
        
        @media (min-width: 768px) {
            .actions-section {
                gap: 20px;
            }
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
        
        @media (min-width: 576px) {
            .user-info {
                max-width: 150px;
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
            transform: rotate(90deg);
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
            
            .sidebar-collapsed .main-content-wrapper {
                margin-left: var(--sidebar-collapsed);
            }
        }
        
        /* Hide specific elements on small screens */
        @media (max-width: 576px) {
            .nav-controls .sidebar-toggle {
                display: none;
            }
            
            .user-toggle .user-info,
            .user-toggle .fa-chevron-down {
                display: none;
            }
        }

        :root { --topbar-height: 64px; }
        .top-navbar {
            height: var(--topbar-height);
            min-height: var(--topbar-height);
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
        .brand-subtitle { color: #7b8794; font-size: .66rem; letter-spacing: .04em; }
        .nav-controls { margin: 0 0 0 14px; }
        .nav-btn, .mobile-menu-toggle {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border: 1px solid #e7ece9;
            border-radius: 9px;
            background: #fff;
            color: #475569;
            box-shadow: none;
            animation: none;
        }
        .nav-btn::before { display: none; }
        .nav-btn:hover, .mobile-menu-toggle:hover {
            transform: none;
            border-color: #cfe2d5;
            background: #f4f8f5;
            color: var(--primary-green);
            box-shadow: none;
        }
        .actions-section { gap: 10px; margin-left: auto; }
        .branch-context {
            padding: 7px 10px;
            border: 1px solid #dcebe1;
            border-radius: 7px;
            background: #f3f8f5;
            color: #356b4b;
        }
        .user-toggle {
            gap: 9px;
            padding: 4px 8px 4px 4px;
            border: 1px solid transparent;
            border-radius: 10px;
            background: transparent;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }
        .user-toggle:hover { transform: none; border-color: #e8ece9; background: #f7f9f8; box-shadow: none; }
        .user-avatar { width: 36px; height: 36px; border: 0; background: #eaf4ee; box-shadow: none; color: #287348; }
        .user-info .user-name { color: #27332c; }
        .user-role { padding: 0; margin: 0; border: 0; border-radius: 0; background: transparent; color: #7b8794; font-size: .68rem; }
        @media (max-width: 767px) {
            :root { --topbar-height: 60px; }
            .top-navbar { padding: 0 12px; gap: 7px; }
            .brand-container { gap: 8px; }
            .brand-logo { width: 34px; height: 34px; }
            .brand-name { max-width: 170px; font-size: .88rem; }
            .brand-subtitle, .branch-context { display: none; }
            .nav-controls { margin-left: auto; margin-right: 0; }
            .nav-btn, .mobile-menu-toggle { width: 38px; height: 38px; min-width: 38px; }
            .user-toggle { padding: 2px; }
            .user-toggle .user-info { display: none; }
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
                    <span class="brand-subtitle">Attendant Dashboard</span>
                </div>
            </a>
        </div>
        
        <!-- Navigation Controls -->
        <div class="nav-controls">
            <!-- Sidebar Toggle -->
            <button class="nav-btn sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Actions Section -->
        <div class="actions-section">
            <!-- Current Branch -->
            <div class="branch-context d-none d-md-flex align-items-center">
                <i class="fas fa-store me-2"></i>
                <span class="small"><?php echo $branch_name; ?></span>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" title="Mobile Menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            
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
                        <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                        <span class="user-role">Attendant</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper" id="mainContentWrapper">
        <div class="container-fluid py-4 px-4">