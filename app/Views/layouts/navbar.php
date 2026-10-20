<?php
$user = \App\Core\Auth::user();
$isPlatform = \App\Core\Auth::isPlatformAdmin();
$restaurant = \App\Core\TenantContext::getRestaurant();
$graceDays = \App\Core\TenantContext::getGraceDaysRemaining();
$isSubValid = \App\Core\TenantContext::isSubscriptionValid();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
  <div class="container-fluid px-3">
    <!-- Brand / Tenant Name -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/dashboard">
      <span class="navbar-brand-badge">POS</span>
      <span class="fw-semibold text-truncate" style="max-width: 260px;">
        <?= htmlspecialchars($restaurant['name'] ?? ($isPlatform ? 'SaaS Platform Admin' : 'Restaurant POS')) ?>
      </span>
    </a>

    <!-- Mobile Hamburger Toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#posNavbar" aria-controls="posNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="posNavbar">
      <!-- Navigation Links -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/dashboard">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/menu">
            <i class="bi bi-journal-text me-1"></i> Menu
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/inventory">
            <i class="bi bi-boxes me-1"></i> Inventory
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/tables">
            <i class="bi bi-grid-3x3-gap me-1"></i> Tables
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/devices">
            <i class="bi bi-tablet-landscape me-1"></i> Terminals
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/shifts">
            <i class="bi bi-safe me-1"></i> Shifts
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/install">
            <i class="bi bi-tools me-1"></i> Tests
          </a>
        </li>
      </ul>

      <!-- Status Indicators & User Profile -->
      <div class="d-flex align-items-center flex-wrap gap-2">
        <!-- Launch POS Terminal Button -->
        <a href="<?= BASE_URL ?>/pos" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1 shadow-sm">
          <i class="bi bi-display"></i> POS Terminal
        </a>

        <!-- Connectivity Status Pill -->
        <span class="badge bg-success bg-opacity-25 text-success border border-success status-pill d-flex align-items-center gap-1">
          <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;" role="status"></span>
          Online
        </span>

        <!-- Subscription Grace Indicator -->
        <?php if ($restaurant): ?>
          <?php if ($isSubValid): ?>
            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary status-pill">
              <i class="bi bi-shield-check me-1"></i> Subscription Active
            </span>
          <?php else: ?>
            <span class="badge bg-warning bg-opacity-25 text-warning border border-warning status-pill">
              <i class="bi bi-exclamation-triangle me-1"></i> Grace Period: <?= $graceDays ?>d left
            </span>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Active User Profile Dropdown -->
        <?php if ($user): ?>
          <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2 px-3 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle"></i>
              <span class="fw-medium"><?= htmlspecialchars($user['full_name']) ?></span>
              <span class="badge bg-secondary ms-1"><?= htmlspecialchars($user['role_name'] ?? ($isPlatform ? 'SuperAdmin' : 'Staff')) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><h6 class="dropdown-header">Signed in as <?= htmlspecialchars($user['username']) ?></h6></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/change-password"><i class="bi bi-key me-2"></i>Change Password</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/install"><i class="bi bi-check2-circle me-2"></i>System Diagnostics</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
