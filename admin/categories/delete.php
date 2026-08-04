<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('categories');
require_once ROOT . '/lib/category.php';

$pdo = $GLOBALS['pdo'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(admin_url('categories/'));
}
if (!validate_csrf()) {
    flash_set('error', 'Invalid request.');
    redirect(admin_url('categories/'));
}
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect(admin_url('categories/'));
}
$category = category_find_by_id($pdo, $id);
if (!$category) {
    flash_set('error', 'Category not found.');
    redirect(admin_url('categories/'));
}
if (category_count_products($pdo, $id) > 0) {
    flash_set('error', 'Cannot delete: category has products.');
    redirect(admin_url('categories/'));
}
category_soft_delete($pdo, $id);
flash_set('success', 'Category deleted.');
redirect(admin_url('categories/'));
