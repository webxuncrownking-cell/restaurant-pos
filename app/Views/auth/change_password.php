<div class="container my-auto py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
      
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-warning-emphasis p-3 rounded-circle mb-3 shadow-sm" style="width: 60px; height: 60px;">
          <i class="bi bi-key-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Set New Secure Password</h4>
        <p class="text-muted small">Your account was provisioned with a default password. Please choose a private, secure password before continuing.</p>
      </div>

      <?php if (!empty($flashes)): ?>
        <?php foreach ($flashes as $flash): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/change-password">
            <?= $csrf_field ?>

            <div class="mb-3">
              <label for="new_password" class="form-label fw-medium text-secondary small">New Password (min. 8 characters)</label>
              <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password" autofocus>
            </div>

            <div class="mb-4">
              <label for="confirm_password" class="form-label fw-medium text-secondary small">Confirm New Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password">
            </div>

            <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark shadow-sm">
              <i class="bi bi-check2-circle me-1"></i> Update Password & Proceed
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
