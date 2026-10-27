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
      --app-sidebar-width: 352px;
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

    /* ServeFlow shared UI system: consistent controls and roomier navigation. */
    :root {
      --app-control-radius: 10px;
      --app-control-height: 44px;
      --app-control-border: #d7dfeb;
      --app-control-shadow: 0 1px 2px rgba(16, 24, 40, .04);
      --app-focus-ring: 0 0 0 .22rem color-mix(in srgb, var(--pos-primary) 16%, transparent);
    }

    /* Use one shape, height, type scale, and focus treatment across management pages. */
    .app-main .btn:not(.btn-close),
    .app-topbar-pos,
    .app-sidebar-pos,
    .app-user-button,
    .app-mobile-toggle {
      border-radius: var(--app-control-radius);
      font-weight: 650;
      transition: background-color .16s ease, border-color .16s ease, color .16s ease,
        box-shadow .16s ease, transform .16s ease;
    }
    .app-main .btn:not(.btn-close) {
      min-height: var(--app-control-height);
      padding: .62rem .95rem;
      gap: .45rem;
      line-height: 1.2;
      box-shadow: none;
      text-decoration: none;
    }
    .app-main .btn.btn-sm:not(.btn-close) {
      min-height: 36px;
      padding: .42rem .72rem;
      font-size: .86rem;
    }
    .app-main .btn.btn-lg:not(.btn-close) {
      min-height: 48px;
      padding: .78rem 1.15rem;
    }
    .app-main .btn.btn-primary,
    .app-main .btn.btn-success,
    .app-main .btn.btn-danger,
    .app-main .btn.btn-warning,
    .app-main .btn.btn-pos-primary {
      border-color: transparent;
      box-shadow: 0 2px 4px rgba(15, 23, 42, .08);
    }
    .app-main .btn.btn-primary {
      background: var(--pos-primary);
      border-color: var(--pos-primary);
      color: #fff;
    }
    .app-main .btn.btn-primary:hover {
      filter: brightness(.94);
      box-shadow: 0 4px 10px rgba(15, 23, 42, .12);
      transform: translateY(-1px);
    }
    .app-main .btn.btn-outline-primary {
      color: var(--pos-primary);
      border-color: color-mix(in srgb, var(--pos-primary) 36%, #d7dfeb);
      background: #fff;
    }
    .app-main .btn.btn-outline-primary:hover {
      color: var(--pos-primary);
      background: color-mix(in srgb, var(--pos-primary) 7%, white);
      border-color: var(--pos-primary);
    }
    .app-main .btn.btn-outline-secondary,
    .app-main .btn.btn-secondary {
      border-color: #d7dfeb;
    }
    .app-main .btn:disabled,
    .app-main .btn.disabled {
      opacity: .58;
      box-shadow: none;
      transform: none;
      cursor: not-allowed;
    }
    .app-main .btn:focus-visible,
    .app-topbar-pos:focus-visible,
    .app-sidebar-pos:focus-visible,
    .app-user-button:focus-visible,
    .app-mobile-toggle:focus-visible {
      outline: 0;
      box-shadow: var(--app-focus-ring);
    }

    /* Inputs and search fields share the same height, border, padding, and focus state. */
    .app-main .form-control,
    .app-main .form-select,
    .app-main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]),
    .app-main select,
    .app-main textarea {
      border: 1px solid var(--app-control-border);
      border-radius: var(--app-control-radius);
      color: #1f2937;
      background-color: #fff;
      box-shadow: var(--app-control-shadow);
      font-size: .94rem;
      line-height: 1.4;
      transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
    }
    .app-main .form-control,
    .app-main .form-select,
    .app-main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]),
    .app-main select {
      min-height: var(--app-control-height);
      padding: .62rem .82rem;
    }
    .app-main textarea,
    .app-main textarea.form-control {
      min-height: 100px;
      padding: .75rem .82rem;
      resize: vertical;
    }
    .app-main .form-control::placeholder,
    .app-main input::placeholder,
    .app-main textarea::placeholder {
      color: #8a96a8;
      opacity: 1;
    }
    .app-main .form-control:focus,
    .app-main .form-select:focus,
    .app-main input:focus,
    .app-main select:focus,
    .app-main textarea:focus {
      border-color: color-mix(in srgb, var(--pos-primary) 70%, white);
      box-shadow: var(--app-focus-ring);
      outline: 0;
    }
    .app-main input[type="checkbox"],
    .app-main input[type="radio"] {
      accent-color: var(--pos-primary);
    }
    .app-main .input-group > .form-control,
    .app-main .input-group > .form-select,
    .app-main .input-group > .btn {
      min-height: var(--app-control-height);
    }

    /* Search forms should stay compact instead of stretching awkwardly on wide screens. */
    .app-main form[method="get"],
    .app-main form[method="GET"] {
      align-items: center;
    }
    .app-main form[method="get"] input[name="q"],
    .app-main form[method="GET"] input[name="q"] {
      flex: 1 1 260px;
      width: 1%;
      min-width: 180px;
    }
    .app-main form[method="get"] button,
    .app-main form[method="GET"] button {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    /* A little more room for long menu names; keep mobile navigation inside the viewport. */
    .app-sidebar { padding-left: 17px; padding-right: 17px; }
    .app-brand { gap: 12px; padding-left: 7px; padding-right: 7px; }
    .app-brand-mark { flex-basis: 45px; width: 45px; height: 45px; }
    .app-nav-link { gap: 13px; padding-left: 13px; padding-right: 13px; }
    .app-nav-link span { overflow-wrap: anywhere; }
    .app-main { min-width: 0; }
    @media (max-width: 991.98px) {
      :root { --app-sidebar-width: 352px; }
      .app-sidebar { width: min(var(--app-sidebar-width), calc(100vw - 32px)); }
    }
    @media (max-width: 575.98px) {
      .app-main form[method="get"],
      .app-main form[method="GET"] {
        width: 100%;
        min-width: 0;
      }
      .app-main form[method="get"] input[name="q"],
      .app-main form[method="GET"] input[name="q"] {
        min-width: 0;
      }
    }


    /* Sidebar collapse and persistent light/dark theme */
    :root { --app-sidebar-collapsed-width: 82px; --app-surface: #fff; --app-page-bg: #f4f7fb; --app-text: #172033; }
    .app-sidebar-collapse-toggle, .app-theme-toggle {
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      min-width:42px; min-height:42px; padding:9px 11px; border:1px solid #e1e7f0;
      border-radius:11px; background:#fff; color:#475569; cursor:pointer;
      transition:background .15s ease,border-color .15s ease,color .15s ease;
    }
    .app-sidebar-collapse-toggle:hover, .app-theme-toggle:hover { background:#f1f5f9; border-color:#cbd5e1; color:#1d4ed8; }
    .app-sidebar-collapse-toggle { margin-right:1px; }
    .app-sidebar-collapse-toggle .bi { transition:transform .18s ease; }
    body.sidebar-collapsed .app-sidebar { width:var(--app-sidebar-collapsed-width); padding-left:12px; padding-right:12px; }
    body.sidebar-collapsed .app-main { margin-left:var(--app-sidebar-collapsed-width); }
    body.sidebar-collapsed .app-brand { justify-content:center; padding-left:0; padding-right:0; }
    body.sidebar-collapsed .app-brand-copy,
    body.sidebar-collapsed .app-sidebar-context span:not(.context-dot),
    body.sidebar-collapsed .app-nav-heading,
    body.sidebar-collapsed .app-nav-link span,
    body.sidebar-collapsed .app-sidebar-pos .flex-grow-1,
    body.sidebar-collapsed .app-sidebar-pos > .bi-arrow-up-right,
    body.sidebar-collapsed .app-sidebar-version { display:none!important; }
    body.sidebar-collapsed .app-sidebar-context { justify-content:center; margin-left:0; margin-right:0; padding:10px 0; }
    body.sidebar-collapsed .app-nav-group { margin-bottom:14px; }
    body.sidebar-collapsed .app-nav-link { justify-content:center; padding:11px 0; min-height:46px; margin:4px 0; }
    body.sidebar-collapsed .app-nav-link i { width:auto; font-size:1.2rem; }
    body.sidebar-collapsed .app-sidebar-pos { justify-content:center; padding:10px 0; }
    body.sidebar-collapsed .app-sidebar-pos .pos-icon { flex-basis:36px; }
    body.sidebar-collapsed .app-sidebar-bottom { padding-top:14px; }
    body.sidebar-collapsed .app-sidebar-collapse-toggle .bi { transform:rotate(180deg); }
    body.sidebar-collapsed .app-sidebar [title]:hover::after { content:attr(title); }
    @media (max-width:991.98px) {
      .app-sidebar-collapse-toggle { display:none!important; }
      body.sidebar-collapsed .app-sidebar { width:min(var(--app-sidebar-width),calc(100vw - 32px)); padding-left:17px; padding-right:17px; }
      body.sidebar-collapsed .app-main { margin-left:0; }
      body.sidebar-collapsed .app-brand { justify-content:flex-start; padding-left:7px; padding-right:7px; }
      body.sidebar-collapsed .app-brand-copy,
      body.sidebar-collapsed .app-sidebar-context span:not(.context-dot),
      body.sidebar-collapsed .app-nav-heading,
      body.sidebar-collapsed .app-nav-link span,
      body.sidebar-collapsed .app-sidebar-pos .flex-grow-1,
      body.sidebar-collapsed .app-sidebar-pos > .bi-arrow-up-right,
      body.sidebar-collapsed .app-sidebar-version { display:initial!important; }
      body.sidebar-collapsed .app-sidebar-context { justify-content:flex-start; margin:0 7px 19px; padding:9px 10px; }
      body.sidebar-collapsed .app-nav-link { justify-content:flex-start; padding:10px 12px; }
      body.sidebar-collapsed .app-nav-link span { display:inline!important; }
      body.sidebar-collapsed .app-nav-link i { width:19px; }
      body.sidebar-collapsed .app-sidebar-pos { justify-content:flex-start; padding:12px; }
    }

    html[data-theme="dark"] { color-scheme:dark; }
    html[data-theme="dark"] body { --app-surface:#182235; --app-page-bg:#0f1726; --app-text:#e5ebf5; background:var(--app-page-bg)!important; color:var(--app-text)!important; }
    html[data-theme="dark"] .app-topbar { background:rgba(20,30,47,.95); border-color:#2a374b; }
    html[data-theme="dark"] .app-page-context strong { color:#edf2fb; }
    html[data-theme="dark"] .app-page-context small,
    html[data-theme="dark"] .app-footer { color:#a4b0c3; }
    html[data-theme="dark"] .app-theme-toggle,
    html[data-theme="dark"] .app-sidebar-collapse-toggle { background:#202d42; color:#e5ebf5; border-color:#3a4960; }
    html[data-theme="dark"] .app-theme-toggle:hover,
    html[data-theme="dark"] .app-sidebar-collapse-toggle:hover { background:#2b3b54; }
    html[data-theme="dark"] .app-main { background:var(--app-page-bg); }
    html[data-theme="dark"] .app-main .card,
    html[data-theme="dark"] .app-main .modal-content,
    html[data-theme="dark"] .app-main .dropdown-menu,
    html[data-theme="dark"] .app-main .list-group-item,
    html[data-theme="dark"] .app-main .table,
    html[data-theme="dark"] .app-main .bg-white,
    html[data-theme="dark"] .app-main .bg-light,
    html[data-theme="dark"] .app-main .card-header,
    html[data-theme="dark"] .app-main .card-footer { background-color:#182235!important; color:#e5ebf5; border-color:#344156!important; }
    html[data-theme="dark"] .app-main .text-dark,
    html[data-theme="dark"] .app-main .card-title,
    html[data-theme="dark"] .app-main h1,
    html[data-theme="dark"] .app-main h2,
    html[data-theme="dark"] .app-main h3,
    html[data-theme="dark"] .app-main h4,
    html[data-theme="dark"] .app-main h5,
    html[data-theme="dark"] .app-main h6 { color:#edf2fb; }
    html[data-theme="dark"] .app-main .text-muted,
    html[data-theme="dark"] .app-main .form-text { color:#a7b4c8!important; }
    html[data-theme="dark"] .app-main .form-control,
    html[data-theme="dark"] .app-main .form-select,
    html[data-theme="dark"] .app-main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]),
    html[data-theme="dark"] .app-main select,
    html[data-theme="dark"] .app-main textarea { background-color:#111b2b!important; color:#e5ebf5!important; border-color:#3a4960!important; }
    html[data-theme="dark"] .app-main input::placeholder,
    html[data-theme="dark"] .app-main textarea::placeholder { color:#8998ae!important; }
    html[data-theme="dark"] .app-main .table { --bs-table-color:#e5ebf5; --bs-table-bg:#182235; --bs-table-border-color:#344156; --bs-table-striped-bg:#202d42; --bs-table-hover-bg:#26354b; }
    html[data-theme="dark"] .app-main .table thead th { color:#b8c5d8; }
    html[data-theme="dark"] .app-main hr,
    html[data-theme="dark"] .app-footer { border-color:#2a374b!important; }
    html[data-theme="dark"] .app-main .dropdown-item { color:#e5ebf5; }
    html[data-theme="dark"] .app-main .dropdown-item:hover { background:#26354b; }
    html[data-theme="dark"] .app-main .alert { filter:brightness(.9); }
    html[data-theme="dark"] .app-user-button { background:#202d42; border-color:#3a4960; color:#e5ebf5; }
    html[data-theme="dark"] .app-user-avatar { background:#263b5d; color:#a9c8ff; }
    @media (prefers-reduced-motion:reduce) { .app-sidebar,.app-sidebar-collapse-toggle,.app-theme-toggle { transition:none!important; } }

  </style>
</head>
<body>
