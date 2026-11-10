<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

header('Location: logs.php?level=audit');
exit;
