<?php
$base_path = isset($base_path) ? $base_path : '';
?>

<head>
	<title><?php
	if (!empty($meta_title_full)) {
		echo htmlspecialchars($meta_title_full, ENT_QUOTES, 'UTF-8');
	} else {
		echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' | ' : '';
		echo 'Pearlica';
	}
	?></title>
	<meta charset="utf-8">
	<?php if (!empty($meta_description)): ?>
		<meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
	<?php endif; ?>
	<?php if (!empty($meta_keywords)): ?>
		<meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
	<?php endif; ?>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
	<link
		href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300;1,400&display=swap"
		rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0"
		rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet"
		integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/tokens.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/design-system.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/vendor.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/style.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/checkout.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/cart-drawer.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/navbar-search.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/mobile-bottom-nav.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<link rel="icon" href="<?php echo $base_path; ?>assets/images/favicon.png" type="image/png">
	<?php if (!empty($page_head_extra)) {
		echo $page_head_extra;
	} ?>
	<link rel="stylesheet" type="text/css" href="<?php echo $base_path; ?>assets/css/pearlica-contrast.css">
</head>