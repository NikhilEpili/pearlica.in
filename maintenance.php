<?php
/**
 * Storefront maintenance page.
 *
 * Intended to be served during site downtime (manually or via Apache routing).
 * Reuses the same head/navbar/footer as the rest of the site for visual consistency.
 */

$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path_only = parse_url($req_uri, PHP_URL_PATH);
$path_only = $path_only ?: '/';
$query = parse_url($req_uri, PHP_URL_QUERY);

// Fix double path segment (e.g. /pearlica.in/pearlica.in/product -> /pearlica.in/product)
$segments = array_values(array_filter(explode('/', $path_only)));
if (count($segments) >= 2 && $segments[0] === $segments[1]) {
	$correct_path = '/' . implode('/', array_slice($segments, 1));
	$redirect = $correct_path . ($query !== null && $query !== '' ? '?' . $query : '');
	header('Location: ' . $redirect, true, 301);
	exit;
}

http_response_code(503);

require_once __DIR__ . '/includes/store_base_path.php';
$base_path = store_base_path_from_request();

require_once __DIR__ . '/config/config.php';

if (!function_exists('h')) {
	function h($s): string
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$page_title = 'Maintenance';
$meta_description = 'This website is currently undergoing maintenance. Please check back later.';

$home_url = $base_path ? $base_path . 'index' : 'index';
?>

<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>

<body class="homepage">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>
	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="py-5">
		<div class="page-content-constrained text-center bg-off-white border-top border-bottom"
			style="padding: 72px 16px;">
			<p class="section-label mb-3">Pearlica</p>
			<h1 class="mb-3" style="font-family: var(--font-editorial); font-weight: 400; font-size: 2.2rem;">
				This website is currently undergoing maintenance.
			</h1>
			<p class="text-secondary mb-4" style="max-width: 52rem; margin-left: auto; margin-right: auto;">
				Please check back later.
			</p>
			<a href="<?= h($home_url) ?>" class="btn btn-dark px-4 py-3">Back to Home</a>
		</div>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>
</body>

</html>
