<?php
/**
 * Fetch and normalize the public Pearlica blog JSON feed (same shape as /blogs/rss).
 */
declare(strict_types=1);

/**
 * @return string|null Response body or null on failure
 */
function pearlica_blog_feed_http_get(string $url, int $timeout_sec = 10): ?string
{
	$timeout_sec = max(1, min(30, $timeout_sec));
	$headers = "Accept: application/json, text/plain, */*\r\nUser-Agent: PearlicaStorefront/1.0\r\n";
	$ctx = stream_context_create([
		'http' => [
			'timeout' => $timeout_sec,
			'ignore_errors' => true,
			'header' => $headers,
		],
		'ssl' => [
			'verify_peer' => true,
			'verify_peer_name' => true,
		],
	]);
	$body = @file_get_contents($url, false, $ctx);
	if (is_string($body) && $body !== '') {
		return $body;
	}
	if (!function_exists('curl_init')) {
		return null;
	}
	$ch = curl_init($url);
	if ($ch === false) {
		return null;
	}
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => $timeout_sec,
		CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*', 'User-Agent: PearlicaStorefront/1.0'],
	]);
	$out = curl_exec($ch);
	curl_close($ch);
	return is_string($out) && $out !== '' ? $out : null;
}

function pearlica_blog_feed_truncate_plain(string $text, int $max_len = 180): string
{
	$t = preg_replace('/\s+/u', ' ', trim(strip_tags($text)));
	if ($t === '') {
		return '';
	}
	if (function_exists('mb_strlen') && function_exists('mb_substr')) {
		if (mb_strlen($t, 'UTF-8') <= $max_len) {
			return $t;
		}
		return rtrim(mb_substr($t, 0, $max_len - 1, 'UTF-8')) . '…';
	}
	if (strlen($t) <= $max_len) {
		return $t;
	}
	return rtrim(substr($t, 0, $max_len - 1)) . '…';
}

require_once __DIR__ . '/blog_feed_single.php';

/**
 * @param array<string, mixed> $p
 * @return array{title: string, href: string, excerpt: string, date_label: string, image: string, slug: string}|null
 */
function pearlica_blog_feed_normalize_post(array $p, string $site_base): ?array
{
	$title = trim((string) ($p['title'] ?? $p['name'] ?? ''));
	if ($title === '') {
		return null;
	}
	$slug = pearlica_blog_feed_row_slug($p, $site_base);
	$href = trim((string) ($p['url'] ?? $p['link'] ?? $p['permalink'] ?? ''));
	if ($href === '') {
		if ($slug !== '') {
			$href = rtrim($site_base, '/') . '/' . ltrim($slug, '/');
		}
	} elseif (!preg_match('#^https?://#i', $href)) {
		$href = rtrim($site_base, '/') . '/' . ltrim($href, '/');
	}
	if ($href === '') {
		return null;
	}
	$raw_excerpt = (string) ($p['excerpt'] ?? $p['summary'] ?? $p['description'] ?? $p['deck'] ?? '');
	if ($raw_excerpt === '') {
		$raw_excerpt = (string) ($p['content'] ?? $p['body'] ?? $p['html'] ?? $p['markdown'] ?? '');
	}
	$excerpt = pearlica_blog_feed_truncate_plain($raw_excerpt);

	$date_raw = $p['published_at'] ?? $p['published'] ?? $p['date'] ?? $p['created_at'] ?? '';
	$date_label = '';
	if (is_string($date_raw) && $date_raw !== '') {
		try {
			$dt = new DateTimeImmutable($date_raw);
			$date_label = $dt->format('j M Y');
		} catch (Throwable $e) {
			$date_label = $date_raw;
		}
	}

	$img = trim((string) ($p['image'] ?? $p['cover_image'] ?? $p['hero_image'] ?? $p['featured_image'] ?? $p['thumbnail'] ?? ''));
	if ($img !== '' && !preg_match('#^https?://#i', $img)) {
		$img = rtrim($site_base, '/') . '/' . ltrim($img, '/');
	}

	return [
		'title' => $title,
		'href' => $href,
		'excerpt' => $excerpt,
		'date_label' => $date_label,
		'image' => $img,
		'slug' => $slug,
	];
}

/**
 * @return array{
 *   ok: bool,
 *   error: string|null,
 *   site_title: string,
 *   site_base: string,
 *   site_description: string,
 *   generated_at: string|null,
 *   posts: list<array{title: string, href: string, excerpt: string, date_label: string, image: string, slug: string}>
 * }
 */
function pearlica_blog_feed_from_array(array $data): array
{
	$empty = [
		'ok' => false,
		'error' => null,
		'site_title' => 'Pearlica',
		'site_base' => 'https://pearlica.in/blogs/',
		'site_description' => '',
		'generated_at' => null,
		'posts' => [],
	];
	$site = $data['site'] ?? [];
	$site_title = is_array($site) ? trim((string) ($site['title'] ?? 'Pearlica')) : 'Pearlica';
	$site_base = is_array($site) ? trim((string) ($site['base_url'] ?? 'https://pearlica.in/blogs/')) : 'https://pearlica.in/blogs/';
	if ($site_base === '') {
		$site_base = 'https://pearlica.in/blogs/';
	}
	if (!preg_match('#/$#', $site_base)) {
		$site_base .= '/';
	}
	$site_description = is_array($site) ? trim((string) ($site['description'] ?? '')) : '';
	$generated_at = isset($data['generated_at']) && is_string($data['generated_at']) ? $data['generated_at'] : null;

	$raw_posts = $data['posts'] ?? null;
	if (!is_array($raw_posts)) {
		$empty['error'] = 'Blog feed had no posts list.';
		$empty['site_title'] = $site_title;
		$empty['site_base'] = $site_base;
		$empty['site_description'] = $site_description;
		$empty['generated_at'] = $generated_at;
		return $empty;
	}
	$posts = [];
	foreach ($raw_posts as $row) {
		if (!is_array($row)) {
			continue;
		}
		$n = pearlica_blog_feed_normalize_post($row, $site_base);
		if ($n !== null) {
			$posts[] = $n;
		}
	}
	return [
		'ok' => true,
		'error' => null,
		'site_title' => $site_title,
		'site_base' => $site_base,
		'site_description' => $site_description,
		'generated_at' => $generated_at,
		'posts' => $posts,
	];
}

function pearlica_blog_feed_load(string $feed_url): array
{
	$empty = [
		'ok' => false,
		'error' => null,
		'site_title' => 'Pearlica',
		'site_base' => 'https://pearlica.in/blogs/',
		'site_description' => '',
		'generated_at' => null,
		'posts' => [],
	];
	$body = pearlica_blog_feed_http_get($feed_url);
	if ($body === null) {
		$empty['error'] = 'Could not load the blog feed. Please try again later.';
		return $empty;
	}
	$data = json_decode($body, true);
	if (!is_array($data)) {
		$empty['error'] = 'Blog feed was not valid JSON.';
		return $empty;
	}
	return pearlica_blog_feed_from_array($data);
}
