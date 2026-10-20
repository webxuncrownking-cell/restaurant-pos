  <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
    <div class="container">
      <span>Restaurant POS & Management SaaS &copy; <?= date('Y') ?> &bull; Phase 1 Multi-Tenant Foundation</span>
      <div class="mt-1">
        <span class="badge bg-light text-dark border">PHP <?= PHP_VERSION ?></span>
        <span class="badge bg-light text-dark border">Bootstrap 5.3</span>
        <span class="badge bg-light text-dark border">InnoDB utf8mb4</span>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Global Fetch API CSRF Header Helper -->
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      // Automatically inject CSRF header into all Fetch requests
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
      
      const originalFetch = window.fetch;
      window.fetch = function(url, options = {}) {
        options.headers = options.headers || {};
        if (options.method && options.method.toUpperCase() !== 'GET' && csrfToken) {
          if (options.headers instanceof Headers) {
            if (!options.headers.has('X-CSRF-TOKEN')) {
              options.headers.set('X-CSRF-TOKEN', csrfToken);
            }
          } else if (!options.headers['X-CSRF-TOKEN']) {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
          }
        }
        return originalFetch(url, options);
      };
    });
  </script>
</body>
</html>
