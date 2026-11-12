<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Platform Pricing & Payment Settings</h1>
    <p class="text-muted mb-0">Manage activation pricing, subscription prices, and exactly which premium modules each plan unlocks.</p>
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
          <div class="form-text">New registrations use this fee. Existing pending submissions retain their submitted amount.</div>
        </div>
        <div>
          <label class="form-label fw-semibold" for="payment_instructions">Payment instructions shown to restaurant owners</label>
          <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="5" maxlength="5000"><?= htmlspecialchars((string)($settings['activation_payment_instructions'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0">Subscription Plans & Entitlements</h5>
        <small class="text-muted">Unchecked modules are not included. Changes affect access immediately after saving. Existing operational data is retained if a module is disabled.</small>
      </div>
      <?php if (empty($plans)): ?>
        <div class="card-body text-muted">No subscription plans have been configured yet.</div>
      <?php else: ?>
        <div class="accordion accordion-flush" id="planEntitlements">
          <?php foreach ($plans as $plan): $planId = (int)$plan['id']; $features = is_array($plan['features'] ?? null) ? $plan['features'] : []; ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="planHeading<?= $planId ?>">
                <button class="accordion-button <?= $planId === (int)$plans[0]['id'] ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#planBody<?= $planId ?>">
                  <span class="me-3"><strong><?= htmlspecialchars((string)$plan['name'], ENT_QUOTES, 'UTF-8') ?></strong><span class="text-muted small ms-2"><?= htmlspecialchars((string)$plan['slug'], ENT_QUOTES, 'UTF-8') ?></span></span>
                  <span class="ms-auto me-3 small text-muted"><?= !empty($plan['is_active']) ? 'Active' : 'Inactive' ?></span>
                </button>
              </h2>
              <div id="planBody<?= $planId ?>" class="accordion-collapse collapse <?= $planId === (int)$plans[0]['id'] ? 'show' : '' ?>" data-bs-parent="#planEntitlements">
                <div class="accordion-body">
                  <div class="row g-3 mb-4">
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Monthly price (PHP)</label>
                      <input class="form-control" type="number" min="0" max="1000000" step="0.01" required name="plans[<?= $planId ?>][monthly]" value="<?= htmlspecialchars((string)$plan['price_monthly'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Annual price (PHP)</label>
                      <input class="form-control" type="number" min="0" max="10000000" step="0.01" required name="plans[<?= $planId ?>][annually]" value="<?= htmlspecialchars((string)$plan['price_annually'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Maximum staff accounts</label>
                      <input class="form-control" type="number" min="0" max="5000" step="1" required name="plans[<?= $planId ?>][max_staff]" value="<?= (int)($plan['max_staff'] ?? 0) ?>">
                      <div class="form-text">Enter 0 for unlimited. The owner account is not counted.</div>
                    </div>
                  </div>
                  <h6 class="fw-bold mb-3">Premium modules included in this plan</h6>
                  <div class="row g-2">
                    <?php foreach (($featureCatalog ?? []) as $featureKey => $featureLabel): ?>
                      <?php
                        $legacyAliases = [
                          'advanced_reports' => ['reports'],
                          'recipe_costing' => ['recipe_bom'],
                          'multi_terminal' => ['multi_device'],
                        ];
                        $isEnabled = !empty($features[$featureKey]);
                        if (!$isEnabled) foreach (($legacyAliases[$featureKey] ?? []) as $legacyKey) {
                          if (array_key_exists($legacyKey, $features) && !empty($features[$legacyKey])) $isEnabled = true;
                        }
                      ?>
                      <div class="col-md-6 col-lg-4">
                        <label class="border rounded-3 p-3 d-flex align-items-start gap-2 h-100">
                          <input class="form-check-input mt-1" type="checkbox" value="1" name="plans[<?= $planId ?>][features][<?= htmlspecialchars((string)$featureKey, ENT_QUOTES, 'UTF-8') ?>]" <?= $isEnabled ? 'checked' : '' ?>>
                          <span><?= htmlspecialchars((string)$featureLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="form-text mt-3">Standard POS functions such as checkout, orders, KDS, menu management, and basic inventory remain available independently of these premium module switches.</div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary px-4">Save Pricing & Plan Features</button></div>
  </form>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
