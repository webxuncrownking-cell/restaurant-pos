<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php
    $faviconRestaurant = isset($restaurant) && is_array($restaurant) ? $restaurant : null;
    if ($faviconRestaurant === null && isset($catalog['restaurant']) && is_array($catalog['restaurant'])) {
      $faviconRestaurant = $catalog['restaurant'];
    }
    if ($faviconRestaurant === null) {
      try {
        $faviconRestaurant = \App\Core\TenantContext::getRestaurant();
      } catch (\Throwable $ignored) {
        $faviconRestaurant = null;
      }
    }
    $faviconRestaurant = is_array($faviconRestaurant) ? $faviconRestaurant : [];
    $customFaviconPath = trim((string)($faviconRestaurant['favicon_path'] ?? ''));
    $fallbackLogoPath = trim((string)($faviconRestaurant['logo_path'] ?? ''));
    $faviconLogoPath = $customFaviconPath !== '' ? $customFaviconPath : $fallbackLogoPath;
    $faviconExtension = strtolower(pathinfo($faviconLogoPath, PATHINFO_EXTENSION));
    $faviconMimeTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
    $faviconType = $faviconMimeTypes[$faviconExtension] ?? 'image/svg+xml';
    $faviconUrl = $faviconLogoPath !== ''
      ? BASE_URL . '/' . ltrim($faviconLogoPath, '/')
      : BASE_URL . '/public/favicon.svg';
    $pageThemeColor = (string)($faviconRestaurant['pos_primary_color'] ?? '#2563eb');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $pageThemeColor)) $pageThemeColor = '#2563eb';
  ?>
  <meta name="theme-color" content="<?= htmlspecialchars($pageThemeColor, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="manifest" href="<?= htmlspecialchars(BASE_URL . '/manifest.json', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" type="<?= htmlspecialchars($faviconType, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($title ?? 'Restaurant POS & Management', ENT_QUOTES, 'UTF-8') ?></title>

  <?= \App\Core\CSRF::metaHeader() ?>

  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . '/public/assets/css/bootstrap.min.css', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . '/public/assets/css/serveflow.css', ENT_QUOTES, 'UTF-8') ?>?v=4">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <?php
    $primaryColor = $restaurant['pos_primary_color'] ?? '#2563eb';
    $secondaryColor = $restaurant['pos_secondary_color'] ?? '#059669';
  ?>
<style>
    :root {
      --pos-primary: <?= htmlspecialchars((string)$primaryColor, ENT_QUOTES, 'UTF-8') ?>;
      --pos-secondary: <?= htmlspecialchars((string)$secondaryColor, ENT_QUOTES, 'UTF-8') ?>;
      --app-sidebar-width: 352px;
      --app-ink: #172033;
      --app-muted: #718096;
      --app-line: #e7ebf2;
      --app-page: #f5f7fb;
      --app-surface: #ffffff;
    }
</style>

<script>
(function () {
    function installServeFlowBackButton() {
        if (/\/dashboard\/?$/.test(window.location.pathname)) return;

        const target =
            document.querySelector('.pos-brand-lockup') ||
            document.querySelector('.app-topbar-left');

        if (!target || target.querySelector('.app-back-button')) return;

        const back = document.createElement('a');
        back.className = 'app-back-button';
        back.href = '<?= BASE_URL ?>/dashboard';
        back.setAttribute('aria-label', 'Back');
        back.innerHTML = '<i class="bi bi-arrow-left"></i><span>Back</span>';

        target.insertBefore(back, target.firstChild);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installServeFlowBackButton);
    } else {
        installServeFlowBackButton();
    }
})();
</script>

</head>
<body>
