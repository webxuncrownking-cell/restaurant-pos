<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$money = static fn(float $value): string => '₱' . number_format($value, 2);
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<style>
  .expiry-page { padding:24px; }
  .expiry-hero { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:22px; }
  .expiry-eyebrow { color:#8794a7; text-transform:uppercase; letter-spacing:.12em; font-size:.67rem; font-weight:850; }
  .expiry-title { color:#172033; font-size:1.65rem; letter-spacing:-.04em; font-weight:850; margin:4px 0 6px; }
  .expiry-subtitle { color:#718096; margin:0; font-size:.88rem; }
  .expiry-card { background:#fff; border:1px solid #e3eaf2; border-radius:16px; box-shadow:0 3px 14px rgba(15,23,42,.03); overflow:hidden; }
  .expiry-card-head { display:flex; align-items:center; gap:12px; padding:17px 20px; border-bottom:1px solid #edf1f6; }
  .expiry-card-body { padding:18px 20px; }
  .expiry-section-title { margin:0 0 3px; font-size:.98rem; color:#172033; font-weight:850; }
  .expiry-help { margin:0; font-size:.74rem; color:#8794a7; }
  .expiry-icon { display:grid; place-items:center; width:40px; height:40px; flex:0 0 40px; margin:0; padding:0; border-radius:12px; color:#2563eb; background:#eff6ff; line-height:1; font-size:1.05rem; }
  .expiry-icon > .bi { display:block; line-height:1; font-size:1.05rem; }
  .expiry-metric { display:flex; align-items:center; gap:12px; height:100%; padding:14px 16px; border:1px solid #e3eaf2; border-radius:14px; background:#fff; }
  .expiry-metric strong { display:block; font-size:1.36rem; line-height:1.15; color:#172033; overflow-wrap:anywhere; }
  .expiry-metric-label { display:block; margin-top:5px; font-size:.73rem; color:#718096; }
  .expiry-metric.danger { background:#fff7f7; border-color:#fbd5d5; }
  .expiry-metric.danger .expiry-icon { color:#b91c1c; background:#fee2e2; }
  .expiry-metric.warning { background:#fffbeb; border-color:#fde68a; }
  .expiry-metric.warning .expiry-icon { color:#92400e; background:#fef3c7; }
  .expiry-badge { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; border-radius:999px; white-space:nowrap; font-size:.67rem; line-height:1; font-weight:850; }
  .expiry-badge.expired { color:#b91c1c; background:#fee2e2; }
  .expiry-badge.expiring { color:#92400e; background:#fef3c7; }
  .expiry-badge.safe { color:#047857; background:#dcfce7; }
  .expiry-badge.no-expiry { color:#475569; background:#f1f5f9; }
  .expiry-batch-name { color:#263248; font-size:.82rem; font-weight:850; }
  .expiry-subtext { font-size:.71rem; color:#8794a7; margin-top:3px; }
  .expiry-waste-form { min-width:250px; max-width:310px; padding:10px; border:1px solid #e4eaf2; border-radius:11px; background:#fbfcfe; }
  .expiry-waste-form .form-control,.expiry-waste-form .form-select { min-height:34px; padding-top:5px; padding-bottom:5px; font-size:.75rem; }
  .expiry-waste-form .form-label { margin-bottom:4px; font-size:.67rem; font-weight:800; color:#64748b; }
  .expiry-empty { padding:36px 20px; text-align:center; color:#718096; font-size:.82rem; }
  .expiry-empty > .bi { display:block; margin-bottom:8px; color:#a4b0c0; font-size:1.8rem; }
  .expiry-value { font-weight:800; white-space:nowrap; color:#263248; }
  .expiry-table th { white-space:nowrap; }
  @media(max-width:900px) { .expiry-page { padding:16px; } .expiry-card-body { padding:14px; } .expiry-waste-form { min-width:220px; } }
</style>

<div class="expiry-page">
  <?php if (!empty($flashes)): foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= $h(in_array(($flash['type'] ?? 'info'), ['success','warning','danger','info'], true) ? $flash['type'] : 'info') ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
      <?= $h($flash['message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; endif; ?>

  <section class="expiry-hero">
    <div>
      <div class="expiry-eyebrow">Inventory &amp; food safety</div>
      <h1 class="expiry-title"><i class="bi bi-calendar2-x text-primary me-2"></i>Expiry &amp; Wastage Tracking</h1>
      <p class="expiry-subtitle">Monitor expiry dates by received batch, consume stock by earliest expiry first, and record the cost of discarded ingredients.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/inventory" class="btn btn-outline-secondary"><i class="bi bi-boxes"></i> Inventory</a>
      <a href="<?= BASE_URL ?>/procurement" class="btn btn-outline-secondary"><i class="bi bi-truck"></i> Suppliers &amp; POs</a>
      <a href="<?= BASE_URL ?>/inventory/ledger?type=spoilage_waste" class="btn btn-outline-secondary"><i class="bi bi-journal-text"></i> Waste Ledger</a>
    </div>
  </section>

  <?php if (empty($schema_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3" role="alert">
      <div class="d-flex align-items-start gap-3">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div><strong class="d-block mb-1">One-time expiry tracking setup required</strong><div class="small">This adds batch and wastage audit tables. Existing ingredient stock, purchase orders, and sales will be preserved. Expiry dates start tracking for deliveries received after setup.</div></div>
      </div>
      <?php if (!empty($canMigrate)): ?>
        <form method="POST" action="<?= BASE_URL ?>/expiry/migrate">
          <?= \App\Core\CSRF::formField() ?>
          <button class="btn btn-warning fw-semibold" type="submit"><i class="bi bi-database-gear"></i> Apply Expiry Tracking Setup</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3"><div class="expiry-metric danger"><span class="expiry-icon"><i class="bi bi-exclamation-octagon"></i></span><div><strong><?= (int)$summary['expired_batches'] ?></strong><span class="expiry-metric-label">Expired batches with stock</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="expiry-metric warning"><span class="expiry-icon"><i class="bi bi-hourglass-split"></i></span><div><strong><?= (int)$summary['expiring_batches'] ?></strong><span class="expiry-metric-label">Expiring in the next 30 days</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="expiry-metric danger"><span class="expiry-icon"><i class="bi bi-currency-dollar"></i></span><div><strong><?= $money((float)$summary['expired_value']) ?></strong><span class="expiry-metric-label">Estimated value of expired stock</span></div></div></div>
      <div class="col-6 col-xl-3"><div class="expiry-metric"><span class="expiry-icon"><i class="bi bi-recycle"></i></span><div><strong><?= $money((float)$summary['month_waste_cost']) ?></strong><span class="expiry-metric-label">Wastage cost this month · <?= (int)$summary['month_waste_count'] ?> record(s)</span></div></div></div>
    </div>

    <section class="expiry-card mb-4">
      <header class="expiry-card-head">
        <span class="expiry-icon"><i class="bi bi-box-seam"></i></span>
        <div><h2 class="expiry-section-title">Ingredient batches &amp; expiry alerts</h2><p class="expiry-help">Batches with earlier expiry dates are used first when tracked ingredient stock is consumed.</p></div>
      </header>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 expiry-table">
          <thead class="table-light"><tr><th class="ps-3">Ingredient / Batch</th><th>Received</th><th>Remaining</th><th>Expiry</th><th>Remaining value</th><th class="pe-3">Record wastage</th></tr></thead>
          <tbody>
          <?php if (empty($batches)): ?>
            <tr><td colspan="6"><div class="expiry-empty"><i class="bi bi-box2-heart"></i><strong class="d-block text-dark mb-1">No tracked batches yet</strong>Apply the setup, then enter lot codes and expiry dates when receiving new purchase orders.</div></td></tr>
          <?php else: foreach ($batches as $batch): ?>
            <tr>
              <td class="ps-3">
                <div class="expiry-batch-name"><?= $h($batch['ingredient_name']) ?></div>
                <div class="expiry-subtext">SKU <?= $h($batch['sku']) ?> · <?= $h($batch['base_unit']) ?></div>
                <div class="expiry-subtext"><?= $h($batch['batch_code'] ?: ('Batch #' . $batch['id'])) ?><?= !empty($batch['po_number']) ? ' · ' . $h($batch['po_number']) : '' ?></div>
                <?php if (!empty($batch['supplier_name'])): ?><div class="expiry-subtext"><?= $h($batch['supplier_name']) ?></div><?php endif; ?>
              </td>
              <td><?= number_format((float)$batch['received_quantity'], 4) ?><div class="expiry-subtext"><?= $h(date('M j, Y', strtotime((string)$batch['received_at']))) ?></div></td>
              <td><strong><?= number_format((float)$batch['remaining_quantity'], 4) ?></strong><div class="expiry-subtext"><?= $h($batch['base_unit']) ?></div></td>
              <td>
                <?php if (empty($batch['expiry_date'])): ?>
                  <span class="expiry-badge no-expiry">No expiry date</span>
                <?php elseif ($batch['expiry_status'] === 'expired'): ?>
                  <span class="expiry-badge expired"><i class="bi bi-exclamation-circle"></i> Expired</span>
                  <div class="expiry-subtext"><?= $h(date('M j, Y', strtotime((string)$batch['expiry_date']))) ?></div>
                <?php elseif ($batch['expiry_status'] === 'expiring'): ?>
                  <span class="expiry-badge expiring"><i class="bi bi-hourglass-split"></i> <?= (int)$batch['days_until_expiry'] === 0 ? 'Expires today' : (int)$batch['days_until_expiry'] . ' days left' ?></span>
                  <div class="expiry-subtext"><?= $h(date('M j, Y', strtotime((string)$batch['expiry_date']))) ?></div>
                <?php else: ?>
                  <span class="expiry-badge safe">In date</span>
                  <div class="expiry-subtext"><?= $h(date('M j, Y', strtotime((string)$batch['expiry_date']))) ?></div>
                <?php endif; ?>
              </td>
              <td class="expiry-value"><?= $money((float)$batch['remaining_value']) ?></td>
              <td class="pe-3">
                <?php if (!empty($canManage)): ?>
                  <form method="POST" action="<?= BASE_URL ?>/expiry/waste" class="expiry-waste-form">
                    <?= \App\Core\CSRF::formField() ?>
                    <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                    <div class="row g-2">
                      <div class="col-5"><label class="form-label">Quantity (<?= $h($batch['base_unit']) ?>)</label><input class="form-control" type="number" name="quantity" min="0.0001" max="<?= number_format((float)$batch['remaining_quantity'],4,'.','') ?>" step="0.0001" required></div>
                      <div class="col-7"><label class="form-label">Reason</label><select class="form-select" name="reason" required><option value="">Choose...</option><option value="expired">Expired</option><option value="spoiled">Spoiled</option><option value="damaged">Damaged</option><option value="contaminated">Contaminated</option><option value="other">Other</option></select></div>
                      <div class="col-12"><label class="form-label">Notes (optional)</label><input class="form-control" name="notes" maxlength="255" placeholder="e.g. damaged packaging"></div>
                      <div class="col-12"><button class="btn btn-outline-danger btn-sm w-100" type="submit" onclick="return confirm('Record this ingredient waste and deduct it from both batch and stock?')"><i class="bi bi-trash3"></i> Record wastage</button></div>
                    </div>
                  </form>
                <?php else: ?>
                  <span class="text-secondary small">Read only</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="expiry-card">
      <header class="expiry-card-head">
        <span class="expiry-icon"><i class="bi bi-journal-check"></i></span>
        <div><h2 class="expiry-section-title">Recent wastage records</h2><p class="expiry-help">Wastage cost is recorded from the batch's captured unit cost.</p></div>
      </header>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th class="ps-3">Date</th><th>Ingredient / Batch</th><th>Reason</th><th>Quantity</th><th>Cost</th><th>Recorded by</th></tr></thead>
          <tbody>
          <?php if (empty($wasteRecords)): ?>
            <tr><td colspan="6"><div class="expiry-empty"><i class="bi bi-journal"></i>No wastage has been recorded yet.</div></td></tr>
          <?php else: foreach ($wasteRecords as $waste): ?>
            <tr>
              <td class="ps-3"><?= $h(date('M j, Y g:i A', strtotime((string)$waste['created_at']))) ?></td>
              <td><div class="expiry-batch-name"><?= $h($waste['ingredient_name']) ?></div><div class="expiry-subtext"><?= $h($waste['batch_code'] ?: ('Batch #' . $waste['batch_id'])) ?></div></td>
              <td><span class="expiry-badge <?= $waste['reason'] === 'expired' ? 'expired' : 'no-expiry' ?>"><?= $h(ucfirst((string)$waste['reason'])) ?></span><?php if (!empty($waste['notes'])): ?><div class="expiry-subtext"><?= $h($waste['notes']) ?></div><?php endif; ?></td>
              <td><?= number_format((float)$waste['quantity_base'], 4) ?> <?= $h($waste['base_unit']) ?></td>
              <td class="expiry-value"><?= $money((float)$waste['total_cost']) ?></td>
              <td><?= $h($waste['recorded_by_name']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
