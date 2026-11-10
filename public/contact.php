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
$company_address = $settings['company_address'] ?? "Broad Street, Monrovia, Liberia";
$company_phone = $settings['company_phone'] ?? "+231 880 123 456";
$company_email = $settings['company_email'] ?? "info@godswillinginc.com";

// Create contact_messages table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    newsletter TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'read', 'replied', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$conn->query($create_table_sql);

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_contact'])) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $subject = $conn->real_escape_string(trim($_POST['subject']));
    $message = $conn->real_escape_string(trim($_POST['message']));
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Please enter your name";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($subject)) {
        $errors[] = "Please select a subject";
    }
    
    if (empty($message)) {
        $errors[] = "Please enter your message";
    }
    
    if (empty($errors)) {
        // Save contact message to database
        $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, newsletter, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $email, $phone, $subject, $message, $newsletter);
        
        if ($stmt->execute()) {
            $success = "Thank you for contacting GOD'S WILLING INC! Our team will respond within 24 business hours.";
            
            // Send email notification (in production)
            // $to = "contact@godswillinginc.com";
            // $email_subject = "New Contact Form: " . $subject;
            // $email_body = "Name: $name\nEmail: $email\nPhone: $phone\nSubject: $subject\nMessage: $message";
            // mail($to, $email_subject, $email_body);
            
            // Clear form
            $_POST = array();
        } else {
            $error = "There was an error sending your message. Please try again or call us directly.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Contact Us - <?php echo $company_name; ?> | Petroleum Management Experts in Liberia</title>
    
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
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
            min-height: 100vh;
            min-height: calc(var(--vh, 1vh) * 100);
            color: var(--dark-gray);
            line-height: 1.6;
            position: relative;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%2327AE60' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.5;
            z-index: -1;
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
        
        /* Contact Header - Adjusted for mobile */
        .contact-header {
            background: linear-gradient(135deg, rgba(18, 91, 53, 0.94) 0%, rgba(39, 174, 96, 0.88) 100%), 
                        url('images/operations.svg');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            padding: 140px 0 80px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: -70px;
            padding-top: 210px;
        }
        
        @media (max-width: 768px) {
            .contact-header {
                padding-top: 180px;
                padding-bottom: 50px;
                min-height: auto;
                display: flex;
                align-items: center;
            }
        }
        
        .contact-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23FFD700' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        
        .contact-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 3.8rem;
            margin-bottom: 20px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }
        
        .contact-title .highlight {
            color: var(--primary-yellow);
            position: relative;
            display: inline-block;
        }
        
        .contact-title .highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 8px;
            background: rgba(255, 215, 0, 0.3);
            z-index: -1;
            border-radius: 4px;
        }
        
        .contact-subtitle {
            font-size: 1.4rem;
            max-width: 800px;
            margin: 0 auto 30px;
            opacity: 0.95;
            line-height: 1.7;
            position: relative;
            z-index: 2;
        }
        
        .contact-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 50px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        
        .contact-stat {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-width: 200px;
            transition: all 0.4s ease;
        }
        
        .contact-stat:hover, .contact-stat:active {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .contact-stat-number {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            color: var(--primary-yellow);
            display: block;
            line-height: 1;
        }
        
        .contact-stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 10px;
            font-weight: 600;
        }
        
        /* Contact Content */
        .contact-content {
            padding: 0 0 100px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Alert Messages */
        .alert-container {
            max-width: 800px;
            margin: 0 auto 40px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: none;
            border-left: 5px solid var(--primary-green);
            color: var(--dark-green);
            border-radius: 15px;
            padding: 25px;
            font-weight: 600;
            box-shadow: var(--shadow);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: none;
            border-left: 5px solid #dc3545;
            color: #721c24;
            border-radius: 15px;
            padding: 25px;
            font-weight: 600;
            box-shadow: var(--shadow);
        }
        
        /* Contact Cards */
        .contact-info-section {
            margin-bottom: 60px;
        }
        
        .contact-card {
            background: white;
            border-radius: 25px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: none;
            transition: all 0.4s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .contact-card:hover, .contact-card:active {
            transform: translateY(-15px);
            border-color: var(--primary-yellow);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .contact-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            font-size: 2rem;
            color: var(--dark-green);
            transition: all 0.4s ease;
        }
        
        .contact-card:hover .contact-icon, .contact-card:active .contact-icon {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--primary-green) 100%);
            color: white;
            transform: rotateY(360deg);
        }
        
        .contact-card h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 20px;
            font-size: 1.6rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        .contact-card h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--primary-yellow);
            border-radius: 2px;
        }
        
        .contact-info {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .contact-info li {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 25px;
            color: var(--medium-gray);
            font-size: 1.05rem;
        }
        
        .contact-info li:last-child {
            margin-bottom: 0;
        }
        
        .contact-info i {
            color: var(--primary-green);
            font-size: 1.3rem;
            margin-top: 3px;
            flex-shrink: 0;
            width: 25px;
        }
        
        .contact-info strong {
            color: var(--dark-gray);
            display: block;
            margin-bottom: 5px;
        }
        
        /* Form Section */
        .form-section {
            margin-bottom: 60px;
        }
        
        .form-card {
            background: white;
            border-radius: 25px;
            padding: 50px;
            box-shadow: var(--shadow);
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
        }
        
        .form-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 40px;
            font-size: 2.2rem;
            position: relative;
            padding-bottom: 20px;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 5px;
            background: linear-gradient(to right, var(--primary-yellow), var(--primary-green));
            border-radius: 3px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 10px;
            font-size: 1.05rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: auto;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.3rem rgba(39, 174, 96, 0.15);
            background: white;
        }
        
        .form-control::placeholder {
            color: #999;
        }
        
        .form-text {
            color: #666;
            font-size: 0.95rem;
            margin-top: 5px;
        }
        
        .form-check {
            margin-bottom: 20px;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 0.3em;
            margin-right: 10px;
            border: 2px solid #ddd;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .form-check-input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
        }
        
        .form-check-label {
            color: var(--medium-gray);
            font-size: 1.05rem;
            line-height: 1.5;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--accent-yellow) 100%);
            color: var(--dark-gray);
            border: none;
            padding: 18px 50px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.3);
            min-height: 60px;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-submit:hover::before, .btn-submit:active::before {
            left: 100%;
        }
        
        .btn-submit:hover, .btn-submit:active {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(255, 215, 0, 0.4);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        /* Map Section */
        .map-section {
            margin-bottom: 60px;
        }
        
        .map-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: none;
        }
        
        .map-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .map-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
            animation: rotate 20s linear infinite;
        }
        
        .map-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            font-size: 1.8rem;
        }
        
        .map-subtitle {
            opacity: 0.9;
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
        }
        
        .map-container {
            height: 450px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .map-placeholder {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }
        
        .map-icon {
            font-size: 5rem;
            color: var(--primary-green);
            margin-bottom: 25px;
            animation: pulse 2s infinite;
        }
        
        .map-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .map-badge {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            color: var(--dark-gray);
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .map-badge:hover, .map-badge:active {
            border-color: var(--primary-yellow);
            transform: translateY(-3px);
        }
        
        /* FAQ Section */
        .faq-section {
            margin-bottom: 40px;
        }
        
        .faq-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: none;
        }
        
        .faq-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-green) 100%);
            padding: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .faq-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
            animation: rotate 20s linear infinite;
        }
        
        .faq-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            font-size: 1.8rem;
        }
        
        .faq-subtitle {
            opacity: 0.9;
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
        }
        
        .accordion {
            --bs-accordion-border-color: #e0e0e0;
            --bs-accordion-border-width: 0;
            --bs-accordion-border-radius: 0;
            --bs-accordion-inner-border-radius: 0;
            --bs-accordion-btn-padding-x: 30px;
            --bs-accordion-btn-padding-y: 25px;
            --bs-accordion-body-padding-x: 30px;
            --bs-accordion-body-padding-y: 25px;
        }
        
        .accordion-item {
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .accordion-item:last-child {
            border-bottom: none;
        }
        
        .accordion-item:hover {
            background: #f0f0f0;
        }
        
        .accordion-button {
            background: transparent;
            color: var(--dark-gray);
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: none;
            transition: all 0.3s ease;
            min-height: 60px;
            display: flex;
            align-items: center;
        }
        
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, var(--light-yellow) 0%, var(--light-green) 100%);
            color: var(--dark-green);
            box-shadow: none;
        }
        
        .accordion-button:focus {
            border-color: var(--primary-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }
        
        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%232C3E50'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            transition: transform 0.3s ease;
        }
        
        .accordion-button:not(.collapsed)::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231E8449'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }
        
        .accordion-body {
            color: var(--medium-gray);
            line-height: 1.8;
            font-size: 1.05rem;
            background: white;
        }
        
        /* Footer */
        .main-footer {
            background: var(--dark-gray);
            color: white;
            padding: 80px 0 30px;
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 50px;
            margin-bottom: 60px;
        }
        
        .footer-col h4 {
            font-family: 'Raleway', sans-serif;
            font-weight: 800;
            color: var(--primary-yellow);
            margin-bottom: 30px;
            font-size: 1.4rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        .footer-col h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-green);
            border-radius: 2px;
        }
        
        .footer-logo {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2.2rem;
            color: white;
            margin-bottom: 25px;
            line-height: 1.1;
        }
        
        .footer-logo span {
            color: var(--primary-yellow);
            position: relative;
        }
        
        .footer-col p {
            color: #bbb;
            line-height: 1.8;
            margin-bottom: 30px;
            font-size: 1.05rem;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .social-link:hover, .social-link:active {
            background: var(--primary-yellow);
            color: var(--dark-gray);
            transform: translateY(-5px) rotate(5deg);
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 15px;
        }
        
        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05rem;
        }
        
        .footer-links a:hover, .footer-links a:active {
            color: var(--primary-yellow);
            padding-left: 8px;
        }
        
        .contact-info li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #bbb;
            font-size: 1.05rem;
        }
        
        .contact-info i {
            color: var(--primary-green);
            width: 20px;
            font-size: 1.2rem;
        }
        
        .copyright {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            color: #999;
            font-size: 0.95rem;
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
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
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
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        .animate-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        /* Mobile Menu Toggle Animation */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
            }
            to {
                transform: translateX(100%);
            }
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
            
            .contact-header {
                padding: 120px 0 60px;
                padding-top: 180px;
            }
            
            .contact-title {
                font-size: 3.2rem;
            }
            
            .contact-subtitle {
                font-size: 1.2rem;
            }
            
            .contact-stat {
                min-width: 180px;
                padding: 20px 25px;
            }
            
            .contact-stat-number {
                font-size: 2.5rem;
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
            
            .contact-card {
                padding: 30px;
                margin-bottom: 30px;
            }
            
            .form-card {
                padding: 40px;
            }
            
            .form-title {
                font-size: 1.9rem;
            }
            
            .map-container {
                height: 400px;
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
            
            .contact-header {
                padding: 110px 0 50px;
                padding-top: 160px;
            }
            
            .contact-title {
                font-size: 2.3rem;
            }
            
            .contact-subtitle {
                font-size: 1.1rem;
                padding: 0 15px;
            }
            
            .contact-stats {
                gap: 20px;
                margin-top: 40px;
            }
            
            .contact-stat {
                min-width: calc(50% - 10px);
                padding: 15px;
            }
            
            .contact-stat-number {
                font-size: 2rem;
            }
            
            .contact-content {
                padding: 0 0 60px;
            }
            
            .contact-card {
                padding: 25px;
            }
            
            .contact-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
                margin-bottom: 25px;
            }
            
            .contact-card h4 {
                font-size: 1.4rem;
            }
            
            .form-card {
                padding: 30px;
            }
            
            .form-title {
                font-size: 1.7rem;
                margin-bottom: 30px;
            }
            
            .form-control, .form-select {
                padding: 12px 15px;
                font-size: 1rem;
            }
            
            .btn-submit {
                padding: 16px 40px;
                font-size: 1.1rem;
            }
            
            .map-container {
                height: 350px;
            }
            
            .map-icon {
                font-size: 4rem;
            }
            
            .map-badges {
                flex-direction: column;
                align-items: center;
            }
            
            .accordion-button {
                padding: 20px;
                font-size: 1rem;
            }
            
            .accordion-body {
                padding: 20px;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
        
        @media (max-width: 576px) {
            .contact-header {
                padding: 100px 0 40px;
                padding-top: 150px;
            }
            
            .contact-title {
                font-size: 2rem;
            }
            
            .contact-subtitle {
                font-size: 1rem;
            }
            
            .contact-stats {
                gap: 15px;
            }
            
            .contact-stat {
                min-width: calc(50% - 8px);
                padding: 12px;
            }
            
            .contact-stat-number {
                font-size: 1.8rem;
            }
            
            .contact-stat-label {
                font-size: 0.9rem;
            }
            
            .contact-card {
                padding: 20px;
            }
            
            .contact-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
                margin-bottom: 20px;
            }
            
            .contact-card h4 {
                font-size: 1.3rem;
            }
            
            .contact-info li {
                font-size: 1rem;
            }
            
            .form-card {
                padding: 25px;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
            
            .btn-submit {
                padding: 14px 30px;
                font-size: 1rem;
            }
            
            .map-container {
                height: 300px;
            }
            
            .map-icon {
                font-size: 3.5rem;
            }
            
            .map-badge {
                padding: 8px 15px;
                font-size: 0.9rem;
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
            
            .contact-title {
                font-size: 1.8rem;
            }
            
            .contact-stat {
                min-width: calc(50% - 5px);
                padding: 10px;
            }
            
            .contact-stat-number {
                font-size: 1.6rem;
            }
            
            .form-card {
                padding: 20px;
            }
            
            .btn-submit {
                padding: 12px 25px;
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
            
            .contact-stats {
                flex-wrap: nowrap;
                overflow-x: auto;
                justify-content: flex-start;
                padding-bottom: 10px;
                margin: 30px -15px;
                scrollbar-width: none;
            }
            
            .contact-stats::-webkit-scrollbar {
                display: none;
            }
            
            .contact-stat {
                flex-shrink: 0;
                width: 140px;
            }
            
            .contact-content {
                padding: 0 0 50px;
            }
        }
        
        /* Specific adjustments for 720x1612 mobile */
        @media (max-height: 1612px) and (max-width: 720px) {
            .contact-header {
                padding: 100px 0 40px;
                padding-top: 150px;
            }
            
            .contact-title {
                font-size: 2.2rem;
                margin-bottom: 15px;
            }
            
            .contact-subtitle {
                font-size: 1.1rem;
                margin-bottom: 25px;
            }
            
            .contact-stats {
                margin-top: 30px;
            }
            
            .contact-content {
                padding: 0 0 50px;
            }
            
            .contact-info-section {
                margin-bottom: 40px;
            }
            
            .form-section {
                margin-bottom: 40px;
            }
            
            .map-section {
                margin-bottom: 40px;
            }
            
            .faq-section {
                margin-bottom: 30px;
            }
        }
        
        /* Fix for mobile overflow */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
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
            .btn-submit,
            .social-link,
            .footer-links a,
            .accordion-button {
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
            .btn-submit:active,
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
                    <li class="nav-item"><a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a></li>
                    <li class="nav-item"><a href="services.php" class="nav-link"><i class="fas fa-concierge-bell"></i> Services</a></li>
                    <li class="nav-item"><a href="prices.php" class="nav-link"><i class="fas fa-dollar-sign"></i> Prices</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link active"><i class="fas fa-envelope"></i> Contact</a></li>
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
            <li><a href="prices.php" class="nav-link"><i class="fas fa-dollar-sign"></i> Prices</a></li>
            <li><a href="contact.php" class="nav-link active"><i class="fas fa-envelope"></i> Contact</a></li>
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
        <!-- Contact Header -->
        <section class="contact-header">
            <div class="container">
                <h1 class="contact-title animate-up">
                    Contact <span class="highlight">GOD'S WILLING INC</span>
                </h1>
                <p class="contact-subtitle animate-up" style="animation-delay: 0.2s;">
                    Reach out to Liberia's leading petroleum management experts. Whether you need support, 
                    want to schedule a demo, or have questions about our services, we're here to help 
                    transform your fuel business.
                </p>
                <img class="public-hero-image" src="images/operations.svg" alt="Illustration of the people and tools supporting petroleum operations" width="800" height="600" fetchpriority="high">
                
                <div class="contact-stats">
                    <div class="contact-stat floating" style="animation-delay: 0s;">
                        <span class="contact-stat-number">24/7</span>
                        <span class="contact-stat-label">Support Available</span>
                    </div>
                    <div class="contact-stat floating" style="animation-delay: 0.2s;">
                        <span class="contact-stat-number">2h</span>
                        <span class="contact-stat-label">Avg. Response Time</span>
                    </div>
                    <div class="contact-stat floating" style="animation-delay: 0.4s;">
                        <span class="contact-stat-number">100%</span>
                        <span class="contact-stat-label">Customer Satisfaction</span>
                    </div>
                    <div class="contact-stat floating" style="animation-delay: 0.6s;">
                        <span class="contact-stat-number">10+</span>
                        <span class="contact-stat-label">Years Experience</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Content -->
        <section class="contact-content">
            <div class="container">
                <!-- Alerts -->
                <div class="alert-container">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success animate-up">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger animate-up">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contact Information Cards -->
                <div class="contact-info-section">
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="contact-card animate-up" style="animation-delay: 0.1s;">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h4>Our Location</h4>
                                <ul class="contact-info">
                                    <li>
                                        <i class="fas fa-building"></i>
                                        <div>
                                            <strong>Headquarters</strong><br>
                                            <?php echo htmlspecialchars($company_address); ?><br>
                                            Monrovia, Liberia
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <strong>Visit Us</strong><br>
                                            Mon-Fri: 7:00 AM - 8:00 PM<br>
                                            Saturday: 8:00 AM - 6:00 PM
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="contact-card animate-up" style="animation-delay: 0.2s;">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h4>Call Us</h4>
                                <ul class="contact-info">
                                    <li>
                                        <i class="fas fa-phone-volume"></i>
                                        <div>
                                            <strong>Main Office</strong><br>
                                            <?php echo htmlspecialchars($company_phone); ?>
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-headset"></i>
                                        <div>
                                            <strong>Customer Support</strong><br>
                                            +231 770 987 654
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>
                                            <strong>Emergency (24/7)</strong><br>
                                            +231 886 543 210
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="contact-card animate-up" style="animation-delay: 0.3s;">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4>Email Us</h4>
                                <ul class="contact-info">
                                    <li>
                                        <i class="fas fa-envelope-open-text"></i>
                                        <div>
                                            <strong>General Inquiries</strong><br>
                                            <?php echo htmlspecialchars($company_email); ?>
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-bullhorn"></i>
                                        <div>
                                            <strong>Sales Department</strong><br>
                                            sales@godswillinginc.com
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-life-ring"></i>
                                        <div>
                                            <strong>Technical Support</strong><br>
                                            support@godswillinginc.com
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="contact-card animate-up" style="animation-delay: 0.4s;">
                                <div class="contact-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4>Leadership</h4>
                                <ul class="contact-info">
                                    <li>
                                        <i class="fas fa-user-tie"></i>
                                        <div>
                                            <strong>Mr. Karnue</strong><br>
                                            Chief Executive Officer<br>
                                            ceo@godswillinginc.com
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-user-tie"></i>
                                        <div>
                                            <strong>Mrs. Mamie Karnue</strong><br>
                                            Chief Operations Officer<br>
                                            coo@godswillinginc.com
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="form-section">
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="form-card animate-up" style="animation-delay: 0.5s;">
                                <h3 class="form-title">Send Us a Message</h3>
                                <form method="POST" action="" id="contactForm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" required 
                                                   placeholder="Enter your full name"
                                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                            <div class="form-text">We prefer to address you properly</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" required 
                                                   placeholder="your.email@example.com"
                                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                            <div class="form-text">We'll send our response to this address</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="+231 XXX XXX XXX"
                                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                            <div class="form-text">Include country code for international calls</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="subject" class="form-label">Subject *</label>
                                            <select class="form-select" id="subject" name="subject" required>
                                                <option value="">Select a subject</option>
                                                <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                                <option value="Sales Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Sales Inquiry') ? 'selected' : ''; ?>>Sales & Pricing</option>
                                                <option value="Technical Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                                <option value="Business Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Business Partnership') ? 'selected' : ''; ?>>Business Partnership</option>
                                                <option value="Feedback" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feedback') ? 'selected' : ''; ?>>Feedback & Suggestions</option>
                                                <option value="Career Opportunity" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Career Opportunity') ? 'selected' : ''; ?>>Career Opportunities</option>
                                                <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                            <div class="form-text">Choose the most relevant category</div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="message" class="form-label">Your Message *</label>
                                            <textarea class="form-control" id="message" name="message" rows="5" required 
                                                      placeholder="Tell us about your petroleum management needs, questions, or how we can help your business in Liberia..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                            <div class="form-text">Please provide as much detail as possible</div>
                                            <div id="charCount" class="form-text text-end">0/2000 characters</div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" 
                                                       <?php echo (isset($_POST['newsletter']) && $_POST['newsletter'] == '1') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="newsletter">
                                                    Yes, I want to receive updates about petroleum industry news, fuel price changes, 
                                                    special offers, and tips for managing my fuel station in Liberia.
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" name="submit_contact" class="btn-submit" id="submitBtn">
                                                <i class="fas fa-paper-plane"></i> Send Message
                                            </button>
                                        </div>
                                        
                                        <div class="col-12">
                                            <p class="text-center text-muted mt-3 small">
                                                <i class="fas fa-shield-alt"></i> Your information is secure with us. 
                                                We respect your privacy and will never share your details with third parties.
                                            </p>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Map Section -->
                <div class="map-section">
                    <div class="row">
                        <div class="col-lg-10 mx-auto">
                            <div class="map-card animate-up" style="animation-delay: 0.6s;">
                                <div class="map-header">
                                    <h3 class="map-title">Visit Our Headquarters</h3>
                                    <p class="map-subtitle">Located in the heart of Monrovia for your convenience</p>
                                </div>
                                <div class="map-container">
                                    <div class="map-placeholder">
                                        <i class="fas fa-map-marked-alt map-icon"></i>
                                        <h4 class="mb-3">GOD'S WILLING INC Headquarters</h4>
                                        <p class="lead mb-4"><?php echo htmlspecialchars($company_address); ?></p>
                                        <p class="text-muted mb-4">
                                            Our centrally located office in Monrovia is easily accessible from all parts of the city. 
                                            We welcome scheduled visits for consultations, demonstrations, and meetings.
                                        </p>
                                        <div class="map-badges">
                                            <div class="map-badge">
                                                <i class="fas fa-car"></i> Ample Parking Available
                                            </div>
                                            <div class="map-badge">
                                                <i class="fas fa-wheelchair"></i> Wheelchair Accessible
                                            </div>
                                            <div class="map-badge">
                                                <i class="fas fa-wifi"></i> Free Wi-Fi
                                            </div>
                                            <div class="map-badge">
                                                <i class="fas fa-coffee"></i> Refreshments Available
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Section -->
                <div class="faq-section">
                    <div class="row">
                        <div class="col-lg-10 mx-auto">
                            <div class="faq-card animate-up" style="animation-delay: 0.7s;">
                                <div class="faq-header">
                                    <h3 class="faq-title">Frequently Asked Questions</h3>
                                    <p class="faq-subtitle">Quick answers to common questions about contacting us</p>
                                </div>
                                <div class="accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                                How quickly will I receive a response?
                                            </button>
                                        </h2>
                                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                We pride ourselves on quick responses! Typically, you'll hear back from our team 
                                                within <strong>2-4 hours</strong> during business days (Monday-Friday, 7 AM - 8 PM). 
                                                For urgent matters, please call our emergency line for immediate assistance. 
                                                Weekends may have slightly longer response times, but we monitor messages regularly.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                                Do you serve petroleum businesses outside Monrovia?
                                            </button>
                                        </h2>
                                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Absolutely! GOD'S WILLING INC serves petroleum businesses across <strong>all of Liberia</strong>. 
                                                We have established service networks in:
                                                <ul class="mt-2 mb-2">
                                                    <li><strong>Paynesville</strong> - ELWA Road Service Center</li>
                                                    <li><strong>Kakata</strong> - Margibi County Regional Office</li>
                                                    <li><strong>Gbarnga</strong> - Bong County Partnership</li>
                                                    <li><strong>Buchanan</strong> - Grand Bassa Coastal Services</li>
                                                    <li><strong>Harper</strong> - Maryland County Operations</li>
                                                </ul>
                                                Contact us to discuss your specific location and we'll connect you with the nearest service team.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                                What information should I include in my inquiry?
                                            </button>
                                        </h2>
                                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                To help us serve you better, please include:
                                                <ul class="mt-2 mb-2">
                                                    <li>Your name and contact information</li>
                                                    <li>The name and location of your fuel station/business</li>
                                                    <li>Specific challenges or needs you're facing</li>
                                                    <li>Your current fuel management system (if any)</li>
                                                    <li>Number of pumps and average daily sales volume</li>
                                                    <li>Any particular features you're interested in</li>
                                                </ul>
                                                The more details you provide, the better we can tailor our response to your specific needs.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                                Can I schedule an in-person demonstration?
                                            </button>
                                        </h2>
                                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes! We highly recommend in-person demonstrations to see our system in action. 
                                                You can:
                                                <ul class="mt-2 mb-2">
                                                    <li><strong>Visit our Monrovia office</strong> - Schedule an appointment for a full demo</li>
                                                    <li><strong>Request an on-site visit</strong> - Our team can visit your location</li>
                                                    <li><strong>Join a group workshop</strong> - Monthly training sessions for new clients</li>
                                                </ul>
                                                Please mention your preference when contacting us, and we'll arrange the best option for you. 
                                                We recommend bringing key management staff to the demonstration.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                                What support do you provide after implementation?
                                            </button>
                                        </h2>
                                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Our support doesn't end with implementation. We provide:
                                                <ul class="mt-2 mb-2">
                                                    <li><strong>24/7 Technical Support</strong> - Phone, email, and remote assistance</li>
                                                    <li><strong>On-site Training</strong> - Comprehensive staff training programs</li>
                                                    <li><strong>Regular Updates</strong> - System updates and new features</li>
                                                    <li><strong>Quarterly Reviews</strong> - Performance analysis and optimization</li>
                                                    <li><strong>Emergency Response</strong> - Priority support for critical issues</li>
                                                    <li><strong>Liberia-specific Support</strong> - Local market insights and compliance assistance</li>
                                                </ul>
                                                We're committed to your long-term success in Liberia's petroleum market.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                                How do I become a business partner?
                                            </button>
                                        </h2>
                                        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                We welcome partnerships with:
                                                <ul class="mt-2 mb-2">
                                                    <li><strong>Fuel Station Owners</strong> - Implement our management system</li>
                                                    <li><strong>Petroleum Distributors</strong> - Integration partnerships</li>
                                                    <li><strong>Technology Providers</strong> - Co-development opportunities</li>
                                                    <li><strong>Service Providers</strong> - Maintenance and support networks</li>
                                                </ul>
                                                To discuss partnership opportunities:
                                                <ol class="mt-2 mb-0">
                                                    <li>Contact our Business Development team</li>
                                                    <li>Schedule a meeting with Mr. Karnue or Mrs. Mamie Karnue</li>
                                                    <li>Present your partnership proposal</li>
                                                    <li>Review mutual benefits and terms</li>
                                                </ol>
                                                We believe in building strong, mutually beneficial relationships that contribute to Liberia's petroleum sector growth.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company_address); ?></li>
                                <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($company_phone); ?></li>
                                <li><i class="fas fa-phone"></i> +231 770 987 654</li>
                                <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($company_email); ?></li>
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
            
            // Form validation and enhancement
            const contactForm = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            const messageTextarea = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            
            // Character counter for message
            if (messageTextarea && charCount) {
                messageTextarea.addEventListener('input', function() {
                    const length = this.value.length;
                    charCount.textContent = `${length}/2000 characters`;
                    
                    if (length > 2000) {
                        charCount.style.color = '#dc3545';
                        charCount.innerHTML = `${length}/2000 characters <i class="fas fa-exclamation-triangle"></i>`;
                    } else if (length > 1800) {
                        charCount.style.color = '#ffc107';
                    } else {
                        charCount.style.color = '#6c757d';
                    }
                });
                
                // Initialize character count
                messageTextarea.dispatchEvent(new Event('input'));
            }
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 0) {
                        // Format for Liberia: +231 XXX XXX XXX
                        if (!value.startsWith('231')) {
                            value = '231' + value;
                        }
                        
                        if (value.length > 3) {
                            value = value.substring(0, 3) + ' ' + value.substring(3);
                        }
                        if (value.length > 7) {
                            value = value.substring(0, 7) + ' ' + value.substring(7);
                        }
                        if (value.length > 11) {
                            value = value.substring(0, 11) + ' ' + value.substring(11);
                        }
                        
                        if (!value.startsWith('+')) {
                            value = '+' + value;
                        }
                    }
                    
                    e.target.value = value.substring(0, 16); // Limit length
                });
            }
            
            // Form submission handling
            if (contactForm && submitBtn) {
                contactForm.addEventListener('submit', function(e) {
                    // Basic validation
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const subject = document.getElementById('subject').value;
                    const message = document.getElementById('message').value.trim();
                    
                    let errors = [];
                    
                    if (!name) {
                        errors.push('Please enter your full name');
                        document.getElementById('name').classList.add('is-invalid');
                    } else {
                        document.getElementById('name').classList.remove('is-invalid');
                    }
                    
                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        errors.push('Please enter a valid email address');
                        document.getElementById('email').classList.add('is-invalid');
                    } else {
                        document.getElementById('email').classList.remove('is-invalid');
                    }
                    
                    if (!subject) {
                        errors.push('Please select a subject');
                        document.getElementById('subject').classList.add('is-invalid');
                    } else {
                        document.getElementById('subject').classList.remove('is-invalid');
                    }
                    
                    if (!message) {
                        errors.push('Please enter your message');
                        document.getElementById('message').classList.add('is-invalid');
                    } else if (message.length > 2000) {
                        errors.push('Message must be 2000 characters or less');
                        document.getElementById('message').classList.add('is-invalid');
                    } else {
                        document.getElementById('message').classList.remove('is-invalid');
                    }
                    
                    if (errors.length > 0) {
                        e.preventDefault();
                        
                        // Show error alert
                        let errorAlert = document.createElement('div');
                        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                        errorAlert.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                ${errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        const alertContainer = document.querySelector('.alert-container');
                        if (alertContainer) {
                            alertContainer.prepend(errorAlert);
                            
                            // Auto-dismiss after 10 seconds
                            setTimeout(() => {
                                if (errorAlert.parentNode) {
                                    const bsAlert = new bootstrap.Alert(errorAlert);
                                    bsAlert.close();
                                }
                            }, 10000);
                        }
                        
                        // Scroll to first error
                        const firstError = document.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                            firstError.focus();
                        }
                        
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Message...';
                    submitBtn.disabled = true;
                    
                    // Allow form to submit
                    return true;
                });
            }
            
            // Clear error styling on input
            const formInputs = document.querySelectorAll('#contactForm input, #contactForm select, #contactForm textarea');
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
                
                input.addEventListener('change', function() {
                    this.classList.remove('is-invalid');
                });
            });
            
            // Scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -100px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = entry.target.dataset.delay || '0s';
                        entry.target.classList.add('animate-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe elements for animation
            document.querySelectorAll('.contact-card, .form-card, .map-card, .faq-card').forEach(el => {
                observer.observe(el);
            });
            
            // Smooth scrolling for FAQ accordion
            document.querySelectorAll('.accordion-button').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-bs-target');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement && window.innerWidth < 992) {
                        setTimeout(() => {
                            targetElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }, 300);
                    }
                });
            });
            
            // Auto-focus on first input if there are errors
            const firstInvalid = document.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
            
            // Add input focus effects
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
            
            // Pre-fill subject from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const subjectParam = urlParams.get('subject');
            if (subjectParam && document.getElementById('subject')) {
                const subjectSelect = document.getElementById('subject');
                const optionToSelect = Array.from(subjectSelect.options).find(option => 
                    option.value.toLowerCase().includes(subjectParam.toLowerCase()) || 
                    option.text.toLowerCase().includes(subjectParam.toLowerCase())
                );
                
                if (optionToSelect) {
                    optionToSelect.selected = true;
                }
            }
            
            // Add floating effect to contact stats
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const statBoxes = document.querySelectorAll('.contact-stat');
                
                statBoxes.forEach((box, index) => {
                    const delay = index * 0.2;
                    const y = Math.sin((scrolled * 0.001) + delay) * 15;
                    box.style.transform = `translateY(${y}px)`;
                });
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
            
            // Update contact stats layout for mobile
            function updateContactStatsLayout() {
                const contactStats = document.querySelectorAll('.contact-stat');
                const contactStatsContainer = document.querySelector('.contact-stats');
                
                if (window.innerWidth <= 576) {
                    contactStats.forEach(stat => {
                        stat.style.minWidth = 'calc(50% - 10px)';
                        stat.style.flex = '0 0 calc(50% - 10px)';
                    });
                    
                    if (window.innerWidth <= 360) {
                        contactStatsContainer.style.flexWrap = 'nowrap';
                        contactStatsContainer.style.overflowX = 'auto';
                        contactStats.forEach(stat => {
                            stat.style.minWidth = '140px';
                            stat.style.flex = '0 0 auto';
                        });
                    } else {
                        contactStatsContainer.style.flexWrap = 'wrap';
                        contactStatsContainer.style.overflowX = 'visible';
                    }
                } else {
                    contactStats.forEach(stat => {
                        stat.style.minWidth = '';
                        stat.style.flex = '';
                    });
                    contactStatsContainer.style.flexWrap = 'wrap';
                    contactStatsContainer.style.overflowX = 'visible';
                }
            }
            
            updateContactStatsLayout();
            window.addEventListener('resize', updateContactStatsLayout);
            
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
                    updateContactStatsLayout();
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
            console.log('Mobile menu initialized for contact page');
            console.log('Viewport width:', window.innerWidth);
            console.log('Viewport height:', window.innerHeight);
        });
        
        // Add custom styles for form validation
        const style = document.createElement('style');
        style.textContent = `
            .is-invalid {
                border-color: #dc3545 !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
                padding-right: calc(1.5em + 0.75rem);
            }
            
            .is-invalid:focus {
                border-color: #dc3545;
                box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
            }
            
            .form-group.focused .form-label {
                color: var(--primary-green);
                font-weight: 700;
            }
            
            .form-group.focused .form-control,
            .form-group.focused .form-select {
                border-color: var(--primary-green);
                box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.15);
            }
            
            .fa-spin {
                animation: fa-spin 1s infinite linear;
            }
            
            @keyframes fa-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Custom scrollbar */
            ::-webkit-scrollbar {
                width: 10px;
            }
            
            ::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 5px;
            }
            
            ::-webkit-scrollbar-thumb {
                background: linear-gradient(to bottom, var(--primary-yellow), var(--primary-green));
                border-radius: 5px;
            }
            
            ::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(to bottom, var(--accent-yellow), var(--dark-green));
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>