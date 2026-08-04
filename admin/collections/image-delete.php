<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
	$data = $_POST;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}
$pdo = $GLOBALS['pdo'];
$collection_id = (int) ($data['collection_id'] ?? 0);
if ($collection_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid collection']);
	exit;
}
$collection = collection_find_by_id($pdo, $collection_id);
if (!$collection) {
	echo json_encode(['ok' => false, 'error' => 'Collection not found']);
	exit;
}
if (!empty($collection['thumbnail_url'])) {
	collection_delete_thumbnail_disk((string) $collection['thumbnail_url']);
}
collection_set_thumbnail_url($pdo, $collection_id, null);
echo json_encode(['ok' => true]);
