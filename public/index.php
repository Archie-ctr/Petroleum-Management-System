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

// Get active stations (assuming stations are branches)
$stations_sql = "SELECT branch_name, location, address FROM branches WHERE status = 'active' LIMIT 6";
$stations_result = $conn->query($stations_sql);
$stations = [];
while ($row = $stations_result->fetch_assoc()) {
    $stations[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo $company_name; ?> - Leading Petroleum Management in Liberia</title>
    
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
            --warm-yellow: #FFEB3B;
            --accent-yellow: #F1C40F;
            --dark-gray: #2C3E50;
            --medium-gray: #34495E;
            --light-gray: #ECF0F1;
            --white: #FFFFFF;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
        
        /* Hero Section - Adjusted for mobile */
        .hero-section {
            background:
                linear-gradient(90deg, #0a2744 0%, rgba(10, 39, 68, 0.98) 42%, rgba(10, 39, 68, 0.72) 68%, rgba(10, 39, 68, 0.24) 100%),
                url('images/petroleum-management-hero.png') center / cover no-repeat;
            background-size: cover;
            background-position: center right;
            background-attachment: scroll;
            padding: 100px 0 60px;
            position: relative;
            overflow: hidden;
            min-height: min(760px, 100vh);
            display: flex;
            align-items: center;
            margin-top: -70px;
            padding-top: 170px;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                background-image:
                    linear-gradient(90deg, rgba(10, 39, 68, 0.98) 0%, rgba(10, 39, 68, 0.91) 58%, rgba(10, 39, 68, 0.72) 100%),
                    url('images/petroleum-management-hero.png');
                background-position: 63% center;
                padding-top: 150px;
                padding-bottom: 40px;
                min-height: calc(100vh - 70px);
                display: flex;
                align-items: center;
            }
        }
        
        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23FFD700' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .hero-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2.2rem;
            margin-bottom: 20px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
            line-height: 1.2;
            animation: fadeInUp 1s ease-out;
            word-wrap: break-word;
        }
        
        .hero-title .highlight {
            color: var(--primary-yellow);
            position: relative;
            display: inline-block;
            padding: 0 8px;
        }
        
        .hero-title .highlight::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, var(--primary-yellow), var(--accent-yellow));
            z-index: -1;
            border-radius: 8px;
            opacity: 0.2;
            transform: skewX(-15deg);
        }
        
        .hero-subtitle {
            font-size: 1rem;
            max-width: 100%;
            margin: 0 auto 30px;
            opacity: 0.95;
            line-height: 1.6;
            animation: fadeInUp 1s ease-out 0.2s both;
            word-wrap: break-word;
            padding: 0 5px;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 40px 0;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.4s both;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px 15px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            min-width: 120px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            flex: 1;
            min-width: 0;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .stat-box:hover, .stat-box:active {
            transform: translateY(-10px) scale(1.05);
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
        }
        
        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: var(--primary-yellow);
            display: block;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.95;
            margin-top: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.6s both;
        }
        
        .btn-hero-primary {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--accent-yellow) 100%);
            color: var(--dark-gray);
            border: none;
            padding: 16px 30px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            position: relative;
            overflow: hidden;
            justify-content: center;
            text-align: center;
            flex: 1;
            min-width: 0;
            max-width: 100%;
            min-height: 56px;
        }
        
        .btn-hero-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-hero-primary:hover::before, .btn-hero-primary:active::before {
            left: 100%;
        }
        
        .btn-hero-primary:hover, .btn-hero-primary:active {
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 15px 30px rgba(255, 215, 0, 0.5);
        }
        
        .btn-hero-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 16px 30px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            justify-content: center;
            text-align: center;
            flex: 1;
            min-width: 0;
            max-width: 100%;
            min-height: 56px;
        }
        
        .btn-hero-secondary:hover, .btn-hero-secondary:active {
            background: white;
            color: var(--dark-green);
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        /* Features Section */
        .features-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
            position: relative;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--dark-green);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 5px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
        }
        
        .section-subtitle {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 100%;
            margin: 0 auto;
            font-weight: 500;
            padding: 0 15px;
            line-height: 1.6;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.4s ease;
            border: 2px solid transparent;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .feature-card:hover, .feature-card:active {
            transform: translateY(-15px) scale(1.03);
            border-color: var(--primary-yellow);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--dark-green);
            transition: all 0.4s ease;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover .feature-icon, .feature-card:active .feature-icon {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--primary-green) 100%);
            color: white;
            transform: rotateY(360deg);
        }
        
        .feature-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--dark-gray);
            position: relative;
            padding-bottom: 12px;
        }
        
        .feature-card h3::after {
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
        
        .feature-card p {
            color: var(--medium-gray);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* Petroleum in Liberia Section */
        .liberia-section {
            padding: 60px 0;
            background: white;
            position: relative;
        }
        
        .liberia-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .liberia-image {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            position: relative;
        }
        
        .liberia-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 215, 0, 0.2), rgba(39, 174, 96, 0.2));
            z-index: 1;
        }
        
        .liberia-image img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.5s ease;
            display: block;
        }
        
        .liberia-image:hover img {
            transform: scale(1.05);
        }
        
        .liberia-text h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--dark-green);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .liberia-text h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
        }
        
        .liberia-text p {
            color: var(--medium-gray);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            border-left: 4px solid var(--primary-yellow);
        }
        
        .highlight-box h4 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-green);
            margin-bottom: 12px;
            font-size: 1.2rem;
        }
        
        /* Stations Section */
        .stations-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            position: relative;
        }
        
        .stations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .station-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
            position: relative;
        }
        
        .station-card:hover, .station-card:active {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        
        .station-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .station-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
            animation: rotate 20s linear infinite;
        }
        
        .station-icon {
            width: 65px;
            height: 65px;
            background: var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            color: var(--dark-gray);
            position: relative;
            z-index: 2;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .station-name {
            color: white;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
        }
        
        .station-location {
            color: var(--light-yellow);
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }
        
        .station-body {
            padding: 25px;
        }
        
        .station-info {
            list-style: none;
            padding: 0;
        }
        
        .station-info li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--medium-gray);
            font-size: 0.95rem;
        }
        
        .station-info i {
            color: var(--primary-green);
            width: 18px;
            font-size: 1rem;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 60px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .cta-content h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .cta-content p {
            font-size: 1rem;
            opacity: 0.95;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-cta {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--accent-yellow) 100%);
            color: var(--dark-gray);
            border: none;
            padding: 18px 40px;
            border-radius: 40px;
            font-weight: 800;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.4);
            position: relative;
            overflow: hidden;
            justify-content: center;
            text-align: center;
            width: 100%;
            max-width: 100%;
            min-height: 60px;
        }
        
        .btn-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-cta:hover::before, .btn-cta:active::before {
            left: 100%;
        }
        
        .btn-cta:hover, .btn-cta:active {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 40px rgba(255, 215, 0, 0.5);
        }
        
        /* Footer */
        .main-footer {
            background: var(--dark-gray);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-col h4 {
            font-family: 'Raleway', sans-serif;
            font-weight: 800;
            color: var(--primary-yellow);
            margin-bottom: 20px;
            font-size: 1.2rem;
            position: relative;
            padding-bottom: 10px;
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
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-15px);
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
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        .animate-up {
            opacity: 0;
            transform: translateY(20px);
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
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.8rem;
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
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 0.85rem;
                padding: 0;
            }
            
            .hero-stats {
                gap: 10px;
            }
            
            .stat-box {
                min-width: calc(50% - 10px);
                padding: 15px 10px;
            }
            
            .stat-number {
                font-size: 1.6rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn-hero-primary,
            .btn-hero-secondary {
                width: 100%;
                max-width: 280px;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .section-subtitle {
                font-size: 0.85rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .feature-card {
                padding: 20px 15px;
            }
            
            .feature-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }
            
            .feature-card h3 {
                font-size: 1.2rem;
            }
            
            .liberia-text h2 {
                font-size: 1.5rem;
            }
            
            .liberia-image img {
                height: 250px;
            }
            
            .liberia-text p {
                font-size: 0.9rem;
            }
            
            .highlight-box {
                padding: 20px;
            }
            
            .highlight-box h4 {
                font-size: 1.1rem;
            }
            
            .highlight-box p {
                font-size: 0.9rem;
            }
            
            .stations-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .station-header {
                padding: 20px;
            }
            
            .station-icon {
                width: 55px;
                height: 55px;
                font-size: 1.6rem;
            }
            
            .station-name {
                font-size: 1.1rem;
            }
            
            .station-location {
                font-size: 0.85rem;
            }
            
            .station-body {
                padding: 20px;
            }
            
            .station-info li {
                font-size: 0.9rem;
            }
            
            .cta-content h2 {
                font-size: 1.5rem;
            }
            
            .cta-content p {
                font-size: 0.85rem;
            }
            
            .btn-cta {
                padding: 16px 30px;
                font-size: 0.9rem;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
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
            .hero-title {
                font-size: 1.6rem;
            }
            
            .hero-subtitle {
                font-size: 0.8rem;
            }
            
            .stat-box {
                min-width: calc(50% - 5px);
                padding: 12px 8px;
            }
            
            .stat-number {
                font-size: 1.4rem;
            }
            
            .btn-hero-primary,
            .btn-hero-secondary {
                padding: 14px 20px;
                font-size: 0.85rem;
            }
            
            .section-title {
                font-size: 1.3rem;
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
            
            .hero-title {
                font-size: 1.4rem;
            }
            
            .stat-box {
                min-width: calc(50% - 5px);
                padding: 10px 5px;
            }
            
            .stat-number {
                font-size: 1.2rem;
            }
            
            .stat-label {
                font-size: 0.7rem;
            }
            
            .btn-hero-primary,
            .btn-hero-secondary {
                padding: 12px 18px;
                font-size: 0.8rem;
            }
            
            .section-title {
                font-size: 1.2rem;
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
            
            .hero-stats {
                flex-wrap: nowrap;
                overflow-x: auto;
                justify-content: flex-start;
                padding-bottom: 10px;
                margin: 30px -15px;
                scrollbar-width: none;
            }
            
            .hero-stats::-webkit-scrollbar {
                display: none;
            }
            
            .stat-box {
                flex-shrink: 0;
                width: 140px;
            }
            
            .hero-buttons {
                margin-top: 30px;
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
                    <li class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Home</a></li>
                    <li class="nav-item"><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
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
            <li><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
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
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-pattern"></div>
            <div class="container public-hero-layout">
                <div class="hero-content">
                    <h1 class="hero-title">
                        <span class="highlight">Petroleum</span> Excellence <br>in Liberia
                    </h1>
                    <p class="hero-subtitle">
                        GOD'S WILLING INC is Liberia's leading petroleum management company, providing cutting-edge 
                        technology solutions for fuel stations. We're committed to revolutionizing the petroleum industry 
                        through innovation, reliability, and exceptional service.
                    </p>
                    
                    <div class="hero-stats">
                        <div class="stat-box floating" style="animation-delay: 0s;">
                            <span class="stat-number"><?php echo $branch_count; ?>+</span>
                            <span class="stat-label">Stations</span>
                        </div>
                        <div class="stat-box floating" style="animation-delay: 0.2s;">
                            <span class="stat-number">24/7</span>
                            <span class="stat-label">Support</span>
                        </div>
                        <div class="stat-box floating" style="animation-delay: 0.4s;">
                            <span class="stat-number">10+</span>
                            <span class="stat-label">Years</span>
                        </div>
                        <div class="stat-box floating" style="animation-delay: 0.6s;">
                            <span class="stat-number">100%</span>
                            <span class="stat-label">Reliable</span>
                        </div>
                    </div>
                    
                    <div class="hero-buttons">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="btn-hero-primary">
                                <i class="fas fa-rocket"></i> Get Started Now
                            </a>
                            <a href="#features" class="btn-hero-secondary">
                                <i class="fas fa-play-circle"></i> Learn More
                            </a>
                        <?php else: ?>
                            <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="btn-hero-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                            <a href="../public/logout.php" class="btn-hero-secondary">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Our Comprehensive Solutions</h2>
                    <p class="section-subtitle">Designed specifically for Liberia's petroleum industry needs</p>
                </div>
                
                <div class="feature-grid">
                    <div class="feature-card animate-up" style="animation-delay: 0.1s;">
                        <div class="feature-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <h3>Fuel Management</h3>
                        <p>Advanced tank monitoring, automated inventory tracking, and real-time fuel level management for optimal operations.</p>
                    </div>
                    
                    <div class="feature-card animate-up" style="animation-delay: 0.2s;">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Business Analytics</h3>
                        <p>Comprehensive insights and reporting tools to help you make data-driven decisions and maximize profitability.</p>
                    </div>
                    
                    <div class="feature-card animate-up" style="animation-delay: 0.3s;">
                        <div class="feature-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h3>Multi-Role System</h3>
                        <p>Secure role-based access for administrators, managers, attendants, and accountants with customizable permissions.</p>
                    </div>
                    
                    <div class="feature-card animate-up" style="animation-delay: 0.4s;">
                        <div class="feature-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3>Multi-Branch Control</h3>
                        <p>Centralized management of multiple fuel stations across Liberia with synchronized operations and reporting.</p>
                    </div>
                    
                    <div class="feature-card animate-up" style="animation-delay: 0.5s;">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h3>Financial Management</h3>
                        <p>Complete financial suite including expense tracking, invoicing, payment processing, and profit analysis.</p>
                    </div>
                    
                    <div class="feature-card animate-up" style="animation-delay: 0.6s;">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Enterprise Security</h3>
                        <p>Bank-level security with data encryption, audit trails, access logs, and compliance with industry standards.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Petroleum in Liberia Section -->
        <section class="liberia-section" id="liberia">
            <div class="container">
                <div class="liberia-content">
                    <div class="liberia-image">
                        <img src="images/fuel-delivery.svg" alt="Fuel delivery supporting petroleum businesses across Liberia" width="800" height="500" loading="lazy">
                    </div>
                    <div class="liberia-text">
                        <h2>Transforming Liberia's Petroleum Sector</h2>
                        <p>
                            As Liberia's leading petroleum management company, GOD'S WILLING INC is at the forefront of 
                            technological innovation in the fuel industry. We understand the unique challenges and 
                            opportunities in Liberia's petroleum sector and have developed solutions specifically 
                            tailored to meet these needs.
                        </p>
                        <p>
                            Our mission is to empower fuel stations across Liberia with cutting-edge technology that 
                            streamlines operations, reduces costs, and maximizes profits. We're committed to supporting 
                            the growth and development of Liberia's energy infrastructure through reliable, scalable, 
                            and innovative solutions.
                        </p>
                        
                        <div class="highlight-box">
                            <h4>Our Commitment to Liberia</h4>
                            <p>
                                We're proud to contribute to Liberia's economic growth by providing employment opportunities, 
                                supporting local businesses, and investing in the country's petroleum infrastructure. Our 
                                leadership team, led by Mr. Karnue and Mrs. Mamie Karnue, is dedicated to building a 
                                sustainable future for Liberia's energy sector.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stations Section -->
        <section class="stations-section" id="stations">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Our Petroleum Stations</h2>
                    <p class="section-subtitle">Strategic locations across Liberia providing reliable fuel services</p>
                </div>
                
                <div class="stations-grid">
                    <?php if (count($stations) > 0): ?>
                        <?php foreach ($stations as $station): ?>
                        <div class="station-card animate-up">
                            <div class="station-header">
                                <div class="station-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <h3 class="station-name"><?php echo htmlspecialchars($station['branch_name']); ?></h3>
                                <p class="station-location"><?php echo htmlspecialchars($station['location']); ?></p>
                            </div>
                            <div class="station-body">
                                <ul class="station-info">
                                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($station['address']); ?></li>
                                    <li><i class="fas fa-clock"></i> Open 24/7</li>
                                    <li><i class="fas fa-gas-pump"></i> All Fuel Types Available</li>
                                    <li><i class="fas fa-credit-card"></i> Multiple Payment Options</li>
                                </ul>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default stations if none in database -->
                        <div class="station-card animate-up">
                            <div class="station-header">
                                <div class="station-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <h3 class="station-name">Monrovia Main Station</h3>
                                <p class="station-location">Central Monrovia</p>
                            </div>
                            <div class="station-body">
                                <ul class="station-info">
                                    <li><i class="fas fa-map-marker-alt"></i> Broad Street, Monrovia</li>
                                    <li><i class="fas fa-clock"></i> Open 24/7</li>
                                    <li><i class="fas fa-gas-pump"></i> All Fuel Types Available</li>
                                    <li><i class="fas fa-credit-card"></i> Multiple Payment Options</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="station-card animate-up" style="animation-delay: 0.1s;">
                            <div class="station-header">
                                <div class="station-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <h3 class="station-name">Paynesville Station</h3>
                                <p class="station-location">Paynesville City</p>
                            </div>
                            <div class="station-body">
                                <ul class="station-info">
                                    <li><i class="fas fa-map-marker-alt"></i> ELWA Road, Paynesville</li>
                                    <li><i class="fas fa-clock"></i> 6:00 AM - 10:00 PM</li>
                                    <li><i class="fas fa-gas-pump"></i> Premium & Regular Fuel</li>
                                    <li><i class="fas fa-credit-card"></i> Cash & Mobile Money</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="station-card animate-up" style="animation-delay: 0.2s;">
                            <div class="station-header">
                                <div class="station-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <h3 class="station-name">Kakata Station</h3>
                                <p class="station-location">Margibi County</p>
                            </div>
                            <div class="station-body">
                                <ul class="station-info">
                                    <li><i class="fas fa-map-marker-alt"></i> Gbanga Highway, Kakata</li>
                                    <li><i class="fas fa-clock"></i> Open 24/7</li>
                                    <li><i class="fas fa-gas-pump"></i> Diesel & Petrol</li>
                                    <li><i class="fas fa-credit-card"></i> All Payment Methods</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-5">
                    <p class="lead">We operate <?php echo $branch_count > 0 ? $branch_count : 'multiple'; ?> stations across Liberia</p>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-content animate-up">
                    <h2>Join Liberia's Petroleum Revolution</h2>
                    <p>
                        Be part of the transformation in Liberia's petroleum industry. Our comprehensive management 
                        system has helped dozens of fuel stations increase efficiency by up to 40% and reduce 
                        operational costs significantly.
                    </p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn-cta">
                            <i class="fas fa-rocket"></i> Start Your Transformation Today
                        </a>
                    <?php else: ?>
                        <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="btn-cta">
                            <i class="fas fa-tachometer-alt"></i> Access Your Management Portal
                        </a>
                    <?php endif; ?>
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
                    &copy; <span class="current-year"><?php echo date('Y'); ?></span> GOD'S WILLING INC. All rights reserved. | 
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
                console.log('Viewport height set to:', vh * 100 + 'px');
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
            document.querySelectorAll('.feature-card, .station-card, .animate-up').forEach(el => {
                if (!el.classList.contains('hero-content') && !el.classList.contains('cta-content')) {
                    observer.observe(el);
                }
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
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
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
                const heroSection = document.querySelector('.hero-section');
                if (heroSection && window.innerWidth > 768) {
                    const rate = scrolled * 0.5;
                    heroSection.style.transform = `translate3d(0, ${rate}px, 0)`;
                }
                
                // Floating animation for stat boxes
                const statBoxes = document.querySelectorAll('.stat-box');
                statBoxes.forEach((box, index) => {
                    const delay = index * 0.2;
                    const y = Math.sin((scrolled * 0.002) + delay) * 15;
                    box.style.transform = `translateY(${y}px)`;
                });
            });
            
            // Update current year in footer
            const currentYear = new Date().getFullYear();
            document.querySelectorAll('.current-year').forEach(el => {
                el.textContent = currentYear;
            });
            
            // Touch-friendly improvements
            document.querySelectorAll('button, a, .nav-link').forEach(el => {
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
                const statBoxes = document.querySelectorAll('.stat-box');
                const heroStats = document.querySelector('.hero-stats');
                
                if (window.innerWidth <= 576) {
                    statBoxes.forEach(box => {
                        box.style.minWidth = 'calc(50% - 10px)';
                        box.style.flex = '0 0 calc(50% - 10px)';
                    });
                    
                    if (window.innerWidth <= 360) {
                        heroStats.style.flexWrap = 'nowrap';
                        heroStats.style.overflowX = 'auto';
                        statBoxes.forEach(box => {
                            box.style.minWidth = '140px';
                            box.style.flex = '0 0 auto';
                        });
                    } else {
                        heroStats.style.flexWrap = 'wrap';
                        heroStats.style.overflowX = 'visible';
                    }
                } else {
                    statBoxes.forEach(box => {
                        box.style.minWidth = '';
                        box.style.flex = '';
                    });
                    heroStats.style.flexWrap = 'wrap';
                    heroStats.style.overflowX = 'visible';
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
            
            // Add loading state to buttons
            document.querySelectorAll('a[href], button[type="submit"]').forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.classList.contains('mobile-menu-btn') && !this.classList.contains('mobile-menu-close')) {
                        this.classList.add('loading');
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 1000);
                    }
                });
            });
            
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
            
            // Prevent unwanted page refresh on pull-to-refresh
            let touchStartY = 0;
            document.addEventListener('touchstart', (e) => {
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            
            document.addEventListener('touchmove', (e) => {
                const touchY = e.touches[0].clientY;
                const touchDiff = touchStartY - touchY;
                
                // If at the top of the page and pulling down, prevent default
                if (window.scrollY === 0 && touchDiff < 0) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Initialize with proper state
            console.log('Mobile menu initialized');
            console.log('Viewport width:', window.innerWidth);
            console.log('Viewport height:', window.innerHeight);
        });
    </script>
</body>
</html>