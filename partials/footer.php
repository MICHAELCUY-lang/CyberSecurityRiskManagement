<?php
/**
 * Partial: footer.php
 * Closes layout, main-content, adds Chart.js CDN if needed.
 */
?>
    </div><!-- /.content-area -->
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
