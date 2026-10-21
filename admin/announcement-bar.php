<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/site_announcement_bar.php';

$success = flash_get('success');
$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('announcement-bar.php'));
	}
	$next = [
		'enabled' => isset($_POST['enabled']) && (string) $_POST['enabled'] === '1',
		'text' => trim((string) ($_POST['text'] ?? '')),
	];
	if ($next['text'] === '') {
		flash_set('error', 'Announcement text cannot be empty.');
		redirect(admin_url('announcement-bar.php'));
	}
	if (!site_announcement_bar_settings_save($next)) {
		flash_set('error', 'Could not save settings. Check storage permissions.');
		redirect(admin_url('announcement-bar.php'));
	}
	flash_set('success', 'Announcement bar updated.');
	redirect(admin_url('announcement-bar.php'));
}

$cfg = site_announcement_bar_settings_load();
$page_title = 'Announcement bar';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Announcement bar</h1>
	<p class="text-muted small mb-4">
		Edit the promo strip shown above the site header on every page. Turn it off when you do not want a top banner.
	</p>

	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="card" style="max-width: 720px;">
		<div class="card-body">
			<form method="post" action="<?= h(admin_url('announcement-bar.php')) ?>">
				<?= csrf_field() ?>
				<div class="mb-4">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" role="switch" id="announcement-enabled" name="enabled" value="1"
							<?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
						<label class="form-check-label" for="announcement-enabled">Show announcement bar on storefront</label>
					</div>
				</div>
				<div class="mb-3">
					<label class="form-label" for="announcement-text">Bar text</label>
					<textarea class="form-control" id="announcement-text" name="text" rows="3" maxlength="500" required><?= h((string) ($cfg['text'] ?? '')) ?></textarea>
					<small class="text-muted">Max 500 characters. Use middle dots (·) or dashes to separate offers.</small>
				</div>
				<div class="mb-4 p-3 rounded border bg-light">
					<div class="small text-muted mb-2">Preview</div>
					<div style="background:#0a0a0a;padding:10px 1rem;text-align:center;">
						<p class="mb-0" id="announcement-preview" style="font-size:11px;font-weight:500;letter-spacing:0.22em;text-transform:uppercase;color:#fff;line-height:1.35;"><?= h((string) ($cfg['text'] ?? '')) ?></p>
					</div>
				</div>
				<button type="submit" class="btn btn-primary">Save</button>
			</form>
		</div>
	</div>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
	var ta = document.getElementById('announcement-text');
	var preview = document.getElementById('announcement-preview');
	if (!ta || !preview) return;
	ta.addEventListener('input', function () {
		preview.textContent = ta.value;
	});
})();
</script>
</body>
</html>
