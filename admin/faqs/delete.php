<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('faqs');
require_once ROOT . '/lib/faq.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('faqs/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('faqs/'));
}

$pdo = $GLOBALS['pdo'];
faq_ensure_schema($pdo);
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0 || !faq_find_by_id($pdo, $id)) {
	flash_set('error', 'FAQ not found.');
	redirect(admin_url('faqs/'));
}

faq_soft_delete($pdo, $id);
flash_set('success', 'FAQ deleted.');
redirect(admin_url('faqs/'));
