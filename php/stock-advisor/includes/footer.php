
</main>

<footer class="py-3 mt-5" style="background:#0a0f1e;border-top:1px solid #1f2d40;">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 small" style="color:#4b5563;">
                &copy; <?= date('Y') ?> Stock Advisor &mdash; For personal investment guidance only.
            </div>
            <div class="col-md-6 text-md-end small" style="color:#4b5563;">
                Not financial advice. Always do your own research.
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/htmx.org@1.9.10/dist/htmx.min.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
