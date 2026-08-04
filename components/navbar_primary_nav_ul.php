<?php
/**
 * Primary navbar links (Home, Shop, Collections, Categories, About).
 * Set $navbar_nav_ul_extra_classes before include (optional).
 */
$navbar_nav_ul_extra_classes = isset($navbar_nav_ul_extra_classes) ? (string) $navbar_nav_ul_extra_classes : '';
$nav_prefix = (isset($nav_base) && (string) $nav_base !== '') ? (string) $nav_base : '/';
?>
<ul class="navbar-nav navbar-nav-pearlica <?php echo htmlspecialchars($navbar_nav_ul_extra_classes, ENT_QUOTES, 'UTF-8'); ?>">
	<li class="nav-item"><a class="nav-link nav-link-pearlica" href="<?php echo htmlspecialchars($nav_prefix, ENT_QUOTES, 'UTF-8'); ?>">HOME</a></li>
	<li class="nav-item"><a class="nav-link nav-link-pearlica" href="<?php echo htmlspecialchars($nav_prefix . 'products', ENT_QUOTES, 'UTF-8'); ?>">SHOP</a></li>
	<li class="nav-item"><a class="nav-link nav-link-pearlica" href="<?php echo htmlspecialchars($nav_prefix . 'collections', ENT_QUOTES, 'UTF-8'); ?>">COLLECTIONS</a></li>
	<?php if (!empty($nav_parent_categories)): ?>
	<li class="nav-item nav-item-dropdown">
		<a class="nav-link nav-link-pearlica" href="<?php echo htmlspecialchars($nav_prefix . 'products', ENT_QUOTES, 'UTF-8'); ?>" aria-haspopup="true" aria-expanded="false">CATEGORIES</a>
		<ul class="nav-dropdown-panel" role="menu">
			<?php foreach ($nav_parent_categories as $cat): ?>
			<?php
			$nav_cat_name = isset($cat['name']) ? (string) $cat['name'] : '';
			$nav_cat_name = $nav_cat_name !== '' ? mb_strtoupper($nav_cat_name, 'UTF-8') : '';
			?>
			<li><a class="nav-dropdown-link" href="<?php echo htmlspecialchars($nav_prefix . 'products?category=' . (int) $cat['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nav_cat_name, ENT_QUOTES, 'UTF-8'); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</li>
	<?php endif; ?>
	<li class="nav-item"><a class="nav-link nav-link-pearlica" href="<?php echo htmlspecialchars($nav_prefix . 'about', ENT_QUOTES, 'UTF-8'); ?>">ABOUT</a></li>
</ul>
