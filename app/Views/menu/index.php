<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-journal-text text-warning me-2"></i>Menu & Catalog Management</h2>
      <p class="text-muted small mb-0">Manage food categories, items, variant pricing, add-on groups, and recipe Bill of Materials.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/menu/catalog.json" target="_blank" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-file-earmark-code"></i> POS Catalog JSON
      </a>
      <a href="<?= BASE_URL ?>/menu/products/create" class="btn btn-pos-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-plus-circle"></i> Add Menu Item
      </a>
    </div>
  </div>

  <!-- Category Filter Pills -->
  <div class="d-flex flex-wrap gap-2 mb-4 pb-2 border-bottom">
    <a href="<?= BASE_URL ?>/menu" class="btn btn-sm <?= $selectedCat === null ? 'btn-dark' : 'btn-outline-secondary' ?>">
      All Categories <span class="badge bg-secondary ms-1"><?= count($products) ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= BASE_URL ?>/menu?category_id=<?= $cat['id'] ?>" class="btn btn-sm <?= $selectedCat === (int)$cat['id'] ? 'btn-dark' : 'btn-outline-secondary' ?>">
        <?= htmlspecialchars($cat['name']) ?>
        <span class="badge bg-light text-dark border ms-1"><?= $cat['product_count'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Products Grid -->
  <?php if (empty($products)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
      <i class="bi bi-cup-hot text-muted fs-1 mb-2"></i>
      <h5 class="fw-semibold text-secondary">No menu items found</h5>
      <p class="text-muted small">Start by creating your first menu item with variants or recipe ingredients.</p>
      <div>
        <a href="<?= BASE_URL ?>/menu/products/create" class="btn btn-pos-primary btn-sm">Add First Product</a>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($products as $prod): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card card-metric h-100 border-0 shadow-sm rounded-3">
            <div class="card-body p-3 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-light text-muted border small"><?= htmlspecialchars($prod['category_name']) ?></span>
                <span class="fw-bold fs-5 text-success">₱<?= number_format((float)$prod['base_price'], 2) ?></span>
              </div>
              <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($prod['name']) ?></h5>
              <?php if (!empty($prod['description'])): ?>
                <p class="text-muted small mb-2 text-truncate" title="<?= htmlspecialchars($prod['description']) ?>">
                  <?= htmlspecialchars($prod['description']) ?>
                </p>
              <?php endif; ?>

              <!-- Variants & Add-ons Summary -->
              <div class="mb-3 mt-auto">
                <?php if (!empty($prod['variants'])): ?>
                  <div class="small text-secondary mb-1">
                    <i class="bi bi-tags me-1"></i> <strong>Variants:</strong>
                    <?php foreach ($prod['variants'] as $v): ?>
                      <span class="badge bg-secondary-subtle text-secondary border me-1">
                        <?= htmlspecialchars($v['name']) ?> (+₱<?= number_format((float)$v['price_adjustment'], 2) ?>)
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($prod['addon_groups'])): ?>
                  <div class="small text-secondary mb-1">
                    <i class="bi bi-plus-square me-1"></i> <strong>Add-ons:</strong>
                    <?= count($prod['addon_groups']) ?> groups attached
                  </div>
                <?php endif; ?>

                <!-- Estimated Food Cost & Margin -->
                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded small mt-2">
                  <span>Food Cost: <strong>₱<?= number_format((float)$prod['base_cost'], 2) ?></strong></span>
                  <span class="badge <?= $prod['profit_margin'] >= 50 ? 'bg-success' : 'bg-warning text-dark' ?>">
                    Margin: <?= $prod['profit_margin'] ?>%
                  </span>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="d-flex gap-2 pt-2 border-top">
                <a href="<?= BASE_URL ?>/menu/products/<?= $prod['id'] ?>/recipe" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                  <i class="bi bi-card-checklist"></i> Recipe BOM
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
