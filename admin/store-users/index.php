<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();

$pdo = $GLOBALS['pdo'];

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];
if ($q !== '') {
	$where = 'WHERE email LIKE ? OR full_name LIKE ? OR phone LIKE ? OR city LIKE ? OR state LIKE ? OR pincode LIKE ?';
	$like = '%' . $q . '%';
	$params = [$like, $like, $like, $like, $like, $like];
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM store_users ' . $where);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$total_pages = pagination_total_pages($total, $per_page);
if ($total_pages > 0 && $page > $total_pages) {
	$page = $total_pages;
	$offset = ($page - 1) * $per_page;
}

$sql = 'SELECT id, email, full_name, phone, address, address_line_2, city, state, pincode, created_at, updated_at
		FROM store_users ' . $where . '
		ORDER BY id DESC
		LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Customers';
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Customers</h1>
	<p class="text-muted small mb-3">All storefront users stored in <code>store_users</code>. (Super admin only.)</p>

	<form method="get" action="<?= h(admin_url('store-users/')) ?>" class="row g-2 align-items-end mb-3">
		<div class="col-12 col-md-6 col-lg-5">
			<label for="q" class="form-label">Search</label>
			<input type="text" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Email, name, phone, location">
		</div>
		<div class="col-auto">
			<button type="submit" class="btn btn-primary">Search</button>
		</div>
		<div class="col-auto">
			<a class="btn btn-outline-secondary" href="<?= h(admin_url('store-users/')) ?>">Reset</a>
		</div>
	</form>

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead>
						<tr>
							<th>ID</th>
							<th>Email</th>
							<th>Name</th>
							<th>Phone</th>
							<th>Location</th>
							<th>Address</th>
							<th>Created</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($users as $u): ?>
							<?php
							$addr = trim((string) ($u['address'] ?? ''));
							$addr2 = trim((string) ($u['address_line_2'] ?? ''));
							$addr_disp = $addr . ($addr2 !== '' ? "\n" . $addr2 : '');
							$loc_parts = array_filter([
								trim((string) ($u['city'] ?? '')),
								trim((string) ($u['state'] ?? '')),
								trim((string) ($u['pincode'] ?? '')),
							], static fn ($p) => $p !== '');
							?>
							<tr>
								<td><?= (int) ($u['id'] ?? 0) ?></td>
								<td><span class="text-nowrap"><?= h($u['email'] ?? '') ?></span></td>
								<td><?= h($u['full_name'] ?? '') ?></td>
								<td><span class="text-nowrap"><?= h($u['phone'] ?? '') ?></span></td>
								<td style="min-width: 160px; white-space: normal;"><?php if ($loc_parts === []): ?><span class="text-muted">—</span><?php else: ?><?= h(implode(', ', $loc_parts)) ?><?php endif; ?></td>
								<td style="min-width: 220px; white-space: pre-line;"><?php if ($addr_disp !== ''): ?><?= h($addr_disp) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
								<td><span class="text-nowrap"><?= h($u['created_at'] ?? '') ?></span></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($users)): ?>
							<tr>
								<td colspan="7" class="text-center py-4 text-muted">No customers found.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<?php if ($total_pages > 1): ?>
		<nav aria-label="Customers pages" class="mt-3">
			<ul class="pagination mb-0">
				<?php
				$base = admin_url('store-users/') . '?';
				$extra = $q !== '' ? ('q=' . rawurlencode($q) . '&') : '';
				?>
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= h($base . $extra . 'page=' . max(1, $page - 1)) ?>">Prev</a>
				</li>
				<?php for ($i = 1; $i <= $total_pages; $i++): ?>
					<li class="page-item <?= $i === $page ? 'active' : '' ?>">
						<a class="page-link" href="<?= h($base . $extra . 'page=' . $i) ?>"><?= (int) $i ?></a>
					</li>
				<?php endfor; ?>
				<li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= h($base . $extra . 'page=' . min($total_pages, $page + 1)) ?>">Next</a>
				</li>
			</ul>
		</nav>
	<?php endif; ?>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

