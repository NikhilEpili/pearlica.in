<?php
$base_path = isset($base_path) ? $base_path : '';
require_once __DIR__ . '/../includes/asset_url.php';
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
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/tokens.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/design-system.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/vendor.css', $base_path); ?>">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/style.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/checkout.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/cart-drawer.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/navbar-search.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/mobile-bottom-nav.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/login.css', $base_path); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/ui-dialog.css', $base_path); ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<link rel="icon" href="<?php echo $base_path; ?>assets/images/favicon.png" type="image/png">
	<?php if (!empty($page_head_extra)) {
		echo $page_head_extra;
	} ?>
	<link rel="stylesheet" type="text/css" href="<?php echo asset_url('assets/css/pearlica-contrast.css', $base_path); ?>">
	<!-- Meta Pixel Code -->
	<script>
	!function(f,b,e,v,n,t,s)
	{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};
	if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
	n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];
	s.parentNode.insertBefore(t,s)}(window, document,'script',
	'https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', '1606163897779149');
	fbq('track', 'PageView');
	</script>
	<noscript><img height="1" width="1" style="display:none"
	src="https://www.facebook.com/tr?id=1606163897779149&ev=PageView&noscript=1"
	/></noscript>
	<!-- End Meta Pixel Code -->
</head>