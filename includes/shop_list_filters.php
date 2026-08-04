<?php
/**
 * Shared GET filter parsing + query string builder for shop listing pages (products, gifting).
 */

/**
 * If both parent and child categories are selected, keep only the most specific IDs.
 *
 * Example: [Bracelets, Bracelets-RoseGold] -> [Bracelets-RoseGold]
 *
 * @param int[] $cat_ids
 * @return int[]
 */
function products_normalize_category_filter_ids(PDO $pdo, array $cat_ids): array
{
	$cat_ids = array_values(array_unique(array_map('intval', $cat_ids)));
	$cat_ids = array_values(array_filter($cat_ids, static fn(int $id): bool => $id > 0));
	if (count($cat_ids) <= 1) {
		return $cat_ids;
	}

	$stmt = $pdo->query('SELECT id, parent_id FROM categories WHERE deleted_at IS NULL');
	$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
	if ($rows === []) {
		return $cat_ids;
	}

	$parent_by_id = [];
	foreach ($rows as $row) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$parent_by_id[$id] = (int) ($row['parent_id'] ?? 0);
	}

	$selected = array_fill_keys($cat_ids, true);
	$drop = [];
	foreach ($cat_ids as $cid) {
		$walk = (int) ($parent_by_id[$cid] ?? 0);
		$guard = 0;
		while ($walk > 0 && $guard < 32) {
			if (isset($selected[$walk])) {
				$drop[$walk] = true;
			}
			$walk = (int) ($parent_by_id[$walk] ?? 0);
			$guard++;
		}
	}

	if ($drop === []) {
		return $cat_ids;
	}
	return array_values(array_filter($cat_ids, static function (int $id) use ($drop): bool {
		return !isset($drop[$id]);
	}));
}

/**
 * Stable browse-shuffle seed for default (unsorted) product lists.
 * Kept in the URL so pagination does not reshuffle and re-show products.
 */
function products_new_random_seed(): int
{
	try {
		return random_int(1, 2147483646);
	} catch (Throwable $e) {
		return (int) (mt_rand(1, 2147483646));
	}
}

/**
 * @return array{cat: int[], sort: string, gender: string, min_price: ?float, max_price: ?float, silver: bool, sale: bool, stock: bool, q: string, color: string[], rs: int}
 */
function products_parse_filters_from_request(): array
{
	$cat = [];
	if (!empty($_GET['cat']) && is_array($_GET['cat'])) {
		foreach ($_GET['cat'] as $v) {
			$id = (int) $v;
			if ($id > 0) {
				$cat[] = $id;
			}
		}
		$cat = array_values(array_unique($cat));
	}
	if ($cat === [] && isset($_GET['category']) && $_GET['category'] !== '') {
		$id = (int) $_GET['category'];
		if ($id > 0) {
			$cat = [$id];
		}
	}
	if ($cat !== [] && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
		$cat = products_normalize_category_filter_ids($GLOBALS['pdo'], $cat);
	}

	$gender = isset($_GET['gender']) ? strtolower(trim((string) $_GET['gender'])) : '';
	if ($gender === 'all' || $gender === '') {
		$gender = '';
	}
	if (!in_array($gender, ['men', 'women'], true)) {
		$gender = '';
	}

	$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : '';
	$min_raw = $_GET['min_price'] ?? '';
	$max_raw = $_GET['max_price'] ?? '';
	$min_price = ($min_raw !== '' && is_numeric($min_raw)) ? (float) $min_raw : null;
	$max_price = ($max_raw !== '' && is_numeric($max_raw)) ? (float) $max_raw : null;

	$color = [];
	if (!empty($_GET['color']) && is_array($_GET['color'])) {
		foreach ($_GET['color'] as $v) {
			$k = strtolower(trim((string) $v));
			if ($k === 'silver' || $k === 'rose_gold') {
				$color[] = $k;
			}
		}
		$color = array_values(array_unique($color));
	}

	$rs = 0;
	if (isset($_GET['rs']) && is_numeric($_GET['rs'])) {
		$rs = (int) $_GET['rs'];
		if ($rs < 1 || $rs > 2147483646) {
			$rs = 0;
		}
	}

	return [
		'cat' => $cat,
		'sort' => $sort,
		'gender' => $gender,
		'min_price' => $min_price,
		'max_price' => $max_price,
		'silver' => isset($_GET['silver']) && (string) $_GET['silver'] === '1',
		'sale' => isset($_GET['sale']) && (string) $_GET['sale'] === '1',
		'stock' => isset($_GET['stock']) && (string) $_GET['stock'] === '1',
		'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
		'color' => $color,
		'rs' => $rs,
	];
}

/**
 * Ensure a shuffle seed exists when the shop uses default random order (no sort, no search).
 *
 * @param array<string,mixed> $filter_state
 * @return array<string,mixed>
 */
function products_ensure_random_seed(array $filter_state): array
{
	$use_random = (($filter_state['sort'] ?? '') === '' && ($filter_state['q'] ?? '') === '');
	if (!$use_random) {
		$filter_state['rs'] = 0;
		return $filter_state;
	}
	if ((int) ($filter_state['rs'] ?? 0) <= 0) {
		$filter_state['rs'] = products_new_random_seed();
	}
	return $filter_state;
}

/** HTTP query string for filter state (pagination, wishlist return). */
function products_filters_to_query(array $f, array $overrides = []): string
{
	$m = $f;
	foreach ($overrides as $k => $v) {
		if ($v === null) {
			unset($m[$k]);
		} else {
			$m[$k] = $v;
		}
	}
	$q = [];
	if (!empty($m['cat'])) {
		$q['cat'] = $m['cat'];
	}
	if (($m['sort'] ?? '') !== '') {
		$q['sort'] = $m['sort'];
	}
	if (($m['gender'] ?? '') !== '') {
		$q['gender'] = $m['gender'];
	}
	if (isset($m['min_price']) && $m['min_price'] !== null && $m['min_price'] !== '') {
		$q['min_price'] = $m['min_price'];
	}
	if (isset($m['max_price']) && $m['max_price'] !== null && $m['max_price'] !== '') {
		$q['max_price'] = $m['max_price'];
	}
	if (!empty($m['silver'])) {
		$q['silver'] = '1';
	}
	if (!empty($m['sale'])) {
		$q['sale'] = '1';
	}
	if (!empty($m['stock'])) {
		$q['stock'] = '1';
	}
	if (($m['q'] ?? '') !== '') {
		$q['q'] = $m['q'];
	}
	if (!empty($m['color']) && is_array($m['color'])) {
		$q['color'] = $m['color'];
	}
	// Preserve shuffle seed across pagination when default (unsorted) browse is active.
	if (($m['sort'] ?? '') === '' && ($m['q'] ?? '') === '' && (int) ($m['rs'] ?? 0) > 0) {
		$q['rs'] = (int) $m['rs'];
	}
	return http_build_query($q);
}
