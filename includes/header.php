<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

$accountant_branch_id = null;
if (($_SESSION['user_role'] ?? '') === 'accountant') {
    $accountant_branch_id = filter_var($_SESSION['branch_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$accountant_branch_id || $accountant_branch_id < 1) {
        http_response_code(403);
        exit('Your account is not assigned to a branch. Contact an administrator.');
    }
}

// Get company settings including logo
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$company_name = $settings['company_name'] ?? "God Willing Business Center";
$company_logo = $settings['company_logo'] ?? '';
$theme_primary = $settings['theme_primary'] ?? $settings['primary_color'] ?? '#198754'; // Green
$theme_secondary = $settings['theme_secondary'] ?? $settings['secondary_color'] ?? '#FFD700'; // Yellow
$theme_dark = $settings['theme_dark'] ?? $settings['dark_color'] ?? '#1a252f';

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

// Get notifications count
$notification_count = 0;
$message_count = 0;
if (isset($_SESSION['user_id'])) {
    // Pending expenses for approval (admin/accountant)
    if (in_array($_SESSION['user_role'], ['admin', 'accountant'])) {
        $branch_filter = $accountant_branch_id !== null
            ? ' AND branch_id = ' . (int)$accountant_branch_id
            : '';
        $notif_sql = "SELECT COUNT(*) as count FROM expenses WHERE status = 'pending'$branch_filter";
        $notif_result = $conn->query($notif_sql);
        $notification_count += $notif_result->fetch_assoc()['count'];
    }
    
    // Low stock notifications
    $branch_filter = $accountant_branch_id !== null
        ? ' AND branch_id = ' . (int)$accountant_branch_id
        : '';
    $low_stock_sql = "SELECT COUNT(*) as count FROM tanks WHERE current_quantity < minimum_threshold AND status = 'active'$branch_filter";
    $low_stock_result = $conn->query($low_stock_sql);
    $notification_count += $low_stock_result->fetch_assoc()['count'];
    
    // Maintenance pumps
    $maintenance_sql = "SELECT COUNT(*) as count FROM pumps WHERE status = 'maintenance'$branch_filter";
    $maintenance_result = $conn->query($maintenance_sql);
    $notification_count += $maintenance_result->fetch_assoc()['count'];
}

// Get quick stats for header
$quick_stats = [];
if (isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    
    // Today's sales
    $branch_filter = $accountant_branch_id !== null
        ? ' AND branch_id = ' . (int)$accountant_branch_id
        : '';
    $sales_sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = '$today'$branch_filter";
    $sales_result = $conn->query($sales_sql);
    $quick_stats['today_sales'] = $sales_result->fetch_assoc()['total'];
    
    // Active branches
    $branch_sql = $accountant_branch_id !== null
        ? "SELECT COUNT(*) as count FROM branches WHERE status = 'active' AND id = " . (int)$accountant_branch_id
        : "SELECT COUNT(*) as count FROM branches WHERE status = 'active'";
    $branch_result = $conn->query($branch_sql);
    $quick_stats['branches'] = $branch_result->fetch_assoc()['count'];
    
    // Fuel sold today
    $fuel_sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM sales WHERE DATE(sale_date) = '$today'$branch_filter";
    $fuel_result = $conn->query($fuel_sql);
    $quick_stats['fuel_sold'] = $fuel_result->fetch_assoc()['total'];
    
    // Low stock tanks
    $low_sql = "SELECT COUNT(*) as count FROM tanks WHERE current_quantity < minimum_threshold AND status = 'active'$branch_filter";
    $low_result = $conn->query($low_sql);
    $quick_stats['low_stock'] = $low_result->fetch_assoc()['count'];
    
    // Pending tasks (expenses + low stock)
    $quick_stats['pending_tasks'] = $notification_count;
}

// Check if user is logged in for protected pages
$protected_pages = ['admin', 'manager', 'attendant', 'accountant'];
$current_path = $_SERVER['PHP_SELF'];
$current_page = basename($current_path);

foreach ($protected_pages as $folder) {
    if (strpos($current_path, "/$folder/") !== false) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../public/login.php");
            exit();
        }
        
        // Check role access
        $allowed_roles = [];
        switch($folder) {
            case 'admin':
                $allowed_roles = ['admin'];
                break;
            case 'manager':
                $allowed_roles = ['admin', 'manager'];
                break;
            case 'attendant':
                $allowed_roles = ['admin', 'manager', 'attendant'];
                break;
            case 'accountant':
                $allowed_roles = ['admin', 'accountant'];
                break;
        }
        
        if (!in_array($_SESSION['user_role'], $allowed_roles)) {
            header("Location: ../public/index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="God Willing Business Center - Petroleum Management System">
    <meta name="keywords" content="petroleum, fuel, management, system, god willing">
    <meta name="author" content="God Willing Business Center">
    
    <title><?php echo $company_name; ?> - Petroleum Management System</title>
    
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
            --sidebar-width: 232px;
            --sidebar-collapsed: 70px;
            --topbar-height: 60px; /* Reduced for mobile first */
            --transition-speed: 0.3s;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.15);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        [data-bs-theme="dark"] {
            --primary-green: #2ECC71;
            --primary-yellow: #FFD700;
            --dark-bg: #0d1117;
            --light-bg: #161b22;
            --glass-bg: rgba(13, 17, 23, 0.95);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
        
        /* Body states */
        body.no-scroll {
            overflow: hidden;
        }
        
        body.menu-open {
            overflow: hidden;
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
            border-bottom: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            display: flex;
            align-items: center;
            padding: 0 15px;
            transition: all var(--transition-speed) ease;
            flex-wrap: nowrap;
            min-height: 60px;
        }
        
        /* Adjust topbar height for tablets and desktop */
        @media (min-width: 768px) {
            :root {
                --topbar-height: 70px;
            }
            
            .top-navbar {
                padding: 0 25px;
            }
        }
        
        /* Brand Section - Responsive */
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
        
        .brand-logo::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: rotate(45deg) translateX(-150%); }
            100% { transform: rotate(45deg) translateX(150%); }
        }
        
        .brand-logo:hover {
            transform: rotate(5deg) scale(1.05);
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
            max-width: 120px;
        }
        
        @media (min-width: 576px) {
            .brand-tagline {
                font-size: 0.65rem;
                max-width: 150px;
            }
        }
        
        @media (min-width: 768px) {
            .brand-tagline {
                font-size: 0.7rem;
                max-width: none;
                letter-spacing: 0.8px;
                margin-top: 2px;
            }
        }
        
        /* Hide brand text on very small screens */
        @media (max-width: 400px) {
            .brand-tagline {
                display: none;
            }
            
            .brand-name {
                max-width: 80px;
                font-size: 0.8rem;
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
        
        /* Toggle Buttons - Touch Friendly */
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
        
        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 30%, rgba(255, 255, 255, 0.2) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-yellow);
        }
        
        .nav-btn:hover::before {
            opacity: 1;
        }
        
        .sidebar-toggle {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
        }
        
        /* Search Section - Responsive */
        .search-section {
            flex: 1;
            max-width: none;
            position: relative;
            margin: 0 8px;
            display: none; /* Hidden on mobile by default */
        }
        
        @media (min-width: 576px) {
            .search-section {
                display: block;
                max-width: 200px;
            }
        }
        
        @media (min-width: 768px) {
            .search-section {
                max-width: 400px;
                margin: 0 15px;
            }
        }
        
        @media (min-width: 992px) {
            .search-section {
                max-width: 500px;
            }
        }
        
        .search-container {
            position: relative;
            width: 100%;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 45px 10px 15px;
            border: 2px solid transparent;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            height: 40px;
        }
        
        @media (min-width: 768px) {
            .search-input {
                padding: 12px 50px 12px 20px;
                font-size: 0.95rem;
                border-radius: 30px;
                height: auto;
            }
        }
        
        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            border-color: var(--primary-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
            transform: scale(1.02);
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }
        
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--primary-yellow);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 4px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (min-width: 768px) {
            .search-btn {
                right: 15px;
                font-size: 1.1rem;
                width: auto;
                height: auto;
                padding: 0;
            }
        }
        
        .search-btn:hover {
            transform: translateY(-50%) scale(1.2);
        }
        
        /* Actions Section - Responsive */
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
        
        /* Action Items */
        .action-item {
            position: relative;
        }
        
        .action-btn {
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
        
        @media (min-width: 768px) {
            .action-btn {
                padding: 10px;
                width: 45px;
                height: 45px;
                border-radius: 12px;
            }
        }
        
        .action-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px) rotate(5deg);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-yellow);
        }
        
        .action-btn:hover::after {
            opacity: 1;
        }
        
        .action-btn i {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        @media (min-width: 768px) {
            .action-btn i {
                font-size: 1.2rem;
            }
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 12px;
            border: 2px solid var(--primary-green);
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: bounce 2s infinite;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        @media (min-width: 768px) {
            .notification-badge {
                top: -5px;
                right: -5px;
                font-size: 0.7rem;
                padding: 3px 8px;
                border-radius: 20px;
                min-width: 20px;
                height: 20px;
            }
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* User Profile - Responsive */
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
            box-shadow: var(--shadow-md);
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
        
        .user-avatar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.4) 0%, transparent 70%);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            position: relative;
            z-index: 1;
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
        
        .user-role-badge {
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
            .user-role-badge {
                font-size: 0.7rem;
                padding: 3px 10px;
                border-radius: 20px;
                margin-top: 3px;
                letter-spacing: 0.5px;
            }
        }
        
        .user-toggle .fa-chevron-down {
            font-size: 0.8rem;
            margin-left: 4px;
        }
        
        @media (min-width: 768px) {
            .user-toggle .fa-chevron-down {
                font-size: 1rem;
                margin-left: 8px;
            }
        }
        
        /* User Dropdown Menu - Responsive */
        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            min-width: 280px;
            max-width: 90vw;
            border-radius: 15px;
            box-shadow: var(--glass-shadow);
            border: 1px solid var(--glass-border);
            padding: 0;
            display: none;
            z-index: 1060;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }
        
        @media (min-width: 768px) {
            .user-dropdown-menu {
                min-width: 300px;
                border-radius: 20px;
                top: calc(100% + 15px);
            }
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
        
        .user-dropdown-menu.show {
            display: block;
        }
        
        .user-dropdown-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--green-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--glass-border);
        }
        
        @media (min-width: 768px) {
            .user-dropdown-header {
                padding: 25px;
                gap: 20px;
            }
        }
        
        .user-dropdown-header .user-avatar {
            width: 50px;
            height: 50px;
            border-width: 3px;
        }
        
        @media (min-width: 768px) {
            .user-dropdown-header .user-avatar {
                width: 60px;
                height: 60px;
                border-width: 4px;
            }
        }
        
        .user-dropdown-header .user-info {
            flex: 1;
            max-width: none;
        }
        
        .user-dropdown-header .user-name {
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        @media (min-width: 768px) {
            .user-dropdown-header .user-name {
                font-size: 1.1rem;
                margin-bottom: 5px;
            }
        }
        
        .user-dropdown-body {
            padding: 12px 0;
        }
        
        @media (min-width: 768px) {
            .user-dropdown-body {
                padding: 15px 0;
            }
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: var(--bs-body-color);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
            font-size: 0.9rem;
        }
        
        @media (min-width: 768px) {
            .dropdown-item {
                gap: 15px;
                padding: 12px 25px;
                font-size: 1rem;
            }
        }
        
        .dropdown-item::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(25, 135, 84, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
            color: var(--primary-green);
            border-left-color: var(--primary-green);
            padding-left: 25px;
        }
        
        @media (min-width: 768px) {
            .dropdown-item:hover {
                padding-left: 30px;
            }
        }
        
        .dropdown-item:hover::before {
            left: 100%;
        }
        
        .dropdown-item i {
            width: 20px;
            font-size: 1rem;
            color: var(--primary-green);
        }
        
        @media (min-width: 768px) {
            .dropdown-item i {
                width: 25px;
                font-size: 1.1rem;
            }
        }
        
        .dropdown-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, var(--glass-border), transparent);
            margin: 8px 20px;
        }
        
        @media (min-width: 768px) {
            .dropdown-divider {
                margin: 10px 25px;
            }
        }
        
        .dropdown-footer {
            padding: 12px 20px;
            background: rgba(0, 0, 0, 0.05);
        }
        
        @media (min-width: 768px) {
            .dropdown-footer {
                padding: 15px 25px;
            }
        }
        
        .dropdown-footer .btn {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        
        @media (min-width: 768px) {
            .dropdown-footer .btn {
                padding: 10px 16px;
                font-size: 1rem;
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
        
        /* Quick Stats Bar - Responsive */
        .quick-stats-bar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            right: 0;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1040;
            padding: 10px 15px;
            display: flex;
            gap: 15px;
            box-shadow: var(--shadow-md);
            border-bottom: 1px solid var(--glass-border);
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .quick-stats-bar::-webkit-scrollbar {
            display: none;
        }
        
        @media (min-width: 768px) {
            .quick-stats-bar {
                padding: 15px 25px;
                gap: 40px;
            }
        }
        
        .quick-stats-bar.show {
            transform: translateY(0);
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .quick-stat {
            display: flex;
            flex-direction: column;
            min-width: 90px;
            padding: 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .quick-stat {
                min-width: 120px;
                padding: 10px;
                border-radius: 10px;
            }
        }
        
        .quick-stat:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .quick-stat-label {
            font-size: 0.7rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 3px;
            white-space: nowrap;
        }
        
        @media (min-width: 768px) {
            .quick-stat-label {
                font-size: 0.75rem;
                letter-spacing: 1px;
                margin-bottom: 5px;
            }
        }
        
        .quick-stat-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-green);
            line-height: 1.2;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        @media (min-width: 768px) {
            .quick-stat-value {
                font-size: 1.3rem;
                gap: 5px;
            }
        }
        
        .quick-stat-trend {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            white-space: nowrap;
        }

        .accountant-layout .quick-stats-bar {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            overflow-x: hidden;
            overflow-y: auto;
            max-height: calc(100vh - var(--topbar-height));
            max-height: calc(100dvh - var(--topbar-height));
        }

        .accountant-layout .quick-stat {
            min-width: 0;
            flex-shrink: 1;
        }

        .accountant-layout .quick-stat-label,
        .accountant-layout .quick-stat-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        @media (min-width: 768px) and (max-width: 1199px) {
            .accountant-layout .quick-stats-bar {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .accountant-layout .quick-stats-bar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 10px;
            }

            .accountant-layout .quick-stat {
                padding: 7px;
            }

            .accountant-layout .quick-stat-label {
                white-space: normal;
                letter-spacing: 0.4px;
            }

            .accountant-layout .quick-stat-value {
                font-size: 1rem;
            }
        }
        
        @media (min-width: 768px) {
            .quick-stat-trend {
                font-size: 0.8rem;
                padding: 2px 8px;
                border-radius: 10px;
            }
        }
        
        .trend-up {
            background: rgba(25, 135, 84, 0.1);
            color: var(--primary-green);
        }
        
        .trend-down {
            background: rgba(220, 53, 69, 0.1);
            color: var(--bs-danger);
        }
        
        /* Notifications Panel - Responsive */
        .notifications-panel {
            position: fixed;
            top: var(--topbar-height);
            right: 0;
            width: 100%;
            max-width: 350px;
            height: calc(100vh - var(--topbar-height));
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-left: 1px solid var(--glass-border);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 1055;
            box-shadow: var(--glass-shadow);
        }
        
        @media (min-width: 576px) {
            .notifications-panel {
                width: 350px;
            }
        }
        
        .notifications-panel.show {
            transform: translateX(0);
        }
        
        /* Mobile Menu - Responsive */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            z-index: 1060;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            padding: 15px;
            overflow-y: auto;
        }
        
        @media (min-width: 768px) {
            .mobile-menu {
                display: none;
            }
        }
        
        .mobile-menu.show {
            transform: translateX(0);
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--glass-border);
            margin-bottom: 15px;
        }
        
        .mobile-menu-close {
            background: transparent;
            border: none;
            font-size: 1.3rem;
            color: var(--primary-green);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-close:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .mobile-menu-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            text-decoration: none;
            color: var(--bs-body-color);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .mobile-menu-item:hover {
            background: rgba(25, 135, 84, 0.1);
            color: var(--primary-green);
            transform: translateX(5px);
        }
        
        .mobile-menu-item i {
            width: 22px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        /* Main Content Wrapper - Responsive */
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
        
        /* For mobile when menu is open */
        @media (max-width: 767px) {
            body.menu-open .main-content-wrapper {
                margin-left: 280px;
                position: relative;
                overflow: hidden;
            }
        }
        
        /* Hide specific elements on small screens */
        @media (max-width: 576px) {
            /* Hide some nav controls */
            .nav-controls .sidebar-toggle {
                display: none;
            }

            .accountant-layout .nav-controls #sidebarToggle {
                display: flex !important;
            }
            
            /* Hide some action items */
            .action-item:nth-child(2),
            .action-item:nth-child(4) {
                display: none;
            }
            
            /* Compact user dropdown trigger */
            .user-toggle .user-info,
            .user-toggle .fa-chevron-down {
                display: none;
            }
        }
        
        /* Tablet-specific optimizations */
        @media (min-width: 768px) and (max-width: 1024px) {
            /* Adjust brand section for tablets */
            .brand-name {
                font-size: 1.1rem;
            }
            
            .brand-tagline {
                font-size: 0.65rem;
            }
            
            /* Adjust search bar */
            .search-section {
                max-width: 300px;
            }
            
            /* Adjust quick stats */
            .quick-stats-bar {
                gap: 25px;
                padding: 12px 20px;
            }
            
            .quick-stat {
                min-width: 100px;
            }
            
            .quick-stat-value {
                font-size: 1.2rem;
            }
        }
        
        /* Landscape mode optimizations */
        @media (max-height: 500px) and (orientation: landscape) {
            /* Reduce topbar height in landscape */
            :root {
                --topbar-height: 50px;
            }
            
            .top-navbar {
                padding: 0 10px;
            }
            
            .brand-logo {
                width: 35px;
                height: 35px;
            }
            
            .nav-btn,
            .action-btn,
            .user-avatar {
                width: 35px;
                height: 35px;
            }
            
            .user-toggle {
                padding: 4px 8px;
            }
            
            /* Hide non-essential elements */
            .brand-tagline,
            .user-role-badge {
                display: none;
            }
        }
        
        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            /* Increase touch target sizes */
            .nav-btn,
            .action-btn,
            .mobile-menu-toggle,
            .mobile-menu-close {
                min-width: 44px;
                min-height: 44px;
            }
            
            .user-toggle {
                min-height: 44px;
            }
            
            /* Remove hover effects for touch */
            .nav-btn:hover,
            .action-btn:hover,
            .user-toggle:hover {
                transform: none;
            }
            
            /* Add active states for touch */
            .nav-btn:active,
            .action-btn:active,
            .user-toggle:active {
                transform: scale(0.95);
                background: rgba(255, 255, 255, 0.3);
            }
        }
        
        /* High DPI screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            /* Enhance visual quality */
            .brand-logo,
            .user-avatar {
                border-width: 1.5px;
            }
            
            @media (min-width: 768px) {
                .brand-logo,
                .user-avatar {
                    border-width: 2px;
                }
            }
        }
        
        /* Print styles */
        @media print {
            .top-navbar,
            .quick-stats-bar,
            .admin-sidebar {
                display: none !important;
            }
            
            .main-content-wrapper {
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        
        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .brand-logo::before {
                animation: none !important;
            }
            
            .notification-badge {
                animation: none !important;
            }
            
            .sidebar-toggle {
                animation: none !important;
            }
        }
        
        /* Dark mode adjustments for mobile */
        @media (prefers-color-scheme: dark) and (max-width: 768px) {
            .search-input {
                background: rgba(0, 0, 0, 0.3);
            }
            
            .quick-stat {
                background: rgba(0, 0, 0, 0.2);
            }
        }

        :root {
            --topbar-height: 64px;
        }

        .top-navbar {
            height: var(--topbar-height);
            padding: 0 20px;
            gap: 12px;
            background: #fff;
            background-color: #fff;
            border-bottom: 1px solid #e8ecef;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .brand-section {
            gap: 10px;
            min-width: 0;
        }

        .brand-container {
            gap: 10px;
            padding: 4px 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .brand-container:hover {
            background: transparent;
            transform: none;
            box-shadow: none;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 10px;
            background: #eaf4ee;
            box-shadow: none;
        }

        .brand-logo img {
            object-fit: contain;
        }

        .brand-logo i {
            color: var(--primary-green);
        }

        .brand-name {
            max-width: 220px;
            color: #1f2937;
            background: none;
            -webkit-text-fill-color: currentColor;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0;
            text-shadow: none;
        }

        .brand-tagline {
            color: #7b8794;
            font-size: 0.66rem;
            letter-spacing: 0.04em;
        }

        .branch-badge {
            padding: 6px 9px;
            border: 1px solid #dcebe1;
            border-radius: 7px;
            background: #f3f8f5;
            color: #356b4b;
            font-size: 0.76rem;
            white-space: nowrap;
        }

        .nav-controls {
            gap: 6px;
            margin: 0 0 0 auto;
        }

        .nav-btn,
        .action-btn,
        .mobile-menu-toggle {
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

        .nav-btn::before {
            display: none;
        }

        .nav-btn:hover,
        .action-btn:hover,
        .mobile-menu-toggle:hover {
            transform: none;
            border-color: #cfe2d5;
            background: #f4f8f5;
            color: var(--primary-green);
            box-shadow: none;
        }

        .search-section {
            max-width: 420px;
            margin: 0 12px;
        }

        .search-input {
            height: 40px;
            padding: 8px 40px 8px 13px;
            border: 1px solid #e4e9e6;
            border-radius: 9px;
            background: #f7f9f8;
            color: #263238;
            box-shadow: none;
            transform: none;
        }

        .search-input:focus {
            border-color: #9dc9aa;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
            transform: none;
        }

        .search-input::placeholder {
            color: #8a9690;
        }

        .search-btn {
            color: #65756b;
        }

        .search-btn:hover {
            transform: translateY(-50%);
            color: var(--primary-green);
        }

        .actions-section {
            gap: 7px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 0;
            text-decoration: none;
        }

        .user-toggle {
            gap: 9px;
            padding: 4px 7px 4px 4px;
            border: 1px solid transparent;
            border-radius: 10px;
            background: transparent;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .user-toggle:hover {
            transform: none;
            border-color: #e8ece9;
            background: #f7f9f8;
            box-shadow: none;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border: 0;
            background: #eaf4ee;
            box-shadow: none;
            color: #287348;
        }

        .user-info .user-name {
            color: #27332c;
        }

        .user-toggle .fa-chevron-down {
            color: #7b8794;
        }

        .notification-badge {
            top: -3px;
            right: -3px;
        }

        @media (max-width: 767px) {
            :root {
                --topbar-height: 60px;
            }

            .top-navbar {
                padding: 0 12px;
                gap: 7px;
            }

            .brand-container {
                gap: 8px;
            }

            .brand-logo {
                width: 34px;
                height: 34px;
            }

            .brand-name {
                max-width: 170px;
                font-size: 0.88rem;
            }

            .brand-tagline {
                font-size: 0.58rem;
            }

            .search-section {
                display: none;
            }

            .nav-controls {
                margin-right: 0;
            }

            .nav-controls #statsToggle {
                display: none;
            }

            .actions-section {
                gap: 4px;
            }

            .nav-btn,
            .action-btn,
            .mobile-menu-toggle {
                width: 38px;
                height: 38px;
                min-width: 38px;
            }

            .user-toggle {
                padding: 2px;
            }

            .user-avatar {
                width: 34px;
                height: 34px;
            }

            .accountant-layout .mobile-menu-toggle {
                display: none;
            }
        }

        @media (max-width: 400px) {
            .brand-name {
                max-width: 132px;
            }

            .branch-badge {
                display: none;
            }
        }
        
        /* Admin Sidebar Responsive Styles */
        @media (max-width: 767px) {
            /* Hide admin sidebar by default on mobile */
            .admin-sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: var(--shadow-lg);
                top: var(--topbar-height);
                height: calc(100vh - var(--topbar-height));
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            /* When admin sidebar is open, overlay content */
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
</head>
<body class="<?php echo ($_SESSION['user_role'] ?? '') === 'accountant' ? 'accountant-layout' : ''; ?>">
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Top Navigation Bar -->
    <nav class="top-navbar" id="topNavbar">
        <!-- Brand Section -->
        <div class="brand-section">
            <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="brand-container">
                <div class="brand-logo">
                    <?php if (!empty($logo_path)): ?>
                        <img src="<?php echo $logo_path; ?>" alt="<?php echo $company_name; ?>">
                    <?php else: ?>
                        <i class="fas fa-gas-pump"></i>
                    <?php endif; ?>
                </div>
                <div class="brand-text">
                    <span class="brand-name"><?php echo $company_name; ?></span>
                    <span class="brand-tagline">Petroleum Management</span>
                </div>
            </a>
        </div>
        
        <!-- Navigation Controls -->
        <div class="nav-controls">
            <!-- Sidebar Toggle -->
            <button class="nav-btn sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Quick Stats Toggle -->
            <button class="nav-btn" id="statsToggle" title="Quick Stats">
                <i class="fas fa-chart-line"></i>
            </button>
        </div>
        
        <!-- Search Section -->
        <div class="search-section">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search transactions, reports, users...">
                <button class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <!-- Actions Section -->
        <div class="actions-section">
            <!-- Theme Toggle -->
            <button class="nav-btn" id="themeToggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            
            <!-- Notifications -->
            <div class="action-item">
                <a href="#" class="action-btn" id="notificationsToggle" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Messages -->
            <div class="action-item">
                <a href="#" class="action-btn" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($message_count > 0): ?>
                        <span class="notification-badge"><?php echo $message_count; ?></span>
                    <?php endif; ?>
                </a>
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
                        <?php 
                        $role_labels = [
                            'admin' => 'Administrator',
                            'manager' => 'Manager',
                            'attendant' => 'Attendant',
                            'accountant' => 'Accountant'
                        ];
                        $role_colors = [
                            'admin' => '#dc3545',
                            'manager' => '#ffc107',
                            'attendant' => '#0dcaf0',
                            'accountant' => '#198754'
                        ];
                        ?>
                        <span class="user-role-badge" style="background: <?php echo $role_colors[$_SESSION['user_role']] ?? '#6c757d'; ?>22; color: <?php echo $role_colors[$_SESSION['user_role']] ?? '#6c757d'; ?>;">
                            <?php echo $role_labels[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']); ?>
                        </span>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                
                <!-- User Dropdown Menu -->
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <!-- Header -->
                    <div class="user-dropdown-header">
                        <div class="user-avatar">
                            <?php if (!empty($user_initials)): ?>
                                <?php echo $user_initials; ?>
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                            <span class="user-role-badge" style="background: rgba(255, 255, 255, 0.2); color: white;">
                                <?php echo $role_labels[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="user-dropdown-body">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>
                        
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Account Settings</span>
                        </a>
                        
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Security</span>
                        </a>
                        
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <?php if ($notification_count > 0): ?>
                                <span class="badge bg-danger ms-auto"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-question-circle"></i>
                            <span>Help & Support</span>
                        </a>
                        
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-info-circle"></i>
                            <span>About System</span>
                        </a>
                    </div>
                    
                    <!-- Footer -->
                    <div class="dropdown-footer">
                        <a href="../public/logout.php" class="btn btn-danger btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Quick Stats Bar -->
    <div class="quick-stats-bar" id="quickStatsBar">
        <div class="quick-stat">
            <span class="quick-stat-label">Today's Sales</span>
            <span class="quick-stat-value">
                $<?php echo number_format($quick_stats['today_sales'], 2); ?>
                <span class="quick-stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i> 12.5%
                </span>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Active Branches</span>
            <span class="quick-stat-value">
                <?php echo $quick_stats['branches']; ?>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Fuel Sold</span>
            <span class="quick-stat-value">
                <?php echo number_format($quick_stats['fuel_sold'], 2); ?> L
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Low Stock</span>
            <span class="quick-stat-value">
                <?php echo $quick_stats['low_stock']; ?>
                <span class="quick-stat-trend trend-down">
                    <i class="fas fa-exclamation"></i>
                </span>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Pending Tasks</span>
            <span class="quick-stat-value">
                <?php echo $quick_stats['pending_tasks']; ?>
                <span class="quick-stat-trend trend-up">
                    <i class="fas fa-clock"></i>
                </span>
            </span>
        </div>
    </div>
    
    <!-- Notifications Panel -->
    <div class="notifications-panel" id="notificationsPanel">
        <div class="notifications-header p-3 border-bottom">
            <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Notifications</h5>
            <button class="btn btn-sm btn-link position-absolute end-0 top-0 p-3" id="closeNotifications">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notifications-body p-3">
            <!-- Notifications will be loaded here -->
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No new notifications</p>
            </div>
        </div>
    </div>
    
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
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-muted"><?php echo $role_labels[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']); ?></small>
                </div>
            </div>
            <button class="mobile-menu-close btn" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-menu-items p-3">
            <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <?php if ($_SESSION['user_role'] == 'admin'): ?>
            <a href="../admin/branches.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-building"></i>
                <span>Branches</span>
            </a>
            <a href="../admin/users.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="../admin/reports.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>
            <?php if ($_SESSION['user_role'] === 'manager'): ?>
            <a href="../manager/sales.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-cash-register"></i><span>Sales</span>
            </a>
            <a href="../manager/expenses.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-file-invoice-dollar"></i><span>Expenses</span>
            </a>
            <a href="../manager/reports.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-chart-bar"></i><span>Reports</span>
            </a>
            <?php elseif ($_SESSION['user_role'] === 'attendant'): ?>
            <a href="../attendant/sales.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-gas-pump"></i><span>New Sale</span>
            </a>
            <a href="../attendant/sales_history.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-history"></i><span>Sales History</span>
            </a>
            <a href="../attendant/tanks.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-oil-can"></i><span>Tank Levels</span>
            </a>
            <?php elseif ($_SESSION['user_role'] === 'accountant'): ?>
            <a href="../accountant/invoices.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-file-invoice"></i><span>Invoices</span>
            </a>
            <a href="../accountant/payments.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-money-check-alt"></i><span>Payments</span>
            </a>
            <a href="../accountant/reports.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-chart-pie"></i><span>Financial Reports</span>
            </a>
            <?php endif; ?>
            <a href="../public/logout.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none text-danger mt-4">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Admin Sidebar -->
    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <?php
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
        /* Admin Sidebar Specific Styles - RESPONSIVE */
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

        .admin-submenu-link {
            padding: 8px 10px 8px 18px;
            font-size: 0.84rem;
        }

        .admin-submenu-link:hover,
        .admin-submenu-link.active {
            padding-left: 18px;
            background: #f4f7f5;
        }

        .sidebar-collapsed .admin-sidebar {
            width: var(--sidebar-collapsed);
        }

        .admin-sidebar-header {
            padding: 15px 12px; /* Reduced padding for mobile */
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
            background: white;
            position: relative;
            min-height: 80px; /* Reduced height */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .sidebar-collapsed .admin-sidebar-header {
            padding: 12px 8px; /* Further reduced for collapsed */
        }

        @media (min-width: 768px) {
            .admin-sidebar-header {
                padding: 20px 15px;
                min-height: 100px;
            }
            
            .sidebar-collapsed .admin-sidebar-header {
                padding: 15px 10px;
            }
        }

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px; /* Reduced gap */
            margin-top: 12px; /* Reduced margin */
        }

        .sidebar-collapsed .admin-stats {
            display: none;
        }

        @media (max-width: 767px) {
            .admin-stats {
                display: grid !important; /* Force display on mobile when sidebar is open */
            }
        }

        @media (max-width: 576px) {
            .accountant-layout .nav-controls .sidebar-toggle {
                display: flex !important;
            }
        }

        .admin-stat {
            text-align: center;
            padding: 6px; /* Reduced padding */
            border-radius: 6px; /* Smaller radius */
            background: rgba(25, 135, 84, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 50px; /* Minimum height for touch */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .admin-stat {
                padding: 8px;
                border-radius: 8px;
                min-height: 60px;
            }
        }

        .admin-stat:hover {
            background: rgba(25, 135, 84, 0.1);
            transform: translateY(-2px);
        }

        .admin-stat-value {
            font-weight: 800;
            font-size: 0.9rem; /* Smaller for mobile */
            color: var(--primary-green);
            line-height: 1;
        }

        @media (min-width: 768px) {
            .admin-stat-value {
                font-size: 1.1rem;
            }
        }

        .admin-stat-label {
            font-size: 0.6rem; /* Smaller for mobile */
            color: #666;
            margin-top: 2px;
            white-space: nowrap;
        }

        @media (min-width: 768px) {
            .admin-stat-label {
                font-size: 0.65rem;
                margin-top: 3px;
            }
        }

        /* Navigation Menu */
        .admin-nav {
            padding: 12px 0; /* Reduced padding */
            flex: 1;
            overflow-y: auto;
        }

        @media (min-width: 768px) {
            .admin-nav {
                padding: 15px 0;
            }
        }

        .admin-nav-section {
            margin-bottom: 12px; /* Reduced margin */
        }

        @media (min-width: 768px) {
            .admin-nav-section {
                margin-bottom: 15px;
            }
        }

        .admin-nav-label {
            font-size: 0.7rem; /* Smaller for mobile */
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
            padding: 8px 15px; /* Reduced padding */
            margin-bottom: 4px;
            background: rgba(0, 0, 0, 0.02);
            border-left: 3px solid var(--primary-green);
        }

        .sidebar-collapsed .admin-nav-label {
            display: none;
        }

        @media (min-width: 768px) {
            .admin-nav-label {
                font-size: 0.75rem;
                letter-spacing: 0.5px;
                padding: 10px 20px;
                margin-bottom: 5px;
            }
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
            padding: 10px 15px; /* Reduced padding */
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            min-height: 44px; /* Minimum touch target */
        }

        .sidebar-collapsed .admin-nav-link {
            padding: 10px 8px; /* Reduced padding for collapsed */
            justify-content: center;
        }

        @media (min-width: 768px) {
            .admin-nav-link {
                padding: 12px 20px;
            }
            
            .sidebar-collapsed .admin-nav-link {
                padding: 12px 10px;
            }
        }

        .admin-nav-link:hover {
            background: linear-gradient(to right, rgba(25, 135, 84, 0.05), transparent);
            color: var(--primary-green);
            padding-left: 20px; /* Reduced hover padding */
        }

        .sidebar-collapsed .admin-nav-link:hover {
            padding-left: 10px;
        }

        @media (min-width: 768px) {
            .admin-nav-link:hover {
                padding-left: 25px;
            }
        }

        .admin-nav-link.active {
            background: linear-gradient(to right, rgba(25, 135, 84, 0.1), transparent);
            color: var(--primary-green);
            border-left-color: var(--primary-green);
            font-weight: 600;
        }

        .admin-nav-icon {
            font-size: 1rem; /* Smaller for mobile */
            width: 22px; /* Smaller for mobile */
            text-align: center;
            margin-right: 10px; /* Reduced margin */
            color: #666;
            transition: all 0.3s ease;
        }

        .sidebar-collapsed .admin-nav-icon {
            margin-right: 0;
            font-size: 1.1rem; /* Slightly smaller */
        }

        @media (min-width: 768px) {
            .admin-nav-icon {
                font-size: 1.1rem;
                width: 24px;
                margin-right: 12px;
            }
            
            .sidebar-collapsed .admin-nav-icon {
                font-size: 1.2rem;
            }
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
            font-size: 0.85rem; /* Smaller for mobile */
        }

        .sidebar-collapsed .admin-nav-text {
            display: none;
        }

        @media (min-width: 768px) {
            .admin-nav-text {
                font-size: 0.9rem;
            }
        }

        .admin-nav-badge {
            background: var(--primary-green);
            color: white;
            font-size: 0.6rem; /* Smaller for mobile */
            padding: 2px 5px; /* Reduced padding */
            border-radius: 8px; /* Smaller radius */
            margin-left: 4px;
            min-width: 16px; /* Smaller */
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
            font-size: 0.5rem; /* Smaller */
            padding: 1px 3px; /* Reduced */
            min-width: 14px; /* Smaller */
        }

        @media (min-width: 768px) {
            .admin-nav-badge {
                font-size: 0.65rem;
                padding: 2px 6px;
                border-radius: 10px;
                margin-left: 5px;
                min-width: 18px;
            }
            
            .sidebar-collapsed .admin-nav-badge {
                font-size: 0.55rem;
                padding: 1px 4px;
                min-width: 16px;
            }
        }

        /* Submenu */
        .admin-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.02);
            border-left: 3px solid var(--primary-yellow);
            margin-left: 15px; /* Reduced margin */
            border-radius: 0 0 6px 6px;
        }

        .admin-nav-item.has-dropdown.active .admin-submenu {
            max-height: 500px;
        }

        .sidebar-collapsed .admin-submenu {
            display: none !important;
        }

        @media (min-width: 768px) {
            .admin-submenu {
                margin-left: 20px;
                border-radius: 0 0 8px 8px;
            }
        }

        .admin-submenu-item {
            list-style: none;
        }

        .admin-submenu-link {
            display: flex;
            align-items: center;
            padding: 8px 12px 8px 25px; /* Reduced padding */
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.85rem; /* Smaller */
            min-height: 40px; /* Touch target */
        }

        .admin-submenu-link:hover,
        .admin-submenu-link.active {
            color: var(--primary-green);
            background: rgba(25, 135, 84, 0.05);
            padding-left: 30px; /* Reduced hover padding */
        }

        .admin-submenu-link i {
            font-size: 0.75rem; /* Smaller */
            margin-right: 6px; /* Reduced */
            width: 14px; /* Smaller */
            text-align: center;
        }

        @media (min-width: 768px) {
            .admin-submenu-link {
                padding: 10px 15px 10px 30px;
                font-size: 0.9rem;
            }
            
            .admin-submenu-link:hover,
            .admin-submenu-link.active {
                padding-left: 35px;
            }
            
            .admin-submenu-link i {
                font-size: 0.8rem;
                margin-right: 8px;
                width: 16px;
            }
        }

        /* Quick Admin Actions */
        .admin-quick-actions {
            padding: 12px; /* Reduced padding */
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            background: white;
        }

        .sidebar-collapsed .admin-quick-actions {
            padding: 8px; /* Reduced for collapsed */
        }

        @media (min-width: 768px) {
            .admin-quick-actions {
                padding: 15px;
            }
            
            .sidebar-collapsed .admin-quick-actions {
                padding: 10px;
            }
        }

        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px; /* Reduced gap */
            margin-bottom: 8px; /* Reduced margin */
        }

        .sidebar-collapsed .quick-action-grid {
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .quick-action-grid {
                gap: 8px;
                margin-bottom: 10px;
            }
        }

        .quick-action-btn {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--green-dark) 100%);
            color: white;
            border: none;
            padding: 6px; /* Reduced padding */
            border-radius: 6px; /* Smaller radius */
            font-size: 0.7rem; /* Smaller */
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px; /* Reduced gap */
            text-align: center;
            min-height: 36px; /* Smaller touch target */
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }

        .quick-action-btn i {
            font-size: 0.8rem; /* Smaller */
        }

        .sidebar-collapsed .quick-action-btn span {
            display: none;
        }

        @media (min-width: 768px) {
            .quick-action-btn {
                padding: 8px;
                border-radius: 8px;
                font-size: 0.75rem;
                gap: 5px;
                min-height: 40px;
            }
            
            .quick-action-btn i {
                font-size: 0.9rem;
            }
        }

        /* System Status */
        .system-status {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 6px; /* Smaller radius */
            padding: 8px; /* Reduced padding */
            margin-top: 8px; /* Reduced margin */
            font-size: 0.8rem; /* Smaller */
        }

        .sidebar-collapsed .system-status {
            display: none;
        }

        @media (min-width: 768px) {
            .system-status {
                border-radius: 8px;
                padding: 10px;
                margin-top: 10px;
                font-size: 0.8rem;
            }
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px; /* Reduced margin */
            font-size: 0.75rem; /* Smaller */
        }

        .status-item:last-child {
            margin-bottom: 0;
        }

        @media (min-width: 768px) {
            .status-item {
                margin-bottom: 5px;
                font-size: 0.8rem;
            }
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
            padding: 6px 10px; /* Reduced padding */
            border-radius: 6px; /* Smaller radius */
            font-size: 0.75rem; /* Smaller */
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
            left: calc(100% + 10px); /* Reduced distance */
        }

        /* Scrollbar styling */
        .admin-nav::-webkit-scrollbar {
            width: 3px; /* Thinner */
        }

        .admin-nav::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }

        .admin-nav::-webkit-scrollbar-thumb {
            background: rgba(25, 135, 84, 0.3);
            border-radius: 8px;
        }

        .admin-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(25, 135, 84, 0.5);
        }

        /* Mobile Responsive */
        @media (max-width: 767px) {
            .admin-sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: var(--shadow-lg);
                top: var(--topbar-height);
                height: calc(100vh - var(--topbar-height));
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

            /* When menu is open on mobile */
            body.menu-open .admin-sidebar {
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

        /* Dropdown arrow */
        .admin-dropdown-arrow {
            transition: transform 0.3s ease;
            margin-left: auto;
            font-size: 0.75rem; /* Smaller */
            color: #666;
        }

        .admin-nav-item.has-dropdown.active .admin-dropdown-arrow {
            transform: rotate(180deg);
            color: var(--primary-green);
        }

        .sidebar-collapsed .admin-dropdown-arrow {
            display: none;
        }

        @media (min-width: 768px) {
            .admin-dropdown-arrow {
                font-size: 0.8rem;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .admin-nav-link,
            .admin-submenu-link,
            .admin-stat,
            .quick-action-btn {
                min-height: 44px; /* Minimum touch target */
            }
            
            .admin-nav-link:active,
            .admin-submenu-link:active {
                background: rgba(25, 135, 84, 0.15);
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

    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Admin Sidebar Header -->
        <div class="admin-sidebar-header">
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

        <!-- Quick Actions & System Status -->
        <div class="admin-quick-actions">
            <div class="quick-action-grid">
                <button class="quick-action-btn" onclick="window.location.href='users.php?action=add'">
                    <i class="fas fa-user-plus"></i>
                    <span>Add User</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='reports.php'">
                    <i class="fas fa-chart-pie"></i>
                    <span>Reports</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='backup_restore.php'">
                    <i class="fas fa-database"></i>
                    <span>Backup</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='settings.php'">
                    <i class="fas fa-cogs"></i>
                    <span>Settings</span>
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
    <?php else: ?>
        <!-- For other roles, include their sidebar -->
        <?php 
        // Determine which sidebar to include based on user role
        $sidebar_file = "sidebar_" . $_SESSION['user_role'] . ".php";
        if ($_SESSION['user_role'] === 'accountant') {
            include __DIR__ . "/../accountant/accountant_sidebar.php";
        } elseif (file_exists(__DIR__ . "/../includes/" . $sidebar_file)) {
            include __DIR__ . "/../includes/" . $sidebar_file;
        } else {
            include __DIR__ . "/../includes/sidebar_general.php";
        }
        ?>
    <?php endif; ?>
    
    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper" id="mainContentWrapper">
        <div class="container-fluid py-4 px-4">
    <?php else: ?>
        <!-- For non-logged in pages -->
        <div class="container-fluid mt-3">
    <?php endif; ?>

<!-- JavaScript for Header Functionality - RESPONSIVE -->
<script>
$(document).ready(function() {
    // Get DOM elements
    const body = $('body');
    const sidebarToggle = $('#sidebarToggle');
    const adminSidebar = $('#adminSidebar, #accountantSidebar');
    const mobileMenuToggle = $('#mobileMenuToggle');
    const mobileMenu = $('#mobileMenu');
    const mobileMenuClose = $('#mobileMenuClose');
    const statsToggle = $('#statsToggle');
    const quickStatsBar = $('#quickStatsBar');
    const themeToggle = $('#themeToggle');
    const userDropdownToggle = $('#userDropdownToggle');
    const userDropdownMenu = $('#userDropdownMenu');
    const notificationsToggle = $('#notificationsToggle');
    const notificationsPanel = $('#notificationsPanel');
    const closeNotifications = $('#closeNotifications');
    const searchBtn = $('.search-btn');
    const searchInput = $('.search-input');

    // Check if we're on mobile
    function isMobile() {
        return $(window).width() <= 768;
    }

    // Check if we're on tablet
    function isTablet() {
        return $(window).width() > 768 && $(window).width() <= 1024;
    }

    // Toggle Sidebar - Responsive
    sidebarToggle.click(function(e) {
        e.stopPropagation();
        
        if (isMobile()) {
            // For mobile devices
            body.toggleClass('menu-open');
            adminSidebar.toggleClass('show');
            $('#accountantSidebarOverlay').toggleClass('show');
            $(this).find('i').toggleClass('fa-bars fa-times');
        } else {
            // For desktop and tablet - collapse/expand
            body.toggleClass('sidebar-collapsed');
            $(this).find('i').toggleClass('fa-bars fa-times');
        }
    });

    // Close sidebar when clicking outside on mobile
    $(document).click(function(e) {
        if (isMobile()) {
            if (body.hasClass('menu-open') && 
                !$(e.target).closest('#adminSidebar, #accountantSidebar').length && 
                !$(e.target).closest('#sidebarToggle').length) {
                body.removeClass('menu-open');
                adminSidebar.removeClass('show');
                $('#accountantSidebarOverlay').removeClass('show');
                sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
            }
        }
    });

    // Toggle Quick Stats
    statsToggle.click(function() {
        quickStatsBar.toggleClass('show');
        $(this).find('i').toggleClass('fa-chart-line fa-chart-bar');
    });

    // Toggle Theme
    themeToggle.click(function() {
        const currentTheme = $('html').attr('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        $('html').attr('data-bs-theme', newTheme);
        
        // Update icon
        const icon = $(this).find('i');
        if (newTheme === 'dark') {
            icon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            icon.removeClass('fa-sun').addClass('fa-moon');
        }
        
        // Save theme preference to localStorage
        localStorage.setItem('theme', newTheme);
        
        // Show notification
        showNotification(`Switched to ${newTheme} mode`, 'info');
    });
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        $('html').attr('data-bs-theme', savedTheme);
        if (savedTheme === 'dark') {
            themeToggle.find('i').removeClass('fa-moon').addClass('fa-sun');
        }
    }

    // User Dropdown
    userDropdownToggle.click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        userDropdownMenu.toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('.user-profile').length) {
            userDropdownMenu.removeClass('show');
        }
    });

    // Mobile Menu Toggle
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

    // Notifications Toggle
    notificationsToggle.click(function(e) {
        e.preventDefault();
        notificationsPanel.toggleClass('show');
    });

    // Close notifications
    closeNotifications.click(function() {
        notificationsPanel.removeClass('show');
    });

    // Close notifications when clicking outside
    $(document).click(function(e) {
        if (notificationsPanel.hasClass('show') && 
            !$(e.target).closest('#notificationsPanel').length && 
            !$(e.target).closest('#notificationsToggle').length) {
            notificationsPanel.removeClass('show');
        }
    });

    // Search functionality
    searchInput.on('keypress', function(e) {
        if (e.which === 13) {
            const query = $(this).val().trim();
            if (query) {
                performSearch(query);
            }
        }
    });

    searchBtn.click(function() {
        const query = searchInput.val().trim();
        if (query) {
            performSearch(query);
        }
    });

    // Search function
    function performSearch(query) {
        showNotification(`Searching for: ${query}`, 'info');
        // In production, you would make an AJAX call to search endpoint
        // window.location.href = `/search?q=${encodeURIComponent(query)}`;
    }

    // Notification function
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = $(`
            <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100;">
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `);
        
        // Add to body
        $('body').append(notification);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(notification.find('.toast')[0]);
        toast.show();
        
        // Remove after hiding
        notification.find('.toast').on('hidden.bs.toast', function() {
            notification.remove();
        });
    }

    // Update quick stats periodically
    function updateQuickStats() {
        $.ajax({
            url: '../api/get_quick_stats.php',
            method: 'GET',
            success: function(data) {
                if (data.success) {
                    // Update stats in the quick stats bar
                    $('#quickStatsBar .quick-stat:nth-child(1) .quick-stat-value').html(
                        `$${parseFloat(data.today_sales).toFixed(2)}`
                    );
                    $('#quickStatsBar .quick-stat:nth-child(2) .quick-stat-value').html(
                        data.branches
                    );
                    $('#quickStatsBar .quick-stat:nth-child(3) .quick-stat-value').html(
                        `${parseFloat(data.fuel_sold).toFixed(2)} L`
                    );
                    $('#quickStatsBar .quick-stat:nth-child(4) .quick-stat-value').html(
                        data.low_stock
                    );
                    $('#quickStatsBar .quick-stat:nth-child(5) .quick-stat-value').html(
                        data.pending_tasks
                    );
                    
                    // Update notification badge
                    const totalNotifications = parseInt(data.low_stock) + parseInt(data.pending_tasks);
                    if (totalNotifications > 0) {
                        $('.notification-badge').text(totalNotifications).show();
                    } else {
                        $('.notification-badge').hide();
                    }
                }
            },
            error: function() {
                console.log('Failed to update quick stats');
            }
        });
    }

    // Update stats every 30 seconds
    setInterval(updateQuickStats, 30000);

    // Initial stats update
    updateQuickStats();

    // Admin sidebar functionality - Responsive
    function initAdminSidebar() {
        // Dropdown functionality - only on desktop when sidebar is expanded
        $('.admin-nav-item.has-dropdown .admin-nav-link').click(function(e) {
            if ($(window).width() > 768 && !body.hasClass('sidebar-collapsed')) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdownItem = $(this).closest('.admin-nav-item.has-dropdown');
                const isActive = dropdownItem.hasClass('active');
                
                // Close all other dropdowns
                $('.admin-nav-item.has-dropdown.active').not(dropdownItem).removeClass('active');
                
                // Toggle current dropdown
                dropdownItem.toggleClass('active');
            } else if ($(window).width() <= 768) {
                // On mobile, navigate to the main link
                const href = $(this).attr('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            }
        });

        // Close dropdowns when clicking outside
        $(document).click(function(e) {
            if ($(window).width() > 768 && !body.hasClass('sidebar-collapsed')) {
                if (!$(e.target).closest('.admin-nav-item.has-dropdown').length) {
                    $('.admin-nav-item.has-dropdown.active').removeClass('active');
                }
            }
        });

        // Set active menu item based on current page
        function setActiveMenuItem() {
            const currentPage = window.location.pathname.split('/').pop();
            
            // Remove all active classes
            $('.admin-nav-link.active, .admin-submenu-link.active').removeClass('active');
            
            // Find and set active link
            const currentLink = $(`.admin-nav-link[href*="${currentPage}"], .admin-submenu-link[href*="${currentPage}"]`);
            if (currentLink.length) {
                currentLink.addClass('active');
                
                // Open parent dropdown if it's a submenu link
                const dropdownParent = currentLink.closest('.admin-nav-item.has-dropdown');
                if (dropdownParent.length) {
                    dropdownParent.addClass('active');
                }
            }
        }

        setActiveMenuItem();

        // Update sidebar stats periodically
        function updateAdminStats() {
            $.ajax({
                url: '../api/get_admin_stats.php',
                method: 'GET',
                success: function(data) {
                    if (data.success) {
                        // Update user count
                        const userStat = $('.admin-stat:nth-child(1) .admin-stat-value');
                        const userBadge = $('.admin-nav-item.has-dropdown .admin-nav-badge');
                        if (userStat.length) userStat.text(data.total_users);
                        if (userBadge.length) userBadge.text(data.total_users);
                        
                        // Update revenue
                        const revenueStat = $('.admin-stat:nth-child(2) .admin-stat-value');
                        if (revenueStat.length) revenueStat.text('$' + parseFloat(data.today_revenue).toLocaleString());
                        
                        // Update alerts
                        const alertStat = $('.admin-stat:nth-child(3) .admin-stat-value');
                        const alertBadges = $('.admin-nav-badge.danger, .admin-nav-badge.warning');
                        if (alertStat.length) alertStat.text(data.system_alerts);
                        alertBadges.each(function() {
                            const badgeText = $(this).text();
                            if (!isNaN(badgeText) && badgeText.trim() !== '') {
                                $(this).text(data.system_alerts);
                            }
                        });
                    }
                },
                error: function(error) {
                    console.error('Error updating admin stats:', error);
                }
            });
        }

        // Update stats every 2 minutes
        setInterval(updateAdminStats, 120000);

        // Quick action buttons functionality
        $('.quick-action-btn').click(function(e) {
            const action = $(this).attr('onclick');
            if (action) {
                // Show loading state
                const originalHTML = $(this).html();
                $(this).html('<i class="fas fa-spinner fa-spin"></i> <span>Loading...</span>');
                $(this).prop('disabled', true);
                
                // Restore button after 2 seconds
                setTimeout(() => {
                    $(this).html(originalHTML);
                    $(this).prop('disabled', false);
                }, 2000);
            }
        });

        // Keyboard shortcuts for admin
        $(document).on('keydown', function(e) {
            // Only enable shortcuts on desktop
            if ($(window).width() <= 768) return;
            
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
                $('.admin-nav-item.has-dropdown.active').removeClass('active');
            }
        });

        // Initialize system status check
        function checkSystemStatus() {
            $.ajax({
                url: '../api/check_system_status.php',
                method: 'GET',
                success: function(data) {
                    if (data.success) {
                        const systemStatus = $('.status-item:nth-child(1) .status-value');
                        const dbStatus = $('.status-item:nth-child(2) .status-value');
                        const backupStatus = $('.status-item:nth-child(3) .status-value');
                        
                        if (systemStatus.length) {
                            systemStatus.removeClass('online offline warning')
                                .addClass(data.system_online ? 'online' : 'offline')
                                .text(data.system_online ? 'Online' : 'Offline');
                        }
                        
                        if (dbStatus.length) {
                            dbStatus.removeClass('online offline warning')
                                .addClass(data.database_connected ? 'online' : 'offline')
                                .text(data.database_connected ? 'Connected' : 'Disconnected');
                        }
                        
                        if (backupStatus.length) {
                            const hoursAgo = data.last_backup_hours;
                            let statusClass = 'warning';
                            let statusText = '';
                            
                            if (hoursAgo <= 24) {
                                statusClass = 'online';
                                statusText = hoursAgo + 'h ago';
                            } else if (hoursAgo <= 72) {
                                statusClass = 'warning';
                                statusText = Math.floor(hoursAgo / 24) + 'd ago';
                            } else {
                                statusClass = 'offline';
                                statusText = Math.floor(hoursAgo / 24) + 'd ago';
                            }
                            
                            backupStatus.removeClass('online offline warning')
                                .addClass(statusClass)
                                .text(statusText);
                        }
                    }
                },
                error: function(error) {
                    console.error('Error checking system status:', error);
                }
            });
        }

        // Check system status every 5 minutes
        setInterval(checkSystemStatus, 300000);

        // Initial checks
        updateAdminStats();
        checkSystemStatus();
    }

    // Initialize admin sidebar if it exists
    if ($('#adminSidebar').length) {
        initAdminSidebar();
    }

    // Smooth scroll for page anchors
    $('a[href^="#"]').on('click', function(e) {
        if ($(this).attr('href') === '#') return;
        
        e.preventDefault();
        const target = $(this).attr('href');
        const $target = $(target);
        
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 100
            }, 500);
        }
    });

    // Handle window resize - Responsive
    let resizeTimer;
    $(window).resize(function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if ($(window).width() > 768) {
                // Reset mobile menu state when resizing to desktop
                body.removeClass('menu-open');
                adminSidebar.removeClass('show');
                mobileMenu.removeClass('show');
                body.removeClass('no-scroll');
                sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
            } else {
                // Reset desktop collapse state when resizing to mobile
                body.removeClass('sidebar-collapsed');
                sidebarToggle.find('i').removeClass('fa-times').addClass('fa-bars');
            }
            
            // Update sidebar tooltips
            updateSidebarTooltips();
        }, 250);
    });

    // Update sidebar tooltips based on screen size
    function updateSidebarTooltips() {
        if (body.hasClass('sidebar-collapsed') || $(window).width() <= 768) {
            $('.admin-nav-link').each(function() {
                const text = $(this).find('.admin-nav-text').text() || $(this).attr('data-tooltip');
                $(this).attr('data-tooltip', text);
            });
        }
    }

    // Initialize tooltips
    updateSidebarTooltips();

    // Add animation to elements on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1
    });

    // Observe elements for animation
    $('.stat-card').each(function() {
        $(this).css({
            opacity: '0',
            transform: 'translateY(20px)',
            transition: 'opacity 0.5s ease, transform 0.5s ease'
        });
        observer.observe(this);
    });

    // Touch device optimizations
    if ('ontouchstart' in window) {
        // Add touch feedback for buttons
        $('.nav-btn, .action-btn, .admin-nav-link, .admin-submenu-link, .admin-stat, .quick-action-btn').on('touchstart', function() {
            $(this).addClass('touch-active');
        }).on('touchend', function() {
            $(this).removeClass('touch-active');
        });
        
        // Prevent zoom on double-tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    }
});
</script>