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
    ['label' => 'Inventory', 'route' => '/inventory', 'icon' => 'bi-box-seam'],
    ['label' => 'Tables & Floor Plan', 'route' => '/tables', 'icon' => 'bi-grid-3x3-gap'],
  ],
  'Operations' => [
    ['label' => 'POS Terminals', 'route' => '/devices', 'icon' => 'bi-tablet-landscape'],
    ['label' => 'Cashier Shifts', 'route' => '/shifts', 'icon' => 'bi-safe2'],
    ['label' => 'Reports & Analytics', 'route' => '/reports', 'icon' => 'bi-graph-up-arrow'],
  ],
  'Account' => [
    ['label' => 'Subscription & Billing', 'route' => '/billing', 'icon' => 'bi-credit-card-2-front'],
  ],
  'System' => [
    ['label' => 'System Diagnostics', 'route' => '/install', 'icon' => 'bi-gear'],
  ],
];

if ($canManageBranding) {
  $navGroups['Account'][] = ['label' => 'Settings', 'route' => '/settings', 'icon' => 'bi-gear'];
}

$canViewKitchen = $restaurant !== null && ($isPlatform || in_array(\App\Core\Auth::roleSlug(), ['owner', 'manager', 'kitchen'], true) || \App\Core\Auth::can('kitchen.view_kds'));
if ($canViewKitchen) {
  $navGroups['Operations'][] = ['label' => 'Kitchen Display', 'route' => '/kitchen', 'icon' => 'bi-fire'];
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
$restaurantName = $restaurant['name'] ?? ($isPlatform ? 'SaaS Platform' : 'Restaurant POS');
$restaurantTagline = trim((string)($restaurant['tagline'] ?? ''));
$roleLabel = $user['role_name'] ?? $user['role'] ?? ($isPlatform ? 'Platform Admin' : 'Staff');
?>
<div class="app-shell">
  <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <a class="app-brand" href="<?= BASE_URL ?>/dashboard" aria-label="<?= htmlspecialchars((string)$restaurantName, ENT_QUOTES, 'UTF-8') ?> dashboard">
      <span class="app-brand-mark">
        <?php if ($logoUrl !== ''): ?>
          <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$restaurantName, ENT_QUOTES, 'UTF-8') ?> logo">
        <?php else: ?>
          <i class="bi bi-shop-window" aria-hidden="true"></i>
        <?php endif; ?>
      </span>
      <span class="app-brand-copy">
        <strong><?= htmlspecialchars((string)$restaurantName, ENT_QUOTES, 'UTF-8') ?></strong>
        <small title="<?= htmlspecialchars($restaurantTagline, ENT_QUOTES, 'UTF-8') ?>"><?= $restaurantTagline !== '' ? htmlspecialchars($restaurantTagline, ENT_QUOTES, 'UTF-8') : ($isPlatform && !$restaurant ? 'PLATFORM CONTROL CENTER' : 'RESTAURANT WORKSPACE') ?></small>
      </span>
    </a>

    <div class="app-sidebar-context">
      <span class="context-dot" aria-hidden="true"></span>
      <span><?= $isPlatform && !$restaurant ? 'Platform administration' : 'Workspace is ready' ?></span>
    </div>

    <nav class="app-sidebar-nav">
      <?php foreach ($navGroups as $groupLabel => $items): ?>
        <div class="app-nav-group">
          <div class="app-nav-heading"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></div>
          <?php foreach ($items as $item):
            $active = $currentPath === $item['route'] || str_starts_with($currentPath, $item['route'] . '/');
          ?>
            <a class="app-nav-link <?= $active ? 'active' : '' ?>"
               href="<?= BASE_URL . $item['route'] ?>"
               <?= $active ? 'aria-current="page"' : '' ?>>
              <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
              <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-bottom">
      <a class="app-sidebar-pos" href="<?= BASE_URL ?>/pos">
        <span class="pos-icon"><i class="bi bi-display" aria-hidden="true"></i></span>
        <span class="flex-grow-1">
          <strong>Open POS Terminal</strong>
          <small>Go to cashier workspace</small>
        </span>
        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
      </a>
      <div class="app-sidebar-version">Restaurant POS <span class="mx-1">·</span> Management</div>
    </div>
  </aside>

  <button type="button" class="app-sidebar-backdrop" id="appSidebarBackdrop" aria-label="Close navigation menu"></button>

  <main class="app-main">
    <header class="app-topbar">
      <div class="app-topbar-left">
        <button type="button" class="app-mobile-toggle" id="appSidebarToggle" aria-label="Open navigation menu" aria-controls="appSidebar" aria-expanded="false">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="app-page-context">
          <small><?= $isPlatform && !$restaurant ? 'PLATFORM ADMINISTRATION' : 'RESTAURANT MANAGEMENT' ?></small>
          <strong><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>

      <div class="app-topbar-actions">
        <span class="status-pill app-connectivity"><span class="context-dot" aria-hidden="true"></span> Online</span>
        <?php if ($restaurant): ?>
          <?php if ($isSubValid): ?>
            <span class="status-pill d-none d-xl-inline-flex bg-primary bg-opacity-10 text-primary border border-primary-subtle">
              <i class="bi bi-shield-check me-1" aria-hidden="true"></i> Subscription Active
            </span>
          <?php else: ?>
            <span class="status-pill d-none d-xl-inline-flex bg-warning bg-opacity-10 text-dark border border-warning-subtle">
              <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> Grace Period: <?= (int)$graceDays ?>d left
            </span>
          <?php endif; ?>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/pos" class="app-topbar-pos">
          <i class="bi bi-display" aria-hidden="true"></i><span>POS Terminal</span>
        </a>

        <?php if ($user): ?>
          <div class="dropdown">
            <button class="app-user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open user menu">
              <span class="app-user-avatar"><i class="bi bi-person-fill" aria-hidden="true"></i></span>
              <span class="app-user-copy">
                <strong><?= htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
              </span>
              <i class="bi bi-chevron-down user-chevron" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2">
              <li><h6 class="dropdown-header">Signed in as <?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h6></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/change-password"><i class="bi bi-key me-2 text-secondary"></i>Change Password</a></li>
              <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/install"><i class="bi bi-activity me-2 text-secondary"></i>System Diagnostics</a></li>
              <?php if ($canManageBranding): ?>
                <li><a class="dropdown-item rounded-2 py-2" href="<?= BASE_URL ?>/settings"><i class="bi bi-gear me-2 text-secondary"></i>Settings</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item rounded-2 py-2 text-danger" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </header>
