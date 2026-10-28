<?php
declare(strict_types=1);

if (!function_exists('store_analytics_session_take_queue')) {
	return;
}

$pearlica_analytics_bootstrap = [
	'queue' => store_analytics_session_take_queue(),
];
if (!empty($page_analytics_view_content) && is_array($page_analytics_view_content)) {
	$pearlica_analytics_bootstrap['view_content'] = $page_analytics_view_content;
}
if (!empty($page_analytics_initiate_checkout) && is_array($page_analytics_initiate_checkout)) {
	$pearlica_analytics_bootstrap['initiate_checkout'] = $page_analytics_initiate_checkout;
}
if (!empty($page_analytics_purchase) && is_array($page_analytics_purchase)) {
	$pearlica_analytics_bootstrap['purchase'] = $page_analytics_purchase;
}
if ($pearlica_analytics_bootstrap === ['queue' => []]) {
	return;
}
$json = json_encode($pearlica_analytics_bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if (!is_string($json)) {
	return;
}
?>
<script type="application/json" id="pearlica-analytics-bootstrap"><?= $json ?></script>
