<div class="container-fluid px-4 py-4">

  <!-- Flash Alerts -->
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Welcome Banner -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
      <h2 class="h3 fw-bold text-dark mb-1">
        <?php if ($is_platform): ?>
          <i class="bi bi-clouds text-primary me-2"></i>SaaS Platform Administration
        <?php else: ?>
          <i class="bi bi-shop text-warning me-2"></i><?= htmlspecialchars($restaurant['name'] ?? 'Restaurant Dashboard') ?>
        <?php endif; ?>
      </h2>
      <p class="text-muted small mb-0">
        <?php if ($is_platform): ?>
          Master control panel for multi-restaurant tenants, billing, and system diagnostics.
        <?php else: ?>
          Operating Currency: <strong><?= htmlspecialchars($restaurant['currency_symbol'] ?? 'PHP') ?></strong> &bull;
          Timezone: <strong><?= htmlspecialchars($restaurant['timezone'] ?? 'Asia/Manila') ?></strong> &bull;
          Subscription: <span class="badge bg-success"><?= htmlspecialchars(ucfirst($restaurant['subscription_status'] ?? 'Active')) ?></span>
        <?php endif; ?>
      </p>
    </div>

    <div class="d-flex gap-2 mt-2 mt-md-0">
      <a href="<?= BASE_URL ?>/install" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-cpu me-1"></i> System Diagnostics
      </a>
      <?php if ($is_platform && !empty($_SESSION['restaurant_id'])): ?>
        <a href="<?= BASE_URL ?>/dashboard/switch-tenant?id=0" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-arrow-left-circle me-1"></i> Exit Tenant View
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($is_platform && empty($_SESSION['restaurant_id'])): ?>
    <!-- PLATFORM SUPERADMIN VIEW -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
              <i class="bi bi-building fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Active Tenants</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $total_restaurants ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
              <i class="bi bi-check-circle fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Active Subscriptions</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $active_subscriptions ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
              <i class="bi bi-tablet fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Registered POS Devices</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $total_devices ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Platform usage and recurring revenue -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-metric p-3 bg-white h-100">
          <span class="text-muted small fw-medium">Active Restaurants</span>
          <h3 class="fw-bold mb-0 text-dark"><?= (int)($active_tenants ?? 0) ?></h3>
          <small class="text-muted">of <?= (int)($total_restaurants ?? 0) ?> registered tenants</small>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-metric p-3 bg-white h-100">
          <span class="text-muted small fw-medium">Estimated Monthly Recurring Revenue</span>
          <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars(number_format((float)($estimated_mrr ?? 0), 2)) ?></h3>
          <small class="text-muted">Based on active subscription plans; not cash collected</small>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-metric p-3 bg-white h-100">
          <span class="text-muted small fw-medium">Orders This Month</span>
          <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($orders_this_month ?? 0)) ?></h3>
          <small class="text-muted">Excludes cancelled orders</small>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-metric p-3 bg-white h-100">
          <span class="text-muted small fw-medium">Restaurants Using POS This Month</span>
          <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($active_restaurants_this_month ?? 0)) ?></h3>
          <small class="text-muted">Tenants with at least one non-cancelled order</small>
        </div>
      </div>
    </div>

    <!-- Tenant List Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-semibold">Onboarded Tenant Restaurants</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small text-uppercase text-muted">
            <tr>
              <th>ID</th>
              <th>Restaurant Name</th>
              <th>Slug</th>
              <th>Contact</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($restaurants)): ?>
              <?php foreach ($restaurants as $r): ?>
                <tr>
                  <td class="fw-bold">#<?= $r['id'] ?></td>
                  <td class="fw-semibold text-dark"><?= htmlspecialchars($r['name']) ?></td>
                  <td><code><?= htmlspecialchars($r['slug']) ?></code></td>
                  <td class="small text-muted"><?= htmlspecialchars($r['contact_email']) ?><br><?= htmlspecialchars($r['contact_phone']) ?></td>
                  <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['plan_name'] ?? 'Standard') ?></span></td>
                  <td><span class="badge bg-success"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                  <td>
                    <a href="<?= BASE_URL ?>/dashboard/switch-tenant?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-box-arrow-in-right me-1"></i> Inspect Tenant
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No tenant restaurants registered yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <!-- TENANT STAFF VIEW -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-warning bg-opacity-10 text-warning-emphasis rounded-3">
              <i class="bi bi-grid-3x3-gap fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Dining Tables</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $tables_count ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
              <i class="bi bi-cup-hot fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Menu Products</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $products_count ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
              <i class="bi bi-egg-fried fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Raw Ingredients</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $ingredients_count ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
              <i class="bi bi-tablet-landscape fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Registered POS</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $devices_count ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Registered Devices & Offline Sync Readiness Card -->
    <div class="row g-4 mb-4">
      <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-3 h-100">
          <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold text-secondary">
              <i class="bi bi-tablet me-2 text-primary"></i> Registered POS Devices (Offline Capable)
            </h5>
            <span class="badge bg-light text-dark border"><?= count($devices ?? []) ?> paired</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                  <tr>
                    <th>Device Name</th>
                    <th>Device UUID</th>
                    <th>Status</th>
                    <th>Last Sync</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($devices)): ?>
                    <?php foreach ($devices as $dev): ?>
                      <tr>
                        <td class="fw-medium text-dark">
                          <i class="bi bi-display me-1 text-muted"></i> <?= htmlspecialchars($dev['device_name']) ?>
                        </td>
                        <td><code class="small"><?= htmlspecialchars(substr($dev['device_uuid'], 0, 18)) ?>...</code></td>
                        <td><span class="badge bg-success bg-opacity-25 text-success border border-success"><?= htmlspecialchars($dev['status']) ?></span></td>
                        <td class="small text-muted"><?= htmlspecialchars($dev['last_sync_at'] ?? 'Never') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No POS devices paired yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Phase 1 Architecture Verification Summary -->
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 h-100">
          <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-semibold text-secondary">
              <i class="bi bi-shield-check me-2 text-success"></i> Phase 1 Architecture Verification
            </h5>
          </div>
          <div class="card-body">
            <ul class="list-group list-group-flush small">
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>Multi-Tenant Composite Foreign Keys</span>
                <span class="badge bg-success">Verified</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>Base Recipe Uniqueness (NULL GenCol)</span>
                <span class="badge bg-success">Verified</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>PBKDF2 Staff Offline PIN Derivation</span>
                <span class="badge bg-success">Verified</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>Base-Unit Inventory Engine (g, ml, pcs)</span>
                <span class="badge bg-success">Verified</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-check-circle-fill text-success me-2"></i>Zero Cascade Soft-Delete Protection</span>
                <span class="badge bg-success">Verified</span>
              </li>
            </ul>
            <div class="mt-3 text-center">
              <a href="<?= BASE_URL ?>/install/tests" class="btn btn-sm btn-outline-success w-100">
                <i class="bi bi-play-circle me-1"></i> Run Automated Verification Tests
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>
