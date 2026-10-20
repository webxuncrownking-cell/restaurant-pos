<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-clock-history text-warning me-2"></i>Inventory Movements Ledger</h2>
      <p class="text-muted small mb-0">Immutable double-entry audit trail tracking every stock-in, order deduction, wastage, and manual adjustment.</p>
    </div>
    <div>
      <a href="<?= BASE_URL ?>/inventory" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
      </a>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="card border-0 shadow-sm p-3 mb-4 rounded-3">
    <form method="GET" action="<?= BASE_URL ?>/inventory/ledger" class="row g-2 align-items-center">
      <div class="col-12 col-md-5">
        <select name="ingredient_id" class="form-select form-select-sm">
          <option value="">All Ingredients...</option>
          <?php foreach ($ingredients as $ing): ?>
            <option value="<?= $ing['id'] ?>" <?= $selectedIng === (int)$ing['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ing['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <select name="type" class="form-select form-select-sm">
          <option value="">All Movement Types...</option>
          <option value="stock_in" <?= $selectedType === 'stock_in' ? 'selected' : '' ?>>Stock In (Receipts)</option>
          <option value="order_deduction" <?= $selectedType === 'order_deduction' ? 'selected' : '' ?>>Order Deductions (POS Sales)</option>
          <option value="waste" <?= $selectedType === 'waste' ? 'selected' : '' ?>>Waste & Spoilage</option>
          <option value="adjustment" <?= $selectedType === 'adjustment' ? 'selected' : '' ?>>Manual Adjustments</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-dark btn-sm w-100">Filter</button>
        <a href="<?= BASE_URL ?>/inventory/ledger" class="btn btn-light btn-sm border">Reset</a>
      </div>
    </form>
  </div>

  <!-- Ledger Table -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-semibold text-secondary">
        <i class="bi bi-list-check text-primary me-2"></i>Movement Records
      </h5>
      <span class="badge bg-secondary"><?= count($movements) ?> Records</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-secondary">
          <tr>
            <th class="ps-4">Date & Time</th>
            <th>Ingredient</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Unit Cost</th>
            <th>Reference</th>
            <th>Notes</th>
            <th class="pe-4">Recorded By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No inventory movements recorded yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($movements as $m): 
              $isAddition = in_array($m['movement_type'], ['stock_in', 'adjustment']);
            ?>
              <tr>
                <td class="ps-4 small text-muted text-nowrap">
                  <?= htmlspecialchars($m['created_at']) ?>
                </td>
                <td class="fw-semibold text-dark">
                  <?= htmlspecialchars($m['ingredient_name']) ?>
                </td>
                <td>
                  <?php
                    $badgeClass = match ($m['movement_type']) {
                      'stock_in'        => 'bg-success',
                      'order_deduction' => 'bg-primary',
                      'waste'           => 'bg-danger',
                      'adjustment'      => 'bg-warning text-dark',
                      default           => 'bg-secondary'
                    };
                  ?>
                  <span class="badge <?= $badgeClass ?> small">
                    <?= htmlspecialchars(str_replace('_', ' ', strtoupper($m['movement_type']))) ?>
                  </span>
                </td>
                <td class="fw-bold <?= $isAddition ? 'text-success' : 'text-danger' ?>">
                  <?= $isAddition ? '+' : '-' ?><?= number_format((float)$m['quantity'], 2) ?> <?= htmlspecialchars($m['unit']) ?>
                </td>
                <td class="small">
                  <?= $m['cost_per_unit'] !== null ? '₱' . number_format((float)$m['cost_per_unit'], 4) : '&mdash;' ?>
                </td>
                <td class="small text-muted">
                  <?= htmlspecialchars($m['reference_type'] ?? '&mdash;') ?>
                  <?php if (!empty($m['reference_id'])): ?>
                    #<?= (int)$m['reference_id'] ?>
                  <?php endif; ?>
                </td>
                <td class="small text-muted" style="max-width: 250px;">
                  <?= htmlspecialchars($m['notes'] ?? '&mdash;') ?>
                </td>
                <td class="pe-4 small text-muted">
                  <?= htmlspecialchars($m['logged_by'] ?? 'System') ?>
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
