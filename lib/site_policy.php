<?php
/**
 * Storefront legal / care policy pages (admin-editable HTML bodies).
 */

function site_policy_definitions(): array
{
	return [
		'return-exchange-policy' => [
			'title' => 'Return, exchange, cancellation & refund policy',
			'lead' => 'Pearlica – 925 Sterling Silver Jewellery',
			'effective_note' => 'Effective date: 18 February 2026 · Last reviewed: March 2026',
			'meta_title' => 'Return & exchange policy | Pearlica 925 silver jewellery India',
			'meta_description' => 'Read Pearlica’s return, exchange, cancellation and refund policy. 7-day free exchange, 24-hour damage claims, 5–7 day refunds. Shop with confidence.',
			'sort_order' => 10,
		],
		'shipping-policy' => [
			'title' => 'Shipping policy',
			'lead' => 'Pearlica – 925 Sterling Silver Jewellery',
			'effective_note' => 'Last updated: 18 February 2026',
			'meta_title' => 'Shipping policy | Pearlica 925 silver jewellery India',
			'meta_description' => 'Read Pearlica’s shipping policy. Learn about delivery timelines, free shipping above ₹700, COD availability, order tracking, and more.',
			'sort_order' => 20,
		],
		'pearlica-care' => [
			'title' => 'Pearlica Care Promise',
			'lead' => 'Pearlica – 925 Sterling Silver Jewellery',
			'effective_note' => 'Last updated: March 2026',
			'meta_title' => 'Pearlica Care Promise | 6-Month Plating Warranty & Anti-Tarnish Guarantee',
			'meta_description' => 'Pearlica offers a 6-month plating warranty and anti-tarnish guarantee on all rose gold and rhodium plated 925 silver jewellery. Shop with confidence.',
			'sort_order' => 30,
		],
		'care-guide' => [
			'title' => 'Jewellery care guide',
			'lead' => 'Pearlica – 925 Sterling Silver Jewellery',
			'effective_note' => 'Last updated: March 2026',
			'meta_title' => 'Jewellery care guide | Pearlica 925 silver jewellery India',
			'meta_description' => 'Learn how to care for your Pearlica 925 sterling silver, rose gold plated, and rhodium plated jewellery. Tips for cleaning, storing, and maintaining your jewellery’s shine.',
			'sort_order' => 40,
		],
		'privacy-policy' => [
			'title' => 'Privacy Policy',
			'lead' => '',
			'effective_note' => 'Last updated: 18 February 2026',
			'meta_title' => 'Privacy policy | Pearlica India',
			'meta_description' => 'Read Pearlica’s Privacy Policy to understand how we collect, use, and protect your personal information on our website in India.',
			'sort_order' => 50,
		],
		'terms-conditions' => [
			'title' => 'Terms & conditions',
			'lead' => 'Pearlica – 925 Sterling Silver Jewellery',
			'effective_note' => 'Effective date: 18 February 2026',
			'meta_title' => 'Terms & conditions | Pearlica 925 silver jewellery India',
			'meta_description' => 'Read Pearlica’s Terms & Conditions for 925 silver jewellery purchases. Learn about orders, pricing, shipping, returns, and governing law.',
			'sort_order' => 60,
		],
	];
}

function site_policy_ensure_schema(PDO $pdo): void
{
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `site_policies` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `slug` VARCHAR(64) NOT NULL,
		  `title` VARCHAR(255) NOT NULL,
		  `lead_text` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `effective_note` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `meta_title` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `meta_description` VARCHAR(512) NOT NULL DEFAULT \'\',
		  `body_html` MEDIUMTEXT NOT NULL,
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  UNIQUE KEY `uq_site_policies_slug` (`slug`),
		  INDEX `idx_site_policies_sort` (`sort_order`, `slug`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_policies'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		try {
			$pdo->exec(
				'ALTER TABLE `admin_user_permissions`
				 ADD COLUMN `can_policies` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_faqs`'
			);
		} catch (Throwable $e) {
			try {
				$pdo->exec(
					'ALTER TABLE `admin_user_permissions`
					 ADD COLUMN `can_policies` TINYINT(1) NOT NULL DEFAULT 1'
				);
			} catch (Throwable $e2) {
				// optional
			}
		}
	}

	site_policy_seed_missing($pdo);
	site_policy_scrub_policy_copy($pdo);
}

function site_policy_defaults_dir(): string
{
	return dirname(__DIR__) . '/policies/defaults';
}

function site_policy_load_default_body(string $slug): string
{
	$path = site_policy_defaults_dir() . '/' . $slug . '.html';
	if (!is_file($path)) {
		return '';
	}
	$body = (string) file_get_contents($path);
	return trim($body);
}

function site_policy_seed_missing(PDO $pdo): void
{
	$existing = [];
	foreach ($pdo->query('SELECT slug FROM site_policies')->fetchAll(PDO::FETCH_COLUMN) as $slug) {
		$existing[(string) $slug] = true;
	}
	$ins = $pdo->prepare(
		'INSERT INTO site_policies
		 (slug, title, lead_text, effective_note, meta_title, meta_description, body_html, sort_order)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
	);
	foreach (site_policy_definitions() as $slug => $meta) {
		if (isset($existing[$slug])) {
			continue;
		}
		$body = site_policy_load_default_body($slug);
		if ($body === '') {
			continue;
		}
		$ins->execute([
			$slug,
			$meta['title'],
			$meta['lead'],
			$meta['effective_note'],
			$meta['meta_title'],
			$meta['meta_description'],
			$body,
			(int) $meta['sort_order'],
		]);
	}
}

/**
 * One-time copy fixes for stored policy HTML (clearance clause, damage-claim window).
 */
function site_policy_scrub_policy_copy(PDO $pdo): void
{
	try {
		$rows = $pdo->query(
			"SELECT id, slug, body_html FROM site_policies
			 WHERE body_html LIKE '%clearance sales%'
			    OR body_html LIKE '%special promotional offer%'
			    OR body_html LIKE '%special promotions%'
			    OR body_html LIKE '%within <strong>24 hours</strong> of delivery%'
			    OR body_html LIKE '%Contact us within 24 hours of receipt%'"
		)->fetchAll();
	} catch (Throwable $e) {
		return;
	}
	$upd = $pdo->prepare('UPDATE site_policies SET body_html = ? WHERE id = ?');
	foreach ($rows as $row) {
		$html = (string) ($row['body_html'] ?? '');
		$html = preg_replace(
			'/\s*<li>\s*Items purchased during clearance sales or special promotional offers\s*<\/li>/iu',
			'',
			$html
		) ?? $html;
		$html = preg_replace(
			'/\s*<li>\s*Warranty does not apply to items purchased during clearance sales or special promotions\s*<\/li>/iu',
			'',
			$html
		) ?? $html;
		// Damage / unboxing claims: unify to 48 hours (keep order-cancellation 24h wording).
		$html = str_replace(
			'Contact us within <strong>24 hours</strong> of delivery with the video and photographs',
			'Contact us within <strong>48 hours</strong> of delivery with the video and photographs',
			$html
		);
		$html = str_replace(
			'Contact us within 24 hours of receipt',
			'Contact us within 48 hours of receipt',
			$html
		);
		$upd->execute([$html, (int) $row['id']]);
	}
}

function site_policy_list_all(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, slug, title, lead_text, effective_note, meta_title, meta_description, sort_order, updated_at,
		        CHAR_LENGTH(body_html) AS body_len
		 FROM site_policies
		 ORDER BY sort_order ASC, id ASC'
	);
	return $stmt->fetchAll();
}

function site_policy_find_by_slug(PDO $pdo, string $slug): ?array
{
	$slug = trim($slug);
	if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
		return null;
	}
	$stmt = $pdo->prepare(
		'SELECT id, slug, title, lead_text, effective_note, meta_title, meta_description, body_html, sort_order, updated_at
		 FROM site_policies WHERE slug = ? LIMIT 1'
	);
	$stmt->execute([$slug]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function site_policy_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare(
		'SELECT id, slug, title, lead_text, effective_note, meta_title, meta_description, body_html, sort_order, updated_at
		 FROM site_policies WHERE id = ? LIMIT 1'
	);
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function site_policy_update(
	PDO $pdo,
	int $id,
	string $title,
	string $lead_text,
	string $effective_note,
	string $meta_title,
	string $meta_description,
	string $body_html
): bool {
	$stmt = $pdo->prepare(
		'UPDATE site_policies
		 SET title = ?, lead_text = ?, effective_note = ?, meta_title = ?, meta_description = ?, body_html = ?
		 WHERE id = ?'
	);
	return $stmt->execute([
		trim($title),
		trim($lead_text),
		trim($effective_note),
		trim($meta_title),
		trim($meta_description),
		$body_html,
		$id,
	]);
}

function site_policy_render_body(string $body_html, string $base_path): string
{
	return str_replace('{{BASE_PATH}}', $base_path, $body_html);
}

/**
 * Load policy for a storefront page. Falls back to defaults HTML if DB row missing.
 *
 * @return array{title: string, lead_text: string, effective_note: string, meta_title: string, meta_description: string, body_html: string}|null
 */
function site_policy_for_page(?PDO $pdo, string $slug): ?array
{
	$defs = site_policy_definitions();
	if (!isset($defs[$slug])) {
		return null;
	}
	$meta = $defs[$slug];
	$row = null;
	if ($pdo instanceof PDO) {
		try {
			site_policy_ensure_schema($pdo);
			$row = site_policy_find_by_slug($pdo, $slug);
		} catch (Throwable $e) {
			$row = null;
		}
	}
	if ($row) {
		return [
			'title' => (string) $row['title'],
			'lead_text' => (string) ($row['lead_text'] ?? ''),
			'effective_note' => (string) ($row['effective_note'] ?? ''),
			'meta_title' => (string) ($row['meta_title'] ?? $meta['meta_title']),
			'meta_description' => (string) ($row['meta_description'] ?? $meta['meta_description']),
			'body_html' => (string) $row['body_html'],
		];
	}
	$body = site_policy_load_default_body($slug);
	if ($body === '') {
		return null;
	}
	return [
		'title' => $meta['title'],
		'lead_text' => $meta['lead'],
		'effective_note' => $meta['effective_note'],
		'meta_title' => $meta['meta_title'],
		'meta_description' => $meta['meta_description'],
		'body_html' => $body,
	];
}
