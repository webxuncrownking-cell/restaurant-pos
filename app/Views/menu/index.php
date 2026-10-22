<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
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
  </div>

  <?php if (empty($products)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
      <i class="bi bi-cup-hot text-muted fs-1 mb-2"></i>
      <h5 class="fw-semibold text-secondary">No menu items found</h5>
      <p class="text-muted small">Create a product and then configure its recipe or linked stock ingredient.</p>
      <div><a href="<?= BASE_URL ?>/menu/products/create" class="btn btn-pos-primary btn-sm">Add First Product</a></div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($products as $prod): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card card-metric h-100 border-0 shadow-sm rounded-3 <?= empty($prod['is_active']) ? 'opacity-75' : '' ?>">
            <div class="card-body p-3 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-light text-muted border small"><?= htmlspecialchars($prod['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="fw-bold fs-5 text-success">₱<?= number_format((float)$prod['base_price'], 2) ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-start gap-2">
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                <span class="badge <?= !empty($prod['is_active']) ? 'bg-success-subtle text-success' : 'bg-secondary' ?>">
                  <?= !empty($prod['is_active']) ? 'Visible' : 'Hidden' ?>
                </span>
              </div>
              <div class="small text-muted mb-3">
                SKU: <?= htmlspecialchars((string)$prod['sku'], ENT_QUOTES, 'UTF-8') ?>
                · <?= ($prod['product_type'] ?? 'recipe') === 'direct' ? 'Direct stock' : 'Recipe based' ?>
              </div>

              <div class="mb-3 mt-auto">
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

              <div class="d-flex gap-2 pt-2 border-top">
                <a href="<?= BASE_URL ?>/menu/products/<?= (int)$prod['id'] ?>/recipe" class="btn btn-outline-primary btn-sm flex-grow-1">
                  <i class="bi bi-card-checklist me-1"></i> Recipe BOM
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

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
