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

// Get current fuel prices
$fuel_sql = "SELECT * FROM fuel_types WHERE status = 'active' ORDER BY price_per_liter DESC";
$fuels = $conn->query($fuel_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Current Fuel Prices - <?php echo $company_name; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@700;800;900&family=Raleway:wght@800;900&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .nav-link:hover, .nav-link:active {
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
        
        .btn-login:hover, .btn-login:active {
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
        
        .btn-dashboard:hover, .btn-dashboard:active {
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
        
        /* Hero Section for Prices Page - Adjusted for mobile */
        .prices-hero {
            background: linear-gradient(rgba(30, 132, 73, 0.95), rgba(39, 174, 96, 0.95));
            padding: 80px 0 40px;
            position: relative;
            overflow: hidden;
            text-align: center;
            color: white;
            margin-top: -70px;
            padding-top: 150px;
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .prices-hero {
                padding-top: 120px;
                padding-bottom: 30px;
                min-height: calc(100vh - 70px);
            }
        }
        
        .prices-hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23FFD700' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        .prices-hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .prices-hero-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
            line-height: 1.2;
            animation: fadeInUp 1s ease-out;
        }
        
        .prices-hero-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 25px;
            line-height: 1.6;
            animation: fadeInUp 1s ease-out 0.2s both;
        }
        
        /* Current Prices Info */
        .current-prices-info {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 30px auto;
            max-width: 600px;
            animation: fadeInUp 1s ease-out 0.4s both;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .current-prices-info i {
            color: var(--primary-yellow);
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        /* Fuel Prices Section */
        .fuel-prices-section {
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
        
        /* Fuel Cards Grid */
        .fuel-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .fuel-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
            position: relative;
            border: 2px solid transparent;
        }
        
        .fuel-card:hover, .fuel-card:active {
            transform: translateY(-15px);
            border-color: var(--primary-yellow);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        
        .fuel-card-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 30px 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .fuel-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
            animation: rotate 20s linear infinite;
        }
        
        .fuel-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.2rem;
            color: var(--dark-gray);
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.4s ease;
        }
        
        .fuel-card:hover .fuel-icon, .fuel-card:active .fuel-icon {
            transform: rotateY(360deg);
            background: white;
            color: var(--dark-green);
        }
        
        .fuel-name {
            color: white;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
        }
        
        .fuel-price {
            color: var(--light-yellow);
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }
        
        .fuel-card-body {
            padding: 30px 25px;
            text-align: center;
        }
        
        .fuel-price-main {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            color: var(--dark-green);
            margin-bottom: 15px;
            line-height: 1;
        }
        
        .fuel-unit {
            color: var(--medium-gray);
            font-size: 1rem;
            margin-bottom: 20px;
            display: block;
        }
        
        .fuel-description {
            color: var(--medium-gray);
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        
        .fuel-badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .fuel-badge {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            color: var(--dark-green);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .fuel-badge.code {
            background: var(--primary-yellow);
            color: var(--dark-gray);
        }
        
        /* Price Trends Chart */
        .price-trends-section {
            padding: 60px 0;
            background: white;
        }
        
        .trends-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            min-height: 300px;
        }
        
        .trends-card-header {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .trends-card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--dark-green);
            font-size: 1.5rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        .trends-card-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 2px;
        }
        
        /* Additional Information Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .info-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
        }
        
        .info-card:hover, .info-card:active {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .info-card-header {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--accent-yellow) 100%);
            padding: 25px;
            text-align: center;
        }
        
        .info-card-header i {
            font-size: 2.5rem;
            color: var(--dark-gray);
            margin-bottom: 15px;
            display: block;
        }
        
        .info-card-header h3 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-gray);
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .info-card-body {
            padding: 25px;
        }
        
        .info-card-list {
            list-style: none;
            padding: 0;
        }
        
        .info-card-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            color: var(--medium-gray);
        }
        
        .info-card-list i {
            color: var(--primary-green);
            font-size: 1.1rem;
            width: 20px;
        }
        
        .progress-section {
            margin-top: 20px;
        }
        
        .progress-item {
            margin-bottom: 20px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .progress-bar-custom {
            height: 12px;
            background: var(--light-gray);
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 1.5s ease;
        }
        
        .progress-fill.quality {
            background: linear-gradient(to right, #27AE60, #2ECC71);
        }
        
        .progress-fill.satisfaction {
            background: linear-gradient(to right, #3498DB, #2980B9);
        }
        
        .progress-fill.purity {
            background: linear-gradient(to right, #FFD700, #F1C40F);
        }
        
        /* CTA Section */
        .prices-cta-section {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 60px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .prices-cta-content {
            position: relative;
            z-index: 2;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .prices-cta-content h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .prices-cta-content p {
            font-size: 1rem;
            opacity: 0.95;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-prices-cta {
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
        
        .btn-prices-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-prices-cta:hover::before, .btn-prices-cta:active::before {
            left: 100%;
        }
        
        .btn-prices-cta:hover, .btn-prices-cta:active {
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
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        /* Loading animation for better UX */
        .loading {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .loaded {
            opacity: 1;
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
            
            .prices-hero-title {
                font-size: 2rem;
            }
            
            .prices-hero-subtitle {
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
            
            .prices-hero-title {
                font-size: 1.6rem;
            }
            
            .prices-hero-subtitle {
                font-size: 0.85rem;
            }
            
            .current-prices-info {
                padding: 15px;
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .section-subtitle {
                font-size: 0.85rem;
            }
            
            .fuel-cards-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                max-width: 400px;
                padding: 0 10px;
            }
            
            .fuel-card-header {
                padding: 25px 20px;
            }
            
            .fuel-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }
            
            .fuel-name {
                font-size: 1.3rem;
            }
            
            .fuel-card-body {
                padding: 25px 20px;
            }
            
            .fuel-price-main {
                font-size: 2.2rem;
            }
            
            .fuel-description {
                font-size: 0.9rem;
            }
            
            .fuel-badges {
                gap: 8px;
            }
            
            .fuel-badge {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
                gap: 15px;
                max-width: 400px;
                margin: 40px auto 0;
                padding: 0 10px;
            }
            
            .trends-card {
                padding: 25px 20px;
                margin: 0 10px 30px;
                min-height: 250px;
            }
            
            .trends-card-title {
                font-size: 1.3rem;
            }
            
            .info-card-header {
                padding: 20px;
            }
            
            .info-card-header i {
                font-size: 2rem;
            }
            
            .info-card-header h3 {
                font-size: 1.2rem;
            }
            
            .info-card-body {
                padding: 20px;
            }
            
            .info-card-list li {
                font-size: 0.9rem;
            }
            
            .btn-prices-cta {
                padding: 16px 30px;
                font-size: 0.9rem;
                min-height: 56px;
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
            
            /* App-like touch feedback */
            button:active, 
            a:active,
            .nav-link:active,
            .btn-prices-cta:active,
            .social-link:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }
        }
        
        @media (max-width: 576px) {
            .prices-hero-title {
                font-size: 1.4rem;
            }
            
            .prices-hero-subtitle {
                font-size: 0.8rem;
            }
            
            .current-prices-info {
                padding: 12px;
                font-size: 0.85rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .section-subtitle {
                font-size: 0.8rem;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .logo-wrapper {
                width: 40px;
                height: 40px;
            }
            
            .btn-prices-cta {
                padding: 14px 25px;
                font-size: 0.85rem;
            }
            
            .fuel-price-main {
                font-size: 2rem;
            }
            
            .fuel-name {
                font-size: 1.2rem;
            }
            
            .prices-cta-content h2 {
                font-size: 1.4rem;
            }
            
            .prices-cta-content p {
                font-size: 0.85rem;
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
            
            .prices-hero-title {
                font-size: 1.3rem;
            }
            
            .prices-hero-subtitle {
                font-size: 0.75rem;
            }
            
            .fuel-cards-grid {
                padding: 0 5px;
            }
            
            .footer-container {
                padding: 0 10px;
            }
            
            .mobile-menu {
                width: 90%;
            }
            
            .fuel-icon {
                width: 65px;
                height: 65px;
                font-size: 1.6rem;
            }
            
            .fuel-price-main {
                font-size: 1.8rem;
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
            
            .prices-hero-title {
                font-size: 1.2rem;
            }
            
            .prices-hero-subtitle {
                font-size: 0.7rem;
            }
            
            .fuel-cards-grid {
                padding: 0 5px;
            }
            
            .fuel-card-header {
                padding: 20px 15px;
            }
            
            .fuel-card-body {
                padding: 20px 15px;
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
        
        /* Swipe to close mobile menu */
        .mobile-menu {
            touch-action: pan-y;
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
                    <li class="nav-item"><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
                    <li class="nav-item"><a href="services.php" class="nav-link"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li class="nav-item"><a href="prices.php" class="nav-link active"><i class="fas fa-dollar-sign"></i> Prices</a></li>
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
            <li><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
            <li><a href="services.php" class="nav-link"><i class="fas fa-concierge-bell"></i> Services</a></li>
            <li><a href="prices.php" class="nav-link active"><i class="fas fa-dollar-sign"></i> Prices</a></li>
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
        <!-- Hero Section for Prices -->
        <section class="prices-hero">
            <div class="prices-hero-pattern"></div>
            <div class="container">
                <div class="prices-hero-content">
                    <h1 class="prices-hero-title">Current Fuel Prices in Liberia</h1>
                    <p class="prices-hero-subtitle">
                        Get real-time fuel prices from GOD'S WILLING INC - Liberia's trusted petroleum provider. 
                        We offer competitive rates with premium quality fuels for all your needs.
                    </p>
                    <img class="public-hero-image" src="images/fuel-station.svg" alt="Fuel station serving drivers in Liberia" width="900" height="640" fetchpriority="high">
                    
                    <div class="current-prices-info animate-up">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="fas fa-info-circle"></i>
                            <span>Prices are updated regularly. Last updated: <?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Fuel Prices Section -->
        <section class="fuel-prices-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Current Fuel Prices</h2>
                    <p class="section-subtitle">Competitive rates for premium quality fuels across all our stations</p>
                </div>
                
                <div class="fuel-cards-grid">
                    <?php if ($fuels->num_rows > 0): ?>
                        <?php while($fuel = $fuels->fetch_assoc()): ?>
                        <div class="fuel-card animate-up">
                            <div class="fuel-card-header">
                                <div class="fuel-icon">
                                    <?php if (strpos($fuel['fuel_name'], 'Petrol') !== false || strpos($fuel['fuel_name'], 'petrol') !== false): ?>
                                        <i class="fas fa-gas-pump"></i>
                                    <?php elseif (strpos($fuel['fuel_name'], 'Diesel') !== false || strpos($fuel['fuel_name'], 'diesel') !== false): ?>
                                        <i class="fas fa-truck"></i>
                                    <?php elseif (strpos($fuel['fuel_name'], 'Kerosene') !== false || strpos($fuel['fuel_name'], 'kerosene') !== false): ?>
                                        <i class="fas fa-fire"></i>
                                    <?php else: ?>
                                        <i class="fas fa-oil-can"></i>
                                    <?php endif; ?>
                                </div>
                                <h3 class="fuel-name"><?php echo htmlspecialchars($fuel['fuel_name']); ?></h3>
                                <p class="fuel-price">Premium Quality Fuel</p>
                            </div>
                            <div class="fuel-card-body">
                                <div class="fuel-price-main">
                                    $<?php echo number_format($fuel['price_per_liter'], 2); ?>
                                </div>
                                <span class="fuel-unit">per <?php echo htmlspecialchars($fuel['unit']); ?></span>
                                
                                <p class="fuel-description">
                                    <?php echo htmlspecialchars($fuel['description'] ?? 'High-quality fuel for optimal performance and efficiency.'); ?>
                                </p>
                                
                                <div class="fuel-badges">
                                    <span class="fuel-badge code">Code: <?php echo htmlspecialchars($fuel['fuel_code']); ?></span>
                                    <span class="fuel-badge">Available Now</span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- Default fuel prices if none in database -->
                        <div class="fuel-card animate-up">
                            <div class="fuel-card-header">
                                <div class="fuel-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <h3 class="fuel-name">Petrol Super</h3>
                                <p class="fuel-price">Premium Petrol</p>
                            </div>
                            <div class="fuel-card-body">
                                <div class="fuel-price-main">
                                    $1.50
                                </div>
                                <span class="fuel-unit">per Liter</span>
                                
                                <p class="fuel-description">
                                    High-octane premium petrol for superior engine performance and fuel efficiency.
                                </p>
                                
                                <div class="fuel-badges">
                                    <span class="fuel-badge code">Code: PS</span>
                                    <span class="fuel-badge">Available Now</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="fuel-card animate-up" style="animation-delay: 0.1s;">
                            <div class="fuel-card-header">
                                <div class="fuel-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <h3 class="fuel-name">Diesel</h3>
                                <p class="fuel-price">Regular Diesel</p>
                            </div>
                            <div class="fuel-card-body">
                                <div class="fuel-price-main">
                                    $1.30
                                </div>
                                <span class="fuel-unit">per Liter</span>
                                
                                <p class="fuel-description">
                                    High-quality diesel fuel for trucks, buses, and heavy machinery with excellent mileage.
                                </p>
                                
                                <div class="fuel-badges">
                                    <span class="fuel-badge code">Code: DL</span>
                                    <span class="fuel-badge">Available Now</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="fuel-card animate-up" style="animation-delay: 0.2s;">
                            <div class="fuel-card-header">
                                <div class="fuel-icon">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <h3 class="fuel-name">Kerosene</h3>
                                <p class="fuel-price">Household Kerosene</p>
                            </div>
                            <div class="fuel-card-body">
                                <div class="fuel-price-main">
                                    $1.10
                                </div>
                                <span class="fuel-unit">per Liter</span>
                                
                                <p class="fuel-description">
                                    Clean-burning kerosene for lighting, heating, and cooking with minimal smoke.
                                </p>
                                
                                <div class="fuel-badges">
                                    <span class="fuel-badge code">Code: KN</span>
                                    <span class="fuel-badge">Available Now</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Price Trends Section -->
        <section class="price-trends-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Fuel Price Trends</h2>
                    <p class="section-subtitle">Historical price analysis for informed decision making</p>
                </div>
                
                <div class="trends-card animate-up">
                    <div class="trends-card-header">
                        <h3 class="trends-card-title">Price Trends (Last 30 Days)</h3>
                    </div>
                    <div class="trends-card-body">
                        <canvas id="priceChart" height="150"></canvas>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="info-cards">
                    <div class="info-card animate-up" style="animation-delay: 0.1s;">
                        <div class="info-card-header">
                            <i class="fas fa-question-circle"></i>
                            <h3>Pricing Information</h3>
                        </div>
                        <div class="info-card-body">
                            <ul class="info-card-list">
                                <li><i class="fas fa-check"></i> All prices include applicable taxes</li>
                                <li><i class="fas fa-check"></i> Volume discounts available for bulk purchases</li>
                                <li><i class="fas fa-check"></i> Credit terms available for registered customers</li>
                                <li><i class="fas fa-check"></i> Competitive market rates guaranteed</li>
                                <li><i class="fas fa-check"></i> Special rates for commercial fleet operators</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="info-card animate-up" style="animation-delay: 0.2s;">
                        <div class="info-card-header">
                            <i class="fas fa-gas-pump"></i>
                            <h3>Fuel Quality Standards</h3>
                        </div>
                        <div class="info-card-body">
                            <p>All our fuels meet or exceed industry quality standards:</p>
                            
                            <div class="progress-section">
                                <div class="progress-item">
                                    <div class="progress-label">
                                        <span>Quality Tested</span>
                                        <span>100%</span>
                                    </div>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill quality" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <div class="progress-item">
                                    <div class="progress-label">
                                        <span>Customer Satisfaction</span>
                                        <span>95%</span>
                                    </div>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill satisfaction" style="width: 95%"></div>
                                    </div>
                                </div>
                                
                                <div class="progress-item">
                                    <div class="progress-label">
                                        <span>Purity Level</span>
                                        <span>98%</span>
                                    </div>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill purity" style="width: 98%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="prices-cta-section">
            <div class="container">
                <div class="prices-cta-content animate-up">
                    <h2>Get Real-Time Price Updates</h2>
                    <p>
                        Stay informed about the latest fuel prices in Liberia. Sign up for our price alert system 
                        and get notifications when prices change or special offers are available.
                    </p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn-prices-cta">
                            <i class="fas fa-bell"></i> Sign Up for Price Alerts
                        </a>
                    <?php else: ?>
                        <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="btn-prices-cta">
                            <i class="fas fa-chart-line"></i> View Detailed Price Analytics
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
            document.querySelectorAll('.fuel-card, .info-card, .trends-card, .animate-up').forEach(el => {
                observer.observe(el);
            });
            
            // Add active class to current page in navigation
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            document.querySelectorAll('.nav-link').forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Update current year in footer
            const currentYear = new Date().getFullYear();
            document.querySelectorAll('.current-year').forEach(el => {
                el.textContent = currentYear;
            });
            
            // Animate progress bars on scroll
            const progressObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const progressBars = entry.target.querySelectorAll('.progress-fill');
                        progressBars.forEach(bar => {
                            const width = bar.style.width;
                            bar.style.width = '0';
                            setTimeout(() => {
                                bar.style.width = width;
                            }, 300);
                        });
                        progressObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            
            const infoCards = document.querySelectorAll('.info-card');
            infoCards.forEach(card => {
                progressObserver.observe(card);
            });
            
            // Price Chart - Responsive for mobile
            const createPriceChart = () => {
                const ctx = document.getElementById('priceChart');
                if (!ctx) return;
                
                // Destroy existing chart if it exists
                if (window.priceChartInstance) {
                    window.priceChartInstance.destroy();
                }
                
                const isMobile = window.innerWidth <= 768;
                
                window.priceChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: isMobile ? ['W1', 'W2', 'W3', 'W4', 'Now'] : ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Current'],
                        datasets: [
                            {
                                label: 'Petrol Super',
                                data: [1.45, 1.48, 1.50, 1.52, 1.50],
                                borderColor: 'rgb(39, 174, 96)',
                                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                                borderWidth: isMobile ? 2 : 3,
                                tension: 0.4,
                                fill: true,
                                pointRadius: isMobile ? 4 : 5,
                                pointHoverRadius: isMobile ? 6 : 7
                            },
                            {
                                label: 'Diesel',
                                data: [1.25, 1.28, 1.30, 1.32, 1.30],
                                borderColor: 'rgb(255, 215, 0)',
                                backgroundColor: 'rgba(255, 215, 0, 0.1)',
                                borderWidth: isMobile ? 2 : 3,
                                tension: 0.4,
                                fill: true,
                                pointRadius: isMobile ? 4 : 5,
                                pointHoverRadius: isMobile ? 6 : 7
                            },
                            {
                                label: 'Kerosene',
                                data: [1.05, 1.08, 1.10, 1.12, 1.10],
                                borderColor: 'rgb(52, 152, 219)',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                borderWidth: isMobile ? 2 : 3,
                                tension: 0.4,
                                fill: true,
                                pointRadius: isMobile ? 4 : 5,
                                pointHoverRadius: isMobile ? 6 : 7
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: isMobile ? 'bottom' : 'top',
                                labels: {
                                    padding: isMobile ? 10 : 20,
                                    usePointStyle: true,
                                    font: {
                                        size: isMobile ? 10 : 12,
                                        family: "'Poppins', sans-serif"
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    family: "'Poppins', sans-serif",
                                    size: isMobile ? 11 : 12
                                },
                                bodyFont: {
                                    family: "'Poppins', sans-serif",
                                    size: isMobile ? 11 : 12
                                },
                                padding: isMobile ? 8 : 12,
                                cornerRadius: isMobile ? 6 : 8
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: isMobile ? 10 : 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: false,
                                min: 1.0,
                                max: 1.6,
                                title: {
                                    display: !isMobile,
                                    text: 'Price per Liter ($)',
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: isMobile ? 10 : 12,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: isMobile ? 10 : 11
                                    },
                                    callback: function(value) {
                                        return '$' + value.toFixed(2);
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'nearest'
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            };
            
            // Initialize chart
            createPriceChart();
            
            // Recreate chart on resize
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    createPriceChart();
                }, 250);
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
            
            // Touch-friendly improvements
            document.querySelectorAll('button, a, .nav-link, .fuel-card, .info-card').forEach(el => {
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
            
            // Handle device orientation changes
            window.addEventListener('orientationchange', function() {
                // Close mobile menu on orientation change
                if (isMobileMenuOpen) {
                    closeMobileMenu();
                }
                
                // Recalculate layout
                setTimeout(() => {
                    setVH();
                    createPriceChart();
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
            
            // Initialize with proper state
            console.log('Prices page mobile menu initialized');
            console.log('Viewport width:', window.innerWidth);
            console.log('Viewport height:', window.innerHeight);
        });
    </script>
</body>
</html>