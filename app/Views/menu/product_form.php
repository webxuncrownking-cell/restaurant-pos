<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/menu">Menu</a></li>
          <li class="breadcrumb-item active" aria-current="page">Add Menu Item</li>
        </ol>
      </nav>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle text-warning me-2"></i>Create Menu Item</h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/menu/products" id="productForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Basic Info -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-8">
                <label for="name" class="form-label fw-semibold small">Product Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Sizzling Sisig w/ Egg">
              </div>
              <div class="col-12 col-md-4">
                <label for="category_id" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                  <option value="">Select Category...</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label for="base_price" class="form-label fw-semibold small">Base Price (PHP) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" step="0.01" min="0" class="form-control" id="base_price" name="base_price" required placeholder="0.00">
                </div>
              </div>
              <div class="col-12 col-md-6">
                <label for="sku" class="form-label fw-semibold small">SKU / Code <span class="text-muted">(Optional)</span></label>
                <input type="text" class="form-control" id="sku" name="sku" placeholder="e.g. SISIG-REG">
              </div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label fw-semibold small">Description</label>
              <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief description of the dish..."></textarea>
            </div>

            <!-- Toggles -->
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <div class="form-check form-switch p-3 bg-light rounded-3">
                  <input class="form-check-input ms-0 me-2" type="checkbox" id="track_inventory" name="track_inventory" value="1" checked>
                  <label class="form-check-label fw-semibold small" for="track_inventory">
                    Track Inventory via Recipe BOM
                  </label>
                  <div class="text-muted small">Auto-deduct ingredient stock upon order</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch p-3 bg-light rounded-3">
                  <input class="form-check-input ms-0 me-2" type="checkbox" id="is_taxable" name="is_taxable" value="1" checked>
                  <label class="form-check-label fw-semibold small" for="is_taxable">
                    Subject to 12% VAT
                  </label>
                  <div class="text-muted small">Included in tax computation</div>
                </div>
              </div>
            </div>

            <!-- Product Variants Section -->
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold small mb-0">Size / Serving Variants <span class="text-muted">(Optional)</span></label>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addVariantRow()">
                  <i class="bi bi-plus-lg"></i> Add Variant
                </button>
              </div>
              <div id="variantsContainer" class="d-flex flex-column gap-2">
                <!-- Variant row template inserted here -->
              </div>
              <small class="text-muted">e.g. Regular (₱0.00 adjustment), Large (+₱50.00 adjustment)</small>
            </div>

            <!-- Add-on Groups Section -->
            <?php if (!empty($addonGroups)): ?>
              <div class="mb-4">
                <label class="form-label fw-semibold small">Applicable Add-on Groups</label>
                <div class="row g-2">
                  <?php foreach ($addonGroups as $ag): ?>
                    <div class="col-md-6">
                      <div class="form-check border p-2 rounded ps-4">
                        <input class="form-check-input" type="checkbox" name="addon_group_ids[]" value="<?= $ag['id'] ?>" id="ag_<?= $ag['id'] ?>">
                        <label class="form-check-label small fw-medium" for="ag_<?= $ag['id'] ?>">
                          <?= htmlspecialchars($ag['name']) ?>
                          <span class="text-muted">(<?= $ag['is_required'] ? 'Required' : 'Optional' ?>)</span>
                        </label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/menu" class="btn btn-light px-4">Cancel</a>
              <button type="submit" class="btn btn-pos-primary px-4 fw-semibold">
                Save & Configure Recipe <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let variantCount = 0;
function addVariantRow(name = '', adjustment = 0) {
  const container = document.getElementById('variantsContainer');
  const div = document.createElement('div');
  div.className = 'row g-2 align-items-center bg-light p-2 rounded';
  div.innerHTML = `
    <div class="col-6">
      <input type="text" name="variants[${variantCount}][name]" class="form-control form-control-sm" placeholder="Variant Name (e.g. Solo / Sharing)" value="${name}" required>
    </div>
    <div class="col-4">
      <div class="input-group input-group-sm">
        <span class="input-group-text">+₱</span>
        <input type="number" step="0.01" name="variants[${variantCount}][price_adjustment]" class="form-control form-control-sm" placeholder="0.00" value="${adjustment}">
      </div>
    </div>
    <div class="col-2 text-end">
      <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.row').remove()"><i class="bi bi-trash"></i></button>
    </div>
  `;
  container.appendChild(div);
  variantCount++;
}
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
