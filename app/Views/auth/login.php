<div class="container my-auto py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
      
      <!-- Brand Header -->
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-warning-emphasis p-3 rounded-circle mb-3 shadow-sm" style="width: 68px; height: 68px;">
          <i class="bi bi-shop fs-1"></i>
        </div>
        <h3 class="fw-bold text-dark mb-1">Restaurant POS</h3>
        <p class="text-muted small">Multi-Tenant Management & Point of Sale System</p>
      </div>

      <!-- Flash Messages -->
      <?php if (!empty($flashes)): ?>
        <?php foreach ($flashes as $flash): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Login Card -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-semibold text-secondary d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock text-primary"></i> Sign In to Terminal
          </h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/login" id="loginForm">
            <?= $csrf_field ?>

            <div class="mb-3">
              <label for="login" class="form-label fw-medium text-secondary small">Username or Email</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="login" name="login" required placeholder="Enter username or email" autofocus>
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-medium text-secondary small">Password</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required placeholder="Enter password">
              </div>
            </div>

            <div class="mb-4">
              <label for="tenant_slug" class="form-label fw-medium text-secondary small d-flex justify-content-between">
                <span>Restaurant Slug <span class="text-muted fw-normal">(Optional)</span></span>
                <span class="text-muted fw-normal">e.g. mang-juan</span>
              </label>
              <input type="text" class="form-control" id="tenant_slug" name="tenant_slug" placeholder="Leave empty for auto-detect or superadmin">
            </div>

            <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark shadow-sm">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
          </form>
        </div>

        <!-- Quick Demo Credentials for Fast XAMPP Testing -->
        <div class="card-footer bg-light border-top p-3 text-center">
          <span class="d-block text-muted small fw-semibold mb-2">⚡ Quick Test Account Fill:</span>
          <div class="d-flex flex-wrap gap-1 justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillCreds('juan_admin', 'RestoStaff2026!', 'mang-juan')">
              Mang Juan (Owner)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillCreds('maria_cashier', 'RestoStaff2026!', 'mang-juan')">
              Maria (Cashier)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillCreds('platform_admin', 'AdminPassword2026!', '')">
              SaaS Superadmin
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillCreds('nena_admin', 'RestoStaff2026!', 'aling-nena')">
              Aling Nena (Tenant 2)
            </button>
          </div>
        </div>
      </div>

      <!-- Diagnostics Link -->
      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/install" class="text-decoration-none text-muted small">
          <i class="bi bi-tools me-1"></i> System Diagnostics & Setup Wizard
        </a>
      </div>

    </div>
  </div>
</div>

<script>
function fillCreds(login, pass, slug) {
  document.getElementById('login').value = login;
  document.getElementById('password').value = pass;
  document.getElementById('tenant_slug').value = slug;
}
</script>
