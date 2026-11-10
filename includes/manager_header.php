<?php
session_start();
require_once '../config/db.php';

// Check if user is manager and logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header("Location: ../public/login.php");
    exit();
}

// Get manager's branch details
$branch_id = $_SESSION['branch_id'];
$branch_sql = "SELECT * FROM branches WHERE id = $branch_id";
$branch_result = $conn->query($branch_sql);
$branch = $branch_result->fetch_assoc();

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

// Get notifications count for manager
$notification_count = 0;
$message_count = 0;
$today = date('Y-m-d');

// Low stock notifications
$low_stock_sql = "SELECT COUNT(*) as count FROM tanks 
                  WHERE branch_id = $branch_id 
                  AND current_quantity < minimum_threshold 
                  AND status = 'active'";
$low_stock_result = $conn->query($low_stock_sql);
$low_stock_count = (int)$low_stock_result->fetch_assoc()['count'];
$notification_count += $low_stock_count;

// Pending expenses
$pending_sql = "SELECT COUNT(*) as count FROM expenses 
                WHERE branch_id = $branch_id 
                AND status = 'pending'";
$pending_result = $conn->query($pending_sql);
$pending_expense_count = (int)$pending_result->fetch_assoc()['count'];
$notification_count += $pending_expense_count;

// Maintenance pumps
$maintenance_sql = "SELECT COUNT(*) as count FROM pumps 
                    WHERE branch_id = $branch_id 
                    AND status = 'maintenance'";
$maintenance_result = $conn->query($maintenance_sql);
$notification_count += $maintenance_result->fetch_assoc()['count'];

// Get quick stats for header
$quick_stats = [];

// Today's sales
$sales_sql = "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
              FROM sales 
              WHERE branch_id = $branch_id 
              AND DATE(sale_date) = '$today'";
$sales_result = $conn->query($sales_sql);
$sales_data = $sales_result->fetch_assoc();
$quick_stats['today_sales'] = $sales_data['total'];
$quick_stats['today_transactions'] = $sales_data['count'];

// Yesterday's sales for comparison
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_sql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                  FROM sales 
                  WHERE branch_id = $branch_id 
                  AND DATE(sale_date) = '$yesterday'";
$yesterday_result = $conn->query($yesterday_sql);
$yesterday_data = $yesterday_result->fetch_assoc();
$quick_stats['yesterday_sales'] = $yesterday_data['total'];

// Calculate sales growth
$growth = 0;
if ($quick_stats['yesterday_sales'] > 0) {
    $growth = round((($quick_stats['today_sales'] - $quick_stats['yesterday_sales']) / $quick_stats['yesterday_sales']) * 100, 1);
}
$quick_stats['sales_growth'] = $growth;

// Pending expenses count
$pending_count = $pending_expense_count;
$quick_stats['pending_expenses'] = $pending_count;

// Fuel sold today
$fuel_sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM sales 
             WHERE branch_id = $branch_id 
             AND DATE(sale_date) = '$today'";
$fuel_result = $conn->query($fuel_sql);
$quick_stats['fuel_sold'] = $fuel_result->fetch_assoc()['total'];

// Pending tasks
$quick_stats['pending_tasks'] = $pending_count + $low_stock_count;

// Get manager-specific stats
$manager_stats = [];
try {
    // Active attendants
    $attendants_sql = "SELECT COUNT(*) as count FROM users 
                       WHERE branch_id = ? 
                       AND role = 'attendant' 
                       AND status = 'active'";
    $attendants_stmt = $conn->prepare($attendants_sql);
    $attendants_stmt->bind_param("i", $branch_id);
    $attendants_stmt->execute();
    $attendants_result = $attendants_stmt->get_result();
    $manager_stats['active_attendants'] = (int)$attendants_result->fetch_assoc()['count'];
    $attendants_stmt->close();
    
    // Monthly revenue
    $month = date('Y-m');
    $monthly_sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales 
                    WHERE branch_id = ? 
                    AND DATE_FORMAT(sale_date, '%Y-%m') = ? 
                    AND status = 'completed'";
    $monthly_stmt = $conn->prepare($monthly_sql);
    $monthly_stmt->bind_param("is", $branch_id, $month);
    $monthly_stmt->execute();
    $monthly_result = $monthly_stmt->get_result();
    $manager_stats['monthly_revenue'] = (float)$monthly_result->fetch_assoc()['total'];
    $monthly_stmt->close();
    
    // Branch alerts
    $alerts_sql = "SELECT (
        (SELECT COUNT(*) FROM expenses WHERE branch_id = ? AND status = 'pending') +
        (SELECT COUNT(*) FROM tanks WHERE branch_id = ? AND current_quantity < minimum_threshold AND status = 'active') +
        (SELECT COUNT(*) FROM pumps WHERE branch_id = ? AND status = 'maintenance')
    ) as total_alerts";
    $alerts_stmt = $conn->prepare($alerts_sql);
    $alerts_stmt->bind_param("iii", $branch_id, $branch_id, $branch_id);
    $alerts_stmt->execute();
    $alerts_result = $alerts_stmt->get_result();
    $manager_stats['branch_alerts'] = (int)$alerts_result->fetch_assoc()['total_alerts'];
    $alerts_stmt->close();
    
} catch (Exception $e) {
    error_log("Manager stats error: " . $e->getMessage());
    $manager_stats = [
        'active_attendants' => 0,
        'monthly_revenue' => 0,
        'branch_alerts' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Manager Dashboard - <?php echo $company_name; ?>">
    <meta name="keywords" content="petroleum, fuel, management, system, manager">
    <meta name="author" content="<?php echo $company_name; ?>">
    
    <title>Manager Dashboard - <?php echo $company_name; ?></title>
    
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Manager Header Styles - Same as Admin -->
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
            --topbar-height: 60px;
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
        
        /* Responsive Top Navigation Bar - Same as Admin */
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
        
        /* Branch Badge */
        .branch-badge {
            margin-left: 8px;
            padding: 2px 8px;
            background: rgba(255, 215, 0, 0.2);
            color: var(--primary-yellow);
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(255, 215, 0, 0.3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
        }
        
        @media (min-width: 576px) {
            .branch-badge {
                max-width: 120px;
            }
        }
        
        @media (min-width: 768px) {
            .branch-badge {
                font-size: 0.65rem;
                padding: 3px 10px;
                max-width: 150px;
            }
        }
        
        /* Hide brand text on very small screens */
        @media (max-width: 400px) {
            .brand-tagline,
            .branch-badge {
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
            display: none;
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
        
        /* Quick Sale Button */
        .quick-sale-btn {
            background: linear-gradient(135deg, var(--primary-yellow), var(--yellow-light));
            color: var(--green-dark);
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .quick-sale-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
            background: linear-gradient(135deg, var(--yellow-light), var(--primary-yellow));
        }
        
        @media (max-width: 768px) {
            .quick-sale-btn span {
                display: none;
            }
            
            .quick-sale-btn {
                padding: 8px;
                width: 40px;
                height: 40px;
            }
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
        
        /* Hide specific elements on small screens */
        @media (max-width: 576px) {
            /* Hide some nav controls */
            .nav-controls .sidebar-toggle {
                display: none;
            }
            
            /* Hide some action items */
            .action-item:nth-child(2) {
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
            .user-role-badge,
            .branch-badge {
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
            .manager-sidebar {
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
        .brand-section { gap: 10px; min-width: 0; }
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
        .branch-badge { padding: 6px 9px; border: 1px solid #dcebe1; border-radius: 7px; background: #f3f8f5; color: #356b4b; font-size: .76rem; white-space: nowrap; }
        .nav-controls { gap: 6px; margin: 0 0 0 auto; }
        .nav-btn, .action-btn, .mobile-menu-toggle {
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
        .nav-btn:hover, .action-btn:hover, .mobile-menu-toggle:hover {
            transform: none;
            border-color: #cfe2d5;
            background: #f4f8f5;
            color: var(--primary-green);
            box-shadow: none;
        }
        .search-section { max-width: 380px; margin: 0 12px; }
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
        .search-input:focus { border-color: #9dc9aa; background: #fff; box-shadow: 0 0 0 3px rgba(25, 135, 84, .1); transform: none; }
        .search-input::placeholder { color: #8a9690; }
        .search-btn { color: #65756b; }
        .search-btn:hover { transform: translateY(-50%); color: var(--primary-green); }
        .actions-section { gap: 7px; }
        .action-btn { display: flex; align-items: center; justify-content: center; position: relative; padding: 0; text-decoration: none; }
        .quick-sale-btn { min-height: 38px; padding: 0 12px; border: 0; border-radius: 8px; background: var(--primary-green); box-shadow: none; font-size: .82rem; }
        .quick-sale-btn:hover { transform: none; background: var(--green-dark); box-shadow: none; }
        .user-toggle { gap: 9px; padding: 4px 7px 4px 4px; border: 1px solid transparent; border-radius: 10px; background: transparent; -webkit-backdrop-filter: none; backdrop-filter: none; }
        .user-toggle:hover { transform: none; border-color: #e8ece9; background: #f7f9f8; box-shadow: none; }
        .user-avatar { width: 36px; height: 36px; border: 0; background: #eaf4ee; box-shadow: none; color: #287348; }
        .user-info .user-name { color: #27332c; }
        .user-toggle .fa-chevron-down { color: #7b8794; }
        .notification-badge { top: -3px; right: -3px; }
        @media (max-width: 767px) {
            :root { --topbar-height: 60px; }
            .top-navbar { padding: 0 12px; gap: 7px; }
            .brand-container { gap: 8px; }
            .brand-logo { width: 34px; height: 34px; }
            .brand-name { max-width: 170px; font-size: .88rem; }
            .brand-tagline, .search-section, .nav-controls #statsToggle { display: none; }
            .nav-controls { margin-right: 0; }
            .actions-section { gap: 4px; }
            .nav-btn, .action-btn, .mobile-menu-toggle { width: 38px; height: 38px; min-width: 38px; }
            .quick-sale-btn { width: 38px; min-width: 38px; padding: 0; }
            .quick-sale-btn span, .user-toggle .user-info, .user-toggle .fa-chevron-down { display: none; }
            .user-toggle { padding: 2px; }
            .user-avatar { width: 34px; height: 34px; }
        }
        @media (max-width: 400px) {
            .brand-name { max-width: 132px; }
            .branch-badge { display: none; }
        }

        /* Manager Sidebar Responsive Styles */
        @media (max-width: 767px) {
            /* Hide manager sidebar by default on mobile */
            .manager-sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: var(--shadow-lg);
                top: var(--topbar-height);
                height: calc(100vh - var(--topbar-height));
            }

            .manager-sidebar.show {
                transform: translateX(0);
            }

            /* When manager sidebar is open, overlay content */
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
<body>
    <!-- Top Navigation Bar - Same Design as Admin -->
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
                    <span class="brand-tagline">Manager Dashboard</span>
                </div>
            </a>
            <span class="branch-badge" title="Current Branch">
                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($branch['branch_name'] ?? 'Main Branch'); ?>
            </span>
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
                <input type="text" class="search-input" placeholder="Search sales, expenses, inventory...">
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
            
            <!-- Quick Sale Button -->
            <button class="quick-sale-btn" onclick="window.location.href='sales.php?action=new'">
                <i class="fas fa-plus-circle"></i>
                <span>New Sale</span>
            </button>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" title="Mobile Menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            
            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-toggle" id="userDropdownToggle">
                    <div class="user-avatar">
                        <?php echo $user_initials; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                        <span class="user-role-badge">Branch Manager</span>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                
                <!-- User Dropdown Menu -->
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <!-- Header -->
                    <div class="user-dropdown-header">
                        <div class="user-avatar">
                            <?php echo $user_initials; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                            <span class="user-role-badge" style="background: rgba(255, 255, 255, 0.2); color: white;">
                                Branch Manager
                            </span>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="user-dropdown-body">
                        <a href="dashboard.php" class="dropdown-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        
                        <a href="#" class="dropdown-item" id="notificationsDropdown">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <?php if ($notification_count > 0): ?>
                                <span class="badge bg-danger ms-auto"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="sales.php?action=new" class="dropdown-item">
                            <i class="fas fa-plus-circle"></i>
                            <span>New Sale</span>
                        </a>
                        
                        <a href="expenses.php?action=add" class="dropdown-item">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Add Expense</span>
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="../public/logout.php" class="dropdown-item text-danger">
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
                <span class="quick-stat-trend <?php echo $quick_stats['sales_growth'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $quick_stats['sales_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($quick_stats['sales_growth']); ?>%
                </span>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Transactions</span>
            <span class="quick-stat-value">
                <?php echo $quick_stats['today_transactions']; ?>
                <span class="badge bg-primary" style="font-size: 0.7rem; padding: 2px 6px;">
                    <i class="fas fa-shopping-cart"></i>
                </span>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Fuel Sold</span>
            <span class="quick-stat-value">
                <?php echo number_format($quick_stats['fuel_sold'], 2); ?> L
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Pending Tasks</span>
            <span class="quick-stat-value">
                <?php echo $quick_stats['pending_tasks']; ?>
                <?php if ($quick_stats['pending_tasks'] > 0): ?>
                    <span class="quick-stat-trend trend-down">
                        <i class="fas fa-exclamation"></i>
                    </span>
                <?php endif; ?>
            </span>
        </div>
        
        <div class="quick-stat">
            <span class="quick-stat-label">Branch Alerts</span>
            <span class="quick-stat-value">
                <?php echo $manager_stats['branch_alerts']; ?>
                <?php if ($manager_stats['branch_alerts'] > 0): ?>
                    <span class="badge bg-danger" style="font-size: 0.7rem; padding: 2px 6px;">
                        <i class="fas fa-bell"></i>
                    </span>
                <?php endif; ?>
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
                    <?php echo $user_initials; ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-muted">Branch Manager</small>
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
                <i class="fas fa-cash-register"></i>
                <span>Sales</span>
            </a>
            <a href="expenses.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Expenses</span>
            </a>
            <a href="reports.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none mb-2">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <div class="dropdown-divider my-2"></div>
            <a href="../public/logout.php" class="mobile-menu-item d-flex align-items-center gap-3 p-3 rounded text-decoration-none text-danger mt-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <?php
    // Include manager sidebar with same design as admin
    require_once 'manager_sidebar.php';
    ?>
    
    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper" id="mainContentWrapper">
        <div class="container-fluid py-4 px-4">

<!-- JavaScript for Manager Header Functionality - Same as Admin -->
<script>
$(document).ready(function() {
    // Get DOM elements
    const body = $('body');
    const sidebarToggle = $('#sidebarToggle');
    const managerSidebar = $('#managerSidebar');
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

    // Toggle Sidebar - Responsive
    sidebarToggle.click(function(e) {
        e.stopPropagation();
        
        if (isMobile()) {
            // For mobile devices
            body.toggleClass('menu-open');
            managerSidebar.toggleClass('show');
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
                !$(e.target).closest('#managerSidebar').length && 
                !$(e.target).closest('#sidebarToggle').length) {
                body.removeClass('menu-open');
                managerSidebar.removeClass('show');
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
            url: '../api/get_manager_stats.php?branch_id=<?php echo $branch_id; ?>',
            method: 'GET',
            success: function(data) {
                if (data.success) {
                    // Update stats in the quick stats bar
                    $('#quickStatsBar .quick-stat:nth-child(1) .quick-stat-value').html(
                        `$${parseFloat(data.today_sales).toFixed(2)}`
                    );
                    $('#quickStatsBar .quick-stat:nth-child(2) .quick-stat-value').html(
                        data.today_transactions
                    );
                    $('#quickStatsBar .quick-stat:nth-child(3) .quick-stat-value').html(
                        `${parseFloat(data.fuel_sold).toFixed(2)} L`
                    );
                    $('#quickStatsBar .quick-stat:nth-child(4) .quick-stat-value').html(
                        data.pending_tasks
                    );
                    $('#quickStatsBar .quick-stat:nth-child(5) .quick-stat-value').html(
                        data.branch_alerts
                    );
                    
                    // Update notification badge
                    const totalNotifications = parseInt(data.branch_alerts);
                    if (totalNotifications > 0) {
                        $('.notification-badge').text(totalNotifications).show();
                    } else {
                        $('.notification-badge').hide();
                    }
                }
            },
            error: function() {
                console.log('Failed to update manager stats');
            }
        });
    }

    // Update stats every 30 seconds
    setInterval(updateQuickStats, 30000);

    // Initial stats update
    updateQuickStats();

    // Initialize manager sidebar functionality
    function initManagerSidebar() {
        // Dropdown functionality - only on desktop when sidebar is expanded
        $('.manager-nav-item.has-dropdown .manager-nav-link').click(function(e) {
            if ($(window).width() > 768 && !body.hasClass('sidebar-collapsed')) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdownItem = $(this).closest('.manager-nav-item.has-dropdown');
                const isActive = dropdownItem.hasClass('active');
                
                // Close all other dropdowns
                $('.manager-nav-item.has-dropdown.active').not(dropdownItem).removeClass('active');
                
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
                if (!$(e.target).closest('.manager-nav-item.has-dropdown').length) {
                    $('.manager-nav-item.has-dropdown.active').removeClass('active');
                }
            }
        });

        // Set active menu item based on current page
        function setActiveMenuItem() {
            const currentUrl = new URL(window.location.href);
            const currentPage = currentUrl.pathname.split('/').pop();
            const currentAction = currentUrl.searchParams.get('action') || '';
            
            // Remove all active classes
            $('.manager-nav-link.active, .manager-submenu-link.active').removeClass('active');
            
            // Match the exact route, including the quick-action query when present.
            const currentLink = currentUrl.hash
                ? $('.manager-nav-link[data-dashboard-section]').filter(function() {
                    return this.dataset.dashboardSection === currentUrl.hash.slice(1);
                })
                : $('.manager-nav-link, .manager-submenu-link').filter(function() {
                    const linkUrl = new URL(this.href, currentUrl);
                    return linkUrl.pathname.endsWith('/' + currentPage)
                        && (linkUrl.searchParams.get('action') || '') === currentAction
                        && !linkUrl.hash;
                });
            if (currentLink.length) {
                currentLink.addClass('active');
                
                // Open parent dropdown if it's a submenu link
                const dropdownParent = currentLink.closest('.manager-nav-item.has-dropdown');
                if (dropdownParent.length) {
                    dropdownParent.addClass('active');
                }
            }
        }

        setActiveMenuItem();

        // Update sidebar stats periodically
        function updateManagerStats() {
            $.ajax({
                url: '../api/get_manager_sidebar_stats.php?branch_id=<?php echo $branch_id; ?>',
                method: 'GET',
                success: function(data) {
                    if (data.success) {
                        // Update user count
                        const userStat = $('.manager-stat:nth-child(1) .manager-stat-value');
                        const userBadge = $('.manager-nav-item.has-dropdown .manager-nav-badge');
                        if (userStat.length) userStat.text(data.active_attendants);
                        if (userBadge.length) userBadge.text(data.active_attendants);
                        
                        // Update revenue
                        const revenueStat = $('.manager-stat:nth-child(2) .manager-stat-value');
                        if (revenueStat.length) revenueStat.text('$' + parseFloat(data.monthly_revenue).toLocaleString());
                        
                        // Update alerts
                        const alertStat = $('.manager-stat:nth-child(3) .manager-stat-value');
                        const alertBadges = $('.manager-nav-badge.danger, .manager-nav-badge.warning');
                        if (alertStat.length) alertStat.text(data.branch_alerts);
                        alertBadges.each(function() {
                            const badgeText = $(this).text();
                            if (!isNaN(badgeText) && badgeText.trim() !== '') {
                                $(this).text(data.branch_alerts);
                            }
                        });
                    }
                },
                error: function(error) {
                    console.error('Error updating manager stats:', error);
                }
            });
        }

        // Update stats every 2 minutes
        setInterval(updateManagerStats, 120000);

        // Quick action buttons functionality
        $('.manager-quick-action-btn').click(function(e) {
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

        // Keyboard shortcuts for manager
        $(document).on('keydown', function(e) {
            // Only enable shortcuts on desktop
            if ($(window).width() <= 768) return;
            
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Alt + S for sales
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'sales.php?action=new';
            }
            
            // Alt + E for expenses
            if (e.altKey && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'expenses.php?action=add';
            }
            
            // Alt + R for reports
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports.php';
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                $('.manager-nav-item.has-dropdown.active').removeClass('active');
            }
        });

        // Initialize system status check
        function checkBranchStatus() {
            $.ajax({
                url: '../api/check_branch_status.php',
                method: 'GET',
                success: function(data) {
                    if (data.success) {
                        const systemStatus = $('.status-item:nth-child(1) .status-value');
                        const inventoryStatus = $('.status-item:nth-child(2) .status-value');
                        const pumpsStatus = $('.status-item:nth-child(3) .status-value');
                        
                        if (systemStatus.length) {
                            systemStatus.removeClass('online offline warning')
                                .addClass(data.system_online ? 'online' : 'offline')
                                .text(data.system_online ? 'Online' : 'Offline');
                        }
                        
                        if (inventoryStatus.length) {
                            const lowStock = data.low_stock_count;
                            let statusClass = 'online';
                            let statusText = 'Good';
                            
                            if (lowStock > 3) {
                                statusClass = 'offline';
                                statusText = 'Critical';
                            } else if (lowStock > 0) {
                                statusClass = 'warning';
                                statusText = 'Low Stock';
                            }
                            
                            inventoryStatus.removeClass('online offline warning')
                                .addClass(statusClass)
                                .text(statusText);
                        }
                        
                        if (pumpsStatus.length) {
                            const maintenancePumps = data.maintenance_pumps;
                            let statusClass = 'online';
                            let statusText = 'All Active';
                            
                            if (maintenancePumps > 2) {
                                statusClass = 'offline';
                                statusText = maintenancePumps + ' Down';
                            } else if (maintenancePumps > 0) {
                                statusClass = 'warning';
                                statusText = maintenancePumps + ' Maintenance';
                            }
                            
                            pumpsStatus.removeClass('online offline warning')
                                .addClass(statusClass)
                                .text(statusText);
                        }
                    }
                },
                error: function(error) {
                    console.error('Error checking branch status:', error);
                }
            });
        }

        // Check system status every 5 minutes
        setInterval(checkBranchStatus, 300000);

        // Initial checks
        updateManagerStats();
        checkBranchStatus();
    }

    // Initialize manager sidebar if it exists
    if (managerSidebar.length) {
        initManagerSidebar();
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
                managerSidebar.removeClass('show');
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
            $('.manager-nav-link').each(function() {
                const text = $(this).find('.manager-nav-text').text() || $(this).attr('data-tooltip');
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
        $('.nav-btn, .action-btn, .manager-nav-link, .manager-submenu-link, .manager-stat, .manager-quick-action-btn, .quick-sale-btn').on('touchstart', function() {
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