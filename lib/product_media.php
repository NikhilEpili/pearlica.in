<?php
function product_media_list_by_product(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare('SELECT id, product_id, media_type, url, sort_order, is_primary FROM product_media WHERE product_id = ? AND deleted_at IS NULL ORDER BY is_primary DESC, sort_order, id');
    $stmt->execute([(int) $product_id]);
    return $stmt->fetchAll();
}

function product_media_insert(PDO $pdo, $product_id, $media_type, $url, $sort_order = 0, $is_primary = 0) {
    $stmt = $pdo->prepare('INSERT INTO product_media (product_id, media_type, url, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([(int) $product_id, $media_type, $url, (int) $sort_order, (int) $is_primary]);
    return $pdo->lastInsertId();
}

function product_media_set_primary(PDO $pdo, $product_id, $media_id) {
    $pdo->prepare('UPDATE product_media SET is_primary = 0 WHERE product_id = ?')->execute([(int) $product_id]);
    $stmt = $pdo->prepare('UPDATE product_media SET is_primary = 1 WHERE id = ? AND product_id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $media_id, (int) $product_id]);
}

function product_media_soft_delete(PDO $pdo, $id) {
    $stmt = $pdo->prepare('UPDATE product_media SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $id]);
}

function product_media_find_by_id(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id, product_id, url FROM product_media WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function product_media_update_order(PDO $pdo, $product_id, array $id_order) {
    $product_id = (int) $product_id;
    $stmt = $pdo->prepare('UPDATE product_media SET sort_order = ? WHERE id = ? AND product_id = ? AND deleted_at IS NULL');
    foreach ($id_order as $i => $id) {
        $stmt->execute([$i, (int) $id, $product_id]);
    }
    $pdo->prepare('UPDATE product_media SET is_primary = 0 WHERE product_id = ? AND deleted_at IS NULL')->execute([$product_id]);
    if (!empty($id_order)) {
        $pdo->prepare('UPDATE product_media SET is_primary = 1 WHERE id = ? AND product_id = ? AND deleted_at IS NULL')->execute([(int) $id_order[0], $product_id]);
    }
}
