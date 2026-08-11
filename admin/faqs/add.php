<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('faqs');
require_once ROOT . '/lib/faq.php';

$pdo = $GLOBALS['pdo'];
faq_ensure_schema($pdo);

$question = '';
$answer = '';
$answer_html = '';
$sort_order = faq_next_sort_order($pdo);
$is_active = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('faqs/add.php'));
	}
	$question = trim((string) ($_POST['question'] ?? ''));
	$answer = trim((string) ($_POST['answer'] ?? ''));
	$answer_html = trim((string) ($_POST['answer_html'] ?? ''));
	$sort_order = (int) ($_POST['sort_order'] ?? $sort_order);
	$is_active = isset($_POST['is_active']);

	if ($question === '' || $answer === '') {
		flash_set('error', 'Question and answer are required.');
		redirect(admin_url('faqs/add.php'));
	}

	faq_insert($pdo, $question, $answer, $answer_html !== '' ? $answer_html : null, $sort_order, $is_active);
	flash_set('success', 'FAQ added.');
	redirect(admin_url('faqs/'));
}

$page_title = 'Add FAQ';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Add FAQ</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('faqs/add.php')) ?>">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12">
						<label for="question" class="form-label">Question *</label>
						<input type="text" class="form-control" id="question" name="question" required maxlength="512" value="<?= h($question) ?>">
					</div>
					<div class="col-12">
						<label for="answer" class="form-label">Answer *</label>
						<textarea class="form-control" id="answer" name="answer" rows="5" required><?= h($answer) ?></textarea>
					</div>
					<div class="col-12">
						<label for="answer_html" class="form-label">Answer HTML <span class="text-muted">(optional)</span></label>
						<textarea class="form-control font-monospace small" id="answer_html" name="answer_html" rows="3" placeholder='e.g. Contact us at <a href="mailto:contact@pearlica.in">contact@pearlica.in</a>'><?= h($answer_html) ?></textarea>
						<small class="text-muted">If set, this HTML is shown instead of the plain answer (use for links).</small>
					</div>
					<div class="col-md-4">
						<label for="sort_order" class="form-label">Sort order</label>
						<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int) $sort_order ?>">
						<small class="text-muted">Lower numbers appear first.</small>
					</div>
					<div class="col-12">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : '' ?>>
							<label class="form-check-label" for="is_active">Active on About page</label>
						</div>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Save</button>
				<a href="<?= h(admin_url('faqs/')) ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
