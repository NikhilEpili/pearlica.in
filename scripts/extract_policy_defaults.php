<?php
declare(strict_types=1);

$slugs = [
	'return-exchange-policy',
	'shipping-policy',
	'pearlica-care',
	'care-guide',
	'privacy-policy',
	'terms-conditions',
];

$dir = dirname(__DIR__) . '/policies/defaults';
if (!is_dir($dir)) {
	mkdir($dir, 0755, true);
}

foreach ($slugs as $slug) {
	$path = dirname(__DIR__) . '/policies/' . $slug . '.php';
	$src = file_get_contents($path);
	if ($src === false) {
		fwrite(STDERR, "FAIL read $slug\n");
		continue;
	}

	$body = null;
	if (preg_match('/<div class="policy-content">(.*?)\s*<\?php include __DIR__ \. \'\/includes\/related-policies\.php\'; \?>/s', $src, $m)) {
		$body = trim($m[1]);
	} elseif (preg_match('/<div class="policy-content">(.*)<\/div>\s*<\/div>\s*<\/section>/s', $src, $m)) {
		$body = trim($m[1]);
		$body = preg_replace('/\s*<\?php include __DIR__ \. \'\/includes\/related-policies\.php\'; \?>\s*/', '', $body) ?? $body;
		$body = preg_replace('/\s*<\/div>\s*$/', '', $body) ?? $body;
	}

	if ($body === null || $body === '') {
		fwrite(STDERR, "FAIL extract $slug\n");
		continue;
	}

	$body = preg_replace(
		'/<\?php echo htmlspecialchars\(\$base_path, ENT_QUOTES, \'UTF-8\'\); \?>/',
		'{{BASE_PATH}}',
		$body
	) ?? $body;
	$body = preg_replace(
		'/<\?php echo htmlspecialchars\(\$base_path \. \'([^\']*)\', ENT_QUOTES, \'UTF-8\'\); \?>/',
		'{{BASE_PATH}}$1',
		$body
	) ?? $body;

	file_put_contents($dir . '/' . $slug . '.html', $body);
	echo 'OK ' . $slug . ' ' . strlen($body) . " bytes\n";
}
