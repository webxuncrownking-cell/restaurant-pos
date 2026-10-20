<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-safe text-primary me-2"></i>Cashier Shifts & Cash Drawer
      </h1>
      <p class="text-muted small mb-0">Track shift sessions, starting cash floats, cash in/out movements, and closing balance audits.</p>
    </div>
    <div>
      <?php if ($activeShift): ?>
        <a href="<?= BASE_URL ?>/shifts/active" class="btn btn-success">
          <i class="bi bi-clock-history me-1"></i> View Active Shift (#<?= (int)$activeShift['id'] ?>)
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/shifts/open" class="btn btn-primary">
          <i class="bi bi-box-arrow-in-right me-1"></i> Open New Shift / Float
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Active Shift Banner if Open -->
  <?php if ($activeShift): ?>
    <div class="card border-success border-2 shadow-sm mb-4">
      <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <span class="badge bg-success px-3 py-1 mb-2 text-uppercase fw-semibold">Shift In Progress</span>
          <h4 class="fw-bold mb-1">Shift #<?= (int)$activeShift['id'] ?> &bull; <?= htmlspecialchars($activeShift['device_name']) ?></h4>
          <div class="text-muted small">
            Cashier: <strong><?= htmlspecialchars($activeShift['cashier_name']) ?></strong> &bull; 
            Opened: <strong><?= date('M d, Y h:i A', strtotime($activeShift['opened_at'])) ?></strong> &bull; 
            Opening Float: <strong>₱<?= number_format((float)$activeShift['opening_cash'], 2) ?></strong>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= BASE_URL ?>/shifts/active" class="btn btn-success px-4">
            <i class="bi bi-calculator me-1"></i> Manage Cash Drawer
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Shift History Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 border-0">
      <h5 class="fw-bold mb-0">Shift Sessions History</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Shift ID</th>
            <th>Terminal / Device</th>
            <th>Cashier</th>
            <th>Opened / Closed</th>
            <th class="text-end">Opening Float</th>
            <th class="text-end">Expected Cash</th>
            <th class="text-end">Actual Counted</th>
            <th class="text-end">Variance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentShifts)): ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                No shift history recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentShifts as $s): 
              $diff = (float)($s['cash_difference'] ?? 0);
            ?>
              <tr>
                <td>
                  <span class="fw-bold">#<?= (int)$s['id'] ?></span>
                </td>
                <td>
                  <div><?= htmlspecialchars($s['device_name']) ?></div>
                </td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($s['cashier_name']) ?></div>
                </td>
                <td>
                  <div class="small text-muted"><?= date('M d, H:i', strtotime($s['opened_at'])) ?></div>
                  <div class="small text-muted"><?= $s['closed_at'] ? date('M d, H:i', strtotime($s['closed_at'])) : '<em>Still Open</em>' ?></div>
                </td>
                <td class="text-end font-monospace">₱<?= number_format((float)$s['opening_cash'], 2) ?></td>
                <td class="text-end font-monospace">₱<?= number_format((float)$s['expected_cash'], 2) ?></td>
                <td class="text-end font-monospace">
                  <?= $s['closing_cash'] !== null ? '₱' . number_format((float)$s['closing_cash'], 2) : '<span class="text-muted">-</span>' ?>
                </td>
                <td class="text-end font-monospace">
                  <?php if ($s['status'] === 'closed'): ?>
                    <?php if (abs($diff) < 0.01): ?>
                      <span class="badge bg-success-subtle text-success">₱0.00</span>
                    <?php elseif ($diff > 0): ?>
                      <span class="badge bg-info-subtle text-info">+₱<?= number_format($diff, 2) ?></span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger">-₱<?= number_format(abs($diff), 2) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($s['status'] === 'open'): ?>
                    <span class="badge bg-success">OPEN</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">CLOSED</span>
                  <?php endif; ?>
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
