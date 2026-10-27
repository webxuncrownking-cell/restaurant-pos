<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';
$money = static fn(float $amount): string => '₱' . number_format($amount, 2);
?>
<style>
.expenses-page{padding:24px}.expenses-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.expenses-title{font-size:1.7rem;font-weight:800;color:#172033;margin:0 0 6px}.expenses-subtitle{color:#718096;margin:0}
.expense-card{background:#fff;border:1px solid #e3e8f0;border-radius:16px;padding:20px;box-shadow:0 4px 16px rgba(15,23,42,.035);height:100%}
.expense-kpi-label{color:#718096;font-size:.86rem;font-weight:650}.expense-kpi-value{font-size:1.65rem;font-weight:850;color:#172033;margin-top:5px}
.expenses-page .form-control,.expenses-page .form-select{min-height:44px;font-size:.96rem}.expenses-page .btn{min-height:42px}
.expenses-table th{font-size:.8rem;color:#64748b;text-transform:uppercase;letter-spacing:.04em}.expenses-table td{vertical-align:middle}
.expense-status{display:inline-block;border-radius:999px;padding:5px 9px;font-size:.78rem;font-weight:750;background:#e8f8ef;color:#087443}.expense-status.voided{background:#f1f5f9;color:#64748b}
@media(max-width:767px){.expenses-page{padding:14px}.expenses-title{font-size:1.45rem}.expense-card{padding:15px}}
</style>
<div class="expenses-page">
  <?php foreach (($flashes ?? []) as $flash): ?>
    <div class="alert alert-<?= htmlspecialchars((string)($flash['type']??'info'),ENT_QUOTES,'UTF-8') ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars((string)($flash['message']??''),ENT_QUOTES,'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
  <header class="expenses-hero">
    <div><div class="text-uppercase small fw-bold text-primary mb-1">Business operations</div>
      <h1 class="expenses-title"><i class="bi bi-wallet2 me-2"></i>Operating Expenses</h1>
      <p class="expenses-subtitle">Record day-to-day costs and review expenses by date range. Voided records remain in history.</p></div>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/reports"><i class="bi bi-graph-up me-1"></i> Reports</a>
  </header>

  <?php if (empty($schemaReady)): ?>
    <section class="expense-card">
      <h2 class="h5 fw-bold">Set up expense tracking</h2>
      <p class="text-muted">This creates a separate tenant-scoped expense table. Existing sales, payments, and inventory records are not modified.</p>
      <form method="post" action="<?= BASE_URL ?>/expenses/migrate">
        <?= \App\Core\CSRF::formField() ?>
        <button class="btn btn-primary" type="submit"><i class="bi bi-database-gear me-1"></i> Prepare Expense Tracking</button>
      </form>
    </section>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6"><section class="expense-card"><div class="expense-kpi-label">Active expenses · selected period</div><div class="expense-kpi-value"><?= $money((float)$total) ?></div><div class="text-muted small mt-1">Voided entries are excluded from this total.</div></section></div>
      <div class="col-12 col-md-6"><section class="expense-card"><div class="expense-kpi-label">Expense entries</div><div class="expense-kpi-value"><?= (int)$entries ?></div><div class="text-muted small mt-1">From <?= htmlspecialchars($from,ENT_QUOTES,'UTF-8') ?> through <?= htmlspecialchars($to,ENT_QUOTES,'UTF-8') ?></div></section></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-5">
        <section class="expense-card">
          <h2 class="h5 fw-bold mb-3">Record an expense</h2>
          <form method="post" action="<?= BASE_URL ?>/expenses/create">
            <?= \App\Core\CSRF::formField() ?>
            <div class="mb-3"><label class="form-label fw-semibold" for="expense-description">Description</label><input class="form-control" id="expense-description" name="description" maxlength="180" required placeholder="e.g. Electricity bill"></div>
            <div class="row g-3">
              <div class="col-6"><label class="form-label fw-semibold" for="expense-category">Category</label><select class="form-select" id="expense-category" name="category" required><?php foreach ($categories as $key=>$label): ?><option value="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
              <div class="col-6"><label class="form-label fw-semibold" for="expense-amount">Amount (₱)</label><input class="form-control" id="expense-amount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" required></div>
              <div class="col-6"><label class="form-label fw-semibold" for="expense-date">Expense date</label><input class="form-control" id="expense-date" name="expense_date" type="date" value="<?= htmlspecialchars(date('Y-m-d'),ENT_QUOTES,'UTF-8') ?>" required></div>
              <div class="col-6"><label class="form-label fw-semibold" for="expense-method">Payment method</label><select class="form-select" id="expense-method" name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="qr_online">QR / Online</option><option value="other">Other</option></select></div>
            </div>
            <div class="mt-3"><label class="form-label fw-semibold" for="expense-reference">Reference number <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="expense-reference" name="reference_number" maxlength="80" placeholder="Receipt or transaction reference"></div>
            <div class="mt-3"><label class="form-label fw-semibold" for="expense-notes">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="expense-notes" name="notes" rows="2" maxlength="4000"></textarea></div>
            <button class="btn btn-primary w-100 mt-4" type="submit"><i class="bi bi-plus-circle me-1"></i> Save Expense</button>
          </form>
        </section>
      </div>
      <div class="col-12 col-xl-7">
        <section class="expense-card">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 fw-bold mb-0">Expense history</h2><span class="text-muted small">Latest 500 entries</span></div>
          <form class="row g-2 align-items-end mb-3" method="get" action="<?= BASE_URL ?>/expenses">
            <div class="col-6"><label class="form-label small fw-semibold" for="filter-from">From</label><input class="form-control" id="filter-from" type="date" name="from" value="<?= htmlspecialchars($from,ENT_QUOTES,'UTF-8') ?>" required></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="filter-to">To</label><input class="form-control" id="filter-to" type="date" name="to" value="<?= htmlspecialchars($to,ENT_QUOTES,'UTF-8') ?>" required></div>
            <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i> Apply Date Filter</button></div>
          </form>
          <div class="table-responsive">
            <table class="table table-hover expenses-table">
              <thead><tr><th>Date / Description</th><th>Category</th><th class="text-end">Amount</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>
              <?php if (empty($expenses)): ?><tr><td colspan="5" class="text-center text-muted py-4">No expense entries in this date range.</td></tr><?php endif; ?>
              <?php foreach ($expenses as $expense): ?>
                <tr>
                  <td><div class="fw-semibold"><?= htmlspecialchars((string)$expense['description'],ENT_QUOTES,'UTF-8') ?></div><div class="small text-muted"><?= htmlspecialchars((string)$expense['expense_date'],ENT_QUOTES,'UTF-8') ?> · <?= htmlspecialchars((string)$expense['created_by_name'],ENT_QUOTES,'UTF-8') ?></div><?php if (!empty($expense['reference_number'])): ?><div class="small text-muted">Ref: <?= htmlspecialchars((string)$expense['reference_number'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?></td>
                  <td><?= htmlspecialchars($categories[(string)$expense['category']]??(string)$expense['category'],ENT_QUOTES,'UTF-8') ?><div class="small text-muted"><?= htmlspecialchars(ucwords(str_replace('_',' ',(string)$expense['payment_method'])),ENT_QUOTES,'UTF-8') ?></div></td>
                  <td class="text-end fw-bold text-nowrap"><?= $money((float)$expense['amount']) ?></td>
                  <td><span class="expense-status <?= $expense['status']==='voided'?'voided':'' ?>"><?= htmlspecialchars(ucfirst((string)$expense['status']),ENT_QUOTES,'UTF-8') ?></span></td>
                  <td><?php if ($expense['status']==='active'): ?><form method="post" action="<?= BASE_URL ?>/expenses/void" onsubmit="return confirm('Void this expense? It will be excluded from totals but retained in history.');"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="expense_id" value="<?= (int)$expense['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Void</button></form><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
