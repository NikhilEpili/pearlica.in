<?php
declare(strict_types=1);

/**
 * Respond when MySQL or other shared resources are temporarily exhausted (festive traffic bursts).
 */
function pearlica_service_unavailable(string $log_message = ''): never
{
	if ($log_message !== '') {
		error_log($log_message);
	}
	if (!headers_sent()) {
		header('Retry-After: 2', true, 503);
		header('Content-Type: text/html; charset=UTF-8');
	}
	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Please retry — Pearlica</title></head><body><p>We are handling high traffic. Please wait a moment and refresh.</p></body></html>';
	exit;
}
