<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<style>
  .sf-search-panel{display:block;width:100%;margin:18px 0;padding:18px 20px;background:#fff;border:1px solid #e3e9f2;border-radius:16px;box-shadow:0 3px 12px rgba(15,23,42,.035);}
  .sf-search-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end;width:100%;}
  .sf-search-field{display:block;min-width:0;width:100%;margin:0;}
  .sf-search-label{display:block;margin:0 0 7px;color:#344054;font-size:.86rem;font-weight:700;line-height:1.3;}
  .sf-search-input{display:block;width:100%;min-width:0;height:44px;padding:10px 13px;border:1px solid #d5deea;border-radius:10px;background:#fff;color:#172033;font:inherit;font-size:.92rem;box-shadow:inset 0 1px 2px rgba(16,24,40,.025);}
  .sf-search-input:focus{outline:0;border-color:#6ea3f8;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
  .sf-search-actions{display:flex;align-items:center;gap:8px;}
  .sf-search-actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:10px 17px;border-radius:10px;white-space:nowrap;font-size:.9rem;font-weight:700;}
  .sf-search-hint{margin:6px 0 0;color:#8491a5;font-size:.76rem;line-height:1.4;}
  @media(max-width:600px){.sf-search-panel{padding:14px;border-radius:13px}.sf-search-row{grid-template-columns:minmax(0,1fr);gap:10px}.sf-search-actions{justify-content:flex-start}.sf-search-actions .btn{flex:1}}
</style>
<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-boxes text-warning me-2"></i>Inventory & Stock Control</h2>
      <p class="text-muted small mb-0">Raw ingredients catalog, base unit normalization, low-stock threshold monitoring, and packaging factors.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/inventory/ledger" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-clock-history"></i> Movements Ledger
      </a>
      <a href="<?= BASE_URL ?>/inventory/adjustment" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-sliders"></i> Adjust Stock
      </a>
      <a href="<?= BASE_URL ?>/inventory/stock-in" class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-box-arrow-in-down"></i> Receive Goods (Stock In)
      </a>
    </div>
  </div>

  <!-- Low Stock Alert Banner -->
  <?php if (!empty($lowStock)): ?>
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-3 mb-4 p-3 rounded-3">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
      <div class="flex-grow-1">
        <h6 class="fw-bold mb-1"><?= count($lowStock) ?> Ingredient(s) Below Alert Threshold!</h6>
        <div class="d-flex flex-wrap gap-2 small">
          <?php foreach ($lowStock as $ls): ?>
            <span class="badge bg-white text-danger border">
              <?= htmlspecialchars($ls['name']) ?>: <?= number_format((float)$ls['current_stock'], 2) ?> <?= htmlspecialchars($ls['base_unit']) ?> (Deficit: <?= number_format((float)$ls['deficit'], 2) ?>)
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/inventory/stock-in" class="btn btn-danger btn-sm px-3 fw-semibold text-nowrap">Restock Now</a>
    </div>
  <?php endif; ?>

  <!-- Success Notification -->
  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form method="get" action="<?= BASE_URL ?>/inventory" class="sf-search-panel">
    <div class="sf-search-row">
      <div class="sf-search-field">
        <label for="inventorySearch" class="sf-search-label">Search inventory</label>
        <input id="inventorySearch" class="sf-search-input" type="search" name="search" value="<?= htmlspecialchars((string)($search ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Ingredient name, SKU, or unit">
      </div>
      <div class="sf-search-actions">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i>Search</button>
        <?php if (!empty($search)): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/inventory">Clear</a><?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Ingredients Table -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-semibold text-secondary">
        <i class="bi bi-egg text-primary me-2"></i>Ingredients & Base Stock Levels
      </h5>
      <span class="badge bg-secondary"><?= count($ingredients) ?> Items</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-secondary">
          <tr>
            <th class="ps-4">Ingredient</th>
            <th>Base Unit</th>
            <th>Current Stock</th>
            <th>Alert Threshold</th>
            <th>Unit Cost</th>
            <th>Packaged Units</th>
            <th>Status</th>
            <th class="pe-4 text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ingredients as $ing): 
            $isLow = (float)$ing['current_stock'] <= (float)$ing['minimum_stock_alert'];
          ?>
            <tr class="<?= $isLow ? 'table-danger-subtle' : '' ?>">
              <td class="ps-4 fw-semibold text-dark">
                <?= htmlspecialchars($ing['name']) ?>
                <div class="text-muted small">SKU: <?= htmlspecialchars($ing['sku']) ?></div>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($ing['base_unit']) ?></span>
              </td>
              <td class="fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?>">
                <?= number_format((float)$ing['current_stock'], 2) ?> <?= htmlspecialchars($ing['base_unit']) ?>
              </td>
              <td class="text-muted">
                <?= number_format((float)$ing['minimum_stock_alert'], 2) ?> <?= htmlspecialchars($ing['base_unit']) ?>
              </td>
              <td>
                ₱<?= number_format((float)$ing['cost_per_base_unit'], 4) ?> / <?= htmlspecialchars($ing['base_unit']) ?>
              </td>
              <td>
                <?php if ($ing['packaging_count'] > 0): ?>
                  <span class="badge bg-info-subtle text-info-emphasis border">
                    <?= $ing['packaging_count'] ?> Conversion unit(s)
                  </span>
                <?php else: ?>
                  <span class="text-muted small">None</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isLow): ?>
                  <span class="badge bg-danger">Low Stock</span>
                <?php else: ?>
                  <span class="badge bg-success">Adequate</span>
                <?php endif; ?>
              </td>
              <td class="pe-4 text-end">
                <a href="<?= BASE_URL ?>/inventory/ledger?ingredient_id=<?= $ing['id'] ?>" class="btn btn-outline-secondary btn-sm" title="View Movements">
                  <i class="bi bi-journal-text"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
