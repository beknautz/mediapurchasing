
</main><!-- /.container-fluid -->

<footer class="bg-dark text-light py-3 mt-5">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 small text-muted">
                &copy; <?= date('Y') ?> MediaBuy Platform. All rights reserved.
            </div>
            <div class="col-md-6 text-md-end small text-muted">
                Media Purchasing &amp; Campaign Management System
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
