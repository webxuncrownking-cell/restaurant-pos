<div class="container-fluid px-4 py-4">

  <!-- Flash Alerts -->
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Welcome Banner -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
      <h2 class="h3 fw-bold text-dark mb-1">
        <?php if ($is_platform): ?>
          <i class="bi bi-clouds text-primary me-2"></i>Platform Overview
        <?php else: ?>
          <i class="bi bi-shop text-primary me-2"></i><?= htmlspecialchars($restaurant['name'] ?? 'Restaurant Dashboard', ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </h2>
      <p class="text-muted small mb-0">
        <?php if ($is_platform): ?>
          Manage restaurant accounts, monitor subscriptions, and track platform performance and recurring revenue.
        <?php else: ?>
          A clear view of today's sales, orders, and inventory activity.
        <?php endif; ?>
      </p>
    </div>

    <div class="d-flex gap-2 mt-2 mt-md-0">
      <?php if ($is_platform): ?>
        <a href="<?= BASE_URL ?>/install" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-cpu me-1"></i> System Diagnostics
        </a>
      <?php endif; ?>
      <?php if ($is_platform && !empty($_SESSION['restaurant_id'])): ?>
        <a href="<?= BASE_URL ?>/dashboard/switch-tenant?id=0" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-arrow-left-circle me-1"></i> Exit Tenant View
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($is_platform && empty($_SESSION['restaurant_id'])): ?>
    <!-- PLATFORM SUPERADMIN VIEW -->
    <div class="row g-3 mb-4" id="platform-analytics">
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
              <i class="bi bi-building fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Registered Restaurants</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $total_restaurants ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
              <i class="bi bi-check-circle fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Active Subscriptions</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $active_subscriptions ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card card-metric p-3 bg-white">
          <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
              <i class="bi bi-tablet fs-3"></i>
            </div>
            <div>
              <span class="text-muted small fw-medium">Registered POS Terminals</span>
              <h3 class="fw-bold mb-0 text-dark"><?= $total_devices ?? 0 ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dedicated platform tools -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-6">
        <a href="<?= BASE_URL ?>/platform-analytics" class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none text-reset">
          <div class="card-body d-flex align-items-center gap-3 p-4">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-graph-up-arrow fs-3"></i></div>
            <div class="flex-grow-1">
              <h5 class="fw-semibold mb-1">Platform Analytics</h5>
              <p class="text-muted small mb-0">Review subscription status, plan distribution, recurring revenue estimates, and POS usage trends.</p>
            </div>
            <i class="bi bi-arrow-right text-primary"></i>
          </div>
        </a>
      </div>
      <div class="col-12 col-lg-6">
        <a href="<?= BASE_URL ?>/restaurant-accounts" class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none text-reset">
          <div class="card-body d-flex align-items-center gap-3 p-4">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-3"><i class="bi bi-building fs-3"></i></div>
            <div class="flex-grow-1">
              <h5 class="fw-semibold mb-1">Restaurant Accounts</h5>
              <p class="text-muted small mb-0">Search registered restaurants, review their plan and status, and inspect a tenant workspace.</p>
            </div>
            <i class="bi bi-arrow-right text-primary"></i>
          </div>
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- RESTAURANT OPERATIONS DASHBOARD -->
    <?php
      $dashboardCurrency = (string)($restaurant['currency_symbol'] ?? '₱');
      $statusLabels = [
        'pending' => 'Pending',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'served' => 'Served',
        'completed' => 'Completed'
      ];
    ?>
    <section class="sf-dashboard">
      <div class="sf-dashboard-heading">
        <div>
          <span class="sf-eyebrow">DAILY OPERATIONS</span>
          <h2>Restaurant overview</h2>
          <p>Your essential activity, sales, and stock alerts in one place.</p>
        </div>
        <?php if (!empty($can_create_orders)): ?>
          <a href="<?= BASE_URL ?>/pos" class="btn btn-primary sf-primary-action">
            <i class="bi bi-display" aria-hidden="true"></i> Open POS Terminal
          </a>
        <?php endif; ?>
      </div>

      <?php if (!empty($can_view_sales) || !empty($can_view_order_activity) || !empty($can_process_payments) || !empty($can_view_inventory)): ?>
      <div class="sf-stat-grid">
        <?php if (!empty($can_view_sales)): ?>
          <article class="sf-stat-card sf-stat-sales">
            <div class="sf-stat-top"><span class="sf-stat-icon"><i class="bi bi-currency-exchange"></i></span><span class="sf-stat-label">Sales today</span></div>
            <div class="sf-stat-value"><?= htmlspecialchars($dashboardCurrency, ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)($sales_today ?? 0), 2) ?></div>
            <div class="sf-stat-note">Paid orders recorded today</div>
          </article>
        <?php endif; ?>
        <?php if (!empty($can_view_order_activity)): ?>
          <article class="sf-stat-card">
            <div class="sf-stat-top"><span class="sf-stat-icon"><i class="bi bi-receipt"></i></span><span class="sf-stat-label">Orders today</span></div>
            <div class="sf-stat-value"><?= number_format((int)($orders_today ?? 0)) ?></div>
            <div class="sf-stat-note"><?= number_format((int)($active_orders ?? 0)) ?> currently in progress</div>
          </article>
        <?php endif; ?>
        <?php if (!empty($can_process_payments)): ?>
          <article class="sf-stat-card">
            <div class="sf-stat-top"><span class="sf-stat-icon"><i class="bi bi-wallet2"></i></span><span class="sf-stat-label">Open bills</span></div>
            <div class="sf-stat-value"><?= number_format((int)($open_bills_count ?? 0)) ?></div>
            <div class="sf-stat-note">Unpaid or partially paid</div>
          </article>
        <?php endif; ?>
        <?php if (!empty($can_view_inventory)): ?>
          <article class="sf-stat-card <?= !empty($low_stock_count) ? 'sf-stat-warning' : '' ?>">
            <div class="sf-stat-top"><span class="sf-stat-icon"><i class="bi bi-box-seam"></i></span><span class="sf-stat-label">Low-stock items</span></div>
            <div class="sf-stat-value"><?= number_format((int)($low_stock_count ?? 0)) ?></div>
            <div class="sf-stat-note">At or below minimum stock</div>
          </article>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php
        $hasQuickAccess = !empty($can_process_payments) || !empty($can_view_inventory) || !empty($can_view_sales);
      ?>
      <?php if ($hasQuickAccess): ?>
        <div class="sf-section-heading">
          <div><h3>Quick access</h3><p>Go straight to the tasks you use every day.</p></div>
        </div>
        <div class="sf-quick-grid">
          <?php if (!empty($can_process_payments)): ?>
            <a class="sf-quick-card" href="<?= BASE_URL ?>/bills">
              <span class="sf-quick-icon"><i class="bi bi-receipt-cutoff"></i></span>
              <span><strong>Orders &amp; Bills</strong><small>Review unpaid bills and payments</small></span>
              <i class="bi bi-arrow-up-right sf-quick-arrow"></i>
            </a>
          <?php endif; ?>
          <?php if (!empty($can_view_inventory)): ?>
            <a class="sf-quick-card" href="<?= BASE_URL ?>/inventory">
              <span class="sf-quick-icon"><i class="bi bi-box-seam"></i></span>
              <span><strong>Inventory</strong><small>Check ingredients and stock levels</small></span>
              <i class="bi bi-arrow-up-right sf-quick-arrow"></i>
            </a>
          <?php endif; ?>
          <?php if (!empty($can_view_sales)): ?>
            <a class="sf-quick-card" href="<?= BASE_URL ?>/reports">
              <span class="sf-quick-icon"><i class="bi bi-graph-up-arrow"></i></span>
              <span><strong>Analytics</strong><small>View sales and business performance</small></span>
              <i class="bi bi-arrow-up-right sf-quick-arrow"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php
        $showRecentOrders = !empty($can_view_order_activity);
        $showStockAlerts = !empty($can_view_inventory);
        $showOrderFinancials = !empty($can_view_order_financials);
        $showSingleDashboardPanel = $showRecentOrders xor $showStockAlerts;
      ?>
      <?php if ($showRecentOrders || $showStockAlerts): ?>
        <div class="sf-dashboard-lower<?= $showSingleDashboardPanel ? ' sf-dashboard-lower-single' : '' ?>">
          <?php if ($showRecentOrders): ?>
            <section class="sf-panel">
              <div class="sf-panel-heading">
                <div><h3>Recent orders</h3><p>Latest non-cancelled orders across your restaurant.</p></div>
                <?php if (!empty($can_process_payments)): ?>
                  <a href="<?= BASE_URL ?>/bills">View open bills <i class="bi bi-arrow-right"></i></a>
                <?php endif; ?>
              </div>
              <div class="table-responsive">
                <table class="table align-middle mb-0 sf-orders-table">
                  <thead>
                    <tr>
                      <th>Order</th><th>Type / Table</th><th>Order status</th>
                      <?php if ($showOrderFinancials): ?><th>Payment</th><th class="text-end">Total</th><?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($recent_orders)): ?>
                      <?php foreach ($recent_orders as $order): ?>
                        <?php
                          $orderStatus = (string)($order['order_status'] ?? 'pending');
                          $paymentStatus = (string)($order['payment_status'] ?? 'unpaid');
                          $statusClass = $orderStatus === 'completed' ? 'success' : ($orderStatus === 'ready' ? 'info' : 'secondary');
                          $paymentClass = $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'partially_paid' ? 'warning' : 'danger');
                          $orderType = ucfirst(str_replace('_', ' ', (string)($order['order_type'] ?? '')));
                          $tableLabel = trim((string)($order['table_number'] ?? '')) !== '' ? 'Table ' . (string)$order['table_number'] : (string)($order['table_name'] ?? '');
                        ?>
                        <tr>
                          <td><strong><?= htmlspecialchars((string)($order['order_number'] ?? ('#' . $order['id'])), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(date('M j, g:i A', strtotime((string)$order['created_at'])), ENT_QUOTES, 'UTF-8') ?></small></td>
                          <td><span><?= htmlspecialchars($orderType, ENT_QUOTES, 'UTF-8') ?></span><?php if ($tableLabel !== ''): ?><small><?= htmlspecialchars($tableLabel, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                          <td><span class="badge text-bg-<?= $statusClass ?>"><?= htmlspecialchars($statusLabels[$orderStatus] ?? ucfirst($orderStatus), ENT_QUOTES, 'UTF-8') ?></span></td>
                          <?php if ($showOrderFinancials): ?>
                            <td><span class="badge text-bg-<?= $paymentClass ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $paymentStatus)), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-end fw-semibold text-nowrap"><?= htmlspecialchars($dashboardCurrency, ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)($order['total_amount'] ?? 0), 2) ?></td>
                          <?php endif; ?>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="<?= $showOrderFinancials ? 5 : 3 ?>" class="sf-empty-state">No orders have been recorded yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($showStockAlerts): ?>
            <section class="sf-panel sf-stock-panel">
              <div class="sf-panel-heading">
                <div><h3>Stock alerts</h3><p>Ingredients that need attention.</p></div>
                <a href="<?= BASE_URL ?>/inventory">Inventory <i class="bi bi-arrow-right"></i></a>
              </div>
              <?php if (!empty($low_stock_items)): ?>
                <div class="sf-stock-list">
                  <?php foreach ($low_stock_items as $stockItem): ?>
                    <div class="sf-stock-row">
                      <span class="sf-stock-mark"><i class="bi bi-exclamation"></i></span>
                      <span class="sf-stock-copy">
                        <strong><?= htmlspecialchars((string)$stockItem['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small>Minimum <?= number_format((float)$stockItem['minimum_stock_alert'], 2) ?> <?= htmlspecialchars((string)$stockItem['base_unit'], ENT_QUOTES, 'UTF-8') ?></small>
                      </span>
                      <span class="sf-stock-quantity"><?= number_format((float)$stockItem['current_stock'], 2) ?> <?= htmlspecialchars((string)$stockItem['base_unit'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="sf-stock-empty">
                  <span><i class="bi bi-check2-circle"></i></span>
                  <strong>Stock levels look good</strong>
                  <small>No ingredients are currently at or below their minimum stock level.</small>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <style>
      .sf-dashboard { --sf-muted:#778398; --sf-line:#e8edf4; color:#182338; }
      .sf-dashboard-heading { display:flex; align-items:center; justify-content:space-between; gap:20px; margin:8px 0 25px; }
      .sf-eyebrow { display:block; margin-bottom:7px; color:#7183a0; font-size:.7rem; font-weight:800; letter-spacing:.13em; }
      .sf-dashboard-heading h2 { margin:0; color:#182338; font-size:1.55rem; font-weight:800; letter-spacing:-.04em; }
      .sf-dashboard-heading p,.sf-section-heading p,.sf-panel-heading p { margin:5px 0 0; color:var(--sf-muted); font-size:.88rem; }
      .sf-primary-action { min-height:46px!important; padding:0 17px!important; border-radius:11px!important; box-shadow:0 5px 14px rgba(37,99,235,.18)!important; }
      .sf-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(205px,1fr)); gap:15px; margin-bottom:29px; }
      .sf-stat-card { min-width:0; padding:19px; background:#fff; border:1px solid var(--sf-line); border-radius:16px; box-shadow:0 5px 18px rgba(22,34,55,.035); }
      .sf-stat-top { display:flex; align-items:center; gap:10px; color:#748198; font-size:.82rem; font-weight:700; }
      .sf-stat-icon { display:grid; place-items:center; width:36px; height:36px; flex:0 0 36px; border-radius:11px; color:#2563eb; background:#edf4ff; font-size:1rem; }
      .sf-stat-value { margin-top:13px; color:#172238; font-size:1.75rem; font-weight:800; line-height:1.15; letter-spacing:-.045em; overflow-wrap:anywhere; }
      .sf-stat-note { margin-top:7px; color:#8995a8; font-size:.76rem; }
      .sf-stat-sales { background:linear-gradient(135deg,#1f5fd1,#2b78e4); border-color:#1f5fd1; }
      .sf-stat-sales .sf-stat-top,.sf-stat-sales .sf-stat-note { color:rgba(255,255,255,.78); }
      .sf-stat-sales .sf-stat-icon { color:#fff; background:rgba(255,255,255,.16); }
      .sf-stat-sales .sf-stat-value { color:#fff; }
      .sf-stat-warning .sf-stat-icon { color:#b7791f; background:#fff5df; }
      .sf-section-heading { margin:0 0 13px; }
      .sf-section-heading h3,.sf-panel-heading h3 { margin:0; color:#202d42; font-size:1rem; font-weight:800; letter-spacing:-.02em; }
      .sf-section-heading p { margin-top:3px; }
      .sf-quick-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(205px,1fr)); gap:13px; margin-bottom:25px; }
      .sf-quick-card { display:flex; align-items:center; gap:12px; min-width:0; padding:15px 14px; color:#26354b; background:#fff; border:1px solid var(--sf-line); border-radius:14px; text-decoration:none; box-shadow:0 4px 14px rgba(22,34,55,.025); transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
      .sf-quick-card:hover { color:#1d4ed8; border-color:#c9d9f5; transform:translateY(-2px); box-shadow:0 9px 22px rgba(22,34,55,.07); }
      .sf-quick-icon { display:grid; place-items:center; width:42px; height:42px; flex:0 0 42px; color:#2563eb; background:#eff5ff; border-radius:12px; font-size:1.1rem; }
      .sf-quick-card>span:nth-child(2) { display:flex; flex-direction:column; min-width:0; }
      .sf-quick-card strong { color:#25334a; font-size:.88rem; font-weight:800; }
      .sf-quick-card small { margin-top:3px; color:#8995a8; font-size:.72rem; line-height:1.35; }
      .sf-quick-arrow { margin-left:auto; color:#a0aabc; font-size:.82rem; }
      .sf-quick-pos { border-color:#cbdcf9; background:linear-gradient(140deg,#fff,#f4f8ff); }
      .sf-dashboard-lower { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(285px,.9fr); gap:16px; align-items:stretch; }
      .sf-dashboard-lower-single { grid-template-columns:minmax(0,1fr); }
      .sf-panel { min-width:0; overflow:hidden; background:#fff; border:1px solid var(--sf-line); border-radius:16px; box-shadow:0 5px 18px rgba(22,34,55,.035); }
      .sf-panel-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:18px 19px; border-bottom:1px solid #eef1f6; }
      .sf-panel-heading a { color:#2563eb; font-size:.79rem; font-weight:750; text-decoration:none; white-space:nowrap; }
      .sf-orders-table { min-width:630px; font-size:.82rem; }
      .sf-orders-table thead th { padding:12px 14px; color:#8a96a8; background:#fafbfd; font-size:.68rem; font-weight:800; letter-spacing:.06em; }
      .sf-orders-table tbody td { padding:13px 14px; color:#526076; border-color:#f0f2f6; }
      .sf-orders-table tbody td:first-child strong,.sf-orders-table tbody td:nth-child(2) span { display:block; color:#26354b; font-weight:750; }
      .sf-orders-table tbody td small { display:block; margin-top:4px; color:#929daf; font-size:.7rem; }
      .sf-orders-table .badge { font-size:.68rem; font-weight:700; padding:.45em .65em; border-radius:7px; }
      .sf-empty-state { padding:32px 16px!important; color:#8995a8!important; text-align:center; }
      .sf-stock-list { padding:4px 18px 10px; }
      .sf-stock-row { display:flex; align-items:center; gap:10px; padding:13px 0; border-bottom:1px solid #f0f2f6; }
      .sf-stock-row:last-child { border-bottom:0; }
      .sf-stock-mark { display:grid; place-items:center; width:30px; height:30px; flex:0 0 30px; border-radius:9px; color:#b7791f; background:#fff5df; }
      .sf-stock-copy { display:flex; flex:1; flex-direction:column; min-width:0; }
      .sf-stock-copy strong { color:#344158; font-size:.82rem; font-weight:750; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
      .sf-stock-copy small { margin-top:3px; color:#919db0; font-size:.69rem; }
      .sf-stock-quantity { color:#b7791f; font-size:.77rem; font-weight:800; white-space:nowrap; }
      .sf-stock-empty { display:flex; min-height:205px; padding:25px; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
      .sf-stock-empty>span { display:grid; place-items:center; width:45px; height:45px; margin-bottom:10px; border-radius:50%; color:#15966a; background:#e9f8f1; font-size:1.25rem; }
      .sf-stock-empty strong { color:#304057; font-size:.88rem; }
      .sf-stock-empty small { max-width:235px; margin-top:5px; color:#929daf; font-size:.74rem; }
      @media (max-width:1199.98px) { .sf-stat-grid,.sf-quick-grid { grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); } }
      @media (max-width:767.98px) { .sf-dashboard-heading { align-items:flex-start; flex-direction:column; } .sf-dashboard-lower { grid-template-columns:1fr; } }
      @media (max-width:479.98px) {
        .sf-stat-grid,.sf-quick-grid { grid-template-columns:repeat(auto-fit,minmax(145px,1fr)); gap:9px; }
        .sf-stat-card { padding:14px 12px; }
        .sf-stat-value { font-size:1.35rem; }
        .sf-stat-top { align-items:flex-start; flex-direction:column; gap:7px; font-size:.74rem; }
        .sf-stat-note { font-size:.68rem; }
        .sf-quick-card { align-items:flex-start; flex-direction:column; gap:8px; padding:12px; position:relative; }
        .sf-quick-arrow { position:absolute; right:12px; top:15px; }
        .sf-quick-card small { font-size:.67rem; }
      }
    </style>
  <?php endif; ?>

  

</div>
