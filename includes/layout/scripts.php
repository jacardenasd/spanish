<?php
if (!isset($extra_js)) { $extra_js = []; }
?>
<script src="<?php echo ASSET_BASE; ?>assets/js/app.js"></script>

<?php foreach ($extra_js as $js): ?>
	<script src="<?php echo ASSET_BASE . ltrim($js,'/'); ?>"></script>
<?php endforeach; ?>

</body>
</html>
