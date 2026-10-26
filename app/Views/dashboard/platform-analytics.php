<div class="container-fluid px-4 py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
    <div>
      <h2 class="h3 fw-bold mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Platform Analytics</h2>
      <p class="text-muted small mb-0">Monitor tenant adoption, subscription mix, recurring revenue estimates, and POS usage.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/platform-analytics/export.csv?type=revenue" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Revenue CSV</a>
      <a href="<?= BASE_URL ?>/platform-analytics/export.csv?type=subscriptions" class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>Subscriptions CSV</a>
      <a href="<?= BASE_URL ?>/platform-analytics/export.csv?type=restaurants" class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>Restaurants CSV</a>
      <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <?php if (!empty($analytics_error)): ?>
    <div class="alert alert-warning" role="alert"><?= htmlspecialchars($analytics_error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100"><span class="text-muted small">Registered Restaurants</span><h3 class="fw-bold mb-0"><?= number_format((int)($total_restaurants ?? 0)) ?></h3></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100"><span class="text-muted small">Active Restaurants</span><h3 class="fw-bold mb-0"><?= number_format((int)($active_restaurants ?? 0)) ?></h3><small class="text-muted">Accounts marked active</small></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100"><span class="text-muted small">Estimated Monthly Recurring Revenue</span><h3 class="fw-bold mb-0">₱<?= number_format((float)($estimated_mrr ?? 0), 2) ?></h3><small class="text-muted">Plan-based estimate, not cash collected</small></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100"><span class="text-muted small">Active or Trial Plans</span><h3 class="fw-bold mb-0"><?php
        $activeTrialCount = 0;
        foreach (($subscription_counts ?? []) as $row) {
          if (in_array(strtolower((string)($row['status'] ?? '')), ['active', 'trial'], true)) $activeTrialCount += (int)($row['total'] ?? 0);
        }
        echo number_format($activeTrialCount);
      ?></h3><small class="text-muted">Current active and trial subscriptions</small></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100">
        <span class="text-muted small">Payments Collected — All Time</span>
        <h3 class="fw-bold mb-0">₱<?= number_format((float)($actual_collected_total ?? 0), 2) ?></h3>
        <small class="text-muted">Successful platform payments recorded</small>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100">
        <span class="text-muted small">Payments Collected — This Month</span>
        <h3 class="fw-bold mb-0">₱<?= number_format((float)($actual_collected_month ?? 0), 2) ?></h3>
        <small class="text-muted">Based on successful payment records</small>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100">
        <span class="text-muted small">Outstanding Open Invoices</span>
        <h3 class="fw-bold mb-0">₱<?= number_format((float)($outstanding_invoices['amount'] ?? 0), 2) ?></h3>
        <small class="text-muted"><?= number_format((int)($outstanding_invoices['invoice_count'] ?? 0)) ?> open invoices</small>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card card-metric p-3 bg-white h-100">
        <span class="text-muted small">Inactive Restaurants</span>
        <h3 class="fw-bold mb-0"><?= number_format((int)($inactive_restaurants ?? 0)) ?></h3>
        <small class="text-muted">Registered accounts not marked active</small>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3">
      <h5 class="mb-1 fw-semibold">Growth & Actual Collections — Last 6 Months</h5>
      <small class="text-muted">Registration counts use restaurant creation dates. Collections include only payments marked succeeded.</small>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-muted">
          <tr><th>Month</th><th class="text-end">New Restaurants</th><th class="text-end">Successful Payments</th><th class="text-end">Collected (PHP)</th></tr>
        </thead>
        <tbody>
          <?php
            $growthRows = [];
            foreach (($monthly_registrations ?? []) as $row) $growthRows[$row['month_key']]['registrations'] = (int)$row['new_restaurants'];
            foreach (($monthly_collections ?? []) as $row) {
              $growthRows[$row['month_key']]['payments'] = (int)$row['payment_count'];
              $growthRows[$row['month_key']]['collected'] = (float)$row['collected'];
            }
            ksort($growthRows);
          ?>
          <?php if (!empty($growthRows)): foreach ($growthRows as $month => $row): ?>
            <tr>
              <td><?= htmlspecialchars(date('F Y', strtotime($month . '-01')), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end"><?= number_format((int)($row['registrations'] ?? 0)) ?></td>
              <td class="text-end"><?= number_format((int)($row['payments'] ?? 0)) ?></td>
              <td class="text-end fw-semibold">₱<?= number_format((float)($row['collected'] ?? 0), 2) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No registration or successful payment activity found for the last six months.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold">Subscription Status</h5></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted"><tr><th>Status</th><th class="text-end">Subscriptions</th></tr></thead>
            <tbody>
              <?php if (!empty($subscription_counts)): foreach ($subscription_counts as $row): ?>
                <tr><td><?= htmlspecialchars(ucfirst((string)$row['status']), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end fw-semibold"><?= number_format((int)$row['total']) ?></td></tr>
              <?php endforeach; else: ?>
                <tr><td colspan="2" class="text-center text-muted py-4">No subscription records found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold">Plan Distribution</h5></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted"><tr><th>Plan</th><th class="text-end">Active / Trial</th></tr></thead>
            <tbody>
              <?php if (!empty($plan_distribution)): foreach ($plan_distribution as $row): ?>
                <tr><td><?= htmlspecialchars((string)$row['plan_name'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end fw-semibold"><?= number_format((int)$row['total']) ?></td></tr>
              <?php endforeach; else: ?>
                <tr><td colspan="2" class="text-center text-muted py-4">No active or trial plan subscriptions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3"><h5 class="mb-1 fw-semibold">POS Usage — Last 6 Months</h5><small class="text-muted">Non-cancelled orders recorded by the platform. This measures usage, not sales revenue.</small></div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-muted"><tr><th>Month</th><th class="text-end">Orders</th><th class="text-end">Restaurants Used</th></tr></thead>
        <tbody>
          <?php if (!empty($monthly_orders)): foreach ($monthly_orders as $row): ?>
            <tr>
              <td><?= htmlspecialchars(date('F Y', strtotime((string)$row['month_key'] . '-01')), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-semibold"><?= number_format((int)$row['total_orders']) ?></td>
              <td class="text-end"><?= number_format((int)$row['active_restaurants']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="text-center text-muted py-4">No order activity found for the last six months.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
