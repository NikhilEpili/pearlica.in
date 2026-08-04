<?php
function category_list_all(PDO $pdo) {
    $stmt = $pdo->query('SELECT c.id, c.parent_id, c.name, c.slug, c.created_at,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
        FROM categories c WHERE c.deleted_at IS NULL ORDER BY c.name');
    return $stmt->fetchAll();
}

/** Parent categories only (parent_id IS NULL), for store front nav. Alphabetical by name. */
function category_list_parents_for_store(PDO $pdo) {
	$stmt = $pdo->query("SELECT c.id, c.name, c.slug FROM categories c
		WHERE c.deleted_at IS NULL
		  AND (c.parent_id IS NULL OR c.parent_id = 0)
		  AND EXISTS (
			SELECT 1 FROM products p
			LEFT JOIN categories ch ON ch.id = p.category_id AND ch.deleted_at IS NULL
			WHERE (p.category_id = c.id OR ch.parent_id = c.id)
			  AND p.deleted_at IS NULL
			  AND p.status = 'active'
			  AND COALESCE(p.stock_quantity, 0) > 0
		  )
		ORDER BY c.name ASC");
	return $stmt->fetchAll();
}

/** Categories with count of active products only (for store front). */
function category_list_for_store(PDO $pdo) {
    $stmt = $pdo->query("SELECT c.id, c.parent_id, c.name, c.slug, c.created_at,
        (
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN categories ch ON ch.id = p.category_id AND ch.deleted_at IS NULL
            WHERE (p.category_id = c.id OR ch.parent_id = c.id)
              AND p.deleted_at IS NULL
              AND p.status = 'active'
              AND COALESCE(p.stock_quantity, 0) > 0
        ) AS product_count
        FROM categories c
        WHERE c.deleted_at IS NULL
          AND EXISTS (
            SELECT 1 FROM products p2
            LEFT JOIN categories ch2 ON ch2.id = p2.category_id AND ch2.deleted_at IS NULL
            WHERE (p2.category_id = c.id OR ch2.parent_id = c.id)
              AND p2.deleted_at IS NULL
              AND p2.status = 'active'
              AND COALESCE(p2.stock_quantity, 0) > 0
          )
        ORDER BY c.name");
    return $stmt->fetchAll();
}

function category_list_for_select(PDO $pdo, $exclude_id = null) {
    $sql = 'SELECT id, parent_id, name FROM categories WHERE deleted_at IS NULL ORDER BY name';
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    if ($exclude_id !== null) {
        $rows = array_filter($rows, function ($r) use ($exclude_id) {
            return (int) $r['id'] !== (int) $exclude_id;
        });
    }
    return array_values($rows);
}

function category_find_by_id(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id, parent_id, name, slug FROM categories WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

/** @return array{id:int,parent_id:?int,name:string,slug:string}|null */
function category_find_by_slug(PDO $pdo, string $slug): ?array
{
	$slug = trim(strtolower($slug));
	if ($slug === '') {
		return null;
	}
	$stmt = $pdo->prepare('SELECT id, parent_id, name, slug FROM categories WHERE LOWER(TRIM(slug)) = ? AND deleted_at IS NULL LIMIT 1');
	$stmt->execute([$slug]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	return $row ?: null;
}

/**
 * Child categories under a parent that have at least one active storefront product.
 *
 * @return list<array{id:int,name:string,slug:string}>
 */
function category_list_children_for_store(PDO $pdo, int $parent_id): array
{
	if ($parent_id <= 0) {
		return [];
	}
	$stmt = $pdo->prepare("SELECT c.id, c.name, c.slug,
		(SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0) AS active_count
		FROM categories c
		WHERE c.deleted_at IS NULL AND c.parent_id = ?
		ORDER BY c.name ASC");
	$stmt->execute([$parent_id]);
	$out = [];
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if ((int) ($row['active_count'] ?? 0) < 1) {
			continue;
		}
		$out[] = [
			'id' => (int) $row['id'],
			'name' => (string) $row['name'],
			'slug' => (string) ($row['slug'] ?? ''),
		];
	}

	return $out;
}

/** True if any selected category is the parent or a direct/indirect child of parent (for filter UI). */
function category_filter_covers_parent(PDO $pdo, array $selected_cat_ids, int $parent_id): bool
{
	$parent_id = (int) $parent_id;
	if ($parent_id <= 0 || $selected_cat_ids === []) {
		return false;
	}
	foreach ($selected_cat_ids as $cid) {
		$cid = (int) $cid;
		if ($cid <= 0) {
			continue;
		}
		if ($cid === $parent_id) {
			return true;
		}
		$row = category_find_by_id($pdo, $cid);
		if (!$row) {
			continue;
		}
		$walk = (int) ($row['parent_id'] ?? 0);
		$guard = 0;
		while ($walk > 0 && $guard < 32) {
			if ($walk === $parent_id) {
				return true;
			}
			$pr = category_find_by_id($pdo, $walk);
			if (!$pr) {
				break;
			}
			$walk = (int) ($pr['parent_id'] ?? 0);
			$guard++;
		}
	}

	return false;
}

function category_count_products(PDO $pdo, $category_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL');
    $stmt->execute([(int) $category_id]);
    return (int) $stmt->fetchColumn();
}

function category_slug_exists(PDO $pdo, $slug, $exclude_id = null) {
    $sql = 'SELECT 1 FROM categories WHERE slug = ? AND deleted_at IS NULL';
    $params = [$slug];
    if ($exclude_id !== null) {
        $sql .= ' AND id != ?';
        $params[] = (int) $exclude_id;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function category_insert(PDO $pdo, $data) {
    $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name, slug) VALUES (?, ?, ?)');
    $stmt->execute([
        $data['parent_id'] ?: null,
        $data['name'],
        $data['slug']
    ]);
    return $pdo->lastInsertId();
}

function category_update(PDO $pdo, $id, $data) {
    $stmt = $pdo->prepare('UPDATE categories SET parent_id = ?, name = ?, slug = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([
        $data['parent_id'] ?: null,
        $data['name'],
        $data['slug'],
        (int) $id
    ]);
}

function category_soft_delete(PDO $pdo, $id) {
    $stmt = $pdo->prepare('UPDATE categories SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $id]);
}
