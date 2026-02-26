<?php
/**
 * Partial: footer.php
 * Closes layout, main-content. Each page closes its own .content-area.
 * Adds Chart.js CDN if needed, and any $extraJs.
 */
?>
</div><!-- /.main-content -->
</div><!-- /.layout -->

<?php if (!empty($includeCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<?php endif; ?>

<?php if (!empty($extraJs)): ?>
<?= $extraJs ?>
<?php endif; ?>

</body>
</html>

