<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('policies');
require_once ROOT . '/lib/site_policy.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$policies = [];
try {
	site_policy_ensure_schema($pdo);
	$policies = site_policy_list_all($pdo);
} catch (Throwable $e) {
	$policies = [];
	$migration_hint = true;
}

$page_title = 'Policies';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Policies</h1>
	<p class="text-muted small mb-4">
		Edit storefront legal and care pages (Return &amp; exchange, Shipping, Privacy, Terms, Care Promise, Care guide).
		Use <code>{{BASE_PATH}}</code> in HTML links (e.g. <code>href="{{BASE_PATH}}policies/shipping-policy"</code>).
	</p>

	<?php if (!empty($migration_hint)): ?>
		<div class="alert alert-warning">
			Could not create or read the policies table. Check database permissions, or run
			<code>sql/add_site_policies.sql</code> in phpMyAdmin, then refresh.
		</div>
	<?php endif; ?>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="card">
		<div class="card-body p-0">
			<table class="table table-hover mb-0 align-middle">
				<thead>
					<tr>
						<th>Policy</th>
						<th>Slug / URL</th>
						<th>Updated</th>
						<th style="width:8rem">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($policies as $p): ?>
						<tr>
							<td>
								<strong><?= h((string) ($p['title'] ?? '')) ?></strong>
							</td>
							<td class="small">
								<code><?= h((string) ($p['slug'] ?? '')) ?></code>
								<div class="text-muted">/policies/<?= h((string) ($p['slug'] ?? '')) ?></div>
							</td>
							<td class="small text-muted"><?= h((string) ($p['updated_at'] ?? '')) ?></td>
							<td>
								<a href="<?= h(admin_url('policies/edit.php?id=' . (int) $p['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($policies) && !$migration_hint): ?>
						<tr><td colspan="4" class="text-muted p-3">No policies seeded yet. Refresh after ensuring <code>policies/defaults/</code> exists.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
