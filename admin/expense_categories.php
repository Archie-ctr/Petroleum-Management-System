<?php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Check if user has admin access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php?error=unauthorized');
    exit();
}

// Initialize variables
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $created_by = $_SESSION['user_id'];
        
        // Validate input
        if (empty($category_name)) {
            $message = 'Category name is required';
            $message_type = 'error';
        } else {
            // Check if category already exists
            $check_sql = "SELECT id FROM expense_categories WHERE category_name = ? AND status != 'deleted'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'Category already exists';
                $message_type = 'error';
            } else {
                // Insert new category
                $insert_sql = "INSERT INTO expense_categories (category_name, description, status, created_by) 
                               VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssi", $category_name, $description, $status, $created_by);
                
                if ($insert_stmt->execute()) {
                    $message = 'Category added successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding category: ' . $conn->error;
                    $message_type = 'error';
                }
            }
        }
    }
    
    // Update category
    if (isset($_POST['update_category'])) {
        $category_id = $_POST['category_id'];
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $updated_by = $_SESSION['user_id'];
        
        // Validate input
        if (empty($category_name)) {
            $message = 'Category name is required';
            $message_type = 'error';
        } else {
            // Update category
            $update_sql = "UPDATE expense_categories 
                          SET category_name = ?, description = ?, status = ?, updated_at = NOW(), updated_by = ?
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssii", $category_name, $description, $status, $updated_by, $category_id);
            
            if ($update_stmt->execute()) {
                $message = 'Category updated successfully';
                $message_type = 'success';
            } else {
                $message = 'Error updating category: ' . $conn->error;
                $message_type = 'error';
            }
        }
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // Check if category has expenses
    $check_expenses_sql = "SELECT COUNT(*) as count FROM expenses WHERE category_id = ? AND status != 'deleted'";
    $check_stmt = $conn->prepare($check_expenses_sql);
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $expense_count = $check_result->fetch_assoc()['count'];
    
    if ($expense_count > 0) {
        $message = 'Cannot delete category. It has ' . $expense_count . ' associated expenses.';
        $message_type = 'error';
    } else {
        // Soft delete category
        $delete_sql = "UPDATE expense_categories SET status = 'deleted', updated_at = NOW(), updated_by = ? WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $_SESSION['user_id'], $category_id);
        
        if ($delete_stmt->execute()) {
            $message = 'Category deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting category: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get all categories
$categories_sql = "SELECT ec.*, u.full_name as created_by_name 
                  FROM expense_categories ec 
                  LEFT JOIN users u ON ec.created_by = u.id 
                  WHERE ec.status != 'deleted' 
                  ORDER BY ec.category_name";
$categories_result = $conn->query($categories_sql);

// Get category for editing
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $category_id = $_GET['edit'];
    $edit_sql = "SELECT * FROM expense_categories WHERE id = ? AND status != 'deleted'";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $category_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_category = $edit_result->fetch_assoc();
}

// Get expense counts per category
$category_stats_sql = "SELECT ec.id, ec.category_name, 
                      COUNT(e.id) as expense_count,
                      COALESCE(SUM(e.amount), 0) as total_amount
                      FROM expense_categories ec
                      LEFT JOIN expenses e ON ec.id = e.category_id AND e.status != 'deleted'
                      WHERE ec.status != 'deleted'
                      GROUP BY ec.id, ec.category_name
                      ORDER BY ec.category_name";
$category_stats = $conn->query($category_stats_sql);
?>

<style>
    .expense-categories-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1a252f;
        margin-bottom: 10px;
    }
    
    .page-subtitle {
        color: #6c757d;
        font-size: 1rem;
    }
    
    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        border: none;
    }
    
    .card-header {
        background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 20px 25px;
        border: none;
    }
    
    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
    }
    
    .card-body {
        padding: 25px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
        display: block;
    }
    
    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
    
    .btn {
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-green) 0%, #16a34a 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
    }
    
    .btn-outline-primary {
        border: 2px solid var(--primary-green);
        color: var(--primary-green);
    }
    
    .btn-outline-primary:hover {
        background: var(--primary-green);
        color: white;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }
    
    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #495057;
        padding: 15px;
        border-bottom: 2px solid #dee2e6;
    }
    
    .table td {
        padding: 15px;
        vertical-align: middle;
        border-color: #f1f3f4;
    }
    
    .table tbody tr:hover {
        background-color: rgba(25, 135, 84, 0.05);
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-active {
        background: rgba(25, 135, 84, 0.1);
        color: var(--primary-green);
    }
    
    .status-inactive {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }
    
    .stats-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s;
    }
    
    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(25, 135, 84, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-green);
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    
    .stats-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1a252f;
        margin-bottom: 5px;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }
    
    .btn-edit {
        background: #0dcaf0;
    }
    
    .btn-delete {
        background: #dc3545;
    }
    
    .alert {
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: none;
    }
    
    .alert-success {
        background: rgba(25, 135, 84, 0.1);
        color: var(--primary-green);
        border-left: 4px solid var(--primary-green);
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border-left: 4px solid #dc3545;
    }
    
    .search-box {
        position: relative;
        margin-bottom: 20px;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 0.95rem;
    }
    
    .search-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .empty-state-title {
        color: #6c757d;
        margin-bottom: 10px;
    }
    
    @media (max-width: 768px) {
        .expense-categories-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            font-size: 0.9rem;
        }
    }
</style>

<div class="expense-categories-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">Expense Categories Management</h1>
        <p class="page-subtitle">Create and manage expense categories for better financial tracking</p>
    </div>
    
    <!-- Display Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Category Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="stats-value"><?php echo $categories_result->num_rows; ?></div>
                <div class="stats-label">Total Categories</div>
            </div>
        </div>
        <?php 
        $active_count = 0;
        $categories_result->data_seek(0);
        while($category = $categories_result->fetch_assoc()) {
            if ($category['status'] === 'active') $active_count++;
        }
        $categories_result->data_seek(0);
        ?>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-value"><?php echo $active_count; ?></div>
                <div class="stats-label">Active Categories</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stats-value">
                    <?php 
                    $total_expenses = 0;
                    if ($category_stats) {
                        while($stat = $category_stats->fetch_assoc()) {
                            $total_expenses += $stat['total_amount'];
                        }
                    }
                    echo '$' . number_format($total_expenses, 2);
                    ?>
                </div>
                <div class="stats-label">Total Expenses</div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Category Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas <?php echo $edit_category ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" 
                                   value="<?php echo $edit_category ? htmlspecialchars($edit_category['category_name']) : ''; ?>" 
                                   placeholder="Enter category name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo ($edit_category && $edit_category['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_category && $edit_category['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="3" placeholder="Enter category description"><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if ($edit_category): ?>
                        <button type="submit" name="update_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Category
                        </button>
                        <a href="expense_categories.php" class="btn btn-outline-primary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    <?php else: ?>
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Add Category
                        </button>
                        <button type="reset" class="btn btn-outline-primary">
                            <i class="fas fa-redo me-2"></i> Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Categories List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Expense Categories List
                </h5>
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search categories...">
                    <button class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($categories_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Expenses</th>
                                <th>Total Amount</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset category stats pointer
                            $category_stats->data_seek(0);
                            $stats_data = [];
                            while($stat = $category_stats->fetch_assoc()) {
                                $stats_data[$stat['id']] = $stat;
                            }
                            
                            while($category = $categories_result->fetch_assoc()): 
                                $stat = $stats_data[$category['id']] ?? ['expense_count' => 0, 'total_amount' => 0];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($category['description'] ?: 'No description'); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $category['status']; ?>">
                                        <i class="fas fa-circle me-1" style="font-size: 0.7rem;"></i>
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $stat['expense_count']; ?> expenses</span>
                                </td>
                                <td>
                                    <strong class="text-success">$<?php echo number_format($stat['total_amount'], 2); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($category['created_by_name'] ?: 'System'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $category['id']; ?>" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $category['id']; ?>" 
                                           class="action-btn btn-delete" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this category?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h4 class="empty-state-title">No Expense Categories Found</h4>
                    <p class="text-muted">Start by adding your first expense category using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Search functionality
        $('.search-input').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('table tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.includes(searchTerm));
            });
        });
        
        // Form validation
        $('form').on('submit', function(e) {
            const categoryName = $('#category_name').val().trim();
            if (!categoryName) {
                e.preventDefault();
                alert('Category name is required');
                $('#category_name').focus();
                return false;
            }
            return true;
        });
        
        // Auto-resize textarea
        $('#description').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Initialize tooltips
        $('[title]').tooltip({
            trigger: 'hover',
            placement: 'top'
        });
        
        // Add animation to table rows
        $('table tbody tr').each(function(index) {
            $(this).css('animation-delay', (index * 0.05) + 's');
        });
    });
</script>

<?php
require_once '../includes/footer.php';
?>