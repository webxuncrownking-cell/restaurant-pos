<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Breadcrumb & Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shifts">Cashier Shifts</a></li>
          <li class="breadcrumb-item active" aria-current="page">Active Shift #<?= (int)$activeShift['id'] ?></li>
        </ol>
      </nav>
      <h1 class="h3 fw-bold mb-0">
        <i class="bi bi-calculator text-success me-2"></i>Active Cash Drawer Session
      </h1>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cashMovementModal">
        <i class="bi bi-arrow-left-right me-1"></i> Cash In / Out / Drop
      </button>
      <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
        <i class="bi bi-lock-fill me-1"></i> Close & Audit Shift
      </button>
    </div>
  </div>

  <!-- Session Metadata Banner -->
  <div class="card border-0 shadow-sm bg-light mb-4">
    <div class="card-body py-3 px-4">
      <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
          <div class="text-muted small">Terminal Device:</div>
          <div class="fw-bold fs-6">
            <i class="bi bi-tablet-landscape me-1 text-primary"></i><?= htmlspecialchars($activeShift['device_name']) ?>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-muted small">Cashier in Charge:</div>
          <div class="fw-bold fs-6">
            <i class="bi bi-person-circle me-1 text-secondary"></i><?= htmlspecialchars($activeShift['cashier_name']) ?>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-muted small">Shift Started At:</div>
          <div class="fw-bold fs-6">
            <i class="bi bi-clock me-1 text-info"></i><?= date('M d, Y h:i:s A', strtotime($activeShift['opened_at'])) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Financial Reconciliation Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Starting Float</div>
        <div class="h3 fw-bold text-dark font-monospace mb-0">₱<?= number_format($summary['opening_cash'], 2) ?></div>
        <div class="small text-muted mt-2">Counted at shift opening</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Total Cash Sales</div>
        <div class="h3 fw-bold text-success font-monospace mb-0">+₱<?= number_format($summary['cash_sales'], 2) ?></div>
        <div class="small text-muted mt-2"><?= (int)$summary['paid_orders_count'] ?> completed cash orders</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Cash In / Out Adjustments</div>
        <div class="d-flex justify-content-between align-items-baseline mb-0">
          <span class="text-info font-monospace fw-bold">+₱<?= number_format($summary['cash_in'], 2) ?></span>
          <span class="text-warning font-monospace fw-bold">-₱<?= number_format($summary['cash_out'], 2) ?></span>
        </div>
        <div class="small text-muted mt-2">Petty cash & safe drops</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3 bg-primary text-white">
        <div class="text-white-50 small fw-semibold text-uppercase mb-1">Expected In Drawer</div>
        <div class="h3 fw-bold font-monospace mb-0">₱<?= number_format($summary['expected_cash'], 2) ?></div>
        <div class="small text-white-50 mt-2">Float + Sales + In - Out</div>
      </div>
    </div>
  </div>

  <!-- Cash Movements Ledger -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0">Cash Movements Ledger</h5>
      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cashMovementModal">
        <i class="bi bi-plus-lg me-1"></i> Record Cash Movement
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Time</th>
            <th>Type</th>
            <th class="text-end">Amount</th>
            <th>Reason / Description</th>
            <th>Cashier Audit</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                No manual cash movements recorded yet in this shift.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($movements as $m): ?>
              <tr>
                <td><?= date('h:i A', strtotime($m['created_at'])) ?></td>
                <td>
                  <?php if ($m['movement_type'] === 'cash_in'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Cash In (Float)</span>
                  <?php elseif ($m['movement_type'] === 'cash_out'): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Cash Out (Expense)</span>
                  <?php else: ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle">Safe Drop</span>
                  <?php endif; ?>
                </td>
                <td class="text-end font-monospace fw-bold">
                  <?= $m['movement_type'] === 'cash_in' ? '+' : '-' ?>₱<?= number_format((float)$m['amount'], 2) ?>
                </td>
                <td><?= htmlspecialchars($m['reason']) ?></td>
                <td><small class="text-muted"><?= htmlspecialchars($m['cashier_name']) ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Record Cash Movement -->
<div class="modal fade" id="cashMovementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= BASE_URL ?>/shifts/movement">
        <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
        <input type="hidden" name="shift_id" value="<?= $activeShift['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>Record Cash Movement
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Movement Type <span class="text-danger">*</span></label>
            <select name="movement_type" class="form-select form-select-lg" required>
              <option value="cash_in">Cash In (Add extra change / float)</option>
              <option value="cash_out">Cash Out (Petty cash / supply expense)</option>
              <option value="drop">Safe Drop (Mid-shift cash skim to manager safe)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount (₱) <span class="text-danger">*</span></label>
            <div class="input-group input-group-lg">
              <span class="input-group-text fw-bold">₱</span>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control fw-bold" placeholder="0.00" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Reason / Audit Justification <span class="text-danger">*</span></label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Bought emergency receipt rolls, Safe drop #1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Movement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Close & Audit Shift -->
<div class="modal fade" id="closeShiftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= BASE_URL ?>/shifts/close">
        <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
        <input type="hidden" name="shift_id" value="<?= $activeShift['id'] ?>">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-lock-fill me-2"></i>Close Shift & Cash Audit
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="alert alert-secondary mb-4">
            <div class="d-flex justify-content-between align-items-center">
              <span>System Expected Cash:</span>
              <span class="fs-4 fw-bold font-monospace" id="expectedCashVal" data-val="<?= $summary['expected_cash'] ?>">
                ₱<?= number_format($summary['expected_cash'], 2) ?>
              </span>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold fs-5">Actual Counted Cash in Drawer (₱) <span class="text-danger">*</span></label>
            <div class="input-group input-group-lg">
              <span class="input-group-text fw-bold">₱</span>
              <input type="number" step="0.01" min="0" name="closing_cash" id="closingCashInput" class="form-control form-control-lg fw-bold fs-3 text-primary" placeholder="0.00" required>
            </div>
            <div class="form-text">Perform a physical count of all banknotes and coins in the cash drawer.</div>
          </div>

          <!-- Real-time Variance Indicator -->
          <div class="card border-0 shadow-sm p-3 mb-4" id="varianceCard" style="display: none;">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold" id="varianceLabel">Cash Variance:</span>
              <span class="fs-4 fw-bold font-monospace" id="varianceValue">₱0.00</span>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Closing Notes / Discrepancy Explanation</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Explain any shortage, overage, or handover details to incoming cashier..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger fw-semibold px-4" onclick="return confirm('Are you sure you want to finalize and close this shift? This action cannot be undone.');">
            Finalize & Close Shift
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const closingInput = document.getElementById('closingCashInput');
  const expectedVal = parseFloat(document.getElementById('expectedCashVal').getAttribute('data-val')) || 0;
  const varianceCard = document.getElementById('varianceCard');
  const varianceLabel = document.getElementById('varianceLabel');
  const varianceValue = document.getElementById('varianceValue');

  if (closingInput) {
    closingInput.addEventListener('input', function() {
      const val = parseFloat(this.value);
      if (isNaN(val)) {
        varianceCard.style.display = 'none';
        return;
      }
      varianceCard.style.display = 'block';
      const diff = val - expectedVal;
      
      if (Math.abs(diff) < 0.01) {
        varianceCard.className = 'card border-0 shadow-sm p-3 mb-4 bg-success bg-opacity-10 text-success';
        varianceLabel.textContent = 'Exact Balance:';
        varianceValue.textContent = '₱0.00 (Balanced)';
      } else if (diff > 0) {
        varianceCard.className = 'card border-0 shadow-sm p-3 mb-4 bg-info bg-opacity-10 text-info';
        varianceLabel.textContent = 'Cash Overage (+):';
        varianceValue.textContent = '+₱' + diff.toFixed(2);
      } else {
        varianceCard.className = 'card border-0 shadow-sm p-3 mb-4 bg-danger bg-opacity-10 text-danger';
        varianceLabel.textContent = 'Cash Shortage (-):';
        varianceValue.textContent = '-₱' + Math.abs(diff).toFixed(2);
      }
    });
  }
});
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
