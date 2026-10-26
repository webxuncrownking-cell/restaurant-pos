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
      if (window.innerWidth > 991) closeSidebar();
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
