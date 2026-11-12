    <?php
      $currentRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      $currentRequestPage = basename(rtrim($currentRequestPath, '/'));
      $hideFormPageFooter = in_array($currentRequestPage, [
        'login',
        'register',
        'terminal-login',
        'change-password',
        'activation'
      ], true);
    ?>
    <?php if (!$hideFormPageFooter): ?>
      <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> ServeFlow POS. All rights reserved.</span>
        <span><i class="bi bi-shield-check me-1" aria-hidden="true"></i> Restaurant operations platform</span>
      </footer>
    <?php endif; ?>
  </main>
</div>

<script src="<?= htmlspecialchars(BASE_URL . '/public/assets/js/bootstrap.bundle.min.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
  // Automatically inject the CSRF token into state-changing Fetch requests.
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  const originalFetch = window.fetch.bind(window);

  window.fetch = function(url, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();

    if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
      const headers = new Headers(options.headers || {});
      if (!headers.has('X-CSRF-TOKEN')) {
        headers.set('X-CSRF-TOKEN', csrfToken);
      }
      options = { ...options, headers };
    }

    return originalFetch(url, options);
  };
});
</script>

<script>
(function () {
    function addServeFlowBackButton() {
        if (/\\/dashboard\\/?$/.test(window.location.pathname)) return;
        const targets = [
            document.querySelector('.app-topbar-left'),
            document.querySelector('.pos-brand-lockup')
        ];

        const target = targets.find(Boolean);
        if (!target || target.querySelector('.app-back-button')) return;

        const button = document.createElement('a');
        button.className = 'app-back-button';
        button.href = '<?= BASE_URL ?>/dashboard';
        button.setAttribute('aria-label', 'Go back');
        button.innerHTML = '<i class="bi bi-arrow-left"></i><span>Back</span>';

        target.insertBefore(button, target.firstChild);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addServeFlowBackButton);
    } else {
        addServeFlowBackButton();
    }
})();
</script>


<script>
(function () {
    function styleRedeemLoyaltyField() {
        if (!document.querySelector('.pos-workspace')) return;

        document.querySelectorAll('label').forEach(function (label) {
            if (!label.textContent.toLowerCase().includes('redeem loyalty points')) return;

            let parent = label;
            for (let i = 0; i < 5 && parent; i++, parent = parent.parentElement) {
                const field = parent.querySelector('select');
                if (field) {
                    field.classList.add('redeem-loyalty-select');
                    break;
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', styleRedeemLoyaltyField);
    } else {
        styleRedeemLoyaltyField();
    }
})();
</script>

</body>
</html>
