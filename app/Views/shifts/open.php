<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-6">
      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shifts">Cashier Shifts</a></li>
          <li class="breadcrumb-item active" aria-current="page">Open Shift</li>
        </ol>
      </nav>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white p-4 text-center">
          <i class="bi bi-box-arrow-in-right fs-1 mb-2"></i>
          <h4 class="fw-bold mb-1">Open Shift & Register Float</h4>
          <p class="small text-white-50 mb-0">Record your starting drawer cash before commencing POS sales.</p>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/shifts/open">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold">Cashier Staff</label>
              <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['full_name']) ?> (@<?= htmlspecialchars($user['username']) ?>)" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">POS Terminal Device <span class="text-danger">*</span></label>
              <select name="device_id" class="form-select form-select-lg" required>
                <?php if (empty($devices)): ?>
                  <option value="" disabled selected>No devices registered! Register a device first.</option>
                <?php else: ?>
                  <?php foreach ($devices as $dev): ?>
                    <option value="<?= $dev['id'] ?>" <?= $dev['status'] !== 'active' ? 'disabled' : '' ?>>
                      <?= htmlspecialchars($dev['device_name']) ?> (<?= $dev['status'] ?>)
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <div class="form-text">Select the physical tablet or terminal device assigned to your station.</div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Starting Cash Float (₱) <span class="text-danger">*</span></label>
              <div class="input-group input-group-lg">
                <span class="input-group-text fw-bold">₱</span>
                <input type="number" step="0.01" min="0" name="opening_cash" class="form-control fw-bold fs-4 text-success" placeholder="0.00" value="1000.00" required>
              </div>
              <div class="form-text">Count physical bills and coins currently inside the cash drawer.</div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Shift Notes / Float Breakdown (Optional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="e.g. 5x ₱100, 10x ₱50..."></textarea>
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary btn-lg fw-semibold" <?= empty($devices) ? 'disabled' : '' ?>>
                <i class="bi bi-unlock-fill me-1"></i> Confirm & Open Drawer
              </button>
              <a href="<?= BASE_URL ?>/shifts" class="btn btn-light">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
