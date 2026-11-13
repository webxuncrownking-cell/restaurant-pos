<?php
$user = \App\Core\Auth::user();
$isPlatform = \App\Core\Auth::isPlatformAdmin();
$restaurant = \App\Core\TenantContext::getRestaurant();
$graceDays = \App\Core\TenantContext::getGraceDaysRemaining();
$isSubValid = \App\Core\TenantContext::isSubscriptionValid();
$currentPath = (new \App\Core\Request())->getPath();
$canManageBranding = $restaurant !== null && ($isPlatform || \App\Core\Auth::roleSlug() === 'owner');

// The app uses dashboard tiles and the account dropdown rather than a sidebar.
$pageTitles = [
  '/dashboard' => 'Dashboard',
  '/pos' => 'POS Terminal',
  '/bills' => 'View Orders & Bills',
  '/menu' => 'Product List',
  '/reports' => 'Analytics',
  '/inventory' => 'Inventory',
  '/kitchen' => 'Kitchen Display',
  '/shifts' => 'Cashier Shifts',
  '/settings' => 'Settings',
  '/staff-pins' => 'Staff',
  '/devices' => 'POS Terminals',
  '/billing' => 'Subscription & Billing',
  '/change-password' => 'Change Password',
];
$pageTitle = $pageTitles[$currentPath] ?? 'Restaurant workspace';

$logoPath = (string)($restaurant['logo_path'] ?? '');
$logoUrl = $logoPath !== '' ? BASE_URL . '/' . ltrim($logoPath, '/') : '';
$restaurantName = $restaurant['name'] ?? ($isPlatform ? 'ServeFlow POS' : 'Restaurant POS');
$restaurantTagline = trim((string)($restaurant['tagline'] ?? ''));
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
        <?php if ($restaurant && $user && !empty($user['activation_trial_until'])): ?>
          <span class="header-subscription header-subscription-warning" title="Your payment is awaiting verification.">
            <i class="bi bi-clock-history"></i>
            Payment pending · Temporary access until <?= htmlspecialchars((new \\DateTimeImmutable((string)$user['activation_trial_until'], new \\DateTimeZone('UTC')))->setTimezone(new \\DateTimeZone('Asia/Manila'))->format('M j, g:i A'), ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php endif; ?>

        <?php if ($user): ?>
          <div class="dropdown">
            <button class="app-user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="app-user-avatar"><i class="bi bi-person-fill"></i></span>
              <span class="app-user-copy">
                <strong><?= htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></strong>
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
              <li><a class="dropdown-item rounded-2 py-2 app-signout-link" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </header>