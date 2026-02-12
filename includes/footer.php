            <!-- Alert Container -->
            <?php 
            $alert = getAlert();
            if ($alert): 
            ?>
            <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show mb-4" role="alert">
                <?php echo $alert['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            submenu.classList.toggle('show');
        }
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // Initialize DataTables
        $(document).ready(function() {
            if ($('.datatable').length) {
                $('.datatable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']]
                });
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Print functionality
        function printReport() {
            window.print();
        }
        
        // Confirm delete
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this record?');
        }
        
        // Format currency inputs
        function formatCurrencyInput(input) {
            let value = input.value.replace(/[^\d.]/g, '');
            if (value) {
                input.value = parseFloat(value).toFixed(2);
            }
        }
    </script>
</body>
</html>
