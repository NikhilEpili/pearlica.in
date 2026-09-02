<?php
/**
 * Homepage banner image storage: preserve upload quality; only downscale oversized masters.
 */
declare(strict_types=1);

if (!defined('BANNER_MAX_STORE_WIDTH')) {
	require_once dirname(__DIR__) . '/config/config.php';
}

/**
 * @return array{ok:bool,width:int,height:int,error?:string}
 */
function banner_image_store_from_upload(string $tmp_path, string $dest_path, string $mime): array
{
	if (!is_file($tmp_path)) {
		return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Upload file missing.'];
	}

	$info = @getimagesize($tmp_path);
	if ($info === false) {
		return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not read image dimensions.'];
	}

	$src_w = (int) ($info[0] ?? 0);
	$src_h = (int) ($info[1] ?? 0);
	if ($src_w <= 0 || $src_h <= 0) {
		return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Invalid image dimensions.'];
	}

	$max_w = defined('BANNER_MAX_STORE_WIDTH') ? (int) BANNER_MAX_STORE_WIDTH : 3840;
	if ($max_w <= 0) {
		$max_w = 3840;
	}

	if ($src_w <= $max_w) {
		if (!@copy($tmp_path, $dest_path)) {
			return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not save image.'];
		}

		return ['ok' => true, 'width' => $src_w, 'height' => $src_h];
	}

	if (!function_exists('imagecreatetruecolor')) {
		if (!@copy($tmp_path, $dest_path)) {
			return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not save image.'];
		}

		return ['ok' => true, 'width' => $src_w, 'height' => $src_h];
	}

	$target_w = $max_w;
	$target_h = (int) max(1, round($src_h * ($target_w / $src_w)));
	$source = banner_image_load_gd($tmp_path, $mime);
	if ($source === null) {
		if (!@copy($tmp_path, $dest_path)) {
			return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not save image.'];
		}

		return ['ok' => true, 'width' => $src_w, 'height' => $src_h];
	}

	$canvas = imagecreatetruecolor($target_w, $target_h);
	if ($canvas === false) {
		imagedestroy($source);

		return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not process image.'];
	}

	if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		$transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
		if ($transparent !== false) {
			imagefilledrectangle($canvas, 0, 0, $target_w, $target_h, $transparent);
		}
	}

	imagecopyresampled($canvas, $source, 0, 0, 0, 0, $target_w, $target_h, $src_w, $src_h);
	imagedestroy($source);

	$saved = banner_image_save_gd($canvas, $dest_path, $mime);
	imagedestroy($canvas);

	if (!$saved) {
		return ['ok' => false, 'width' => 0, 'height' => 0, 'error' => 'Could not save resized image.'];
	}

	return ['ok' => true, 'width' => $target_w, 'height' => $target_h];
}

/**
 * @return \GdImage|resource|null
 */
function banner_image_load_gd(string $path, string $mime)
{
	return match ($mime) {
		'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
		'image/png' => @imagecreatefrompng($path) ?: null,
		'image/gif' => @imagecreatefromgif($path) ?: null,
		'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
		default => null,
	};
}

/**
 * @param \GdImage|resource $image
 */
function banner_image_save_gd($image, string $dest_path, string $mime): bool
{
	$jpeg_q = defined('BANNER_JPEG_QUALITY') ? (int) BANNER_JPEG_QUALITY : 92;
	$webp_q = defined('BANNER_WEBP_QUALITY') ? (int) BANNER_WEBP_QUALITY : 90;

	return match ($mime) {
		'image/jpeg' => imagejpeg($image, $dest_path, max(80, min(100, $jpeg_q))),
		'image/png' => imagepng($image, $dest_path, 1),
		'image/gif' => imagegif($image, $dest_path),
		'image/webp' => function_exists('imagewebp')
			? imagewebp($image, $dest_path, max(80, min(100, $webp_q)))
			: false,
		default => false,
	};
}

/**
 * @param array{tmp_name:string,size?:int,error?:int} $file
 * @return array{ok:bool,error?:string,mime?:string,ext?:string}
 */
function banner_image_validate_upload(array $file): array
{
	if (empty($file['tmp_name'])) {
		return ['ok' => false, 'error' => 'No image selected'];
	}

	$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($err !== UPLOAD_ERR_OK) {
		$msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'File too large' : 'Upload error ' . $err;

		return ['ok' => false, 'error' => $msg];
	}
	if (!is_uploaded_file($file['tmp_name'])) {
		return ['ok' => false, 'error' => 'Invalid upload'];
	}

	$max_size = defined('BANNER_MAX_UPLOAD_SIZE') ? BANNER_MAX_UPLOAD_SIZE : (15 * 1024 * 1024);
	if (($file['size'] ?? 0) > $max_size) {
		$max_mb = (int) round($max_size / (1024 * 1024));

		return ['ok' => false, 'error' => 'File too large (max ' . $max_mb . 'MB)'];
	}

	$allowed = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime = $finfo->file($file['tmp_name']);
	if (!in_array($mime, $allowed, true)) {
		return ['ok' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF, or WebP.'];
	}

	$ext = match ($mime) {
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
		default => 'jpg',
	};

	return ['ok' => true, 'mime' => $mime, 'ext' => $ext];
}
