<?php
if (!isset($page_title)) { $page_title = 'SGRH'; }
if (!isset($extra_css)) { $extra_css = []; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($page_title); ?></title>

	<!-- Global stylesheets -->
	<link href="https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900" rel="stylesheet" type="text/css">
	<link href="<?php echo ASSET_BASE; ?>/global_assets/css/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">
	<link href="<?php echo ASSET_BASE; ?>/assets/css/all.min.css" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<?php foreach ($extra_css as $css): ?>
		<link href="<?php echo ASSET_BASE . ltrim($css,'/'); ?>" rel="stylesheet" type="text/css">
	<?php endforeach; ?>

	<!-- Core JS files (Limitless los carga en head) -->
	<script src="<?php echo ASSET_BASE; ?>/global_assets/js/main/jquery.min.js"></script>
	<script src="<?php echo ASSET_BASE; ?>/global_assets/js/main/bootstrap.bundle.min.js"></script>
	<!-- /core JS files -->

	<!-- Theme JS files -->
	<script src="assets/js/app.js"></script>
	<!-- /theme JS files -->

</head>
<body>
