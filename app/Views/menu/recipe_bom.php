<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/menu">Menu</a></li>
      <li class="breadcrumb-item active" aria-current="page">Recipe Bill of Materials: <?= htmlspecialchars($product['name']) ?></li>
    </ol>
  </nav>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h3 class="fw-bold mb-1">
        <i class="bi bi-card-checklist text-warning me-2"></i>Recipe BOM: <?= htmlspecialchars($product['name']) ?>
      </h3>
      <p class="text-muted small mb-0">
        Define exact ingredients, packaging, and quantities deducted upon ordering. Calculations automatically factor unit conversions and live inventory costs.
      </p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="card bg-light border p-2 px-3 text-center">
        <span class="text-muted small">Selling Price</span>
        <span class="fw-bold text-dark">₱<?= number_format((float)$product['base_price'], 2) ?></span>
      </div>
      <div class="card bg-light border p-2 px-3 text-center">
        <span class="text-muted small">Calculated Cost</span>
        <span class="fw-bold text-danger">₱<?= number_format((float)$costInfo['total_cost'], 2) ?></span>
      </div>
      <?php 
        $basePrice = (float)$product['base_price'];
        $margin = $basePrice > 0 ? round((($basePrice - (float)$costInfo['total_cost']) / $basePrice) * 100, 1) : 0;
      ?>
      <div class="card <?= $margin >= 50 ? 'bg-success text-white' : 'bg-warning text-dark' ?> border p-2 px-3 text-center">
        <span class="small opacity-75">Gross Margin</span>
        <span class="fw-bold"><?= $margin ?>%</span>
      </div>
    </div>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-2"></i>Recipe Bill of Materials successfully updated!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Recipe Editor Column -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
          <h5 class="mb-0 fw-semibold text-secondary">
            <i class="bi bi-egg-fried text-primary me-2"></i>Base Ingredients (Per Order)
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="addIngredientRow()">
            <i class="bi bi-plus-lg"></i> Add Ingredient
          </button>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/menu/products/<?= $product['id'] ?>/recipe" id="bomForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div id="recipeContainer" class="d-flex flex-column gap-2 mb-3">
              <!-- Render existing base recipe rows -->
              <?php 
                $rowIndex = 0;
                foreach ($product['recipe_bom'] as $item): 
                  if ($item['variant_id'] !== null) continue;
              ?>
                <div class="row g-2 align-items-center bg-light p-2 rounded recipe-row">
                  <div class="col-5">
                    <select name="items[<?= $rowIndex ?>][ingredient_id]" class="form-select form-select-sm" required>
                      <option value="">Select Ingredient...</option>
                      <?php foreach ($ingredients as $ing): ?>
                        <option value="<?= $ing['id'] ?>" <?= (int)$item['ingredient_id'] === (int)$ing['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($ing['name']) ?> (Base: <?= htmlspecialchars($ing['unit']) ?> - ₱<?= number_format((float)$ing['cost_per_unit'], 4) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-3">
                    <input type="number" step="0.0001" min="0.0001" name="items[<?= $rowIndex ?>][quantity]" class="form-control form-control-sm" placeholder="Quantity" value="<?= (float)$item['quantity'] ?>" required>
                  </div>
                  <div class="col-3">
                    <select name="items[<?= $rowIndex ?>][unit]" class="form-select form-select-sm" required>
                      <option value="g" <?= $item['unit'] === 'g' ? 'selected' : '' ?>>Grams (g)</option>
                      <option value="kg" <?= $item['unit'] === 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option>
                      <option value="ml" <?= $item['unit'] === 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
                      <option value="l" <?= $item['unit'] === 'l' ? 'selected' : '' ?>>Liters (L)</option>
                      <option value="pcs" <?= $item['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                      <option value="cup" <?= $item['unit'] === 'cup' ? 'selected' : '' ?>>Cups (cup)</option>
                      <option value="tbsp" <?= $item['unit'] === 'tbsp' ? 'selected' : '' ?>>Tablespoons (tbsp)</option>
                      <option value="tsp" <?= $item['unit'] === 'tsp' ? 'selected' : '' ?>>Teaspoons (tsp)</option>
                    </select>
                  </div>
                  <div class="col-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.recipe-row').remove()"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
              <?php 
                $rowIndex++;
                endforeach; 
              ?>
            </div>

            <?php if (empty($product['recipe_bom'])): ?>
              <div id="noRecipeNotice" class="alert alert-light border text-center py-3 text-muted small">
                No ingredients linked yet. Click "Add Ingredient" above to build the Bill of Materials.
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/menu" class="btn btn-light px-4">Back to Menu</a>
              <button type="submit" class="btn btn-pos-primary px-4 fw-semibold">
                <i class="bi bi-check2-circle me-1"></i> Save Bill of Materials
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Live Cost Breakdown Column -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 80px;">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h6 class="mb-0 fw-semibold text-secondary"><i class="bi bi-pie-chart text-success me-2"></i>Cost Breakdown</h6>
        </div>
        <div class="card-body p-3">
          <?php if (empty($costInfo['breakdown'])): ?>
            <p class="text-muted small text-center my-3">Add ingredients to see real-time cost breakdown.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush small">
              <?php foreach ($costInfo['breakdown'] as $b): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div>
                    <div class="fw-medium text-dark"><?= htmlspecialchars($b['name']) ?></div>
                    <div class="text-muted"><?= $b['quantity'] ?> <?= $b['unit'] ?> (<?= $b['normalized'] ?>)</div>
                  </div>
                  <span class="fw-semibold text-danger">₱<?= number_format((float)$b['cost'], 2) ?></span>
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
    </div>
  </div>
</div>

<script>
let recipeRowCount = <?= $rowIndex ?>;
const availableIngredients = <?= json_encode($ingredients) ?>;

function addIngredientRow() {
  const notice = document.getElementById('noRecipeNotice');
  if (notice) notice.remove();

  const container = document.getElementById('recipeContainer');
  const div = document.createElement('div');
  div.className = 'row g-2 align-items-center bg-light p-2 rounded recipe-row';
  
  let options = '<option value="">Select Ingredient...</option>';
  availableIngredients.forEach(ing => {
    options += `<option value="${ing.id}">${ing.name} (Base: ${ing.unit} - ₱${parseFloat(ing.cost_per_unit).toFixed(4)})</option>`;
  });

  div.innerHTML = `
    <div class="col-5">
      <select name="items[${recipeRowCount}][ingredient_id]" class="form-select form-select-sm" required>
        ${options}
      </select>
    </div>
    <div class="col-3">
      <input type="number" step="0.0001" min="0.0001" name="items[${recipeRowCount}][quantity]" class="form-control form-control-sm" placeholder="Quantity" required>
    </div>
    <div class="col-3">
      <select name="items[${recipeRowCount}][unit]" class="form-select form-select-sm" required>
        <option value="g">Grams (g)</option>
        <option value="kg">Kilograms (kg)</option>
        <option value="ml">Milliliters (ml)</option>
        <option value="l">Liters (L)</option>
        <option value="pcs">Pieces (pcs)</option>
        <option value="cup">Cups (cup)</option>
        <option value="tbsp">Tablespoons (tbsp)</option>
        <option value="tsp">Teaspoons (tsp)</option>
      </select>
    </div>
    <div class="col-1 text-end">
      <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.recipe-row').remove()"><i class="bi bi-trash"></i></button>
    </div>
  `;
  container.appendChild(div);
  recipeRowCount++;
}
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
