<?php
// admin/help_docs.php
session_start();
require_once '../config/db.php';
require_once '../includes/security.php';

// Check if user is admin or has access
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'accountant', 'attendant'], true)) {
    header('Location: ../index.php');
    exit;
}

$csrf_token = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['help_error'] = 'Security validation failed. Refresh the page and try again.';
    } else {
        try {
            if (isset($_POST['helpful_document_id'])) {
                $document_id = filter_input(INPUT_POST, 'helpful_document_id', FILTER_VALIDATE_INT);
                if (!$document_id) {
                    throw new InvalidArgumentException('Invalid documentation article.');
                }
                $check = $conn->prepare("SELECT id FROM help_documents WHERE id = ? AND status = 'published'");
                $check->bind_param('i', $document_id);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    throw new RuntimeException('The article could not be found.');
                }
                $stmt = $conn->prepare(
                    "INSERT INTO help_document_feedback (document_id, user_id)
                     SELECT id, ? FROM help_documents WHERE id = ? AND status = 'published'
                     ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP"
                );
                $stmt->bind_param('ii', $_SESSION['user_id'], $document_id);
                $stmt->execute();
                $_SESSION['help_success'] = 'Thank you for your feedback.';
            } elseif (isset($_POST['request_topic'])) {
                $topic = trim((string)$_POST['request_topic']);
                if ($topic === '' || mb_strlen($topic) > 200) {
                    throw new InvalidArgumentException('Enter a topic of up to 200 characters.');
                }
                $stmt = $conn->prepare('INSERT INTO help_document_requests (topic, requested_by) VALUES (?, ?)');
                $stmt->bind_param('si', $topic, $_SESSION['user_id']);
                $stmt->execute();
                $_SESSION['help_success'] = 'Your documentation request has been submitted.';
            }
        } catch (Throwable $e) {
            logError('Help documentation action failed: ' . $e->getMessage());
            $_SESSION['help_error'] = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Unable to save your request. Please try again.';
        }
    }
    header('Location: help_docs.php');
    exit;
}

require_once '../includes/header.php';

// Handle search
$search = is_string($_GET['search'] ?? null) ? trim(substr($_GET['search'], 0, 100)) : '';
$category = max(0, (int)($_GET['category'] ?? 0));

// Build query
$query = "SELECT hd.*, hc.name as category_name 
          FROM help_documents hd 
          LEFT JOIN help_categories hc ON hd.category_id = hc.id 
          WHERE hd.status = 'published'";

$params = [];
$types = "";

if ($search) {
    $query .= " AND (hd.title LIKE ? OR hd.content LIKE ? OR hd.tags LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

if ($category > 0) {
    $query .= " AND hd.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

$query .= " ORDER BY 
            CASE hd.priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END, 
            hd.title ASC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all categories for filter
$categories_result = $conn->query("SELECT id, name FROM help_categories WHERE status = 'active' ORDER BY name");
?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-question-circle"></i> Help & Documentation
                </h5>
            </div>
            <div class="card-body">
                <?php foreach (['help_success' => 'success', 'help_error' => 'danger'] as $flash_key => $flash_type): ?>
                    <?php if (isset($_SESSION[$flash_key])): ?>
                        <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION[$flash_key], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION[$flash_key]); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <!-- Search and Filter -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="GET" action="" class="d-flex">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search documentation..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <select class="form-select" name="category" style="max-width: 200px;">
                                    <option value="0">All Categories</option>
                                    <?php while($cat = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($search || $category): ?>
                                <a href="help_docs.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickLinksModal">
                                <i class="fas fa-bolt"></i> Quick Links
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="printHelpPage()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($search): ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-search"></i> Showing results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <span class="badge bg-primary ms-2"><?php echo $result->num_rows; ?> articles found</span>
                </div>
                <?php endif; ?>

                <!-- Quick Help Grid -->
                <div class="row mb-5">
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="fas fa-gas-pump fa-3x text-primary"></i>
                                </div>
                                <h5>Fuel Management</h5>
                                <p class="text-muted small">Managing fuel types, prices, and inventory</p>
                                <a href="#fuel-management" class="btn btn-sm btn-outline-primary">View Guides</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="fas fa-shopping-cart fa-3x text-success"></i>
                                </div>
                                <h5>Sales & POS</h5>
                                <p class="text-muted small">Processing sales and managing transactions</p>
                                <a href="#sales-pos" class="btn btn-sm btn-outline-success">View Guides</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="fas fa-chart-bar fa-3x text-info"></i>
                                </div>
                                <h5>Reports</h5>
                                <p class="text-muted small">Generating reports and analytics</p>
                                <a href="#reports" class="btn btn-sm btn-outline-info">View Guides</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="fas fa-users-cog fa-3x text-warning"></i>
                                </div>
                                <h5>User Management</h5>
                                <p class="text-muted small">Managing users, roles, and permissions</p>
                                <a href="#user-management" class="btn btn-sm btn-outline-warning">View Guides</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentation Content -->
                <div class="row">
                    <div class="col-md-3">
                        <!-- Table of Contents -->
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-list"></i> Table of Contents</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <a href="#getting-started" class="list-group-item list-group-item-action">
                                        <i class="fas fa-play-circle me-2"></i> Getting Started
                                    </a>
                                    <a href="#fuel-management" class="list-group-item list-group-item-action">
                                        <i class="fas fa-gas-pump me-2"></i> Fuel Management
                                    </a>
                                    <a href="#sales-pos" class="list-group-item list-group-item-action">
                                        <i class="fas fa-cash-register me-2"></i> Sales & POS
                                    </a>
                                    <a href="#inventory" class="list-group-item list-group-item-action">
                                        <i class="fas fa-boxes me-2"></i> Inventory Management
                                    </a>
                                    <a href="#reports" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-line me-2"></i> Reports & Analytics
                                    </a>
                                    <a href="#user-management" class="list-group-item list-group-item-action">
                                        <i class="fas fa-users me-2"></i> User Management
                                    </a>
                                    <a href="#system" class="list-group-item list-group-item-action">
                                        <i class="fas fa-cog me-2"></i> System Settings
                                    </a>
                                    <a href="#troubleshooting" class="list-group-item list-group-item-action">
                                        <i class="fas fa-wrench me-2"></i> Troubleshooting
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <!-- Search Results or Default Content -->
                        <?php if ($result->num_rows > 0): ?>
                            <!-- Search Results -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-search me-2"></i> Search Results</h6>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="searchResultsAccordion">
                                        <?php $count = 0; while($doc = $result->fetch_assoc()): $count++; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?php echo $count > 1 ? 'collapsed' : ''; ?>" 
                                                        type="button" data-bs-toggle="collapse" 
                                                        data-bs-target="#collapse<?php echo $doc['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                                    <span class="badge ms-2 
                                                        <?php echo $doc['priority'] == 'high' ? 'bg-danger' : 
                                                              ($doc['priority'] == 'medium' ? 'bg-warning' : 'bg-info'); ?>">
                                                        <?php echo ucfirst($doc['priority']); ?>
                                                    </span>
                                                    <small class="text-muted ms-2">
                                                        <i class="fas fa-folder"></i> <?php echo $doc['category_name']; ?>
                                                    </small>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $doc['id']; ?>" 
                                                 class="accordion-collapse collapse <?php echo $count == 1 ? 'show' : ''; ?>" 
                                                 data-bs-parent="#searchResultsAccordion">
                                                <div class="accordion-body">
                                                    <?php echo $doc['content']; ?>
                                                    <?php if ($doc['tags']): ?>
                                                    <div class="mt-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-tags"></i> Tags: 
                                                            <?php echo htmlspecialchars($doc['tags']); ?>
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="mt-3 d-flex justify-content-between">
                                                        <small class="text-muted">
                                                            Last updated: <?php echo date('M d, Y', strtotime($doc['updated_at'])); ?>
                                                        </small>
                                                        <div>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="helpful_document_id" value="<?php echo (int)$doc['id']; ?>">
                                                                <button class="btn btn-sm btn-outline-primary" type="submit">
                                                            <i class="fas fa-thumbs-up"></i> Helpful
                                                                </button>
                                                            </form>
                                                            <button class="btn btn-sm btn-outline-secondary" 
                                                            onclick="printArticle(<?php echo (int)$doc['id']; ?>)">
                                                                <i class="fas fa-print"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Default Documentation Content -->
                            <div id="getting-started" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i> Getting Started</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Welcome to God Willing Petroleum Management System</h6>
                                    <p>This system helps you manage your petroleum business efficiently. Follow these steps to get started:</p>
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-user-plus text-primary me-2"></i> Step 1: Add Users</h6>
                                                    <p>Create user accounts for your team members with appropriate roles:</p>
                                                    <ul>
                                                        <li><strong>Admin</strong>: Full system access</li>
                                                        <li><strong>Manager</strong>: Branch management</li>
                                                        <li><strong>Attendant</strong>: Sales processing</li>
                                                        <li><strong>Accountant</strong>: Financial reporting</li>
                                                    </ul>
                                                    <a href="users.php" class="btn btn-sm btn-primary">Go to Users</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-gas-pump text-success me-2"></i> Step 2: Setup Fuel Types</h6>
                                                    <p>Define the fuel products you sell:</p>
                                                    <ul>
                                                        <li>Petrol Super</li>
                                                        <li>Diesel</li>
                                                        <li>Kerosene</li>
                                                        <li>Other petroleum products</li>
                                                    </ul>
                                                    <a href="fuel_types.php" class="btn btn-sm btn-success">Go to Fuel Types</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-oil-can text-info me-2"></i> Step 3: Add Tanks</h6>
                                                    <p>Configure your fuel storage tanks:</p>
                                                    <ul>
                                                        <li>Set tank capacities</li>
                                                        <li>Define minimum thresholds</li>
                                                        <li>Connect to branches</li>
                                                    </ul>
                                                    <a href="tanks.php" class="btn btn-sm btn-info">Go to Tanks</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-cash-register text-warning me-2"></i> Step 4: Setup Pumps</h6>
                                                    <p>Configure fuel dispensing pumps:</p>
                                                    <ul>
                                                        <li>Connect pumps to tanks</li>
                                                        <li>Set pump numbers and names</li>
                                                        <li>Configure initial readings</li>
                                                    </ul>
                                                    <a href="pumps.php" class="btn btn-sm btn-warning">Go to Pumps</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fuel Management Section -->
                            <div id="fuel-management" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-gas-pump me-2"></i> Fuel Management</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Managing Fuel Products</h6>
                                    <p>Learn how to manage your fuel inventory and prices.</p>
                                    
                                    <div class="accordion mb-3">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFuelTypes">
                                                    Adding New Fuel Types
                                                </button>
                                            </h2>
                                            <div id="collapseFuelTypes" class="accordion-collapse collapse show">
                                                <div class="accordion-body">
                                                    <ol>
                                                        <li>Go to <strong>Fuel Types</strong> in the admin menu</li>
                                                        <li>Click <strong>Add New Fuel Type</strong></li>
                                                        <li>Enter fuel details:
                                                            <ul>
                                                                <li>Fuel Name (e.g., "Petrol Super")</li>
                                                                <li>Fuel Code (e.g., "PS")</li>
                                                                <li>Price per Liter</li>
                                                                <li>Unit of measurement</li>
                                                            </ul>
                                                        </li>
                                                        <li>Click <strong>Save</strong></li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePriceUpdate">
                                                    Updating Fuel Prices
                                                </button>
                                            </h2>
                                            <div id="collapsePriceUpdate" class="accordion-collapse collapse">
                                                <div class="accordion-body">
                                                    <p>To update fuel prices:</p>
                                                    <ol>
                                                        <li>Navigate to <strong>Fuel Types</strong></li>
                                                        <li>Click the <strong>Edit</strong> button next to the fuel type</li>
                                                        <li>Update the <strong>Price per Liter</strong> field</li>
                                                        <li>Click <strong>Update</strong> to save changes</li>
                                                        <li>The new prices will be reflected in all sales</li>
                                                    </ol>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i> Price changes only affect new sales. Existing sales retain their original prices.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales & POS Section -->
                            <div id="sales-pos" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-cash-register me-2"></i> Sales & Point of Sale</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Processing Fuel Sales</h6>
                                    <p>Step-by-step guide to processing fuel sales through the POS system.</p>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-list-ol text-primary me-2"></i> Sales Process</h6>
                                                    <ol>
                                                        <li>Select the pump</li>
                                                        <li>Choose fuel type</li>
                                                        <li>Enter quantity or amount</li>
                                                        <li>Select payment method</li>
                                                        <li>Enter customer details (optional)</li>
                                                        <li>Process payment</li>
                                                        <li>Print receipt</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-credit-card text-success me-2"></i> Payment Methods</h6>
                                                    <ul>
                                                        <li><strong>Cash</strong>: Immediate payment</li>
                                                        <li><strong>Card</strong>: Credit/Debit card</li>
                                                        <li><strong>Mobile Money</strong>: MTN, Vodafone, Airtel</li>
                                                        <li><strong>Credit</strong>: Approved credit sales</li>
                                                    </ul>
                                                    <div class="alert alert-warning mt-2">
                                                        <small><i class="fas fa-exclamation-triangle"></i> Credit sales require manager approval</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ Section -->
                            <div id="troubleshooting" class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-wrench me-2"></i> Frequently Asked Questions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="faqAccordion">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                                    How do I reset a user's password?
                                                </button>
                                            </h2>
                                            <div id="faq1" class="accordion-collapse collapse show">
                                                <div class="accordion-body">
                                                    <p>Only administrators can reset passwords:</p>
                                                    <ol>
                                                        <li>Go to <strong>Users</strong> page</li>
                                                        <li>Find the user and click <strong>Reset Password</strong></li>
                                                        <li>Enter a new temporary password</li>
                                                        <li>The user will be prompted to change it on next login</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                                    How do I generate sales reports?
                                                </button>
                                            </h2>
                                            <div id="faq2" class="accordion-collapse collapse">
                                                <div class="accordion-body">
                                                    <p>To generate sales reports:</p>
                                                    <ol>
                                                        <li>Navigate to <strong>Reports</strong></li>
                                                        <li>Select report type (Daily, Weekly, Monthly)</li>
                                                        <li>Choose date range</li>
                                                        <li>Select branch (or All Branches)</li>
                                                        <li>Click <strong>Generate Report</strong></li>
                                                        <li>You can export as PDF, Excel, or print</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                                    What should I do if a pump shows incorrect readings?
                                                </button>
                                            </h2>
                                            <div id="faq3" class="accordion-collapse collapse">
                                                <div class="accordion-body">
                                                    <p>If pump readings are incorrect:</p>
                                                    <ol>
                                                        <li>Go to <strong>Pumps</strong> page</li>
                                                        <li>Find the problematic pump</li>
                                                        <li>Click <strong>Update Reading</strong></li>
                                                        <li>Enter the correct current reading</li>
                                                        <li>Set pump status to <strong>Maintenance</strong> if needed</li>
                                                        <li>Contact technical support if issue persists</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                                    How do I add a new branch?
                                                </button>
                                            </h2>
                                            <div id="faq4" class="accordion-collapse collapse">
                                                <div class="accordion-body">
                                                    <p>To add a new branch (Admin only):</p>
                                                    <ol>
                                                        <li>Go to <strong>Branches</strong> page</li>
                                                        <li>Click <strong>Add New Branch</strong></li>
                                                        <li>Fill in branch details:
                                                            <ul>
                                                                <li>Branch Name</li>
                                                                <li>Location</li>
                                                                <li>Address</li>
                                                                <li>Contact Information</li>
                                                                <li>Manager (assign from existing users)</li>
                                                            </ul>
                                                        </li>
                                                        <li>Click <strong>Save</strong></li>
                                                        <li>Setup tanks and pumps for the new branch</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Contact Support -->
                        <div class="card mt-4">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-headset me-2"></i> Need More Help?</h5>
                                <p class="mb-3">Can't find what you're looking for? Contact our support team.</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="fas fa-phone fa-2x text-primary mb-2"></i>
                                            <strong>Phone Support</strong>
                                            <small>+231 77 123 4567</small>
                                            <small>Mon-Fri, 8AM-5PM</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                                            <strong>Email Support</strong>
                                            <small>support@godwilling.com</small>
                                            <small>24/7 Response</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="fas fa-comments fa-2x text-info mb-2"></i>
                                            <strong>Live Chat</strong>
                                            <small>Available Online</small>
                                            <small>Quick Responses</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="mailto:support@godwilling.com" class="btn btn-primary me-2">
                                        <i class="fas fa-paper-plane"></i> Send Message
                                    </a>
                                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#requestGuideModal">
                                        <i class="fas fa-plus"></i> Request New Guide
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links Modal -->
<div class="modal fade" id="quickLinksModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bolt me-2"></i> Quick Links</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6><i class="fas fa-video text-primary me-2"></i> Video Tutorials</h6>
                                <ul class="list-unstyled">
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-play-circle me-2"></i> Getting Started Guide</a></li>
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-play-circle me-2"></i> Sales Processing</a></li>
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-play-circle me-2"></i> Report Generation</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6><i class="fas fa-download text-success me-2"></i> Downloads</h6>
                                <ul class="list-unstyled">
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-file-pdf me-2"></i> User Manual (PDF)</a></li>
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-file-excel me-2"></i> Report Templates</a></li>
                                    <li><a href="#" class="text-decoration-none"><i class="fas fa-file-word me-2"></i> SOP Documents</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6><i class="fas fa-external-link-alt text-info me-2"></i> External Resources</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <a href="https://www.petrol.com" target="_blank" class="text-decoration-none d-block mb-2">
                                            <i class="fas fa-globe me-2"></i> Petroleum Industry News
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="https://www.safety.gov" target="_blank" class="text-decoration-none d-block mb-2">
                                            <i class="fas fa-shield-alt me-2"></i> Safety Guidelines
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="https://www.training.petrol" target="_blank" class="text-decoration-none d-block mb-2">
                                            <i class="fas fa-graduation-cap me-2"></i> Training Resources
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestGuideModal" tabindex="-1" aria-labelledby="requestGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestGuideModalLabel">Request a guide</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="requestTopic" class="form-label">What topic would you like documented?</label>
                <input id="requestTopic" name="request_topic" class="form-control" maxlength="200" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit request</button>
            </div>
        </form>
    </div>
</div>

<script>
function printHelpPage() {
    window.print();
}

function printArticle(id) {
    const article = document.querySelector(`#collapse${id} .accordion-body`);
    if (!article) {
        window.print();
        return;
    }
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        window.print();
        return;
    }
    printWindow.document.write('<!doctype html><html><head><title>Help article</title><style>body{font:16px/1.5 sans-serif;max-width:800px;margin:40px auto;padding:0 20px}</style></head><body></body></html>');
    printWindow.document.body.innerHTML = article.innerHTML;
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}

// Smooth scroll for table of contents links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if(targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if(targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 20,
                behavior: 'smooth'
            });
        }
    });
});

// Auto-expand search results when only one result
<?php if ($result->num_rows === 1): ?>
document.addEventListener('DOMContentLoaded', function() {
    const accordionButton = document.querySelector('.accordion-button');
    if(accordionButton) {
        accordionButton.click();
    }
});
<?php endif; ?>
</script>

<style>
@media print {
    .no-print, .card-header button, .modal, .btn, .input-group, .sticky-top {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .accordion-button::after {
        display: none !important;
    }
    
    .accordion-button:not(.collapsed) {
        color: #000 !important;
        background-color: #fff !important;
    }
    
    .accordion-collapse {
        display: block !important;
    }
}

.sticky-top {
    z-index: 100;
}

.accordion-button:focus {
    box-shadow: none;
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #0d6efd;
}

.list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<?php
require_once '../includes/footer.php';
?>