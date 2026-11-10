<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit();
}

$catId = intval($_POST['id']);

// First, update documents in this category to uncategorized
$updateDocsSql = "UPDATE help_documents SET category_id = NULL WHERE category_id = $catId";
$conn->query($updateDocsSql);

// Then delete the category
$deleteCatSql = "DELETE FROM help_categories WHERE id = $catId";
if ($conn->query($deleteCatSql)) {
    echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>