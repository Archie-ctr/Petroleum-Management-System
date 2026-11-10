<?php
// tanks.php
require_once 'attendant_header.php';
require_once 'attendant_sidebar.php';

$branch_id = $_SESSION['branch_id'];
?>

<style>
    .tank-summary-grid > [class*="col-"] {
        display: flex;
    }

    .tank-stat-card {
        --tank-stat-color: #145da0;
        position: relative;
        width: 100%;
        min-height: 132px;
        overflow: hidden;
        border: 1px solid color-mix(in srgb, var(--tank-stat-color) 20%, white);
        border-radius: 14px;
        background: linear-gradient(145deg, #fff 0%, color-mix(in srgb, var(--tank-stat-color) 7%, white) 100%);
        color: #17231c;
        box-shadow: 0 5px 16px rgba(20, 45, 30, 0.07);
    }

    .tank-stat-card::after {
        position: absolute;
        right: -24px;
        bottom: -34px;
        width: 104px;
        height: 104px;
        border: 16px solid color-mix(in srgb, var(--tank-stat-color) 8%, transparent);
        border-radius: 50%;
        content: "";
        pointer-events: none;
    }

    .tank-stat-primary { --tank-stat-color: #1769aa; }
    .tank-stat-capacity { --tank-stat-color: #16804a; }
    .tank-stat-stock { --tank-stat-color: #087e8b; }
    .tank-stat-alert { --tank-stat-color: #b54708; }

    .tank-stat-card .card-body {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.55rem;
        padding: 1rem;
    }

    .tank-stat-copy {
        min-width: 0;
    }

    .tank-stat-label {
        margin: 0 0 0.55rem;
        color: #52635a;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.3;
    }

    .tank-stat-value {
        display: block;
        color: var(--tank-stat-color);
        font-size: clamp(1.25rem, 2vw, 1.75rem);
        font-weight: 800;
        letter-spacing: -0.035em;
        line-height: 1.1;
        white-space: nowrap;
    }

    .tank-stat-note {
        display: block;
        margin-top: 0.45rem;
        color: #697970;
        font-size: 0.72rem;
        line-height: 1.3;
    }

    .tank-stat-icon {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: color-mix(in srgb, var(--tank-stat-color) 11%, white);
        color: var(--tank-stat-color);
        font-size: 1.1rem;
    }

    @media (max-width: 767px) {
        #mainContentWrapper {
            width: 100%;
            margin-left: 0 !important;
            transition: none !important;
        }
    }

    @media (max-width: 440px) {
        .tank-stat-card { min-height: 118px; border-radius: 12px; }
        .tank-stat-card .card-body { gap: 0.35rem; padding: 0.65rem; }
        .tank-stat-label { margin-bottom: 0.4rem; font-size: 0.75rem; }
        .tank-stat-value { font-size: clamp(0.95rem, 4.5vw, 1.15rem); }
        .tank-stat-note { font-size: 0.66rem; }
        .tank-stat-icon { display: none; }
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-oil-can"></i> Tank Inventory
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshTankData()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addRefuelModal">
                            <i class="fas fa-gas-pump"></i> Record Refuel
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Tank Summary -->
                <div class="row g-3 mb-4 tank-summary-grid">
                    <div class="col-6 col-xl-3">
                        <div class="card tank-stat-card tank-stat-primary">
                            <div class="card-body">
                                <div class="tank-stat-copy">
                                    <h6 class="tank-stat-label">Total Tanks</h6>
                                    <span class="tank-stat-value" id="totalTanks">0</span>
                                    <small class="tank-stat-note">Active at this branch</small>
                                </div>
                                <span class="tank-stat-icon"><i class="fas fa-oil-can" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card tank-stat-card tank-stat-capacity">
                            <div class="card-body">
                                <div class="tank-stat-copy">
                                    <h6 class="tank-stat-label">Total Capacity</h6>
                                    <span class="tank-stat-value" id="totalCapacity">0 L</span>
                                    <small class="tank-stat-note">Combined tank capacity</small>
                                </div>
                                <span class="tank-stat-icon"><i class="fas fa-weight-hanging" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card tank-stat-card tank-stat-stock">
                            <div class="card-body">
                                <div class="tank-stat-copy">
                                    <h6 class="tank-stat-label">Current Stock</h6>
                                    <span class="tank-stat-value" id="currentStock">0 L</span>
                                    <small class="tank-stat-note">Fuel currently available</small>
                                </div>
                                <span class="tank-stat-icon"><i class="fas fa-gas-pump" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card tank-stat-card tank-stat-alert">
                            <div class="card-body">
                                <div class="tank-stat-copy">
                                    <h6 class="tank-stat-label">Low Stock Alerts</h6>
                                    <span class="tank-stat-value" id="lowStockAlerts">0</span>
                                    <small class="tank-stat-note">Tanks below minimum level</small>
                                </div>
                                <span class="tank-stat-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tank Cards -->
                <div class="row" id="tankCards">
                    <!-- Tank cards will be loaded here via AJAX -->
                    <div class="col-md-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading tanks...</span>
                        </div>
                    </div>
                </div>

                <!-- Tank Details Table -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Tank Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tankTable" data-datatable-lazy="true">
                                <thead>
                                    <tr>
                                        <th>Tank No.</th>
                                        <th>Fuel Type</th>
                                        <th>Capacity</th>
                                        <th>Current Level</th>
                                        <th>Percentage</th>
                                        <th>Min. Threshold</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Refuel Modal -->
<div class="modal fade" id="addRefuelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Fuel Refill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="refuelForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="refuelTank" class="form-label">Select Tank *</label>
                        <select class="form-select" id="refuelTank" required>
                            <option value="">Select Tank</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currentLevel" class="form-label">Current Level (L)</label>
                                <input type="number" class="form-control" id="currentLevel" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tankCapacity" class="form-label">Tank Capacity (L)</label>
                                <input type="number" class="form-control" id="tankCapacity" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="refuelAmount" class="form-label">Refuel Amount (L) *</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="refuelAmount" 
                                   step="0.01" min="0" required>
                            <span class="input-group-text">Liters</span>
                        </div>
                        <div class="form-text">
                            Available space: <span id="availableSpace">0.00</span> L
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="refuelCost" class="form-label">Refuel Cost ($)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="refuelCost" 
                                   step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="refuelNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="refuelNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Refuel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tank Details Modal -->
<div class="modal fade" id="tankDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tank Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="tankDetailsContent">
                <!-- Tank details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadTankData();
    loadTankOptions();
    
    // Tank selection change handler
    $('#refuelTank').change(function() {
        const tankId = $(this).val();
        if (!tankId) return;
        
        $.ajax({
            url: 'ajax/get_tank_details.php',
            method: 'GET',
            data: { tank_id: tankId },
            success: function(data) {
                if (data.success) {
                    $('#currentLevel').val(data.current_quantity);
                    $('#tankCapacity').val(data.capacity);
                    
                    const availableSpace = data.capacity - data.current_quantity;
                    $('#availableSpace').text(availableSpace.toFixed(2));
                    
                    $('#refuelAmount').attr('max', availableSpace);
                    $('#refuelAmount').val('');
                } else {
                    showNotification(data.message || 'Failed to load tank details', 'error');
                }
            },
            error: function() {
                showNotification('Failed to load tank details', 'error');
            }
        });
    });
    
    // Refuel form submission
    $('#refuelForm').submit(function(e) {
        e.preventDefault();
        
        const formData = {
            tank_id: $('#refuelTank').val(),
            refuel_amount: $('#refuelAmount').val(),
            refuel_cost: $('#refuelCost').val(),
            notes: $('#refuelNotes').val(),
            attendant_id: <?php echo $_SESSION['user_id']; ?>
        };
        
        $.ajax({
            url: 'ajax/record_refuel.php',
            method: 'POST',
            data: formData,
            success: function(data) {
                if (data.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('addRefuelModal')).hide();
                    $('#refuelForm')[0].reset();
                    showNotification('Refuel recorded successfully!', 'success');
                    loadTankData();
                } else {
                    showNotification(data.message || 'Error recording refuel', 'error');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to record refuel';
                showNotification(message, 'error');
            }
        });
    });
    
    // Auto-refresh every 2 minutes
    setInterval(loadTankData, 120000);
});

function loadTankData() {
    $.ajax({
        url: 'ajax/get_tanks.php',
        method: 'GET',
        success: function(data) {
            if (data.success) {
                updateSummary(data.summary);
                displayTankCards(data.tanks);
                updateTankTable(data.tanks);
            } else {
                showNotification(data.message || 'Failed to load tank data', 'error');
            }
        },
        error: function() {
            showNotification('Failed to load tank data', 'error');
        }
    });
}

function loadTankOptions() {
    $.ajax({
        url: 'ajax/get_tank_options.php',
        method: 'GET',
        success: function(data) {
            const select = $('#refuelTank');
            select.empty();
            select.append('<option value="">Select Tank</option>');
            
            data.forEach(tank => {
                select.append(new Option(
                    `Tank ${tank.tank_number} - ${tank.fuel_name} (${tank.current_quantity}L / ${tank.capacity}L)`,
                    tank.id
                ));
            });
        },
        error: function() {
            showNotification('Failed to load tanks for refuel', 'error');
        }
    });
}

function updateSummary(summary) {
    $('#totalTanks').text(summary.total_tanks);
    const formatLiters = value => new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2
    }).format(Number(value) || 0) + ' L';
    $('#totalCapacity').text(formatLiters(summary.total_capacity));
    $('#currentStock').text(formatLiters(summary.current_stock));
    $('#lowStockAlerts').text(summary.low_stock_alerts);
}

function displayTankCards(tanks) {
    const container = $('#tankCards');
    container.empty();
    
    if (tanks.length === 0) {
        container.html(`
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No tanks found for this branch.
                </div>
            </div>
        `);
        return;
    }
    
    tanks.forEach(tank => {
        const percentage = (tank.current_quantity / tank.capacity) * 100;
        let statusClass = 'success';
        let statusText = 'Good';
        
        if (percentage < 20) {
            statusClass = 'danger';
            statusText = 'Critical';
        } else if (tank.current_quantity < tank.minimum_threshold) {
            statusClass = 'warning';
            statusText = 'Low';
        }
        
        const card = `
            <div class="col-md-4 mb-3">
                <div class="card tank-card" data-tank-id="${tank.id}" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="card-title mb-1">Tank ${tank.tank_number}</h6>
                                <p class="card-text text-muted small mb-0">${tank.fuel_name}</p>
                            </div>
                            <span class="badge bg-${statusClass}">${statusText}</span>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Current Level</span>
                                <span class="small">${percentage.toFixed(1)}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-${statusClass}" 
                                     role="progressbar" 
                                     style="width: ${percentage}%"
                                     aria-valuenow="${percentage}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <small class="text-muted d-block">Current</small>
                                    <h6 class="mb-0">${tank.current_quantity.toFixed(2)}L</h6>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <small class="text-muted d-block">Capacity</small>
                                    <h6 class="mb-0">${tank.capacity.toFixed(2)}L</h6>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="small text-muted">
                            <div class="d-flex justify-content-between">
                                <span>Min. Threshold:</span>
                                <span>${tank.minimum_threshold.toFixed(2)}L</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Last Updated:</span>
                                <span>${tank.last_refill ? new Date(tank.last_refill).toLocaleTimeString() : 'Not recorded'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.append(card);
    });
    
    // Add click handlers to tank cards
    $('.tank-card').click(function() {
        const tankId = $(this).data('tank-id');
        showTankDetails(tankId);
    });
}

function updateTankTable(tanks) {
    const table = $('#tankTable');
    if ($.fn.DataTable.isDataTable(table)) {
        table.DataTable().clear().destroy();
    }

    const tbody = table.find('tbody');
    tbody.empty();
    
    tanks.forEach(tank => {
        const percentage = (tank.current_quantity / tank.capacity) * 100;
        let statusClass = 'success';
        let statusText = 'Good';
        
        if (percentage < 20) {
            statusClass = 'danger';
            statusText = 'Critical';
        } else if (tank.current_quantity < tank.minimum_threshold) {
            statusClass = 'warning';
            statusText = 'Low';
        }
        
        const row = `
            <tr data-tank-id="${tank.id}" style="cursor: pointer;">
                <td>${tank.tank_number}</td>
                <td>${tank.fuel_name}</td>
                <td>${tank.capacity.toFixed(2)} L</td>
                <td>${tank.current_quantity.toFixed(2)} L</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                            <div class="progress-bar bg-${statusClass}" 
                                 style="width: ${percentage}%"></div>
                        </div>
                        <span>${percentage.toFixed(1)}%</span>
                    </div>
                </td>
                <td>${tank.minimum_threshold.toFixed(2)} L</td>
                <td>
                    <span class="badge bg-${statusClass}">${statusText}</span>
                </td>
                <td>${tank.last_refill ? new Date(tank.last_refill).toLocaleString() : 'Not recorded'}</td>
            </tr>
        `;
        
        tbody.append(row);
    });
    
    table.DataTable({
        responsive: true,
        pageLength: 10,
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search...',
            emptyTable: 'No tank data available'
        }
    });

    tbody.off('click', 'tr').on('click', 'tr', function() {
        const tankId = $(this).data('tank-id');
        showTankDetails(tankId);
    });
}

function showTankDetails(tankId) {
    $.ajax({
        url: 'ajax/get_tank_details.php',
        method: 'GET',
        data: { tank_id: tankId },
        success: function(data) {
            if (data.success) {
                $('#tankDetailsContent').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Tank Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Tank Number:</th>
                                    <td>${data.tank.tank_number}</td>
                                </tr>
                                <tr>
                                    <th>Fuel Type:</th>
                                    <td>${data.tank.fuel_name}</td>
                                </tr>
                                <tr>
                                    <th>Capacity:</th>
                                    <td>${data.tank.capacity.toFixed(2)} L</td>
                                </tr>
                                <tr>
                                    <th>Current Level:</th>
                                    <td>${data.tank.current_quantity.toFixed(2)} L</td>
                                </tr>
                                <tr>
                                    <th>Available Space:</th>
                                    <td>${(data.tank.capacity - data.tank.current_quantity).toFixed(2)} L</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Status</h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Fill Level</span>
                                    <span>${data.tank.percentage.toFixed(1)}%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-${data.tank.status_class}" 
                                         style="width: ${data.tank.percentage}%">
                                        ${data.tank.percentage.toFixed(1)}%
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-${data.tank.status_class}">
                                <i class="fas fa-${data.tank.status_icon}"></i>
                                ${data.tank.status_text}
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-4">Recent Activity</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Quantity (L)</th>
                                    <th>Attendant</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.activity.map(act => `
                                    <tr>
                                        <td>${new Date(act.timestamp).toLocaleString()}</td>
                                        <td><span class="badge ${act.type === 'refuel' ? 'bg-success' : 'bg-primary'}">${act.type}</span></td>
                                        <td>${act.quantity.toFixed(2)}</td>
                                        <td>${act.attendant_name || '-'}</td>
                                        <td>${act.notes || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `);
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('tankDetailsModal')).show();
            } else {
                showNotification(data.message || 'Failed to load tank details', 'error');
            }
        },
        error: function(xhr) {
            showNotification(xhr.responseJSON?.message || 'Failed to load tank details', 'error');
        }
    });
}

function refreshTankData() {
    showNotification('Refreshing tank data...', 'info');
    loadTankData();
}

function showNotification(message, type) {
    const alert = $(`
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100;">
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(alert);
    
    const toast = new bootstrap.Toast(alert.find('.toast')[0]);
    toast.show();
    
    alert.find('.toast').on('hidden.bs.toast', function() {
        alert.remove();
    });
}
</script>

<?php
require_once 'attendant_footer.php';
?>