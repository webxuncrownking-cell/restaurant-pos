<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">
      <i class="bi bi-credit-card-2-front text-primary me-2"></i>SaaS Subscription & Billing
    </h1>
    <p class="text-muted small mb-0">Manage your restaurant POS subscription tier, terminal allocations, and view billing invoices.</p>
  </div>

  <!-- Current Subscription Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="text-muted small fw-semibold text-uppercase mb-1">Current Active Plan</div>
          <h3 class="fw-bold mb-1 text-primary">
            <?= htmlspecialchars($subscription['plan_name'] ?? 'Professional Plan') ?>
            <?php if (!empty($subscription['status'])): ?>
              <span class="badge bg-success ms-2 fs-6 text-uppercase"><?= htmlspecialchars($subscription['status']) ?></span>
            <?php endif; ?>
          </h3>
          <div class="text-muted small">
            Billing Cycle: <strong><?= ucfirst($subscription['billing_cycle'] ?? 'monthly') ?></strong> &bull; 
            Renewal / End Date: <strong><?= !empty($subscription['current_period_end']) ? date('M d, Y', strtotime($subscription['current_period_end'])) : 'Dec 31, 2026' ?></strong>
          </div>
        </div>

        <div>
          <div class="text-end">
            <span class="fs-4 fw-bold font-monospace">₱<?= number_format((float)($subscription['price_monthly'] ?? 1499), 2) ?></span>
            <span class="text-muted small">/ month</span>
          </div>
          <?php if ($graceDays > 0): ?>
            <div class="badge bg-info-subtle text-info border border-info-subtle mt-1">
              <i class="bi bi-clock me-1"></i> <?= $graceDays ?> Days Grace Period Protected
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Subscription Pricing Plans -->
  <h4 class="fw-bold mb-3">Available SaaS Pricing Tiers</h4>
  <div class="row g-4 mb-5">
    <?php foreach ($plans as $p): 
      $isCurrent = $subscription && (int)$subscription['plan_id'] === (int)$p['id'];
    ?>
      <div class="col-12 col-md-4">
        <div class="card h-100 border <?= $isCurrent ? 'border-primary border-2 shadow' : 'border-0 shadow-sm' ?>">
          <div class="card-body p-4 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($p['name']) ?></h5>
                <?php if ($isCurrent): ?>
                  <span class="badge bg-primary">Current Plan</span>
                <?php endif; ?>
              </div>
              <p class="text-muted small mb-3"><?= htmlspecialchars($p['description'] ?? 'Complete POS features') ?></p>

              <div class="mb-4">
                <span class="h2 fw-bold font-monospace">₱<?= number_format((float)$p['price_monthly'], 0) ?></span>
                <span class="text-muted">/ month</span>
              </div>

              <ul class="list-unstyled small mb-4">
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Up to <strong><?= (int)$p['max_terminals'] ?> POS Terminals</strong></li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Up to <strong><?= (int)$p['max_products'] ?> Menu Products</strong></li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Offline PWA Sync & Storage</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Recipe BOM & Unit Inventory</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>7-Day Offline Grace Lease</li>
              </ul>
            </div>

            <div>
              <?php if ($isCurrent): ?>
                <button class="btn btn-outline-primary w-100 disabled" disabled>Active Plan</button>
              <?php else: ?>
                <button type="button" class="btn btn-primary w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#subscribePlan<?= (int)$p['id'] ?>">
                  <i class="bi bi-credit-card me-1"></i> Choose this plan
                </button>
                <div class="modal fade" id="subscribePlan<?= (int)$p['id'] ?>" tabindex="-1" aria-labelledby="subscribePlanLabel<?= (int)$p['id'] ?>" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <form method="POST" action="<?= BASE_URL ?>/billing/payment/submit" enctype="multipart/form-data">
                        <div class="modal-header border-0 px-4 pt-4">
                          <div>
                            <h2 class="modal-title h5 fw-bold" id="subscribePlanLabel<?= (int)$p['id'] ?>">Subscribe to <?= htmlspecialchars($p['name']) ?></h2>
                            <p class="text-muted small mb-0">Submit your payment details for administrator verification.</p>
                          </div>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body px-4">
                          <?= \App\Core\CSRF::formField() ?>
                          <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                          <div class="mb-3"><label class="form-label small fw-semibold">Billing cycle</label><select class="form-select" name="billing_cycle"><option value="monthly">Monthly</option><option value="annually">Annual</option></select></div>
                          <div class="mb-3"><label class="form-label small fw-semibold">Payment method</label><select class="form-select" name="payment_method" required><option value="">Choose payment method</option><option value="gcash">GCash</option><option value="bank_transfer">Bank transfer</option><option value="cash_deposit">Cash deposit</option><option value="other">Other</option></select></div>
                          <div class="mb-3"><label class="form-label small fw-semibold">Transaction reference</label><input class="form-control" name="transaction_reference" maxlength="120" required></div>
                          <div class="mb-3"><label class="form-label small fw-semibold">Payer name</label><input class="form-control" name="payer_name" maxlength="120" required></div>
                          <div class="mb-2"><label class="form-label small fw-semibold">Proof of payment</label><input class="form-control" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required><div class="form-text">JPG, PNG, WEBP, or PDF; maximum 5 MB.</div></div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4">
                          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary fw-semibold">Submit payment for review</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>         </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (trim((string)($paymentInstructions ?? '')) !== ''): ?>
    <div class="alert alert-info mb-4"><strong>Payment instructions</strong><br><?= nl2br(htmlspecialchars($paymentInstructions, ENT_QUOTES, 'UTF-8')) ?></div>
  <?php endif; ?>
  <?php if (!empty($paymentSubmissions)): ?>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body"><h5 class="fw-bold">Subscription payment submissions</h5>
      <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Reference</th><th>Status</th><th>Review note</th></tr></thead><tbody>
      <?php foreach ($paymentSubmissions as $submission): ?><tr><td><?= htmlspecialchars(date('M d, Y H:i', strtotime($submission['created_at']))) ?></td><td><?= htmlspecialchars($submission['plan_name']) ?> (<?= htmlspecialchars($submission['billing_cycle']) ?>)</td><td>₱<?= number_format((float)$submission['amount'],2) ?></td><td><?= htmlspecialchars($submission['transaction_reference']) ?></td><td><span class="badge bg-<?= $submission['status']==='approved'?'success':($submission['status']==='rejected'?'danger':'warning') ?>"><?= htmlspecialchars(ucfirst($submission['status'])) ?></span></td><td><?= htmlspecialchars((string)($submission['reviewer_note'] ?? '—')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  <?php endif; ?>

  <!-- Invoices History -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 border-0">
      <h5 class="fw-bold mb-0">Billing & Invoice History</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Invoice #</th>
            <th>Plan Tier</th>
            <th>Issue / Due Date</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($invoices)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">No past invoices recorded.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td class="font-monospace fw-bold"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                <td><?= htmlspecialchars($inv['plan_name'] ?? 'Professional Plan') ?></td>
                <td>
                  <div class="small text-muted">Issued: <?= date('M d, Y', strtotime($inv['created_at'])) ?></div>
                  <div class="small text-muted">Due: <?= date('M d, Y', strtotime($inv['due_date'])) ?></div>
                </td>
                <td class="text-end font-monospace fw-bold">₱<?= number_format((float)$inv['total_amount'], 2) ?></td>
                <td>
                  <?php if ($inv['status'] === 'paid'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Paid</span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= ucfirst($inv['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alert('Viewing Invoice <?= htmlspecialchars($inv['invoice_number']) ?>');">
                    <i class="bi bi-file-earmark-text"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
