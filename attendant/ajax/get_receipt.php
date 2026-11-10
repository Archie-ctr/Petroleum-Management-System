<?php
// ajax/get_receipt.php
session_start();
require_once '../../config/db.php';

$sale_id = $_GET['sale_id'] ?? 0;

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'attendant') {
    http_response_code(401);
    exit('Unauthorized');
}

// Get sale details
$sql = "SELECT 
        s.*,
        f.fuel_name,
        f.price_per_liter,
        b.branch_name,
        b.address AS branch_address,
        b.phone AS branch_phone,
        u.full_name as attendant_name
        FROM sales s
        JOIN fuel_types f ON s.fuel_type_id = f.id
        JOIN branches b ON s.branch_id = b.id
        JOIN users u ON s.attendant_id = u.id
        WHERE s.id = ? AND s.attendant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $sale_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$sale = $result->fetch_assoc();

if (!$sale) {
    echo '<div class="alert alert-danger">Receipt not found!</div>';
    exit;
}

// Format date and time
$sale_date = new DateTime($sale['sale_date']);
$date_str = $sale_date->format('d/m/Y');
$time_str = $sale_date->format('h:i A');

$unit_price = (float)($sale['unit_price'] ?? 0);
if ($unit_price <= 0) {
    $unit_price = (float)$sale['price_per_liter'];
}

$tax_result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1");
$tax_rate = (float)($tax_result->fetch_assoc()['setting_value'] ?? 0);
$subtotal = (float)$sale['total_amount'];
$tax_amount = round(($tax_rate / 100) * $subtotal, 2);
$grand_total = $subtotal + $tax_amount;

// Generate receipt HTML
$receipt = '
<div class="receipt">
    <!-- Header -->
    <div class="text-center mb-3">
        <h4 class="bold mb-1">' . htmlspecialchars($sale['branch_name']) . '</h4>
        <p class="mb-1 small">' . htmlspecialchars($sale['branch_address']) . '</p>
        <p class="mb-1 small">Tel: ' . htmlspecialchars($sale['branch_phone']) . '</p>
        <p class="mb-1 small">' . htmlspecialchars($_SESSION['company_name'] ?? 'Petroleum Station') . '</p>
    </div>
    
    <hr>
    
    <!-- Receipt Details -->
    <div class="mb-3">
        <div class="row mb-1">
            <div class="col-6"><strong>Receipt No:</strong></div>
            <div class="col-6 text-right">' . htmlspecialchars($sale['sale_number']) . '</div>
        </div>
        <div class="row mb-1">
            <div class="col-6"><strong>Date:</strong></div>
            <div class="col-6 text-right">' . $date_str . '</div>
        </div>
        <div class="row mb-1">
            <div class="col-6"><strong>Time:</strong></div>
            <div class="col-6 text-right">' . $time_str . '</div>
        </div>
        <div class="row mb-1">
            <div class="col-6"><strong>Attendant:</strong></div>
            <div class="col-6 text-right">' . htmlspecialchars($sale['attendant_name']) . '</div>
        </div>
    </div>
    
    <hr>
    
    <!-- Sale Items -->
    <div class="mb-3">
        <h6 class="bold mb-2">FUEL SALE</h6>
        <div class="row mb-2">
            <div class="col-8">
                <div><strong>' . htmlspecialchars($sale['fuel_name']) . '</strong></div>
                <div class="small">@ $' . number_format($unit_price, 2) . '/L</div>
            </div>
            <div class="col-4 text-right">
                <div><strong>' . number_format($sale['quantity'], 2) . ' L</strong></div>
                <div class="small">$' . number_format($sale['total_amount'], 2) . '</div>
            </div>
        </div>
    </div>
    
    <hr>
    
    <!-- Totals -->
    <div class="mb-3">
        <div class="row mb-1">
            <div class="col-6"><strong>Subtotal:</strong></div>
            <div class="col-6 text-right">$' . number_format($subtotal, 2) . '</div>
        </div>
        <div class="row mb-1">
            <div class="col-6"><strong>VAT (' . $tax_rate . '%):</strong></div>
            <div class="col-6 text-right">$' . number_format($tax_amount, 2) . '</div>
        </div>
        <div class="row mb-2 border-top pt-2">
            <div class="col-6"><strong>TOTAL:</strong></div>
            <div class="col-6 text-right"><strong>$' . number_format($grand_total, 2) . '</strong></div>
        </div>
    </div>
    
    <hr>
    
    <!-- Payment Info -->
    <div class="mb-3">
        <div class="row mb-1">
            <div class="col-6"><strong>Payment Method:</strong></div>
            <div class="col-6 text-right">
                <span class="badge bg-' . ($sale['payment_method'] == 'cash' ? 'success' : 'info') . '">
                    ' . strtoupper($sale['payment_method']) . '
                </span>
            </div>
        </div>
    </div>
    
    <hr>
    
    <!-- Footer -->
    <div class="text-center mt-4">
        <p class="small mb-1">*** THANK YOU FOR YOUR BUSINESS ***</p>
        <p class="small mb-1">Receipt is valid for tax purposes</p>
        <p class="small mb-1">Items cannot be returned or exchanged</p>
        <p class="small">For inquiries: ' . htmlspecialchars($sale['branch_phone']) . '</p>
    </div>
</div>';

echo $receipt;
?>