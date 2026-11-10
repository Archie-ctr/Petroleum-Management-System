<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit();
}

$docId = intval($_GET['id']);

$sql = "SELECT * FROM help_documents WHERE id = $docId AND status != 'deleted'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $document = $result->fetch_assoc();
    echo json_encode(['success' => true, 'document' => $document]);
} else {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
}
?>