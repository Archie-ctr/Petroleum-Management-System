/**
 * God Willing Business Center - Petroleum Management System
 * Main JavaScript File
 */

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
    
    // Sales calculation
    $('body').on('change input', '.fuel-calculator', function() {
        calculateSaleTotal();
    });
    
    // Print receipt function
    window.printReceipt = function(saleId) {
        window.open('receipt.php?id=' + saleId, '_blank');
    };
    
    // Confirm before delete
    $('a[data-confirm]').on('click', function(e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });
    
    // Form validation
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Date picker initialization
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
    
    // Auto-generate sale number
    if ($('#sale_number').length) {
        $('#sale_number').val('SALE-' + new Date().getTime());
    }
    
    // Real-time clock
    function updateClock() {
        var now = new Date();
        var timeString = now.toLocaleTimeString();
        var dateString = now.toLocaleDateString();
        $('.real-time-clock').text(dateString + ' ' + timeString);
    }
    
    // Update clock every second
    setInterval(updateClock, 1000);
    updateClock();
});

// Calculate sale total
function calculateSaleTotal() {
    var quantity = parseFloat($('#quantity').val()) || 0;
    var price = parseFloat($('#price_per_liter').val()) || 0;
    var total = Math.round((quantity * price + Number.EPSILON) * 100) / 100;
    
    $('#total_amount').val('$' + total.toFixed(2));
    
    // Calculate tax if applicable
    if ($('#tax_rate').length) {
        var taxRate = parseFloat($('#tax_rate').val()) || 0;
        var taxAmount = total * (taxRate / 100);
        var grandTotal = total + taxAmount;
        
        $('#tax_amount').val('$' + taxAmount.toFixed(2));
        $('#grand_total').val('$' + grandTotal.toFixed(2));
    }
}

// Export to Excel
function exportToExcel(tableId, filename) {
    var table = document.getElementById(tableId);
    var workbook = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
    XLSX.writeFile(workbook, filename + '.xlsx');
}

// Export to PDF
function exportToPDF(tableId, filename) {
    var element = document.getElementById(tableId);
    var opt = {
        margin:       1,
        filename:     filename + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().from(element).set(opt).save();
}

// Load fuel price based on selection
function loadFuelPrice(fuelId) {
    $.ajax({
        url: 'api/get_fuel_price.php',
        type: 'POST',
        data: { fuel_id: fuelId },
        success: function(response) {
            if (response.success) {
                $('#price_per_liter').val(response.price);
                calculateSaleTotal();
            }
        }
    });
}

// Check tank capacity
function checkTankCapacity(tankId, quantity) {
    $.ajax({
        url: 'api/check_tank_capacity.php',
        type: 'POST',
        data: { tank_id: tankId, quantity: quantity },
        success: function(response) {
            if (!response.available) {
                alert('Insufficient fuel in tank! Available: ' + response.available_quantity + ' L');
                $('#quantity').val('');
            }
        }
    });
}

// Generate barcode
function generateBarcode(text, elementId) {
    JsBarcode('#' + elementId, text, {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 30,
        displayValue: true
    });
}

// Format currency
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format date
function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Show loading spinner
function showLoading() {
    $('#loading-spinner').fadeIn();
}

// Hide loading spinner
function hideLoading() {
    $('#loading-spinner').fadeOut();
}

// Toast notification
function showToast(type, message) {
    var toast = '<div class="toast align-items-center text-white bg-' + type + ' border-0" role="alert">';
    toast += '<div class="d-flex">';
    toast += '<div class="toast-body">' + message + '</div>';
    toast += '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>';
    toast += '</div></div>';
    
    $('#toast-container').append(toast);
    $('.toast').toast('show');
    
    setTimeout(function() {
        $('.toast').toast('hide');
    }, 3000);
}

// Initialize charts
function initializeSalesChart(canvasId, labels, data) {
    var ctx = document.getElementById(canvasId).getContext('2d');
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales ($)',
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    return chart;
}

// Initialize pie chart
function initializePieChart(canvasId, labels, data, colors) {
    var ctx = document.getElementById(canvasId).getContext('2d');
    var chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors
            }]
        },
        options: {
            responsive: true
        }
    });
    
    return chart;
}