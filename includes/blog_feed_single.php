<?php
/**
 * Single-post blog helpers (slug resolution, body HTML, find by slug).
 * Loaded before `blog_feed.php` from `blogs/post.php` so partial deploys still work.
 */
declare(strict_types=1);

if (!function_exists('pearlica_blog_feed_row_slug')) {
	/**
	 * @param array<string, mixed> $p
	 */
	function pearlica_blog_feed_row_slug(array $p, string $site_base): string
	{
		$slug = trim((string) ($p['slug'] ?? $p['path'] ?? ''));
		if ($slug !== '') {
			return trim(str_replace('\\', '/', $slug), '/');
		}
		$href = trim((string) ($p['url'] ?? $p['link'] ?? $p['permalink'] ?? ''));
		if ($href === '') {
			return '';
		}
		if (!preg_match('#^https?://#i', $href)) {
			$href = rtrim($site_base, '/') . '/' . ltrim($href, '/');
		}
		$path = parse_url($href, PHP_URL_PATH);
		if (!is_string($path) || $path === '' || $path === '/') {
			return '';
		}
		$base_path = parse_url(rtrim($site_base, '/') . '/', PHP_URL_PATH);
		$base_path = is_string($base_path) ? rtrim($base_path, '/') : '';
		if ($base_path !== '' && str_starts_with($path, $base_path)) {
			$rest = trim(substr($path, strlen($base_path)), '/');
			return $rest !== '' ? $rest : trim(basename($path), '/');
		}
		return trim(basename($path), '/');
	}
}

if (!function_exists('pearlica_blog_feed_sanitize_body_html')) {
	function pearlica_blog_feed_sanitize_body_html(string $html): string
	{
		$out = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
		$out = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $out) ?? $out;
		$out = preg_replace('#\s*on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $out) ?? $out;
		return $out;
	}
}

if (!function_exists('pearlica_blog_feed_row_body_html')) {
	/**
	 * @param array<string, mixed> $p
	 */
	function pearlica_blog_feed_row_body_html(array $p): string
	{
		foreach (['html', 'body_html', 'content_html', 'rendered_html', 'article_html', 'body', 'content', 'markdown'] as $key) {
			$raw = $p[$key] ?? null;
			if (!is_string($raw) || trim($raw) === '') {
				continue;
			}
			$t = trim($raw);
			if ($key === 'markdown') {
				$escaped = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
				return '<div class="blogs-post-markdown"><pre class="blogs-post-pre">' . $escaped . '</pre></div>';
			}
			if (str_contains($t, '<')) {
				return pearlica_blog_feed_sanitize_body_html($t);
			}
			$paras = array_filter(array_map('trim', preg_split('/\R{2,}/u', $t) ?: []));
			if ($paras === []) {
				return '<p class="blogs-post-plain">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</p>';
			}
			$blocks = [];
			foreach ($paras as $para) {
				$blocks[] = '<p class="blogs-post-plain">' . nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) . '</p>';
			}
			return implode("\n", $blocks);
		}
		return '';
	}
}

if (!function_exists('pearlica_blog_feed_find_post_by_slug')) {
	/**
	 * @return array{
	 *   ok: bool,
	 *   error: string|null,
	 *   site_title: string,
	 *   site_base: string,
	 *   post: array{
	 *     title: string,
	 *     slug: string,
	 *     href: string,
	 *     excerpt: string,
	 *     date_label: string,
	 *     image: string,
	 *     body_html: string
	 *   }|null
	 * }
	 */
	function pearlica_blog_feed_find_post_by_slug(string $feed_url, string $slug): array
	{
		$empty = [
			'ok' => false,
			'error' => null,
			'site_title' => 'Pearlica',
			'site_base' => 'https://pearlica.in/blogs/',
			'post' => null,
		];
		$want = trim(str_replace('\\', '/', $slug), '/');
		if ($want === '' || !preg_match('#^[A-Za-z0-9][A-Za-z0-9_-]*$#', $want)) {
			$empty['error'] = 'Invalid article.';
			return $empty;
		}
		if (!function_exists('pearlica_blog_feed_http_get')) {
			$empty['error'] = 'Blog feed helpers are not loaded.';
			return $empty;
		}
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
		$site = $data['site'] ?? [];
		$site_title = is_array($site) ? trim((string) ($site['title'] ?? 'Pearlica')) : 'Pearlica';
		$site_base = is_array($site) ? trim((string) ($site['base_url'] ?? 'https://pearlica.in/blogs/')) : 'https://pearlica.in/blogs/';
		if ($site_base === '') {
			$site_base = 'https://pearlica.in/blogs/';
		}
		if (!preg_match('#/$#', $site_base)) {
			$site_base .= '/';
		}
		$empty['site_title'] = $site_title;
		$empty['site_base'] = $site_base;

		$raw_posts = $data['posts'] ?? null;
		if (!is_array($raw_posts)) {
			$empty['error'] = 'Blog feed had no posts list.';
			return $empty;
		}
		foreach ($raw_posts as $row) {
			if (!is_array($row)) {
				continue;
			}
			$row_slug = pearlica_blog_feed_row_slug($row, $site_base);
			if ($row_slug === '' || strcasecmp($row_slug, $want) !== 0) {
				continue;
			}
			if (!function_exists('pearlica_blog_feed_normalize_post')) {
				$empty['error'] = 'Blog feed helpers are not loaded.';
				return $empty;
			}
			$norm = pearlica_blog_feed_normalize_post($row, $site_base);
			if ($norm === null) {
				continue;
			}
			$body_html = pearlica_blog_feed_row_body_html($row);
			if ($body_html === '' && $norm['excerpt'] !== '') {
				$body_html = '<p class="blogs-post-lead">' . htmlspecialchars($norm['excerpt'], ENT_QUOTES, 'UTF-8') . '</p>';
			}
			$slug_out = $norm['slug'] ?? $row_slug;
			return [
				'ok' => true,
				'error' => null,
				'site_title' => $site_title,
				'site_base' => $site_base,
				'post' => [
					'title' => $norm['title'],
					'slug' => is_string($slug_out) ? $slug_out : $row_slug,
					'href' => $norm['href'],
					'excerpt' => $norm['excerpt'],
					'date_label' => $norm['date_label'],
					'image' => $norm['image'],
					'body_html' => $body_html,
				],
			];
		}
		$empty['error'] = 'This article could not be found.';
		return $empty;
	}
}
