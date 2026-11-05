<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Platform Pricing & Payment Settings</h1>
    <p class="text-muted mb-0">Manage the one-time restaurant activation fee, manual payment instructions, and subscription plan prices.</p>
  </div>

  <?php foreach (($flashes ?? []) as $flash): ?>
    <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?>"><?= htmlspecialchars((string)($flash['message'] ?? '')) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="<?= BASE_URL ?>/platform-pricing/save">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CSRF::getToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3"><h5 class="fw-bold mb-0">Restaurant Activation</h5></div>
      <div class="card-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="activation_fee">One-time activation fee (PHP)</label>
          <input class="form-control" id="activation_fee" name="activation_fee" type="number" min="0" max="1000000" step="0.01" required value="<?= htmlspecialchars((string)($settings['activation_fee_php'] ?? '399.00'), ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-text">New registrations use this fee when submitting payment. Existing pending submissions retain the amount recorded at submission.</div>
        </div>
        <div>
          <label class="form-label fw-semibold" for="payment_instructions">Payment instructions shown to restaurant owners</label>
          <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="5" maxlength="5000" placeholder="Enter GCash or bank transfer details..."><?= htmlspecialchars((string)($settings['activation_payment_instructions'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0">Subscription Plan Prices</h5>
        <small class="text-muted">These values update the plan catalogue. This screen does not collect or verify subscription payments.</small>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th class="ps-4">Plan</th><th>Monthly (PHP)</th><th>Annual (PHP)</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (empty($plans)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No subscription plans have been configured yet.</td></tr>
          <?php else: foreach ($plans as $plan): ?>
            <tr>
              <td class="ps-4"><strong><?= htmlspecialchars((string)$plan['name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars((string)$plan['slug'], ENT_QUOTES, 'UTF-8') ?></div></td>
              <td><input class="form-control" style="max-width:180px" type="number" min="0" max="1000000" step="0.01" required name="plans[<?= (int)$plan['id'] ?>][monthly]" value="<?= htmlspecialchars((string)$plan['price_monthly'], ENT_QUOTES, 'UTF-8') ?>"></td>
              <td><input class="form-control" style="max-width:180px" type="number" min="0" max="10000000" step="0.01" required name="plans[<?= (int)$plan['id'] ?>][annually]" value="<?= htmlspecialchars((string)$plan['price_annually'], ENT_QUOTES, 'UTF-8') ?>"></td>
              <td><?= !empty($plan['is_active']) ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>' ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary px-4">Save Platform Pricing</button></div>
  </form>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
