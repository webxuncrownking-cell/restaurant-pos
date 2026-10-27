    <footer class="app-footer">
      <span>&copy; <?= date('Y') ?> ServeFlow POS. All rights reserved.</span>
      <span><i class="bi bi-shield-check me-1" aria-hidden="true"></i> Restaurant operations platform</span>
    </footer>
  </main>
</div>

<script src="<?= htmlspecialchars(BASE_URL . '/public/assets/js/bootstrap.bundle.min.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('appSidebar');
    const toggle = document.getElementById('appSidebarToggle');
    const backdrop = document.getElementById('appSidebarBackdrop');
    const collapseToggle = document.getElementById('appSidebarCollapseToggle');
    const themeToggle = document.getElementById('appThemeToggle');
    const root = document.documentElement;

    // Apply saved preferences before interaction; defaults are expanded sidebar and light theme.
    let savedTheme = 'light';
    let savedCollapse = false;
    try {
      savedTheme = localStorage.getItem('serveflow-theme') === 'dark' ? 'dark' : 'light';
      savedCollapse = localStorage.getItem('serveflow-sidebar-collapsed') === '1';
    } catch (error) {}
    const applyTheme = theme => {
      root.setAttribute('data-theme', theme);
      if (themeToggle) {
        const dark = theme === 'dark';
        themeToggle.checked = dark;
        themeToggle.setAttribute('aria-checked', dark ? 'true' : 'false');
        const label = document.querySelector('.app-theme-switch-label');
        if (label) label.textContent = dark ? 'Dark mode' : 'Light mode';
        themeToggle.setAttribute('aria-label', dark ? 'Dark mode enabled' : 'Dark mode disabled');
      }
    };
    applyTheme(savedTheme);
    if (savedCollapse) root.setAttribute('data-sidebar-collapsed', 'true');
    else root.removeAttribute('data-sidebar-collapsed');
    document.body.classList.toggle('sidebar-collapsed', savedCollapse && window.innerWidth > 991);
    const updateCollapseToggle = () => {
      const collapsed = root.getAttribute('data-sidebar-collapsed') === 'true';
      if (!collapseToggle) return;
      collapseToggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
      collapseToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      collapseToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
      collapseToggle.innerHTML = '<i class="bi ' + (collapsed ? 'bi-chevron-double-right' : 'bi-chevron-double-left') + '" aria-hidden="true"></i>';
    };
    updateCollapseToggle();
    collapseToggle?.addEventListener('click', () => {
      const collapsed = root.getAttribute('data-sidebar-collapsed') !== 'true';
      if (collapsed) root.setAttribute('data-sidebar-collapsed', 'true');
      else root.removeAttribute('data-sidebar-collapsed');
      document.body.classList.toggle('sidebar-collapsed', collapsed && window.innerWidth > 991);
      try { localStorage.setItem('serveflow-sidebar-collapsed', collapsed ? '1' : '0'); } catch (error) {}
      updateCollapseToggle();
    });
    themeToggle?.addEventListener('change', () => {
      const theme = themeToggle.checked ? 'dark' : 'light';
      applyTheme(theme);
      try { localStorage.setItem('serveflow-theme', theme); } catch (error) {}
    });

    const closeSidebar = () => {
      if (!sidebar || !toggle || !backdrop) return;
      sidebar.classList.remove('is-open');
      backdrop.classList.remove('is-visible');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('sidebar-open');
    };

    const openSidebar = () => {
      if (!sidebar || !toggle || !backdrop) return;
      sidebar.classList.add('is-open');
      backdrop.classList.add('is-visible');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.classList.add('sidebar-open');
    };

    toggle?.addEventListener('click', () => {
      if (sidebar?.classList.contains('is-open')) closeSidebar();
      else openSidebar();
    });
    backdrop?.addEventListener('click', closeSidebar);
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeSidebar();
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 991) {
        closeSidebar();
        document.body.classList.toggle('sidebar-collapsed', root.getAttribute('data-sidebar-collapsed') === 'true');
      } else {
        document.body.classList.remove('sidebar-collapsed');
      }
      updateCollapseToggle();
    });

    // Automatically inject the CSRF token into state-changing Fetch requests.
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const originalFetch = window.fetch.bind(window);
    window.fetch = function(url, options = {}) {
      const method = String(options.method || 'GET').toUpperCase();
      if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
        const headers = new Headers(options.headers || {});
        if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', csrfToken);
        options = { ...options, headers };
      }
      return originalFetch(url, options);
    };
  });
</script>
</body>
</html>
