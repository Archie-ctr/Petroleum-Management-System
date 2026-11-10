<?php
// Only close body and html tags if they were opened in the header
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'manager'):
?>
    </div> <!-- Close manager-content-wrapper -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <?php
    // Show session messages if any
    if (isset($_SESSION['success_message'])): 
    ?>
    <script>
        $(document).ready(function() {
            showNotification('<?php echo $_SESSION['success_message']; ?>', 'success');
        });
    </script>
    <?php 
        unset($_SESSION['success_message']);
    endif;
    
    if (isset($_SESSION['error_message'])): 
    ?>
    <script>
        $(document).ready(function() {
            showNotification('<?php echo $_SESSION['error_message']; ?>', 'error');
        });
    </script>
    <?php 
        unset($_SESSION['error_message']);
    endif;
    ?>
    
</body>
</html>
<?php
else:
// For non-manager pages (like login, public pages)
?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Show session messages if any
    if (isset($_SESSION['success_message'])): 
    ?>
    <script>
        $(document).ready(function() {
            const toast = new bootstrap.Toast(document.getElementById('successToast'));
            toast.show();
        });
    </script>
    <?php 
        unset($_SESSION['success_message']);
    endif;
    
    if (isset($_SESSION['error_message'])): 
    ?>
    <script>
        $(document).ready(function() {
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            toast.show();
        });
    </script>
    <?php 
        unset($_SESSION['error_message']);
    endif;
    ?>
    
</body>
</html>
<?php endif; ?>