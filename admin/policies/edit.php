<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('policies');
require_once ROOT . '/lib/site_policy.php';

$pdo = $GLOBALS['pdo'];
site_policy_ensure_schema($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$policy = $id > 0 ? site_policy_find_by_id($pdo, $id) : null;
if (!$policy) {
	flash_set('error', 'Policy not found.');
	redirect(admin_url('policies/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('policies/edit.php?id=' . $id));
	}
	$title = trim((string) ($_POST['title'] ?? ''));
	$lead_text = trim((string) ($_POST['lead_text'] ?? ''));
	$effective_note = trim((string) ($_POST['effective_note'] ?? ''));
	$meta_title = trim((string) ($_POST['meta_title'] ?? ''));
	$meta_description = trim((string) ($_POST['meta_description'] ?? ''));
	$body_html = (string) ($_POST['body_html'] ?? '');

	if ($title === '' || trim($body_html) === '') {
		flash_set('error', 'Title and body HTML are required.');
		redirect(admin_url('policies/edit.php?id=' . $id));
	}

	// Keep clearance/promo non-returnable wording out even if pasted back in.
	$body_html = preg_replace(
		'/\s*<li>\s*Items purchased during clearance sales or special promotional offers\s*<\/li>/iu',
		'',
		$body_html
	) ?? $body_html;
	$body_html = preg_replace(
		'/\s*<li>\s*Warranty does not apply to items purchased during clearance sales or special promotions\s*<\/li>/iu',
		'',
		$body_html
	) ?? $body_html;
	$body_html = str_replace(
		'Contact us within <strong>24 hours</strong> of delivery with the video and photographs',
		'Contact us within <strong>48 hours</strong> of delivery with the video and photographs',
		$body_html
	);
	$body_html = str_replace(
		'Contact us within 24 hours of receipt',
		'Contact us within 48 hours of receipt',
		$body_html
	);

	site_policy_update($pdo, $id, $title, $lead_text, $effective_note, $meta_title, $meta_description, $body_html);
	flash_set('success', 'Policy updated.');
	redirect(admin_url('policies/'));
}

$page_title = 'Edit policy';
$error = flash_get('error');
$title = (string) ($policy['title'] ?? '');
$lead_text = (string) ($policy['lead_text'] ?? '');
$effective_note = (string) ($policy['effective_note'] ?? '');
$meta_title = (string) ($policy['meta_title'] ?? '');
$meta_description = (string) ($policy['meta_description'] ?? '');
$body_html = (string) ($policy['body_html'] ?? '');
$slug = (string) ($policy['slug'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Edit policy</h1>
	<p class="text-muted small mb-3">
		Slug: <code><?= h($slug) ?></code>
		· Public URL: <a href="<?= h('../policies/' . $slug) ?>" target="_blank" rel="noopener">/policies/<?= h($slug) ?></a>
	</p>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('policies/edit.php?id=' . $id)) ?>">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12">
						<label for="title" class="form-label">Page title *</label>
						<input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= h($title) ?>">
					</div>
					<div class="col-md-6">
						<label for="lead_text" class="form-label">Lead / subtitle</label>
						<input type="text" class="form-control" id="lead_text" name="lead_text" maxlength="255" value="<?= h($lead_text) ?>">
					</div>
					<div class="col-md-6">
						<label for="effective_note" class="form-label">Effective / last updated note</label>
						<input type="text" class="form-control" id="effective_note" name="effective_note" maxlength="255" value="<?= h($effective_note) ?>">
					</div>
					<div class="col-12">
						<label for="meta_title" class="form-label">SEO title</label>
						<input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255" value="<?= h($meta_title) ?>">
					</div>
					<div class="col-12">
						<label for="meta_description" class="form-label">SEO description</label>
						<textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="512"><?= h($meta_description) ?></textarea>
					</div>
					<div class="col-12">
						<label for="body_html" class="form-label">Body HTML *</label>
						<textarea class="form-control font-monospace small" id="body_html" name="body_html" rows="22" required><?= h($body_html) ?></textarea>
						<small class="text-muted">
							HTML only (headings, paragraphs, lists, links). For internal policy links use
							<code>{{BASE_PATH}}policies/…</code>. Do not list site-wide discounts as “non-returnable clearance / promo” items.
						</small>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Save</button>
				<a href="<?= h(admin_url('policies/')) ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
