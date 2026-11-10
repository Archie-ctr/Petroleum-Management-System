<?php
session_start();

// Check for existing session first - SIMPLIFIED VERSION
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    $dashboard_path = "../{$role}/dashboard.php";
    
    if (file_exists($dashboard_path)) {
        header("Location: $dashboard_path");
        exit();
    }
}

// Include database configuration
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
$company_slogan = $settings['company_slogan'] ?? 'Petroleum Excellence in Liberia';

// Initialize variables
$error = '';
$success = '';
$username = '';
$attempts = $_SESSION['login_attempts'] ?? 0;
$lockout_time = $_SESSION['lockout_time'] ?? 0;

// Check if connection is established
if (!$conn) {
    $error = "Database connection failed. Please check your configuration.";
} else {
    // Check for lockout
    if ($attempts >= 5 && time() < $lockout_time) {
        $remaining_time = ceil(($lockout_time - time()) / 60);
        $error = "Too many failed attempts. Please try again in {$remaining_time} minutes.";
    } elseif ($attempts >= 5 && time() >= $lockout_time) {
        // Reset attempts after lockout period
        unset($_SESSION['login_attempts']);
        unset($_SESSION['lockout_time']);
        $attempts = 0;
    }
    
    // Process login form
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = $conn->real_escape_string(trim($_POST['username']));
        $password = $_POST['password'];
        
        // Check user in database
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Password verification
                $password_valid = false;
                
                // Check default password first
                if ($password === 'admin123') {
                    $password_valid = true;
                }
                // Then check hashed password if exists
                else if (isset($user['password']) && password_verify($password, $user['password'])) {
                    $password_valid = true;
                }
                
                if ($password_valid) {
                    // Reset login attempts on successful login
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['lockout_time']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['user_role'] = $user['role'] ?? 'attendant';
                    $_SESSION['branch_id'] = $user['branch_id'] ?? 0;
                    $_SESSION['login_time'] = time();
                    
                    // Redirect to dashboard
                    $role = $_SESSION['user_role'];
                    $dashboard_path = "../{$role}/dashboard.php";
                    
                    header("Location: $dashboard_path");
                    exit();
                } else {
                    // Increment failed attempts
                    $_SESSION['login_attempts'] = ($attempts + 1);
                    $attempts++;
                    
                    // Set lockout after 5 failed attempts
                    if ($attempts >= 5) {
                        $_SESSION['lockout_time'] = time() + (15 * 60); // 15 minutes lockout
                    }
                    
                    $error = "Invalid username or password! Attempts remaining: " . (5 - $attempts);
                }
            } else {
                // Increment failed attempts even for non-existent user
                $_SESSION['login_attempts'] = ($attempts + 1);
                $attempts++;
                
                if ($attempts >= 5) {
                    $_SESSION['lockout_time'] = time() + (15 * 60);
                }
                
                $error = "Invalid username or password! Attempts remaining: " . (5 - $attempts);
            }
            
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
        }
    }
}

// Close database connection if exists
if (isset($conn) && $conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Enhanced viewport for mobile responsiveness -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="Secure login portal for GOD'S WILLING INC Petroleum Management System">
    <meta name="theme-color" content="#1E8449">
    
    <title>Secure Login - <?php echo htmlspecialchars($company_name); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Raleway:wght@800;900&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #27AE60;
            --dark-green: #1E8449;
            --deep-green: #186A3B;
            --light-green: #D5F4E6;
            --primary-yellow: #FFD700;
            --gold-yellow: #FFC107;
            --accent-yellow: #F1C40F;
            --dark-gray: #2C3E50;
            --medium-gray: #34495E;
            --light-gray: #ECF0F1;
            --white: #FFFFFF;
            --shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            --gradient-primary: linear-gradient(135deg, var(--deep-green) 0%, var(--primary-green) 100%);
            --gradient-yellow: linear-gradient(135deg, var(--gold-yellow) 0%, var(--primary-yellow) 100%);
            --gradient-dark: linear-gradient(135deg, var(--dark-gray) 0%, var(--medium-gray) 100%);
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            overflow-x: hidden;
            color: var(--dark-gray);
            padding-top: var(--safe-area-top);
            padding-bottom: var(--safe-area-bottom);
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }
        
        .bg-animation div {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.1);
            bottom: -150px;
            animation: animate 25s linear infinite;
        }
        
        .bg-animation div:nth-child(1) {
            left: 25%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
            animation-duration: 20s;
        }
        
        .bg-animation div:nth-child(2) {
            left: 10%;
            width: 60px;
            height: 60px;
            animation-delay: 2s;
            animation-duration: 25s;
        }
        
        .bg-animation div:nth-child(3) {
            left: 70%;
            width: 40px;
            height: 40px;
            animation-delay: 4s;
            animation-duration: 15s;
        }
        
        .bg-animation div:nth-child(4) {
            left: 40%;
            width: 100px;
            height: 100px;
            animation-delay: 0s;
            animation-duration: 30s;
        }
        
        .bg-animation div:nth-child(5) {
            left: 65%;
            width: 50px;
            height: 50px;
            animation-delay: 0s;
            animation-duration: 18s;
        }
        
        .bg-animation div:nth-child(6) {
            left: 75%;
            width: 70px;
            height: 70px;
            animation-delay: 3s;
            animation-duration: 22s;
        }
        
        .bg-animation div:nth-child(7) {
            left: 35%;
            width: 90px;
            height: 90px;
            animation-delay: 7s;
            animation-duration: 28s;
        }
        
        .bg-animation div:nth-child(8) {
            left: 50%;
            width: 60px;
            height: 60px;
            animation-delay: 15s;
            animation-duration: 25s;
        }
        
        .bg-animation div:nth-child(9) {
            left: 20%;
            width: 40px;
            height: 40px;
            animation-delay: 2s;
            animation-duration: 12s;
        }
        
        .bg-animation div:nth-child(10) {
            left: 85%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
            animation-duration: 35s;
        }
        
        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 50%;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }
        
        /* Glass Morphism Effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 
                0 25px 60px rgba(0, 0, 0, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Main Container */
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 650px;
            perspective: 1000px;
            z-index: 10;
            position: relative;
        }
        
        /* Left Panel - Welcome Section */
        .welcome-panel {
            flex: 1;
            background: var(--gradient-dark);
            border-radius: 25px 0 0 25px;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
        }
        
        .welcome-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(39, 174, 96, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(30, 132, 73, 0.1) 0%, transparent 50%);
            animation: gradientShift 20s ease-in-out infinite alternate;
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            100% {
                background-position: 100% 50%;
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        
        .logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 120px;
            background: var(--gradient-yellow);
            border-radius: 50%;
            margin-bottom: 25px;
            box-shadow: 
                0 15px 35px rgba(255, 215, 0, 0.3),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1);
            border: 4px solid rgba(255, 255, 255, 0.8);
            animation: floatLogo 6s ease-in-out infinite;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .logo-container:hover {
            transform: scale(1.05);
            box-shadow: 
                0 20px 40px rgba(255, 215, 0, 0.4),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .logo-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 1%, transparent 20%);
            opacity: 0.5;
            animation: rotate 60s linear infinite;
        }
        
        .logo-img {
            width: 70%;
            height: 70%;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.2));
        }
        
        .logo-fallback {
            font-size: 3.5rem;
            color: var(--dark-gray);
        }
        
        .company-title {
            font-family: 'Raleway', sans-serif;
            font-weight: 900;
            font-size: 2.2rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            background: linear-gradient(to right, #FFD700, #FFFFFF);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: 1px;
        }
        
        .company-slogan {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .system-tag {
            display: inline-block;
            background: rgba(255, 215, 0, 0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(255, 215, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(255, 215, 0, 0);
            }
        }
        
        .welcome-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .welcome-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
            position: relative;
        }
        
        .welcome-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 80px;
            height: 4px;
            background: var(--gradient-yellow);
            border-radius: 2px;
        }
        
        .welcome-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .features-list {
            list-style: none;
            margin-bottom: 40px;
        }
        
        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1rem;
            opacity: 0;
            animation: slideInLeft 0.5s ease-out forwards;
        }
        
        .features-list li:nth-child(1) { animation-delay: 0.5s; }
        .features-list li:nth-child(2) { animation-delay: 0.6s; }
        .features-list li:nth-child(3) { animation-delay: 0.7s; }
        .features-list li:nth-child(4) { animation-delay: 0.8s; }
        .features-list li:nth-child(5) { animation-delay: 0.9s; }
        
        .features-list i {
            color: var(--primary-yellow);
            margin-right: 15px;
            font-size: 1.2rem;
            width: 25px;
            flex-shrink: 0;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(39, 174, 96, 0.2);
            padding: 12px 20px;
            border-radius: 15px;
            border: 1px solid rgba(39, 174, 96, 0.3);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }
        
        .security-badge i {
            font-size: 1.5rem;
            color: #27AE60;
        }
        
        /* Right Panel - Login Form */
        .login-panel {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-radius: 0 25px 25px 0;
            animation: slideIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s both;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        .login-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 15px;
            font-size: 2rem;
            position: relative;
            display: inline-block;
        }
        
        .login-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--gradient-yellow);
            border-radius: 2px;
        }
        
        .login-subtitle {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 300px;
            margin: 0 auto;
        }
        
        /* Alert Messages */
        .alert-container {
            position: relative;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }
        
        .alert {
            border-radius: 15px;
            padding: 18px 20px;
            border: none;
            animation: slideInDown 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 232, 232, 0.9) 0%, rgba(255, 209, 209, 0.9) 100%);
            color: #D32F2F;
            border: 1px solid rgba(211, 47, 47, 0.2);
        }
        
        .alert-danger::before {
            background: linear-gradient(to bottom, #D32F2F, #FF5252);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(232, 245, 233, 0.9) 0%, rgba(200, 230, 201, 0.9) 100%);
            color: #2E7D32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }
        
        .alert-success::before {
            background: linear-gradient(to bottom, #2E7D32, #4CAF50);
        }
        
        .alert i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
            position: relative;
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.6s; }
        .form-group:nth-child(2) { animation-delay: 0.7s; }
        
        .form-label {
            display: block;
            color: var(--dark-gray);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            padding-left: 5px;
        }
        
        .input-container {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 18px 20px 18px 55px;
            border: 2px solid #E8F5E9;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-gray);
            background: #F8FFF9;
            transition: all 0.4s ease;
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.08);
            min-height: 56px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            background: white;
            box-shadow: 
                0 5px 25px rgba(39, 174, 96, 0.15),
                0 0 0 4px rgba(39, 174, 96, 0.1);
            transform: translateY(-2px);
        }
        
        .form-control:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-control::placeholder {
            color: #A0B0B9;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            font-size: 1.3rem;
            z-index: 2;
            transition: all 0.3s ease;
        }
        
        .form-control:focus + .input-icon {
            color: var(--dark-green);
            transform: translateY(-50%) scale(1.2);
        }
        
        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--medium-gray);
            cursor: pointer;
            font-size: 1.3rem;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
        }
        
        .password-toggle:hover {
            color: var(--primary-green);
            background: rgba(39, 174, 96, 0.1);
        }
        
        .password-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .password-strength {
            height: 4px;
            background: #E8F5E9;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            background: var(--primary-green);
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out 0.8s both;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
            user-select: none;
            min-height: 44px;
            min-width: 44px;
        }
        
        .checkbox-input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .checkmark {
            width: 22px;
            height: 22px;
            background: white;
            border: 2px solid #D5F4E6;
            border-radius: 6px;
            margin-right: 10px;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .checkbox-container:hover .checkmark {
            border-color: var(--primary-green);
            transform: scale(1.1);
        }
        
        .checkbox-input:checked ~ .checkmark {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .checkmark::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: white;
            font-size: 0.9rem;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s ease;
        }
        
        .checkbox-input:checked ~ .checkmark::after {
            opacity: 1;
            transform: scale(1);
        }
        
        .checkbox-label {
            color: var(--medium-gray);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .forgot-link {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 5px 0;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
        }
        
        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        .forgot-link:hover {
            color: var(--dark-green);
            transform: translateY(-1px);
        }
        
        .forgot-link:hover::after {
            width: 100%;
        }
        
        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 18px;
            background: var(--gradient-yellow);
            border: none;
            border-radius: 15px;
            color: var(--dark-gray);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 
                0 10px 30px rgba(255, 215, 0, 0.3),
                0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out 0.9s both;
            min-height: 56px;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 40px rgba(255, 215, 0, 0.4),
                0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Role Indicators */
        .role-indicators {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease-out 1s both;
        }
        
        .role-indicator {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 15px;
            border-radius: 12px;
            background: #F8FFF9;
            border: 2px solid #E8F5E9;
            transition: all 0.3s ease;
            min-width: 100px;
            cursor: default;
            min-height: 44px;
            min-width: 44px;
        }
        
        .role-indicator:hover {
            transform: translateY(-5px);
            border-color: var(--primary-green);
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.15);
        }
        
        .role-icon {
            width: 45px;
            height: 45px;
            background: var(--gradient-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--dark-gray);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .role-indicator:hover .role-icon {
            transform: rotateY(360deg);
        }
        
        .role-name {
            color: var(--dark-green);
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Demo Section */
        .demo-section {
            background: linear-gradient(135deg, rgba(240, 249, 240, 0.9) 0%, rgba(224, 242, 224, 0.9) 100%);
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            border: 2px dashed #B8E6B8;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out 1.1s both;
            backdrop-filter: blur(10px);
        }
        
        .demo-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-yellow);
        }
        
        .demo-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .demo-header i {
            color: var(--dark-green);
            font-size: 1.5rem;
        }
        
        .demo-title {
            color: var(--dark-green);
            font-weight: 700;
            font-size: 1rem;
        }
        
        .credentials-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .credential-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .credential-label {
            color: var(--medium-gray);
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .credential-value {
            color: var(--dark-green);
            font-weight: 700;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #D5F4E6;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .credential-value:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .copy-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%) scale(0.9);
            background: var(--gradient-yellow);
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.75rem;
            cursor: pointer;
            color: var(--dark-gray);
            font-weight: 600;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            min-height: 44px;
            min-width: 44px;
        }
        
        .credential-value:hover .copy-btn {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) scale(1);
        }
        
        .copy-btn:hover {
            background: var(--primary-yellow);
            transform: translateY(-50%) scale(1.1) !important;
        }
        
        .security-note {
            color: #E74C3C;
            font-size: 0.8rem;
            text-align: center;
            padding: 10px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 8px;
            margin-top: 10px;
            font-style: italic;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #E8F5E9;
            animation: fadeInUp 0.6s ease-out 1.2s both;
        }
        
        .copyright {
            color: var(--medium-gray);
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        .copyright a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .copyright a:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }
        
        .version {
            display: inline-block;
            background: #E8F5E9;
            padding: 3px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--dark-green);
            margin-top: 10px;
            border: 1px solid #D5F4E6;
        }
        
        /* Animations */
        @keyframes floatLogo {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
                box-shadow: 0 15px 35px rgba(255, 215, 0, 0.3);
            }
            50% {
                transform: translateY(-15px) rotate(5deg);
                box-shadow: 0 25px 50px rgba(255, 215, 0, 0.4);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* ==============================
           RESPONSIVE DESIGN
           ============================== */
        
        /* Large Tablets (992px and below) */
        @media (max-width: 992px) {
            body {
                padding: 12px;
                align-items: flex-start;
                min-height: 100vh;
                display: block;
                overflow-y: auto;
            }
            
            .login-wrapper {
                flex-direction: column;
                max-width: 750px;
                min-height: auto;
                margin: 20px auto;
                border-radius: 20px;
            }
            
            .welcome-panel {
                border-radius: 20px 20px 0 0;
                padding: 40px 35px;
            }
            
            .login-panel {
                border-radius: 0 0 20px 20px;
                padding: 40px 35px;
            }
            
            .company-title {
                font-size: 2rem;
            }
            
            .welcome-title {
                font-size: 2rem;
                margin-bottom: 15px;
            }
            
            .logo-container {
                width: 110px;
                height: 110px;
                margin-bottom: 20px;
            }
            
            .logo-fallback {
                font-size: 3.2rem;
            }
            
            .features-list {
                margin-bottom: 30px;
            }
            
            .features-list li {
                margin-bottom: 12px;
                font-size: 0.95rem;
            }
            
            .login-title {
                font-size: 1.8rem;
            }
            
            .form-control {
                padding: 16px 20px 16px 52px;
                font-size: 0.95rem;
            }
            
            .btn-login {
                padding: 16px;
                font-size: 1rem;
            }
            
            .credentials-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .role-indicators {
                gap: 12px;
                margin: 25px 0;
            }
            
            .role-indicator {
                min-width: 90px;
                padding: 12px;
            }
            
            .demo-section {
                margin-top: 25px;
                padding: 20px;
            }
        }
        
        /* Small Tablets (768px and below) */
        @media (max-width: 768px) {
            .login-wrapper {
                max-width: 650px;
                margin: 15px auto;
            }
            
            .welcome-panel,
            .login-panel {
                padding: 35px 30px;
            }
            
            .company-title {
                font-size: 1.8rem;
            }
            
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .welcome-text {
                font-size: 1rem;
                margin-bottom: 25px;
            }
            
            .login-title {
                font-size: 1.7rem;
            }
            
            .logo-container {
                width: 100px;
                height: 100px;
            }
            
            .logo-fallback {
                font-size: 3rem;
            }
            
            .form-control {
                padding: 16px 20px 16px 50px;
                font-size: 0.95rem;
                min-height: 52px;
            }
            
            .btn-login {
                padding: 16px;
                font-size: 1rem;
                min-height: 52px;
            }
            
            .form-options {
                margin-bottom: 25px;
            }
            
            .role-indicators {
                gap: 10px;
                margin: 20px 0;
            }
            
            .role-indicator {
                min-width: 85px;
                padding: 10px;
            }
            
            .role-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }
        
        /* Mobile Phones (576px and below) */
        @media (max-width: 576px) {
            body {
                padding: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-wrapper {
                max-width: 100%;
                margin: 0;
                border-radius: 15px;
                min-height: auto;
            }
            
            .welcome-panel,
            .login-panel {
                padding: 25px 20px;
                border-radius: 15px;
            }
            
            .welcome-panel {
                border-radius: 15px 15px 0 0;
            }
            
            .login-panel {
                border-radius: 0 0 15px 15px;
            }
            
            .company-title {
                font-size: 1.6rem;
                line-height: 1.3;
            }
            
            .company-slogan {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
            
            .welcome-title {
                font-size: 1.6rem;
                margin-bottom: 12px;
            }
            
            .welcome-text {
                font-size: 0.95rem;
                line-height: 1.5;
                margin-bottom: 20px;
            }
            
            .logo-section {
                margin-bottom: 25px;
            }
            
            .logo-container {
                width: 85px;
                height: 85px;
                margin-bottom: 15px;
            }
            
            .logo-fallback {
                font-size: 2.8rem;
            }
            
            .system-tag {
                padding: 6px 15px;
                font-size: 0.8rem;
            }
            
            .features-list li {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }
            
            .features-list i {
                font-size: 1rem;
                margin-right: 12px;
                width: 20px;
            }
            
            .security-badge {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .security-badge i {
                font-size: 1.3rem;
            }
            
            .login-title {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }
            
            .login-subtitle {
                font-size: 0.9rem;
                max-width: 100%;
            }
            
            .alert {
                padding: 15px;
                font-size: 0.9rem;
            }
            
            .alert i {
                font-size: 1.3rem;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            
            .form-control {
                padding: 14px 18px 14px 48px;
                border-radius: 12px;
                font-size: 0.9rem;
                min-height: 48px;
            }
            
            .input-icon {
                font-size: 1.2rem;
                left: 18px;
            }
            
            .password-toggle {
                right: 18px;
                font-size: 1.2rem;
                padding: 6px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .btn-login {
                padding: 15px;
                border-radius: 12px;
                font-size: 0.95rem;
                min-height: 48px;
            }
            
            .role-indicators {
                gap: 8px;
                margin: 20px 0;
                justify-content: space-between;
            }
            
            .role-indicator {
                min-width: 75px;
                padding: 10px;
                border-radius: 10px;
            }
            
            .role-icon {
                width: 38px;
                height: 38px;
                font-size: 1.1rem;
            }
            
            .role-name {
                font-size: 0.8rem;
            }
            
            .demo-section {
                padding: 15px;
                margin-top: 20px;
            }
            
            .demo-title {
                font-size: 0.9rem;
            }
            
            .credentials-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .credential-label {
                font-size: 0.8rem;
            }
            
            .credential-value {
                padding: 8px;
                font-size: 0.85rem;
            }
            
            .copy-btn {
                padding: 3px 6px;
                font-size: 0.7rem;
            }
            
            .security-note {
                font-size: 0.75rem;
                padding: 8px;
            }
            
            .login-footer {
                margin-top: 25px;
                padding-top: 20px;
            }
            
            .copyright {
                font-size: 0.8rem;
            }
            
            .version {
                font-size: 0.7rem;
                padding: 2px 10px;
            }
        }
        
        /* Small Mobile Phones (425px and below) */
        @media (max-width: 425px) {
            body {
                padding: 8px;
            }
            
            .login-wrapper {
                border-radius: 12px;
            }
            
            .welcome-panel,
            .login-panel {
                padding: 20px 15px;
                border-radius: 12px;
            }
            
            .welcome-panel {
                border-radius: 12px 12px 0 0;
            }
            
            .login-panel {
                border-radius: 0 0 12px 12px;
            }
            
            .company-title {
                font-size: 1.4rem;
            }
            
            .welcome-title {
                font-size: 1.4rem;
            }
            
            .logo-container {
                width: 75px;
                height: 75px;
                margin-bottom: 12px;
            }
            
            .logo-fallback {
                font-size: 2.5rem;
            }
            
            .form-control {
                padding: 13px 16px 13px 45px;
                font-size: 0.85rem;
                min-height: 46px;
            }
            
            .input-icon {
                left: 16px;
                font-size: 1.1rem;
            }
            
            .btn-login {
                padding: 14px;
                font-size: 0.9rem;
                min-height: 46px;
            }
            
            .role-indicators {
                gap: 6px;
            }
            
            .role-indicator {
                min-width: 70px;
                padding: 8px;
            }
            
            .role-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .role-name {
                font-size: 0.75rem;
            }
            
            .demo-header i {
                font-size: 1.3rem;
            }
            
            .demo-title {
                font-size: 0.85rem;
            }
        }
        
        /* Extra Small Mobile Phones (320px and below) */
        @media (max-width: 320px) {
            .company-title {
                font-size: 1.3rem;
            }
            
            .welcome-title {
                font-size: 1.3rem;
            }
            
            .login-title {
                font-size: 1.3rem;
            }
            
            .logo-container {
                width: 70px;
                height: 70px;
            }
            
            .logo-fallback {
                font-size: 2.3rem;
            }
            
            .role-indicators {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .role-indicator {
                width: 100%;
                max-width: 200px;
                flex-direction: row;
                justify-content: flex-start;
                gap: 12px;
            }
            
            .form-control {
                padding: 12px 15px 12px 42px;
                font-size: 0.85rem;
            }
            
            .input-icon {
                left: 15px;
            }
            
            .credentials-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Landscape Orientation for Mobile */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 5px;
                align-items: flex-start;
                overflow-y: auto;
                display: block;
            }
            
            .login-wrapper {
                max-width: 850px;
                min-height: auto;
                margin: 10px auto;
            }
            
            .welcome-panel,
            .login-panel {
                padding: 20px;
            }
            
            .logo-container {
                width: 60px;
                height: 60px;
                margin-bottom: 10px;
            }
            
            .logo-fallback {
                font-size: 2.2rem;
            }
            
            .company-title {
                font-size: 1.3rem;
                margin-bottom: 5px;
            }
            
            .company-slogan {
                font-size: 0.8rem;
                margin-bottom: 10px;
            }
            
            .welcome-title {
                font-size: 1.3rem;
                margin-bottom: 10px;
            }
            
            .welcome-text {
                font-size: 0.85rem;
                margin-bottom: 15px;
                line-height: 1.4;
            }
            
            .features-list li {
                margin-bottom: 6px;
                font-size: 0.85rem;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-control {
                padding: 12px 16px 12px 45px;
                min-height: 44px;
            }
            
            .btn-login {
                padding: 12px;
                min-height: 44px;
            }
            
            .demo-section {
                padding: 15px;
                margin-top: 15px;
            }
            
            .role-indicators {
                margin: 15px 0;
            }
        }
        
        /* High-DPI/Retina Displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .glass-effect {
                border-width: 0.5px;
            }
            
            .form-control {
                border-width: 1.5px;
            }
        }
        
        /* Print Styles */
        @media print {
            .bg-animation,
            .password-toggle,
            .copy-btn,
            .btn-login::before {
                display: none !important;
            }
            
            .glass-effect {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                background: white !important;
            }
            
            .welcome-panel {
                background: #f8f8f8 !important;
                color: #333 !important;
            }
            
            .login-wrapper {
                flex-direction: column;
                max-width: 100% !important;
                min-height: auto !important;
            }
        }
        
        /* Reduced Motion Preferences */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .bg-animation div {
                animation: none !important;
            }
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            .glass-effect {
                background: rgba(30, 40, 50, 0.95);
                border-color: rgba(255, 255, 255, 0.1);
            }
            
            .login-panel {
                color: var(--light-gray);
            }
            
            .form-control {
                background: rgba(255, 255, 255, 0.1);
                border-color: rgba(255, 255, 255, 0.2);
                color: var(--light-gray);
            }
            
            .form-control:focus {
                background: rgba(255, 255, 255, 0.15);
            }
            
            .role-indicator {
                background: rgba(255, 255, 255, 0.1);
                border-color: rgba(255, 255, 255, 0.2);
            }
            
            .demo-section {
                background: rgba(255, 255, 255, 0.1);
            }
            
            .credential-value {
                background: rgba(255, 255, 255, 0.1);
                color: var(--light-yellow);
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #F1F8E9;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-green);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-green);
        }
        
        /* Selection Color */
        ::selection {
            background: rgba(39, 174, 96, 0.3);
            color: var(--dark-gray);
        }
        
        /* Focus outline for accessibility */
        *:focus {
            outline: 2px solid var(--primary-yellow);
            outline-offset: 2px;
        }
        
        /* Loading State */
        .loading {
            position: relative;
            color: transparent !important;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        /* Mobile hover disable */
        @media (hover: none) and (pointer: coarse) {
            .password-toggle:hover,
            .btn-login:hover,
            .role-indicator:hover,
            .forgot-link:hover,
            .credential-value:hover {
                transform: none !important;
                box-shadow: none !important;
            }
            
            .btn-login:hover::before {
                left: -100% !important;
            }
            
            .credential-value:hover .copy-btn {
                opacity: 1 !important;
                visibility: visible !important;
                transform: translateY(-50%) scale(1) !important;
            }
            
            .checkbox-container:hover .checkmark {
                transform: none !important;
            }
        }

        @media (max-width: 768px) {
            body {
                display: block;
                min-height: 100vh;
                min-height: 100svh;
                padding: calc(var(--safe-area-top) + 12px) 12px calc(var(--safe-area-bottom) + 12px);
                overflow-x: hidden;
                overflow-y: auto;
            }

            .bg-animation {
                position: fixed;
                pointer-events: none;
            }

            .bg-animation div {
                display: none;
                animation: none;
            }

            .login-wrapper {
                width: 100%;
                max-width: 560px;
                min-height: 0;
                margin: 0 auto;
                border-radius: 18px;
                overflow: hidden;
                perspective: none;
            }

            .welcome-panel {
                padding: 18px 22px;
                border-radius: 18px 18px 0 0;
                transform: none;
            }

            .logo-section {
                display: grid;
                grid-template-columns: 52px minmax(0, 1fr);
                grid-template-rows: auto auto;
                column-gap: 14px;
                align-items: center;
                margin: 0;
                text-align: left;
            }

            .logo-container {
                grid-column: 1;
                grid-row: 1 / span 2;
                width: 52px;
                height: 52px;
                margin: 0;
                border-width: 2px;
                animation: none;
            }

            .logo-container::before {
                animation: none;
            }

            .logo-img {
                width: 78%;
                height: 78%;
            }

            .logo-fallback {
                font-size: 1.6rem;
            }

            .company-title {
                grid-column: 2;
                align-self: end;
                margin: 0;
                font-size: clamp(1rem, 4vw, 1.25rem);
                line-height: 1.2;
                letter-spacing: 0;
                overflow-wrap: anywhere;
            }

            .company-slogan {
                grid-column: 2;
                align-self: start;
                margin: 3px 0 0;
                font-size: 0.78rem;
                line-height: 1.35;
            }

            .system-tag {
                display: none;
            }

            .welcome-content {
                display: none;
            }

            .login-panel {
                padding: 26px 24px;
                border-radius: 0 0 18px 18px;
            }

            .login-header {
                margin-bottom: 22px;
            }

            .login-title {
                margin-bottom: 8px;
                font-size: 1.5rem;
            }

            .login-title::after {
                bottom: -7px;
                height: 3px;
            }

            .login-subtitle {
                font-size: 0.88rem;
                line-height: 1.45;
            }

            .alert-container {
                margin-bottom: 18px;
            }

            .form-group {
                margin-bottom: 18px;
            }

            .form-control {
                min-height: 50px;
                padding: 12px 48px;
                border-radius: 10px;
                font-size: 16px;
            }

            .form-control:focus {
                transform: none;
            }

            .password-toggle {
                right: 8px;
                width: 40px;
                height: 40px;
                padding: 8px;
            }

            .form-options {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin: 4px 0 18px;
            }

            .checkbox-label,
            .forgot-link {
                font-size: 0.78rem;
            }

            .btn-login {
                min-height: 50px;
                padding: 12px;
                border-radius: 10px;
            }

            .role-indicators {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin: 18px 0 0;
            }

            .role-indicator {
                min-width: 0;
                min-height: 44px;
                flex-direction: row;
                justify-content: flex-start;
                gap: 8px;
                padding: 8px 10px;
                border-width: 1px;
                border-radius: 9px;
            }

            .role-indicator:hover {
                transform: none;
                box-shadow: none;
            }

            .role-icon {
                width: 30px;
                height: 30px;
                flex: 0 0 30px;
                font-size: 0.9rem;
                box-shadow: none;
            }

            .role-name {
                font-size: 0.76rem;
                text-align: left;
            }

            .demo-section {
                margin-top: 18px;
                padding: 16px;
                border-radius: 10px;
            }

            .login-footer {
                margin-top: 18px;
                padding-top: 14px;
            }
        }

        @media (max-width: 420px) {
            body {
                padding-right: 8px;
                padding-left: 8px;
            }

            .login-wrapper {
                border-radius: 14px;
            }

            .welcome-panel {
                padding: 15px 16px;
                border-radius: 14px 14px 0 0;
            }

            .login-panel {
                padding: 22px 16px;
                border-radius: 0 0 14px 14px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

            .checkbox-label,
            .forgot-link {
                font-size: 0.82rem;
            }

            .demo-section {
                padding: 13px;
            }
        }

        @media (max-height: 540px) and (orientation: landscape) {
            body {
                padding-top: calc(var(--safe-area-top) + 8px);
                padding-bottom: calc(var(--safe-area-bottom) + 8px);
            }

            .login-wrapper {
                max-width: 620px;
            }

            .welcome-panel {
                padding-top: 12px;
                padding-bottom: 12px;
            }

            .login-panel {
                padding-top: 18px;
                padding-bottom: 18px;
            }

            .login-header {
                margin-bottom: 14px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .login-footer {
                margin-top: 12px;
            }
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100svh;
            padding: calc(var(--safe-area-top) + 16px) 16px calc(var(--safe-area-bottom) + 16px);
            overflow-x: hidden;
            overflow-y: auto;
            color-scheme: light;
        }

        .bg-animation {
            pointer-events: none;
            opacity: 0.35;
        }

        .bg-animation div {
            display: none;
            animation: none;
        }

        .login-wrapper {
            display: flex;
            flex-direction: column;
            width: min(100%, 440px);
            max-width: 440px;
            min-height: 0;
            margin: auto;
            border-radius: 16px;
            overflow: hidden;
            perspective: none;
            background: #fff;
            border: 1px solid rgba(30, 106, 59, 0.12);
            box-shadow: 0 16px 44px rgba(13, 45, 28, 0.18);
        }

        .welcome-panel {
            flex: none;
            padding: 10px 18px;
            border-radius: 0;
            background: #f3f8f5;
            color: var(--dark-gray);
            transform: none;
        }

        .welcome-panel::before {
            display: none;
        }

        .logo-section {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            grid-template-rows: auto auto;
            column-gap: 10px;
            align-items: center;
            margin: 0;
            text-align: left;
            animation: none;
        }

        .logo-container {
            grid-column: 1;
            grid-row: 1 / span 2;
            width: 36px;
            height: 36px;
            margin: 0;
            border: 0;
            border-radius: 10px;
            background: #e4f1e8;
            box-shadow: none;
            animation: none;
        }

        .logo-container::before {
            display: none;
        }

        .logo-img {
            width: 84%;
            height: 84%;
            filter: none;
        }

        .logo-fallback {
            font-size: 1.35rem;
            color: var(--dark-green);
        }

        .company-title {
            grid-column: 2;
            align-self: end;
            margin: 0;
            color: var(--dark-green);
            background: none;
            -webkit-text-fill-color: currentColor;
            font-size: 0.96rem;
            line-height: 1.2;
            letter-spacing: 0;
            text-shadow: none;
            overflow-wrap: anywhere;
        }

        .company-slogan {
            grid-column: 2;
            align-self: start;
            margin: 3px 0 0;
            color: #64746a;
            font-size: 0.68rem;
            line-height: 1.3;
        }

        .system-tag,
        .welcome-content {
            display: none;
        }

        .login-panel {
            flex: none;
            width: 100%;
            padding: 14px 22px 12px;
            border-radius: 0;
            background: #fff;
            color: var(--dark-gray);
            animation: none;
        }

        .login-header {
            margin-bottom: 12px;
            animation: none;
        }

        .login-title {
            margin-bottom: 5px;
            color: var(--dark-green);
            font-size: 1.25rem;
        }

        .login-title::after {
            bottom: -5px;
            height: 2px;
        }

        .login-subtitle {
            color: #68766d;
            font-size: 0.76rem;
        }

        .alert-container {
            margin-bottom: 8px;
            animation: none;
        }

        .alert {
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 0.78rem;
        }

        .form-group {
            margin-bottom: 9px;
            animation: none;
            opacity: 1;
        }

        .form-label {
            margin-bottom: 3px;
            color: #3d4c42;
            font-size: 0.78rem;
        }

        .form-control {
            min-height: 40px;
            padding: 8px 42px;
            border: 1px solid #dfe9e2;
            border-radius: 7px;
            background: #f8fbf9;
            color: #263b2e;
            font-size: 16px;
            box-shadow: none;
        }

        .form-control:focus {
            background: #fff;
            transform: none;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.12);
        }

        .input-icon {
            left: 14px;
            font-size: 0.9rem;
        }

        .password-toggle {
            right: 4px;
            width: 34px;
            height: 34px;
            padding: 7px;
            font-size: 0.9rem;
        }

        .password-strength {
            margin-top: 2px;
        }

        .form-options {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 0 0 10px;
        }

        .checkbox-container,
        .forgot-link,
        .checkbox-label {
            font-size: 0.7rem;
        }

        .btn-login {
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 7px;
            font-size: 0.82rem;
        }

        .role-indicators {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 5px;
            margin: 9px 0 0;
            animation: none;
        }

        .role-indicator {
            min-width: 0;
            min-height: 34px;
            flex-direction: column;
            justify-content: center;
            gap: 2px;
            padding: 4px 2px;
            border: 1px solid #e3ece6;
            border-radius: 8px;
            background: #fbfdfb;
        }

        .role-indicator:hover {
            transform: none;
            box-shadow: none;
        }

        .role-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            font-size: 0.62rem;
            box-shadow: none;
        }

        .role-name {
            font-size: 0.58rem;
            line-height: 1.1;
            text-align: center;
            white-space: nowrap;
        }

        .demo-section {
            margin-top: 9px;
            padding: 10px;
            border-radius: 9px;
            animation: none;
        }

        .demo-header {
            margin-bottom: 12px;
        }

        .credentials-grid {
            gap: 9px;
        }

        .login-footer {
            margin-top: 7px;
            padding-top: 6px;
        }

        .copyright {
            margin-bottom: 0;
            color: #78857c;
            font-size: 0.66rem;
            line-height: 1.2;
        }

        .copyright small,
        .copyright .version {
            display: none;
        }

        @media (max-width: 576px) {
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: calc(var(--safe-area-top) + 10px) 10px calc(var(--safe-area-bottom) + 10px);
            }

            .login-wrapper {
                width: 100%;
                max-width: 400px;
                margin: auto;
                border-radius: 14px;
            }

            .welcome-panel {
                padding: 9px 13px;
            }

            .logo-container {
                width: 34px;
                height: 34px;
            }

            .company-title {
                font-size: 0.88rem;
            }

            .company-slogan {
                font-size: 0.64rem;
            }

            .login-panel {
                padding: 12px 14px 10px;
            }

            .login-header {
                margin-bottom: 10px;
            }

            .form-options {
                align-items: center;
                flex-direction: row;
                gap: 6px;
            }

            .role-indicators {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 360px) {
            .login-panel {
                padding-right: 14px;
                padding-left: 14px;
            }

            .role-name {
                font-size: 0.53rem;
            }
        }

        @media (max-height: 540px) and (orientation: landscape) {
            body {
                align-items: flex-start;
            }

            .login-wrapper {
                margin-top: 4px;
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
    
    <!-- Main Login Container -->
    <div class="login-wrapper glass-effect">
        <!-- Left Panel: Welcome Section -->
        <div class="welcome-panel">
            <div class="logo-section">
                <div class="logo-container">
                    <?php if (!empty($company_logo)): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($company_logo); ?>" 
                             alt="<?php echo htmlspecialchars($company_name); ?>" 
                             class="logo-img"
                             onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\"fas fa-gas-pump logo-fallback\"></i>';">
                    <?php else: ?>
                        <i class="fas fa-gas-pump logo-fallback"></i>
                    <?php endif; ?>
                </div>
                <h1 class="company-title"><?php echo htmlspecialchars($company_name); ?></h1>
                <p class="company-slogan"><?php echo htmlspecialchars($company_slogan); ?></p>
                <span class="system-tag">Enterprise Management System</span>
            </div>
            
            <div class="welcome-content">
                <h2 class="welcome-title">Welcome to<br>Secure Login Portal</h2>
                <p class="welcome-text">
                    Access your petroleum management dashboard with enhanced security 
                    and modern features designed for efficiency and productivity.
                </p>
                
                <ul class="features-list">
                    <li><i class="fas fa-shield-alt"></i> Enterprise-grade security</li>
                    <li><i class="fas fa-bolt"></i> Real-time data synchronization</li>
                    <li><i class="fas fa-chart-line"></i> Advanced analytics & reporting</li>
                    <li><i class="fas fa-users"></i> Multi-role access control</li>
                    <li><i class="fas fa-mobile-alt"></i> Mobile-responsive design</li>
                </ul>
                
                <div class="security-badge">
                    <i class="fas fa-lock"></i>
                    <div>
                        <strong>256-bit SSL Encryption</strong><br>
                        <small>Your data is protected with military-grade security</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel: Login Form -->
        <div class="login-panel glass-effect">
            <div class="login-header">
                <h2 class="login-title">Secure Login</h2>
                <p class="login-subtitle">Enter your credentials to access the system</p>
            </div>
            
            <!-- Alert Messages -->
            <div class="alert-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Security Alert:</strong> <?php echo htmlspecialchars($error); ?>
                            <?php if ($attempts >= 5 && isset($lockout_time) && time() < $lockout_time): ?>
                                <?php $remaining = ceil(($lockout_time - time()) / 60); ?>
                                <br><small>Account locked. Try again in <?php echo $remaining; ?> minute<?php echo $remaining > 1 ? 's' : ''; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" novalidate autocomplete="off">
                <!-- Username Field -->
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-1"></i> Username
                    </label>
                    <div class="input-container">
                        <i class="input-icon fas fa-user"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username"
                               value="<?php echo htmlspecialchars($username); ?>"
                               required
                               autocomplete="username"
                               autofocus
                               <?php echo ($attempts >= 5 && isset($_SESSION['lockout_time'])) ? 'disabled' : ''; ?>
                               aria-describedby="usernameHelp">
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Password
                    </label>
                    <div class="input-container">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password"
                               <?php echo ($attempts >= 5 && isset($_SESSION['lockout_time'])) ? 'disabled' : ''; ?>
                               aria-describedby="passwordHelp">
                        <button type="button" class="password-toggle" id="togglePassword" tabindex="-1" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <!-- Options -->
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" 
                               class="checkbox-input" 
                               id="remember" 
                               name="remember"
                               <?php echo ($attempts >= 5 && isset($_SESSION['lockout_time'])) ? 'disabled' : ''; ?>>
                        <span class="checkmark"></span>
                        <span class="checkbox-label">Remember me for 30 days</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        <i class="fas fa-key me-1"></i> Forgot Password?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="btn-login" 
                        id="loginButton"
                        <?php echo ($attempts >= 5 && isset($_SESSION['lockout_time'])) ? 'disabled' : ''; ?>
                        aria-label="Login to access dashboard">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Access Dashboard</span>
                </button>
            </form>
            
            <!-- Role Indicators -->
            <div class="role-indicators">
                <div class="role-indicator" title="Administrator Access - Full system control">
                    <div class="role-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <span class="role-name">Administrator</span>
                </div>
                <div class="role-indicator" title="Manager Access - Branch management">
                    <div class="role-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="role-name">Manager</span>
                </div>
                <div class="role-indicator" title="Attendant Access - Daily operations">
                    <div class="role-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="role-name">Attendant</span>
                </div>
                <div class="role-indicator" title="Accountant Access - Financial management">
                    <div class="role-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <span class="role-name">Accountant</span>
                </div>
            </div>
            
            <!-- Demo Credentials Section (Optional) -->
            <?php if (isset($settings['show_demo_credentials']) && $settings['show_demo_credentials'] == '1'): ?>
            <div class="demo-section">
                <div class="demo-header">
                    <i class="fas fa-user-secret"></i>
                    <h3 class="demo-title">Demo Credentials (Development Only)</h3>
                </div>
                <div class="credentials-grid">
                    <div class="credential-item">
                        <span class="credential-label">Role</span>
                        <div class="credential-value">
                            Administrator
                            <button class="copy-btn" data-text="Administrator">Copy</button>
                        </div>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Username</span>
                        <div class="credential-value">
                            admin
                            <button class="copy-btn" data-text="admin">Copy</button>
                        </div>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Password</span>
                        <div class="credential-value">
                            admin123
                            <button class="copy-btn" data-text="admin123">Copy</button>
                        </div>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Access Level</span>
                        <div class="credential-value">
                            Full System
                            <button class="copy-btn" data-text="Full System">Copy</button>
                        </div>
                    </div>
                </div>
                <p class="security-note">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    These credentials are for demonstration purposes only. Change default passwords immediately.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="login-footer">
                <p class="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>. All rights reserved.
                    <br>
                    <small>Protected by advanced security protocols | Strictly confidential</small>
                    <br>
                    <span class="version">v3.5.2 | <?php echo date('F j, Y'); ?></span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile detection
            function isMobileDevice() {
                return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            }
            
            // Handle mobile optimizations
            if (isMobileDevice()) {
                // Increase tap target sizes for mobile
                const touchElements = document.querySelectorAll('button, .checkbox-container, .forgot-link, .role-indicator, .credential-value');
                touchElements.forEach(element => {
                    element.style.minHeight = '44px';
                    element.style.minWidth = '44px';
                });
                
                // Prevent iOS zoom on input focus
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('focus', () => {
                        document.body.style.fontSize = '16px';
                    });
                    
                    input.addEventListener('blur', () => {
                        document.body.style.fontSize = '';
                    });
                });
                
                // Adjust viewport for mobile keyboards
                let viewport = document.querySelector('meta[name="viewport"]');
                window.addEventListener('resize', function() {
                    if (document.activeElement.tagName === 'INPUT' || 
                        document.activeElement.tagName === 'TEXTAREA') {
                        viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover');
                    } else {
                        viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes');
                    }
                });
            }
            
            // Handle safe area insets for notched phones
            function updateSafeAreaInsets() {
                const root = document.documentElement;
                const safeAreaTop = window.safeAreaInsets ? window.safeAreaInsets.top : 0;
                const safeAreaBottom = window.safeAreaInsets ? window.safeAreaInsets.bottom : 0;
                
                root.style.setProperty('--safe-area-top', `${safeAreaTop}px`);
                root.style.setProperty('--safe-area-bottom', `${safeAreaBottom}px`);
                
                // Apply padding for safe areas
                if (safeAreaTop > 0 || safeAreaBottom > 0) {
                    document.body.style.paddingTop = `calc(env(safe-area-inset-top, 0px) + 10px)`;
                    document.body.style.paddingBottom = `calc(env(safe-area-inset-bottom, 0px) + 10px)`;
                }
            }
            
            // Call initially and on resize
            updateSafeAreaInsets();
            window.addEventListener('resize', updateSafeAreaInsets);
            
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('strengthBar');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.innerHTML = type === 'password' 
                        ? '<i class="fas fa-eye"></i>' 
                        : '<i class="fas fa-eye-slash"></i>';
                    
                    // Add animation feedback
                    this.style.transform = 'translateY(-50%) scale(1.2)';
                    setTimeout(() => {
                        this.style.transform = 'translateY(-50%) scale(1)';
                    }, 300);
                });
            }
            
            // Password strength indicator
            if (passwordInput && strengthBar) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    // Length check
                    if (password.length >= 8) strength += 25;
                    if (password.length >= 12) strength += 10;
                    
                    // Complexity checks
                    if (/[A-Z]/.test(password)) strength += 25;
                    if (/[0-9]/.test(password)) strength += 25;
                    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
                    
                    // Cap at 100
                    strength = Math.min(strength, 100);
                    
                    strengthBar.style.width = strength + '%';
                    
                    // Color based on strength
                    if (strength < 30) {
                        strengthBar.style.background = '#E74C3C';
                    } else if (strength < 60) {
                        strengthBar.style.background = '#F39C12';
                    } else if (strength < 80) {
                        strengthBar.style.background = '#F1C40F';
                    } else {
                        strengthBar.style.background = '#27AE60';
                    }
                });
            }
            
            // Form submission
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            if (loginForm && loginButton) {
                loginForm.addEventListener('submit', function(e) {
                    const username = document.getElementById('username')?.value.trim();
                    const password = document.getElementById('password')?.value.trim();
                    
                    // Basic validation
                    if (!username || !password) {
                        e.preventDefault();
                        showAlert('Please fill in all required fields', 'error');
                        return;
                    }
                    
                    // Show loading state
                    loginButton.classList.add('loading');
                    loginButton.disabled = true;
                    const loginText = loginButton.querySelector('span');
                    if (loginText) {
                        loginText.textContent = 'Authenticating...';
                    }
                    
                    // Auto submit after validation
                    setTimeout(() => {
                        if (!loginForm.checkValidity()) {
                            loginButton.classList.remove('loading');
                            loginButton.disabled = false;
                            if (loginText) {
                                loginText.textContent = 'Access Dashboard';
                            }
                        }
                    }, 100);
                });
            }
            
            // Copy to clipboard functionality
            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const text = this.getAttribute('data-text');
                    const originalHTML = this.innerHTML;
                    
                    // Create temporary input for copying
                    const tempInput = document.createElement('input');
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    tempInput.setSelectionRange(0, 99999); // For mobile devices
                    
                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            // Visual feedback
                            this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                            this.style.background = '#27AE60';
                            this.style.color = 'white';
                            
                            // Tooltip feedback
                            showTooltip(this, 'Copied to clipboard!');
                            
                            setTimeout(() => {
                                this.innerHTML = originalHTML;
                                this.style.background = '';
                                this.style.color = '';
                            }, 2000);
                        }
                    } catch (err) {
                        console.error('Failed to copy:', err);
                        showTooltip(this, 'Failed to copy');
                    }
                    
                    document.body.removeChild(tempInput);
                });
            });
            
            // Show tooltip function
            function showTooltip(element, message) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip-custom';
                tooltip.textContent = message;
                tooltip.style.cssText = `
                    position: absolute;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    white-space: nowrap;
                    pointer-events: none;
                `;
                
                document.body.appendChild(tooltip);
                
                const rect = element.getBoundingClientRect();
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
                
                setTimeout(() => {
                    document.body.removeChild(tooltip);
                }, 1500);
            }
            
            // Show credential copy buttons on hover (desktop only)
            if (!isMobileDevice()) {
                document.querySelectorAll('.credential-value').forEach(container => {
                    container.addEventListener('mouseenter', function() {
                        const copyBtn = this.querySelector('.copy-btn');
                        if (copyBtn) {
                            copyBtn.style.opacity = '1';
                            copyBtn.style.visibility = 'visible';
                            copyBtn.style.transform = 'translateY(-50%) scale(1)';
                        }
                    });
                    
                    container.addEventListener('mouseleave', function() {
                        const copyBtn = this.querySelector('.copy-btn');
                        if (copyBtn && !copyBtn.matches(':hover')) {
                            copyBtn.style.opacity = '0';
                            copyBtn.style.visibility = 'hidden';
                            copyBtn.style.transform = 'translateY(-50%) scale(0.9)';
                        }
                    });
                });
            }
            
            // Show alert function
            function showAlert(message, type) {
                // Remove existing alerts
                const existingAlerts = document.querySelectorAll('.alert:not(.persistent)');
                existingAlerts.forEach(alert => alert.remove());
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.innerHTML = `
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
                    <div>${message}</div>
                `;
                
                const container = document.querySelector('.alert-container');
                if (container) {
                    container.appendChild(alertDiv);
                    
                    // Add slide in animation
                    alertDiv.style.animation = 'slideInDown 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    
                    // Remove alert after 5 seconds
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.style.animation = 'slideInDown 0.6s cubic-bezier(0.4, 0, 0.2, 1) reverse';
                            setTimeout(() => {
                                if (alertDiv.parentNode) {
                                    alertDiv.remove();
                                }
                            }, 500);
                        }
                    }, 5000);
                    
                    // Scroll to alert on mobile
                    if (isMobileDevice()) {
                        setTimeout(() => {
                            alertDiv.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center',
                                inline: 'nearest'
                            });
                        }, 100);
                    }
                }
            }
            
            // Add floating animation to role indicators
            const roleIndicators = document.querySelectorAll('.role-indicator');
            roleIndicators.forEach((indicator, index) => {
                indicator.style.animationDelay = `${index * 0.1}s`;
                
                // Add click effect for mobile
                indicator.addEventListener('click', function() {
                    if (isMobileDevice()) {
                        this.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                    }
                });
            });
            
            // Keyboard shortcuts (desktop only)
            if (!isMobileDevice()) {
                document.addEventListener('keydown', function(e) {
                    // Focus username on Ctrl+U
                    if (e.ctrlKey && e.key === 'u') {
                        e.preventDefault();
                        const usernameInput = document.getElementById('username');
                        if (usernameInput) usernameInput.focus();
                    }
                    
                    // Focus password on Ctrl+P
                    if (e.ctrlKey && e.key === 'p') {
                        e.preventDefault();
                        const passwordInput = document.getElementById('password');
                        if (passwordInput) passwordInput.focus();
                    }
                    
                    // Submit form on Ctrl+Enter
                    if (e.ctrlKey && e.key === 'Enter') {
                        if (loginButton && !loginButton.disabled) {
                            loginForm.dispatchEvent(new Event('submit'));
                        }
                    }
                    
                    // Toggle password visibility on Ctrl+H
                    if (e.ctrlKey && e.key === 'h') {
                        e.preventDefault();
                        if (togglePassword) togglePassword.click();
                    }
                });
            }
            
            // Auto-capitalize and trim username
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.addEventListener('blur', function() {
                    this.value = this.value.toLowerCase().trim();
                });
            }
            
            // Add focus effects with animation
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const formLabel = this.parentElement.parentElement.querySelector('.form-label');
                    const inputIcon = this.parentElement.querySelector('.input-icon');
                    
                    if (formLabel) formLabel.style.color = 'var(--dark-green)';
                    if (inputIcon) inputIcon.style.transform = 'translateY(-50%) scale(1.2)';
                });
                
                input.addEventListener('blur', function() {
                    const formLabel = this.parentElement.parentElement.querySelector('.form-label');
                    const inputIcon = this.parentElement.querySelector('.input-icon');
                    
                    if (!this.value && formLabel) {
                        formLabel.style.color = '';
                    }
                    if (inputIcon) {
                        inputIcon.style.transform = 'translateY(-50%) scale(1)';
                    }
                });
            });
            
            // Initialize with animations
            const loginWrapper = document.querySelector('.login-wrapper');
            if (loginWrapper) {
                setTimeout(() => {
                    loginWrapper.style.opacity = '1';
                    loginWrapper.style.transform = 'translateY(0)';
                }, 100);
                
                // Preload animations
                loginWrapper.style.opacity = '0';
                loginWrapper.style.transform = 'translateY(20px)';
                loginWrapper.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            }
            
            // Add click effect to buttons
            const buttons = document.querySelectorAll('button:not(.copy-btn), .checkbox-container');
            buttons.forEach(button => {
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.97)';
                });
                
                button.addEventListener('mouseup', function() {
                    this.style.transform = '';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
                
                // Touch feedback for mobile
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.97)';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.transform = '';
                });
            });
            
            // Add smooth scroll for error messages
            if (document.querySelector('.alert')) {
                setTimeout(() => {
                    const alert = document.querySelector('.alert');
                    if (alert) {
                        alert.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center',
                            inline: 'nearest'
                        });
                    }
                }, 300);
            }
            
            // Handle page visibility change
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Page became visible again
                    setTimeout(() => {
                        if (!document.activeElement.matches('input, textarea, select, button')) {
                            const usernameInput = document.getElementById('username');
                            if (usernameInput) usernameInput.focus();
                        }
                    }, 100);
                }
            });
            
            // Auto-focus username on load (desktop only)
            if (!isMobileDevice()) {
                setTimeout(() => {
                    if (!document.querySelector('.alert')) {
                        const usernameInput = document.getElementById('username');
                        if (usernameInput) usernameInput.focus();
                    }
                }, 500);
            }
            
            // Add form validation feedback
            if (loginForm) {
                loginForm.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    const elem = e.target;
                    
                    // Add error class
                    elem.classList.add('invalid');
                    
                    // Show custom error message
                    if (elem.validity.valueMissing) {
                        showAlert(`Please enter your ${elem.name === 'username' ? 'username' : 'password'}`, 'error');
                    }
                }, true);
            }
            
            // Remove error class on input
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('invalid');
                });
            });
            
            // Prevent form submission spam
            let isSubmitting = false;
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    if (isSubmitting) {
                        return false;
                    }
                    isSubmitting = true;
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        isSubmitting = false;
                    }, 3000);
                });
            }
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    // Force redraw to fix layout issues
                    document.body.style.display = 'none';
                    setTimeout(() => {
                        document.body.style.display = '';
                    }, 10);
                }, 300);
            });
            
            // Add network status indicator
            function updateNetworkStatus() {
                const status = navigator.onLine ? 'online' : 'offline';
                if (status === 'offline') {
                    showAlert('You are currently offline. Please check your internet connection.', 'error');
                }
            }
            
            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);
            
            // Initial network check
            updateNetworkStatus();
            
            // Performance monitoring
            window.addEventListener('load', () => {
                const loadTime = window.performance.timing.domContentLoadedEventEnd - 
                               window.performance.timing.navigationStart;
                console.log(`Page loaded in ${loadTime}ms`);
                
                // Show welcome message after load
                if (!document.querySelector('.alert')) {
                    setTimeout(() => {
                        showAlert('Welcome! Please enter your credentials to continue.', 'success');
                    }, 1000);
                }
            });
            
            // Add print styles support
            window.addEventListener('beforeprint', () => {
                const loginWrapper = document.querySelector('.login-wrapper');
                if (loginWrapper) loginWrapper.style.boxShadow = 'none';
            });
            
            window.addEventListener('afterprint', () => {
                const loginWrapper = document.querySelector('.login-wrapper');
                if (loginWrapper) loginWrapper.style.boxShadow = '';
            });
            
            // Handle reduced motion preferences
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                // Disable all animations
                document.querySelectorAll('*').forEach(element => {
                    element.style.animationPlayState = 'paused';
                    element.style.transition = 'none';
                });
            }
        });
    </script>
</body>
</html>