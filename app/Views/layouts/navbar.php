<?php
$user = \App\Core\Auth::user();
$isPlatform = \App\Core\Auth::isPlatformAdmin();
$restaurant = \App\Core\TenantContext::getRestaurant();
$graceDays = \App\Core\TenantContext::getGraceDaysRemaining();
$isSubValid = \App\Core\TenantContext::isSubscriptionValid();
$currentPath = (new \App\Core\Request())->getPath();
$canManageBranding = $restaurant !== null && ($isPlatform || \App\Core\Auth::roleSlug() === 'owner');

$navGroups = [
  'Workspace' => [
    ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'bi-speedometer2'],
    ['label' => 'Menu Management', 'route' => '/menu', 'icon' => 'bi-journal-richtext'],
    ['label' => 'Combo Meals', 'route' => '/combos', 'icon' => 'bi-boxes'],
    ['label' => 'Scheduled Menu Pricing', 'route' => '/pricing', 'icon' => 'bi-clock-history'],
    ['label' => 'Customer CRM', 'route' => '/customers', 'icon' => 'bi-people'],
    ['label' => 'Inventory', 'route' => '/inventory', 'icon' => 'bi-box-seam'],
    ['label' => 'Expiry & Wastage Tracking', 'route' => '/expiry', 'icon' => 'bi-calendar2-x'],
    ['label' => 'Suppliers & Purchase Orders', 'route' => '/procurement', 'icon' => 'bi-truck'],
    ['label' => 'Tables & Floor Plan', 'route' => '/tables', 'icon' => 'bi-grid-3x3-gap'],
    ['label' => 'Reservations & Waitlist', 'route' => '/reservations', 'icon' => 'bi-calendar2-check'],
  ],
  'Operations' => [
    ['label' => 'POS Terminals', 'route' => '/devices', 'icon' => 'bi-tablet-landscape'],
    ['label' => 'Cashier Shifts', 'route' => '/shifts', 'icon' => 'bi-safe2'],
    ['label' => 'Open Bills & Split Payments', 'route' => '/bills', 'icon' => 'bi-receipt-cutoff'],
    ['label' => 'Reports & Analytics', 'route' => '/reports', 'icon' => 'bi-graph-up-arrow'],
    ['label' => 'Operating Expenses', 'route' => '/expenses', 'icon' => 'bi-wallet2'],
  ],
  'Account' => [
    ['label' => 'Subscription & Billing', 'route' => '/billing', 'icon' => 'bi-credit-card-2-front'],
  ],
  'System' => [
    ['label' => 'System Diagnostics', 'route' => '/install', 'icon' => 'bi-gear'],
  ],
];

// Keep navigation consistent with the active user's tenant-scoped permissions.
// Hiding a link is only the UI layer; controllers must continue enforcing access too.
$navPermissionMap = [
  '/menu' => 'menu.manage_catalog',
  '/combos' => 'menu.manage_catalog',
  '/pricing' => 'menu.manage_catalog',
  '/customers' => 'reports.view_sales',
  '/inventory' => 'inventory.view_stock',
  '/expiry' => 'inventory.adjust',
  '/procurement' => 'inventory.stock_in',
  '/tables' => 'tables.view_status',
  '/reservations' => 'tables.view_status',
  '/devices' => 'users.manage_staff',
  '/shifts' => 'shifts.open_close',
  '/bills' => 'pos.process_payment',
  '/reports' => 'reports.view_sales',
  '/expenses' => 'reports.view_sales',
  '/billing' => 'subscription.manage',
  '/install' => 'platform.only',
];

if ($isPlatform) {
  // Platform superadmins get platform-level navigation only.
  $navGroups = [
    'Overview' => [
      ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'bi-speedometer2'],
      ['label' => 'Platform Analytics', 'route' => '/platform-analytics', 'icon' => 'bi-graph-up-arrow'],
    ],
    'Tenants' => [
      ['label' => 'Restaurant Accounts', 'route' => '/restaurant-accounts', 'icon' => 'bi-building'],
      ['label' => 'Plans & Subscriptions', 'route' => '/platform-subscriptions', 'icon' => 'bi-credit-card'],
      ['label' => 'Activation Payments', 'route' => '/platform-activation-payments', 'icon' => 'bi-shield-check'],
      ['label' => 'Subscription Payment Reviews', 'route' => '/platform-subscription-payments', 'icon' => 'bi-cash-stack'],
      ['label' => 'Pricing & Payment Settings', 'route' => '/platform-pricing', 'icon' => 'bi-sliders'],

    ],
    'System' => [
      ['label' => 'Platform Health & Logs', 'route' => '/platform-health', 'icon' => 'bi-activity'],
      ['label' => 'System Diagnostics', 'route' => '/install', 'icon' => 'bi-gear'],
    ],
  ];
} else {
  // Restaurant accounts see only menus allowed by their role permissions.
  foreach ($navGroups as $group => $items) {
    $navGroups[$group] = array_values(array_filter($items, static function (array $item) use ($navPermissionMap): bool {
      $route = (string)($item['route'] ?? '');
      if ($route === '/install') return false;
      if ($route === '/billing') return \App\Core\Auth::roleSlug() === 'owner';
      if ($route === '/reservations') return in_array(\App\Core\Auth::roleSlug(), ['owner', 'manager'], true);
      if (isset($navPermissionMap[$route])) return \App\Core\Auth::can($navPermissionMap[$route]);
      return true;
    }));
    if (!$navGroups[$group]) unset($navGroups[$group]);
  }

  if ($canManageBranding) {
    $navGroups['Account'][] = ['label' => 'Settings', 'route' => '/settings', 'icon' => 'bi-gear'];
  }

  if ($user && !$isPlatform && \App\Core\Auth::roleSlug() === 'owner') {
    $navGroups['Account'][] = ['label' => 'Staff Terminal PINs', 'route' => '/staff-pins', 'icon' => 'bi-shield-lock'];
  }

  $canViewKitchen = $restaurant !== null && (in_array(\App\Core\Auth::roleSlug(), ['owner', 'manager', 'kitchen'], true) || \App\Core\Auth::can('kitchen.view_kds'));
  if ($canViewKitchen) {
    $navGroups['Operations'][] = ['label' => 'Kitchen Display', 'route' => '/kitchen', 'icon' => 'bi-fire'];
  }
}

$pageTitle = 'Restaurant workspace';
foreach ($navGroups as $items) {
  foreach ($items as $item) {
    if ($currentPath === $item['route'] || str_starts_with($currentPath, $item['route'] . '/')) {
      $pageTitle = $item['label'];
    }
  }
}

$logoPath = (string)($restaurant['logo_path'] ?? '');
$logoUrl = $logoPath !== '' ? BASE_URL . '/' . ltrim($logoPath, '/') : '';
$restaurantName = $restaurant['name'] ?? ($isPlatform ? 'ServeFlow POS' : 'Restaurant POS');
$restaurantTagline = trim((string)($restaurant['tagline'] ?? ''));
$roleLabel = $user['role_name'] ?? $user['role'] ?? ($isPlatform ? 'Platform Admin' : 'Staff');
?>
<div class="app-shell">
  <main class="app-main">
    <header class="app-topbar">
      <div class="app-topbar-left">
        <a class="app-header-brand" href="<?= BASE_URL ?>/dashboard" aria-label="Dashboard">
          <?php if ($logoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars((string)$restaurantName, ENT_QUOTES, 'UTF-8') ?>">
          <?php else: ?>
            <span class="app-header-brand-icon"><i class="bi bi-shop-window"></i></span>
          <?php endif; ?>
          <span class="app-header-brand-copy">
            <strong><?= htmlspecialchars((string)$restaurantName, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($restaurantTagline !== ''): ?>
              <small><?= htmlspecialchars($restaurantTagline, ENT_QUOTES, 'UTF-8') ?></small>
            <?php else: ?>
              <small><?= $isPlatform && !$restaurant ? 'PLATFORM MANAGEMENT' : 'RESTAURANT WORKSPACE' ?></small>
            <?php endif; ?>
          </span>
        </a>
        <span class="app-header-divider"></span>
        <div class="app-page-context">
          <strong><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>

      <div class="app-topbar-actions">
        <?php if ($restaurant): ?>
          <?php if ($isSubValid): ?>
            <span class="header-subscription"><i class="bi bi-shield-check"></i> Subscription Active</span>
          <?php else: ?>
            <span class="header-subscription header-subscription-warning">
              <i class="bi bi-exclamation-triangle"></i> Grace Period: <?= (int)$graceDays ?>d left
            </span>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
          <div class="dropdown">
            <button class="app-user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="app-user-avatar"><i class="bi bi-person-fill"></i></span>
              <span class="app-user-copy">
                <strong><?= htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
              </span>
              <i class="bi bi-chevron-down user-chevron"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2">
              <li><h6 class="dropdown-header">Signed in as <?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h6></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/change-password"><i class="bi bi-key me-2"></i>Change Password</a></li>
              <?php if ($isPlatform): ?>
                <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/install"><i class="bi bi-activity me-2"></i>System Diagnostics</a></li>
              <?php endif; ?>
              <?php if ($canManageBranding): ?>
                <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item rounded-2 py-2 text-danger" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </header>
