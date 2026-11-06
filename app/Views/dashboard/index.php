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

  <?php if ($is_platform): ?>
  <!-- Welcome Banner -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
      <h2 class="h3 fw-bold text-dark mb-1">
        <?php if ($is_platform): ?>
          <i class="bi bi-clouds text-primary me-2"></i>Platform Overview
        <?php else: ?>
          <i class="bi bi-shop text-warning me-2"></i><?= htmlspecialchars($restaurant['name'] ?? 'Restaurant Dashboard') ?>
        <?php endif; ?>
      </h2>
      <p class="text-muted small mb-0">
        <?php if ($is_platform): ?>
          Manage restaurant accounts, monitor subscriptions, and track platform performance and recurring revenue.
        <?php else: ?>
          Operating Currency: <strong><?= htmlspecialchars($restaurant['currency_symbol'] ?? 'PHP') ?></strong> &bull;
          Timezone: <strong><?= htmlspecialchars($restaurant['timezone'] ?? 'Asia/Manila') ?></strong> &bull;
          Subscription: <span class="badge bg-success"><?= htmlspecialchars(ucfirst($restaurant['subscription_status'] ?? 'Active')) ?></span>
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

    <?php endif; ?>

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
    <!-- TENANT APP DASHBOARD -->
    <?php
      $currency = (string)($restaurant['currency_symbol'] ?? '₱');
      $money = static function ($amount) use ($currency): string {
          return $currency . number_format((float)$amount, 2);
      };
      $tiles = [
          [
              'label' => 'POS Terminal',
              'description' => 'Take orders and process sales',
              'route' => '/pos',
              'icon' => 'bi-shop-window',
              'color' => 'blue',
              'allowed' => \App\Core\Auth::can('pos.create_order')
          ],
          [
              'label' => 'View Orders & Bills',
              'description' => 'Review orders and collect payments',
              'route' => '/bills',
              'icon' => 'bi-receipt',
              'color' => 'orange',
              'allowed' => \App\Core\Auth::can('pos.process_payment')
          ],
          [
              'label' => 'Product List',
              'description' => 'Manage menu items and products',
              'route' => '/menu',
              'icon' => 'bi-cup-hot',
              'color' => 'purple',
              'allowed' => \App\Core\Auth::can('menu.manage_catalog')
          ],
          [
              'label' => 'Analytics',
              'description' => 'Track sales and business performance',
              'route' => '/reports',
              'icon' => 'bi-graph-up-arrow',
              'color' => 'green',
              'allowed' => \App\Core\Auth::can('reports.view_sales')
          ],
          [
              'label' => 'Inventory',
              'description' => 'Monitor stock and ingredients',
              'route' => '/inventory',
              'icon' => 'bi-box-seam',
              'color' => 'teal',
              'allowed' => \App\Core\Auth::can('inventory.view_stock')
          ],
          [
              'label' => 'Kitchen Display',
              'description' => 'View and update kitchen orders',
              'route' => '/kitchen',
              'icon' => 'bi-fire',
              'color' => 'orange',
              'allowed' => (
                  in_array(\App\Core\Auth::roleSlug(), ['owner', 'manager', 'kitchen'], true)
                  || \App\Core\Auth::can('kitchen.view_kds')
              )
          ],
          [
              'label' => 'Cashier Shifts',
              'description' => 'Manage cashier shifts and cash flow',
              'route' => '/shifts',
              'icon' => 'bi-safe2',
              'color' => 'indigo',
              'allowed' => \App\Core\Auth::can('shifts.open_close')
          ],
          [
              'label' => 'Staff',
              'description' => 'Manage staff accounts and terminal PINs',
              'route' => '/staff-pins',
              'icon' => 'bi-people',
              'color' => 'indigo',
              'allowed' => \\App\\Core\\Auth::roleSlug() === 'owner'
          ],
          [
              'label' => 'Settings',
              'description' => 'Restaurant details and preferences',
              'route' => '/settings',
              'icon' => 'bi-gear',
              'color' => 'slate',
              'allowed' => \App\Core\Auth::roleSlug() === 'owner'
          ],
      ];
      $visibleTiles = array_values(array_filter($tiles, static fn($tile) => $tile['allowed']));
    ?>

    <section class="dashboard-welcome dashboard-welcome-date-only mb-3">
      <div class="dashboard-date">
        <i class="bi bi-calendar3"></i>
        <?= date('l, F j, Y') ?>
      </div>
    </section>

    <?php if (!empty($db_error)): ?>
      <div class="alert alert-warning">
        Dashboard information could not be fully loaded. Please check the application logs.
      </div>
    <?php endif; ?>

    <section class="mb-4">
      <div class="dashboard-section-heading">
        <div>
          <h2>Today's Snapshot</h2>
          <p>Your restaurant's performance so far today</p>
        </div>
      </div>

      <div class="dashboard-kpis">
        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-blue"><i class="bi bi-currency-exchange"></i></div>
          <div class="kpi-copy">
            <span>Sales Today</span>
            <strong><?= htmlspecialchars($money($sales_today ?? 0)) ?></strong>
            <small>Fully paid orders</small>
          </div>
        </article>

        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-green"><i class="bi bi-graph-up-arrow"></i></div>
          <div class="kpi-copy">
            <span>Profit Today</span>
            <strong><?= htmlspecialchars($money($profit_today ?? 0)) ?></strong>
            <small>Estimated gross profit</small>
          </div>
        </article>

        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-orange"><i class="bi bi-wallet2"></i></div>
          <div class="kpi-copy">
            <span>Debt Paid Today</span>
            <strong><?= htmlspecialchars($money($debt_paid_today ?? 0)) ?></strong>
            <small>Payments for previous orders</small>
          </div>
        </article>

        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-purple"><i class="bi bi-receipt-cutoff"></i></div>
          <div class="kpi-copy">
            <span>Orders Today</span>
            <strong><?= number_format((int)($orders_today ?? 0)) ?></strong>
            <small>Excluding cancelled orders</small>
          </div>
        </article>
      </div>
    </section>

    <div class="dashboard-main-grid">
      <section class="dashboard-management">
        <div class="dashboard-section-heading">
          <div>
            <h2>Management</h2>
            <p>Choose what you want to work on</p>
          </div>
        </div>

        <div class="management-grid">
          <?php foreach ($visibleTiles as $tile): ?>
            <a class="management-tile tile-<?= htmlspecialchars($tile['color']) ?>"
               href="<?= BASE_URL . $tile['route'] ?>">
              <span class="management-icon"><i class="bi <?= htmlspecialchars($tile['icon']) ?>"></i></span>
              <span class="management-text">
                <strong><?= htmlspecialchars($tile['label']) ?></strong>
                <small><?= htmlspecialchars($tile['description']) ?></small>
              </span>
              <i class="bi bi-arrow-up-right management-arrow"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="dashboard-recent">
        <div class="recent-heading">
          <div>
            <h2>Recent Orders</h2>
            <p>Latest restaurant transactions</p>
          </div>
          <?php if (\App\Core\Auth::can('pos.process_payment')): ?>
            <a href="<?= BASE_URL ?>/bills">View all <i class="bi bi-arrow-right"></i></a>
          <?php endif; ?>
        </div>

        <div class="recent-order-list">
          <?php if (!empty($recent_orders)): ?>
            <?php foreach ($recent_orders as $order): ?>
              <div class="recent-order">
                <div class="recent-order-icon"><i class="bi bi-receipt"></i></div>
                <div class="recent-order-info">
                  <strong><?= htmlspecialchars((string)($order['order_number'] ?? ('Order #' . $order['id']))) ?></strong>
                  <small>
                    <?= number_format((float)$order['item_count'], 0) ?> items
                    · <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$order['order_type']))) ?>
                    · <?= htmlspecialchars(date('M j, g:i A', strtotime($order['created_at']))) ?>
                  </small>
                </div>
                <div class="recent-order-total">
                  <strong><?= htmlspecialchars($money($order['total_amount'])) ?></strong>
                  <span class="order-status status-<?= htmlspecialchars((string)$order['payment_status']) ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$order['payment_status']))) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="recent-empty">
              <i class="bi bi-receipt"></i>
              <strong>No orders yet</strong>
              <span>New orders will appear here.</span>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  <?php endif; ?>

</div>
