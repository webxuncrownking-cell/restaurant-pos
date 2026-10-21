<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<?php
$unitGroups = [
  'g' => ['g' => 'Grams (g)', 'kg' => 'Kilograms (kg)', 'mg' => 'Milligrams (mg)', 'lb' => 'Pounds (lb)', 'oz' => 'Ounces (oz)'],
  'ml' => ['ml' => 'Milliliters (ml)', 'l' => 'Liters (L)', 'cl' => 'Centiliters (cl)', 'gal' => 'Gallons (gal)', 'cup' => 'Cups (cup)', 'tbsp' => 'Tablespoons (tbsp)', 'tsp' => 'Teaspoons (tsp)'],
  'pcs' => ['pcs' => 'Pieces (pcs)', 'pc' => 'Piece (pc)', 'dz' => 'Dozen (dz)', 'tray' => 'Tray (tray)', 'box' => 'Box (box)', 'pack' => 'Pack (pack)']
];
$unitGroupsJson = json_encode($unitGroups, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
$ingredientsJson = json_encode($ingredients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
$unitConversionsJson = json_encode($unitConversions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/inventory">Inventory</a></li>
          <li class="breadcrumb-item active" aria-current="page">Adjust Stock</li>
        </ol>
      </nav>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-sliders text-warning me-2"></i>Manual Stock Adjustment</h5>
          <p class="text-muted small mb-0 mt-1">Record a correction with a reason. Each adjustment updates stock and creates an audit-ledger entry in one transaction.</p>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/inventory/adjustment" id="adjustmentForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
              <label for="ingredient_id" class="form-label fw-semibold small">Ingredient <span class="text-danger">*</span></label>
              <select id="ingredient_id" name="ingredient_id" class="form-select" required>
                <option value="">Select ingredient...</option>
                <?php foreach ($ingredients as $ingredient): ?>
                  <option value="<?= (int)$ingredient['id'] ?>">
                    <?= htmlspecialchars($ingredient['name'], ENT_QUOTES, 'UTF-8') ?> — current stock <?= number_format((float)$ingredient['current_stock'], 4) ?> <?= htmlspecialchars($ingredient['base_unit'], ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">Adjustment Direction <span class="text-danger">*</span></label>
              <div class="row g-2">
                <div class="col-12 col-sm-6">
                  <label class="border rounded-3 p-3 d-flex gap-2 align-items-start w-100 h-100">
                    <input type="radio" name="direction" value="increase" required class="form-check-input mt-1">
                    <span><strong class="text-success d-block">Increase stock</strong><small class="text-muted">For correcting an under-count or recording found stock.</small></span>
                  </label>
                </div>
                <div class="col-12 col-sm-6">
                  <label class="border rounded-3 p-3 d-flex gap-2 align-items-start w-100 h-100">
                    <input type="radio" name="direction" value="decrease" required class="form-check-input mt-1">
                    <span><strong class="text-danger d-block">Decrease stock</strong><small class="text-muted">For damage, an over-count correction, or missing stock.</small></span>
                  </label>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label for="quantity" class="form-label fw-semibold small">Quantity <span class="text-danger">*</span></label>
                <input id="quantity" name="quantity" type="number" step="0.0001" min="0.0001" max="999999999" class="form-control" required placeholder="Enter positive amount">
              </div>
              <div class="col-12 col-md-6">
                <label for="unit" class="form-label fw-semibold small">Unit <span class="text-danger">*</span></label>
                <select id="unit" name="unit" class="form-select" required disabled>
                  <option value="">Select ingredient first...</option>
                </select>
              </div>
            </div>

            <div class="mb-4">
              <label for="reason" class="form-label fw-semibold small">Reason / Notes <span class="text-danger">*</span></label>
              <textarea id="reason" name="reason" class="form-control" rows="3" maxlength="255" required placeholder="e.g. Physical count correction after closing inventory"></textarea>
              <div class="form-text">The reason will be saved in the inventory movement ledger.</div>
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/inventory" class="btn btn-light px-4">Cancel</a>
              <button type="submit" class="btn btn-warning px-4 fw-semibold"><i class="bi bi-check2-circle me-1"></i> Record Adjustment</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const ingredients = <?= $ingredientsJson ?>;
  const conversions = <?= $unitConversionsJson ?>;
  const unitGroups = <?= $unitGroupsJson ?>;
  const ingredientSelect = document.getElementById('ingredient_id');
  const unitSelect = document.getElementById('unit');

  function addOption(value, label, selected) {
    const option = document.createElement('option');
    option.value = String(value);
    option.textContent = String(label);
    option.selected = Boolean(selected);
    unitSelect.appendChild(option);
  }

  function updateUnits() {
    const ingredient = ingredients.find(function (item) {
      return Number(item.id) === Number(ingredientSelect.value);
    });
    unitSelect.replaceChildren();
    if (!ingredient) {
      addOption('', 'Select ingredient first...', true);
      unitSelect.disabled = true;
      return;
    }

    unitSelect.disabled = false;
    const baseUnit = String(ingredient.base_unit);
    const allowed = unitGroups[baseUnit] || {};
    const seen = new Set();
    Object.keys(allowed).forEach(function (code) {
      addOption(code, allowed[code], code === baseUnit);
      seen.add(code.toLowerCase());
    });
    conversions.filter(function (item) {
      return Number(item.ingredient_id) === Number(ingredient.id);
    }).forEach(function (item) {
      const code = String(item.unit_name);
      if (seen.has(code.toLowerCase())) return;
      const factor = Number(item.multiplier_to_base);
      addOption(code, code + ' (×' + factor.toLocaleString(undefined, {maximumFractionDigits: 4}) + ' ' + baseUnit + ')', false);
      seen.add(code.toLowerCase());
    });
  }

  ingredientSelect.addEventListener('change', updateUnits);
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
