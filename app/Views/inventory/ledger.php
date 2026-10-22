<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<?php
$movementLabels = [
    'purchase_receipt' => 'Stock In / Purchase',
    'order_sale' => 'POS Sale Deduction',
    'order_cancellation' => 'Order Cancellation Reversal',
    'order_refund' => 'Refund Reversal',
    'manual_adjustment' => 'Manual Adjustment',
    'spoilage_waste' => 'Waste / Spoilage'
];
$movementBadges = [
    'purchase_receipt' => 'bg-success',
    'order_sale' => 'bg-primary',
    'order_cancellation' => 'bg-info text-dark',
    'order_refund' => 'bg-info text-dark',
    'manual_adjustment' => 'bg-warning text-dark',
    'spoilage_waste' => 'bg-danger'
];
?>

<div class="container py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-clock-history text-warning me-2"></i>Inventory Movements Ledger</h2>
      <p class="text-muted small mb-0">Audit trail of stock receipts, sales, cancellations, refunds, adjustments, and wastage.</p>
    </div>
    <a href="<?= BASE_URL ?>/inventory" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Inventory</a>
  </div>

  <div class="card border-0 shadow-sm p-3 mb-4 rounded-3">
    <form method="GET" action="<?= BASE_URL ?>/inventory/ledger" class="row g-2 align-items-end">
      <div class="col-12 col-md-5">
        <label for="ingredient_id" class="form-label form-label-sm small fw-semibold">Ingredient</label>
        <select id="ingredient_id" name="ingredient_id" class="form-select form-select-sm">
          <option value="">All Ingredients</option>
          <?php foreach ($ingredients as $ing): ?>
            <option value="<?= (int)$ing['id'] ?>" <?= $selectedIng === (int)$ing['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ing['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label for="type" class="form-label form-label-sm small fw-semibold">Movement Type</label>
        <select id="type" name="type" class="form-select form-select-sm">
          <option value="">All Movement Types</option>
          <?php foreach ($movementLabels as $type => $label): ?>
            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-dark btn-sm w-100">Filter</button>
        <a href="<?= BASE_URL ?>/inventory/ledger" class="btn btn-light btn-sm border">Reset</a>
      </div>
    </form>
  </div>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center gap-2">
      <h5 class="mb-0 fw-semibold text-secondary"><i class="bi bi-list-check text-primary me-2"></i>Movement Records</h5>
      <span class="badge bg-secondary"><?= count($movements) ?> Records</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-secondary">
          <tr>
            <th class="ps-4">Date & Time</th>
            <th>Ingredient</th>
            <th>Movement</th>
            <th class="text-end">Change</th>
            <th class="text-end">Stock Before</th>
            <th class="text-end">Stock After</th>
            <th>Reference / Notes</th>
            <th class="pe-4">Recorded By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements)): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No inventory movements match these filters.</td></tr>
          <?php else: ?>
            <?php foreach ($movements as $m):
              $quantityChange = (float)$m['quantity_change'];
              $isAddition = $quantityChange > 0;
              $movementType = (string)$m['reference_type'];
              $badgeClass = $movementBadges[$movementType] ?? 'bg-secondary';
            ?>
              <tr>
                <td class="ps-4 small text-muted text-nowrap"><?= htmlspecialchars($m['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="fw-semibold text-dark"><?= htmlspecialchars($m['ingredient_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="small text-muted">Current cost: ₱<?= number_format((float)$m['current_cost_per_base_unit'], 4) ?>/<?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td><span class="badge <?= $badgeClass ?> small"><?= htmlspecialchars($movementLabels[$movementType] ?? $movementType, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="text-end fw-bold <?= $isAddition ? 'text-success' : 'text-danger' ?>">
                  <?= $isAddition ? '+' : '' ?><?= number_format($quantityChange, 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td class="text-end small"><?= number_format((float)$m['stock_before'], 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end small fw-semibold"><?= number_format((float)$m['stock_after'], 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="small text-muted" style="max-width:280px">
                  <?php if (!empty($m['reference_id'])): ?>
                    <div>Reference #<?= (int)$m['reference_id'] ?></div>
                  <?php endif; ?>
                  <?= htmlspecialchars($m['notes'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td class="pe-4 small text-muted"><?= htmlspecialchars($m['logged_by'] ?? 'Unknown user', ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-2 mb-0">Quantities use the ingredient's base unit. Unit cost shown is the ingredient's current cost, not a historical cost snapshot for each movement.</p>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
