<style>
  .password-page-wrap {
    min-height: calc(100vh - 58px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(20px, 4vh, 44px) 18px;
  }
  .password-page-wrap > .container {
    width: 100%;
    max-width: 560px;
    margin: 0 !important;
    padding: 0 !important;
  }
  .password-page-wrap .password-brand-title {
    font-size: clamp(1.8rem, 2.2vw, 2.15rem);
    letter-spacing: -.035em;
  }
  @media (max-height: 760px) {
    .password-page-wrap { align-items: flex-start; padding-top: 22px; padding-bottom: 22px; }
  }
</style>
<div class="password-page-wrap">
  <div class="container">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-warning-emphasis p-3 rounded-circle mb-3 shadow-sm" style="width:68px;height:68px;">
        <i class="bi bi-shop fs-1"></i>
      </div>
      <h1 class="fw-bold text-dark mb-1 password-brand-title">ServeFlow POS</h1>
      <p class="text-muted small mb-0">Restaurant operations, made simple.</p>
    </div>

    <?php if (!empty($flashes)): ?>
      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show shadow-sm" role="alert">
          <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <h2 class="h5 mb-0 fw-semibold text-secondary d-flex align-items-center gap-2">
          <i class="bi bi-shield-lock text-primary"></i> Set New Secure Password
        </h2>
      </div>
      <div class="card-body p-4">
        <p class="text-muted small mb-4">Your account was provisioned with a default password. Choose a private, secure password before continuing.</p>
        <form method="POST" action="<?= BASE_URL ?>/change-password" id="changePasswordForm">
          <?= $csrf_field ?>

          <div class="mb-3">
            <label for="new_password" class="form-label fw-medium text-secondary small">New Password (min. 8 characters)</label>
            <input type="password" class="form-control ps-3" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password" autocomplete="new-password" autofocus>
          </div>

          <div class="mb-4">
            <label for="confirm_password" class="form-label fw-medium text-secondary small">Confirm New Password</label>
            <input type="password" class="form-control ps-3" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Update Password & Proceed
          </button>
        </form>
      </div>
    </div>
    <div class="text-center mt-3">
      <span class="text-muted small">Restaurant operations, made simple.</span>
    </div>
  </div>
</div>
