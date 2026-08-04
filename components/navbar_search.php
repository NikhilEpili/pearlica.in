<?php
if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}
$ns_base = isset($nav_base) ? (string) $nav_base : '';
$ns_trim = trim($ns_base, '/');
$search_suggest_path = ($ns_trim === '' ? '' : '/' . $ns_trim) . '/search-suggest';
$ns_input_id = isset($navbar_search_input_id) && (string) $navbar_search_input_id !== ''
	? (string) $navbar_search_input_id
	: 'site-search-input';
$ns_animated = !empty($navbar_search_animated_placeholder);
if (!isset($navbar_placeholder_phrases) || !is_array($navbar_placeholder_phrases)) {
	$navbar_placeholder_phrases = [
		'Search rings, chains, pendants…',
		'925 sterling silver jewellery…',
		'Everyday silver, elevated…',
	];
}
$ns_first_phrase = (string) ($navbar_placeholder_phrases[0] ?? '');
$ns_phrases_json = htmlspecialchars(
	json_encode(array_values($navbar_placeholder_phrases), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
	ENT_QUOTES,
	'UTF-8'
);
?>
<div class="site-search-wrap w-100 px-0 pb-0" data-site-search-wrap>
	<div class="site-search position-relative mx-auto<?php echo $ns_animated ? ' site-search--navbar-inline' : ''; ?>"
		data-site-search
		data-search-url="<?php echo h($search_suggest_path); ?>"
		<?php if ($ns_animated): ?>data-placeholder-phrases="<?php echo $ns_phrases_json; ?>"<?php endif; ?>>
		<label class="visually-hidden" for="<?php echo h($ns_input_id); ?>">Search products</label>
		<?php if ($ns_animated): ?>
		<div class="site-search-field">
			<span class="site-search-animated-placeholder" data-search-animated-ph aria-hidden="true"><?php echo h($ns_first_phrase); ?></span>
			<input type="search" id="<?php echo h($ns_input_id); ?>"
				class="site-search-input form-control"
				name="q" autocomplete="off" placeholder="" maxlength="80" data-site-search-input>
		</div>
		<?php else: ?>
			<input type="search" id="<?php echo h($ns_input_id); ?>"
				class="site-search-input form-control"
				name="q" autocomplete="off" placeholder="Search silver ring, pendants…" maxlength="80" data-site-search-input>
		<?php endif; ?>
		<div class="site-search-dropdown list-group list-group-flush shadow-sm d-none" data-site-search-dropdown role="listbox" aria-label="Search suggestions"></div>
	</div>
</div>
