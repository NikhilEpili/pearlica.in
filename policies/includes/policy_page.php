<?php
/**
 * Shared storefront policy page shell.
 * Expects $policy_slug (e.g. 'return-exchange-policy').
 */
declare(strict_types=1);

if (!isset($policy_slug) || !is_string($policy_slug) || $policy_slug === '') {
	http_response_code(404);
	exit('Policy not found.');
}

$base_path = '../';
require_once __DIR__ . '/../../lib/site_policy.php';

$pdo = null;
try {
	if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
		require_once __DIR__ . '/../../config/db.php';
	}
	$pdo = $GLOBALS['pdo'] ?? null;
} catch (Throwable $e) {
	$pdo = null;
}

$policy = site_policy_for_page($pdo instanceof PDO ? $pdo : null, $policy_slug);
if ($policy === null) {
	http_response_code(404);
	exit('Policy not found.');
}

$meta_title_full = $policy['meta_title'] !== '' ? $policy['meta_title'] : ($policy['title'] . ' | Pearlica');
$meta_description = $policy['meta_description'];
$body_html = site_policy_render_body($policy['body_html'], $base_path);
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../../components/head.php'; ?>

<body class="homepage">

	<?php include __DIR__ . '/../../components/navbar.php'; ?>

	<main>
		<section class="py-4 py-lg-5">
			<div class="container">
				<h4 class="section-title text-uppercase mb-4"><?= htmlspecialchars($policy['title'], ENT_QUOTES, 'UTF-8') ?></h4>
				<?php if (trim($policy['lead_text']) !== ''): ?>
					<p class="lead text-secondary"><?= htmlspecialchars($policy['lead_text'], ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>
				<?php if (trim($policy['effective_note']) !== ''): ?>
					<p class="text-secondary mb-4"><em><?= htmlspecialchars($policy['effective_note'], ENT_QUOTES, 'UTF-8') ?></em></p>
				<?php endif; ?>
				<div class="policy-content">
					<?= $body_html ?>
					<?php include __DIR__ . '/related-policies.php'; ?>
				</div>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/../../components/footer.php'; ?>

	<script src="<?php echo htmlspecialchars(asset_url('assets/js/jquery.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/plugins.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/../../components/swiper_bundle_script.php'; ?>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/script.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>
