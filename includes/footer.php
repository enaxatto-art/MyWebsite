    <?php if (isset($_SESSION['role']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student')): ?>
        </div> <!-- /.layout-content -->
    </div> <!-- /.layout -->
    <?php endif; ?>

    <?php $assetVersion = file_exists(__DIR__.'/../assets/js/main.js') ? filemtime(__DIR__.'/../assets/js/main.js') : time(); ?>
    <script src="assets/js/main.js?v=<?= $assetVersion ?>"></script>
    <script>
        // Add CSRF token to all forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?= $_SESSION['csrf_token'] ?? '' ?>';
                    form.appendChild(csrfInput);
                }
            });
        });
    </script>
</body>
</html>
