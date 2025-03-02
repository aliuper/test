</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_ASSETS_URL ?>/js/admin.js"></script>
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
    
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });
            
            // Dropdown toggle
            $('.dropdown-toggle').on('click', function() {
                if ($(this).hasClass('active')) {
                    $(this).removeClass('active');
                } else {
                    $('.dropdown-toggle').removeClass('active');
                    $(this).addClass('active');
                }
            });
            
            // Tooltip aktivasyonu
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Uyarı mesajlarını otomatik kapat
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>