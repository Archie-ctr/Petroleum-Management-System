<?php
session_start();
require_once '../config/db.php';

// Get company settings
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$company_name = $settings['company_name'] ?? "GOD'S WILLING INC";
$company_logo = $settings['company_logo'] ?? '';

// Get branch count for statistics
$branch_count_sql = "SELECT COUNT(*) as count FROM branches WHERE status = 'active'";
$branch_count_result = $conn->query($branch_count_sql);
$branch_count = $branch_count_result->fetch_assoc()['count'] ?? 0;

// Get leadership information (you would typically have this in a database)
$leadership = [
    [
        'name' => 'Mr. Karnue',
        'title' => 'Chief Executive Officer',
        'bio' => 'Visionary leader with 15+ years in petroleum industry. Passionate about transforming Liberia\'s energy sector through technology and innovation. Under his leadership, GOD\'S WILLING INC has grown from a single station to managing over 50 locations across Liberia.',
        'experience' => '15+ Years',
        'achievements' => ['Founded GOD\'S WILLING INC in 2012', 'Expanded to 50+ stations', 'Pioneered digital petroleum management in Liberia']
    ],
    [
        'name' => 'Mrs. Mamie Karnue',
        'title' => 'Chief Operations Officer',
        'bio' => 'Operations expert with deep knowledge of Liberia\'s fuel distribution network. Committed to excellence in customer service and operational efficiency. She has implemented innovative systems that have increased station efficiency by 40%.',
        'experience' => '12+ Years',
        'achievements' => ['Optimized supply chain operations', 'Implemented quality control systems', 'Led employee training programs']
    ]
];

// Get company stats
$stats = [
    ['value' => '2012', 'label' => 'Founded', 'icon' => 'fas fa-calendar-alt'],
    ['value' => $branch_count . '+', 'label' => 'Stations', 'icon' => 'fas fa-gas-pump'],
    ['value' => '100+', 'label' => 'Employees', 'icon' => 'fas fa-users'],
    ['value' => '10+', 'label' => 'Years Experience', 'icon' => 'fas fa-award']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>About Us - <?php echo $company_name; ?> | Petroleum Management in Liberia</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@700;800;900&family=Raleway:wght@800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #27AE60;
            --dark-green: #1E8449;
            --light-green: #D5F4E6;
            --vibrant-green: #00C851;
            --primary-yellow: #FFD700;
            --gold-yellow: #FFC107;
            --light-yellow: #FFF9C4;
            --accent-yellow: #F1C40F;
            --dark-gray: #2C3E50;
            --medium-gray: #34495E;
            --light-gray: #ECF0F1;
            --white: #FFFFFF;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.15);
            --vh: 1vh;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            line-height: 1.6;
            color: var(--dark-gray);
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        /* App-like body for mobile */
        @media (max-width: 768px) {
            body {
                background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
                min-height: calc(var(--vh, 1vh) * 100);
            }
        }
        
        /* Header & Navigation - Mobile App Style */
        .main-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 8px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-bottom: 3px solid var(--primary-yellow);
            min-height: 70px;
            transition: transform 0.3s ease;
            padding-top: env(safe-area-inset-top);
            backdrop-filter: blur(10px);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
            flex-wrap: nowrap;
            height: 70px;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: transform 0.3s ease;
            flex-shrink: 0;
            min-width: 0;
            flex: 1;
            max-width: calc(100% - 120px);
        }
        
        .logo-area:hover {
            transform: scale(1.02);
        }
        
        .logo-wrapper {
            position: relative;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .logo-circle {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--accent-yellow) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
            border: 2px solid white;
            overflow: hidden;
        }
        
        .logo-img {
            width: 85%;
            height: 85%;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            flex: 1;
        }
        
        .company-name {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.4rem;
            color: white;
            line-height: 1.1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.25);
            letter-spacing: 0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .company-name span {
            color: var(--primary-yellow);
            font-weight: 900;
            position: relative;
        }
        
        .company-name span::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }
        
        .company-tagline {
            font-family: 'Poppins', sans-serif;
            font-size: 0.7rem;
            color: var(--light-yellow);
            margin-top: 2px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Desktop Navigation */
        .main-nav {
            flex: 1;
            display: flex;
            justify-content: center;
        }
        
        .main-nav ul {
            display: flex;
            list-style: none;
            gap: 3px;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .nav-link i {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover i {
            transform: scale(1.2);
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .nav-link:hover::before {
            left: 100%;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--primary-yellow);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .nav-link.active {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
            font-weight: 700;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .btn-login {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            border: none;
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
            white-space: nowrap;
            font-size: 0.85rem;
        }
        
        .btn-login:hover {
            background: var(--accent-yellow);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 15px rgba(255, 215, 0, 0.4);
            color: var(--dark-gray);
        }
        
        .btn-dashboard {
            background: transparent;
            color: white;
            border: 2px solid var(--primary-yellow);
            padding: 6px 16px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            font-size: 0.85rem;
        }
        
        .btn-dashboard:hover {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 215, 0, 0.3);
        }
        
        /* Mobile Menu Button - Improved for Touch */
        .mobile-menu-btn {
            display: none;
            background: var(--primary-yellow);
            border: none;
            color: var(--dark-gray);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-left: 8px;
            position: relative;
            z-index: 1001;
        }
        
        .mobile-menu-btn:hover, .mobile-menu-btn:active {
            transform: rotate(90deg);
            background: var(--accent-yellow);
        }
        
        .mobile-menu-btn.active {
            transform: rotate(90deg);
            background: var(--accent-yellow);
        }
        
        /* Mobile Menu - Improved for App-like Experience */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 85%;
            max-width: 320px;
            height: 100vh;
            height: calc(var(--vh, 1vh) * 100);
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            z-index: 2000;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.3);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .mobile-menu.active {
            right: 0;
        }
        
        .mobile-menu-header {
            padding: 20px;
            display: flex;
            justify-content: flex-end;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            height: 70px;
            align-items: center;
        }
        
        .mobile-menu-close {
            background: var(--primary-yellow);
            border: none;
            color: var(--dark-gray);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-menu-close:hover, .mobile-menu-close:active {
            transform: rotate(90deg);
            background: var(--accent-yellow);
        }
        
        .mobile-menu ul {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }
        
        .mobile-menu li {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .mobile-menu li:last-child {
            border-bottom: none;
        }
        
        .mobile-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 18px 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            min-height: 60px;
        }
        
        .mobile-menu a i {
            width: 24px;
            font-size: 1.2rem;
            color: var(--primary-yellow);
        }
        
        .mobile-menu a:hover, .mobile-menu a:active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--primary-yellow);
            padding-left: 30px;
        }
        
        .mobile-menu a.active {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            font-weight: 700;
        }
        
        .mobile-menu a.active i {
            color: var(--dark-gray);
        }
        
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            background: rgba(0, 0, 0, 0.7);
            z-index: 1999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(3px);
        }
        
        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Main Content with header offset */
        main {
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* About Hero Section - Adjusted for mobile */
        .about-hero {
            background: linear-gradient(rgba(18, 91, 53, 0.92), rgba(39, 174, 96, 0.88)), 
                        url('images/operations.svg');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            padding: 120px 0 60px;
            position: relative;
            overflow: hidden;
            margin-top: -70px;
            padding-top: 190px;
        }
        
        @media (max-width: 768px) {
            .about-hero {
                padding-top: 170px;
                padding-bottom: 40px;
                min-height: 50vh;
                display: flex;
                align-items: center;
            }
        }
        
        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23FFD700' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        .about-hero-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .about-hero-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2.5rem;
            margin-bottom: 20px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
            line-height: 1.2;
            animation: fadeInUp 1s ease-out;
            word-wrap: break-word;
        }
        
        @media (min-width: 768px) {
            .about-hero-title {
                font-size: 3rem;
            }
        }
        
        @media (min-width: 992px) {
            .about-hero-title {
                font-size: 3.5rem;
            }
        }
        
        .about-hero-title span {
            color: var(--primary-yellow);
            position: relative;
            display: inline-block;
            padding: 0 10px;
        }
        
        .about-hero-title span::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, var(--primary-yellow), var(--accent-yellow));
            z-index: -1;
            border-radius: 10px;
            opacity: 0.2;
            transform: skewX(-15deg);
        }
        
        .about-hero-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 30px;
            animation: fadeInUp 1s ease-out 0.2s both;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            word-wrap: break-word;
            padding: 0 5px;
        }
        
        @media (min-width: 768px) {
            .about-hero-subtitle {
                font-size: 1.3rem;
            }
        }
        
        /* Company Stats */
        .company-stats {
            background: white;
            padding: 60px 0;
            position: relative;
        }
        
        @media (min-width: 768px) {
            .company-stats {
                padding: 80px 0;
            }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 30px 20px;
            background: var(--light-gray);
            border-radius: 20px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .stat-item:hover, .stat-item:active {
            transform: translateY(-10px) scale(1.03);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-yellow);
            background: white;
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary-green);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover .stat-icon, .stat-item:active .stat-icon {
            color: var(--primary-yellow);
            transform: rotateY(360deg);
        }
        
        .stat-value {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            color: var(--dark-green);
            display: block;
            line-height: 1;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-label {
            color: var(--medium-gray);
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        
        /* Mission Section */
        .mission-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
            position: relative;
        }
        
        @media (min-width: 768px) {
            .mission-section {
                padding: 80px 0;
            }
        }
        
        .mission-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        @media (min-width: 992px) {
            .mission-content {
                grid-template-columns: 1fr 1fr;
                gap: 50px;
            }
        }
        
        .mission-image {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        
        .mission-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 215, 0, 0.1), rgba(39, 174, 96, 0.1));
            z-index: 1;
        }
        
        .mission-image img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.5s ease;
            display: block;
        }
        
        @media (min-width: 768px) {
            .mission-image img {
                height: 400px;
            }
        }
        
        .mission-image:hover img {
            transform: scale(1.05);
        }
        
        .mission-text h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--dark-green);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .mission-text h2 {
                font-size: 2.5rem;
            }
        }
        
        .mission-text h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
        }
        
        .mission-text p {
            color: var(--medium-gray);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        
        .mission-highlight {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            border-left: 4px solid var(--primary-yellow);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .mission-highlight h4 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-green);
            margin-bottom: 15px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Values Section */
        .values-section {
            padding: 60px 0;
            background: white;
            position: relative;
        }
        
        @media (min-width: 768px) {
            .values-section {
                padding: 80px 0;
            }
        }
        
        .section-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--dark-green);
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0 15px;
        }
        
        @media (min-width: 768px) {
            .section-title {
                font-size: 2.5rem;
                margin-bottom: 60px;
            }
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .value-card {
            background: var(--light-gray);
            border-radius: 20px;
            padding: 35px 25px;
            text-align: center;
            transition: all 0.4s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .value-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .value-card:hover, .value-card:active {
            transform: translateY(-15px) scale(1.03);
            border-color: var(--primary-yellow);
            box-shadow: var(--shadow-lg);
            background: white;
        }
        
        .value-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: var(--dark-green);
            transition: all 0.4s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .value-card:hover .value-icon, .value-card:active .value-icon {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--primary-green) 100%);
            color: white;
            transform: rotateY(360deg);
        }
        
        .value-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--dark-gray);
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 12px;
        }
        
        .value-card h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--primary-yellow);
            border-radius: 2px;
        }
        
        .value-card p {
            color: var(--medium-gray);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* Leadership Section */
        .leadership-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            position: relative;
        }
        
        @media (min-width: 768px) {
            .leadership-section {
                padding: 80px 0;
            }
        }
        
        .leadership-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        @media (min-width: 992px) {
            .leadership-grid {
                grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
                gap: 40px;
            }
        }
        
        .leader-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
            position: relative;
        }
        
        .leader-card:hover, .leader-card:active {
            transform: translateY(-15px);
            box-shadow: var(--shadow-lg);
        }
        
        .leader-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .leader-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
            animation: rotate 20s linear infinite;
        }
        
        .leader-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary-yellow);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--dark-gray);
            border: 5px solid white;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .leader-name {
            color: white;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }
        
        .leader-title {
            color: var(--light-yellow);
            font-size: 1.1rem;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        
        .leader-body {
            padding: 30px;
        }
        
        .leader-bio {
            color: var(--medium-gray);
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        
        .leader-achievements {
            margin-bottom: 25px;
        }
        
        .leader-achievements h4 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-green);
            margin-bottom: 15px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .achievement-list {
            list-style: none;
            padding: 0;
        }
        
        .achievement-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            color: var(--medium-gray);
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .achievement-list i {
            color: var(--primary-green);
            font-size: 0.9rem;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .leader-experience {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            border-left: 4px solid var(--primary-yellow);
        }
        
        .experience-label {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .experience-value {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--primary-green);
            font-size: 1.5rem;
        }
        
        /* Liberia Impact Section */
        .impact-section {
            padding: 60px 0;
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        @media (min-width: 768px) {
            .impact-section {
                padding: 80px 0;
            }
        }
        
        .impact-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.2;
        }
        
        .impact-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            text-align: center;
        }
        
        .impact-content h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
        }
        
        @media (min-width: 768px) {
            .impact-content h2 {
                font-size: 2.5rem;
            }
        }
        
        .impact-content > p {
            font-size: 1.1rem;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 40px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media (min-width: 768px) {
            .impact-content > p {
                font-size: 1.3rem;
                margin-bottom: 50px;
            }
        }
        
        .impact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .impact-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 30px 20px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .impact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), white);
        }
        
        .impact-card:hover, .impact-card:active {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .impact-icon {
            font-size: 2.5rem;
            color: var(--primary-yellow);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .impact-card:hover .impact-icon, .impact-card:active .impact-icon {
            transform: rotateY(360deg);
        }
        
        .impact-card h4 {
            font-family: 'Montserrat', sans-serif;
            color: white;
            margin-bottom: 15px;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .impact-card p {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* Footer */
        .main-footer {
            background: var(--dark-gray);
            color: white;
            padding: 50px 0 20px;
        }
        
        @media (min-width: 768px) {
            .main-footer {
                padding: 70px 0 30px;
            }
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 40px;
            }
        }
        
        .footer-col h4 {
            font-family: 'Raleway', sans-serif;
            font-weight: 800;
            color: var(--primary-yellow);
            margin-bottom: 20px;
            font-size: 1.2rem;
            position: relative;
            padding-bottom: 12px;
        }
        
        .footer-col h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-green);
            border-radius: 2px;
        }
        
        .footer-logo {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.6rem;
            color: white;
            margin-bottom: 20px;
            line-height: 1.1;
        }
        
        .footer-logo span {
            color: var(--primary-yellow);
            position: relative;
        }
        
        .footer-col p {
            color: #bbb;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .social-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .social-link:hover, .social-link:active {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            transform: translateY(-3px) rotate(5deg);
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        
        .footer-links a:hover, .footer-links a:active {
            color: var(--primary-yellow);
            padding-left: 5px;
        }
        
        .contact-info li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            color: #bbb;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .contact-info i {
            color: var(--primary-green);
            width: 18px;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            color: #999;
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        .copyright a {
            color: var(--primary-yellow);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .copyright a:hover, .copyright a:active {
            color: var(--accent-yellow);
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        .animate-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        /* Responsive Design for Mobile */
        @media (min-width: 993px) {
            .mobile-menu-btn,
            .mobile-menu,
            .mobile-menu-overlay {
                display: none !important;
            }
        }
        
        @media (max-width: 992px) {
            .main-nav {
                display: none !important;
            }
            
            .mobile-menu-btn {
                display: flex !important;
            }
            
            .company-name {
                font-size: 1.2rem;
            }
            
            .company-tagline {
                font-size: 0.65rem;
            }
            
            .logo-wrapper {
                width: 50px;
                height: 50px;
            }
            
            .btn-login, .btn-dashboard {
                padding: 6px 12px;
                font-size: 0.75rem;
                display: none;
            }
            
            .mobile-menu-btn {
                width: 42px;
                height: 42px;
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 768px) {
            body {
                background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            }
            
            .header-container {
                padding: 0 10px;
            }
            
            .company-name {
                font-size: 1.1rem;
            }
            
            .company-tagline {
                font-size: 0.6rem;
            }
            
            .logo-wrapper {
                width: 45px;
                height: 45px;
            }
            
            .logo-area {
                gap: 8px;
            }
            
            .mobile-menu-btn {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                margin-left: 5px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-item {
                padding: 25px 15px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .stat-icon {
                font-size: 1.8rem;
            }
            
            .mission-content {
                gap: 30px;
            }
            
            .mission-image img {
                height: 250px;
            }
            
            .mission-text h2 {
                font-size: 1.6rem;
            }
            
            .mission-text p {
                font-size: 0.95rem;
            }
            
            .mission-highlight {
                padding: 20px;
            }
            
            .mission-highlight h4 {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .values-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .value-card {
                padding: 30px 20px;
            }
            
            .value-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }
            
            .value-card h3 {
                font-size: 1.3rem;
            }
            
            .leadership-grid {
                gap: 25px;
            }
            
            .leader-header {
                padding: 30px 20px;
            }
            
            .leader-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .leader-name {
                font-size: 1.4rem;
            }
            
            .leader-title {
                font-size: 1rem;
            }
            
            .leader-body {
                padding: 25px;
            }
            
            .leader-bio {
                font-size: 0.95rem;
            }
            
            .leader-achievements h4 {
                font-size: 1.1rem;
            }
            
            .achievement-list li {
                font-size: 0.9rem;
            }
            
            .experience-value {
                font-size: 1.4rem;
            }
            
            .impact-content h2 {
                font-size: 1.6rem;
            }
            
            .impact-content > p {
                font-size: 1rem;
            }
            
            .impact-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .impact-card {
                padding: 25px 20px;
            }
            
            .impact-icon {
                font-size: 2.2rem;
            }
            
            .impact-card h4 {
                font-size: 1.2rem;
            }
            
            .impact-card p {
                font-size: 0.9rem;
            }
            
            .footer-grid {
                gap: 25px;
            }
            
            .footer-logo {
                font-size: 1.4rem;
            }
            
            .social-link {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .footer-links a {
                font-size: 0.9rem;
            }
            
            .contact-info li {
                font-size: 0.9rem;
            }
            
            .copyright {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .about-hero-title {
                font-size: 1.8rem;
            }
            
            .about-hero-subtitle {
                font-size: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                max-width: 280px;
            }
            
            .mission-text h2 {
                font-size: 1.4rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .logo-wrapper {
                width: 40px;
                height: 40px;
            }
        }
        
        @media (max-width: 400px) {
            .company-name {
                font-size: 0.9rem;
            }
            
            .company-tagline {
                font-size: 0.55rem;
            }
            
            .logo-wrapper {
                width: 38px;
                height: 38px;
            }
            
            .about-hero-title {
                font-size: 1.6rem;
            }
            
            .stat-item {
                padding: 20px 15px;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
            
            .stat-label {
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .mobile-menu {
                width: 90%;
            }
        }
        
        @media (max-width: 360px) {
            .logo-wrapper {
                width: 36px;
                height: 36px;
            }
            
            .company-name {
                font-size: 0.85rem;
            }
            
            .company-tagline {
                font-size: 0.5rem;
            }
            
            .mobile-menu-btn {
                width: 38px;
                height: 38px;
            }
            
            .stats-grid {
                max-width: 260px;
            }
        }
        
        /* Fix for mobile overflow */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }
        
        /* Touch-friendly button sizes */
        button,
        a.btn,
        .nav-link {
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        
        /* Prevent text from being too small on mobile */
        body {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
            -moz-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }
        
        /* Better scrolling on iOS */
        .mobile-menu {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Prevent zoom on input focus */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea {
            font-size: 16px;
        }
        
        /* Improve tap targets */
        @media (max-width: 768px) {
            .nav-link,
            .btn-login,
            .btn-dashboard,
            .btn-hero-primary,
            .btn-hero-secondary,
            .btn-cta,
            .social-link,
            .footer-links a {
                min-height: 44px;
                min-width: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .mobile-menu a {
                min-height: 50px;
            }
            
            /* App-like touch feedback */
            button:active, 
            a:active,
            .nav-link:active,
            .btn-hero-primary:active,
            .btn-hero-secondary:active,
            .btn-cta:active,
            .social-link:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }
        }
        
        /* Swipe to close mobile menu */
        .mobile-menu {
            touch-action: pan-y;
        }
        
        /* Loading animation for better UX */
        .loading {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .loaded {
            opacity: 1;
        }
        
        /* Safe area insets for modern mobile devices */
        @supports (padding: max(0px)) {
            body, .main-header, .mobile-menu {
                padding-left: min(15px, env(safe-area-inset-left));
                padding-right: min(15px, env(safe-area-inset-right));
            }
            
            .main-header {
                padding-top: max(8px, env(safe-area-inset-top));
            }
        }
    </style>
    <link rel="stylesheet" href="public-responsive.css">
</head>
<body class="loading">
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="header-container">
            <!-- Logo Area -->
            <a href="index.php" class="logo-area">
                <div class="logo-wrapper">
                    <div class="logo-circle">
                        <?php if (!empty($company_logo)): ?>
                            <img src="../uploads/<?php echo $company_logo; ?>" alt="<?php echo $company_name; ?>" class="logo-img">
                        <?php else: ?>
                            <div style="color: var(--dark-gray); font-weight: 900; font-size: 1.4rem; font-family: 'Raleway', sans-serif; line-height: 1;">
                                GWI
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="logo-text">
                    <div class="company-name">GOD'S WILLING <span>INC</span></div>
                    <div class="company-tagline">Petroleum Excellence in Liberia</div>
                </div>
            </a>
            
            <!-- Desktop Navigation -->
            <nav class="main-nav d-none d-lg-block">
                <ul>
                    <li class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                    <li class="nav-item"><a href="about.php" class="nav-link active"><i class="fas fa-info-circle"></i> About</a></li>
                    <li class="nav-item"><a href="services.php" class="nav-link"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li class="nav-item"><a href="prices.php" class="nav-link"><i class="fas fa-dollar-sign"></i> Prices</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </nav>
            
            <!-- User Actions -->
            <div class="user-actions">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn-login d-none d-md-inline-flex">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php else: ?>
                    <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="btn-dashboard d-none d-md-inline-flex">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                <?php endif; ?>
                <button class="mobile-menu-btn d-lg-none" id="mobileMenuBtn" aria-label="Toggle mobile menu" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <div class="mobile-menu-header">
            <button class="mobile-menu-close" id="mobileMenuClose" aria-label="Close mobile menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <ul>
            <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="about.php" class="nav-link active"><i class="fas fa-info-circle"></i> About</a></li>
            <li><a href="services.php" class="nav-link"><i class="fas fa-concierge-bell"></i> Services</a></li>
            <li><a href="prices.php" class="nav-link"><i class="fas fa-dollar-sign"></i> Prices</a></li>
            <li><a href="contact.php" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Main Content -->
    <main>
        <!-- About Hero Section -->
        <section class="about-hero">
            <div class="about-hero-content">
                <h1 class="about-hero-title">About <span>GOD'S WILLING INC</span></h1>
                <p class="about-hero-subtitle">
                    Liberia's leading petroleum management company, transforming the energy sector through 
                    innovation, technology, and commitment to excellence since 2012. Under the visionary 
                    leadership of Mr. Karnue and Mrs. Mamie Karnue.
                </p>
            </div>
        </section>

        <!-- Company Stats -->
        <section class="company-stats">
            <div class="stats-grid">
                <?php foreach ($stats as $index => $stat): ?>
                <div class="stat-item animate-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <div class="stat-icon">
                        <i class="<?php echo $stat['icon']; ?>"></i>
                    </div>
                    <span class="stat-value"><?php echo $stat['value']; ?></span>
                    <span class="stat-label"><?php echo $stat['label']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Mission Section -->
        <section class="mission-section">
            <div class="mission-content">
                <div class="mission-image animate-up">
                    <img src="images/operations.svg" alt="Illustration of fuel inventory and business operations monitoring" width="800" height="600" loading="lazy">
                </div>
                <div class="mission-text animate-up" style="animation-delay: 0.2s;">
                    <h2>Our Mission & Vision</h2>
                    <p>
                        GOD'S WILLING INC is committed to revolutionizing Liberia's petroleum industry through 
                        cutting-edge technology solutions. Our mission is to empower fuel stations with efficient, 
                        reliable, and transparent management systems that drive growth and profitability.
                    </p>
                    <p>
                        We envision a future where every fuel station in Liberia operates at peak efficiency, 
                        contributing to the nation's economic development while providing exceptional service 
                        to customers across the country.
                    </p>
                    <p>
                        Founded in 2012 by Mr. Karnue and Mrs. Mamie Karnue, our company has grown from a 
                        single-station operation to managing over <?php echo $branch_count; ?> petroleum stations across Liberia, 
                        becoming the trusted partner for petroleum businesses nationwide.
                    </p>
                    
                    <div class="mission-highlight">
                        <h4><i class="fas fa-star"></i> Our Commitment to Liberia</h4>
                        <p>
                            We're proud to be a 100% Liberian-owned company contributing to our nation's growth. 
                            Through job creation, skills development, and community investment, we're building 
                            a sustainable future for Liberia's energy sector.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Core Values -->
        <section class="values-section">
            <h2 class="section-title">Our Core Values</h2>
            <div class="values-grid">
                <div class="value-card animate-up">
                    <div class="value-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Excellence</h3>
                    <p>We strive for perfection in everything we do, from technology development to customer service. Continuous improvement is our standard.</p>
                </div>
                
                <div class="value-card animate-up" style="animation-delay: 0.1s;">
                    <div class="value-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3>Innovation</h3>
                    <p>Constantly evolving our solutions to meet the changing needs of Liberia's petroleum industry. We embrace new technologies.</p>
                </div>
                
                <div class="value-card animate-up" style="animation-delay: 0.2s;">
                    <div class="value-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3>Integrity</h3>
                    <p>Building trust through transparency, honesty, and ethical business practices. Our word is our bond.</p>
                </div>
                
                <div class="value-card animate-up" style="animation-delay: 0.3s;">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Partnership</h3>
                    <p>Working collaboratively with our clients to achieve shared success and growth. Your success is our success.</p>
                </div>
                
                <div class="value-card animate-up" style="animation-delay: 0.4s;">
                    <div class="value-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3>Sustainability</h3>
                    <p>Promoting environmentally responsible practices in petroleum management. We care for Liberia's future.</p>
                </div>
                
                <div class="value-card animate-up" style="animation-delay: 0.5s;">
                    <div class="value-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Community</h3>
                    <p>Investing in Liberia's growth and supporting local communities. We're proud to be Liberian.</p>
                </div>
            </div>
        </section>

        <!-- Leadership -->
        <section class="leadership-section">
            <h2 class="section-title">Our Leadership Team</h2>
            <div class="leadership-grid">
                <?php foreach ($leadership as $index => $leader): ?>
                <div class="leader-card animate-up" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                    <div class="leader-header">
                        <div class="leader-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="leader-name"><?php echo $leader['name']; ?></h3>
                        <p class="leader-title"><?php echo $leader['title']; ?></p>
                    </div>
                    <div class="leader-body">
                        <p class="leader-bio"><?php echo $leader['bio']; ?></p>
                        
                        <div class="leader-achievements">
                            <h4><i class="fas fa-trophy"></i> Key Achievements</h4>
                            <ul class="achievement-list">
                                <?php foreach ($leader['achievements'] as $achievement): ?>
                                <li><i class="fas fa-check-circle"></i> <?php echo $achievement; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="leader-experience">
                            <div class="experience-label">Industry Experience</div>
                            <div class="experience-value"><?php echo $leader['experience']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Liberia Impact -->
        <section class="impact-section">
            <div class="impact-content">
                <h2>Our Impact in Liberia</h2>
                <p>
                    As a proudly Liberian company, we're committed to the growth and development of our nation's 
                    petroleum sector. Our solutions have created hundreds of jobs, supported local businesses, 
                    and contributed to Liberia's economic stability.
                </p>
                
                <div class="impact-grid">
                    <div class="impact-card animate-up">
                        <div class="impact-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h4>Job Creation</h4>
                        <p>Created 200 employment opportunities across Liberia, with 80% of positions held by Liberian nationals.</p>
                    </div>
                    
                    <div class="impact-card animate-up" style="animation-delay: 0.1s;">
                        <div class="impact-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Economic Growth</h4>
                        <p>Contributing significantly to Liberia's GDP through efficient petroleum management and fuel distribution.</p>
                    </div>
                    
                    <div class="impact-card animate-up" style="animation-delay: 0.2s;">
                        <div class="impact-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4>Skills Development</h4>
                        <p>Training programs for petroleum industry professionals, enhancing technical skills across the sector.</p>
                    </div>
                    
                    <div class="impact-card animate-up" style="animation-delay: 0.3s;">
                        <div class="impact-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h4>Community Support</h4>
                        <p>Investing in local communities through education, healthcare, and social development initiatives.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="footer-container">
                <div class="footer-grid">
                    <div class="footer-col">
                        <div class="footer-logo">GOD'S WILLING <span>INC</span></div>
                        <p>
                            Liberia's leading petroleum management company, providing innovative technology 
                            solutions for fuel stations. Empowering businesses with efficiency, reliability, 
                            and growth opportunities.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Quick Links</h4>
                        <div class="footer-links">
                            <ul>
                                <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                                <li><a href="about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                                <li><a href="services.php"><i class="fas fa-chevron-right"></i> Our Services</a></li>
                                <li><a href="prices.php"><i class="fas fa-chevron-right"></i> Fuel Prices</a></li>
                                <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="footer-col">
                        <h4>System Access</h4>
                        <div class="footer-links">
                            <ul>
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> User Login</a></li>
                                    <li><a href="login.php"><i class="fas fa-user-shield"></i> Admin Login</a></li>
                                    <li><a href="contact.php"><i class="fas fa-headset"></i> Support</a></li>
                                <?php else: ?>
                                    <li><a href="../admin/dashboard.php"><i class="fas fa-cogs"></i> Admin Panel</a></li>
                                    <li><a href="../manager/dashboard.php"><i class="fas fa-user-tie"></i> Manager Portal</a></li>
                                    <li><a href="../attendant/dashboard.php"><i class="fas fa-user"></i> Attendant Portal</a></li>
                                    <li><a href="../accountant/dashboard.php"><i class="fas fa-calculator"></i> Accountant Portal</a></li>
                                    <li><a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Contact Info</h4>
                        <div class="contact-info">
                            <ul>
                                <li><i class="fas fa-map-marker-alt"></i> Broad Street, Monrovia, Liberia</li>
                                <li><i class="fas fa-phone"></i> +231 880 123 456</li>
                                <li><i class="fas fa-phone"></i> +231 770 987 654</li>
                                <li><i class="fas fa-envelope"></i> info@godswillinginc.com</li>
                                <li><i class="fas fa-clock"></i> Mon - Fri: 7:00 AM - 8:00 PM</li>
                                <li><i class="fas fa-headset"></i> 24/7 Emergency Support</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> GOD'S WILLING INC. All rights reserved. | 
                    Petroleum Management System v3.0 | Designed with <i class="fas fa-heart" style="color: #ff4757;"></i> for Liberia
                    <br>
                    <small>CEO: Mr. Karnue | COO: Mrs. Mamie Karnue</small>
                </div>
            </div>
        </footer>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fix for mobile 100vh issue
            function setVH() {
                let vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            }
            
            setVH();
            window.addEventListener('resize', setVH);
            window.addEventListener('orientationchange', setVH);
            
            // Show content after loading
            document.body.classList.add('loaded');
            
            // Mobile Menu Toggle - Enhanced for touch
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
            
            let isMobileMenuOpen = false;
            let touchStartX = 0;
            let touchEndX = 0;
            
            function openMobileMenu() {
                mobileMenu.classList.add('active');
                mobileMenuOverlay.classList.add('active');
                mobileMenuBtn.classList.add('active');
                mobileMenuBtn.setAttribute('aria-expanded', 'true');
                mobileMenu.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
                isMobileMenuOpen = true;
                
                // Add animation
                mobileMenu.style.animation = 'none';
                requestAnimationFrame(() => {
                    mobileMenu.style.animation = 'slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                });
            }
            
            function closeMobileMenu() {
                mobileMenu.style.animation = 'slideOut 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    mobileMenu.classList.remove('active');
                    mobileMenuOverlay.classList.remove('active');
                    mobileMenuBtn.classList.remove('active');
                    mobileMenuBtn.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                    isMobileMenuOpen = false;
                }, 350);
            }
            
            function toggleMobileMenu() {
                if (isMobileMenuOpen) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            }
            
            // Event Listeners
            mobileMenuBtn.addEventListener('click', openMobileMenu);
            mobileMenuClose.addEventListener('click', closeMobileMenu);
            mobileMenuOverlay.addEventListener('click', closeMobileMenu);
            
            // Close mobile menu when clicking on links
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.getAttribute('href').startsWith('#')) {
                        closeMobileMenu();
                    }
                });
            });
            
            // Close mobile menu with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && isMobileMenuOpen) {
                    closeMobileMenu();
                }
            });
            
            // Swipe to close mobile menu
            mobileMenu.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            mobileMenu.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                const swipeDistance = touchEndX - touchStartX;
                
                // If swipe right more than 50px, close menu
                if (swipeDistance > 50 && isMobileMenuOpen) {
                    closeMobileMenu();
                }
            });
            
            // Prevent body scroll when menu is open
            mobileMenu.addEventListener('touchmove', (e) => {
                if (isMobileMenuOpen) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Scroll animations
            const observerOptions = {
                threshold: 0.05,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe elements for animation
            document.querySelectorAll('.animate-up').forEach(el => {
                observer.observe(el);
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        // Close mobile menu if open
                        if (isMobileMenuOpen) {
                            closeMobileMenu();
                        }
                        
                        e.preventDefault();
                        
                        // Calculate header offset
                        const headerHeight = document.querySelector('.main-header').offsetHeight;
                        const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset;
                        const offsetPosition = targetPosition - headerHeight - 20;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Add active class to current page in navigation
            const currentPage = window.location.pathname.split('/').pop() || 'about.php';
            document.querySelectorAll('.nav-link').forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Add active class to mobile menu links
            mobileMenu.querySelectorAll('.nav-link').forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Parallax effect for hero section
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const heroSection = document.querySelector('.about-hero');
                if (heroSection && window.innerWidth > 768) {
                    const rate = scrolled * 0.5;
                    heroSection.style.transform = `translate3d(0, ${rate}px, 0)`;
                }
            });
            
            // Update current year in stats
            const currentYear = new Date().getFullYear();
            const yearsOperating = currentYear - 2012;
            const yearsExperience = document.querySelector('.stat-item:nth-child(4) .stat-value');
            if (yearsExperience) {
                yearsExperience.textContent = yearsOperating + '+';
            }
            
            // Touch-friendly improvements
            document.querySelectorAll('button, a, .nav-link, .stat-item, .value-card, .leader-card, .impact-card').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.97)';
                    this.style.transition = 'transform 0.1s ease';
                });
                
                el.addEventListener('touchend', function() {
                    this.style.transform = '';
                    this.style.transition = 'transform 0.3s ease';
                });
            });
            
            // Prevent zoom on double tap for mobile
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, { passive: false });
            
            // Auto-hide header on scroll down, show on scroll up
            let lastScrollTop = 0;
            const header = document.querySelector('.main-header');
            const headerHeight = header.offsetHeight;
            let isScrolling = false;
            
            window.addEventListener('scroll', function() {
                if (!isScrolling) {
                    window.requestAnimationFrame(function() {
                        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                        
                        if (scrollTop > lastScrollTop && scrollTop > 100) {
                            // Scrolling down
                            header.style.transform = 'translateY(-100%)';
                        } else {
                            // Scrolling up
                            header.style.transform = 'translateY(0)';
                        }
                        
                        lastScrollTop = scrollTop;
                        isScrolling = false;
                    });
                    
                    isScrolling = true;
                }
            });
            
            // Fix for iOS rubber band effect
            document.body.addEventListener('touchmove', function(e) {
                if (isMobileMenuOpen) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Handle keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (isMobileMenuOpen && e.key === 'Tab') {
                    const focusableElements = mobileMenu.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];
                    
                    if (e.shiftKey && document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            });
            
            // Update stat boxes layout for mobile
            function updateStatBoxesLayout() {
                const statBoxes = document.querySelectorAll('.stat-item');
                const statsGrid = document.querySelector('.stats-grid');
                
                if (window.innerWidth <= 576) {
                    if (statBoxes.length === 4) {
                        statsGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                        statsGrid.style.maxWidth = '400px';
                    }
                } else if (window.innerWidth <= 400) {
                    statsGrid.style.gridTemplateColumns = '1fr';
                    statsGrid.style.maxWidth = '280px';
                } else {
                    statsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
                    statsGrid.style.maxWidth = '1200px';
                }
            }
            
            updateStatBoxesLayout();
            window.addEventListener('resize', updateStatBoxesLayout);
            
            // Load images lazily
            const images = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => imageObserver.observe(img));
            
            // Handle device orientation changes
            window.addEventListener('orientationchange', function() {
                // Close mobile menu on orientation change
                if (isMobileMenuOpen) {
                    closeMobileMenu();
                }
                
                // Recalculate layout
                setTimeout(() => {
                    setVH();
                    updateStatBoxesLayout();
                }, 300);
            });
            
            // Initialize with proper state
            console.log('About page mobile menu initialized');
            console.log('Viewport width:', window.innerWidth);
            console.log('Viewport height:', window.innerHeight);
        });
    </script>
</body>
</html>