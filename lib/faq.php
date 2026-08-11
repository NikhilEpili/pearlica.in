<?php
/**
 * About-page FAQs (admin-editable).
 */

function faq_default_rows(): array
{
	return [
		['q' => 'Is Pearlica jewellery made of real 925 silver?', 'a' => 'Yes. All Pearlica pieces are crafted from certified 925 sterling silver, containing 92.5% pure silver blended with strengthening alloys for durability.'],
		['q' => 'Will 925 silver tarnish over time?', 'a' => 'Silver may naturally oxidize when exposed to moisture, perfumes, or air. With proper care and storage, your jewellery will maintain its shine for a long time.'],
		['q' => 'Does Pearlica provide an authenticity confirmation?', 'a' => 'Yes, every order includes confirmation of 925 sterling silver quality along with secure packaging.'],
		['q' => 'Do I pay before my order ships?', 'a' => 'You can pay securely online at checkout (UPI, cards, net banking), or choose Cash on Delivery (COD) where available. Prepaid orders are confirmed after successful payment; COD orders are confirmed when placed.'],
		['q' => 'How long does delivery take?', 'a' => 'Orders are usually dispatched within 2–3 working days. Delivery timelines vary depending on your location within India.'],
		['q' => 'Can I exchange my jewellery?', 'a' => 'Yes. Your first exchange within 7 days of delivery is free with doorstep pickup, subject to our Return, exchange, cancellation & refund policy.'],
		['q' => 'Are earrings returnable?', 'a' => 'For hygiene reasons, earrings are not eligible for return or exchange unless received in damaged condition.'],
		['q' => 'How should I store my silver jewellery?', 'a' => 'Store your jewellery in a dry place, preferably in an airtight pouch. Avoid contact with water, perfumes, or harsh chemicals.'],
		['q' => 'Do you ship internationally?', 'a' => 'Currently, Pearlica delivers within India only.'],
		['q' => 'Is 925 silver suitable for daily wear?', 'a' => 'Yes, 925 sterling silver is durable for everyday use. However, removing jewellery before swimming, bathing, or gym activities is recommended.'],
		['q' => 'Will the jewellery cause skin irritation?', 'a' => '925 silver is generally skin-friendly and suitable for most people. Those with specific metal sensitivities should review material details before purchase.'],
		['q' => 'How do I know my ring size?', 'a' => 'You can refer to our size guide on the website. Measuring an existing ring is the easiest way to determine your correct size.'],
		['q' => 'Do you offer resizing services?', 'a' => 'Currently, resizing is not available for all products. Please review product details before purchasing.'],
		['q' => 'What payment methods do you accept?', 'a' => 'We accept UPI, debit and credit cards, and net banking through our checkout.'],
		['q' => 'Can I change my address after placing the order?', 'a' => 'Address changes may be possible before dispatch. Please contact us immediately after placing your order.'],
		['q' => 'What if my product arrives damaged?', 'a' => 'If you receive a damaged item, please record a clear unboxing video and contact us within 48 hours of delivery.'],
		['q' => 'Do you offer gift packaging?', 'a' => 'Yes, gift-ready packaging is available at an additional charge. Please select the gift packaging option on the Checkout Page or contact us for confirmation before placing your order.'],
		['q' => 'How should I clean my 925 silver jewellery?', 'a' => 'Gently polish using a soft, dry cloth. Avoid harsh chemicals or abrasive materials.'],
		['q' => 'Is Pearlica jewellery 925 sterling silver?', 'a' => 'Yes. Our pieces are crafted to 925 sterling silver standards. Any extra product-specific details appear on the individual product page.'],
		['q' => 'Do you restock sold-out products?', 'a' => 'Some designs may be restocked depending on availability. Please contact us for restock updates.'],
		['q' => 'Can I cancel my order?', 'a' => 'You can cancel within 24 hours of placing the order, or any time before it is dispatched. Once shipped, cancellation is not possible — see our Return, exchange, cancellation & refund policy.'],
		['q' => 'Is Pearlica jewellery waterproof?', 'a' => 'Silver jewellery is not recommended for prolonged water exposure. Frequent contact with water may affect shine over time.'],
		['q' => 'Do you offer bulk or corporate gifting options?', 'a' => 'Yes, for bulk or corporate gifting inquiries, please contact us directly.'],
		['q' => 'How long will my silver jewellery last?', 'a' => 'With proper care and storage, 925 sterling silver jewellery can last for many years.'],
		[
			'q' => 'How can I contact customer support?',
			'a' => 'You can reach us at: contact@pearlica.in, +91 9699518449, Monday – Saturday | 10 AM – 6 PM.',
			'a_html' => 'You can reach us at: <a href="mailto:contact@pearlica.in">contact@pearlica.in</a>, <a href="tel:+919699518449">+91 9699518449</a>, Monday – Saturday | 10 AM – 6 PM.',
		],
	];
}

/**
 * Create faqs table (+ can_faqs permission) if missing, and seed defaults when empty.
 */
function faq_ensure_schema(PDO $pdo): void
{
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `faqs` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `question` VARCHAR(512) NOT NULL,
		  `answer` TEXT NOT NULL,
		  `answer_html` TEXT NULL DEFAULT NULL,
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
		  INDEX `idx_faqs_list` (`is_active`, `sort_order`, `deleted_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_faqs'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		try {
			$pdo->exec(
				'ALTER TABLE `admin_user_permissions`
				 ADD COLUMN `can_faqs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_banners`'
			);
		} catch (Throwable $e) {
			try {
				$pdo->exec(
					'ALTER TABLE `admin_user_permissions`
					 ADD COLUMN `can_faqs` TINYINT(1) NOT NULL DEFAULT 1'
				);
			} catch (Throwable $e2) {
				// Permission column optional — super admins can still manage FAQs.
			}
		}
	}

	$count = (int) $pdo->query('SELECT COUNT(*) FROM faqs WHERE deleted_at IS NULL')->fetchColumn();
	if ($count === 0) {
		faq_seed_defaults($pdo);
	} else {
		// One-time copy fixes for live FAQ rows.
		try {
			$fix = $pdo->prepare(
				"UPDATE faqs
				 SET answer = REPLACE(
				   answer,
				   'select the gift packaging option on the product page',
				   'select the gift packaging option on the Checkout Page'
				 )
				 WHERE deleted_at IS NULL
				   AND answer LIKE '%gift packaging option on the product page%'"
			);
			$fix->execute();

			$pdo->prepare(
				"UPDATE faqs
				 SET answer = ?
				 WHERE deleted_at IS NULL
				   AND question LIKE 'Do I pay before my order ships%'
				   AND answer LIKE '%Payment is collected securely online%'"
			)->execute([
				'You can pay securely online at checkout (UPI, cards, net banking), or choose Cash on Delivery (COD) where available. Prepaid orders are confirmed after successful payment; COD orders are confirmed when placed.',
			]);

			$pdo->prepare(
				"UPDATE faqs
				 SET answer = REPLACE(answer, 'within 24 hours of delivery', 'within 48 hours of delivery')
				 WHERE deleted_at IS NULL
				   AND (
				     question LIKE '%damaged%'
				     OR answer LIKE '%damaged item%'
				     OR answer LIKE '%unboxing video%'
				   )
				   AND answer LIKE '%within 24 hours of delivery%'"
			)->execute();
		} catch (Throwable $e) {
			// ignore
		}
	}
}

function faq_seed_defaults(PDO $pdo): void
{
	$stmt = $pdo->prepare(
		'INSERT INTO faqs (question, answer, answer_html, sort_order, is_active)
		 VALUES (?, ?, ?, ?, 1)'
	);
	foreach (faq_default_rows() as $i => $row) {
		$stmt->execute([
			(string) $row['q'],
			(string) $row['a'],
			isset($row['a_html']) ? (string) $row['a_html'] : null,
			(int) $i,
		]);
	}
}

function faq_list_all(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, question, answer, answer_html, sort_order, is_active, created_at, updated_at
		 FROM faqs
		 WHERE deleted_at IS NULL
		 ORDER BY sort_order ASC, id ASC'
	);
	return $stmt->fetchAll();
}

function faq_list_active(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, question, answer, answer_html, sort_order
		 FROM faqs
		 WHERE deleted_at IS NULL AND is_active = 1
		 ORDER BY sort_order ASC, id ASC'
	);
	return $stmt->fetchAll();
}

function faq_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare(
		'SELECT id, question, answer, answer_html, sort_order, is_active
		 FROM faqs
		 WHERE id = ? AND deleted_at IS NULL
		 LIMIT 1'
	);
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function faq_next_sort_order(PDO $pdo): int
{
	$stmt = $pdo->query(
		'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM faqs WHERE deleted_at IS NULL'
	);
	return (int) $stmt->fetchColumn();
}

function faq_insert(
	PDO $pdo,
	string $question,
	string $answer,
	?string $answer_html = null,
	?int $sort_order = null,
	bool $is_active = true
): int {
	if ($sort_order === null) {
		$sort_order = faq_next_sort_order($pdo);
	}
	$html = $answer_html !== null ? trim($answer_html) : '';
	$stmt = $pdo->prepare(
		'INSERT INTO faqs (question, answer, answer_html, sort_order, is_active)
		 VALUES (?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		trim($question),
		trim($answer),
		$html !== '' ? $html : null,
		(int) $sort_order,
		$is_active ? 1 : 0,
	]);
	return (int) $pdo->lastInsertId();
}

function faq_update(
	PDO $pdo,
	int $id,
	string $question,
	string $answer,
	?string $answer_html,
	int $sort_order,
	bool $is_active
): bool {
	$html = $answer_html !== null ? trim($answer_html) : '';
	$stmt = $pdo->prepare(
		'UPDATE faqs
		 SET question = ?, answer = ?, answer_html = ?, sort_order = ?, is_active = ?
		 WHERE id = ? AND deleted_at IS NULL'
	);
	return $stmt->execute([
		trim($question),
		trim($answer),
		$html !== '' ? $html : null,
		(int) $sort_order,
		$is_active ? 1 : 0,
		$id,
	]);
}

function faq_soft_delete(PDO $pdo, int $id): bool
{
	$stmt = $pdo->prepare('UPDATE faqs SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
	return $stmt->execute([$id]);
}

/**
 * Shape used by the About accordion component.
 *
 * @return list<array{q: string, a: string, a_html?: string}>
 */
function faq_for_accordion(PDO $pdo): array
{
	$rows = faq_list_active($pdo);
	$out = [];
	foreach ($rows as $row) {
		$item = [
			'q' => (string) ($row['question'] ?? ''),
			'a' => (string) ($row['answer'] ?? ''),
		];
		$html = trim((string) ($row['answer_html'] ?? ''));
		if ($html !== '') {
			$item['a_html'] = $html;
		}
		if ($item['q'] !== '') {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Hardcoded fallback (used when DB is unavailable). Includes corrected gift packaging copy.
 *
 * @return list<array{q: string, a: string, a_html?: string}>
 */
function faq_fallback_accordion(): array
{
	$out = [];
	foreach (faq_default_rows() as $row) {
		$item = ['q' => $row['q'], 'a' => $row['a']];
		if (!empty($row['a_html'])) {
			$item['a_html'] = $row['a_html'];
		}
		$out[] = $item;
	}
	return $out;
}
