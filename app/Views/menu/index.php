<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<style>
  /* Fixed photo frame: absolutely constrain the image box, not just its crop mode. */
  .menu-product-photo {
    position:relative;
    display:block;
    width:100%;
    height:180px;
    min-height:180px;
    max-height:180px;
    overflow:hidden;
    border-radius:13px;
    background:linear-gradient(145deg,#f7f9fc,#edf2f8);
    border:1px solid #e8edf4;
    isolation:isolate;
  }
  .menu-product-photo > img {
    position:absolute !important;
    inset:0 !important;
    display:block !important;
    width:100% !important;
    height:100% !important;
    min-width:0 !important;
    min-height:0 !important;
    max-width:none !important;
    max-height:none !important;
    margin:0 !important;
    object-fit:cover !important;
    object-position:50% 50% !important;
  }
  .menu-product-photo .menu-photo-empty {
    position:absolute;
    inset:0;
    display:grid;
    place-items:center;
    font-size:2rem;
    color:#b2bdcd;
  }
  .menu-product-photo .menu-photo-empty { font-size:2rem; color:#b2bdcd; }
  .menu-product-grid { --bs-gutter-y: 1rem; }
  .menu-view-toggle { border-color:#dbe3ed !important; }
  .menu-view-toggle-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:38px; min-width:38px; height:36px; padding:.35rem !important;
    border-radius:8px !important; line-height:1;
  }
  .menu-view-toggle-btn .bi { display:block; line-height:1; font-size:1rem; }
  .menu-view-toggle-btn.active {
    color:#fff !important; background:#2563eb !important; border-color:#2563eb !important;
    box-shadow:0 1px 3px rgba(37,99,235,.2);
  }
  .menu-view-toggle-btn:not(.active):hover { color:#1d4ed8 !important; background:#eff6ff !important; border-color:#93c5fd !important; }
  .menu-product-identity, .menu-product-secondary { display:contents; }
  .menu-product-grid.is-list {
    display:flex; flex-direction:column;
    --bs-gutter-x:0; --bs-gutter-y:.85rem;
  }
  .menu-product-grid.is-list > .menu-product-col { width:100%; max-width:100%; flex:0 0 100%; padding-left:0; padding-right:0; }
  .menu-product-grid.is-list .menu-product-card-body {
    display:grid !important;
    grid-template-columns:140px minmax(220px,1fr) minmax(260px,1.05fr) auto;
    grid-template-areas:"photo identity secondary actions";
    align-items:center; gap:16px;
  }
  .menu-product-grid.is-list .menu-product-photo {
    grid-area:photo; width:140px; height:120px; min-height:120px; max-height:120px;
    margin:0 !important; align-self:center;
  }
  .menu-product-grid.is-list .menu-product-identity {
    grid-area:identity; display:flex; flex-direction:column; min-width:0;
  }
  .menu-product-grid.is-list .menu-product-price-row { gap:8px; margin-bottom:.5rem !important; }
  .menu-product-grid.is-list .menu-product-price-row .fs-5 { font-size:1.05rem !important; }
  .menu-product-grid.is-list .menu-product-title-row { align-items:flex-start !important; }
  .menu-product-grid.is-list .menu-product-title-row h5 { font-size:1rem; overflow-wrap:anywhere; }
  .menu-product-grid.is-list .menu-product-sku { margin-bottom:0 !important; overflow-wrap:anywhere; }
  .menu-product-grid.is-list .menu-product-secondary {
    grid-area:secondary; display:flex; flex-direction:column; justify-content:center; min-width:0;
  }
  .menu-product-grid.is-list .menu-product-details { margin-top:0 !important; margin-bottom:0 !important; }
  .menu-product-grid.is-list .menu-product-actions {
    grid-area:actions; display:flex; flex-direction:column; min-width:110px;
    border-top:0 !important; padding-top:0 !important; align-self:center;
  }
  .menu-product-grid.is-list .menu-product-actions > a,
  .menu-product-grid.is-list .menu-product-actions > form,
  .menu-product-grid.is-list .menu-product-actions > form .btn { width:100%; }
  @media(max-width:1100px) {
    .menu-product-grid.is-list .menu-product-card-body {
      grid-template-columns:120px minmax(0,1fr);
      grid-template-areas:"photo identity" "secondary secondary" "actions actions";
      align-items:start; gap:12px;
    }
    .menu-product-grid.is-list .menu-product-photo { width:120px; height:110px; min-height:110px; max-height:110px; }
    .menu-product-grid.is-list .menu-product-actions { flex-direction:row; align-self:stretch; min-width:0; border-top:1px solid #e2e8f0 !important; padding-top:.75rem !important; }
    .menu-product-grid.is-list .menu-product-actions > a { width:auto; }
    .menu-product-grid.is-list .menu-product-actions > a:first-child { flex:1 1 auto; }
    .menu-product-grid.is-list .menu-product-actions > form { width:auto; }
    .menu-product-grid.is-list .menu-product-actions > form .btn { width:auto; }
  }
  @media(max-width:560px) {
    .menu-product-grid.is-list .menu-product-card-body { grid-template-columns:88px minmax(0,1fr); gap:10px; padding:.75rem !important; }
    .menu-product-grid.is-list .menu-product-photo { width:88px; height:88px; min-height:88px; max-height:88px; }
    .menu-product-grid.is-list .menu-product-title-row h5 { font-size:.92rem; }
    .menu-product-grid.is-list .menu-product-price-row .badge { white-space:normal; }
    .menu-product-grid.is-list .menu-product-actions { flex-wrap:wrap; }
  }
  .menu-management-page .btn-pos-primary {
    background-color:#2563eb !important;
    border-color:#2563eb !important;
    color:#fff !important;
    filter:none !important;
    transition:background-color .15s ease,border-color .15s ease,box-shadow .15s ease,transform .15s ease;
    box-shadow:0 2px 5px rgba(37,99,235,.16);
  }
  .menu-management-page .btn-pos-primary:not(:disabled):not(.disabled):hover {
    background-color:#1d4ed8 !important;
    border-color:#1d4ed8 !important;
    color:#fff !important;
    filter:none !important;
    box-shadow:0 4px 10px rgba(37,99,235,.22);
    transform:translateY(-1px);
  }
  .menu-management-page .btn-pos-primary:not(:disabled):not(.disabled):active {
    background-color:#1e40af !important;
    border-color:#1e40af !important;
    color:#fff !important;
    transform:translateY(0);
    box-shadow:0 1px 3px rgba(37,99,235,.2);
  }
  .menu-management-page .btn-pos-primary:focus-visible {
    outline:0;
    box-shadow:0 0 0 .2rem rgba(37,99,235,.24);
  }
</style>

<div class="container py-4 menu-management-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-journal-text text-warning me-2"></i>Menu & Catalog Management</h2>
      <p class="text-muted small mb-0">Manage categories, products, serving variants, add-on groups, and recipe bills of materials.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/menu/catalog.json" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-file-earmark-code"></i> POS Catalog JSON
      </a>
      <a href="<?= BASE_URL ?>/menu/products/create" class="btn btn-pos-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-plus-circle"></i> Add Menu Item
      </a>
    </div>
  </div>

  <?php if (isset($_GET['product_updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Menu item updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['image_updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Menu item image saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (isset($_GET['category_created'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Category created successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (isset($_GET['visibility_updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Product visibility updated.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (empty($product_image_column_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm" role="alert">
      <strong>Menu photos are not enabled in this database yet.</strong>
      Run <code>ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER barcode;</code> once in phpMyAdmin → SQL. Existing menu and sales data will be preserved.
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
      <form method="POST" action="<?= BASE_URL ?>/menu/categories" class="row g-2 align-items-end">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="col-12 col-md-7">
          <label for="new-category-name" class="form-label small fw-semibold mb-1">Add a category</label>
          <input id="new-category-name" name="name" type="text" maxlength="100" class="form-control form-control-sm" placeholder="e.g. Rice Meals, Drinks, Snacks" required>
        </div>
        <div class="col-12 col-md-auto">
          <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Category</button>
        </div>
      </form>
    </div>
  </div>

  <form method="get" action="<?= BASE_URL ?>/menu" class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
      <?php if ($selectedCat !== null): ?><input type="hidden" name="category_id" value="<?= (int)$selectedCat ?>"><?php endif; ?>
      <div class="flex-grow-1" style="min-width:220px">
        <label for="menuSearch" class="form-label small fw-semibold">Search menu catalog</label>
        <input id="menuSearch" class="form-control" type="search" name="search" value="<?= htmlspecialchars((string)($search ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Menu item, SKU, or category">
      </div>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
      <?php if (!empty($search)): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/menu<?= $selectedCat !== null ? '?category_id='.(int)$selectedCat : '' ?>">Clear</a><?php endif; ?>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-4 pb-2 border-bottom">
    <a href="<?= BASE_URL ?>/menu" class="btn btn-sm <?= $selectedCat === null ? 'btn-dark' : 'btn-outline-secondary' ?>">
      All Categories <span class="badge bg-secondary ms-1"><?= count($products) ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= BASE_URL ?>/menu?category_id=<?= (int)$cat['id'] ?>" class="btn btn-sm <?= $selectedCat === (int)$cat['id'] ? 'btn-dark' : 'btn-outline-secondary' ?>">
        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
        <span class="badge bg-light text-dark border ms-1"><?= (int)$cat['product_count'] ?></span>
      </a>
    <?php endforeach; ?>
    <div class="menu-view-toggle ms-auto d-inline-flex align-items-center gap-1 p-1 rounded-3 border bg-white" role="group" aria-label="Menu view">
      <button type="button" class="btn btn-sm btn-outline-secondary menu-view-toggle-btn active" data-menu-view="grid" title="Grid view" aria-label="Grid view" aria-pressed="true">
        <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary menu-view-toggle-btn" data-menu-view="list" title="List view" aria-label="List view" aria-pressed="false">
        <i class="bi bi-list-ul" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <?php if (empty($products)): ?>    <div class="card border-0 shadow-sm p-5 text-center">
      <i class="bi bi-cup-hot text-muted fs-1 mb-2"></i>
      <h5 class="fw-semibold text-secondary">No menu items found</h5>
      <p class="text-muted small">Create a product and then configure its recipe or linked stock ingredient.</p>
      <div><a href="<?= BASE_URL ?>/menu/products/create" class="btn btn-pos-primary btn-sm">Add First Product</a></div>
    </div>
  <?php else: ?>
    <div class="row g-3 menu-product-grid" id="menuProductGrid">
      <?php foreach ($products as $prod): ?>
        <div class="menu-product-col col-12 col-md-6 col-lg-4">
          <div class="menu-product-card card card-metric h-100 border-0 shadow-sm rounded-3 <?= empty($prod['is_active']) ? 'opacity-75' : '' ?>">
            <div class="menu-product-card-body card-body p-3 d-flex flex-column">
              <div class="menu-product-photo mb-3">
                <?php if (!empty($prod['image_path'])): ?>
                  <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$prod['image_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                  <i class="bi bi-image menu-photo-empty" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="menu-product-identity">
              <div class="menu-product-price-row d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-light text-muted border small"><?= htmlspecialchars($prod['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="fw-bold fs-5 text-success">₱<?= number_format((float)$prod['base_price'], 2) ?></span>
              </div>
              <div class="menu-product-title-row d-flex justify-content-between align-items-start gap-2">
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                <span class="badge <?= !empty($prod['is_active']) ? 'bg-success-subtle text-success' : 'bg-secondary' ?>">
                  <?= !empty($prod['is_active']) ? 'Visible' : 'Hidden' ?>
                </span>
              </div>
              <div class="menu-product-sku small text-muted mb-3">
                SKU: <?= htmlspecialchars((string)$prod['sku'], ENT_QUOTES, 'UTF-8') ?>
                · <?= ($prod['product_type'] ?? 'recipe') === 'direct' ? 'Direct stock' : 'Recipe based' ?>
              </div>

              </div>
              <div class="menu-product-secondary">


              <div class="menu-product-details mb-3 mt-auto">
                <?php if (!empty($prod['variants'])): ?>
                  <div class="small text-secondary mb-1">
                    <i class="bi bi-tags me-1"></i> <strong>Variants:</strong>
                    <?php foreach ($prod['variants'] as $v): ?>
                      <span class="badge bg-secondary-subtle text-secondary border me-1 mb-1">
                        <?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= ((float)$v['additional_price'] >= 0 ? '+' : '') ?>₱<?= number_format((float)$v['additional_price'], 2) ?>)
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($prod['addon_groups'])): ?>
                  <div class="small text-secondary mb-1">
                    <i class="bi bi-plus-square me-1"></i> <strong>Add-ons:</strong>
                    <?= count($prod['addon_groups']) ?> group<?= count($prod['addon_groups']) === 1 ? '' : 's' ?> attached
                  </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded small mt-2">
                  <span>Food Cost: <strong>₱<?= number_format((float)$prod['base_cost'], 2) ?></strong></span>
                  <span class="badge <?= (float)$prod['profit_margin'] >= 50 ? 'bg-success' : 'bg-warning text-dark' ?>">
                    Margin: <?= number_format((float)$prod['profit_margin'], 1) ?>%
                  </span>
                </div>
              </div>

              </div>
              <div class="menu-product-actions d-flex flex-wrap gap-2 pt-2 border-top">
                <a href="<?= BASE_URL ?>/menu/products/<?= (int)$prod['id'] ?>/recipe" class="btn btn-outline-primary btn-sm flex-grow-1">
                  <i class="bi bi-card-checklist me-1"></i> Recipe BOM
                </a>
                <a href="<?= BASE_URL ?>/menu/products/<?= (int)$prod['id'] ?>/edit" class="btn btn-outline-secondary btn-sm" title="Edit menu item" aria-label="Edit <?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-pencil-square"></i> Edit
                </a>
                <form method="POST" action="<?= BASE_URL ?>/menu/products/status">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                  <input type="hidden" name="is_active" value="<?= empty($prod['is_active']) ? '1' : '0' ?>">
                  <button type="submit" class="btn btn-sm <?= empty($prod['is_active']) ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= empty($prod['is_active']) ? 'Show on POS' : 'Hide from POS' ?>">
                    <?= empty($prod['is_active']) ? 'Show' : 'Hide' ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const productGrid = document.getElementById('menuProductGrid');
  const viewButtons = Array.from(document.querySelectorAll('[data-menu-view]'));
  const viewStorageKey = 'restaurantPosMenuView';
  const applyMenuView = mode => {
    const selectedMode = mode === 'list' ? 'list' : 'grid';
    if (productGrid) productGrid.classList.toggle('is-list', selectedMode === 'list');
    viewButtons.forEach(button => {
      const active = button.dataset.menuView === selectedMode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    try { localStorage.setItem(viewStorageKey, selectedMode); } catch (error) { /* Storage may be disabled. */ }
  };
  let initialView = 'grid';
  try {
    const savedView = localStorage.getItem(viewStorageKey);
    if (savedView === 'list' || savedView === 'grid') initialView = savedView;
  } catch (error) { /* Default to grid when storage is unavailable. */ }
  applyMenuView(initialView);
  viewButtons.forEach(button => button.addEventListener('click', () => applyMenuView(button.dataset.menuView)));

})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
