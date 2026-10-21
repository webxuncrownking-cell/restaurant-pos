<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<?php
$selectedVariant = null;
foreach (($product['variants'] ?? []) as $variant) {
    if ($selectedVariantId !== null && (int)$variant['id'] === (int)$selectedVariantId) {
        $selectedVariant = $variant;
        break;
    }
}
$recipeItems = array_values(array_filter($product['recipe_bom'] ?? [], static function (array $item) use ($selectedVariantId): bool {
    if ($selectedVariantId === null) {
        return $item['variant_id'] === null;
    }
    return $item['variant_id'] !== null && (int)$item['variant_id'] === $selectedVariantId;
}));
$displayPrice = (float)$product['base_price'] + ($selectedVariant ? (float)$selectedVariant['additional_price'] : 0.0);
$standardUnits = [
    'g' => ['g' => 'Grams (g)', 'kg' => 'Kilograms (kg)', 'mg' => 'Milligrams (mg)', 'lb' => 'Pounds (lb)', 'oz' => 'Ounces (oz)'],
    'ml' => ['ml' => 'Milliliters (ml)', 'l' => 'Liters (L)', 'cl' => 'Centiliters (cl)', 'gal' => 'Gallons (gal)', 'cup' => 'Cups (cup)', 'tbsp' => 'Tablespoons (tbsp)', 'tsp' => 'Teaspoons (tsp)'],
    'pcs' => ['pcs' => 'Pieces (pcs)', 'pc' => 'Piece (pc)', 'dz' => 'Dozen (dz)', 'tray' => 'Tray (tray)', 'box' => 'Box (box)', 'pack' => 'Pack (pack)']
];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$ingredientsJson = json_encode($ingredients, $jsonFlags | JSON_THROW_ON_ERROR);
$unitConversionsJson = json_encode($unitConversions, $jsonFlags | JSON_THROW_ON_ERROR);
?>

<div class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/menu">Menu</a></li>
      <li class="breadcrumb-item active" aria-current="page">Recipe Bill of Materials</li>
    </ol>
  </nav>

  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Recipe Bill of Materials saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (isset($_GET['created'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Product created. Configure its recipe or serving variants below.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h3 class="fw-bold mb-1"><i class="bi bi-card-checklist text-warning me-2"></i>Recipe BOM: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="text-muted small mb-0">Define ingredient amounts per product or variant. Quantities are normalized and stored in the ingredient's base unit.</p>
    </div>
    <div class="d-flex flex-wrap align-items-stretch gap-2">
      <div class="card bg-light border p-2 px-3 text-center">
        <span class="text-muted small">Selling Price</span>
        <span class="fw-bold text-dark">₱<?= number_format($displayPrice, 2) ?></span>
      </div>
      <div class="card bg-light border p-2 px-3 text-center">
        <span class="text-muted small">Calculated Cost</span>
        <span class="fw-bold text-danger">₱<?= number_format((float)$costInfo['total_cost'], 2) ?></span>
      </div>
      <?php $margin = $displayPrice > 0 ? round((($displayPrice - (float)$costInfo['total_cost']) / $displayPrice) * 100, 1) : 0; ?>
      <div class="card <?= $margin >= 50 ? 'bg-success text-white' : 'bg-warning text-dark' ?> border p-2 px-3 text-center">
        <span class="small opacity-75">Gross Margin</span>
        <span class="fw-bold"><?= number_format($margin, 1) ?>%</span>
      </div>
    </div>
  </div>

  <?php if (($product['product_type'] ?? 'recipe') === 'direct'): ?>
    <div class="alert alert-info">
      This is a direct-stock product. Its default cost is taken from the linked ingredient. A recipe BOM is usually needed only when one sale consumes multiple ingredients.
    </div>
  <?php endif; ?>

  <?php if (!empty($product['variants'])): ?>
    <form method="GET" action="<?= BASE_URL ?>/menu/products/<?= (int)$product['id'] ?>/recipe" class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div class="flex-grow-1">
          <label for="variant_id" class="form-label small fw-semibold">Recipe being edited</label>
          <select id="variant_id" name="variant_id" class="form-select">
            <option value="" <?= $selectedVariantId === null ? 'selected' : '' ?>>Base product recipe</option>
            <?php foreach ($product['variants'] as $variant): ?>
              <option value="<?= (int)$variant['id'] ?>" <?= $selectedVariantId !== null && (int)$selectedVariantId === (int)$variant['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($variant['name'], ENT_QUOTES, 'UTF-8') ?> — <?= ((float)$variant['additional_price'] >= 0 ? '+' : '') ?>₱<?= number_format((float)$variant['additional_price'], 2) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($selectedVariant): ?>
            <div class="form-text">This variant inherits the base recipe ingredients and can add variant-specific ingredients.</div>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-outline-primary">Load Recipe</button>
      </div>
    </form>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0 fw-semibold text-secondary">
              <i class="bi bi-egg-fried text-primary me-2"></i><?= $selectedVariant ? htmlspecialchars($selectedVariant['name'], ENT_QUOTES, 'UTF-8') . ' Ingredients' : 'Base Ingredients' ?>
            </h5>
            <div class="small text-muted mt-1"><?= $selectedVariant ? 'Variant-specific recipe rows only; base recipe is inherited.' : 'Ingredient quantities for the base product.' ?></div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="addIngredientBtn"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
        </div>
        <div class="card-body p-3 p-md-4">
          <form method="POST" action="<?= BASE_URL ?>/menu/products/<?= (int)$product['id'] ?>/recipe" id="bomForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="variant_id" value="<?= $selectedVariantId !== null ? (int)$selectedVariantId : '' ?>">

            <div id="recipeContainer" class="d-flex flex-column gap-2 mb-3">
              <?php $rowIndex = 0; foreach ($recipeItems as $item): ?>
                <div class="recipe-row border rounded-3 p-2 bg-light">
                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1">Ingredient</label>
                      <select name="items[<?= $rowIndex ?>][ingredient_id]" class="form-select form-select-sm ingredient-select" required>
                        <option value="">Select ingredient...</option>
                        <?php foreach ($ingredients as $ing): ?>
                          <option value="<?= (int)$ing['id'] ?>" <?= (int)$item['ingredient_id'] === (int)$ing['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ing['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($ing['base_unit'], ENT_QUOTES, 'UTF-8') ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1">Quantity</label>
                      <input type="number" step="0.0001" min="0.0001" max="999999999" name="items[<?= $rowIndex ?>][quantity]" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$item['quantity_required'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1">Unit</label>
                      <select name="items[<?= $rowIndex ?>][unit]" class="form-select form-select-sm unit-select" required>
                        <?php
                          $ingId = (int)$item['ingredient_id'];
                          $baseUnit = (string)$item['base_ingredient_unit'];
                          foreach (($standardUnits[$baseUnit] ?? [$baseUnit => $baseUnit]) as $unitCode => $unitLabel):
                        ?>
                          <option value="<?= htmlspecialchars($unitCode, ENT_QUOTES, 'UTF-8') ?>" <?= $unitCode === $baseUnit ? 'selected' : '' ?>><?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($unitConversions as $conversion): if ((int)$conversion['ingredient_id'] !== $ingId) continue; ?>
                          <option value="<?= htmlspecialchars($conversion['unit_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($conversion['unit_name'], ENT_QUOTES, 'UTF-8') ?> (×<?= number_format((float)$conversion['multiplier_to_base'], 4) ?> <?= htmlspecialchars($baseUnit, ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-1 text-end">
                      <button type="button" class="btn btn-outline-danger btn-sm remove-row" aria-label="Remove ingredient row"><i class="bi bi-trash"></i><span class="d-md-none ms-1">Remove</span></button>
                    </div>
                  </div>
                </div>
              <?php $rowIndex++; endforeach; ?>
            </div>

            <?php if (empty($recipeItems)): ?>
              <div id="noRecipeNotice" class="alert alert-light border text-center py-3 text-muted small">
                No ingredients are configured in this recipe section yet. Use “Add Ingredient” to start.
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/menu" class="btn btn-light px-4">Back to Menu</a>
              <button type="submit" class="btn btn-pos-primary px-4 fw-semibold"><i class="bi bi-check2-circle me-1"></i> Save Recipe</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h6 class="mb-0 fw-semibold text-secondary"><i class="bi bi-pie-chart text-success me-2"></i>Saved Cost Breakdown</h6>
        </div>
        <div class="card-body p-3">
          <?php if (empty($costInfo['breakdown'])): ?>
            <p class="text-muted small text-center my-3">Save ingredients and reload the recipe to update the cost breakdown.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush small">
              <?php foreach ($costInfo['breakdown'] as $b): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2 px-0">
                  <div>
                    <div class="fw-medium text-dark"><?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted"><?= number_format((float)$b['quantity'], 4) ?> <?= htmlspecialchars($b['unit'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <span class="fw-semibold text-danger text-nowrap">₱<?= number_format((float)$b['cost'], 2) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2 fw-bold">
              <span>Total Food Cost:</span>
              <span class="text-danger fs-5">₱<?= number_format((float)$costInfo['total_cost'], 2) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <p class="form-text mt-2">The displayed cost is based on saved ingredients and current ingredient costs, not unsaved changes in the form.</p>
    </div>
  </div>
</div>

<script>
(function () {
  const ingredients = <?= $ingredientsJson ?>;
  const unitConversions = <?= $unitConversionsJson ?>;
  const standardUnits = <?= json_encode($standardUnits, $jsonFlags | JSON_THROW_ON_ERROR) ?>;
  const container = document.getElementById('recipeContainer');
  const notice = document.getElementById('noRecipeNotice');
  let rowIndex = <?= (int)$rowIndex ?>;

  function appendOption(select, value, label, selected) {
    const option = document.createElement('option');
    option.value = String(value);
    option.textContent = String(label);
    option.selected = Boolean(selected);
    select.appendChild(option);
  }

  function populateUnits(select, ingredientId, selectedUnit) {
    const ingredient = ingredients.find(function (item) { return Number(item.id) === Number(ingredientId); });
    select.replaceChildren();
    if (!ingredient) {
      appendOption(select, '', 'Select ingredient first', true);
      select.disabled = true;
      return;
    }
    select.disabled = false;
    const baseUnit = String(ingredient.base_unit);
    const standard = standardUnits[baseUnit] || {};
    const added = new Set();
    Object.keys(standard).forEach(function (code) {
      appendOption(select, code, standard[code], code === selectedUnit);
      added.add(code.toLowerCase());
    });
    unitConversions.filter(function (item) {
      return Number(item.ingredient_id) === Number(ingredientId);
    }).forEach(function (item) {
      const code = String(item.unit_name);
      if (added.has(code.toLowerCase())) return;
      const factor = Number(item.multiplier_to_base);
      appendOption(select, code, code + ' (×' + factor.toLocaleString(undefined, {maximumFractionDigits: 4}) + ' ' + baseUnit + ')', code === selectedUnit);
      added.add(code.toLowerCase());
    });
    if (!added.has(String(selectedUnit).toLowerCase())) {
      appendOption(select, selectedUnit, selectedUnit, true);
    }
  }

  function createIngredientRow(initial) {
    const idx = rowIndex++;
    const row = document.createElement('div');
    row.className = 'recipe-row border rounded-3 p-2 bg-light';
    const grid = document.createElement('div');
    grid.className = 'row g-2 align-items-end';

    const ingredientCol = document.createElement('div');
    ingredientCol.className = 'col-12 col-md-5';
    const ingredientLabel = document.createElement('label');
    ingredientLabel.className = 'form-label small mb-1';
    ingredientLabel.textContent = 'Ingredient';
    const ingredientSelect = document.createElement('select');
    ingredientSelect.name = 'items[' + idx + '][ingredient_id]';
    ingredientSelect.className = 'form-select form-select-sm ingredient-select';
    ingredientSelect.required = true;
    appendOption(ingredientSelect, '', 'Select ingredient...', !initial);
    ingredients.forEach(function (ing) {
      appendOption(ingredientSelect, ing.id, ing.name + ' (' + ing.base_unit + ')', initial && Number(initial.ingredient_id) === Number(ing.id));
    });
    ingredientCol.append(ingredientLabel, ingredientSelect);

    const quantityCol = document.createElement('div');
    quantityCol.className = 'col-6 col-md-3';
    const quantityLabel = document.createElement('label');
    quantityLabel.className = 'form-label small mb-1';
    quantityLabel.textContent = 'Quantity';
    const quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.name = 'items[' + idx + '][quantity]';
    quantity.className = 'form-control form-control-sm';
    quantity.step = '0.0001';
    quantity.min = '0.0001';
    quantity.max = '999999999';
    quantity.required = true;
    quantity.value = initial && initial.quantity ? String(initial.quantity) : '';
    quantityCol.append(quantityLabel, quantity);

    const unitCol = document.createElement('div');
    unitCol.className = 'col-6 col-md-3';
    const unitLabel = document.createElement('label');
    unitLabel.className = 'form-label small mb-1';
    unitLabel.textContent = 'Unit';
    const unitSelect = document.createElement('select');
    unitSelect.name = 'items[' + idx + '][unit]';
    unitSelect.className = 'form-select form-select-sm unit-select';
    unitSelect.required = true;
    unitCol.append(unitLabel, unitSelect);

    const removeCol = document.createElement('div');
    removeCol.className = 'col-12 col-md-1 text-end';
    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'btn btn-outline-danger btn-sm remove-row';
    removeButton.setAttribute('aria-label', 'Remove ingredient row');
    removeButton.textContent = 'Remove';
    removeButton.addEventListener('click', function () { row.remove(); });
    removeCol.appendChild(removeButton);

    grid.append(ingredientCol, quantityCol, unitCol, removeCol);
    row.appendChild(grid);
    ingredientSelect.addEventListener('change', function () {
      populateUnits(unitSelect, ingredientSelect.value, '');
    });
    populateUnits(unitSelect, ingredientSelect.value, initial ? String(initial.unit || '') : '');
    container.appendChild(row);
    if (notice) notice.remove();
  }

  document.querySelectorAll('.recipe-row').forEach(function (row) {
    const ingredientSelect = row.querySelector('.ingredient-select');
    const unitSelect = row.querySelector('.unit-select');
    if (ingredientSelect && unitSelect) {
      ingredientSelect.addEventListener('change', function () {
        populateUnits(unitSelect, ingredientSelect.value, '');
      });
    }
    const remove = row.querySelector('.remove-row');
    if (remove) remove.addEventListener('click', function () { row.remove(); });
  });

  document.getElementById('addIngredientBtn').addEventListener('click', function () {
    createIngredientRow(null);
  });
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
