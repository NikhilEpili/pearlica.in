<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/promo_code.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('promo-codes/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('promo-codes/'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
	promo_code_delete($pdo, $id);
	flash_set('success', 'Promo code deleted.');
}
redirect(admin_url('promo-codes/'));
