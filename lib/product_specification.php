<?php
function product_specification_list_by_product(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare('SELECT id, product_id, spec_key, spec_value FROM product_specifications WHERE product_id = ? AND deleted_at IS NULL ORDER BY id');
    $stmt->execute([(int) $product_id]);
    return $stmt->fetchAll();
}

function product_specification_insert(PDO $pdo, $product_id, $spec_key, $spec_value) {
    $stmt = $pdo->prepare('INSERT INTO product_specifications (product_id, spec_key, spec_value) VALUES (?, ?, ?)');
    $stmt->execute([(int) $product_id, $spec_key, $spec_value ?? '']);
    return $pdo->lastInsertId();
}

function product_specification_update(PDO $pdo, $id, $spec_key, $spec_value) {
    $stmt = $pdo->prepare('UPDATE product_specifications SET spec_key = ?, spec_value = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([$spec_key, $spec_value ?? '', (int) $id]);
}

function product_specification_soft_delete(PDO $pdo, $id) {
    $stmt = $pdo->prepare('UPDATE product_specifications SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $id]);
}

function product_specification_sync(PDO $pdo, $product_id, $pairs) {
    $pdo->prepare('UPDATE product_specifications SET deleted_at = NOW() WHERE product_id = ?')->execute([(int) $product_id]);
    $stmt = $pdo->prepare('INSERT INTO product_specifications (product_id, spec_key, spec_value) VALUES (?, ?, ?)');
    foreach ((array) $pairs as $key => $value) {
        $key = trim((string) $key);
        if ($key === '') continue;
        $stmt->execute([(int) $product_id, $key, (string) $value]);
    }
}
