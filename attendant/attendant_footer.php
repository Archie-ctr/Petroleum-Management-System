<?php
// attendant_footer.php
?>
        </div> <!-- Close container-fluid -->
    </div> <!-- Close main-content-wrapper -->
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.table').each(function() {
                if ($(this).is('[data-datatable-lazy="true"]')) {
                    return;
                }

                var emptyRow = $(this).find('tbody td[colspan]').closest('tr');
                var emptyMessage = emptyRow.text().trim();
                emptyRow.remove();

                $(this).DataTable({
                    responsive: true,
                    pageLength: 10,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search...",
                        emptyTable: emptyMessage || "No data available in table"
                    }
                });
            });
        });
        
        // Auto-refresh dashboard every 2 minutes
        setTimeout(function() {
            window.location.reload();
        }, 120000);
    </script>
</body>
</html>