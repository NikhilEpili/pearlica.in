</div><!-- .admin-main -->
</div><!-- .admin-wrapper -->
<script>
(function() {
	var sidebar = document.getElementById('admin-sidebar');
	var toggle = document.getElementById('admin-sidebar-toggle');
	if (!sidebar || !toggle) return;
	var key = 'admin-sidebar-collapsed';
	function isCollapsed() { return localStorage.getItem(key) === '1'; }
	function setCollapsed(collapsed) {
		sidebar.classList.toggle('collapsed', collapsed);
		localStorage.setItem(key, collapsed ? '1' : '0');
		toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
		toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
		toggle.querySelector('.sidebar-chevron').style.transform = collapsed ? 'rotate(180deg)' : '';
	}
	toggle.addEventListener('click', function() { setCollapsed(!sidebar.classList.contains('collapsed')); });
	setCollapsed(isCollapsed());
})();
</script>
