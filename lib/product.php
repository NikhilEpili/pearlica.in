<?php
function product_list_paginated(PDO $pdo, $page, $per_page, $search = '', $category_id = '', $status = '') {
    $offset = ($page - 1) * $per_page;
    $where = ['p.deleted_at IS NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($category_id !== '') {
        $where[] = 'p.category_id = ?';
        $params[] = (int) $category_id;
    }
    if ($status !== '') {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM products p WHERE {$where_sql}";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $params[] = $per_page;
    $params[] = $offset;
    $sql = "SELECT p.id, p.name, p.slug, p.sku, p.status, p.category_id, p.created_at,
        c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
        WHERE {$where_sql}
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    return ['items' => $items, 'total' => $total];
}

/**
 * Category IDs whose slug/name match a storefront gender segment (Men's / Women's).
 * Empty array if nothing matches — caller should treat as “no gender filter”.
 */
function product_store_category_ids_for_gender(PDO $pdo, string $gender): array
{
    $gender = strtolower(trim($gender));
    if ($gender !== 'men' && $gender !== 'women') {
        return [];
    }
    $stmt = $pdo->query('SELECT id, slug, name FROM categories WHERE deleted_at IS NULL');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = [];
    foreach ($rows as $r) {
        $slug = strtolower((string) ($r['slug'] ?? ''));
        $name = strtolower((string) ($r['name'] ?? ''));
        $is_women = str_contains($slug, 'women') || str_contains($name, 'women') || str_contains($name, "women's");
        if ($gender === 'women') {
            if ($is_women) {
                $ids[] = (int) $r['id'];
            }
            continue;
        }
        // men — avoid matching “women” / “women's” via substring “men”
        if ($is_women) {
            continue;
        }
        $is_men = $slug === 'men' || $slug === 'mens' || str_starts_with($slug, 'mens-') || str_starts_with($slug, 'men-')
            || str_contains($name, "men's") || preg_match('/\bmens\b/u', $name) || preg_match('/\bmen\'s\b/u', $name);
        if ($is_men) {
            $ids[] = (int) $r['id'];
        }
    }
    return array_values(array_unique(array_filter($ids, function ($id) {
        return $id > 0;
    })));
}

/**
 * Expand storefront category filter IDs to all descendants (children, grandchildren, …).
 * Products have a single category_id; deep rows must match when a parent category is selected.
 *
 * @param int[] $category_ids
 * @return int[]
 */
function product_store_expand_category_tree_ids(PDO $pdo, array $category_ids): array
{
    $seen = [];
    foreach ($category_ids as $cid) {
        $cid = (int) $cid;
        if ($cid > 0) {
            $seen[$cid] = true;
        }
    }
    if ($seen === []) {
        return [];
    }
    $frontier = array_keys($seen);
    while ($frontier !== []) {
        $ph = implode(',', array_fill(0, count($frontier), '?'));
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE deleted_at IS NULL AND parent_id IN ({$ph})");
        $stmt->execute($frontier);
        $next = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid <= 0 || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $next[] = $cid;
        }
        $frontier = $next;
    }

    return array_map('intval', array_keys($seen));
}

/**
 * Basic singular/plural normalization for storefront search tokens.
 *
 * @return string[] token variants (lowercase, unique)
 */
function product_search_token_variants(string $token): array
{
    $token = strtolower(trim($token));
    if ($token === '') {
        return [];
    }

    $variants = [$token];
    $len = strlen($token);
    if ($len >= 3) {
        if (preg_match('/ies$/', $token) === 1 && $len > 3) {
            $variants[] = substr($token, 0, -3) . 'y';
        } elseif (preg_match('/sses$/', $token) === 1 && $len > 4) {
            $variants[] = substr($token, 0, -2);
        } elseif (str_ends_with($token, 's') && !str_ends_with($token, 'ss') && $len > 3) {
            $variants[] = substr($token, 0, -1);
        } else {
            $variants[] = $token . 's';
        }
    }

    $out = [];
    $seen = [];
    foreach ($variants as $v) {
        $v = trim((string) $v);
        if ($v === '' || isset($seen[$v])) {
            continue;
        }
        $seen[$v] = true;
        $out[] = $v;
    }
    return $out;
}

/**
 * SQL expression of product copy used for storefront colour (finish) filters.
 * Colour lives in name/description copy; metal_type/purity are the base metal (usually 925 silver).
 */
function product_store_color_copy_sql(): string
{
    return "LOWER(CONCAT_WS(' ',
        COALESCE(p.name, ''),
        COALESCE(p.slug, ''),
        COALESCE(p.description, ''),
        COALESCE(p.specification, ''),
        COALESCE(p.additional_information, ''),
        COALESCE(p.meta_title, ''),
        COALESCE(p.meta_description, ''),
        COALESCE(p.meta_keywords, '')
    ))";
}

/**
 * Rose-gold plating phrases (avoids matching floral “rose” on its own).
 *
 * @return list<string>
 */
function product_store_rose_gold_like_patterns(): array
{
    return ['%rose gold%', '%rose-gold%', '%rosegold%'];
}

/**
 * Storefront product listing with filters.
 *
 * @param array $filters Keys: category_ids (int[]), gender (men|women), min_price, max_price (float|null),
 *                       silver_925 (bool), on_sale (bool), in_stock (bool, ignored; storefront always requires stock), search (string), sort (string),
 *                       metal_colors (string[]) storefront finish from name/description: silver, rose_gold (OR when multiple),
 *                       random_order (bool) when true, shuffle with a stable seed (random_seed) so pagination does not repeat products,
 *                       random_seed (int) seed for deterministic browse shuffle (required when random_order is true),
 *                       added_within_days (int) when >0, only products with p.created_at in the last N days
 */
function product_list_store(PDO $pdo, int $page, int $per_page, array $filters = []): array
{
    $offset = ($page - 1) * $per_page;
    $where = ['p.deleted_at IS NULL', "p.status = 'active'", 'COALESCE(p.stock_quantity, 0) > 0'];
    $params = [];
    $order_params = [];

    $category_ids = [];
    if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
        foreach ($filters['category_ids'] as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $category_ids[] = $cid;
            }
        }
        $category_ids = array_values(array_unique($category_ids));
    }
    if (!empty($category_ids)) {
        $expanded_ids = product_store_expand_category_tree_ids($pdo, $category_ids);
        $ph = implode(',', array_fill(0, count($expanded_ids), '?'));
        $where[] = "p.category_id IN ({$ph})";
        foreach ($expanded_ids as $cid) {
            $params[] = $cid;
        }
    }

    $gender = strtolower(trim((string) ($filters['gender'] ?? '')));
    if ($gender === 'men' || $gender === 'women') {
        $g_ids = product_store_category_ids_for_gender($pdo, $gender);
        if (!empty($g_ids)) {
            $ph = implode(',', array_fill(0, count($g_ids), '?'));
            $where[] = "p.category_id IN ({$ph})";
            foreach ($g_ids as $gid) {
                $params[] = $gid;
            }
        }
    }

    $search = trim((string) ($filters['search'] ?? ''));
    $search_normalized = '';
    $search_tokens = [];
    if ($search !== '') {
        $search_normalized = strtolower(trim((string) preg_replace('/\s+/u', ' ', $search)));
        $search_tokens = array_values(array_filter(preg_split('/\s+/u', $search_normalized), static function ($t) {
            return (string) $t !== '';
        }));
        if ($search_tokens === []) {
            $search_tokens = [$search_normalized];
        }

        // Match intent: every token must appear either in name (word boundary preferred) or SKU.
        // This avoids broad OR matching that returns loosely related products.
        $name_expr = "LOWER(COALESCE(p.name, ''))";
        $sku_expr = "LOWER(COALESCE(p.sku, ''))";
        $token_parts = [];
        foreach ($search_tokens as $tok) {
            $tok = trim((string) $tok);
            if ($tok === '') {
                continue;
            }
            $variants = product_search_token_variants($tok);
            if ($variants === []) {
                $variants = [$tok];
            }

            $variant_parts = [];
            foreach ($variants as $variant) {
                $tok_like = '%' . $variant . '%';
                if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $variant)) {
                    $tok_word = '[[:<:]]' . preg_quote($variant, '/') . '[[:>:]]';
                    $variant_parts[] = "(($name_expr REGEXP ?) OR ($name_expr LIKE ?) OR ($sku_expr LIKE ?))";
                    $params[] = $tok_word;
                    $params[] = $tok_like;
                    $params[] = $tok_like;
                } else {
                    $variant_parts[] = "(($name_expr LIKE ?) OR ($sku_expr LIKE ?))";
                    $params[] = $tok_like;
                    $params[] = $tok_like;
                }
            }
            if ($variant_parts !== []) {
                $token_parts[] = '(' . implode(' OR ', $variant_parts) . ')';
            }
        }
        if ($token_parts !== []) {
            $where[] = '(' . implode(' AND ', $token_parts) . ')';
        }
    }

    $metal_colors = [];
    if (!empty($filters['metal_colors']) && is_array($filters['metal_colors'])) {
        foreach ($filters['metal_colors'] as $mc) {
            $mc = strtolower(trim((string) $mc));
            if ($mc === 'silver' || $mc === 'rose_gold') {
                $metal_colors[] = $mc;
            }
        }
        $metal_colors = array_values(array_unique($metal_colors));
    }
    if ($metal_colors !== []) {
        $copy_expr = product_store_color_copy_sql();
        $rose_patterns = product_store_rose_gold_like_patterns();
        $rose_sql = '(' . implode(' OR ', array_fill(0, count($rose_patterns), $copy_expr . ' LIKE ?')) . ')';
        $color_parts = [];
        foreach ($metal_colors as $mc) {
            if ($mc === 'rose_gold') {
                $color_parts[] = $rose_sql;
                foreach ($rose_patterns as $pat) {
                    $params[] = $pat;
                }
            } elseif ($mc === 'silver') {
                // Silver-toned finish: copy mentions silver/sterling/rhodium, and is not rose-gold plated.
                $not_rose = implode(' AND ', array_fill(0, count($rose_patterns), $copy_expr . ' NOT LIKE ?'));
                $color_parts[] = '(' . $not_rose . ' AND (' . $copy_expr . ' LIKE ? OR ' . $copy_expr . ' LIKE ? OR ' . $copy_expr . ' LIKE ?))';
                foreach ($rose_patterns as $pat) {
                    $params[] = $pat;
                }
                $params[] = '%silver%';
                $params[] = '%sterling%';
                $params[] = '%rhodium%';
            }
        }
        if ($color_parts !== []) {
            $where[] = '(' . implode(' OR ', $color_parts) . ')';
        }
    }

    if (!empty($filters['silver_925'])) {
        $where[] = '(LOWER(COALESCE(p.purity, \'\')) LIKE ? OR LOWER(COALESCE(p.metal_type, \'\')) LIKE ? OR LOWER(COALESCE(p.metal_type, \'\')) LIKE ? OR LOWER(COALESCE(p.metal_type, \'\')) LIKE ?)';
        $params[] = '%925%';
        $params[] = '%925%';
        $params[] = '%sterling%';
        $params[] = '%silver%';
    }

    if (!empty($filters['on_sale'])) {
        $where[] = 'COALESCE(p.discount_percentage, 0) > 0';
    }

    if (!empty($filters['is_gifting'])) {
        $where[] = 'COALESCE(p.is_gifting, 0) = 1';
    }

    $added_within = $filters['added_within_days'] ?? null;
    if ($added_within !== null && $added_within !== '' && is_numeric($added_within) && (int) $added_within > 0) {
        $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params[] = (int) $added_within;
    }

    $eff_price = '(CASE WHEN COALESCE(p.discount_percentage, 0) > 0 THEN COALESCE(p.mrp, 0) * (1 - LEAST(GREATEST(p.discount_percentage, 0), 100) / 100) ELSE COALESCE(p.mrp, 0) END)';

    $min_p = $filters['min_price'] ?? null;
    if ($min_p !== null && $min_p !== '' && is_numeric($min_p)) {
        $where[] = "{$eff_price} >= ?";
        $params[] = (float) $min_p;
    }
    $max_p = $filters['max_price'] ?? null;
    if ($max_p !== null && $max_p !== '' && is_numeric($max_p)) {
        $where[] = "{$eff_price} <= ?";
        $params[] = (float) $max_p;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM products p WHERE {$where_sql}";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $sort = (string) ($filters['sort'] ?? '');
    $order_by = 'p.id DESC';
    if ($search !== '' && $sort === '') {
        $name_expr = "LOWER(COALESCE(p.name, ''))";
        $sku_expr = "LOWER(COALESCE(p.sku, ''))";
        $first_tok = $search_tokens[0] ?? $search_normalized;
        $first_tok_variants = product_search_token_variants((string) $first_tok);
        if ($first_tok_variants === []) {
            $first_tok_variants = [(string) $first_tok];
        }
        usort($first_tok_variants, static function (string $a, string $b): int {
            return strlen($a) <=> strlen($b);
        });
        $rank_tok = $first_tok_variants[0];
        $first_tok_word = '[[:<:]]' . preg_quote($rank_tok, '/') . '[[:>:]]';
        $order_by = "(CASE
            WHEN {$name_expr} = ? THEN 300
            WHEN {$sku_expr} = ? THEN 260
            WHEN {$name_expr} LIKE ? THEN 220
            WHEN {$name_expr} REGEXP ? THEN 180
            WHEN {$name_expr} LIKE ? THEN 140
            WHEN {$sku_expr} LIKE ? THEN 120
            ELSE 0 END) DESC, p.id DESC";
        $order_params[] = $search_normalized;
        $order_params[] = $search_normalized;
        $order_params[] = $search_normalized . '%';
        $order_params[] = $first_tok_word;
        $order_params[] = '%' . $rank_tok . '%';
        $order_params[] = '%' . $rank_tok . '%';
    } elseif (!empty($filters['random_order']) && $search === '') {
        // Deterministic per-product shuffle: same seed => same order across pages (no RAND()+OFFSET repeats).
        $seed = (int) ($filters['random_seed'] ?? 0);
        if ($seed <= 0) {
            $seed = (int) (crc32((string) microtime(true)) & 0x7fffffff);
            if ($seed <= 0) {
                $seed = 1;
            }
        }
        $order_by = 'CRC32(CONCAT(CAST(p.id AS CHAR), \':\', ?)) ASC, p.id DESC';
        $order_params[] = (string) $seed;
    } elseif ($sort === 'price-asc') {
        $order_by = "{$eff_price} ASC, p.id DESC";
    } elseif ($sort === 'price-desc') {
        $order_by = "{$eff_price} DESC, p.id DESC";
    } elseif ($sort === 'newest') {
        $order_by = 'p.created_at DESC, p.id DESC';
    }

    $query_params = array_merge($params, $order_params);
    $query_params[] = $per_page;
    $query_params[] = $offset;
    $sql = "SELECT p.id, p.name, p.slug, p.mrp, p.discount_percentage, p.stock_quantity,
        c.name AS category_name,
        (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url,
        (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1 OFFSET 1) AS image_url_2
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
        WHERE {$where_sql}
        ORDER BY {$order_by}
        LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $rows = $stmt->fetchAll();
    if ($rows !== []) {
        $by_id = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (!isset($by_id[$pid])) {
                $by_id[$pid] = $row;
            }
        }
        $rows = array_values($by_id);
    }

    return ['items' => $rows, 'total' => $total];
}

function product_find_by_id(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id, category_id, product_group_id, name, slug, description, specification, additional_information, styling_tips, status, sku, metal_type, purity, weight_grams, size, cost_price, mrp, discount_percentage, gst_percent, stock_quantity, is_gifting, meta_title, meta_description, meta_keywords FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

/** Fetch product id, name, slug, mrp, discount_percentage, image_url for cart display. Returns array keyed by id. */
function product_get_cart_details(PDO $pdo, array $product_ids) {
    $product_ids = array_map('intval', array_filter($product_ids));
    if (empty($product_ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $sql = "SELECT p.id, p.name, p.slug, p.sku, p.mrp, p.discount_percentage, p.stock_quantity,
        c.name AS category_name,
        (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
        WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($product_ids));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = $r;
    }
    return $out;
}

/**
 * Drop cart lines that are inactive or out of stock. Persists cookie when the map changes.
 *
 * @param array<int,int> $cart
 * @return array<int,int>
 */
function cart_prune_unavailable(PDO $pdo, array $cart): array
{
    if ($cart === []) {
        return [];
    }
    $details = product_get_cart_details($pdo, array_keys($cart));
    $out = [];
    foreach ($cart as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid <= 0 || $qty <= 0 || !isset($details[$pid])) {
            continue;
        }
        $out[$pid] = $qty;
    }
    if ($out !== $cart) {
        cart_to_cookie($out);
    }
    return $out;
}

function product_find_id_by_sku(PDO $pdo, $sku) {
    $sku = trim((string) $sku);
    if ($sku === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$sku]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function product_slug_exists(PDO $pdo, $slug, $exclude_id = null) {
    $sql = 'SELECT 1 FROM products WHERE slug = ?';
    $params = [$slug];
    if ($exclude_id !== null) {
        $sql .= ' AND id != ?';
        $params[] = (int) $exclude_id;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function product_sku_exists(PDO $pdo, $sku, $exclude_id = null) {
    $sql = 'SELECT 1 FROM products WHERE sku = ?';
    $params = [$sku];
    if ($exclude_id !== null) {
        $sql .= ' AND id != ?';
        $params[] = (int) $exclude_id;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function product_insert(PDO $pdo, $data) {
    $stmt = $pdo->prepare('INSERT INTO products (category_id, product_group_id, name, slug, description, specification, additional_information, styling_tips, status, sku, metal_type, purity, weight_grams, size, cost_price, mrp, discount_percentage, gst_percent, stock_quantity, is_gifting, meta_title, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int) $data['category_id'],
        isset($data['product_group_id']) && $data['product_group_id'] !== '' && $data['product_group_id'] !== null ? (int) $data['product_group_id'] : null,
        $data['name'],
        $data['slug'],
        $data['description'] ?? null,
        $data['specification'] ?? null,
        $data['additional_information'] ?? null,
        $data['styling_tips'] ?? null,
        $data['status'] ?? 'draft',
        $data['sku'] ?? null,
        $data['metal_type'] ?? null,
        $data['purity'] ?? null,
        isset($data['weight_grams']) && $data['weight_grams'] !== '' && $data['weight_grams'] !== null ? (float) $data['weight_grams'] : null,
        $data['size'] ?? null,
        isset($data['cost_price']) && $data['cost_price'] !== '' && $data['cost_price'] !== null ? $data['cost_price'] : null,
        isset($data['mrp']) && $data['mrp'] !== '' && $data['mrp'] !== null ? $data['mrp'] : null,
        isset($data['discount_percentage']) && $data['discount_percentage'] !== '' && $data['discount_percentage'] !== null ? (float) $data['discount_percentage'] : 0,
        isset($data['gst_percent']) && $data['gst_percent'] !== '' && $data['gst_percent'] !== null ? (float) $data['gst_percent'] : null,
        (int) ($data['stock_quantity'] ?? 0),
        !empty($data['is_gifting']) ? 1 : 0,
        isset($data['meta_title']) && trim((string) $data['meta_title']) !== '' ? mb_substr(trim((string) $data['meta_title']), 0, 255) : null,
        isset($data['meta_description']) && trim((string) $data['meta_description']) !== '' ? mb_substr(trim((string) $data['meta_description']), 0, 512) : null,
        isset($data['meta_keywords']) && trim((string) $data['meta_keywords']) !== '' ? mb_substr(trim((string) $data['meta_keywords']), 0, 512) : null,
    ]);
    return $pdo->lastInsertId();
}

function product_update(PDO $pdo, $id, $data) {
    $stmt = $pdo->prepare('UPDATE products SET category_id = ?, product_group_id = ?, name = ?, slug = ?, description = ?, specification = ?, additional_information = ?, styling_tips = ?, status = ?, sku = ?, metal_type = ?, purity = ?, weight_grams = ?, size = ?, cost_price = ?, mrp = ?, discount_percentage = ?, gst_percent = ?, stock_quantity = ?, is_gifting = ?, meta_title = ?, meta_description = ?, meta_keywords = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([
        (int) $data['category_id'],
        isset($data['product_group_id']) && $data['product_group_id'] !== '' && $data['product_group_id'] !== null ? (int) $data['product_group_id'] : null,
        $data['name'],
        $data['slug'],
        $data['description'] ?? null,
        $data['specification'] ?? null,
        $data['additional_information'] ?? null,
        $data['styling_tips'] ?? null,
        $data['status'] ?? 'draft',
        $data['sku'] ?? null,
        $data['metal_type'] ?? null,
        $data['purity'] ?? null,
        isset($data['weight_grams']) && $data['weight_grams'] !== '' && $data['weight_grams'] !== null ? (float) $data['weight_grams'] : null,
        $data['size'] ?? null,
        isset($data['cost_price']) && $data['cost_price'] !== '' && $data['cost_price'] !== null ? $data['cost_price'] : null,
        isset($data['mrp']) && $data['mrp'] !== '' && $data['mrp'] !== null ? $data['mrp'] : null,
        isset($data['discount_percentage']) && $data['discount_percentage'] !== '' && $data['discount_percentage'] !== null ? (float) $data['discount_percentage'] : 0,
        isset($data['gst_percent']) && $data['gst_percent'] !== '' && $data['gst_percent'] !== null ? (float) $data['gst_percent'] : null,
        (int) ($data['stock_quantity'] ?? 0),
        !empty($data['is_gifting']) ? 1 : 0,
        isset($data['meta_title']) && trim((string) $data['meta_title']) !== '' ? mb_substr(trim((string) $data['meta_title']), 0, 255) : null,
        isset($data['meta_description']) && trim((string) $data['meta_description']) !== '' ? mb_substr(trim((string) $data['meta_description']), 0, 512) : null,
        isset($data['meta_keywords']) && trim((string) $data['meta_keywords']) !== '' ? mb_substr(trim((string) $data['meta_keywords']), 0, 512) : null,
        (int) $id
    ]);
}

/**
 * Resolved storefront SEO for a product (custom meta_* or sensible defaults).
 *
 * @param array<string,mixed> $product Row must include name; category_name, description, meta_* optional.
 * @return array{meta_title_full: string, meta_description: string, meta_keywords: string}
 */
function product_store_seo_meta(array $product): array
{
    $name = trim((string) ($product['name'] ?? ''));
    $cat = trim((string) ($product['category_name'] ?? ''));
    $desc_html = (string) ($product['description'] ?? '');
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($desc_html)));

    $mt = trim((string) ($product['meta_title'] ?? ''));
    $meta_title_full = $mt !== '' ? $mt : ($name . ' | Pearlica 925 Silver Jewellery');

    $md = trim((string) ($product['meta_description'] ?? ''));
    if ($md !== '') {
        $meta_description = mb_substr($md, 0, 512);
    } elseif ($plain !== '') {
        $meta_description = mb_substr($plain, 0, 160);
        if (mb_strlen($plain) > 160) {
            $meta_description .= '…';
        }
    } else {
        $b = 'Shop ' . $name;
        if ($cat !== '') {
            $b .= ' in ' . $cat;
        }
        $b .= '. Premium 925 sterling silver jewellery from Pearlica.';
        $meta_description = mb_substr($b, 0, 320);
    }

    $mk = trim((string) ($product['meta_keywords'] ?? ''));
    if ($mk !== '') {
        $meta_keywords = mb_substr($mk, 0, 512);
    } else {
        $parts = ['Pearlica', '925 sterling silver', 'silver jewellery'];
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($cat !== '') {
            $parts[] = $cat;
        }
        if (!empty($product['is_gifting'])) {
            $parts[] = 'gifting';
        }
        $meta_keywords = implode(', ', array_unique($parts));
    }

    return [
        'meta_title_full' => $meta_title_full,
        'meta_description' => $meta_description,
        'meta_keywords' => $meta_keywords,
    ];
}

function product_update_stock(PDO $pdo, $id, $quantity) {
    $stmt = $pdo->prepare('UPDATE products SET stock_quantity = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([max(0, (int) $quantity), (int) $id]);
}

function product_soft_delete(PDO $pdo, $id) {
    $stmt = $pdo->prepare('UPDATE products SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $id]);
}

function product_tags_by_product(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare('SELECT tag_id FROM product_tags WHERE product_id = ? AND deleted_at IS NULL');
    $stmt->execute([(int) $product_id]);
    return array_column($stmt->fetchAll(), 'tag_id');
}

function product_tags_sync(PDO $pdo, $product_id, $tag_ids) {
    $pdo->prepare('UPDATE product_tags SET deleted_at = NOW() WHERE product_id = ?')->execute([(int) $product_id]);
    $tag_ids = array_unique(array_map('intval', (array) $tag_ids));
    $stmt = $pdo->prepare('INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)');
    foreach ($tag_ids as $tid) {
        if ($tid <= 0) continue;
        $stmt->execute([(int) $product_id, $tid]);
    }
}

/**
 * Storefront listing columns for product cards (related, etc.).
 */
function product_store_card_columns_sql() {
    return "p.id, p.name, p.slug, p.mrp, p.discount_percentage, p.stock_quantity, c.name AS category_name,
        (SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url";
}

/**
 * Related products: same category, then shared tags, then best sellers from order_items.
 *
 * @return list<array<string,mixed>>
 */
function product_related_for_store(PDO $pdo, int $product_id, ?int $category_id, int $limit = 8) {
    $limit = max(4, min(16, $limit));
    $cols = product_store_card_columns_sql();
    $picked = [];
    $exclude = [$product_id];

    $append_rows = function (array $rows) use (&$picked, &$exclude, $limit) {
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0 || isset($picked[$id])) {
                continue;
            }
            $picked[$id] = $r;
            $exclude[] = $id;
            if (count($picked) >= $limit) {
                return;
            }
        }
    };

    $ex_sql = function () use (&$exclude) {
        $ids = array_values(array_unique(array_map('intval', $exclude)));
        $ids = array_filter($ids, function ($x) {
            return $x > 0;
        });
        if ($ids === []) {
            return '';
        }
        return ' AND p.id NOT IN (' . implode(',', $ids) . ')';
    };

    if ($category_id !== null && $category_id > 0 && count($picked) < $limit) {
        $sql = "SELECT {$cols} FROM products p
            LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
            WHERE p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0 AND p.category_id = ?" . $ex_sql() . "
            ORDER BY p.id DESC LIMIT " . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id]);
        $append_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    if (count($picked) < $limit) {
        $tag_ids = product_tags_by_product($pdo, $product_id);
        $tag_ids = array_values(array_filter(array_map('intval', $tag_ids), function ($t) {
            return $t > 0;
        }));
        if ($tag_ids !== []) {
            $ph = implode(',', array_fill(0, count($tag_ids), '?'));
            $need = $limit - count($picked);
            $sql = "SELECT DISTINCT {$cols} FROM products p
                LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
                INNER JOIN product_tags pt ON pt.product_id = p.id AND pt.deleted_at IS NULL
                WHERE p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0 AND pt.tag_id IN ({$ph})" . $ex_sql() . "
                ORDER BY p.id DESC LIMIT " . (int) max(1, $need);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($tag_ids);
            $append_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
    }

    if (count($picked) < $limit) {
        $need = $limit - count($picked);
        $sql = "SELECT {$cols}, COALESCE(sales.units, 0) AS _sort_units FROM products p
            LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT oi.product_id AS pid, SUM(oi.quantity) AS units
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE o.status IN ('paid', 'processing', 'shipped', 'delivered')
                GROUP BY oi.product_id
            ) sales ON sales.pid = p.id
            WHERE p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0" . $ex_sql() . "
            ORDER BY _sort_units DESC, p.id DESC LIMIT " . (int) max(1, $need);
        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$row) {
            unset($row['_sort_units']);
        }
        unset($row);
        $append_rows($rows ?: []);
    }

    return array_values($picked);
}

/**
 * Product-only autocomplete (ranked storefront search, no categories or popular queries).
 *
 * @return list<array{type:string,label:string,url:string}>
 */
function product_search_product_suggestions(PDO $pdo, string $q, int $limit = 12): array
{
    $q = trim($q);
    $limit = max(1, min(20, $limit));
    $qlen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
    if ($qlen < 2) {
        return [];
    }

    $result = product_list_store($pdo, 1, $limit, ['search' => $q]);
    $out = [];
    foreach ($result['items'] ?? [] as $row) {
        $label = trim((string) ($row['name'] ?? ''));
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($label === '' || $slug === '') {
            continue;
        }
        $out[] = [
            'type' => 'product',
            'label' => $label,
            'url' => 'product?slug=' . rawurlencode($slug),
        ];
    }
    return $out;
}

/**
 * Autocomplete suggestions from product names and category names (relative URLs, no base path).
 *
 * @return list<array{type:string,label:string,url:string}>
 */
function product_search_suggestions(PDO $pdo, string $q, int $limit = 12, bool $products_only = false) {
    if ($products_only) {
        return product_search_product_suggestions($pdo, $q, $limit);
    }

    $q = trim($q);
    $limit = max(1, min(20, $limit));
    $out = [];
    $seen = [];

    $qlen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
    if ($qlen < 2) {
        $examples = [
            ['type' => 'search', 'label' => 'Silver ring', 'url' => 'products?q=' . rawurlencode('silver ring')],
            ['type' => 'search', 'label' => 'Heart pendant', 'url' => 'products?q=' . rawurlencode('heart pendant')],
        ];
        foreach ($examples as $ex) {
            $key = 'e:' . $ex['label'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $ex;
            if (count($out) >= $limit) {
                return $out;
            }
        }
        $stmt = $pdo->query("SELECT name, slug FROM products WHERE deleted_at IS NULL AND status = 'active' AND COALESCE(stock_quantity, 0) > 0 ORDER BY id DESC LIMIT 8");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $label = trim((string) ($r['name'] ?? ''));
            $slug = trim((string) ($r['slug'] ?? ''));
            if ($label === '' || $slug === '') {
                continue;
            }
            $key = 'p:' . $slug;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['type' => 'product', 'label' => $label, 'url' => 'product?slug=' . rawurlencode($slug)];
            if (count($out) >= $limit) {
                return $out;
            }
        }
        $cstmt = $pdo->query('SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 8');
        $crows = $cstmt ? $cstmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($crows as $c) {
            $cid = (int) ($c['id'] ?? 0);
            $name = trim((string) ($c['name'] ?? ''));
            if ($cid <= 0 || $name === '') {
                continue;
            }
            $key = 'c:' . $cid;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['type' => 'category', 'label' => $name, 'url' => 'products?cat[]=' . $cid];
            if (count($out) >= $limit) {
                return $out;
            }
        }
        return $out;
    }

    $like = '%' . $q . '%';
    $half = (int) ceil($limit / 2);
    $stmt = $pdo->prepare("SELECT name, slug FROM products WHERE deleted_at IS NULL AND status = 'active' AND COALESCE(stock_quantity, 0) > 0 AND name LIKE ? ORDER BY name ASC LIMIT " . (int) $limit);
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $label = trim((string) ($r['name'] ?? ''));
        $slug = trim((string) ($r['slug'] ?? ''));
        if ($label === '' || $slug === '') {
            continue;
        }
        $key = 'p:' . $slug;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = ['type' => 'product', 'label' => $label, 'url' => 'product?slug=' . rawurlencode($slug)];
        if (count($out) >= $limit) {
            return $out;
        }
    }

    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE deleted_at IS NULL AND name LIKE ? ORDER BY name ASC LIMIT ' . (int) max(1, $half));
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
        $cid = (int) ($c['id'] ?? 0);
        $name = trim((string) ($c['name'] ?? ''));
        if ($cid <= 0 || $name === '') {
            continue;
        }
        $key = 'c:' . $cid;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = ['type' => 'category', 'label' => $name, 'url' => 'products?cat[]=' . $cid];
        if (count($out) >= $limit) {
            return $out;
        }
    }

    return $out;
}
