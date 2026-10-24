<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$money = static fn(float $value): string => '₱' . number_format($value, 2);
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$unitCatalog = [];
foreach (($ingredients ?? []) as $ingredient) {
    $unitCatalog[(string)$ingredient['id']] = $ingredient['purchase_units'] ?? [];
}
?>
<style>
  .procurement-page { padding:24px; }
  .procurement-hero { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-start; justify-content:space-between; margin-bottom:22px; }
  .procurement-eyebrow { color:#8794a7; text-transform:uppercase; letter-spacing:.12em; font-size:.67rem; font-weight:850; }
  .procurement-title { color:#172033; font-size:1.65rem; letter-spacing:-.04em; font-weight:850; margin:4px 0 6px; }
  .procurement-subtitle { color:#718096; margin:0; font-size:.88rem; }
  .procurement-card { background:#fff; border:1px solid #e3eaf2; border-radius:16px; box-shadow:0 3px 14px rgba(15,23,42,.03); overflow:hidden; }
  .procurement-card-head { display:flex; align-items:center; gap:12px; padding:17px 20px; border-bottom:1px solid #edf1f6; }
  .procurement-card-body { padding:20px; }
  .procurement-icon { width:40px; height:40px; flex:0 0 40px; border-radius:12px; display:grid; place-items:center; color:#2563eb; background:#eff6ff; font-size:1.05rem; }
  .procurement-heading { margin:0 0 3px; font-weight:850; font-size:.98rem; color:#172033; }
  .procurement-help { margin:0; font-size:.74rem; color:#8794a7; }
  .procurement-metric { display:flex; align-items:center; gap:12px; border:1px solid #e3eaf2; border-radius:14px; background:#fff; padding:14px 16px; height:100%; }
  .procurement-metric strong { display:block; color:#172033; font-size:1.45rem; line-height:1; }
  .procurement-metric span { display:block; margin-top:6px; color:#718096; font-size:.74rem; }
  .procurement-badge { display:inline-flex; align-items:center; padding:5px 9px; border-radius:999px; font-size:.68rem; font-weight:850; white-space:nowrap; }
  .procurement-badge.draft { background:#f1f5f9; color:#475569; }
  .procurement-badge.ordered { background:#eaf2ff; color:#1d4ed8; }
  .procurement-badge.received { background:#dcfce7; color:#047857; }
  .procurement-badge.cancelled { background:#fee2e2; color:#b91c1c; }
  .procurement-supplier-row { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid #edf1f6; }
  .procurement-supplier-row:last-child { border-bottom:0; }
  .procurement-supplier-name { color:#263248; font-size:.85rem; font-weight:800; }
  .procurement-muted { color:#8794a7; font-size:.73rem; }
  .procurement-line { padding:12px; border:1px solid #e4eaf2; background:#fbfcfe; border-radius:12px; margin-bottom:10px; }
  .procurement-line:last-child { margin-bottom:0; }
  .procurement-line-grid { display:grid; grid-template-columns:minmax(180px,2fr) minmax(95px,.8fr) minmax(120px,1fr) minmax(115px,.9fr) auto; gap:9px; align-items:end; }
  .procurement-line label { display:block; color:#64748b; font-size:.7rem; font-weight:800; margin-bottom:5px; }
  .procurement-line .form-control,.procurement-line .form-select { font-size:.82rem; min-height:38px; }
  .procurement-total { border-radius:12px; padding:13px 15px; background:#f8fafc; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .procurement-total strong { font-size:1.25rem; color:#172033; }
  .procurement-order { border:1px solid #e3eaf2; border-radius:14px; overflow:hidden; margin-bottom:14px; }
  .procurement-order:last-child { margin-bottom:0; }
  .procurement-order-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:15px 17px; background:#fcfdff; border-bottom:1px solid #edf1f6; }
  .procurement-po-number { color:#1d4ed8; font-size:.9rem; font-weight:850; }
  .procurement-order-body { padding:12px 17px; }
  .procurement-order-line { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:start; gap:12px; padding:9px 0; border-bottom:1px dashed #e8edf4; }
  .procurement-order-line:last-child { border-bottom:0; }
  .procurement-order-line-name { color:#263248; font-size:.8rem; font-weight:750; }
  .procurement-order-line-detail { margin-top:3px; font-size:.72rem; color:#8794a7; }
  .procurement-order-total { color:#172033; font-weight:850; white-space:nowrap; }
  .procurement-empty { padding:32px 20px; text-align:center; color:#7c899b; font-size:.83rem; }
  .procurement-empty i { display:block; margin-bottom:8px; font-size:1.8rem; color:#a4b0c0; }
  @media(max-width:1000px) { .procurement-page { padding:16px; } .procurement-card-body { padding:15px; } .procurement-line-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .procurement-line-grid .procurement-line-remove { grid-column:2; justify-self:end; } }
  @media(max-width:560px) { .procurement-line-grid { grid-template-columns:1fr; } .procurement-line-grid .procurement-line-remove { grid-column:1; justify-self:start; } .procurement-order-head { padding:13px; } .procurement-order-body { padding:11px 13px; } }
</style>

<div class="procurement-page">
  <?php if (!empty($flashes)): foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= $h(in_array(($flash['type'] ?? 'info'), ['success','warning','danger','info'], true) ? $flash['type'] : 'info') ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
      <?= $h($flash['message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; endif; ?>
  <?php if (isset($_GET['success'])): ?><div class="alert alert-success"><?= $h($_GET['success']) ?></div><?php endif; ?>
  <?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= $h($_GET['error']) ?></div><?php endif; ?>

  <section class="procurement-hero">
    <div>
      <div class="procurement-eyebrow">Inventory &amp; purchasing</div>
      <h1 class="procurement-title"><i class="bi bi-truck text-primary me-2"></i>Suppliers &amp; Purchase Orders</h1>
      <p class="procurement-subtitle">Manage vendors, prepare purchase orders, and receive deliveries directly into ingredient stock.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>/inventory" class="btn btn-outline-secondary bg-white"><i class="bi bi-boxes me-1"></i> Inventory</a>
      <a href="<?= BASE_URL ?>/inventory/ledger?type=purchase_receipt" class="btn btn-outline-secondary bg-white"><i class="bi bi-journal-text me-1"></i> Purchase Receipts</a>
    </div>
  </section>

  <?php if (empty($schema_ready)): ?>
    <section class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-start gap-3"><i class="bi bi-database-exclamation fs-4"></i><div><strong class="d-block mb-1">One-time purchasing setup required</strong><div class="small">Create missing supplier and purchase-order tables without dropping or rewriting inventory, orders, or sales data.</div></div></div>
      <?php if (!empty($canManage)): ?>
        <form method="POST" action="<?= BASE_URL ?>/procurement/migrate"><?= \App\Core\CSRF::formField() ?><button class="btn btn-warning fw-semibold" type="submit"><i class="bi bi-database-gear me-1"></i> Apply Purchasing Setup</button></form>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3"><div class="procurement-metric"><span class="procurement-icon"><i class="bi bi-truck"></i></span><div><strong><?= count($suppliers) ?></strong><span>Suppliers</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="procurement-metric"><span class="procurement-icon"><i class="bi bi-file-earmark-text"></i></span><div><strong><?= count($purchaseOrders) ?></strong><span>Purchase orders shown</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="procurement-metric"><span class="procurement-icon"><i class="bi bi-send"></i></span><div><strong><?= count(array_filter($purchaseOrders, static fn(array $po): bool => $po['status'] === 'ordered')) ?></strong><span>Awaiting delivery</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="procurement-metric"><span class="procurement-icon"><i class="bi bi-check2-circle"></i></span><div><strong><?= count(array_filter($purchaseOrders, static fn(array $po): bool => $po['status'] === 'received')) ?></strong><span>Received POs</span></div></div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-4">
        <section class="procurement-card h-100">
          <header class="procurement-card-head"><span class="procurement-icon"><i class="bi bi-person-plus"></i></span><div><h2 class="procurement-heading">Add supplier</h2><p class="procurement-help">Keep vendor contact information together.</p></div></header>
          <div class="procurement-card-body">
            <?php if (!empty($canManage)): ?>
              <form method="POST" action="<?= BASE_URL ?>/procurement/suppliers/create">
                <?= \App\Core\CSRF::formField() ?>
                <div class="mb-3"><label class="form-label small fw-semibold" for="supplierName">Supplier name *</label><input class="form-control" id="supplierName" name="name" maxlength="150" required></div>
                <div class="row g-3">
                  <div class="col-12 col-md-6"><label class="form-label small fw-semibold" for="supplierPhone">Phone *</label><input class="form-control" id="supplierPhone" name="phone" maxlength="30" required></div>
                  <div class="col-12 col-md-6"><label class="form-label small fw-semibold" for="supplierContact">Contact person</label><input class="form-control" id="supplierContact" name="contact_person" maxlength="100"></div>
                  <div class="col-12"><label class="form-label small fw-semibold" for="supplierEmail">Email</label><input class="form-control" id="supplierEmail" name="email" maxlength="100" type="email"></div>
                  <div class="col-12"><label class="form-label small fw-semibold" for="supplierAddress">Address</label><textarea class="form-control" id="supplierAddress" name="address" rows="2" maxlength="5000"></textarea></div>
                </div>
                <button class="btn btn-primary w-100 mt-3" type="submit"><i class="bi bi-plus-circle me-1"></i> Save supplier</button>
              </form>
            <?php else: ?><p class="text-secondary small mb-0">Your account has read-only purchasing access.</p><?php endif; ?>

            <hr class="my-4">
            <h3 class="procurement-heading mb-2">Supplier directory</h3>
            <?php if (empty($suppliers)): ?><div class="procurement-empty"><i class="bi bi-truck"></i>No suppliers yet.</div>
            <?php else: foreach ($suppliers as $supplier): ?>
              <div class="procurement-supplier-row">
                <div class="min-w-0">
                  <div class="procurement-supplier-name"><?= $h($supplier['name']) ?> <span class="procurement-badge <?= !empty($supplier['is_active']) ? 'received' : 'cancelled' ?>"><?= !empty($supplier['is_active']) ? 'Active' : 'Inactive' ?></span></div>
                  <div class="procurement-muted"><?= $h($supplier['phone']) ?><?= !empty($supplier['contact_person']) ? ' · ' . $h($supplier['contact_person']) : '' ?></div>
                  <?php if (!empty($supplier['email'])): ?><div class="procurement-muted"><?= $h($supplier['email']) ?></div><?php endif; ?>
                  <div class="procurement-muted"><?= (int)$supplier['purchase_order_count'] ?> purchase order(s)</div>
                </div>
                <?php if (!empty($canManage)): ?>
                  <form method="POST" action="<?= BASE_URL ?>/procurement/suppliers/status"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>"><input type="hidden" name="is_active" value="<?= !empty($supplier['is_active']) ? '0' : '1' ?>"><button type="submit" class="btn btn-sm <?= !empty($supplier['is_active']) ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= !empty($supplier['is_active']) ? 'Deactivate' : 'Reactivate' ?></button></form>
                <?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-8">
        <section class="procurement-card h-100">
          <header class="procurement-card-head"><span class="procurement-icon"><i class="bi bi-file-earmark-plus"></i></span><div><h2 class="procurement-heading">Create purchase order</h2><p class="procurement-help">A draft has no stock effect. Send it to the supplier, then receive the delivery.</p></div></header>
          <div class="procurement-card-body">
            <?php if (empty($canManage)): ?><div class="alert alert-info mb-0">Your account has read-only purchasing access.</div>
            <?php elseif (empty(array_filter($suppliers, static fn(array $s): bool => !empty($s['is_active']))) || empty($ingredients)): ?>
              <div class="procurement-empty"><i class="bi bi-clipboard2-x"></i>Add at least one active supplier and ingredient before creating a purchase order.</div>
            <?php else: ?>
              <form method="POST" action="<?= BASE_URL ?>/procurement/orders/create" id="purchaseOrderForm">
                <?= \App\Core\CSRF::formField() ?>
                <div class="row g-3 mb-3">
                  <div class="col-12 col-md-6"><label class="form-label small fw-semibold" for="poSupplier">Supplier *</label><select class="form-select" id="poSupplier" name="supplier_id" required><option value="">Choose supplier...</option><?php foreach ($suppliers as $supplier): if (empty($supplier['is_active'])) continue; ?><option value="<?= (int)$supplier['id'] ?>"><?= $h($supplier['name']) ?></option><?php endforeach; ?></select></div>
                  <div class="col-12 col-md-6"><label class="form-label small fw-semibold" for="poNotes">Notes</label><input class="form-control" id="poNotes" name="notes" maxlength="2000" placeholder="Delivery date, quote reference, etc."></div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-2 mb-2"><h3 class="procurement-heading mb-0">Ingredient lines</h3><button type="button" class="btn btn-sm btn-outline-primary" id="addPurchaseLine"><i class="bi bi-plus-lg me-1"></i> Add line</button></div>
                <div id="purchaseLines">
                  <div class="procurement-line" data-po-line>
                    <div class="procurement-line-grid">
                      <div><label>Ingredient *</label><select class="form-select" name="ingredient_ids[]" data-ingredient-select required><option value="">Choose ingredient...</option><?php foreach ($ingredients as $ingredient): ?><option value="<?= (int)$ingredient['id'] ?>"><?= $h($ingredient['name']) ?> · stock <?= number_format((float)$ingredient['current_stock'], 2) ?> <?= $h($ingredient['base_unit']) ?></option><?php endforeach; ?></select></div>
                      <div><label>Quantity *</label><input class="form-control" name="quantities[]" type="number" min="0.0001" max="1000000" step="0.0001" placeholder="e.g. 5" required></div>
                      <div><label>Purchase unit *</label><select class="form-select" name="purchase_units[]" data-purchase-unit required><option value="">Choose ingredient first</option></select></div>
                      <div><label>Cost / unit (₱) *</label><input class="form-control" name="unit_costs[]" type="number" min="0" max="100000000" step="0.0001" placeholder="0.00" required></div>
                      <div class="procurement-line-remove"><label>&nbsp;</label><button class="btn btn-outline-danger" type="button" data-remove-line title="Remove line" aria-label="Remove line"><i class="bi bi-trash"></i></button></div>
                    </div>
                    <div class="procurement-muted mt-2" data-line-hint>Select an ingredient to see purchase units.</div>
                  </div>
                </div>
                <div class="procurement-total mt-3"><span class="text-secondary small fw-semibold">Estimated purchase total</span><strong id="purchaseOrderEstimate">₱0.00</strong></div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-1"></i> Save draft purchase order</button></div>
              </form>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>

    <section class="procurement-card">
      <header class="procurement-card-head"><span class="procurement-icon"><i class="bi bi-clipboard2-check"></i></span><div><h2 class="procurement-heading">Purchase order history</h2><p class="procurement-help">Draft → Ordered → Received. Receipt updates the inventory ledger and stock balance exactly once.</p></div></header>
      <div class="procurement-card-body">
        <?php if (empty($purchaseOrders)): ?><div class="procurement-empty"><i class="bi bi-clipboard2"></i>No purchase orders yet.</div>
        <?php else: foreach ($purchaseOrders as $po): ?>
          <article class="procurement-order">
            <header class="procurement-order-head">
              <div>
                <div class="procurement-po-number"><?= $h($po['po_number']) ?> <span class="procurement-badge <?= $h($po['status']) ?>"><?= $h(ucfirst((string)$po['status'])) ?></span></div>
                <div class="procurement-muted mt-1"><?= $h($po['supplier_name']) ?> · <?= $h(date('M j, Y g:i A', strtotime((string)$po['created_at']))) ?><?= !empty($po['received_at']) ? ' · Received ' . $h(date('M j, Y g:i A', strtotime((string)$po['received_at']))) : '' ?></div>
                <?php if (!empty($po['notes'])): ?><div class="procurement-muted mt-1"><?= $h($po['notes']) ?></div><?php endif; ?>
              </div>
              <div class="text-end"><div class="procurement-order-total"><?= $money((float)$po['total_amount']) ?></div><div class="procurement-muted"><?= count($po['items']) ?> line(s)</div></div>
            </header>
            <div class="procurement-order-body">
              <?php foreach ($po['items'] as $line): ?>
                <div class="procurement-order-line">
                  <div><div class="procurement-order-line-name"><?= $h($line['ingredient_name']) ?></div><div class="procurement-order-line-detail"><?= number_format((float)$line['quantity_purchased'], 4) ?> <?= $h($line['purchase_unit']) ?> (<?= number_format((float)$line['quantity_base'], 4) ?> <?= $h($line['base_unit']) ?> base) × <?= $money((float)$line['unit_cost']) ?> / purchase unit</div></div>
                  <div class="procurement-order-total"><?= $money((float)$line['subtotal']) ?></div>
                </div>
              <?php endforeach; ?>
              <?php if (!empty($canManage) && in_array($po['status'], ['draft','ordered'], true)): ?>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                  <?php if ($po['status'] === 'draft'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/procurement/orders/action"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="purchase_order_id" value="<?= (int)$po['id'] ?>"><input type="hidden" name="status" value="ordered"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-send me-1"></i> Mark Ordered</button></form>
                  <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/procurement/orders/receive" onsubmit="return confirm('Receive this entire delivery? This will increase ingredient stock and update the inventory ledger. The purchase order cannot be received again.')"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="purchase_order_id" value="<?= (int)$po['id'] ?>"><button class="btn btn-success btn-sm" type="submit"><i class="bi bi-box-arrow-in-down me-1"></i> Receive Delivery</button></form>
                  <?php endif; ?>
                  <form method="POST" action="<?= BASE_URL ?>/procurement/orders/action" onsubmit="return confirm('Cancel this purchase order? No stock will be changed.')"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="purchase_order_id" value="<?= (int)$po['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-outline-danger btn-sm" type="submit">Cancel PO</button></form>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
(() => {
  const unitCatalog = <?= json_encode($unitCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const lines = document.getElementById('purchaseLines');
  const addButton = document.getElementById('addPurchaseLine');
  const estimate = document.getElementById('purchaseOrderEstimate');
  if (!lines || !addButton || !estimate) return;

  const money = value => '₱' + (Number.isFinite(value) ? value : 0).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
  const configureLine = line => {
    const ingredientSelect = line.querySelector('[data-ingredient-select]');
    const unitSelect = line.querySelector('[data-purchase-unit]');
    const hint = line.querySelector('[data-line-hint]');
    if (!ingredientSelect || !unitSelect) return;
    const updateUnits = () => {
      const ingredientId = ingredientSelect.value;
      const units = unitCatalog[ingredientId] || [];
      unitSelect.replaceChildren();
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = units.length ? 'Choose unit...' : 'No purchase units';
      unitSelect.appendChild(placeholder);
      units.forEach(unit => {
        const option = document.createElement('option');
        option.value = unit.name;
        option.textContent = unit.name + (Number(unit.factor) !== 1 ? ' (×' + Number(unit.factor).toLocaleString() + ' base)' : ' (base)');
        unitSelect.appendChild(option);
      });
      const chosen = units.find(unit => unit.name.toLowerCase() === (ingredientSelect.options[ingredientSelect.selectedIndex]?.dataset.baseUnit || '').toLowerCase());
      if (units.length === 1) unitSelect.value = units[0].name;
      if (hint) hint.textContent = ingredientId && units.length ? 'Quantity is entered in the chosen purchase unit; inventory is normalized to base units when received.' : 'Select an ingredient to see its configured purchase units.';
      updateEstimate();
    };
    ingredientSelect.addEventListener('change', updateUnits);
    line.querySelectorAll('input[name="quantities[]"],input[name="unit_costs[]"]').forEach(input => input.addEventListener('input', updateEstimate));
    line.querySelector('[data-remove-line]')?.addEventListener('click', () => {
      if (lines.querySelectorAll('[data-po-line]').length <= 1) {
        ingredientSelect.value = '';
        unitSelect.replaceChildren(new Option('Choose ingredient first', ''));
        line.querySelector('input[name="quantities[]"]').value = '';
        line.querySelector('input[name="unit_costs[]"]').value = '';
        updateEstimate();
        return;
      }
      line.remove();
      updateEstimate();
    });
    updateUnits();
  };
  const updateEstimate = () => {
    let total = 0;
    lines.querySelectorAll('[data-po-line]').forEach(line => {
      const quantity = Number(line.querySelector('input[name="quantities[]"]')?.value || 0);
      const cost = Number(line.querySelector('input[name="unit_costs[]"]')?.value || 0);
      if (quantity > 0 && cost >= 0 && Number.isFinite(quantity * cost)) total += Math.round(quantity * cost * 100) / 100;
    });
    estimate.textContent = money(total);
  };

  lines.querySelectorAll('[data-po-line]').forEach(configureLine);
  addButton.addEventListener('click', () => {
    if (lines.querySelectorAll('[data-po-line]').length >= 100) return;
    const clone = lines.querySelector('[data-po-line]').cloneNode(true);
    clone.querySelectorAll('select').forEach(select => { select.selectedIndex = 0; });
    clone.querySelectorAll('input').forEach(input => { input.value = ''; });
    const unit = clone.querySelector('[data-purchase-unit]');
    if (unit) unit.replaceChildren(new Option('Choose ingredient first', ''));
    lines.appendChild(clone);
    configureLine(clone);
  });
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
