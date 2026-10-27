<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';
?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Combo Meals</h1>
    <p class="text-muted mb-0">Bundle two or more standard menu items under an existing menu product. Set that product's base price to the combo selling price in Menu Management.</p>
  </div>
  <?php foreach (($flashes ?? []) as $flash): ?>
    <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach; ?>
  <div class="alert alert-info small">
    Combo components must be active standard items without variants/add-on groups, and nested combos are not supported. Components are included in one kitchen ticket line; inventory is deducted from each component's recipe/direct-stock setup. Review the product recipe and inventory configuration before using combos for live sales.
  </div>
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">Create a combo</h2>
      <form method="post" action="<?= BASE_URL ?>/combos/save" id="comboForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
          <label class="form-label" for="comboProduct">Combo menu item</label>
          <select class="form-select" name="product_id" id="comboProduct" required>
            <option value="">Choose an existing menu item to use as the combo product</option>
            <?php foreach ($products as $product): ?>
              <option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?> — ₱<?= number_format((float)$product['base_price'], 2) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">The parent menu item's price is the bundle price charged at POS.</div>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <label class="form-label mb-0">Included menu items</label>
          <button class="btn btn-sm btn-outline-primary" type="button" id="addComboRow">Add component</button>
        </div>
        <div id="comboRows" class="vstack gap-2">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="row g-2 align-items-center combo-row">
              <div class="col-8">
                <select class="form-select" name="component_product_id[]">
                  <option value="">Choose component</option>
                  <?php foreach ($componentProducts as $component): ?>
                    <option value="<?= (int)$component['id'] ?>"><?= htmlspecialchars((string)$component['name'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3"><input class="form-control" type="number" name="quantity[]" min="0.001" max="999" step="0.001" value="" placeholder="Qty"></div>
              <div class="col-1"><button class="btn btn-sm btn-outline-danger remove-combo-row" type="button" aria-label="Remove component row">×</button></div>
            </div>
          <?php endfor; ?>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Save combo</button>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
      <div class="p-3 border-bottom"><h2 class="h5 fw-bold mb-0">Configured combos</h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Combo</th><th>Included items</th><th>Bundle price</th><th></th></tr></thead>
          <tbody>
          <?php if (!$combos): ?><tr><td colspan="4" class="text-center text-muted py-4">No combo meals configured yet.</td></tr><?php endif; ?>
          <?php foreach ($combos as $combo): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string)$combo['combo_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
              <td><?php foreach ($combo['components'] as $component): ?><div><?= htmlspecialchars((string)$component['name'], ENT_QUOTES, 'UTF-8') ?> <span class="text-muted">× <?= rtrim(rtrim(number_format((float)$component['quantity'], 3, '.', ''), '0'), '.') ?></span></div><?php endforeach; ?></td>
              <td>₱<?= number_format((float)$combo['base_price'], 2) ?></td>
              <td><form method="post" action="<?= BASE_URL ?>/combos/remove" onsubmit="return confirm('Remove this combo definition? The menu item itself will remain available at its normal price.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="product_id" value="<?= (int)$combo['product_id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove combo</button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = document.getElementById('comboRows');
  const add = document.getElementById('addComboRow');
  if (!rows || !add) return;
  const template = rows.querySelector('.combo-row');
  add.addEventListener('click', () => {
    if (rows.querySelectorAll('.combo-row').length >= 20) return;
    const clone = template.cloneNode(true);
    clone.querySelector('select').value = '';
    clone.querySelector('input').value = '';
    rows.appendChild(clone);
  });
  rows.addEventListener('click', event => {
    const button = event.target.closest('.remove-combo-row');
    if (!button) return;
    const row = button.closest('.combo-row');
    if (rows.querySelectorAll('.combo-row').length > 2) row.remove();
    else { row.querySelector('select').value = ''; row.querySelector('input').value = ''; }
  });
});
</script>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
