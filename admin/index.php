<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['admin_id'])) {
	redirect(admin_url('dashboard/'));
}
redirect(admin_url('login.php'));
