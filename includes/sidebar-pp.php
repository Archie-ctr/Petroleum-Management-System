<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="branches.php">
                    <i class="fas fa-store me-2"></i>
                    Branches
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Users
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="fuel_types.php">
                    <i class="fas fa-gas-pump me-2"></i>
                    Fuel Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="tanks.php">
                    <i class="fas fa-oil-can me-2"></i>
                    Tanks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pumps.php">
                    <i class="fas fa-fire me-2"></i>
                    Pumps
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sales.php">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <hr>
        
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" 
               id="dropdownUser1" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle fa-2x me-2"></i>
                <strong><?php echo $_SESSION['full_name']; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                <li><a class="dropdown-item" href="#">
                    <i class="fas fa-user me-2"></i>Profile
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../includes/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Sign out
                </a></li>
            </ul>
        </div>
    </div>
</div>