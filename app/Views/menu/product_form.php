<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/menu">Menu</a></li>
          <li class="breadcrumb-item active" aria-current="page">Add Menu Item</li>
        </ol>
      </nav>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($categories)): ?>
        <div class="alert alert-warning">
          <strong>No active categories yet.</strong>
          Create a category on the <a href="<?= BASE_URL ?>/menu">Menu Management</a> page before adding a product.
        </div>
      <?php endif; ?>

      <?php if (empty($product_image_column_ready)): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <strong>Product image uploads need a one-time database update.</strong>
          Run <code>ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER barcode;</code> once in phpMyAdmin → SQL. Menu items can still be created without an image until then.
        </div>
      <?php endif; ?>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle text-warning me-2"></i>Create Menu Item</h5>
          <p class="small text-muted mb-0 mt-1">Create a schema-compatible catalog item for this restaurant.</p>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/menu/products" id="productForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="row g-3 mb-3">
              <div class="col-12 col-md-8">
                <label for="name" class="form-label fw-semibold small">Product Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" maxlength="150" required placeholder="e.g. Pork Adobo Rice Meal">
              </div>
              <div class="col-12 col-md-4">
                <label for="category_id" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required <?= empty($categories) ? 'disabled' : '' ?>>
                  <option value="">Select Category...</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-12 col-md-6">
                <label for="sku" class="form-label fw-semibold small">SKU / Code <span class="text-muted">(Optional; generated if blank)</span></label>
                <input type="text" class="form-control text-uppercase" id="sku" name="sku" maxlength="50" pattern="[A-Za-z0-9._-]+" placeholder="e.g. ADOBO-RICE">
              </div>
              <div class="col-12 col-md-6">
                <label for="product_type" class="form-label fw-semibold small">Inventory Type <span class="text-danger">*</span></label>
                <select class="form-select" id="product_type" name="product_type" required>
                  <option value="recipe" selected>Recipe / Ingredients</option>
                  <option value="direct">Direct stock item (e.g. bottled drink)</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-12 col-md-4">
                <label for="base_price" class="form-label fw-semibold small">Selling Price (PHP) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" step="0.01" min="0" class="form-control" id="base_price" name="base_price" required placeholder="0.00">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label for="cost_price" class="form-label fw-semibold small">Estimated Cost (PHP)</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="0.00">
                </div>
                <div class="form-text">Used as a fallback cost where no recipe applies.</div>
              </div>
              <div class="col-12 col-md-4">
                <label for="tax_rate" class="form-label fw-semibold small">Tax Rate (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="tax_rate" name="tax_rate" value="12.00" required>
              </div>
            </div>

            <div class="border rounded-3 p-3 mb-4 bg-light">
              <div class="d-flex align-items-start gap-3">
                <div class="flex-grow-1">
                  <label for="productImageInput" class="form-label fw-semibold small">Menu Item Photo <span class="text-muted">(Optional)</span></label>
                  <input type="file" class="form-control" id="productImageInput" name="product_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= empty($product_image_column_ready) ? 'disabled' : '' ?>>
                  <div class="form-text">JPG, PNG, or WebP · up to 3 MB · square or landscape images work best. The photo will appear on the POS menu.</div>
                </div>
                <div class="menu-image-preview-frame d-none" id="productImagePreviewFrame" style="width:105px;height:92px;flex:0 0 105px;border-radius:12px;overflow:hidden;border:1px solid #dce3ec;background:#fff">
                  <img id="productImagePreview" src="" alt="Product image preview" style="width:100%;height:100%;object-fit:cover">
                </div>
              </div>
            </div>

            <div id="directStockSection" class="border rounded-3 bg-light p-3 mb-4 d-none">
              <label for="direct_stock_ingredient_id" class="form-label fw-semibold small">Stock Ingredient <span class="text-danger">*</span></label>
              <select class="form-select" id="direct_stock_ingredient_id" name="direct_stock_ingredient_id">
                <option value="">Select the ingredient deducted per unit sold...</option>
                <?php foreach ($directStockIngredients as $ing): ?>
                  <option value="<?= (int)$ing['id'] ?>">
                    <?= htmlspecialchars($ing['name'], ENT_QUOTES, 'UTF-8') ?> — <?= number_format((float)$ing['current_stock'], 2) ?> <?= htmlspecialchars($ing['base_unit'], ENT_QUOTES, 'UTF-8') ?> in stock
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Direct-stock products deduct one base unit per unit sold. Use a recipe for multi-unit or ingredient-based products.</div>
            </div>

            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold small mb-0">Size / Serving Variants <span class="text-muted">(Optional)</span></label>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addVariantBtn"><i class="bi bi-plus-lg"></i> Add Variant</button>
              </div>
              <div id="variantsContainer" class="d-flex flex-column gap-2"></div>
              <small class="text-muted">Enter a price adjustment, such as 0 for Solo or 50 for Sharing. Total variant price is the base price plus the adjustment.</small>
            </div>

            <?php if (!empty($addonGroups)): ?>
              <div class="mb-4">
                <label class="form-label fw-semibold small">Applicable Add-on Groups</label>
                <div class="row g-2">
                  <?php foreach ($addonGroups as $ag): ?>
                    <div class="col-12 col-md-6">
                      <div class="form-check border p-3 rounded">
                        <input class="form-check-input" type="checkbox" name="addon_group_ids[]" value="<?= (int)$ag['id'] ?>" id="ag_<?= (int)$ag['id'] ?>">
                        <label class="form-check-label small fw-medium" for="ag_<?= (int)$ag['id'] ?>">
                          <?= htmlspecialchars($ag['name'], ENT_QUOTES, 'UTF-8') ?>
                          <span class="text-muted">(<?= !empty($ag['is_required']) ? 'Required' : 'Optional' ?>)</span>
                        </label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <a href="<?= BASE_URL ?>/menu" class="btn btn-light px-4">Cancel</a>
              <button type="submit" class="btn btn-pos-primary px-4 fw-semibold" <?= empty($categories) ? 'disabled' : '' ?>>
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
(function () {
  const typeSelect = document.getElementById('product_type');
  const directSection = document.getElementById('directStockSection');
  const directIngredient = document.getElementById('direct_stock_ingredient_id');
  const variantsContainer = document.getElementById('variantsContainer');
  const productImageInput = document.getElementById('productImageInput');
  const productImagePreviewFrame = document.getElementById('productImagePreviewFrame');
  const productImagePreview = document.getElementById('productImagePreview');
  let productImagePreviewUrl = null;
  productImageInput?.addEventListener('change', () => {
    const file = productImageInput.files && productImageInput.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
      alert('Choose a JPG, PNG, or WebP image up to 3 MB.');
      productImageInput.value = '';
      productImagePreviewFrame?.classList.add('d-none');
      return;
    }
    if (productImagePreviewUrl) URL.revokeObjectURL(productImagePreviewUrl);
    productImagePreviewUrl = URL.createObjectURL(file);
    if (productImagePreview) productImagePreview.src = productImagePreviewUrl;
    productImagePreviewFrame?.classList.remove('d-none');
  });
  let variantCount = 0;

  function toggleProductType() {
    const isDirect = typeSelect.value === 'direct';
    directSection.classList.toggle('d-none', !isDirect);
    directIngredient.required = isDirect;
    if (!isDirect) directIngredient.value = '';
  }

  function addVariantRow() {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center bg-light p-2 rounded';
    const nameCol = document.createElement('div');
    nameCol.className = 'col-6';
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.name = 'variants[' + variantCount + '][name]';
    nameInput.className = 'form-control form-control-sm';
    nameInput.placeholder = 'Variant name (e.g. Solo / Sharing)';
    nameInput.maxLength = 100;
    nameInput.required = true;
    nameCol.appendChild(nameInput);

    const priceCol = document.createElement('div');
    priceCol.className = 'col-4';
    const group = document.createElement('div');
    group.className = 'input-group input-group-sm';
    const prefix = document.createElement('span');
    prefix.className = 'input-group-text';
    prefix.textContent = '+₱';
    const priceInput = document.createElement('input');
    priceInput.type = 'number';
    priceInput.name = 'variants[' + variantCount + '][price_adjustment]';
    priceInput.className = 'form-control form-control-sm';
    priceInput.step = '0.01';
    priceInput.value = '0';
    priceInput.required = true;
    group.append(prefix, priceInput);
    priceCol.appendChild(group);

    const removeCol = document.createElement('div');
    removeCol.className = 'col-2 text-end';
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline-danger btn-sm';
    remove.textContent = 'Remove';
    remove.addEventListener('click', function () { row.remove(); });
    removeCol.appendChild(remove);
    row.append(nameCol, priceCol, removeCol);
    variantsContainer.appendChild(row);
    variantCount++;
  }

  typeSelect.addEventListener('change', toggleProductType);
  document.getElementById('addVariantBtn').addEventListener('click', addVariantRow);
  toggleProductType();
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
