<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<?php
$movementLabels = [
    'purchase_receipt' => 'Stock In / Purchase',
    'order_sale' => 'POS Sale',
    'order_cancellation' => 'Order Cancellation',
    'order_refund' => 'Refund Reversal',
    'manual_adjustment' => 'Manual Adjustment',
    'spoilage_waste' => 'Waste / Spoilage'
];
$movementBadges = [
    'purchase_receipt' => 'stock-in',
    'order_sale' => 'sale',
    'order_cancellation' => 'reversal',
    'order_refund' => 'reversal',
    'manual_adjustment' => 'adjustment',
    'spoilage_waste' => 'waste'
];
?>

<style>
.inventory-ledger-page {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 30px clamp(18px, 2vw, 32px) 42px;
    color: #172033;
}
.inventory-ledger-page,
.inventory-ledger-page * { box-sizing: border-box; }
.ledger-heading {
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:20px; margin-bottom:24px;
}
.ledger-eyebrow {
    display:flex; align-items:center; gap:8px; margin-bottom:7px;
    color:#64748b; font-size:.75rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase;
}
.ledger-heading h1 { margin:0; color:#172033; font-size:clamp(1.45rem,2vw,1.9rem); font-weight:800; letter-spacing:-.04em; }
.ledger-heading p { margin:7px 0 0; color:#718096; font-size:.92rem; }
.ledger-back {
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    min-height:42px; padding:0 15px; border:1px solid #dbe3ee; border-radius:10px;
    background:#fff; color:#475569; font-size:.84rem; font-weight:700; text-decoration:none;
    white-space:nowrap; box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.ledger-back:hover { color:#1d4ed8; border-color:#b9cdf2; background:#f8fbff; }
.ledger-filter-card,.ledger-records-card {
    overflow:hidden; border:1px solid #e5eaf1; border-radius:15px;
    background:#fff; box-shadow:0 5px 18px rgba(15,23,42,.045);
}
.ledger-filter-card { padding:20px; margin-bottom:22px; }
.ledger-filter-top { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; }
.ledger-filter-top h2 { margin:0; font-size:.98rem; font-weight:800; color:#253248; }
.ledger-filter-top span { color:#8995a7; font-size:.78rem; }
.ledger-filter-grid {
    display:grid; grid-template-columns:minmax(0,1.25fr) minmax(0,1fr) auto;
    align-items:end; gap:16px;
}
.ledger-field { min-width:0; }
.ledger-field label { display:block; margin:0 0 7px; color:#536176; font-size:.78rem; font-weight:750; }
.ledger-field select {
    display:block; width:100%; min-width:0; height:46px; padding:0 38px 0 13px;
    border:1px solid #d9e1ec; border-radius:9px; background-color:#fff;
    color:#253248; font-size:.88rem; box-shadow:none;
}
.ledger-field select:focus { border-color:#75a7f7; outline:3px solid rgba(37,99,235,.12); }
.ledger-filter-actions { display:flex; align-items:center; gap:9px; }
.ledger-filter-actions button,.ledger-filter-actions a {
    display:inline-flex; align-items:center; justify-content:center; gap:7px;
    min-height:46px; padding:0 18px; border-radius:9px; font-size:.84rem; font-weight:750;
    text-decoration:none; white-space:nowrap; cursor:pointer;
}
.ledger-apply { border:1px solid #1764d8; background:#1764d8; color:#fff; }
.ledger-apply:hover { background:#1455b8; border-color:#1455b8; }
.ledger-reset { border:1px solid #dbe3ee; background:#fff; color:#526176; }
.ledger-reset:hover { background:#f7f9fc; color:#1d4ed8; }
.ledger-records-head {
    display:flex; justify-content:space-between; align-items:center; gap:12px;
    padding:18px 21px; border-bottom:1px solid #e8edf3;
}
.ledger-records-title { display:flex; align-items:center; gap:10px; color:#263449; font-size:1rem; font-weight:800; }
.ledger-records-title i { color:#2563eb; font-size:1.1rem; }
.ledger-count { padding:5px 10px; border-radius:999px; background:#eff5ff; color:#2459a6; font-size:.74rem; font-weight:800; white-space:nowrap; }
.ledger-table-wrap { width:100%; overflow-x:auto; }
.ledger-table { width:100%; min-width:1180px; margin:0; border-collapse:separate; border-spacing:0; }
.ledger-table thead th {
    padding:13px 14px; border-bottom:1px solid #e1e7ef; background:#f7f9fc;
    color:#718096; font-size:.68rem; font-weight:850; letter-spacing:.055em;
    text-transform:uppercase; white-space:nowrap;
}
.ledger-table tbody td {
    padding:15px 14px; border-bottom:1px solid #edf1f5; color:#435168;
    font-size:.79rem; vertical-align:middle;
}
.ledger-table tbody tr:last-child td { border-bottom:0; }
.ledger-table tbody tr:hover { background:#f9fbfe; }
.ledger-table .ledger-date { color:#64748b; font-variant-numeric:tabular-nums; white-space:nowrap; }
.ledger-ingredient-name { color:#253248; font-size:.84rem; font-weight:800; }
.ledger-subtext { margin-top:4px; color:#8995a7; font-size:.72rem; line-height:1.45; }
.ledger-type {
    display:inline-flex; align-items:center; padding:6px 9px; border-radius:7px;
    font-size:.7rem; font-weight:800; white-space:nowrap;
}
.ledger-type.stock-in { background:#e8f7ef; color:#18794e; }
.ledger-type.sale { background:#eaf2ff; color:#1d5fc2; }
.ledger-type.reversal { background:#e9f7fa; color:#08758b; }
.ledger-type.adjustment { background:#fff5df; color:#9a6500; }
.ledger-type.waste { background:#fff0ef; color:#c13c35; }
.ledger-quantity { font-weight:850; font-variant-numeric:tabular-nums; white-space:nowrap; }
.ledger-quantity.positive { color:#16834d; }
.ledger-quantity.negative { color:#d33b42; }
.ledger-stock { color:#3e4c61; font-variant-numeric:tabular-nums; white-space:nowrap; }
.ledger-stock.after { color:#253248; font-weight:800; }
.ledger-reference { min-width:210px; max-width:310px; line-height:1.45; overflow-wrap:anywhere; }
.ledger-user { color:#64748b; white-space:nowrap; }
.ledger-empty { padding:54px 20px !important; text-align:center; color:#8793a5 !important; }
.ledger-footnote { margin:12px 2px 0; color:#8995a7; font-size:.75rem; line-height:1.5; }
@media (max-width: 900px) {
    .ledger-filter-grid { grid-template-columns:1fr 1fr; }
    .ledger-filter-actions { grid-column:1 / -1; }
    .ledger-filter-actions button,.ledger-filter-actions a { flex:1; }
}
@media (max-width: 575px) {
    .inventory-ledger-page { padding:20px 13px 30px; }
    .ledger-heading { flex-direction:column; align-items:stretch; gap:14px; }
    .ledger-back { align-self:flex-start; }
    .ledger-filter-card { padding:15px; }
    .ledger-filter-grid { grid-template-columns:1fr; gap:13px; }
    .ledger-filter-actions { grid-column:auto; }
    .ledger-filter-top span { display:none; }
    .ledger-records-head { padding:15px; }
}
</style>

<main class="inventory-ledger-page">
  <header class="ledger-heading">
    <div>
      <div class="ledger-eyebrow"><i class="bi bi-box-seam"></i> Inventory management</div>
      <h1>Inventory Movements Ledger</h1>
      <p>Track every stock receipt, sale, cancellation, refund, adjustment, and wastage in one place.</p>
    </div>
    <a href="<?= BASE_URL ?>/inventory" class="ledger-back"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
  </header>

  <section class="ledger-filter-card" aria-labelledby="ledgerFilterTitle">
    <div class="ledger-filter-top">
      <h2 id="ledgerFilterTitle"><i class="bi bi-funnel me-2"></i>Filter movement records</h2>
      <span>Choose one or both filters, then apply</span>
    </div>
    <form method="GET" action="<?= BASE_URL ?>/inventory/ledger">
      <div class="ledger-filter-grid">
        <div class="ledger-field">
          <label for="ingredient_id">Ingredient</label>
          <select id="ingredient_id" name="ingredient_id">
            <option value="">All ingredients</option>
            <?php foreach ($ingredients as $ing): ?>
              <option value="<?= (int)$ing['id'] ?>" <?= $selectedIng === (int)$ing['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ing['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ledger-field">
          <label for="type">Movement type</label>
          <select id="type" name="type">
            <option value="">All movement types</option>
            <?php foreach ($movementLabels as $type => $label): ?>
              <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ledger-filter-actions">
          <button type="submit" class="ledger-apply"><i class="bi bi-funnel"></i> Apply filters</button>
          <a href="<?= BASE_URL ?>/inventory/ledger" class="ledger-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        </div>
      </div>
    </form>
  </section>

  <section class="ledger-records-card" aria-labelledby="ledgerRecordsTitle">
    <div class="ledger-records-head">
      <div class="ledger-records-title" id="ledgerRecordsTitle"><i class="bi bi-list-check"></i> Movement records</div>
      <span class="ledger-count"><?= count($movements) ?> <?= count($movements) === 1 ? 'record' : 'records' ?></span>
    </div>
    <div class="ledger-table-wrap">
      <table class="ledger-table">
        <thead>
          <tr>
            <th>Date &amp; time</th>
            <th>Ingredient</th>
            <th>Movement</th>
            <th class="text-end">Change</th>
            <th class="text-end">Stock before</th>
            <th class="text-end">Stock after</th>
            <th>Reference / notes</th>
            <th>Recorded by</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements)): ?>
            <tr><td colspan="8" class="ledger-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No inventory movements match these filters.</td></tr>
          <?php else: ?>
            <?php foreach ($movements as $m):
              $quantityChange = (float)$m['quantity_change'];
              $isAddition = $quantityChange > 0;
              $movementType = (string)$m['reference_type'];
              $badgeClass = $movementBadges[$movementType] ?? 'reversal';
            ?>
              <tr>
                <td class="ledger-date"><?= htmlspecialchars($m['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="ledger-ingredient-name"><?= htmlspecialchars($m['ingredient_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="ledger-subtext">Current cost: ₱<?= number_format((float)$m['current_cost_per_base_unit'], 4) ?>/<?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td><span class="ledger-type <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($movementLabels[$movementType] ?? $movementType, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="text-end"><span class="ledger-quantity <?= $isAddition ? 'positive' : 'negative' ?>"><?= $isAddition ? '+' : '' ?><?= number_format($quantityChange, 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="text-end"><span class="ledger-stock"><?= number_format((float)$m['stock_before'], 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="text-end"><span class="ledger-stock after"><?= number_format((float)$m['stock_after'], 4) ?> <?= htmlspecialchars($m['base_unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="ledger-reference">
                  <?php if (!empty($m['reference_id'])): ?>
                    <div class="fw-semibold text-dark">Reference #<?= (int)$m['reference_id'] ?></div>
                  <?php endif; ?>
                  <div class="ledger-subtext"><?= htmlspecialchars($m['notes'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="ledger-user"><?= htmlspecialchars($m['logged_by'] ?? 'Unknown user', ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <p class="ledger-footnote">Quantities use the ingredient's base unit. The displayed unit cost is the ingredient's current cost, not a historical cost snapshot for each movement.</p>
</main>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
