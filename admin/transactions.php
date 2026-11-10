<?php
session_start();
require_once '../config/db.php';

// Check if user has admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php?error=unauthorized');
    exit();
}

// Initialize variables
$search = '';
$filter_type = '';
$filter_status = '';
$date_from = '';
$date_to = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];
$param_types = '';

// Handle search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $where_conditions[] = "(t.description LIKE ? OR b.branch_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

// Handle filter by transaction type
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $filter_type = $_GET['type'];
    $where_conditions[] = "t.transaction_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

// Handle filter by status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter_status = $_GET['status'];
    $where_conditions[] = "t.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// Handle date range filter
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $date_from = $_GET['date_from'];
    $where_conditions[] = "t.transaction_date >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $date_to = $_GET['date_to'];
    $where_conditions[] = "t.transaction_date <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

// Build the WHERE clause
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM transactions t
              LEFT JOIN branches b ON t.branch_id = b.id
              $where_sql";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$stmt->close();
$filter_params = $params;
$filter_param_types = $param_types;

// Get transactions with pagination
$transactions_sql = "SELECT t.*, 
                     b.branch_name,
                     b.location
                     FROM transactions t
                     LEFT JOIN branches b ON t.branch_id = b.id
                     $where_sql
                     ORDER BY t.created_at DESC
                     LIMIT ? OFFSET ?";

$stmt = $conn->prepare($transactions_sql);

// Add pagination parameters
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$transactions_result = $stmt->get_result();
$transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_transactions,
              SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) as total_completed,
              SUM(CASE WHEN t.status = 'pending' THEN t.amount ELSE 0 END) as total_pending,
              SUM(CASE WHEN t.status = 'failed' THEN t.amount ELSE 0 END) as total_failed,
              AVG(t.amount) as avg_amount,
              COUNT(DISTINCT t.branch_id) as branches_involved
              FROM transactions t
              LEFT JOIN branches b ON t.branch_id = b.id";

if (!empty($where_sql)) {
    $stats_sql .= " " . $where_sql;
}

$stmt = $conn->prepare($stats_sql);
if (!empty($filter_params)) {
    $stmt->bind_param($filter_param_types, ...$filter_params);
}
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

// Get transaction types for filter dropdown
$types_sql = "SELECT DISTINCT transaction_type FROM transactions ORDER BY transaction_type";
$types_result = $conn->query($types_sql);
$transaction_types = $types_result->fetch_all(MYSQLI_ASSOC);

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    exportTransactionsToCSV($conn, $where_sql, $filter_params, $filter_param_types);
    exit();
}

require_once '../includes/header.php';

function exportTransactionsToCSV($conn, $where_sql, $params, $param_types) {
    $sql = "SELECT t.*, b.branch_name, b.location,
                   NULL AS user_name, NULL AS approved_by_name
            FROM transactions t
            LEFT JOIN branches b ON t.branch_id = b.id
            $where_sql
            ORDER BY t.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    exportToCSV($result, 'all_transactions.csv');
    $stmt->close();
}

function exportToCSV($result, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'Transaction ID',
        'Type',
        'Amount',
        'Currency',
        'Description',
        'Status',
        'Branch',
        'Transaction Date',
        'Created At'
    ]);
    
    // Data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['transaction_type'],
            $row['amount'],
            $row['currency'],
            $row['description'],
            $row['status'],
            $row['branch_name'],
            $row['transaction_date'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions Management - God Willing Business Center</title>
    
    <style>
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-green);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .table-card .card-header {
            background: linear-gradient(135deg, var(--primary-green), #198754);
            color: white;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-top: none;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.05);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-completed {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-failed {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .status-rejected {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        
        .page-link {
            color: var(--primary-green);
            border-color: #dee2e6;
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-2">Transactions Management</h1>
                <p class="text-muted mb-0">Manage and monitor all financial transactions</p>
            </div>
            <div>
                <a href="?export=csv<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>" 
                   class="btn btn-success">
                    <i class="fas fa-file-export me-2"></i> Export CSV
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
                    <div class="stat-label">Total Transactions</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_completed'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Completed</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></div>
                    <div class="stat-label">Pending Amount</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['avg_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Transaction</div>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="filters-card">
            <form method="GET" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search transactions..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($transaction_types as $type): ?>
                                <option value="<?php echo $type['transaction_type']; ?>" 
                                    <?php echo $filter_type === $type['transaction_type'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type['transaction_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" 
                               placeholder="From Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" 
                               placeholder="To Date">
                    </div>
                    <div class="col-12 d-flex justify-content-between mt-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <a href="transactions.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Bulk Actions -->
        <div class="mb-3 text-muted">
            <?php echo number_format($total_rows); ?> transactions found
        </div>
        
            <!-- Transactions Table -->
            <div class="table-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 text-white">All Transactions</h5>
                        <p class="text-white-50 mb-0 small">Showing <?php echo count($transactions); ?> of <?php echo number_format($total_rows); ?> transactions</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($transactions) > 0): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <strong>TRX-<?php echo str_pad((string)$transaction['id'], 6, '0', STR_PAD_LEFT); ?></strong><br>
                                            <small class="text-muted">ID: <?php echo (int)$transaction['id']; ?></small>
                                        </td>
                                        <td>
                                            <span class="type-badge">
                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($transaction['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float)$transaction['amount'], 2); ?></td>
                                        <td>
                                            <?php $description = (string)($transaction['description'] ?? ''); ?>
                                            <?php echo htmlspecialchars(substr($description, 0, 50), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (strlen($description) > 50): ?>...<?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['branch_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $status = (string)($transaction['status'] ?? '');
                                            $status_class = 'status-' . preg_replace('/[^a-z0-9_-]/i', '', $status);
                                            $status_label = ucfirst($status);
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars((string)($transaction['transaction_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
                                            <small class="text-muted"><?php echo !empty($transaction['created_at']) ? date('h:i A', strtotime($transaction['created_at'])) : ''; ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="viewTransaction(<?php echo $transaction['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Transactions Found</h5>
                                            <p class="text-muted">No transactions match your search criteria</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Transactions pagination">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="transactionDetails" style="white-space: pre-line;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printTransaction()">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const transactionRecords = <?php echo json_encode(
            $transactions,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ); ?>;

        function viewTransaction(id) {
            const transaction = transactionRecords.find(record => Number(record.id) === Number(id));
            if (!transaction) {
                return;
            }

            const details = [
                `Transaction: TRX-${String(transaction.id).padStart(6, '0')}`,
                `Type: ${transaction.transaction_type}`,
                `Amount: ${transaction.currency || 'USD'} ${Number(transaction.amount || 0).toFixed(2)}`,
                `Description: ${transaction.description || 'N/A'}`,
                `Branch: ${transaction.branch_name || 'N/A'}`,
                `Status: ${transaction.status || 'N/A'}`,
                `Transaction date: ${transaction.transaction_date || 'N/A'}`,
                `Created: ${transaction.created_at || 'N/A'}`
            ];
            document.getElementById('transactionDetails').textContent = details.join('\n');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('transactionModal')).show();
        }
        
        // Print transaction
        function printTransaction() {
            const printContent = document.getElementById('transactionDetails').innerText;
            const originalContent = document.body.innerHTML;

            document.body.innerHTML = `<pre>${printContent.replace(/[&<>]/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;'
            })[character])}</pre>`;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Auto-submit form when filters change (optional)
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                if (this.name !== 'search') {
                    document.getElementById('filterForm').submit();
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            // Esc to clear search
            if (e.key === 'Escape') {
                document.querySelector('input[name="search"]').value = '';
                document.getElementById('filterForm').submit();
            }
        });
    </script>
</body>
</html>

<?php
require_once '../includes/footer.php';
?>