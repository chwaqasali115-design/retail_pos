<?php if (isset($_SESSION['user_id'])): ?>
    </div> <!-- End container-fluid -->
    </div> <!-- End page-content-wrapper -->
    </div> <!-- End wrapper -->
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");

    if (toggleButton) {
        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    }
</script>
</body>

</html>