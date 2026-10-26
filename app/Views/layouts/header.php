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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <?php
    $primaryColor = $restaurant['pos_primary_color'] ?? '#2563eb';
    $secondaryColor = $restaurant['pos_secondary_color'] ?? '#059669';
  ?>
  <style>
    :root {
      --pos-primary: <?= htmlspecialchars((string)$primaryColor, ENT_QUOTES, 'UTF-8') ?>;
      --pos-secondary: <?= htmlspecialchars((string)$secondaryColor, ENT_QUOTES, 'UTF-8') ?>;
      --app-sidebar-width: 272px;
      --app-ink: #172033;
      --app-muted: #718096;
      --app-line: #e7ebf2;
      --app-page: #f5f7fb;
      --app-surface: #ffffff;
    }

    * { box-sizing: border-box; }
    html { min-height: 100%; }
    body {
      margin: 0;
      min-height: 100vh;
      background: var(--app-page);
      color: var(--app-ink);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
      font-size: .94rem;
      -webkit-font-smoothing: antialiased;
    }
    a { text-underline-offset: 3px; }
    .navbar-brand-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 36px; height: 36px; border-radius: 11px;
      background: var(--pos-primary); color: #fff; font-size: .8rem; font-weight: 800;
      letter-spacing: -.03em;
    }
    .btn-pos-primary { background: var(--pos-primary); border-color: var(--pos-primary); color: #fff; }
    .btn-pos-primary:hover { filter: brightness(.92); color: #fff; }
    .status-pill { font-size: .72rem; padding: 6px 10px; border-radius: 999px; font-weight: 700; white-space: nowrap; }
    .card-metric {
      border: 1px solid rgba(226,232,240,.8) !important; border-radius: 16px;
      box-shadow: 0 4px 16px rgba(15,23,42,.035); transition: transform .15s ease, box-shadow .15s ease;
    }
    .card-metric:hover { transform: translateY(-2px); box-shadow: 0 9px 24px rgba(15,23,42,.07); }
    .card, .modal-content { border-radius: 16px; }
    .card { border-color: #e8edf5; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .card-header { border-bottom-color: #edf0f5; border-top-left-radius: 16px !important; border-top-right-radius: 16px !important; }
    .btn:not(.btn-close) {
      --bs-btn-padding-y: 0.7rem;
      border-radius: 10px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      vertical-align: middle;
      line-height: 1.25;
      gap: .35rem;
      transition: color .15s ease-in-out, background-color .15s ease-in-out, border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    .btn:not(.btn-close) > .bi,
    .btn:not(.btn-close) > i {
      display: inline-block;
      flex: 0 0 auto;
      line-height: 1;
      vertical-align: middle;
    }
    .btn:not(.btn-close):focus-visible {
      outline: 0;
      box-shadow: 0 0 0 .2rem rgba(37,99,235,.2);
    }
    .form-control, .form-select { border-color: #dbe2ec; border-radius: 10px; min-height: 42px; }
    .form-control:focus, .form-select:focus { border-color: var(--pos-primary); box-shadow: 0 0 0 .2rem rgba(37,99,235,.13); }
    .table > :not(caption) > * > * { padding: .85rem .9rem; }
    .table thead th { color: #657184; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .045em; white-space: nowrap; }
    .badge { letter-spacing: .01em; }

    /* Main application shell */
    .app-shell { min-height: 100vh; }
    .app-sidebar {
      position: fixed; inset: 0 auto 0 0; z-index: 1045;
      width: var(--app-sidebar-width); display: flex; flex-direction: column;
      padding: 22px 15px 16px; overflow-y: auto; overflow-x: hidden;
      color: #d5deee; background: #111827;
      border-right: 1px solid rgba(255,255,255,.055);
      box-shadow: 8px 0 28px rgba(15,23,42,.05);
      transition: transform .22s ease;
    }
    .app-brand {
      display: flex; align-items: center; gap: 11px; min-width: 0;
      padding: 0 8px 22px; color: #fff; text-decoration: none;
      border-bottom: 1px solid rgba(255,255,255,.09); margin-bottom: 21px;
    }
    .app-brand-mark {
      flex: 0 0 43px; width: 43px; height: 43px; display: grid; place-items: center;
      overflow: hidden; border-radius: 13px; color: #fff; background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.12); font-size: 1.2rem;
    }
    .app-brand-mark img { width: 100%; height: 100%; object-fit: contain; background: #fff; padding: 3px; }
    .app-brand-copy { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
    .app-brand-copy strong { font-size: .9rem; font-weight: 800; letter-spacing: -.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .app-brand-copy small { margin-top: 5px; color: #92a0b6; font-size: .68rem; letter-spacing: .025em; }
    .app-sidebar-context {
      display: flex; align-items: center; gap: 8px; margin: 0 7px 19px; padding: 9px 10px;
      border-radius: 10px; background: rgba(255,255,255,.055); color: #aebbd0; font-size: .73rem;
    }
    .app-sidebar-context .context-dot { width: 7px; height: 7px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 4px rgba(52,211,153,.11); }
    .app-nav-group { margin-bottom: 19px; }
    .app-nav-heading { margin: 0 10px 8px; color: #7f8ca3; font-size: .64rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
    .app-nav-link {
      display: flex; align-items: center; gap: 12px; min-height: 43px;
      padding: 10px 12px; margin: 3px 0; color: #aebbd0; text-decoration: none;
      font-size: .83rem; font-weight: 600; border-radius: 11px;
      transition: color .15s ease, background .15s ease, transform .15s ease;
    }
    .app-nav-link i { width: 19px; text-align: center; font-size: 1.02rem; opacity: .92; }
    .app-nav-link:hover { background: rgba(255,255,255,.07); color: #fff; transform: translateX(1px); }
    .app-nav-link.active {
      color: #fff; background: linear-gradient(100deg, rgba(59,130,246,.24), rgba(59,130,246,.10));
      box-shadow: inset 3px 0 var(--pos-primary), inset 0 0 0 1px rgba(147,197,253,.08);
    }
    .app-sidebar-bottom { margin-top: auto; padding-top: 18px; border-top: 1px solid rgba(255,255,255,.09); }
    .app-sidebar-pos {
      display: flex; align-items: center; gap: 10px; padding: 12px;
      border-radius: 13px; border: 1px solid rgba(96,165,250,.25);
      background: rgba(37,99,235,.13); color: #fff; text-decoration: none;
    }
    .app-sidebar-pos:hover { color: #fff; background: rgba(37,99,235,.22); }
    .app-sidebar-pos .pos-icon { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #2563eb; font-size: 1rem; }
    .app-sidebar-pos strong { display: block; font-size: .8rem; }
    .app-sidebar-pos small { display: block; margin-top: 3px; color: #9fb6dc; font-size: .68rem; }
    .app-sidebar-version { padding: 12px 7px 0; color: #66748b; font-size: .66rem; }

    .app-main { display: flex; flex-direction: column; min-height: 100vh; min-width: 0; margin-left: var(--app-sidebar-width); }
    .app-topbar {
      position: sticky; top: 0; z-index: 1020; min-height: 78px;
      display: flex; align-items: center; justify-content: space-between; gap: 20px;
      padding: 13px 27px; background: rgba(255,255,255,.93); backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--app-line);
    }
    .app-topbar-left { display: flex; align-items: center; gap: 13px; min-width: 0; }
    .app-mobile-toggle {
      display: none; width: 40px; height: 40px; border: 1px solid #e1e7f0; border-radius: 11px;
      background: #fff; color: #475569; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .app-page-context { min-width: 0; }
    .app-page-context small { display: block; color: #8994a5; font-size: .64rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
    .app-page-context strong { display: block; margin-top: 3px; color: #172033; font-size: 1.02rem; font-weight: 750; letter-spacing: -.025em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .app-topbar-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; min-width: 0; }
    .app-connectivity { display: inline-flex; align-items: center; gap: 7px; color: #13835d; background: #eafaf3; border: 1px solid #c9f0df; }
    .app-connectivity .context-dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; }
    .app-topbar-pos { display: inline-flex; align-items: center; gap: 8px; padding: 10px 13px; color: #fff; background: #2563eb; border: 1px solid #2563eb; border-radius: 10px; font-size: .78rem; text-decoration: none; font-weight: 750; box-shadow: 0 3px 9px rgba(37,99,235,.17); white-space: nowrap; }
    .app-topbar-pos:hover { color: #fff; background: #1d4ed8; }
    .app-user-button { display: flex; align-items: center; gap: 9px; padding: 6px 8px 6px 10px; color: #344054; background: #fff; border: 1px solid #e2e8f0; border-radius: 11px; max-width: 255px; }
    .app-user-avatar { width: 30px; height: 30px; display: grid; place-items: center; flex: 0 0 30px; color: #1d4ed8; background: #eff6ff; border-radius: 9px; }
    .app-user-copy { display: flex; flex-direction: column; text-align: left; min-width: 0; line-height: 1.15; }
    .app-user-copy strong { font-size: .74rem; font-weight: 750; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .app-user-copy small { font-size: .66rem; margin-top: 4px; color: #8a94a5; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .app-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: auto; padding: 18px 28px; color: #8b95a6; font-size: .69rem; border-top: 1px solid var(--app-line); }
    .app-sidebar-backdrop { display: none; }
    @media (max-width: 1199.98px) {
      .app-topbar { padding-left: 20px; padding-right: 20px; }
      .app-topbar-actions { gap: 7px; }
      .app-user-button { max-width: 205px; }
      .app-connectivity { display: none; }
    }
    @media (max-width: 991.98px) {
      .app-sidebar { width: min(var(--app-sidebar-width), calc(100vw - 48px)); transform: translateX(-105%); }
      .app-sidebar.is-open { transform: translateX(0); }
      .app-sidebar-backdrop.is-visible { display: block; position: fixed; inset: 0; z-index: 1040; border: 0; padding: 0; background: rgba(15,23,42,.5); backdrop-filter: blur(2px); }
      .app-main { margin-left: 0; }
      .app-mobile-toggle { display: inline-flex; }
      .app-topbar { min-height: 70px; padding: 12px 16px; }
    }
    @media (max-width: 575.98px) {
      .app-topbar { align-items: center; gap: 8px; padding: 10px 11px; }
      .app-topbar-left { gap: 8px; }
      .app-page-context small { font-size: .55rem; }
      .app-page-context strong { font-size: .86rem; max-width: 130px; }
      .app-topbar-pos { width: 39px; height: 39px; justify-content: center; padding: 0; font-size: 0; gap: 0; }
      .app-topbar-pos i { font-size: 1rem; }
      .app-user-button { max-width: 48px; padding: 4px; gap: 3px; border: 0; }
      .app-user-copy, .app-user-button .user-chevron, .app-user-button .dropdown-toggle::after { display: none; }
      .app-user-avatar { width: 34px; height: 34px; flex-basis: 34px; }
      .app-footer { padding: 15px; flex-direction: column; align-items: flex-start; }
    }

    /* Accessibility and readability pass: larger type, clearer controls, calmer spacing. */
    html { font-size: 16px; scroll-behavior: smooth; }
    body { font-size: 1rem; line-height: 1.55; }
    p, .form-text, .alert { line-height: 1.55; }
    .text-muted { color: #606d80 !important; }
    .small, small, .form-text { font-size: .875rem !important; }
    .app-brand-copy strong { font-size: 1rem; }
    .app-brand-copy small { font-size: .75rem; line-height: 1.4; }
    .app-sidebar-context { font-size: .84rem; }
    .app-nav-heading { font-size: .72rem; letter-spacing: .1em; }
    .app-nav-link { min-height: 46px; font-size: .92rem; line-height: 1.35; }
    .app-nav-link i { font-size: 1.12rem; }
    .app-page-context small { font-size: .72rem; }
    .app-page-context strong { font-size: 1.08rem; }
    .app-user-copy strong { font-size: .84rem; }
    .app-user-copy small { font-size: .75rem; }
    .app-footer { font-size: .8rem; }
    .status-pill { font-size: .8rem; }
    .badge { font-size: .78rem; padding: .4em .6em; }
    .table { font-size: .94rem; }
    .table thead th { font-size: .78rem; line-height: 1.4; }
    .table > :not(caption) > * > * { padding: .9rem .95rem; }
    .form-label { font-size: .92rem; font-weight: 600; margin-bottom: .4rem; }
    .form-control, .form-select { min-height: 46px; font-size: 1rem; }
    textarea.form-control { min-height: 110px; }
    .btn:not(.btn-close) { font-size: .94rem; min-height: 42px; }
    .btn.btn-sm:not(.btn-close) { min-height: 38px; font-size: .86rem; }
    .btn.btn-lg:not(.btn-close) { min-height: 48px; font-size: 1.02rem; }
    .card-body { line-height: 1.55; }
    .card-header { padding-top: .9rem; padding-bottom: .9rem; }
    .alert { font-size: .95rem; }
    input, select, textarea, button { scroll-margin-top: 100px; }
    :focus-visible { outline: 3px solid rgba(37,99,235,.65); outline-offset: 2px; }
    a, button, input, select, textarea { touch-action: manipulation; }
    @media (max-width: 575.98px) {
      body { font-size: .98rem; }
      .container, .container-fluid { --bs-gutter-x: 1.15rem; }
      .table > :not(caption) > * > * { padding: .75rem .7rem; }
      .app-page-context strong { font-size: .95rem; max-width: 150px; }
      .btn:not(.btn-close) { min-height: 44px; }
      .btn.btn-sm:not(.btn-close) { min-height: 40px; }
    }
    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
      *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
    }
  </style>
</head>
<body>
