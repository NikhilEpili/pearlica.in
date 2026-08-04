<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('product_groups');
require_once ROOT . '/lib/product_group.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(admin_url('product-groups/'));
}
if (!validate_csrf()) {
    flash_set('error', 'Invalid request.');
    redirect(admin_url('product-groups/'));
}
$pdo = $GLOBALS['pdo'];
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect(admin_url('product-groups/'));
}
$group = product_group_find_by_id($pdo, $id);
if (!$group) {
    flash_set('error', 'Group not found.');
    redirect(admin_url('product-groups/'));
}
product_group_soft_delete($pdo, $id);
flash_set('success', 'Group deleted.');
redirect(admin_url('product-groups/'));
