<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="text-uppercase small fw-bold text-secondary mb-1">Restaurant operations</div>
      <h1 class="h3 fw-bold mb-1">All Orders</h1>
      <p class="text-secondary mb-0">Monitor every order and its kitchen and payment status.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/bills"><i class="bi bi-receipt me-1"></i> Open Bills & Payments</a>
  </div>

  <form method="GET" action="<?= BASE_URL ?>/orders" class="card border-0 shadow-sm rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-lg-4">
        <label class="form-label small fw-semibold" for="order-search">Search orders</label>
        <input class="form-control" id="order-search" type="search" name="search" value="<?= htmlspecialchars((string)$filters['search'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Order number, table, cashier">
      </div>
      <div class="col-6 col-lg-3">
        <label class="form-label small fw-semibold" for="order-status">Order status</label>
        <select class="form-select" id="order-status" name="status">
          <option value="">All statuses</option>
          <?php foreach (['pending'=>'Pending','preparing'=>'Preparing','ready'=>'Ready','served'=>'Served','completed'=>'Completed','cancelled'=>'Cancelled'] as $value=>$label): ?>
            <option value="<?= $value ?>" <?= $filters['status']===$value?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-3">
        <label class="form-label small fw-semibold" for="payment-status">Payment status</label>
        <select class="form-select" id="payment-status" name="payment_status">
          <option value="">All payment statuses</option>
          <?php foreach (['unpaid'=>'Unpaid','partially_paid'=>'Partially paid','paid'=>'Paid','refunded'=>'Refunded'] as $value=>$label): ?>
            <option value="<?= $value ?>" <?= $filters['payment_status']===$value?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search me-1"></i> Filter</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/orders" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="small text-secondary"><?= count($orders) ?> order(s) shown · Latest 200</span>
    <span class="small text-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh page for latest updates</span>
  </div>

  <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Order</th><th>Type / Table</th><th>Items</th><th>Cashier</th><th>Order status</th><th>Payment</th><th class="text-end">Total</th><th class="text-end pe-3">Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="8" class="text-center text-secondary py-5">No orders match your filters.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $order):
              $orderStatus = (string)$order['order_status'];
              $paymentState = (string)$order['payment_status'];
              $orderColors = ['pending'=>'warning','preparing'=>'info','ready'=>'success','served'=>'primary','completed'=>'secondary','cancelled'=>'danger'];
              $paymentColors = ['unpaid'=>'warning','partially_paid'=>'info','paid'=>'success','refunded'=>'danger'];
              $total = (float)$order['total_amount'];
              $paid = (float)$order['amount_paid'];
              $balance = max(0, $total - $paid);
            ?>
              <tr>
                <td class="ps-3">
                  <strong class="d-block"><?= htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <small class="text-secondary">#<?= (int)$order['id'] ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string)$order['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                </td>
                <td>
                  <?= htmlspecialchars(ucfirst(str_replace('_',' ',(string)$order['order_type'])), ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($order['table_number'] !== ''): ?><small class="d-block text-secondary">Table <?= htmlspecialchars((string)$order['table_number'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                </td>
                <td><?= (int)$order['item_count'] ?></td>
                <td><?= htmlspecialchars((string)$order['cashier_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge text-bg-<?= $orderColors[$orderStatus] ?? 'secondary' ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$orderStatus)), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><span class="badge text-bg-<?= $paymentColors[$paymentState] ?? 'secondary' ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$paymentState)), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="text-end fw-semibold">₱<?= number_format($total,2) ?></td>
                <td class="text-end pe-3 <?= $balance > 0.009 ? 'text-danger fw-semibold' : 'text-secondary' ?>">₱<?= number_format($balance,2) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>This page is for monitoring only. Use Open Bills & Payments to collect or manage payments.</p>
</div>
