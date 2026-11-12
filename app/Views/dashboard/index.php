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

  <?php if (!$is_platform && (($restaurant['activation_status'] ?? 'activated') !== 'activated')): ?>
    <div class="modal fade" id="serveflowActivationModal" tabindex="-1" aria-labelledby="serveflowActivationTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-body p-4 p-md-5 text-center">
            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width:68px;height:68px;">
              <i class="bi bi-shield-lock fs-2"></i>
            </div>
            <h2 class="h4 fw-bold mb-2" id="serveflowActivationTitle">Activate Your Restaurant</h2>
            <p class="text-muted mb-3">Your account has been created successfully. Submit the one-time activation payment to unlock the POS, orders, inventory, and restaurant management features.</p>
            <div class="alert alert-warning text-start small mb-4"><strong>Activation required.</strong> Operational tools remain locked until ServeFlow verifies and approves your payment.</div>
            <a href="<?= BASE_URL ?>/activation" class="btn btn-primary btn-lg w-100"><i class="bi bi-credit-card me-2"></i>Continue to Activation</a>
            <a href="<?= BASE_URL ?>/logout" class="btn btn-link text-muted mt-2">Sign out</a>
          </div>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var element = document.getElementById('serveflowActivationModal');
        if (element && window.bootstrap) {
          bootstrap.Modal.getOrCreateInstance(element, {backdrop: 'static', keyboard: false}).show();
        }
      });
    </script>
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

    <!-- Platform workspace: larger navigation area with recent activity sidebar -->
    <section class="platform-workspace mb-4">
      <div class="row g-3 align-items-stretch">
        <div class="col-12 col-xl-7">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
              <h3 class="h5 fw-bold mb-1">Platform Management</h3>
              <p class="text-muted small mb-0">Your main workspace for managing ServeFlow.</p>
            </div>
          </div>
          <div class="row g-3">
            <?php
              $platformMenus = [
                ['Platform Analytics', '/platform-analytics', 'bi-graph-up-arrow', 'Revenue, subscriptions, and usage insights.', 'tile-blue'],
                ['Restaurant Accounts', '/restaurant-accounts', 'bi-buildings', 'Manage registered restaurant accounts.', 'tile-orange'],
                ['Activation Payments', '/platform-activation-payments', 'bi-shield-check', 'Review proofs and activate restaurants.', 'tile-green'],
                ['Subscriptions', '/platform-subscriptions', 'bi-credit-card', 'Manage plans and subscriptions.', 'tile-indigo'],
                ['Subscription Payments', '/platform-subscription-payments', 'bi-receipt', 'Review subscription payment records.', 'tile-orange'],
                ['Platform Pricing', '/platform-pricing', 'bi-tags', 'Manage plans and pricing.', 'tile-purple'],
                ['Platform Health', '/platform-health', 'bi-heart-pulse', 'Monitor platform services and status.', 'tile-green'],
                ['System Diagnostics', '/install', 'bi-cpu', 'System configuration and diagnostics.', 'tile-slate'],
              ];
            ?>
            <div class="management-grid">
              <?php foreach ($platformMenus as $menu): ?>
                <a href="<?= BASE_URL . $menu[1] ?>" class="management-tile <?= htmlspecialchars($menu[4], ENT_QUOTES, 'UTF-8') ?>">
                  <span class="management-icon">
                    <i class="bi <?= htmlspecialchars($menu[2], ENT_QUOTES, 'UTF-8') ?>"></i>
                  </span>
                  <span class="management-text">
                    <strong><?= htmlspecialchars($menu[0], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($menu[3], ENT_QUOTES, 'UTF-8') ?></small>
                  </span>
                  <i class="bi bi-arrow-up-right management-arrow" aria-hidden="true"></i>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <aside class="col-12 col-xl-5">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h3 class="h6 fw-bold mb-1">Platform Activity</h3>
                  <small class="text-muted">Recent updates</small>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary">Overview</span>
              </div>

              <div class="rounded-3 bg-warning bg-opacity-10 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <small class="text-muted d-block">Activation payments to review</small>
                    <strong class="fs-4"><?= (int)($pending_activation_count ?? 0) ?></strong>
                  </div>
                  <i class="bi bi-shield-exclamation text-warning fs-3"></i>
                </div>
                <a href="<?= BASE_URL ?>/platform-activation-payments" class="small fw-semibold text-decoration-none">Review submissions <i class="bi bi-arrow-right"></i></a>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="small">Recently Added Restaurants</strong>
                  <a href="<?= BASE_URL ?>/restaurant-accounts" class="small text-decoration-none">View all</a>
                </div>
                <?php if (!empty($recent_restaurants)): ?>
                  <?php foreach ($recent_restaurants as $item): ?>
                    <div class="d-flex justify-content-between gap-2 py-2 border-bottom">
                      <span class="min-width-0">
                        <strong class="d-block small text-truncate"><?= htmlspecialchars($item['name'] ?? 'Restaurant') ?></strong>
                        <small class="text-muted"><?= !empty($item['created_at']) ? date('M j', strtotime($item['created_at'])) : 'Date unavailable' ?></small>
                      </span>
                      <span class="badge <?= ($item['activation_status'] ?? '') === 'activated' ? 'bg-success' : 'bg-warning text-dark' ?> align-self-center"><?= htmlspecialchars(ucfirst((string)($item['activation_status'] ?? 'pending'))) ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="small text-muted mb-0">No restaurant accounts yet.</p>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="small">Recent Subscriptions</strong>
                  <a href="<?= BASE_URL ?>/platform-subscriptions" class="small text-decoration-none">View all</a>
                </div>
                <?php if (!empty($recent_subscriptions)): ?>
                  <?php foreach ($recent_subscriptions as $item): ?>
                    <div class="py-2 border-bottom">
                      <strong class="d-block small text-truncate"><?= htmlspecialchars($item['restaurant_name'] ?? 'Restaurant') ?></strong>
                      <small class="text-muted"><?= htmlspecialchars($item['plan_name'] ?? 'Plan') ?> · <?= htmlspecialchars(ucfirst((string)($item['status'] ?? 'unknown'))) ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="small text-muted mb-0">No subscription activity yet.</p>
                <?php endif; ?>
              </div>

              <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="small">POS Terminal Activity</strong>
                  <a href="<?= BASE_URL ?>/platform-health" class="small text-decoration-none">View health</a>
                </div>
                <?php if (!empty($recent_pos_devices)): ?>
                  <?php foreach ($recent_pos_devices as $device): ?>
                    <div class="d-flex justify-content-between gap-2 py-2 border-bottom">
                      <span class="small text-truncate"><?= htmlspecialchars($device['restaurant_name'] ?? 'Restaurant') ?></span>
                      <small class="text-muted text-nowrap"><?= !empty($device['last_sync_at']) ? date('M j, g:i A', strtotime($device['last_sync_at'])) : 'Not synced' ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="small text-muted mb-0">No POS terminal activity yet.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>

  <?php else: ?>
    <!-- TENANT APP DASHBOARD -->
    <?php
      $roleSlug = (string)(\App\Core\Auth::roleSlug() ?? 'staff');
      $roleDashboard = [
          'owner' => ['title' => 'Owner Overview', 'description' => 'Your restaurant performance, team, and subscription at a glance.'],
          'manager' => ['title' => 'Manager Workspace', 'description' => 'Monitor daily operations, stock, sales, and team activity.'],
          'cashier' => ['title' => 'Cashier Workspace', 'description' => 'Access the POS, customer bills, and your cash shift tools.'],
          'kitchen' => ['title' => 'Kitchen Workspace', 'description' => 'Focus on incoming orders and kitchen preparation.'],
          'waiter' => ['title' => 'Service Workspace', 'description' => 'Access order entry and table service tools.']
      ];
      $roleDashboardInfo = $roleDashboard[$roleSlug] ?? ['title' => 'Staff Workspace', 'description' => 'Access the tools assigned to your restaurant role.'];
      $canSeeFinancialSnapshot = in_array($roleSlug, ['owner', 'manager', 'cashier'], true);
      $canSeeRecentOrders = in_array($roleSlug, ['owner', 'manager', 'cashier'], true);
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
              'label' => 'Floor & Tables',
              'description' => 'View table availability and service status',
              'route' => '/tables',
              'icon' => 'bi-grid-3x3-gap',
              'color' => 'teal',
              'allowed' => \App\Core\Auth::can('tables.view_status')
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
              'allowed' => \App\Core\Auth::roleSlug() === 'owner'
          ],
          [
              'label' => 'Subscription & Billing',
              'description' => 'Manage your plan, payments, and invoices',
              'route' => '/billing',
              'icon' => 'bi-credit-card',
              'color' => 'blue',
              'allowed' => \App\Core\Auth::roleSlug() === 'owner'
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

    <section class="dashboard-welcome mb-3">
      <div>
        <div class="dashboard-date"><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></div>
        <h1 class="h3 fw-bold mt-2 mb-1"><?= htmlspecialchars($roleDashboardInfo['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($roleDashboardInfo['description'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </section>

    <?php if (!empty($db_error)): ?>
      <div class="alert alert-warning">
        Dashboard information could not be fully loaded. Please check the application logs.
      </div>
    <?php endif; ?>

    <?php if ($canSeeFinancialSnapshot): ?>
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

        <?php if (in_array($roleSlug, ['owner', 'manager'], true)): ?>
        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-green"><i class="bi bi-graph-up-arrow"></i></div>
          <div class="kpi-copy">
            <span>Profit Today</span>
            <strong><?= htmlspecialchars($money($profit_today ?? 0)) ?></strong>
            <small>Estimated gross profit</small>
          </div>
        </article>
        <?php endif; ?>

        <?php if (in_array($roleSlug, ['owner', 'manager'], true)): ?>
        <article class="dashboard-kpi">
          <div class="kpi-icon kpi-orange"><i class="bi bi-wallet2"></i></div>
          <div class="kpi-copy">
            <span>Debt Paid Today</span>
            <strong><?= htmlspecialchars($money($debt_paid_today ?? 0)) ?></strong>
            <small>Payments for previous orders</small>
          </div>
        </article>
        <?php endif; ?>

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
    <?php endif; ?>

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

      <?php if ($canSeeRecentOrders): ?>
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
      <?php else: ?>
      <section class="dashboard-recent">
        <div class="recent-heading">
          <div>
            <h2><?= $roleSlug === 'kitchen' ? 'Kitchen orders' : 'Your service tools' ?></h2>
            <p><?= $roleSlug === 'kitchen' ? 'Open the kitchen display to view and update incoming orders.' : 'Use the management shortcuts available for your role.' ?></p>
          </div>
        </div>
        <div class="recent-empty">
          <i class="bi <?= $roleSlug === 'kitchen' ? 'bi-fire' : 'bi-grid' ?>"></i>
          <strong><?= $roleSlug === 'kitchen' ? 'Kitchen Display' : 'Role-based workspace' ?></strong>
          <span><?= $roleSlug === 'kitchen' ? 'Your kitchen queue is available in the Kitchen Display.' : 'Your dashboard only shows the tools assigned to your role.' ?></span>
          <?php if ($roleSlug === 'kitchen'): ?>
            <a class="btn btn-primary btn-sm mt-3" href="<?= BASE_URL ?>/kitchen">Open Kitchen Display</a>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
