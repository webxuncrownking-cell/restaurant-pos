<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$money = static fn(float $amount): string => '₱' . number_format($amount, 2);
?>
<style>
  .bills-page { padding:24px; }
  .bills-hero { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:22px; }
  .bills-eyebrow { color:#8794a7; text-transform:uppercase; letter-spacing:.12em; font-size:.67rem; font-weight:850; }
  .bills-title { color:#172033; font-size:1.65rem; letter-spacing:-.04em; font-weight:850; margin:4px 0 6px; }
  .bills-subtitle { color:#718096; margin:0; font-size:.88rem; }
  .bill-card { border:1px solid #e2e8f0; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 4px 14px rgba(15,23,42,.035); }
  .bill-card + .bill-card { margin-top:18px; }
  .bill-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; padding:18px 20px; border-bottom:1px solid #edf1f6; background:#fcfdff; }
  .bill-order-number { color:#172033; font-weight:850; font-size:1rem; overflow-wrap:anywhere; }
  .bill-meta { display:flex; flex-wrap:wrap; gap:7px; margin-top:8px; }
  .bill-chip { display:inline-flex; align-items:center; gap:5px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; padding:4px 8px; font-size:.7rem; color:#64748b; font-weight:700; }
  .bill-status { display:inline-flex; border-radius:999px; padding:5px 9px; background:#fff4cf; color:#92400e; font-size:.7rem; font-weight:850; white-space:nowrap; }
  .bill-status.partial { background:#eaf2ff; color:#1d4ed8; }
  .bill-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; padding:15px 20px; border-bottom:1px solid #edf1f6; }
  .bill-summary-box { padding:11px 13px; border:1px solid #e8edf4; background:#fff; border-radius:11px; min-width:0; }
  .bill-summary-box span { color:#7c899b; display:block; font-size:.7rem; font-weight:750; }
  .bill-summary-box strong { color:#172033; display:block; font-size:1.1rem; margin-top:5px; overflow-wrap:anywhere; }
  .bill-summary-box.due { background:#fff9eb; border-color:#f5e5bc; }
  .bill-summary-box.due strong { color:#92400e; }
  .bill-body { padding:18px 20px 20px; }
  .bill-section-title { margin:0 0 12px; color:#243047; font-size:.86rem; font-weight:850; }
  .bill-item-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:start; padding:10px 0; border-bottom:1px dashed #e8edf4; }
  .bill-item-row:last-child { border-bottom:0; }
  .bill-item-name { color:#263248; font-size:.82rem; font-weight:750; }
  .bill-item-meta { color:#8794a7; font-size:.72rem; margin-top:3px; }
  .bill-item-price { text-align:right; font-size:.82rem; font-weight:800; color:#344256; white-space:nowrap; }
  .bill-payment-panel { border:1px solid #dfe8f5; border-radius:12px; background:#f8fbff; padding:15px; }
  .bill-payment-panel + .bill-payment-panel { margin-top:10px; }
  .bill-splits-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
  .bill-share { border:1px solid #e2e8f0; border-radius:13px; padding:14px; background:#fff; min-width:0; }
  .bill-share.paid { border-color:#b7edd0; background:#f6fdf9; }
  .bill-share-head { display:flex; justify-content:space-between; align-items:start; gap:10px; margin-bottom:10px; }
  .bill-share-label { font-size:.85rem; font-weight:850; color:#263248; overflow-wrap:anywhere; }
  .bill-share-balance { color:#92400e; font-size:1rem; font-weight:850; white-space:nowrap; }
  .bill-share.paid .bill-share-balance { color:#047857; }
  .bill-empty { padding:52px 18px; border:1px dashed #cbd5e1; border-radius:16px; background:#fff; text-align:center; }
  .bill-empty i { font-size:2rem; color:#a4b0c0; display:block; margin-bottom:10px; }
  .bill-empty h2 { color:#243047; font-weight:850; font-size:1rem; }
  .bill-empty p { color:#718096; margin:0; font-size:.84rem; }
  .bill-label-help { color:#8794a7; font-size:.72rem; margin-top:5px; }

  .bills-filter-panel {
    background:var(--bs-body-bg,#fff);
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:18px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(15,23,42,.035);
  }
  .bills-filter-panel .form-label {
    color:#64748b;
    font-size:.78rem;
    font-weight:750;
    margin-bottom:6px;
  }
  .bills-filter-panel .form-control,
  .bills-filter-panel .form-select {
    min-height:44px;
    border-radius:9px;
  }
  .bills-filter-actions {
    display:flex;
    align-items:end;
    gap:8px;
  }
  .bills-pagination {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:center;
    gap:6px;
    margin:22px 0;
  }
  .bills-pagination a,
  .bills-pagination span {
    display:inline-flex;
    min-width:40px;
    min-height:40px;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border:1px solid #e2e8f0;
    border-radius:9px;
    background:#fff;
    color:#475569;
    text-decoration:none;
    font-size:.85rem;
    font-weight:750;
  }
  .bills-pagination a.btn-primary {
    background:#2563eb;
    border-color:#2563eb;
    color:#fff;
  }
  .bills-pagination .disabled {
    opacity:.45;
    pointer-events:none;
  }
  .bills-results-meta {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin:0 0 14px;
    color:#718096;
    font-size:.84rem;
  }

  @media(max-width:720px) {
    .bills-page { padding:15px; }
    .bill-card-head,.bill-body { padding:15px; }
    .bill-summary { padding:12px; gap:7px; }
    .bill-summary-box { padding:10px; }
    .bill-summary-box strong { font-size:.95rem; }
    .bill-splits-grid { grid-template-columns:1fr; }
  }

  /* Billing readability pass: make balances, payment state, and actions clearer. */
  .bills-subtitle { font-size: .96rem; line-height: 1.5; }
  .bills-eyebrow { font-size: .74rem; }
  .bill-order-number { font-size: 1.12rem; }
  .bill-meta { font-size: .9rem; line-height: 1.5; }
  .bill-chip, .bill-status { font-size: .84rem; }
  .bill-summary { font-size: .96rem; }
  .bill-summary-box { padding: 13px; }
  .bill-summary-box .amount, .bill-summary-box strong { font-size: 1.12rem; }
  .bill-section-title { font-size: 1rem; }
  .bill-item-name { font-size: .98rem; line-height: 1.4; }
  .bill-item-meta { font-size: .84rem; line-height: 1.45; }
  .bill-item-price { font-size: .96rem; font-weight: 750; }
  .bill-payment-panel .form-control, .bill-payment-panel .form-select { min-height: 46px; font-size: 1rem; }
  .bill-payment-panel .btn { min-height: 44px; font-size: .94rem; }
  .bill-label-help { font-size: .84rem; line-height: 1.45; }
  .bill-payment-panel :focus-visible { outline: 3px solid rgba(37,99,235,.65); outline-offset: 2px; }
  @media (max-width: 767.98px) {
    .bills-page { padding: 14px; }
    .bills-title { font-size: 1.5rem; }
    .bill-card { border-radius: 14px; }
    .bill-card-head { gap: 10px; }
    .bill-payment-panel .btn { width: 100%; }
  }

</style>

<div class="bills-page">
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="bills-hero">
    <div>
      <div class="bills-eyebrow">Cashier operations</div>
      <h1 class="bills-title"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Open Bills &amp; Split Payments</h1>
      <p class="bills-subtitle">Collect partial payments, split a dine-in bill into guest shares, and track each remaining balance.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>/pos" class="btn btn-primary"><i class="bi bi-display me-1"></i> POS Terminal</a>
      <a href="<?= BASE_URL ?>/tables" class="btn btn-outline-secondary"><i class="bi bi-grid-3x3-gap me-1"></i> Floor Plan</a>
    </div>
  </section>
<?php if (!empty($schema_ready)): ?>
  <form method="GET" action="<?= BASE_URL ?>/bills" class="bills-filter-panel">
    <div class="row g-3 align-items-end">
      <div class="col-12 col-lg-4">
        <label class="form-label" for="bill-search">Search open bills</label>
        <div class="input-group">
          <span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" id="bill-search" name="search"
            value="<?= htmlspecialchars((string)($filters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Order number, table, cashier...">
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label" for="bill-payment-status">Payment status</label>
        <select class="form-select" id="bill-payment-status" name="payment_status">
          <option value="">All open bills</option>
          <option value="unpaid" <?= ($filters['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
          <option value="partially_paid" <?= ($filters['payment_status'] ?? '') === 'partially_paid' ? 'selected' : '' ?>>Partially paid</option>
        </select>
      </div>
      <div class="col-6 col-sm-3 col-lg-2">
        <label class="form-label" for="bill-date-from">From date</label>
        <input type="date" class="form-control" id="bill-date-from" name="date_from"
          value="<?= htmlspecialchars((string)($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6 col-sm-3 col-lg-2">
        <label class="form-label" for="bill-date-to">To date</label>
        <input type="date" class="form-control" id="bill-date-to" name="date_to"
          value="<?= htmlspecialchars((string)($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-lg-2">
        <div class="bills-filter-actions">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-search me-1"></i> Search
          </button>
          <a href="<?= BASE_URL ?>/bills" class="btn btn-outline-secondary"
             title="Clear filters" aria-label="Clear filters">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>


  <?php if (empty($schema_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3" role="alert">
      <div class="d-flex align-items-start gap-3">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>
          <strong class="d-block mb-1">One-time open-bills update required</strong>
          <div class="small">This adds bill-share and payment-request tracking tables and one nullable payment identifier. Existing restaurant, order, and payment records will be preserved.</div>
        </div>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/bills/migrate">
        <?= \App\Core\CSRF::formField() ?>
        <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-database-gear me-1"></i> Apply Open Bills Update</button>
      </form>
    </div>
  <?php else: ?>
    <?php
      $totalBills = (int)($pagination['total'] ?? 0);
      $currentPage = (int)($pagination['page'] ?? 1);
      $perPage = (int)($pagination['per_page'] ?? 20);
      $totalPages = (int)($pagination['total_pages'] ?? 1);
      $firstBill = $totalBills > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
      $lastBill = min($totalBills, $currentPage * $perPage);

      $billPageFilters = [
        'search' => (string)($filters['search'] ?? ''),
        'payment_status' => (string)($filters['payment_status'] ?? ''),
        'date_from' => (string)($filters['date_from'] ?? ''),
        'date_to' => (string)($filters['date_to'] ?? ''),
      ];

      $billPageUrl = static function (int $targetPage) use ($billPageFilters): string {
        return BASE_URL . '/bills?' . http_build_query(
          array_merge($billPageFilters, ['page' => $targetPage])
        );
      };

      $startPage = max(1, $currentPage - 2);
      $endPage = min($totalPages, $currentPage + 2);

      $hasBillFilters = (
        $billPageFilters['search'] !== '' ||
        $billPageFilters['payment_status'] !== '' ||
        $billPageFilters['date_from'] !== '' ||
        $billPageFilters['date_to'] !== ''
      );
    ?>
    <div class="bills-results-meta">
      Showing <?= $firstBill ?>–<?= $lastBill ?> of <?= $totalBills ?> open bills
    </div>

    <?php if (empty($bills)): ?>
      <div class="bill-empty">
        <i class="bi bi-<?= $hasBillFilters ? 'search' : 'check2-circle' ?>"></i>
        <h2><?= $hasBillFilters ? 'No bills match your filters' : 'No outstanding bills' ?></h2>
        <p><?= $hasBillFilters
          ? 'Try changing your search or filters to find matching open bills.'
          : 'Use “Send to Kitchen / Save Open Bill” in the POS to create a dine-in order that can be paid later or split among guests.' ?></p>
        <?php if ($hasBillFilters): ?>
          <a href="<?= BASE_URL ?>/bills" class="btn btn-outline-primary mt-2">Clear filters</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <?php foreach ($bills as $bill): ?>
      <article class="bill-card">
        <header class="bill-card-head">
          <div>
            <div class="bill-order-number"><?= htmlspecialchars((string)$bill['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="bill-meta">
              <?php if (!empty($bill['table_number'])): ?><span class="bill-chip"><i class="bi bi-grid-3x3-gap"></i>Table #<?= htmlspecialchars((string)$bill['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$bill['table_name'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <span class="bill-chip"><i class="bi bi-clock"></i><?= htmlspecialchars(date('M j, g:i A', strtotime((string)$bill['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="bill-chip"><i class="bi bi-person"></i><?= htmlspecialchars((string)($bill['cashier_name'] ?? 'Cashier'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="bill-chip"><i class="bi bi-fire"></i><?= htmlspecialchars(ucfirst((string)$bill['order_status']), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
          <span class="bill-status <?= $bill['payment_status'] === 'partially_paid' ? 'partial' : '' ?>"><?= $bill['payment_status'] === 'partially_paid' ? 'Partially paid' : 'Unpaid' ?></span>
        </header>

        <div class="bill-summary">
          <div class="bill-summary-box"><span>Bill total</span><strong><?= $money((float)$bill['total_amount']) ?></strong></div>
          <div class="bill-summary-box"><span>Paid so far</span><strong><?= $money((float)$bill['amount_paid']) ?></strong></div>
          <div class="bill-summary-box due"><span>Balance due</span><strong><?= $money((float)$bill['balance_due']) ?></strong></div>
        </div>

        <div class="bill-body">
          <div class="row g-4">
            <div class="col-12 col-lg-5">
              <h2 class="bill-section-title">Order items</h2>
              <?php foreach ($bill['items'] as $item): ?>
                <div class="bill-item-row">
                  <div>
                    <div class="bill-item-name"><?= (float)$item['quantity'] ?>× <?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?><?php if (!empty($item['variant_name'])): ?> · <?= htmlspecialchars((string)$item['variant_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div>
                    <div class="bill-item-meta"><?= $money((float)$item['unit_price']) ?> each<?php if (!empty($item['special_instructions'])): ?> · <?= htmlspecialchars((string)$item['special_instructions'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div>
                  </div>
                  <div class="bill-item-price"><?= $money((float)$item['subtotal']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="col-12 col-lg-7">
              <?php if (!empty($bill['splits'])): ?>
                <h2 class="bill-section-title">Guest shares</h2>
                <div class="bill-splits-grid">
                  <?php foreach ($bill['splits'] as $split): ?>
                    <section class="bill-share <?= (float)$split['balance_due'] <= 0.009 ? 'paid' : '' ?>">
                      <div class="bill-share-head">
                        <div>
                          <div class="bill-share-label"><?= htmlspecialchars((string)$split['split_label'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="bill-item-meta">Share <?= (int)$split['split_number'] ?> · <?= $money((float)$split['amount_due']) ?> total · <?= $money((float)$split['amount_paid']) ?> paid</div>
                          <?php if (!empty($split['items'])): ?>
                            <div class="bill-item-meta mt-2 fw-semibold">Items assigned to this share</div>
                            <ul class="small text-secondary ps-3 mb-2">
                              <?php foreach ($split['items'] as $splitItem): ?>
                                <li><?= (float)$splitItem['allocated_quantity'] ?>× <?= htmlspecialchars((string)$splitItem['product_name'], ENT_QUOTES, 'UTF-8') ?><?php if (!empty($splitItem['variant_name'])): ?> · <?= htmlspecialchars((string)$splitItem['variant_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> <span class="fw-semibold"><?= $money((float)$splitItem['allocated_amount']) ?></span></li>
                              <?php endforeach; ?>
                            </ul>
                          <?php endif; ?>
                        </div>
                        <div class="bill-share-balance"><?= (float)$split['balance_due'] <= 0.009 ? 'Paid' : $money((float)$split['balance_due']) ?></div>
                      </div>
                      <?php if ((float)$split['balance_due'] > 0.009): ?>
                        <form method="POST" action="<?= BASE_URL ?>/bills/payment" class="bill-payment-panel">
                          <?= \App\Core\CSRF::formField() ?>
                          <input type="hidden" name="order_id" value="<?= (int)$bill['id'] ?>">
                          <input type="hidden" name="split_id" value="<?= (int)$split['id'] ?>">
                          <input type="hidden" name="payment_uuid" value="<?= htmlspecialchars(\App\Services\DeviceService::generateUuidV4(), ENT_QUOTES, 'UTF-8') ?>">
                          <div class="mb-2"><label class="form-label small fw-semibold">Payment amount</label><input class="form-control form-control-sm" name="amount" type="number" step="0.01" min="0.01" oninput="if(this.form.elements.tendered_amount.dataset.manual !== '1') this.form.elements.tendered_amount.value=this.value" max="<?= number_format((float)$split['balance_due'],2,'.','') ?>" value="<?= number_format((float)$split['balance_due'],2,'.','') ?>" required></div>
                          <div class="row g-2">
                            <div class="col-6"><label class="form-label small fw-semibold">Method</label><select class="form-select form-select-sm" name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="qr_online">QR / Online</option><option value="other">Other</option></select></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Cash tendered</label><input class="form-control form-control-sm" name="tendered_amount" type="number" step="0.01" min="0.01" oninput="this.dataset.manual='1'" value="<?= number_format((float)$split['balance_due'],2,'.','') ?>" required></div>
                          </div>
                          <button class="btn btn-success btn-sm w-100 mt-3" type="submit"><i class="bi bi-check2-circle me-1"></i> Record guest payment</button>
                        </form>
                      <?php endif; ?>
                    </section>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <?php if ((float)$bill['balance_due'] > 0.009): ?>
                  <h2 class="bill-section-title">Collect payment</h2>
                  <form method="POST" action="<?= BASE_URL ?>/bills/payment" class="bill-payment-panel">
                    <?= \App\Core\CSRF::formField() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$bill['id'] ?>">
                    <input type="hidden" name="payment_uuid" value="<?= htmlspecialchars(\App\Services\DeviceService::generateUuidV4(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-3">
                      <div class="col-12 col-md-6"><label class="form-label small fw-semibold">Amount to apply</label><input class="form-control" name="amount" type="number" step="0.01" min="0.01" oninput="if(this.form.elements.tendered_amount.dataset.manual !== '1') this.form.elements.tendered_amount.value=this.value" max="<?= number_format((float)$bill['balance_due'],2,'.','') ?>" value="<?= number_format((float)$bill['balance_due'],2,'.','') ?>" required><div class="bill-label-help">Enter less than the balance to record a partial payment.</div></div>
                      <div class="col-12 col-md-6"><label class="form-label small fw-semibold">Payment method</label><select class="form-select" name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="qr_online">QR / Online</option><option value="other">Other</option></select></div>
                      <div class="col-12"><label class="form-label small fw-semibold">Cash tendered</label><input class="form-control" name="tendered_amount" type="number" step="0.01" min="0.01" oninput="this.dataset.manual='1'" value="<?= number_format((float)$bill['balance_due'],2,'.','') ?>" required><div class="bill-label-help">For card/QR payments, the entered tender amount is ignored; only the amount applied is recorded.</div></div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mt-3"><i class="bi bi-cash-coin me-1"></i> Record payment</button>
                  </form>

                  <?php if ($bill['payment_status'] === 'unpaid' && $bill['order_type'] === 'dine_in' && !empty($bill['table_id'])): ?>
                    <form method="POST" action="<?= BASE_URL ?>/bills/split" class="bill-payment-panel mt-3">
                      <?= \App\Core\CSRF::formField() ?>
                      <input type="hidden" name="order_id" value="<?= (int)$bill['id'] ?>">
                      <h3 class="bill-section-title mb-2">Split this bill equally</h3>
                      <div class="row g-2 align-items-end">
                        <div class="col-4"><label class="form-label small fw-semibold">Guest shares</label><select class="form-select form-select-sm" name="split_count"><?php for ($n=2;$n<=12;$n++): ?><option value="<?= $n ?>" <?= $n===2?'selected':'' ?>><?= $n ?></option><?php endfor; ?></select></div>
                        <div class="col-8"><label class="form-label small fw-semibold">Guest names (optional)</label><textarea class="form-control form-control-sm" name="split_labels" rows="1" placeholder="Juan&#10;Maria"></textarea></div>
                      </div>
                      <div class="bill-label-help">Splitting is available only before the first payment. Amounts are balanced to the cent.</div>
                      <button type="submit" class="btn btn-outline-primary w-100 mt-3"><i class="bi bi-intersect me-1"></i> Create equal shares</button>
                    </form>

                    <?php if (count($bill['items']) >= 2): ?>
                      <form method="POST" action="<?= BASE_URL ?>/bills/split-items" class="bill-payment-panel mt-3" data-item-split-form>
                        <?= \App\Core\CSRF::formField() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$bill['id'] ?>">
                        <h3 class="bill-section-title mb-2">Split by menu items</h3>
                        <p class="bill-label-help mb-3">Assign each order line to a guest. Discounts are allocated proportionally and all shares reconcile to the cent. A line's full quantity stays together in this version.</p>
                        <div class="row g-2 mb-3">
                          <div class="col-4">
                            <label class="form-label small fw-semibold">Guest shares</label>
                            <select class="form-select form-select-sm" name="split_count" data-split-count>
                              <?php for ($n=2; $n<=min(12,count($bill['items'])); $n++): ?>
                                <option value="<?= $n ?>" <?= $n===2?'selected':'' ?>><?= $n ?></option>
                              <?php endfor; ?>
                            </select>
                          </div>
                          <div class="col-8">
                            <label class="form-label small fw-semibold">Guest names (optional)</label>
                            <textarea class="form-control form-control-sm" name="split_labels" rows="1" placeholder="Juan&#10;Maria"></textarea>
                          </div>
                        </div>
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Order line</th><th style="width:180px">Guest share</th></tr></thead>
                            <tbody>
                            <?php foreach ($bill['items'] as $item): ?>
                              <tr>
                                <td>
                                  <div class="fw-semibold small"><?= (float)$item['quantity'] ?>× <?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                  <div class="bill-label-help"><?= $money((float)$item['subtotal']) ?></div>
                                </td>
                                <td>
                                  <select class="form-select form-select-sm" name="item_assignments[<?= (int)$item['id'] ?>]" data-item-assignment required>
                                    <option value="">Choose guest...</option>
                                    <?php for ($share=1; $share<=min(12,count($bill['items'])); $share++): ?>
                                      <option value="<?= $share ?>">Guest <?= $share ?></option>
                                    <?php endfor; ?>
                                  </select>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-list-check me-1"></i> Create item shares</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
      <nav class="bills-pagination" aria-label="Open bills pages">
        <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>"
           href="<?= htmlspecialchars($billPageUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>"
           <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
          <i class="bi bi-chevron-left"></i> Previous
        </a>

        <div class="bills-pagination-pages">
          <?php if ($startPage > 1): ?>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($billPageUrl(1), ENT_QUOTES, 'UTF-8') ?>">1</a>
            <?php if ($startPage > 2): ?><span class="bills-pagination-ellipsis">…</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
            <a class="btn <?= $pageNumber === $currentPage ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= htmlspecialchars($billPageUrl($pageNumber), ENT_QUOTES, 'UTF-8') ?>"
               <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>>
              <?= $pageNumber ?>
            </a>
          <?php endfor; ?>

          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?><span class="bills-pagination-ellipsis">…</span><?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($billPageUrl($totalPages), ENT_QUOTES, 'UTF-8') ?>"><?= $totalPages ?></a>
          <?php endif; ?>
        </div>

        <a class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
           href="<?= htmlspecialchars($billPageUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>"
           <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
          Next <i class="bi bi-chevron-right"></i>
        </a>
      </nav>
    <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script>
(() => {
  document.querySelectorAll('[data-item-split-form]').forEach(form => {
    const countSelect = form.querySelector('[data-split-count]');
    const assignments = Array.from(form.querySelectorAll('[data-item-assignment]'));
    const updateAssignments = () => {
      const count = Number(countSelect.value || 2);
      assignments.forEach(select => {
        Array.from(select.options).forEach(option => {
          if (!option.value) return;
          const disabled = Number(option.value) > count;
          option.disabled = disabled;
          option.hidden = disabled;
          if (disabled && select.value === option.value) select.value = '';
        });
      });
    };
    countSelect.addEventListener('change', updateAssignments);
    updateAssignments();
  });
})();
</script>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
