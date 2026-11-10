<?php
// Security helper functions

function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function executeSecureQuery($conn, $sql, $params = [], $types = '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if ($params && $types) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    return $stmt->get_result();
}

function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, __DIR__ . '/../logs/errors.log');
}

function logSecurityEvent($message) {
    error_log(date('[Y-m-d H:i:s] SECURITY: ') . $message . PHP_EOL, 3, __DIR__ . '/../logs/security.log');
}

function logInfo($message) {
    error_log(date('[Y-m-d H:i:s] INFO: ') . $message . PHP_EOL, 3, __DIR__ . '/../logs/app.log');
}
?>