<?php
/**
 * Product size-guide images.
 * Rings and kadas share one chart. Necklaces and bracelets share one chart
 * once it is uploaded; until then each uses its built-in chart.
 */
declare(strict_types=1);

/** @return array{rings_kadas:string,necklaces_bracelets:string} */
function size_guide_slots(): array
{
	return [
		'rings_kadas' => 'rings-kadas',
		'necklaces_bracelets' => 'necklaces-bracelets',
	];
}

/** Built-in charts in assets/images/size-guide/. */
function size_guide_fallback_file(string $kind): string
{
	return match ($kind) {
		'bracelets' => 'Bracelet Size Guide.png',
		'necklaces' => 'Necklace Size Guide.png',
		default => 'Ring Size Guide2.png',
	};
}

function size_guide_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

	return rtrim((string) $base, '/\\') . '/storage/size-guide.settings.json';
}

/** @return array{rings_kadas:string,necklaces_bracelets:string} */
function size_guide_settings_defaults(): array
{
	return [
		'rings_kadas' => '',
		'necklaces_bracelets' => '',
	];
}

/** @return array{rings_kadas:string,necklaces_bracelets:string} */
function size_guide_settings_load(): array
{
	$defaults = size_guide_settings_defaults();
	$file = size_guide_settings_file_path();
	if (!is_file($file)) {
		return $defaults;
	}
	$raw = @file_get_contents($file);
	if (!is_string($raw) || trim($raw) === '') {
		return $defaults;
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return $defaults;
	}
	$merged = array_merge($defaults, $decoded);
	foreach (array_keys($defaults) as $key) {
		$rel = str_replace('\\', '/', trim((string) ($merged[$key] ?? '')));
		$merged[$key] = str_starts_with($rel, 'size-guide/') ? $rel : '';
	}

	return [
		'rings_kadas' => (string) $merged['rings_kadas'],
		'necklaces_bracelets' => (string) $merged['necklaces_bracelets'],
	];
}

/** @param array{rings_kadas?:string,necklaces_bracelets?:string} $settings */
function size_guide_settings_save(array $settings): bool
{
	$current = size_guide_settings_load();
	foreach (array_keys(size_guide_settings_defaults()) as $key) {
		if (!array_key_exists($key, $settings)) {
			continue;
		}
		$rel = str_replace('\\', '/', trim((string) $settings[$key]));
		$current[$key] = str_starts_with($rel, 'size-guide/') ? $rel : '';
	}

	$file = size_guide_settings_file_path();
	$dir = dirname($file);
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		return false;
	}
	$json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json)) {
		return false;
	}

	return @file_put_contents($file, $json . "\n") !== false;
}

function size_guide_upload_dir(): string
{
	$root = defined('BANNER_UPLOAD_PATH') ? (string) BANNER_UPLOAD_PATH : '';
	if ($root === '') {
		$root = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)), '/\\') . '/uploads/banners';
	}

	return rtrim(str_replace('\\', '/', $root), '/') . '/size-guide';
}

function size_guide_measure_label(string $kind): string
{
	return ($kind === 'necklaces' || $kind === 'bracelets') ? 'Length' : 'Size';
}

function size_guide_alt(string $kind): string
{
	return match ($kind) {
		'necklaces' => 'Necklace size guide',
		'bracelets' => 'Bracelet size guide',
		default => 'Ring and kada size guide',
	};
}

/**
 * rings_kadas, necklaces, or bracelets. Null for other jewellery.
 * Matches the product category, then its parent. Whole words only, so
 * "earrings" does not count as a ring.
 */
function size_guide_kind(?string $name, ?string $slug, ?string $parent_name = null, ?string $parent_slug = null): ?string
{
	$own = size_guide_kind_from_text((string) $name, (string) $slug);
	if ($own !== null) {
		return $own;
	}

	return size_guide_kind_from_text((string) $parent_name, (string) $parent_slug);
}

function size_guide_kind_from_text(string $name, string $slug): ?string
{
	$text = strtolower(trim($name . ' ' . str_replace(['-', '_'], ' ', $slug)));
	$text = preg_replace('/\s+/', ' ', $text) ?? $text;
	if ($text === '') {
		return null;
	}
	$padded = ' ' . $text . ' ';
	if (preg_match('/(?:^|[\s&\/])kadas?(?:$|[\s&\/])/u', $padded)) {
		return 'rings_kadas';
	}
	if (preg_match('/(?:^|[\s&\/])bracelets?(?:$|[\s&\/])/u', $padded)) {
		return 'bracelets';
	}
	if (preg_match('/(?:^|[\s&\/])necklaces?(?:$|[\s&\/])/u', $padded)) {
		return 'necklaces';
	}
	if (preg_match('/(?:^|[\s&\/])rings?(?:$|[\s&\/])/u', $padded)) {
		return 'rings_kadas';
	}

	return null;
}

function size_guide_slot_for_kind(string $kind): string
{
	return $kind === 'rings_kadas' ? 'rings_kadas' : 'necklaces_bracelets';
}

/** Relative banners-bucket path for an uploaded chart, or empty when using the built-in image. */
function size_guide_custom_rel(string $slot): string
{
	$settings = size_guide_settings_load();
	$rel = trim((string) ($settings[$slot] ?? ''));
	if ($rel === '') {
		return '';
	}
	$file = media_serve_resolve('banners', $rel);

	return $file === null ? '' : $rel;
}

/**
 * @return array{src:string,alt:string,custom:bool}
 */
function size_guide_image(string $kind, string $base_path): array
{
	$slot = size_guide_slot_for_kind($kind);
	$rel = size_guide_custom_rel($slot);
	if ($rel !== '') {
		$uploads = function_exists('pearlica_uploads_banners_base')
			? pearlica_uploads_banners_base($base_path)
			: rtrim($base_path, '/') . '/uploads/banners';

		return [
			'src' => media_storage_public_url($uploads, $rel, 'banners'),
			'alt' => size_guide_alt($kind),
			'custom' => true,
		];
	}

	$file = size_guide_fallback_file($kind);
	$relative = 'assets/images/size-guide/' . $file;
	$root = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);
	$disk = $root . '/' . $relative;
	$prefix = $base_path;
	if ($prefix !== '' && !str_ends_with($prefix, '/')) {
		$prefix .= '/';
	}
	$url = $prefix . 'assets/images/size-guide/' . rawurlencode($file);
	$mtime = is_file($disk) ? @filemtime($disk) : false;
	if ($mtime) {
		$url .= '?v=' . (string) (int) $mtime;
	}

	return [
		'src' => $url,
		'alt' => size_guide_alt($kind),
		'custom' => false,
	];
}

/**
 * @param array{tmp_name?:string,size?:int,error?:int,name?:string} $file
 * @return array{ok:bool,error?:string,rel?:string}
 */
function size_guide_store_upload(string $slot, array $file): array
{
	$slots = size_guide_slots();
	if (!isset($slots[$slot])) {
		return ['ok' => false, 'error' => 'Unknown size guide.'];
	}
	if (!function_exists('banner_image_validate_upload')) {
		require_once __DIR__ . '/banner_image.php';
	}
	$validated = banner_image_validate_upload($file);
	if (empty($validated['ok'])) {
		return ['ok' => false, 'error' => (string) ($validated['error'] ?? 'Invalid upload')];
	}

	$dir = size_guide_upload_dir();
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		return ['ok' => false, 'error' => 'Could not create the size guide folder.'];
	}

	$ext = (string) $validated['ext'];
	$filename = $slots[$slot] . '.' . $ext;
	$dest = $dir . '/' . $filename;
	size_guide_delete_slot_files($slot);
	$stored = banner_image_store_from_upload((string) $file['tmp_name'], $dest, (string) $validated['mime']);
	if (empty($stored['ok'])) {
		return ['ok' => false, 'error' => (string) ($stored['error'] ?? 'Could not save image.')];
	}

	$rel = 'size-guide/' . $filename;
	if (!size_guide_settings_save([$slot => $rel])) {
		@unlink($dest);

		return ['ok' => false, 'error' => 'Image saved, but settings could not be updated.'];
	}

	return ['ok' => true, 'rel' => $rel];
}

function size_guide_clear_upload(string $slot): bool
{
	if (!isset(size_guide_slots()[$slot])) {
		return false;
	}
	size_guide_delete_slot_files($slot);

	return size_guide_settings_save([$slot => '']);
}

function size_guide_delete_slot_files(string $slot): void
{
	$slots = size_guide_slots();
	if (!isset($slots[$slot])) {
		return;
	}
	$dir = size_guide_upload_dir();
	foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
		$path = $dir . '/' . $slots[$slot] . '.' . $ext;
		if (is_file($path)) {
			@unlink($path);
		}
	}
}
