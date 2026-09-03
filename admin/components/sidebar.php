<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
	<button type="button" class="admin-sidebar-toggle" id="admin-sidebar-toggle" aria-label="Toggle sidebar" title="Collapse sidebar">
		<svg class="sidebar-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
			<path d="M15 18l-6-6 6-6"/>
		</svg>
	</button>
	<nav class="admin-sidebar-nav">
		<div class="admin-sidebar-section-label">General</div>
		<a href="<?= h(admin_url('dashboard/')) ?>" class="admin-sidebar-link" title="Dashboard">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-gauge"></i></span>
			<span class="sidebar-text">Dashboard</span>
		</a>
		<?php if (current_user_can('orders')): ?>
		<a href="<?= h(admin_url('orders/')) ?>" class="admin-sidebar-link" title="Orders">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
			<span class="sidebar-text">Orders</span>
		</a>
		<?php endif; ?>

		<div class="admin-sidebar-section-label">Catalog</div>
		<a href="<?= h(admin_url('categories/')) ?>" class="admin-sidebar-link" title="Categories">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
			<span class="sidebar-text">Categories</span>
		</a>
		<a href="<?= h(admin_url('product-groups/')) ?>" class="admin-sidebar-link" title="Product Groups">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-regular fa-object-group"></i></span>
			<span class="sidebar-text">Product Groups</span>
		</a>
		<?php if (current_user_can('collections')): ?>
		<a href="<?= h(admin_url('collections/')) ?>" class="admin-sidebar-link" title="Collections">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
			<span class="sidebar-text">Collections</span>
		</a>
		<?php endif; ?>
		<a href="<?= h(admin_url('products/')) ?>" class="admin-sidebar-link" title="Products">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span>
			<span class="sidebar-text">Products</span>
		</a>
		<?php if (current_user_can('banners')): ?>
		<a href="<?= h(admin_url('banners/')) ?>" class="admin-sidebar-link" title="Banners">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
			<span class="sidebar-text">Banners</span>
		</a>
		<?php endif; ?>
		<?php if (current_user_can('reels')): ?>
		<a href="<?= h(admin_url('reels/')) ?>" class="admin-sidebar-link" title="Instagram reels">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-brands fa-instagram"></i></span>
			<span class="sidebar-text">Instagram reels</span>
		</a>
		<?php endif; ?>
		<?php if (current_user_can('faqs')): ?>
		<a href="<?= h(admin_url('faqs/')) ?>" class="admin-sidebar-link" title="FAQs">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
			<span class="sidebar-text">FAQs</span>
		</a>
		<?php endif; ?>
		<?php if (current_user_can('policies')): ?>
		<a href="<?= h(admin_url('policies/')) ?>" class="admin-sidebar-link" title="Policies">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
			<span class="sidebar-text">Policies</span>
		</a>
		<?php endif; ?>
		<?php if (current_user_can('blogs')): ?>
		<a href="<?= h(admin_url('blogs/')) ?>" class="admin-sidebar-link" title="Blog">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-newspaper"></i></span>
			<span class="sidebar-text">Blog</span>
		</a>
		<?php endif; ?>

		<?php if (current_user_is_super_admin()): ?>
		<a href="<?= h(admin_url('media-health.php')) ?>" class="admin-sidebar-link" title="Media health">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-stethoscope"></i></span>
			<span class="sidebar-text">Media health</span>
		</a>
		<a href="<?= h(admin_url('media-migrate.php')) ?>" class="admin-sidebar-link" title="Migrate media">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-hard-drive"></i></span>
			<span class="sidebar-text">Migrate media</span>
		</a>
		<a href="<?= h(admin_url('promo-codes/')) ?>" class="admin-sidebar-link" title="Promo Codes">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-tag"></i></span>
			<span class="sidebar-text">Promo Codes</span>
		</a>

		<div class="admin-sidebar-section-label">Integrations</div>
		<a href="<?= h(admin_url('razorpay.php')) ?>" class="admin-sidebar-link" title="Razorpay">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
			<span class="sidebar-text">Razorpay Settings</span>
		</a>
		<a href="<?= h(admin_url('razorpay-payments.php')) ?>" class="admin-sidebar-link" title="Razorpay Payments">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-money-check-dollar"></i></span>
			<span class="sidebar-text">Razorpay Payments</span>
		</a>
		<a href="<?= h(admin_url('shiprocket.php')) ?>" class="admin-sidebar-link" title="Shiprocket">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-truck-fast"></i></span>
			<span class="sidebar-text">Shiprocket Settings</span>
		</a>
		<a href="<?= h(admin_url('shiprocket-orders.php')) ?>" class="admin-sidebar-link" title="Shiprocket Orders">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
			<span class="sidebar-text">Shiprocket Orders</span>
		</a>
		<a href="<?= h(admin_url('refrens.php')) ?>" class="admin-sidebar-link" title="Invoicing">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
			<span class="sidebar-text">Invoicing Settings</span>
		</a>

		<div class="admin-sidebar-section-label">Users</div>
		<a href="<?= h(admin_url('store-users/')) ?>" class="admin-sidebar-link" title="Customers">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-user-tag"></i></span>
			<span class="sidebar-text">Customers</span>
		</a>
		<a href="<?= h(admin_url('users/')) ?>" class="admin-sidebar-link" title="Users">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
			<span class="sidebar-text">Users</span>
		</a>
		<?php endif; ?>
		<a href="<?= h(admin_url('logout.php')) ?>" class="admin-sidebar-link mt-auto text-danger" title="Logout">
			<span class="sidebar-icon" aria-hidden="true"><i class="fa-solid fa-right-from-bracket"></i></span>
			<span class="sidebar-text">Logout</span>
		</a>
	</nav>
</aside>
