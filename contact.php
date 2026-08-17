<?php
/**
 * Contact page. Renders contact form and company details. Handles form POST:
 * validates CSRF and fields, sends email to contact@pearlica.in, redirects with flash.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/contact_message.php';

$base_path = '';
$meta_title_full = 'Contact Pearlica | 925 Silver Jewellery Support';
$meta_description = 'Need help with your Pearlica order? Contact our 925 silver jewellery support team in India for queries, returns, or assistance.';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$allowed_subjects = [
	'Order Issue',
	'Returns',
	'General Query',
	'Business Inquiry',
];

function contact_csrf_token(): string
{
	if (empty($_SESSION['contact_csrf_token'])) {
		$_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['contact_csrf_token'];
}

function contact_validate_csrf(): bool
{
	$token = $_POST['csrf_token'] ?? '';
	return hash_equals($_SESSION['contact_csrf_token'] ?? '', $token);
}

function contact_flash_set(string $key, string $value): void
{
	$_SESSION['_contact_flash'][$key] = $value;
}

function contact_flash_get(string $key): ?string
{
	$v = $_SESSION['_contact_flash'][$key] ?? null;
	unset($_SESSION['_contact_flash'][$key]);
	return $v;
}

$form_errors = [];
$form_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!contact_validate_csrf()) {
		$form_errors[] = 'Invalid request. Please submit the form again.';
	} else {
		$full_name = trim((string) ($_POST['full_name'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$phone = trim((string) ($_POST['phone'] ?? ''));
		$order_id = trim((string) ($_POST['order_id'] ?? ''));
		$subject = trim((string) ($_POST['subject'] ?? ''));
		$message = trim((string) ($_POST['message'] ?? ''));

		if ($full_name === '') {
			$form_errors[] = 'Full name is required.';
		}
		if ($email === '') {
			$form_errors[] = 'Email address is required.';
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$form_errors[] = 'Please enter a valid email address.';
		}
		if ($subject === '' || !in_array($subject, $allowed_subjects, true)) {
			$form_errors[] = 'Please select a valid subject.';
		}
		if ($message === '') {
			$form_errors[] = 'Message is required.';
		}

		if ($form_errors === []) {
			$pdo = $GLOBALS['pdo'];
			$ip_address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
			$user_agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

			/** @noinspection PhpUndefinedFunctionInspection */
			$contact_id = contact_message_create($pdo, [
				'full_name' => $full_name,
				'email' => $email,
				'phone' => $phone,
				'order_id' => $order_id,
				'subject' => $subject,
				'message' => $message,
				'ip_address' => $ip_address,
				'user_agent' => $user_agent,
			]);

			$submitted_on = date('D, d F Y · H:i T');
			require_once __DIR__ . '/lib/email_notifications.php';
			$email_ok = email_notify_admin_contact([
				'full_name' => $full_name,
				'email' => $email,
				'phone' => $phone,
				'order_id' => $order_id,
				'subject' => $subject,
				'message' => $message,
				'submitted_on' => $submitted_on,
			], $contact_id);
			/** @noinspection PhpUndefinedFunctionInspection */
			contact_message_mark_email_status($pdo, $contact_id, $email_ok ? 1 : 0);

			contact_flash_set('success', 'Thank you for getting in touch. We will respond as soon as possible.');
			header('Location: ' . $base_path . 'contact', true, 302);
			exit;
		}
	}
	// Regenerate CSRF token when re-displaying form after failed validation
	$_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

$flash_success = contact_flash_get('success');
if ($flash_success !== null) {
	$form_success = true;
}

// Regenerate token for next form display
contact_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h($base_path); ?>assets/css/contact.css">

<body class="homepage contact-page">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main>
		<section class="contact-section py-4 py-lg-5 page-content-constrained">
			<div>
				<p class="contact-hero-label">Get in Touch</p>
				<h1 class="contact-hero-title">Contact Us</h1>
				<p class="contact-hero-subtitle">We'd love to hear from you. Use the form below for queries, feedback,
					or suggestions. Our support team will respond as soon as possible.</p>

				<?php if ($form_success && $flash_success !== null): ?>
					<div class="alert alert-success mt-4"><?php echo h($flash_success); ?></div>
				<?php endif; ?>
				<?php foreach ($form_errors as $err): ?>
					<div class="alert alert-danger mt-4"><?php echo h($err); ?></div>
				<?php endforeach; ?>

				<div class="contact-grid mt-5">
					<div class="contact-form-col">
						<form method="post" action="" class="contact-form">
							<input type="hidden" name="csrf_token" value="<?php echo h(contact_csrf_token()); ?>">
							<div class="contact-form-row contact-delay-1">
								<div class="contact-field-group">
									<label for="full_name" class="contact-label">Full Name</label>
									<input type="text" id="full_name" name="full_name" class="contact-input" required
										value="<?php echo h($_POST['full_name'] ?? ''); ?>" placeholder="Your full name"
										autocomplete="name">
								</div>
								<div class="contact-field-group">
									<label for="email" class="contact-label">Email</label>
									<input type="email" id="email" name="email" class="contact-input" required
										value="<?php echo h($_POST['email'] ?? ''); ?>" placeholder="you@example.com"
										autocomplete="email">
								</div>
							</div>
							<div class="contact-form-row contact-delay-2">
								<div class="contact-field-group">
									<label for="phone" class="contact-label">Phone</label>
									<input type="tel" id="phone" name="phone" class="contact-input"
										value="<?php echo h($_POST['phone'] ?? ''); ?>" placeholder="+91 96995 18449"
										autocomplete="tel">
								</div>
								<div class="contact-field-group">
									<label for="order_id" class="contact-label">Order ID</label>
									<input type="text" id="order_id" name="order_id" class="contact-input"
										value="<?php echo h($_POST['order_id'] ?? ''); ?>"
										placeholder="e.g. PRL-20260318-ABC123 (optional)" autocomplete="off">
								</div>
							</div>
							<div class="contact-field-group contact-delay-3">
								<label for="subject" class="contact-label">Subject</label>
								<div class="contact-select-wrap">
									<select id="subject" name="subject" class="contact-select" required>
										<option value="">Select a subject</option>
										<?php foreach ($allowed_subjects as $opt): ?>
											<option value="<?php echo h($opt); ?>" <?php echo (isset($_POST['subject']) && $_POST['subject'] === $opt) ? ' selected' : ''; ?>><?php echo h($opt); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
							<div class="contact-field-group contact-delay-4">
								<label for="message" class="contact-label">Message</label>
								<textarea id="message" name="message" class="contact-textarea" required rows="6"
									placeholder="How can we help? Include order details if relevant."><?php echo h($_POST['message'] ?? ''); ?></textarea>
							</div>
							<div class="contact-field-group contact-delay-5">
								<button type="submit" class="contact-submit-btn">Send Message</button>
							</div>
						</form>
					</div>
					<div class="contact-info-col">
						<div class="contact-info-card">
							<p class="contact-info-card-label">Registered Office</p>
							<div class="contact-address">
								Pearlica<br>
								Shop No. 02, Vijaylaxmi Jewellers<br>
								Plot No. 710, Opp. Girija Nursing Home<br>
								Lal Chakki, Station Road<br>
								Ulhasnagar – 421004<br>
								Thane, Maharashtra, India
							</div>
							<p class="mb-0"><span class="contact-gstin-label">GSTIN:</span> <span
									class="contact-gstin-value">27DBTPR5240G2Z5</span></p>
						</div>
						<div class="contact-info-card">
							<p class="contact-info-card-label">Contact Details</p>
							<div class="contact-detail-row">
								<span class="contact-detail-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
										stroke="currentColor" stroke-width="1.4">
										<path stroke-linecap="round" stroke-linejoin="round"
											d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
									</svg>
								</span>
								<div class="contact-detail-content">
									<p class="contact-info-card-label mb-1">Email</p>
									<p class="contact-detail-value mb-0"><a
											href="mailto:contact@pearlica.in">contact@pearlica.in</a></p>
								</div>
							</div>
							<div class="contact-detail-row">
								<span class="contact-detail-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
										stroke="currentColor" stroke-width="1.4">
										<path stroke-linecap="round" stroke-linejoin="round"
											d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
									</svg>
								</span>
								<div class="contact-detail-content">
									<p class="contact-info-card-label mb-1">Phone</p>
									<p class="contact-detail-value mb-0"><a href="tel:+919699518449">+91 9699518449</a>
									</p>
								</div>
							</div>
						</div>
						<div class="contact-info-card">
							<p class="contact-info-card-label">Opening Hours</p>
							<div class="contact-hours-grid">
								<span>Monday – Saturday</span>
								<span>10:00 AM – 06:00 PM</span>
								<span>Sunday</span>
								<span class="contact-hours-closed">Closed</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		crossorigin="anonymous"></script>
</body>

</html>