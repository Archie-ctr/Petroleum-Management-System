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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Our Services - <?php echo $company_name; ?> | Petroleum Management Solutions</title>
    
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
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
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
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
            overflow-x: hidden;
            line-height: 1.6;
            color: var(--dark-gray);
            min-height: 100vh;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
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
        
        /* Mobile Menu Overlay */
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
        
        /* Main Content with header offset */
        main {
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* Services Hero Section */
        .services-hero {
            background: linear-gradient(135deg, rgba(18, 91, 53, 0.94) 0%, rgba(39, 174, 96, 0.88) 100%), 
                        url('images/fuel-delivery.svg');
            background-size: cover;
            background-position: center;
            padding: 80px 0 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .services-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23FFD700' fill-opacity='0.15' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        .services-hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .services-hero h1 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            margin-bottom: 15px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            line-height: 1.1;
        }
        
        .services-hero .highlight {
            color: var(--primary-yellow);
            position: relative;
            display: inline-block;
            padding: 0 8px;
        }
        
        .services-hero .highlight::before {
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
        
        .services-hero p {
            font-size: 0.95rem;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        /* Main Services Section */
        .main-services {
            padding: 60px 0;
            background: white;
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
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 6px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
        }
        
        .section-subtitle {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 700px;
            margin: 0 auto;
            font-weight: 500;
            padding: 0 15px;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .service-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
            position: relative;
            border: 2px solid transparent;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .service-card:hover, .service-card:active {
            transform: translateY(-15px) scale(1.02);
            border-color: var(--primary-yellow);
            box-shadow: var(--shadow-hover);
        }
        
        .service-icon-wrapper {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .service-card:hover .service-icon-wrapper, .service-card:active .service-icon-wrapper {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--primary-green) 100%);
        }
        
        .service-icon {
            font-size: 3rem;
            color: var(--dark-green);
            transition: all 0.4s ease;
            position: relative;
            z-index: 2;
        }
        
        .service-card:hover .service-icon, .service-card:active .service-icon {
            color: white;
            transform: rotateY(360deg) scale(1.1);
        }
        
        .service-content {
            padding: 25px;
        }
        
        .service-content h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--dark-gray);
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .service-content h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-yellow);
            border-radius: 2px;
        }
        
        .service-content p {
            color: var(--medium-gray);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .service-features {
            list-style: none;
            padding: 0;
        }
        
        .service-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        .service-features i {
            color: var(--primary-green);
            font-size: 1rem;
            min-width: 20px;
        }
        
        /* Additional Features Section */
        .features-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .feature-item {
            background: white;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .feature-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-yellow);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            transform-origin: left;
        }
        
        .feature-item:hover::before, .feature-item:active::before {
            transform: scaleX(1);
        }
        
        .feature-item:hover, .feature-item:active {
            transform: translateY(-8px);
            border-color: var(--primary-yellow);
            box-shadow: var(--shadow-hover);
        }
        
        .feature-item i {
            font-size: 2rem;
            margin-bottom: 15px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .feature-item:hover i, .feature-item:active i {
            transform: scale(1.2);
        }
        
        .feature-item .text-primary { color: var(--primary-green) !important; }
        .feature-item .text-success { color: var(--primary-green) !important; }
        .feature-item .text-warning { color: var(--primary-yellow) !important; }
        .feature-item .text-danger { color: #E74C3C !important; }
        
        .feature-item h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }
        
        .feature-item p {
            color: var(--medium-gray);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Benefits Section */
        .benefits-section {
            padding: 60px 0;
            background: white;
        }
        
        .benefits-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .benefits-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .benefits-card {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .benefits-card:hover, .benefits-card:active {
            border-color: var(--primary-yellow);
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }
        
        .benefits-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--dark-green);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .benefits-card h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-yellow);
            border-radius: 2px;
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .benefit-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--dark-green);
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover .benefit-icon, .benefit-item:active .benefit-icon {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            transform: rotate(10deg);
        }
        
        .benefit-text h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .benefit-text p {
            color: var(--medium-gray);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .why-us-list {
            list-style: none;
            padding: 0;
        }
        
        .why-us-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .why-us-list li:hover, .why-us-list li:active {
            background: white;
            transform: translateX(8px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .why-us-list i {
            color: var(--primary-yellow);
            font-size: 1.2rem;
            margin-top: 3px;
            min-width: 24px;
        }
        
        .why-us-text strong {
            color: var(--dark-green);
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        .why-us-text p {
            color: var(--medium-gray);
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* CTA Section */
        .services-cta {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 60px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .services-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        
        .services-cta-content {
            position: relative;
            z-index: 2;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .services-cta h2 {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: white;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
        }
        
        .services-cta p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-cta-primary {
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
            gap: 12px;
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
        
        .btn-cta-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-cta-primary:hover::before, .btn-cta-primary:active::before {
            left: 100%;
        }
        
        .btn-cta-primary:hover, .btn-cta-primary:active {
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 15px 30px rgba(255, 215, 0, 0.5);
        }
        
        .btn-cta-secondary {
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
            gap: 12px;
            backdrop-filter: blur(10px);
            justify-content: center;
            text-align: center;
            flex: 1;
            min-width: 0;
            max-width: 100%;
            min-height: 56px;
        }
        
        .btn-cta-secondary:hover, .btn-cta-secondary:active {
            background: white;
            color: var(--dark-green);
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        /* Footer */
        .main-footer {
            background: var(--dark-gray);
            color: white;
            padding: 40px 0 20px;
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
            margin-bottom: 30px;
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
            margin-bottom: 15px;
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
            font-size: 0.9rem;
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
            font-size: 0.9rem;
        }
        
        .footer-links a:hover, .footer-links a:active {
            color: var(--primary-yellow);
            padding-left: 5px;
        }
        
        .contact-info li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            color: #bbb;
            font-size: 0.9rem;
        }
        
        .contact-info i {
            color: var(--primary-green);
            width: 18px;
            font-size: 1.1rem;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            color: #999;
            font-size: 0.85rem;
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
        
        .animate-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        /* Responsive Design */
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
            .services-hero {
                padding: 70px 0 35px;
            }
            
            .services-hero h1 {
                font-size: 1.8rem;
            }
            
            .services-hero p {
                font-size: 0.9rem;
            }
            
            .main-services,
            .features-section,
            .benefits-section,
            .services-cta {
                padding: 50px 0;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .section-subtitle {
                font-size: 0.9rem;
            }
            
            .service-grid {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .service-content {
                padding: 20px;
            }
            
            .service-content h3 {
                font-size: 1.2rem;
            }
            
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .benefits-card {
                padding: 20px;
            }
            
            .benefits-card h3 {
                font-size: 1.2rem;
            }
            
            .services-cta h2 {
                font-size: 1.6rem;
            }
            
            .services-cta p {
                font-size: 0.9rem;
            }
            
            .btn-cta-primary,
            .btn-cta-secondary {
                padding: 14px 25px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .services-hero h1 {
                font-size: 1.6rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .service-icon-wrapper {
                padding: 25px;
            }
            
            .service-icon {
                font-size: 2.5rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .benefit-item {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .benefit-text h5 {
                font-size: 0.95rem;
            }
            
            .benefit-text p {
                font-size: 0.8rem;
            }
            
            .services-cta h2 {
                font-size: 1.4rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn-cta-primary,
            .btn-cta-secondary {
                padding: 14px 20px;
                font-size: 0.9rem;
                width: 100%;
                max-width: 280px;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .logo-wrapper {
                width: 45px;
                height: 45px;
            }
            
            .mobile-menu-btn {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                margin-left: 5px;
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
                width: 40px;
                height: 40px;
            }
            
            .services-hero h1 {
                font-size: 1.4rem;
            }
            
            .service-content h3 {
                font-size: 1.1rem;
            }
            
            .services-cta h2 {
                font-size: 1.3rem;
            }
            
            .mobile-menu {
                width: 90%;
            }
        }
        
        @media (max-width: 360px) {
            .logo-wrapper {
                width: 38px;
                height: 38px;
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
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .feature-item {
                padding: 20px 15px;
            }
            
            .feature-item i {
                font-size: 1.8rem;
            }
        }
        
        /* Touch-friendly improvements */
        button,
        a.btn,
        .nav-link {
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        
        /* Better scrolling on iOS */
        .mobile-menu {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Prevent zoom on double tap for mobile */
        @media (max-width: 768px) {
            button:active, 
            a:active,
            .nav-link:active,
            .btn-cta-primary:active,
            .btn-cta-secondary:active,
            .social-link:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }
        }
        
        /* Swipe to close mobile menu */
        .mobile-menu {
            touch-action: pan-y;
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
<body>
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
                    <li class="nav-item"><a href="services.php" class="nav-link active"><i class="fas fa-concierge-bell"></i> Services</a></li>
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
            <li><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
            <li><a href="services.php" class="nav-link active"><i class="fas fa-concierge-bell"></i> Services</a></li>
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
        <!-- Services Hero Section -->
        <section class="services-hero">
            <div class="container">
                <div class="services-hero-content animate-up">
                    <h1>Our <span class="highlight">Comprehensive</span> Services</h1>
                    <p>
                        Discover how GOD'S WILLING INC transforms petroleum businesses in Liberia with 
                        cutting-edge technology solutions. From fuel management to financial reporting, 
                        we provide everything you need to succeed in today's competitive market.
                    </p>
                    <img class="public-hero-image" src="images/fuel-delivery.svg" alt="Fuel tanker delivering products to a service station" width="800" height="500" fetchpriority="high">
                </div>
            </div>
        </section>

        <!-- Main Services Section -->
        <section class="main-services" id="main-services">
            <div class="container">
                <div class="section-header animate-up">
                    <h2 class="section-title">Core Petroleum Solutions</h2>
                    <p class="section-subtitle">Designed specifically for Liberia's fuel industry needs</p>
                </div>
                
                <div class="service-grid">
                    <!-- Fuel Management -->
                    <div class="service-card animate-up" style="animation-delay: 0.1s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-gas-pump service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Fuel Management System</h3>
                            <p>
                                Advanced fuel inventory tracking, tank monitoring, and pump management 
                                systems designed for optimal operations and maximum efficiency.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Real-time inventory tracking</li>
                                <li><i class="fas fa-check"></i> Automatic low stock alerts</li>
                                <li><i class="fas fa-check"></i> Pump calibration management</li>
                                <li><i class="fas fa-check"></i> Tank level monitoring</li>
                                <li><i class="fas fa-check"></i> Automated reordering system</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Sales Management -->
                    <div class="service-card animate-up" style="animation-delay: 0.2s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-shopping-cart service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Sales Management</h3>
                            <p>
                                Streamlined sales processing, customer management, and receipt generation 
                                for smooth transactions and enhanced customer experience.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Quick sales processing</li>
                                <li><i class="fas fa-check"></i> Customer database management</li>
                                <li><i class="fas fa-check"></i> Digital receipts & invoices</li>
                                <li><i class="fas fa-check"></i> Multiple payment methods</li>
                                <li><i class="fas fa-check"></i> Customer loyalty programs</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Reporting & Analytics -->
                    <div class="service-card animate-up" style="animation-delay: 0.3s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-chart-pie service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Reporting & Analytics</h3>
                            <p>
                                Comprehensive reports and analytics to help you make informed business 
                                decisions and drive profitability.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Daily sales reports</li>
                                <li><i class="fas fa-check"></i> Financial statements</li>
                                <li><i class="fas fa-check"></i> Performance analytics</li>
                                <li><i class="fas fa-check"></i> Profit & loss analysis</li>
                                <li><i class="fas fa-check"></i> Trend forecasting</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Financial Management -->
                    <div class="service-card animate-up" style="animation-delay: 0.4s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-file-invoice-dollar service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Financial Management</h3>
                            <p>
                                Complete financial suite including expense tracking, invoicing, payment 
                                processing, and profit analysis.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Expense tracking & approval</li>
                                <li><i class="fas fa-check"></i> Automated invoicing</li>
                                <li><i class="fas fa-check"></i> Payment processing</li>
                                <li><i class="fas fa-check"></i> Tax calculation & reporting</li>
                                <li><i class="fas fa-check"></i> Budget management</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Multi-Branch Management -->
                    <div class="service-card animate-up" style="animation-delay: 0.5s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-building service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Multi-Branch Management</h3>
                            <p>
                                Centralized management of multiple fuel stations across Liberia with 
                                synchronized operations and unified reporting.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Centralized dashboard</li>
                                <li><i class="fas fa-check"></i> Branch performance comparison</li>
                                <li><i class="fas fa-check"></i> Inventory transfer management</li>
                                <li><i class="fas fa-check"></i> Unified reporting</li>
                                <li><i class="fas fa-check"></i> Consolidated financials</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Security & Compliance -->
                    <div class="service-card animate-up" style="animation-delay: 0.6s;">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-shield-alt service-icon"></i>
                        </div>
                        <div class="service-content">
                            <h3>Security & Compliance</h3>
                            <p>
                                Enterprise-grade security with data encryption, audit trails, access 
                                logs, and compliance with industry standards.
                            </p>
                            <ul class="service-features">
                                <li><i class="fas fa-check"></i> Data encryption</li>
                                <li><i class="fas fa-check"></i> Role-based access control</li>
                                <li><i class="fas fa-check"></i> Audit trails & logs</li>
                                <li><i class="fas fa-check"></i> Backup & recovery</li>
                                <li><i class="fas fa-check"></i> Regulatory compliance</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Additional Features Section -->
        <section class="features-section">
            <div class="container">
                <div class="section-header animate-up">
                    <h2 class="section-title">Additional Features</h2>
                    <p class="section-subtitle">Enhancing your petroleum business operations</p>
                </div>
                
                <div class="features-grid">
                    <div class="feature-item animate-up" style="animation-delay: 0.1s;">
                        <i class="fas fa-users text-primary"></i>
                        <h4>Multi-user System</h4>
                        <p>Role-based access for administrators, managers, attendants, and accountants</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.2s;">
                        <i class="fas fa-building text-success"></i>
                        <h4>Multi-branch Support</h4>
                        <p>Manage multiple locations from a single centralized dashboard</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.3s;">
                        <i class="fas fa-mobile-alt text-warning"></i>
                        <h4>Mobile Access</h4>
                        <p>Access your data from smartphones and tablets anywhere, anytime</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.4s;">
                        <i class="fas fa-shield-alt text-danger"></i>
                        <h4>Enterprise Security</h4>
                        <p>Advanced security features to protect your valuable business data</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.5s;">
                        <i class="fas fa-sync text-primary"></i>
                        <h4>Real-time Updates</h4>
                        <p>Live data synchronization across all devices and locations</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.6s;">
                        <i class="fas fa-cloud text-success"></i>
                        <h4>Cloud Storage</h4>
                        <p>Secure cloud storage with automatic backups and recovery</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.7s;">
                        <i class="fas fa-headset text-warning"></i>
                        <h4>24/7 Support</h4>
                        <p>Round-the-clock customer support and technical assistance</p>
                    </div>
                    
                    <div class="feature-item animate-up" style="animation-delay: 0.8s;">
                        <i class="fas fa-cogs text-danger"></i>
                        <h4>Customizable</h4>
                        <p>Tailored solutions to meet your specific business requirements</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Section -->
        <section class="benefits-section">
            <div class="benefits-container">
                <div class="section-header animate-up">
                    <h2 class="section-title">Why Choose Our Services?</h2>
                    <p class="section-subtitle">Transform your petroleum business with proven results</p>
                </div>
                
                <div class="benefits-row">
                    <div class="benefits-card animate-up" style="animation-delay: 0.1s;">
                        <h3>Key Benefits</h3>
                        <div class="benefits-grid">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="benefit-text">
                                    <h5>Time Saving</h5>
                                    <p>Automate manual processes and reduce administrative work by up to 70%</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="benefit-text">
                                    <h5>Increased Revenue</h5>
                                    <p>Better inventory management leads to 30% higher profitability</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-bug"></i>
                                </div>
                                <div class="benefit-text">
                                    <h5>Error Reduction</h5>
                                    <p>Minimize human errors in calculations and record keeping</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="benefit-text">
                                    <h5>Transparency</h5>
                                    <p>Complete transaction tracking and audit trails for accountability</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="benefits-card animate-up" style="animation-delay: 0.2s;">
                        <h3>Why GOD'S WILLING INC?</h3>
                        <ul class="why-us-list">
                            <li>
                                <i class="fas fa-star"></i>
                                <div class="why-us-text">
                                    <strong>Proven Experience:</strong>
                                    <p>10+ years of petroleum management expertise in Liberia</p>
                                </div>
                            </li>
                            
                            <li>
                                <i class="fas fa-star"></i>
                                <div class="why-us-text">
                                    <strong>24/7 Support:</strong>
                                    <p>Round-the-clock customer support and technical assistance</p>
                                </div>
                            </li>
                            
                            <li>
                                <i class="fas fa-star"></i>
                                <div class="why-us-text">
                                    <strong>Customizable Solutions:</strong>
                                    <p>Tailored to your specific business needs and requirements</p>
                                </div>
                            </li>
                            
                            <li>
                                <i class="fas fa-star"></i>
                                <div class="why-us-text">
                                    <strong>Cost-effective:</strong>
                                    <p>Affordable pricing plans with high return on investment</p>
                                </div>
                            </li>
                            
                            <li>
                                <i class="fas fa-star"></i>
                                <div class="why-us-text">
                                    <strong>User-friendly:</strong>
                                    <p>Intuitive interface requiring minimal training</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="services-cta">
            <div class="container">
                <div class="services-cta-content animate-up">
                    <h2>Ready to Transform Your Petroleum Business?</h2>
                    <p>
                        Join dozens of successful petroleum businesses in Liberia that have increased efficiency, 
                        reduced costs, and boosted profits with our comprehensive management system.
                    </p>
                    
                    <div class="cta-buttons">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="btn-cta-primary">
                                <i class="fas fa-rocket"></i> Get Started Now
                            </a>
                            <a href="contact.php" class="btn-cta-secondary">
                                <i class="fas fa-envelope"></i> Contact Our Team
                            </a>
                        <?php else: ?>
                            <a href="../<?php echo $_SESSION['user_role']; ?>/dashboard.php" class="btn-cta-primary">
                                <i class="fas fa-tachometer-alt"></i> Access Your Dashboard
                            </a>
                            <a href="contact.php" class="btn-cta-secondary">
                                <i class="fas fa-headset"></i> Get Support
                            </a>
                        <?php endif; ?>
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
            }
            
            setVH();
            window.addEventListener('resize', setVH);
            window.addEventListener('orientationchange', setVH);
            
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
            document.querySelectorAll('.service-card, .feature-item, .benefits-card, .animate-up').forEach(el => {
                if (!el.classList.contains('services-hero-content') && !el.classList.contains('services-cta-content')) {
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
            
            // Handle device orientation changes
            window.addEventListener('orientationchange', function() {
                // Close mobile menu on orientation change
                if (isMobileMenuOpen) {
                    closeMobileMenu();
                }
                
                // Recalculate layout
                setTimeout(() => {
                    setVH();
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
            
            // Service card hover effects
            const serviceCards = document.querySelectorAll('.service-card');
            serviceCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    const icon = card.querySelector('.service-icon');
                    icon.style.transform = 'rotateY(360deg) scale(1.1)';
                });
                
                card.addEventListener('mouseleave', () => {
                    const icon = card.querySelector('.service-icon');
                    icon.style.transform = 'rotateY(0) scale(1)';
                });
            });
            
            // Benefit icons animation
            const benefitIcons = document.querySelectorAll('.benefit-icon');
            benefitIcons.forEach(icon => {
                icon.addEventListener('mouseenter', () => {
                    icon.style.transform = 'rotate(10deg) scale(1.1)';
                });
                
                icon.addEventListener('mouseleave', () => {
                    icon.style.transform = 'rotate(0) scale(1)';
                });
            });
            
            // Feature items animation
            const featureItems = document.querySelectorAll('.feature-item');
            featureItems.forEach(item => {
                item.addEventListener('mouseenter', () => {
                    const icon = item.querySelector('i');
                    icon.style.transform = 'scale(1.2)';
                });
                
                item.addEventListener('mouseleave', () => {
                    const icon = item.querySelector('i');
                    icon.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>