<?php
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

http_response_code(404);
$base_path = rtrim(dirname($path_only), '/') . '/';
if ($base_path === '/') {
	$base_path = '';
}
require_once __DIR__ . '/config/config.php';
$page_title = 'Page not found | Pearlica';
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>

<body class="homepage">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main class="py-5">
		<div class="page-content-constrained text-center">
			<h4 class="mb-3">404</h4>
			<p class="text-secondary mb-4">The page you requested was not found.</p>
			<a href="<?php echo htmlspecialchars($base_path ? $base_path . 'index' : 'index', ENT_QUOTES, 'UTF-8'); ?>"
				class="btn btn-dark">Back to Home</a>
		</div>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
</body>

</html>