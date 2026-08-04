<?php
/**
 * TeleSign REST credentials (SMS Verify / login OTP).
 *
 * This file is tracked in git by project choice. For public repos, rotate keys
 * after exposure and prefer env-based secrets instead.
 *
 * India (+91): if TeleSign requires TRAI DLT for your route, set the two DLT constants.
 * SMS text is TeleSign’s default OTP message (no custom template in this app).
 */
declare(strict_types=1);

if (!defined('TELESIGN_CUSTOMER_ID')) {
	define('TELESIGN_CUSTOMER_ID', '1166F3E0-1213-4EA8-9A86-BC9BDBA3AB74');
}
if (!defined('TELESIGN_API_KEY')) {
	define('TELESIGN_API_KEY', 'NFj5UazB+U2wXCwZc0TNhPva6qVg7MKvkGa/8WsE4saKE/fRTUOoXeH9xvGMzRSgY/Kki1DCay8VKT0Bh04CYw==');
}
if (!defined('TELESIGN_DLT_TEMPLATE_ID')) {
	define('TELESIGN_DLT_TEMPLATE_ID', '');
}
if (!defined('TELESIGN_DLT_ENTITY_ID')) {
	define('TELESIGN_DLT_ENTITY_ID', '');
}
