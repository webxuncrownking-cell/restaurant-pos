<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/inventory">Inventory</a></li>
          <li class="breadcrumb-item active" aria-current="page">Receive Goods (Stock In)</li>
        </ol>
      </nav>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-in-down text-success me-2"></i>Receive Goods / Stock In</h5>
        </div>
        <div class="card-body p-4">
          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <form method="POST" action="<?= BASE_URL ?>/inventory/stock-in" id="stockInForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Mode Selector: Packaged Goods vs Direct Base Unit -->
            <div class="mb-4">
              <label class="form-label fw-semibold small">Stock In Method</label>
              <div class="d-flex gap-3">
                <div class="form-check border p-3 rounded flex-grow-1 ps-4">
                  <input class="form-check-input" type="radio" name="mode" id="modePackage" value="package" checked onchange="toggleMode()">
                  <label class="form-check-label fw-semibold" for="modePackage">
                    <i class="bi bi-box-seam text-primary me-1"></i> By Packaged Case / Box / Sack
                    <div class="text-muted small fw-normal">Auto-converts into base units (e.g. 5 boxes = 50 pcs)</div>
                  </label>
                </div>
                <div class="form-check border p-3 rounded flex-grow-1 ps-4">
                  <input class="form-check-input" type="radio" name="mode" id="modeBase" value="base" onchange="toggleMode()">
                  <label class="form-check-label fw-semibold" for="modeBase">
                    <i class="bi bi-scale text-success me-1"></i> By Base Quantity
                    <div class="text-muted small fw-normal">Direct entry in grams, liters, or pieces</div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Packaged Goods Section -->
            <div id="packageSection" class="p-3 bg-light rounded-3 mb-4">
              <h6 class="fw-semibold text-secondary mb-3"><i class="bi bi-box-seam me-1"></i>Packaged Delivery Details</h6>
              <div class="row g-3 mb-3">
                <div class="col-12 col-md-7">
                  <label for="package_id" class="form-label small fw-semibold">Select Packaging Configuration</label>
                  <select class="form-select" id="package_id" name="package_id">
                    <option value="">Choose Package...</option>
                    <?php foreach ($packages as $pkg): ?>
                      <option value="<?= $pkg['id'] ?>">
                        <?= htmlspecialchars($pkg['ingredient_name']) ?> &mdash; <?= htmlspecialchars($pkg['unit_name']) ?> (<?= number_format((float)$pkg['multiplier_to_base'], 2) ?> <?= htmlspecialchars($pkg['base_unit']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-5">
                  <label for="package_count" class="form-label small fw-semibold">Number of Packages / Units</label>
                  <input type="number" step="0.01" min="0.01" class="form-control" id="package_count" name="package_count" placeholder="e.g. 5">
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label for="total_cost" class="form-label small fw-semibold">Total Delivery Cost (PHP) <span class="text-muted">(Optional)</span></label>
                  <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" min="0" class="form-control" id="total_cost" name="total_cost" placeholder="0.00">
                  </div>
                </div>
              </div>
            </div>

            <!-- Direct Base Unit Section (Hidden by default) -->
            <div id="baseSection" class="p-3 bg-light rounded-3 mb-4 d-none">
              <h6 class="fw-semibold text-secondary mb-3"><i class="bi bi-scale me-1"></i>Direct Quantity Stock In</h6>
              <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                  <label for="ingredient_id" class="form-label small fw-semibold">Ingredient</label>
                  <select class="form-select" id="ingredient_id" name="ingredient_id">
                    <option value="">Choose Ingredient...</option>
                    <?php foreach ($ingredients as $ing): ?>
                      <option value="<?= $ing['id'] ?>">
                        <?= htmlspecialchars($ing['name']) ?> (Base Unit: <?= htmlspecialchars($ing['base_unit']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label for="quantity" class="form-label small fw-semibold">Quantity</label>
                  <input type="number" step="0.0001" min="0.0001" class="form-control" id="quantity" name="quantity" placeholder="e.g. 5000">
                </div>
                <div class="col-12 col-md-3">
                  <label for="unit" class="form-label small fw-semibold">Unit</label>
                  <select class="form-select" id="unit" name="unit">
                    <option value="g">Grams (g)</option>
                    <option value="kg">Kilograms (kg)</option>
                    <option value="ml">Milliliters (ml)</option>
                    <option value="l">Liters (L)</option>
                    <option value="pcs">Pieces (pcs)</option>
                  </select>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label for="cost_per_unit" class="form-label small fw-semibold">Cost Per Unit (PHP) <span class="text-muted">(Optional)</span></label>
                  <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.0001" min="0" class="form-control" id="cost_per_unit" name="cost_per_unit" placeholder="0.00">
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="mb-4">
              <label for="notes" class="form-label small fw-semibold">Supplier / Invoice Notes</label>
              <input type="text" class="form-control" id="notes" name="notes" placeholder="e.g. PO #1042 from Metro Fresh Market">
            </div>

            <!-- Action buttons -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/inventory" class="btn btn-light px-4">Cancel</a>
              <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Confirm & Update Stock
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleMode() {
  const isPackage = document.getElementById('modePackage').checked;
  const packageSection = document.getElementById('packageSection');
  const baseSection = document.getElementById('baseSection');

  if (isPackage) {
    packageSection.classList.remove('d-none');
    baseSection.classList.add('d-none');
  } else {
    packageSection.classList.add('d-none');
    baseSection.classList.remove('d-none');
  }
}
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
