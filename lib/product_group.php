<?php
function product_group_list_all(PDO $pdo) {
    $stmt = $pdo->query('SELECT g.id, g.name, g.slug, g.created_at,
        (SELECT COUNT(*) FROM products p WHERE p.product_group_id = g.id AND p.deleted_at IS NULL) AS product_count
        FROM product_groups g WHERE g.deleted_at IS NULL ORDER BY g.name');
    return $stmt->fetchAll();
}

function product_group_list_for_select(PDO $pdo, $exclude_id = null) {
    $sql = 'SELECT id, name FROM product_groups WHERE deleted_at IS NULL ORDER BY name';
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    if ($exclude_id !== null) {
        $rows = array_filter($rows, function ($r) use ($exclude_id) {
            return (int) $r['id'] !== (int) $exclude_id;
        });
    }
    return array_values($rows);
}

function product_group_find_by_id(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id, name, slug FROM product_groups WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function product_group_count_products(PDO $pdo, $group_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_group_id = ? AND deleted_at IS NULL');
    $stmt->execute([(int) $group_id]);
    return (int) $stmt->fetchColumn();
}

function product_group_slug_exists(PDO $pdo, $slug, $exclude_id = null) {
    $sql = 'SELECT 1 FROM product_groups WHERE slug = ? AND deleted_at IS NULL';
    $params = [$slug];
    if ($exclude_id !== null) {
        $sql .= ' AND id != ?';
        $params[] = (int) $exclude_id;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function product_group_insert(PDO $pdo, $data) {
    $stmt = $pdo->prepare('INSERT INTO product_groups (name, slug) VALUES (?, ?)');
    $stmt->execute([$data['name'], $data['slug']]);
    return $pdo->lastInsertId();
}

function product_group_update(PDO $pdo, $id, $data) {
    $stmt = $pdo->prepare('UPDATE product_groups SET name = ?, slug = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([$data['name'], $data['slug'], (int) $id]);
}

function product_group_soft_delete(PDO $pdo, $id) {
    $id = (int) $id;
    $pdo->prepare('UPDATE products SET product_group_id = NULL WHERE product_group_id = ?')->execute([$id]);
    $stmt = $pdo->prepare('UPDATE product_groups SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([$id]);
}

/**
 * Get other active products in the same group (for variants on storefront).
 * Excludes the given product_id.
 */
function product_group_variants_for_store(PDO $pdo, $group_id, $exclude_product_id) {
    $group_id = (int) $group_id;
    $exclude_product_id = (int) $exclude_product_id;
    if ($group_id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.mrp, p.discount_percentage,
            (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url
        FROM products p
        WHERE p.product_group_id = ? AND p.id != ? AND p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0
        ORDER BY p.name
    ");
    $stmt->execute([$group_id, $exclude_product_id]);
    return $stmt->fetchAll();
}

/**
 * All active products in a group (storefront), including every member — for variant picker under gallery.
 */
function product_group_members_for_store(PDO $pdo, int $group_id): array
{
    $group_id = (int) $group_id;
    if ($group_id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.mrp, p.discount_percentage,
            (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url
        FROM products p
        WHERE p.product_group_id = ? AND p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0
        ORDER BY p.name
    ");
    $stmt->execute([$group_id]);
    return $stmt->fetchAll();
}
