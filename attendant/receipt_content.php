<?php
// receipt_content.php - Shared receipt content
?>
<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <h5 class="mb-1" style="font-weight: bold; font-size: 1.2em;">
            <?php echo $settings['company_name'] ?? 'God Willing Business Center'; ?>
        </h5>
        <p class="mb-1 small"><?php echo $sale['branch_name']; ?></p>
        <p class="mb-1 small"><?php echo $sale['branch_address']; ?></p>
        <p class="mb-1 small">Tel: <?php echo $sale['branch_phone']; ?></p>
        <p class="mb-0 small">Date: <?php echo date('Y-m-d', strtotime($sale['sale_date'])); ?></p>
        <p class="mb-0 small">Time: <?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></p>
    </div>
    
    <!-- Sale Details -->
    <div class="mb-3">
        <div class="item-row">
            <span>Receipt No:</span>
            <span><?php echo $sale['sale_number']; ?></span>
        </div>
        <div class="item-row">
            <span>Attendant:</span>
            <span><?php echo $sale['attendant_name']; ?></span>
        </div>
        <?php if (!empty($sale['customer_name'])): ?>
        <div class="item-row">
            <span>Customer:</span>
            <span><?php echo $sale['customer_name']; ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($sale['customer_phone'])): ?>
        <div class="item-row">
            <span>Customer No:</span>
            <span><?php echo $sale['customer_phone']; ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($sale['vehicle_number'])): ?>
        <div class="item-row">
            <span>Vehicle No:</span>
            <span><?php echo $sale['vehicle_number']; ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($sale['pump_number'])): ?>
        <div class="item-row">
            <span>Pump No:</span>
            <span><?php echo $sale['pump_number']; ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <hr>
    
    <!-- Items -->
    <div class="mb-3">
        <div class="item-row">
            <span>Item</span>
            <span>Amount</span>
        </div>
        <div class="item-row">
            <span><?php echo $sale['fuel_name']; ?></span>
            <span></span>
        </div>
        <div class="item-row">
            <span>Qty: <?php echo number_format($sale['quantity'], 2); ?> L</span>
            <span>$<?php echo number_format($sale['current_price'], 2); ?>/L</span>
        </div>
    </div>
    
    <hr>
    
    <!-- Totals -->
    <div>
        <div class="item-row">
            <span>Subtotal:</span>
            <span>$<?php echo number_format($sale['total_amount'], 2); ?></span>
        </div>
        
        <?php 
        // Calculate tax
        $tax_rate = isset($settings['tax_rate']) ? floatval($settings['tax_rate']) : 0;
        $tax_amount = $sale['total_amount'] * ($tax_rate / 100);
        $grand_total = $sale['total_amount'] + $tax_amount;
        
        if ($tax_rate > 0): 
        ?>
        <div class="item-row">
            <span>Tax (<?php echo $tax_rate; ?>%):</span>
            <span>$<?php echo number_format($tax_amount, 2); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="item-row total-row">
            <span><?php echo $tax_rate > 0 ? 'GRAND TOTAL:' : 'TOTAL:'; ?></span>
            <span>$<?php echo number_format($grand_total, 2); ?></span>
        </div>
        
        <div class="item-row">
            <span>Payment Method:</span>
            <span style="text-transform: uppercase;"><?php echo $sale['payment_method']; ?></span>
        </div>
        
        <?php if (!empty($sale['payment_reference'])): ?>
        <div class="item-row">
            <span>Reference:</span>
            <span><?php echo $sale['payment_reference']; ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Barcode -->
    <div class="barcode">
        <svg id="barcode"></svg>
    </div>
    
    <!-- Status Badge -->
    <?php if ($sale['status'] == 'voided'): ?>
    <div class="text-center mb-3">
        <span class="badge bg-danger" style="font-size: 1em; padding: 8px;">
            <i class="fas fa-ban me-1"></i> VOIDED
        </span>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="receipt-footer">
        <p class="mb-1"><?php echo $settings['receipt_footer'] ?? 'Thank you for your business!'; ?></p>
        <p class="mb-0">Powered by God Willing Business Center</p>
        <p class="mb-0">Receipt ID: <?php echo $sale['sale_number']; ?></p>
    </div>
</div>