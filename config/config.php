<?php
if (!defined('APP_ENV')) define('APP_ENV', 'production');
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);

// Never expose PHP warnings/errors in HTTP responses; log server-side instead.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/includes/uploads_url.php';
require_once BASE_PATH . '/includes/asset_url.php';
require_once BASE_PATH . '/lib/media_storage.php';
require_once BASE_PATH . '/lib/media_serve.php';

// Optional local secrets early so MEDIA_STORAGE_ROOT can be overridden before paths bind.
$__secrets_local = __DIR__ . '/secrets.local.php';
if (is_file($__secrets_local)) {
	require_once $__secrets_local;
}
unset($__secrets_local);

/**
 * Durable media root (outside public_html when possible).
 * Override in secrets.local.php: define('MEDIA_STORAGE_ROOT', '/home/.../pearlica-media');
 */
if (!defined('MEDIA_STORAGE_ROOT')) {
	define('MEDIA_STORAGE_ROOT', media_storage_resolve_root());
}
media_storage_ensure_buckets((string) MEDIA_STORAGE_ROOT);

if (!defined('UPLOAD_PATH')) {
	define('UPLOAD_PATH', rtrim((string) MEDIA_STORAGE_ROOT, '/\\') . '/products');
}
/** Curated collection cover images (3:4 exports live under this tree). */
if (!defined('COLLECTION_UPLOAD_PATH')) {
	define('COLLECTION_UPLOAD_PATH', rtrim((string) MEDIA_STORAGE_ROOT, '/\\') . '/collections');
}
/** Homepage hero / mid-page banner images. */
if (!defined('BANNER_UPLOAD_PATH')) {
	define('BANNER_UPLOAD_PATH', rtrim((string) MEDIA_STORAGE_ROOT, '/\\') . '/banners');
}
/** Homepage influencer reel videos and their poster frames. */
if (!defined('REEL_UPLOAD_PATH')) {
	define('REEL_UPLOAD_PATH', rtrim((string) MEDIA_STORAGE_ROOT, '/\\') . '/reels');
}
/** Blog post cover images. */
if (!defined('BLOG_UPLOAD_PATH')) {
	define('BLOG_UPLOAD_PATH', rtrim((string) MEDIA_STORAGE_ROOT, '/\\') . '/blogs');
}
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024);
/** Product crop export (4:5) — 1.5× 1080 so retina cards/PDP stay sharp. */
if (!defined('PRODUCT_IMAGE_EXPORT_WIDTH')) {
	define('PRODUCT_IMAGE_EXPORT_WIDTH', 1620);
}
if (!defined('PRODUCT_IMAGE_EXPORT_HEIGHT')) {
	define('PRODUCT_IMAGE_EXPORT_HEIGHT', 2025);
}
if (!defined('PRODUCT_IMAGE_JPEG_QUALITY')) {
	define('PRODUCT_IMAGE_JPEG_QUALITY', 0.95);
}
/** Collection cover images (3:4 portrait exports). */
if (!defined('COLLECTION_COVER_WIDTH')) {
	define('COLLECTION_COVER_WIDTH', 1620);
}
if (!defined('COLLECTION_COVER_HEIGHT')) {
	define('COLLECTION_COVER_HEIGHT', 2160);
}
if (!defined('COLLECTION_IMAGE_JPEG_QUALITY')) {
	define('COLLECTION_IMAGE_JPEG_QUALITY', 0.95);
}
define('COLLECTION_MAX_UPLOAD_SIZE', 12 * 1024 * 1024);
/** Banners are often larger wide assets; still capped. */
define('BANNER_MAX_UPLOAD_SIZE', 15 * 1024 * 1024);
/** Store banner masters up to this width; larger uploads are downscaled (never upscaled). */
define('BANNER_MAX_STORE_WIDTH', 3840);
define('BANNER_JPEG_QUALITY', 92);
define('BANNER_WEBP_QUALITY', 90);
define('BANNER_RECOMMENDED_WIDTH', 2560);
define('BANNER_RECOMMENDED_HEIGHT', 1200);
/** Reels are short vertical clips; keep them small enough to autoplay quickly. */
define('REEL_MAX_VIDEO_SIZE', 32 * 1024 * 1024);
/** Blog cover images (wide hero assets). */
define('BLOG_MAX_UPLOAD_SIZE', 12 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime']);

/**
 * Absolute public base for product/collection media when the local uploads tree
 * is empty (images are gitignored). Local/dev only — never on pearlica.in.
 * Override in secrets.local.php if needed.
 */
if (!defined('PRODUCT_MEDIA_PUBLIC_BASE')) {
	$__media_fallback = pearlica_is_local_dev_host()
		&& !pearlica_upload_dir_has_media(UPLOAD_PATH)
		&& !pearlica_upload_dir_has_media(media_storage_legacy_root() . '/products')
		? 'https://www.pearlica.in/uploads/products'
		: '';
	define('PRODUCT_MEDIA_PUBLIC_BASE', $__media_fallback);
	unset($__media_fallback);
}
if (!defined('COLLECTION_MEDIA_PUBLIC_BASE')) {
	$__media_fallback = pearlica_is_local_dev_host()
		&& !pearlica_upload_dir_has_media(COLLECTION_UPLOAD_PATH)
		&& !pearlica_upload_dir_has_media(media_storage_legacy_root() . '/collections')
		? 'https://www.pearlica.in/uploads/collections'
		: '';
	define('COLLECTION_MEDIA_PUBLIC_BASE', $__media_fallback);
	unset($__media_fallback);
}
if (!defined('BANNER_MEDIA_PUBLIC_BASE')) {
	$__media_fallback = pearlica_is_local_dev_host()
		&& !pearlica_upload_dir_has_media(BANNER_UPLOAD_PATH)
		&& !pearlica_upload_dir_has_media(media_storage_legacy_root() . '/banners')
		? 'https://www.pearlica.in/uploads/banners'
		: '';
	define('BANNER_MEDIA_PUBLIC_BASE', $__media_fallback);
	unset($__media_fallback);
}
if (!defined('REEL_MEDIA_PUBLIC_BASE')) {
	$__media_fallback = pearlica_is_local_dev_host()
		&& !pearlica_upload_dir_has_media(REEL_UPLOAD_PATH)
		&& !pearlica_upload_dir_has_media(media_storage_legacy_root() . '/reels')
		? 'https://www.pearlica.in/uploads/reels'
		: '';
	define('REEL_MEDIA_PUBLIC_BASE', $__media_fallback);
	unset($__media_fallback);
}
if (!defined('BLOG_MEDIA_PUBLIC_BASE')) {
	$__media_fallback = pearlica_is_local_dev_host()
		&& !pearlica_upload_dir_has_media(BLOG_UPLOAD_PATH)
		&& !pearlica_upload_dir_has_media(media_storage_legacy_root() . '/blogs')
		? 'https://www.pearlica.in/uploads/blogs'
		: '';
	define('BLOG_MEDIA_PUBLIC_BASE', $__media_fallback);
	unset($__media_fallback);
}

// TeleSign keys (tracked). Defines only if not already set by secrets.local.php.
$__telesign_cred = __DIR__ . '/telesign.credentials.php';
if (is_file($__telesign_cred)) {
	require_once $__telesign_cred;
}
/** Storefront listing (products, gifting): use a multiple of 4 so 4-column grids don’t end with an empty slot each page. */
define('ITEMS_PER_PAGE', 16);
/**
 * Public JSON blog feed (same shape as the blog engine’s rss.php output): used by `blogs/index.php` and `blogs/post.php`.
 * Include per-post `html`, `content`, or `body` for full article bodies when using Custom Interface mode.
 * Override in `secrets.local.php` if needed (e.g. https://pearlica.in/blogs/rss.php).
 */
if (!defined('BLOG_FEED_JSON_URL')) {
	define('BLOG_FEED_JSON_URL', 'https://www.pearlica.in/blogs/rss');
}
/** On product pages & cards: show this label when stock is 1..N (inclusive). */
define('STORE_LOW_STOCK_CEILING', 5);
/** Homepage “New Arrivals”: newest-first products only (category rails use INDEX_HOME_CATEGORY_PRODUCTS). */
define('INDEX_NEW_ARRIVALS_MAX', 8);
define('INDEX_NEW_ARRIVALS_DAYS', 120);
/** Homepage under New Arrivals: parent categories shown, products per row (newest first). */
define('INDEX_HOME_CATEGORY_MAX', 8);
define('INDEX_HOME_CATEGORY_PRODUCTS', 4);
/** Homepage “Pearlica Picks”: comma-separated YouTube video IDs for embeds; empty = show Instagram CTA only. */
if (!defined('INDEX_HOME_PICK_VIDEO_IDS')) {
	define('INDEX_HOME_PICK_VIDEO_IDS', '');
}
define('SESSION_LIFETIME', 3600);
/** Optional add-on at checkout (paise). Charged in addition to cart subtotal; not discounted by promo. */
define('CHECKOUT_GIFT_PACKAGING_PAISE', 3000);
/** Free standard delivery when merchandise total after promo is at or above this amount (paise = ₹700). */
define('CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE', 70000);
/** Standard delivery fee (paise) when merchandise after promo is below CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE (₹199). */
define('CHECKOUT_SHIPPING_FEE_BELOW_MIN_PAISE', 19900);
/** Policy: COD not offered above this order total (rupees); informational until COD checkout is enabled. */
define('CHECKOUT_COD_MAX_ORDER_RUPEES', 10000);
define('ADMIN_PATH', BASE_PATH . '/admin');
define('AUTH_COOKIE_NAME', 'pearlica_user');
define('AUTH_COOKIE_DAYS', 7);
if (!defined('AUTH_SECRET_KEY')) {
	define('AUTH_SECRET_KEY', 'change-this-in-production-pearlica-auth-secret');
}

/**
 * Shiprocket settings (staging-first).
 * Replace defaults with env vars or secure deployment config in production.
 */
if (!defined('SHIPROCKET_ENABLED')) {
	define('SHIPROCKET_ENABLED', true);
}
if (!defined('SHIPROCKET_ENV')) {
	define('SHIPROCKET_ENV', 'staging'); // staging|production
}
if (!defined('SHIPROCKET_API_BASE_STAGING')) {
	define('SHIPROCKET_API_BASE_STAGING', 'https://apiv2.shiprocket.in/v1/external');
}
if (!defined('SHIPROCKET_API_BASE_PRODUCTION')) {
	define('SHIPROCKET_API_BASE_PRODUCTION', 'https://apiv2.shiprocket.in/v1/external');
}
if (!defined('SHIPROCKET_EMAIL')) {
	define('SHIPROCKET_EMAIL', 'contact@pearlica.in');
}
if (!defined('SHIPROCKET_PASSWORD')) {
	define('SHIPROCKET_PASSWORD', 'qvGBW0*AHXZx%Pl$YH7PDyWtL7%4Aa*b');
}
if (!defined('SHIPROCKET_PICKUP_LOCATION')) {
	define('SHIPROCKET_PICKUP_LOCATION', 'work');
}
/** 6-digit PIN of the Shiprocket pickup warehouse — required for checkout delivery estimates. */
if (!defined('SHIPROCKET_PICKUP_POSTCODE')) {
	define('SHIPROCKET_PICKUP_POSTCODE', '421004');
}
/** Declared weight (kg) when calling Shiprocket serviceability for ETA at checkout. */
if (!defined('SHIPROCKET_ESTIMATE_WEIGHT_KG')) {
	define('SHIPROCKET_ESTIMATE_WEIGHT_KG', 0.5);
}
if (!defined('SHIPROCKET_CHANNEL_ID')) {
	define('SHIPROCKET_CHANNEL_ID', 0);
}
if (!defined('SHIPROCKET_TOKEN_TTL_SECONDS')) {
	define('SHIPROCKET_TOKEN_TTL_SECONDS', 3300);
}

/** Admin inbox for operational alerts (orders, payments, admin login, contact). */
if (!defined('ADMIN_NOTIFY_EMAIL')) {
	define('ADMIN_NOTIFY_EMAIL', 'contact@pearlica.in');
}
if (!defined('MAIL_FROM_ADDRESS')) {
	define('MAIL_FROM_ADDRESS', 'noreply@pearlica.in');
}
if (!defined('MAIL_FROM_NAME')) {
	define('MAIL_FROM_NAME', 'Pearlica');
}
/** Public site URL for images/links inside HTML emails. */
if (!defined('SITE_PUBLIC_URL')) {
	define('SITE_PUBLIC_URL', 'https://pearlica.in');
}

/**
 * TeleSign SMS Verify (login OTP). Defaults: config/telesign.credentials.php (tracked).
 * Override in secrets.local.php if needed. Dashboard: https://portal.telesign.com/
 */
if (!defined('TELESIGN_REST_BASE')) {
	define('TELESIGN_REST_BASE', 'https://rest-ww.telesign.com');
}
if (!defined('TELESIGN_CUSTOMER_ID')) {
	define('TELESIGN_CUSTOMER_ID', '');
}
if (!defined('TELESIGN_API_KEY')) {
	define('TELESIGN_API_KEY', '');
}
/** India SMS: both required for +91 traffic when TeleSign has registered your DLT template (see TeleSign India DLT docs). */
if (!defined('TELESIGN_DLT_TEMPLATE_ID')) {
	define('TELESIGN_DLT_TEMPLATE_ID', '');
}
if (!defined('TELESIGN_DLT_ENTITY_ID')) {
	define('TELESIGN_DLT_ENTITY_ID', '');
}
/** Login SMS OTP length (digits). TeleSign accepts your code via verify_code + template. */
if (!defined('TELESIGN_LOGIN_OTP_DIGITS')) {
	define('TELESIGN_LOGIN_OTP_DIGITS', 4);
}
/** Must contain $$CODE$$; TeleSign replaces it with verify_code. India: register this exact text on DLT if required. */
if (!defined('TELESIGN_LOGIN_OTP_SMS_TEMPLATE')) {
	define(
		'TELESIGN_LOGIN_OTP_SMS_TEMPLATE',
		'$$CODE$$ is your Pearlica sign-in code. Valid 10 min. Do not share with anyone.'
	);
}

/**
 * Refrens Invoice API (https://www.refrens.com/api/docs/).
 * Token: POST /authentication with strategy app-secret, or self-sign ES256 JWT (REFRENS_PRIVATE_KEY).
 */
if (!defined('REFRENS_ENABLED')) {
	define('REFRENS_ENABLED', true);
}
if (!defined('REFRENS_API_BASE')) {
	define('REFRENS_API_BASE', 'https://api.refrens.com');
}
if (!defined('REFRENS_URL_KEY')) {
	define('REFRENS_URL_KEY', 'pearlica');
}
if (!defined('REFRENS_APP_ID')) {
	define('REFRENS_APP_ID', 'pearlica-tbxO8');
}
if (!defined('REFRENS_APP_SECRET')) {
	define('REFRENS_APP_SECRET', 'YMzeBKa3YY3wfKpsQhmCNG8a');
}
if (!defined('REFRENS_PRIVATE_KEY')) {
	define(
		'REFRENS_PRIVATE_KEY',
		<<<'REFRENS_EC_P256'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQguzUsSroi9pdJh+Pf
AAJNWP/Bc7bIH6NUpY9EzVLr1XKhRANCAATzPhrFldJ9lkIkXxnUBZDjf8a27o0W
z3pgI/3c2S20eXb15jxsCtG/0m96RE58LOQcr/6DPtYSHs9XFSiS29Jh
-----END PRIVATE KEY-----
REFRENS_EC_P256
	);
}
if (!defined('REFRENS_INVOICE_TYPE')) {
	define('REFRENS_INVOICE_TYPE', 'INVOICE');
}
if (!defined('REFRENS_CURRENCY')) {
	define('REFRENS_CURRENCY', 'INR');
}
if (!defined('REFRENS_SEND_INVOICE_EMAIL')) {
	define('REFRENS_SEND_INVOICE_EMAIL', true);
}
/** CC on Refrens invoice emails (shop inbox). Empty string to disable. */
if (!defined('REFRENS_INVOICE_CC_EMAIL')) {
	define('REFRENS_INVOICE_CC_EMAIL', 'contact@pearlica.in');
}
/**
 * Refrens payment row on invoice (https://www.refrens.com/api/docs/payment-updates/).
 * Razorpay = online; UPI is valid; use CREDIT_CARD if you prefer a generic card label.
 */
if (!defined('REFRENS_PAYMENT_METHOD')) {
	define('REFRENS_PAYMENT_METHOD', 'UPI');
}
/** GST % on the checkout add-on line (gift packaging stored in shipping_paise). */
if (!defined('REFRENS_SHIPPING_LINE_GST_RATE')) {
	define('REFRENS_SHIPPING_LINE_GST_RATE', 18.0);
}
if (!defined('REFRENS_SELLER_NAME')) {
	define('REFRENS_SELLER_NAME', MAIL_FROM_NAME);
}
if (!defined('REFRENS_SELLER_STREET')) {
	define(
		'REFRENS_SELLER_STREET',
		'1st Floor, 40/1, Plot No. 710, Lal Chakki, Behind Vijaylaxmi Jewellers, Ulhasnagar, Thane, Maharashtra'
	);
}
if (!defined('REFRENS_SELLER_PINCODE')) {
	define('REFRENS_SELLER_PINCODE', '421004');
}
if (!defined('REFRENS_SELLER_GST_STATE')) {
	define('REFRENS_SELLER_GST_STATE', '27');
}
if (!defined('REFRENS_SELLER_GSTIN')) {
	define('REFRENS_SELLER_GSTIN', '27DBTPR5240G2Z5');
}
if (!defined('REFRENS_SELLER_COUNTRY')) {
	define('REFRENS_SELLER_COUNTRY', 'IN');
}
if (!defined('REFRENS_SELLER_EMAIL')) {
	define('REFRENS_SELLER_EMAIL', 'contact@pearlica.in');
}
if (!defined('REFRENS_SELLER_PHONE')) {
	define('REFRENS_SELLER_PHONE', '');
}
if (!defined('REFRENS_TOKEN_CACHE_FILE')) {
	define('REFRENS_TOKEN_CACHE_FILE', BASE_PATH . '/storage/refrens.token.json');
}

/**
 * Homepage coming-soon gate: before this moment (in STORE_LAUNCH_TIMEZONE), index.php redirects to coming-soon.
 * After this time the storefront homepage loads normally. Format: Y-m-d H:i:s (24h).
 */
if (!defined('STORE_LAUNCH_DATETIME')) {
	define('STORE_LAUNCH_DATETIME', '2026-03-30 15:00:00');
}
if (!defined('STORE_LAUNCH_TIMEZONE')) {
	define('STORE_LAUNCH_TIMEZONE', 'Asia/Kolkata');
}

/**
 * True when the store launch time has been reached (or launch constants are missing).
 */
if (!function_exists('pearlica_store_is_live')) {
	function pearlica_store_is_live(): bool
	{
		if (!defined('STORE_LAUNCH_DATETIME') || !defined('STORE_LAUNCH_TIMEZONE')) {
			return true;
		}
		try {
			$tz = new DateTimeZone(STORE_LAUNCH_TIMEZONE);
			$launch = new DateTimeImmutable(STORE_LAUNCH_DATETIME, $tz);
			$now = new DateTimeImmutable('now', $tz);
			return $now >= $launch;
		} catch (Throwable $e) {
			return true;
		}
	}
}

require_once BASE_PATH . '/includes/store_session.php';

