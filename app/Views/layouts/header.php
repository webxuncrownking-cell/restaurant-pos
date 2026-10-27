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
<script id="serveflow-theme-prepaint">
(function () {
  var root = document.documentElement;
  try {
    var savedTheme = localStorage.getItem('serveflow-theme');
    // The default is light, so the first paint matches the footer's default instead of flashing dark.
    root.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
    if (localStorage.getItem('serveflow-sidebar-collapsed') === '1') {
      root.setAttribute('data-sidebar-collapsed', 'true');
    } else {
      root.removeAttribute('data-sidebar-collapsed');
    }
  } catch (e) {
    root.setAttribute('data-theme', 'light');
    root.removeAttribute('data-sidebar-collapsed');
  }
})();
</script>
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


    /* Refined workspace theme: light navigation in light mode, cohesive dark surfaces. */
    html[data-theme="light"] body { background:#f3f5f9; color:#202b3c; }
    html[data-theme="light"] .app-sidebar {
      color:#475569; background:#e9edf4; border-right:1px solid #d8e0eb;
      box-shadow:5px 0 20px rgba(15,23,42,.045);
    }
    html[data-theme="light"] .app-brand { color:#172033; border-bottom-color:#d5dde8; }
    html[data-theme="light"] .app-brand-mark { color:#1d4ed8; background:#fff; border-color:#d7e0ec; }
    html[data-theme="light"] .app-brand-copy small { color:#64748b; }
    html[data-theme="light"] .app-sidebar-context { color:#475569; background:#f7f9fc; border:1px solid #dce3ed; }
    html[data-theme="light"] .app-nav-heading { color:#78869b; }
    html[data-theme="light"] .app-nav-link { color:#475569; }
    html[data-theme="light"] .app-nav-link:hover { background:#dce4ef; color:#172033; }
    html[data-theme="light"] .app-nav-link.active { color:#1748a0; background:#d6e5ff; box-shadow:inset 3px 0 #2563eb; }
    html[data-theme="light"] .app-sidebar-bottom { border-top-color:#d5dde8; }
    html[data-theme="light"] .app-sidebar-pos { color:#1748a0; background:#dce9ff; border-color:#c2d7ff; }
    html[data-theme="light"] .app-sidebar-pos:hover { color:#123d8a; background:#cfe0ff; }
    html[data-theme="light"] .app-sidebar-pos small { color:#526b94; }
    html[data-theme="light"] .app-sidebar-version { color:#78869b; }
    html[data-theme="dark"] .app-sidebar { background:#111a2a; border-right-color:#26354a; }
    html[data-theme="dark"] .app-brand { color:#f1f5fb; border-bottom-color:#2b394e; }
    html[data-theme="dark"] .app-sidebar-context { color:#c0ccdc; background:#1b293d; border-color:#2d3b50; }
    html[data-theme="dark"] .app-nav-link { color:#b6c2d4; }
    html[data-theme="dark"] .app-nav-link:hover { color:#fff; background:#223149; }
    html[data-theme="dark"] .app-nav-link.active { color:#fff; background:#20385d; box-shadow:inset 3px 0 #60a5fa; }
    html[data-theme="dark"] .app-nav-heading { color:#8797af; }
    html[data-theme="dark"] .app-sidebar-bottom { border-top-color:#2b394e; }
    html[data-theme="dark"] .app-sidebar-pos { background:#172b49; border-color:#2d4b77; }
    html[data-theme="dark"] .app-sidebar-version { color:#8493aa; }

    /* Keep the collapse handle centered on the sidebar/content boundary. */
    .app-sidebar-collapse-toggle {
      position:fixed; z-index:1050; top:50%; left:calc(var(--app-sidebar-width) - 16px);
      transform:translateY(-50%); width:32px; min-width:32px; height:48px; min-height:48px;
      padding:0; border:1px solid #d4dce8; border-radius:0 12px 12px 0;
      background:#fff; color:#53647b; box-shadow:2px 3px 10px rgba(15,23,42,.12);
    }
    .app-sidebar-collapse-toggle:hover { background:#eff5ff; color:#1d4ed8; border-color:#b8cdf2; }
    .app-sidebar-collapse-toggle .bi { font-size:.8rem; transition:transform .18s ease; }
    body.sidebar-collapsed .app-sidebar-collapse-toggle { left:calc(var(--app-sidebar-collapsed-width) - 16px); }
    body.sidebar-collapsed .app-sidebar-collapse-toggle .bi { transform:none; }
    .app-topbar-left { gap:12px; }
    .app-theme-switch { display:inline-flex; align-items:center; gap:9px; padding:6px 10px; border:1px solid #dce3ed; border-radius:999px; background:#fff; color:#475569; cursor:pointer; user-select:none; white-space:nowrap; }
    .app-theme-switch input { position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; }
    .app-theme-switch-track { position:relative; width:38px; height:22px; flex:0 0 38px; border-radius:999px; background:#cbd5e1; box-shadow:inset 0 0 0 1px rgba(15,23,42,.08); transition:background .18s ease; }
    .app-theme-switch-track::after { content:""; position:absolute; width:16px; height:16px; left:3px; top:3px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.25); transition:transform .18s ease; }
    .app-theme-switch input:checked + .app-theme-switch-track { background:#2563eb; }
    .app-theme-switch input:checked + .app-theme-switch-track::after { transform:translateX(16px); }
    .app-theme-switch input:focus-visible + .app-theme-switch-track { outline:3px solid rgba(37,99,235,.35); outline-offset:3px; }
    .app-theme-switch-label { font-size:.8rem; font-weight:650; }
    html[data-theme="dark"] .app-theme-switch { color:#e5ebf5; background:#202d42; border-color:#3a4960; }
    html[data-theme="dark"] .app-theme-switch-track { background:#52627a; }
    html[data-theme="dark"] .app-theme-switch input:checked + .app-theme-switch-track { background:#3b82f6; }

    /* More complete dark-mode support for common Bootstrap and custom page surfaces. */
    html[data-theme="dark"] .app-main .container,
    html[data-theme="dark"] .app-main .container-fluid { color:#e5ebf5; }
    html[data-theme="dark"] .app-main .table-responsive { color:#e5ebf5; }
    html[data-theme="dark"] .app-main .form-label,
    html[data-theme="dark"] .app-main label { color:#d5dfed; }
    html[data-theme="dark"] .app-main .input-group-text { background:#202d42; color:#d5dfed; border-color:#3a4960; }
    html[data-theme="dark"] .app-main .border,
    html[data-theme="dark"] .app-main .border-top,
    html[data-theme="dark"] .app-main .border-bottom,
    html[data-theme="dark"] .app-main .border-start,
    html[data-theme="dark"] .app-main .border-end { border-color:#344156!important; }
    html[data-theme="dark"] .app-main .accordion-item,
    html[data-theme="dark"] .app-main .accordion-button { background:#182235; color:#e5ebf5; border-color:#344156; }
    html[data-theme="dark"] .app-main .accordion-button:not(.collapsed) { background:#20385d; color:#eff6ff; box-shadow:none; }
    html[data-theme="dark"] .app-main .page-link { background:#182235; color:#b9d2ff; border-color:#344156; }
    html[data-theme="dark"] .app-main .page-item.active .page-link { background:#2563eb; color:#fff; border-color:#2563eb; }
    html[data-theme="dark"] .app-main .nav-tabs { border-color:#344156; }
    html[data-theme="dark"] .app-main .nav-tabs .nav-link { color:#b7c5d9; }
    html[data-theme="dark"] .app-main .nav-tabs .nav-link.active { background:#182235; color:#eff6ff; border-color:#344156 #344156 #182235; }
    html[data-theme="dark"] .app-main .btn-outline-secondary { color:#cbd5e1; border-color:#64748b; }
    html[data-theme="dark"] .app-main .btn-outline-secondary:hover { background:#334155; color:#fff; }
    html[data-theme="dark"] .app-main .btn-light,
    html[data-theme="dark"] .app-main .btn-outline-light { background:#26354b; color:#e5ebf5; border-color:#46566d; }
    html[data-theme="dark"] .app-main .progress { background:#2b394e; }
    html[data-theme="dark"] .app-main .list-group-item-action:hover { background:#26354b; }
    html[data-theme="dark"] .app-main .dropdown-divider { border-color:#344156; }
    html[data-theme="dark"] .app-main .modal-header,
    html[data-theme="dark"] .app-main .modal-footer { border-color:#344156; }
    html[data-theme="dark"] .app-main .btn-close { filter:invert(1) grayscale(100%) brightness(200%); }
    html[data-theme="dark"] .app-main .alert-success { color:#b7f7d5; background:#12382e; border-color:#23644f; }
    html[data-theme="dark"] .app-main .alert-danger { color:#fecaca; background:#451f28; border-color:#7f303c; }
    html[data-theme="dark"] .app-main .alert-warning { color:#fde68a; background:#493718; border-color:#806027; }
    html[data-theme="dark"] .app-main .alert-info { color:#bae6fd; background:#12364a; border-color:#245b77; }
    @media (max-width:991.98px) { .app-sidebar-collapse-toggle { display:none!important; } }
    @media (max-width:575.98px) { .app-theme-switch { padding:5px 7px; gap:6px; } .app-theme-switch-label { display:none; } }


    /* Avoid a light-theme flash while the saved theme is being applied. */
    html { background:#f3f5f9; }
    html[data-theme="dark"] { background:#0f1726; }
    html[data-theme="dark"] body { transition:none!important; }
    html[data-theme="light"] body { transition:none!important; }
    html[data-theme="light"] .app-sidebar {
      background:linear-gradient(180deg,#f8faff 0%,#e8eef8 100%);
      border-right-color:#cbd8ea;
    }
    html[data-theme="light"] .app-nav-link { border:1px solid transparent; }
    html[data-theme="light"] .app-nav-link:hover { background:#dbeafe; border-color:#c4dafa; color:#123d83; }
    html[data-theme="light"] .app-nav-link.active { background:linear-gradient(100deg,#c7ddff,#e1edff); border-color:#b6d0fb; color:#123f91; box-shadow:inset 4px 0 #2563eb,0 2px 8px rgba(37,99,235,.08); }
    html[data-theme="light"] .app-nav-heading { color:#536b8b; }
    html[data-theme="light"] .app-sidebar-context { background:#fff; border-color:#cbd8ea; box-shadow:0 2px 7px rgba(31,58,95,.05); }
    html[data-theme="light"] .app-sidebar-pos { background:linear-gradient(110deg,#d9e8ff,#eef5ff); border-color:#b9d0f6; box-shadow:0 3px 10px rgba(37,99,235,.08); }
    html[data-theme="light"] .app-sidebar-collapse-toggle { border-color:#c4d3e8; background:#f8fbff; color:#1d4ed8; }
    html[data-theme="dark"] .app-main .form-control,
    html[data-theme="dark"] .app-main .form-select,
    html[data-theme="dark"] .app-main textarea,
    html[data-theme="dark"] .app-main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"]),
    html[data-theme="dark"] .app-main select { background-color:#111b2b!important; color:#e5ebf5!important; border-color:#3a4960!important; }
    html[data-theme="dark"] .app-main input[type="file"] { background:#111b2b!important; color:#cbd5e1!important; border-color:#3a4960!important; }
    html[data-theme="dark"] .app-main .settings-card,
    html[data-theme="dark"] .app-main .reservation-card,
    html[data-theme="dark"] .app-main .supplier-card,
    html[data-theme="dark"] .app-main .expiry-card,
    html[data-theme="dark"] .app-main .expense-card,
    html[data-theme="dark"] .app-main [class*="-panel"],
    html[data-theme="dark"] .app-main [class*="-section"],
    html[data-theme="dark"] .app-main [class*="-card"] { color:#e5ebf5; }
    html[data-theme="dark"] .app-main [style*="background:#fff"],
    html[data-theme="dark"] .app-main [style*="background: #fff"],
    html[data-theme="dark"] .app-main [style*="background-color:#fff"],
    html[data-theme="dark"] .app-main [style*="background-color: #fff"],
    html[data-theme="dark"] .app-main [style*="background: white"],
    html[data-theme="dark"] .app-main [style*="background-color: white"],
    html[data-theme="dark"] .app-main .bg-white,
    html[data-theme="dark"] .app-main .card,
    html[data-theme="dark"] .app-main .modal-content,
    html[data-theme="dark"] .app-main .list-group-item { background-color:#182235!important; color:#e5ebf5!important; border-color:#344156!important; }
    html[data-theme="dark"] .app-main .card *,
    html[data-theme="dark"] .app-main [class*="-card"] *,
    html[data-theme="dark"] .app-main [class*="-panel"] *,
    html[data-theme="dark"] .app-main [class*="-section"] * { border-color:inherit; }
    html[data-theme="dark"] .app-main .card p,
    html[data-theme="dark"] .app-main .card label,
    html[data-theme="dark"] .app-main .card small,
    html[data-theme="dark"] .app-main [class*="-card"] p,
    html[data-theme="dark"] .app-main [class*="-card"] label,
    html[data-theme="dark"] .app-main [class*="-card"] small,
    html[data-theme="dark"] .app-main [class*="-panel"] p,
    html[data-theme="dark"] .app-main [class*="-panel"] label,
    html[data-theme="dark"] .app-main [class*="-panel"] small { color:#b9c6d8; }
    html[data-theme="dark"] .app-main input[type="file"] { padding:0!important; overflow:hidden; min-height:42px; }
    html[data-theme="dark"] .app-main input[type="file"]::file-selector-button {
      height:40px; margin:0 12px 0 0; padding:0 16px; border:0; border-right:1px solid #3a4960;
      background:#26354b; color:#e5ebf5; font:inherit; cursor:pointer;
    }
    html[data-theme="light"] .app-main input[type="file"] { padding:0!important; overflow:hidden; min-height:42px; }
    html[data-theme="light"] .app-main input[type="file"]::file-selector-button {
      height:40px; margin:0 12px 0 0; padding:0 16px; border:0; border-right:1px solid #d7e0ec;
      background:#eef2f7; color:#334155; font:inherit; cursor:pointer;
    }
    @media (prefers-reduced-motion:reduce) { .app-nav-link,.app-sidebar,.app-sidebar-collapse-toggle,.app-theme-switch-track { transition:none!important; } }


    /* Stable initial layout: restore the saved sidebar state before first paint. */
    @media (min-width: 992px) {
      html[data-sidebar-collapsed="true"] .app-sidebar {
        width: var(--app-sidebar-collapsed-width);
        padding-left: 12px;
        padding-right: 12px;
        transition: none !important;
      }
      html[data-sidebar-collapsed="true"] .app-main { margin-left: var(--app-sidebar-collapsed-width); }
      html[data-sidebar-collapsed="true"] .app-brand { justify-content: center; padding-left: 0; padding-right: 0; }
      html[data-sidebar-collapsed="true"] .app-brand-copy,
      html[data-sidebar-collapsed="true"] .app-sidebar-context span:not(.context-dot),
      html[data-sidebar-collapsed="true"] .app-nav-heading,
      html[data-sidebar-collapsed="true"] .app-nav-link span,
      html[data-sidebar-collapsed="true"] .app-sidebar-pos .flex-grow-1,
      html[data-sidebar-collapsed="true"] .app-sidebar-pos > .bi-arrow-up-right,
      html[data-sidebar-collapsed="true"] .app-sidebar-version { display: none !important; }
      html[data-sidebar-collapsed="true"] .app-sidebar-context { justify-content: center; margin-left: 0; margin-right: 0; padding: 10px 0; }
      html[data-sidebar-collapsed="true"] .app-nav-group { margin-bottom: 14px; }
      html[data-sidebar-collapsed="true"] .app-nav-link { justify-content: center; padding: 11px 0; min-height: 46px; margin: 4px 0; }
      html[data-sidebar-collapsed="true"] .app-nav-link i { width: auto; font-size: 1.2rem; }
      html[data-sidebar-collapsed="true"] .app-sidebar-pos { justify-content: center; padding: 10px 0; }
      html[data-sidebar-collapsed="true"] .app-sidebar-pos .pos-icon { flex-basis: 36px; }
      html[data-sidebar-collapsed="true"] .app-sidebar-bottom { padding-top: 14px; }
      html[data-sidebar-collapsed="true"] .app-sidebar-collapse-toggle {
        left: calc(var(--app-sidebar-collapsed-width) - 16px);
        transition: none !important;
      }
      html[data-sidebar-collapsed="true"] .app-sidebar-collapse-toggle .bi { transform: none; }
    }

    /* Brighter, cleaner light sidebar states that still use the restaurant's brand color. */
    html[data-theme="light"] .app-sidebar {
      background: linear-gradient(180deg, #fbfdff 0%, #e6eefb 100%);
      border-right-color: #c7d6eb;
    }
    html[data-theme="light"] .app-nav-link {
      border: 1px solid transparent;
      transform: none;
      transition: color .12s ease, background-color .12s ease, border-color .12s ease, box-shadow .12s ease;
    }
    html[data-theme="light"] .app-nav-link:hover {
      background: color-mix(in srgb, var(--pos-primary) 12%, #fff);
      border-color: color-mix(in srgb, var(--pos-primary) 28%, #fff);
      color: #173f80;
      box-shadow: 0 3px 10px color-mix(in srgb, var(--pos-primary) 12%, transparent);
      transform: none;
    }
    html[data-theme="light"] .app-nav-link.active {
      background: linear-gradient(105deg, color-mix(in srgb, var(--pos-primary) 21%, #fff), color-mix(in srgb, var(--pos-primary) 9%, #fff));
      border-color: color-mix(in srgb, var(--pos-primary) 35%, #fff);
      color: #163c78;
      box-shadow: inset 4px 0 var(--pos-primary), 0 4px 12px color-mix(in srgb, var(--pos-primary) 13%, transparent);
      transform: none;
    }
    html[data-theme="light"] .app-nav-link.active i { color: var(--pos-primary); }

    /* Shared dark tokens make Bootstrap utilities and custom page components agree. */
    html[data-theme="dark"] {
      color-scheme: dark;
      --app-surface: #182235;
      --app-page-bg: #0f1726;
      --app-text: #e5ebf5;
      --bs-body-color: #e5ebf5;
      --bs-body-color-rgb: 229, 235, 245;
      --bs-body-bg: #0f1726;
      --bs-body-bg-rgb: 15, 23, 38;
      --bs-emphasis-color: #f8fafc;
      --bs-emphasis-color-rgb: 248, 250, 252;
      --bs-secondary-color: #a7b4c8;
      --bs-secondary-bg: #202d42;
      --bs-tertiary-bg: #182235;
      --bs-border-color: #344156;
      --bs-heading-color: #edf2fb;
      --bs-link-color: #9dc4ff;
      --bs-link-hover-color: #c4dcff;
      --bs-code-color: #f0abfc;
    }
    html[data-theme="dark"] body {
      background: #0f1726 !important;
      color: #e5ebf5 !important;
      --bs-card-bg: #182235;
      --bs-card-cap-bg: #202d42;
      --bs-card-color: #e5ebf5;
    }
    html[data-theme="dark"] .app-main,
    html[data-theme="dark"] .app-main .container,
    html[data-theme="dark"] .app-main .container-fluid { color: #e5ebf5; }
    html[data-theme="dark"] .app-main .text-muted,
    html[data-theme="dark"] .app-main .text-secondary,
    html[data-theme="dark"] .app-main .text-body-secondary,
    html[data-theme="dark"] .app-main .text-black-50 { color: #aebbd0 !important; }
    html[data-theme="dark"] .app-main .text-dark,
    html[data-theme="dark"] .app-main .text-body { color: #e5ebf5 !important; }
    html[data-theme="dark"] .app-main .text-primary { color: #9dc4ff !important; }
    html[data-theme="dark"] .app-main .text-success { color: #6ee7b7 !important; }
    html[data-theme="dark"] .app-main .text-danger { color: #fca5a5 !important; }
    html[data-theme="dark"] .app-main .text-warning { color: #fcd34d !important; }
    html[data-theme="dark"] .app-main .text-info { color: #7dd3fc !important; }

    html[data-theme="dark"] .app-main .card,
    html[data-theme="dark"] .app-main .modal-content,
    html[data-theme="dark"] .app-main .dropdown-menu,
    html[data-theme="dark"] .app-main .list-group-item,
    html[data-theme="dark"] .app-main .bg-white,
    html[data-theme="dark"] .app-main .bg-light,
    html[data-theme="dark"] .app-main .bg-light-subtle,
    html[data-theme="dark"] .app-main [class*="-card"],
    html[data-theme="dark"] .app-main [class*="-panel"] {
      background-color: #182235 !important;
      color: #e5ebf5 !important;
      border-color: #344156 !important;
    }
    html[data-theme="dark"] .app-main .card-header,
    html[data-theme="dark"] .app-main .card-footer,
    html[data-theme="dark"] .app-main .settings-card-head {
      background-color: #202d42 !important;
      color: #edf2fb !important;
      border-color: #344156 !important;
    }
    html[data-theme="dark"] .app-main .settings-card-body { color: #e5ebf5; }
    html[data-theme="dark"] .app-main .settings-section-icon {
      color: #bfdbfe;
      background: #20385d;
      border: 1px solid #375681;
    }
    html[data-theme="dark"] .app-main .settings-kicker,
    html[data-theme="dark"] .app-main .settings-field-help { color: #a7b4c8 !important; }
    html[data-theme="dark"] .app-main .settings-logo-stage {
      background: linear-gradient(145deg, #172337, #111b2b) !important;
      border-color: #41516a !important;
    }
    html[data-theme="dark"] .app-main .settings-image-wrap {
      background: #111b2b !important;
      border-color: #3a4960 !important;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .16);
    }
    html[data-theme="dark"] .app-main .settings-color-control,
    html[data-theme="dark"] .app-main .settings-brand-preview {
      background: #111b2b !important;
      color: #e5ebf5 !important;
      border-color: #344156 !important;
    }
    html[data-theme="dark"] .app-main .settings-save-bar {
      background: rgba(24, 34, 53, .96) !important;
      border-color: #344156 !important;
      box-shadow: 0 -5px 22px rgba(0, 0, 0, .2);
    }

    html[data-theme="dark"] .app-main .bg-primary-subtle { background: #1b3355 !important; color: #bfdbfe !important; }
    html[data-theme="dark"] .app-main .bg-success-subtle { background: #12382e !important; color: #b7f7d5 !important; }
    html[data-theme="dark"] .app-main .bg-danger-subtle { background: #451f28 !important; color: #fecaca !important; }
    html[data-theme="dark"] .app-main .bg-warning-subtle { background: #493718 !important; color: #fde68a !important; }
    html[data-theme="dark"] .app-main .bg-info-subtle { background: #12364a !important; color: #bae6fd !important; }
    html[data-theme="dark"] .app-main .bg-secondary-subtle { background: #26354b !important; color: #d5dfed !important; }
    html[data-theme="dark"] .app-main .table-light,
    html[data-theme="dark"] .app-main .table-light > :not(caption) > * > * {
      --bs-table-bg: #202d42;
      --bs-table-color: #e5ebf5;
      --bs-table-border-color: #344156;
      --bs-table-striped-bg: #26354b;
      --bs-table-hover-bg: #2d3d57;
      background-color: #202d42 !important;
      color: #e5ebf5 !important;
      border-color: #344156 !important;
    }
    html[data-theme="dark"] .app-main .form-check-input {
      background-color: #111b2b;
      border-color: #5b6b82;
    }
    html[data-theme="dark"] .app-main .form-check-input:checked {
      background-color: var(--pos-primary);
      border-color: var(--pos-primary);
    }
    html[data-theme="dark"] .app-main .btn-outline-primary {
      color: #a6caff;
      border-color: #5879a8;
    }
    html[data-theme="dark"] .app-main .btn-outline-primary:hover {
      background: #20385d;
      color: #eff6ff;
      border-color: #7194c5;
    }

    /* Native file inputs: equal height, no extra inner whitespace, theme-matched button. */
    .app-main input[type="file"] {
      display: block;
      width: 100%;
      max-width: 100%;
      height: 44px;
      min-height: 44px !important;
      padding: 0 !important;
      line-height: 42px;
      overflow: hidden;
      border-radius: 10px;
      font: inherit;
    }
    .app-main input[type="file"]::file-selector-button,
    .app-main input[type="file"]::-webkit-file-upload-button {
      height: 42px;
      margin: 0 10px 0 0;
      padding: 0 16px;
      border: 0;
      border-right: 1px solid #d7e0ec;
      border-radius: 9px 0 0 9px;
      background: #edf2f8;
      color: #334155;
      font: inherit;
      line-height: 1;
      vertical-align: middle;
      cursor: pointer;
    }
    html[data-theme="light"] .app-main input[type="file"] {
      background: #fff !important;
      color: #334155 !important;
      border-color: #d7e0ec !important;
    }
    html[data-theme="dark"] .app-main input[type="file"] {
      background: #111b2b !important;
      color: #d5dfed !important;
      border-color: #3a4960 !important;
    }
    html[data-theme="dark"] .app-main input[type="file"]::file-selector-button,
    html[data-theme="dark"] .app-main input[type="file"]::-webkit-file-upload-button {
      background: #26354b;
      color: #e5ebf5;
      border-right-color: #3a4960;
    }
    .app-main input[type="file"]:focus-visible {
      outline: 0;
      box-shadow: var(--app-focus-ring);
    }


    /* Dark-mode visual hierarchy: keep the navigation darker than the workspace. */
    html[data-theme="dark"] .app-sidebar {
      background: #080f1d !important;
      color: #e7eefb !important;
      border-right: 1px solid #26344b !important;
      box-shadow: 10px 0 32px rgba(0, 0, 0, .28) !important;
    }
    html[data-theme="dark"] .app-sidebar .app-brand {
      color: #f8fbff !important;
      border-bottom-color: #26344b !important;
    }
    html[data-theme="dark"] .app-sidebar .app-brand-mark {
      background: #142139 !important;
      border-color: #30415d !important;
    }
    html[data-theme="dark"] .app-sidebar .app-sidebar-context {
      background: #101c30 !important;
      border: 1px solid #263954 !important;
      color: #b9c8df !important;
    }
    html[data-theme="dark"] .app-sidebar .app-nav-heading {
      color: #7185a5 !important;
    }
    html[data-theme="dark"] .app-sidebar .app-nav-link {
      color: #b5c3d9 !important;
      border: 1px solid transparent !important;
    }
    html[data-theme="dark"] .app-sidebar .app-nav-link:hover,
    html[data-theme="dark"] .app-sidebar .app-nav-link:focus-visible {
      color: #f5f9ff !important;
      background: #14243b !important;
      border-color: #263d5d !important;
    }
    html[data-theme="dark"] .app-sidebar .app-nav-link.active,
    html[data-theme="dark"] .app-sidebar .app-nav-link[aria-current="page"] {
      color: #ffffff !important;
      background: linear-gradient(100deg, #174ea6 0%, #1d4ed8 100%) !important;
      border-color: #3978ed !important;
      box-shadow: inset 3px 0 0 #7db5ff, 0 5px 16px rgba(0, 0, 0, .18) !important;
    }
    html[data-theme="dark"] .app-sidebar .app-nav-link.active i,
    html[data-theme="dark"] .app-sidebar .app-nav-link[aria-current="page"] i {
      color: #bfdbfe !important;
    }
    html[data-theme="dark"] .app-sidebar .app-sidebar-bottom {
      border-top-color: #26344b !important;
    }
    html[data-theme="dark"] .app-sidebar .app-sidebar-version {
      color: #71819b !important;
    }
    html[data-theme="dark"] .app-sidebar-collapse-toggle,
    html[data-theme="dark"] .app-theme-toggle {
      color: #c8d5e8 !important;
      background: #111d30 !important;
      border-color: #30415b !important;
    }
    html[data-theme="dark"] .app-sidebar-collapse-toggle:hover,
    html[data-theme="dark"] .app-theme-toggle:hover {
      color: #ffffff !important;
      background: #1a2b45 !important;
      border-color: #4d6d9b !important;
    }

    /* Dark mode must not leave white Bootstrap panels or custom page cards behind. */
    html[data-theme="dark"] .app-main .card,
    html[data-theme="dark"] .app-main .modal-content,
    html[data-theme="dark"] .app-main .list-group-item,
    html[data-theme="dark"] .app-main .bg-white,
    html[data-theme="dark"] .app-main .bg-light,
    html[data-theme="dark"] .app-main [class*="-card"],
    html[data-theme="dark"] .app-main [class*="-panel"] {
      background-color: #172337 !important;
      color: #e5edf9 !important;
      border-color: #2c3c54 !important;
    }
    html[data-theme="dark"] .app-main .card-header,
    html[data-theme="dark"] .app-main .card-footer {
      background-color: #1d2b41 !important;
      color: #e5edf9 !important;
      border-color: #2c3c54 !important;
    }
    html[data-theme="dark"] .app-main .table,
    html[data-theme="dark"] .app-main .table > :not(caption) > * > * {
      --bs-table-bg: #172337;
      --bs-table-color: #dce6f5;
      --bs-table-border-color: #2c3c54;
      --bs-table-striped-bg: #1b2a40;
      --bs-table-hover-bg: #233650;
      background-color: #172337 !important;
      color: #dce6f5 !important;
      border-color: #2c3c54 !important;
    }
    html[data-theme="dark"] .app-main input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
    html[data-theme="dark"] .app-main select,
    html[data-theme="dark"] .app-main textarea {
      background-color: #0e1929 !important;
      color: #e5edf9 !important;
      border-color: #344761 !important;
    }
    html[data-theme="dark"] .app-main input::placeholder,
    html[data-theme="dark"] .app-main textarea::placeholder {
      color: #8394ad !important;
      opacity: 1;
    }


    /* Stronger sidebar separation in both themes. */
    html[data-theme="light"] .app-sidebar {
      background: #dce4ef !important;
      color: #334155 !important;
      border-right: 1px solid #c0ccdc !important;
      box-shadow: 8px 0 24px rgba(15, 23, 42, .10) !important;
    }
    html[data-theme="light"] .app-sidebar .app-brand { color:#142238 !important; border-bottom-color:#c1ccdb !important; }
    html[data-theme="light"] .app-sidebar .app-brand-copy small { color:#52647d !important; }
    html[data-theme="light"] .app-sidebar .app-brand-mark { color:#1e40af !important; background:#f8fbff !important; border-color:#c0ccdc !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-context { background:#cbd7e7 !important; border-color:#b5c4d8 !important; color:#34445b !important; }
    html[data-theme="light"] .app-sidebar .app-nav-heading { color:#526782 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link { color:#34445b !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link i { color:#435873 !important; opacity:1 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link:hover { color:#142b4c !important; background:#c5d4e8 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link.active { color:#123d8b !important; background:#b8d2fa !important; box-shadow:inset 4px 0 #1765d8 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link.active i { color:#1256bd !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-bottom { border-top-color:#bdc9d9 !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-pos { color:#123d8b !important; background:#c5d9fb !important; border-color:#a9c4f3 !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-pos .pos-icon { color:#fff !important; background:#2563eb !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-version { color:#52647d !important; }

    /* Dark navigation stays distinctly darker than the working area. */
    html[data-theme="dark"] .app-sidebar { background:#070d18 !important; border-right-color:#24334a !important; }
    html[data-theme="dark"] .app-sidebar .app-nav-link.active { background:linear-gradient(100deg,#1647a0,#2563eb) !important; border-color:#477ff0 !important; }

    /* Page-specific cards use their own inline styles, so override those classes explicitly. */
    html[data-theme="dark"] .app-main .kitchen-stat,
    html[data-theme="dark"] .app-main .kitchen-toolbar,
    html[data-theme="dark"] .app-main .kitchen-filter-group,
    html[data-theme="dark"] .app-main .kitchen-empty,
    html[data-theme="dark"] .app-main .kitchen-ticket,
    html[data-theme="dark"] .app-main .kitchen-ticket-head,
    html[data-theme="dark"] .app-main .kitchen-ticket-items,
    html[data-theme="dark"] .app-main .kitchen-item,
    html[data-theme="dark"] .app-main .kitchen-item-status-row,
    html[data-theme="dark"] .app-main .bill-empty,
    html[data-theme="dark"] .app-main .bill-card,
    html[data-theme="dark"] .app-main .bill-summary-box,
    html[data-theme="dark"] .app-main .bill-share,
    html[data-theme="dark"] .app-main .bill-payment-panel {
      background-color:#172337 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
      box-shadow:none;
    }
    html[data-theme="dark"] .app-main .kitchen-filter,
    html[data-theme="dark"] .app-main .kitchen-station-filter {
      background:#101b2c !important;
      color:#c9d7eb !important;
      border-color:#344761 !important;
    }
    html[data-theme="dark"] .app-main .kitchen-filter.active,
    html[data-theme="dark"] .app-main .kitchen-station-filter.active {
      background:#2563eb !important;
      color:#fff !important;
      border-color:#5790ff !important;
    }
    html[data-theme="dark"] .app-main .kitchen-ticket-head,
    html[data-theme="dark"] .app-main .bill-card-head {
      background:#1d2b41 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
    }
    html[data-theme="dark"] .app-main .kitchen-ticket-number,
    html[data-theme="dark"] .app-main .kitchen-item-name,
    html[data-theme="dark"] .app-main .bill-order-number,
    html[data-theme="dark"] .app-main .bill-item-name,
    html[data-theme="dark"] .app-main .bill-item-price { color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .kitchen-ticket-meta,
    html[data-theme="dark"] .app-main .kitchen-stat-label,
    html[data-theme="dark"] .app-main .kitchen-stat-note,
    html[data-theme="dark"] .app-main .bill-meta,
    html[data-theme="dark"] .app-main .bill-item-meta { color:#aabbd2 !important; }
    html[data-theme="dark"] .app-main .kitchen-stat-icon { background:#263a57 !important; color:#a8c9ff !important; }
    html[data-theme="dark"] .app-main .kitchen-item-note { background:#3a301b !important; color:#f6d89a !important; border-color:#62502a !important; }
    html[data-theme="dark"] .app-main .bill-summary-box.due { background:#352b1b !important; border-color:#5b4827 !important; }
    html[data-theme="dark"] .app-main .bill-chip { background:#26354b !important; color:#d8e5f7 !important; border-color:#3a4c66 !important; }

    /* Avoid animated layout effects while filtering; preserve only short hover feedback. */
    html[data-theme="dark"] .app-main .kitchen-ticket,
    html[data-theme="dark"] .app-main .kitchen-stat { transition:none !important; }


    /* Final theme correction: white sidebar in light mode, dark surfaces in dark mode. */
    html[data-theme="light"] .app-sidebar {
      background:#ffffff !important;
      color:#334155 !important;
      border-right:1px solid #d8e0eb !important;
      box-shadow:6px 0 20px rgba(15,23,42,.055) !important;
    }
    html[data-theme="light"] .app-sidebar .app-brand { background:#fff !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-context { background:#f3f6fb !important; border-color:#dce4ef !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link { color:#475569 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link i { color:#52657e !important; opacity:1 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link.active { background:#e5efff !important; color:#1748a0 !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link.active i { color:#1d4ed8 !important; }
    html[data-theme="light"] .app-sidebar .app-sidebar-pos { background:#edf4ff !important; color:#1748a0 !important; }

    /* Kitchen Display uses page-local CSS; explicitly theme its text, controls and footer. */
    html[data-theme="dark"] .app-main .kitchen-stat-value,
    html[data-theme="dark"] .app-main .kitchen-ticket-number,
    html[data-theme="dark"] .app-main .kitchen-item-name,
    html[data-theme="dark"] .app-main .kitchen-item-line,
    html[data-theme="dark"] .app-main .kitchen-section-title { color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .kitchen-ticket-meta,
    html[data-theme="dark"] .app-main .kitchen-ticket-kicker,
    html[data-theme="dark"] .app-main .kitchen-item-variant,
    html[data-theme="dark"] .app-main .kitchen-item-addon,
    html[data-theme="dark"] .app-main .kitchen-refresh-note { color:#aebed3 !important; }
    html[data-theme="dark"] .app-main .kitchen-ticket-actions,
    html[data-theme="dark"] .app-main .kitchen-ticket-note,
    html[data-theme="dark"] .app-main .kitchen-item-status-row { background:#172337 !important; color:#e5edf9 !important; border-color:#2c3d55 !important; }
    html[data-theme="dark"] .app-main .kitchen-chip,
    html[data-theme="dark"] .app-main .kitchen-elapsed,
    html[data-theme="dark"] .app-main .kitchen-item-status {
      background:#263750 !important; color:#dce9fb !important; border-color:#405572 !important;
    }
    html[data-theme="dark"] .app-main .kitchen-status.status-new { background:#49391b !important; color:#ffdf91 !important; }
    html[data-theme="dark"] .app-main .kitchen-status.status-preparing { background:#173e70 !important; color:#c5ddff !important; }
    html[data-theme="dark"] .app-main .kitchen-status.status-ready,
    html[data-theme="dark"] .app-main .kitchen-status.status-served { background:#124b3a !important; color:#b9f6d9 !important; }
    html[data-theme="dark"] .app-main .kitchen-delay-badge { background:#4b2026 !important; color:#ffc8c8 !important; border-color:#74333b !important; }
    html[data-theme="dark"] .app-main .kitchen-ticket-actions .btn-outline-primary,
    html[data-theme="dark"] .app-main .kitchen-ticket-actions .btn-outline-secondary {
      background:#1c2b40 !important; color:#d6e5fb !important; border-color:#526985 !important;
    }
    html[data-theme="dark"] .app-main .kitchen-ticket-actions .btn-primary {
      background:#2563eb !important; color:#fff !important; border-color:#4b83f2 !important;
    }
    html[data-theme="dark"] .app-main .kitchen-ticket-actions .btn:disabled,
    html[data-theme="dark"] .app-main .kitchen-ticket-actions .btn.disabled {
      background:#26354b !important; color:#91a2ba !important; border-color:#3b4b63 !important; opacity:1 !important;
    }

    /* Open Bills and Split Payments: readable empty states and payment panels. */
    html[data-theme="dark"] .app-main .bill-empty,
    html[data-theme="dark"] .app-main .bill-card,
    html[data-theme="dark"] .app-main .bill-summary-box,
    html[data-theme="dark"] .app-main .bill-share,
    html[data-theme="dark"] .app-main .bill-payment-panel { background:#172337 !important; color:#e5edf9 !important; border-color:#2c3d55 !important; }
    html[data-theme="dark"] .app-main .bill-card-head { background:#1d2b41 !important; color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .bill-empty .bill-empty-title,
    html[data-theme="dark"] .app-main .bill-empty strong { color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .bill-empty p,
    html[data-theme="dark"] .app-main .bill-meta,
    html[data-theme="dark"] .app-main .bill-item-meta { color:#aebed3 !important; }
    html[data-theme="dark"] .app-main .bill-summary-box.due { background:#382d1b !important; color:#ffdf91 !important; border-color:#62502b !important; }

    /* Ensure dark-mode generic white utility classes cannot wash out nested panels. */
    html[data-theme="dark"] .app-main .bg-white,
    html[data-theme="dark"] .app-main [style*="background:#fff"],
    html[data-theme="dark"] .app-main [style*="background: #fff"],
    html[data-theme="dark"] .app-main [style*="background-color:#fff"],
    html[data-theme="dark"] .app-main [style*="background-color: #fff"] {
      background-color:#172337 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
    }


    /* Cross-page dark theme pass: inventory, purchasing, tables and expiry/waste. */
    html[data-theme="dark"] .app-main .procurement-card,
    html[data-theme="dark"] .app-main .procurement-card-head,
    html[data-theme="dark"] .app-main .procurement-card-body,
    html[data-theme="dark"] .app-main .procurement-order,
    html[data-theme="dark"] .app-main .procurement-order-body,
    html[data-theme="dark"] .app-main .procurement-order-line,
    html[data-theme="dark"] .app-main .expiry-card,
    html[data-theme="dark"] .app-main .expiry-card-head,
    html[data-theme="dark"] .app-main .expiry-empty {
      background:#172337 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
      box-shadow:none !important;
    }
    html[data-theme="dark"] .app-main .procurement-order-head,
    html[data-theme="dark"] .app-main .procurement-card-head,
    html[data-theme="dark"] .app-main .expiry-card-head {
      background:#1d2b41 !important;
      color:#edf4ff !important;
      border-color:#2c3d55 !important;
    }
    html[data-theme="dark"] .app-main .procurement-order-total,
    html[data-theme="dark"] .app-main .procurement-order-line-name,
    html[data-theme="dark"] .app-main .procurement-supplier-name,
    html[data-theme="dark"] .app-main .expiry-table td,
    html[data-theme="dark"] .app-main .expiry-table th { color:#e5edf9 !important; }
    html[data-theme="dark"] .app-main .procurement-order-line-detail,
    html[data-theme="dark"] .app-main .procurement-empty,
    html[data-theme="dark"] .app-main .expiry-empty p { color:#aebed3 !important; }

    /* Tables/floor plan: QR buttons and card controls should not stay bright white. */
    html[data-theme="dark"] .app-main .table-card,
    html[data-theme="dark"] .app-main .floor-table-card,
    html[data-theme="dark"] .app-main .zone-filter,
    html[data-theme="dark"] .app-main .table-zone-filter {
      background:#172337 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
    }
    html[data-theme="dark"] .app-main a.btn-light,
    html[data-theme="dark"] .app-main button.btn-light,
    html[data-theme="dark"] .app-main .btn-outline-light,
    html[data-theme="dark"] .app-main .btn-light,
    html[data-theme="dark"] .app-main .qr-ordering-btn,
    html[data-theme="dark"] .app-main a[class*="qr"],
    html[data-theme="dark"] .app-main button[class*="qr"] {
      background:#1d2b41 !important;
      color:#c9dcfa !important;
      border-color:#3b5272 !important;
    }
    html[data-theme="dark"] .app-main .btn-light:hover,
    html[data-theme="dark"] .app-main .qr-ordering-btn:hover {
      background:#263b59 !important;
      color:#fff !important;
      border-color:#5c82b7 !important;
    }

    /* Normalize Bootstrap's light table variants and light badges under dark mode. */
    html[data-theme="dark"] .app-main .table-light,
    html[data-theme="dark"] .app-main .table-light > :not(caption) > * > * {
      --bs-table-bg:#1d2b41 !important;
      --bs-table-color:#dce6f5 !important;
      background:#1d2b41 !important;
      color:#dce6f5 !important;
      border-color:#344761 !important;
    }
    html[data-theme="dark"] .app-main .text-body,
    html[data-theme="dark"] .app-main .text-secondary { color:#b9c8dc !important; }
    html[data-theme="dark"] .app-main .bg-body,
    html[data-theme="dark"] .app-main .bg-body-tertiary { background:#172337 !important; color:#e5edf9 !important; }

    /* Catch common inline white surfaces used by older page-specific templates. */
    html[data-theme="dark"] .app-main [style*="background:#fff"],
    html[data-theme="dark"] .app-main [style*="background: #fff"],
    html[data-theme="dark"] .app-main [style*="background-color:#fff"],
    html[data-theme="dark"] .app-main [style*="background-color: #fff"],
    html[data-theme="dark"] .app-main [style*="background: white"],
    html[data-theme="dark"] .app-main [style*="background-color: white"],
    html[data-theme="dark"] .app-main [style*="background:#ffffff"],
    html[data-theme="dark"] .app-main [style*="background: #ffffff"] {
      background:#172337 !important;
      color:#e5edf9 !important;
      border-color:#2c3d55 !important;
    }

    /* Keep sidebar white in light mode while maintaining clear icon contrast. */
    html[data-theme="light"] .app-sidebar,
    html[data-theme="light"] .app-sidebar .app-brand { background:#fff !important; }
    html[data-theme="light"] .app-sidebar .app-nav-link i,
    html[data-theme="light"] .app-sidebar .app-nav-link .bi { color:#52657e !important; opacity:1 !important; }


    /* Procurement: complete dark palette for form rows, totals, labels and history. */
    html[data-theme="dark"] .app-main .procurement-page { color:#e5edf9 !important; }
    html[data-theme="dark"] .app-main .procurement-title,
    html[data-theme="dark"] .app-main .procurement-heading,
    html[data-theme="dark"] .app-main .procurement-metric strong,
    html[data-theme="dark"] .app-main .procurement-order-total { color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .procurement-subtitle,
    html[data-theme="dark"] .app-main .procurement-help,
    html[data-theme="dark"] .app-main .procurement-muted,
    html[data-theme="dark"] .app-main .procurement-order-line-detail { color:#aebed3 !important; }
    html[data-theme="dark"] .app-main .procurement-card,
    html[data-theme="dark"] .app-main .procurement-card-head,
    html[data-theme="dark"] .app-main .procurement-card-body,
    html[data-theme="dark"] .app-main .procurement-order,
    html[data-theme="dark"] .app-main .procurement-order-body,
    html[data-theme="dark"] .app-main .procurement-order-line,
    html[data-theme="dark"] .app-main .procurement-supplier-row {
      background:#172337 !important; color:#e5edf9 !important; border-color:#2c3d55 !important;
      box-shadow:none !important;
    }
    html[data-theme="dark"] .app-main .procurement-card-head,
    html[data-theme="dark"] .app-main .procurement-order-head {
      background:#1d2b41 !important; color:#edf4ff !important; border-color:#2c3d55 !important;
    }
    html[data-theme="dark"] .app-main .procurement-line,
    html[data-theme="dark"] .app-main .procurement-total,
    html[data-theme="dark"] .app-main .procurement-waste-form {
      background:#101b2c !important; color:#e5edf9 !important; border-color:#344761 !important;
      box-shadow:none !important;
    }
    html[data-theme="dark"] .app-main .procurement-total strong,
    html[data-theme="dark"] .app-main .procurement-line label { color:#dce7f7 !important; }
    html[data-theme="dark"] .app-main .procurement-icon,
    html[data-theme="dark"] .app-main .procurement-metric > .procurement-icon {
      background:#263a57 !important; color:#a8c9ff !important;
    }
    html[data-theme="dark"] .app-main .procurement-metric {
      background:#172337 !important; border-color:#2c3d55 !important; color:#e5edf9 !important;
    }
    html[data-theme="dark"] .app-main .procurement-badge.draft { background:#263750 !important; color:#dce6f5 !important; }
    html[data-theme="dark"] .app-main .procurement-badge.ordered { background:#173e70 !important; color:#c5ddff !important; }
    html[data-theme="dark"] .app-main .procurement-badge.received { background:#124b3a !important; color:#b9f6d9 !important; }
    html[data-theme="dark"] .app-main .procurement-badge.cancelled { background:#4b2026 !important; color:#ffc8c8 !important; }
    html[data-theme="dark"] .app-main .procurement-empty { background:#172337 !important; color:#aebed3 !important; }
    html[data-theme="dark"] .app-main .procurement-empty i { color:#a8c9ff !important; }

    /* Expiry metrics have semantic pastel backgrounds in light mode; normalize in dark mode. */
    html[data-theme="dark"] .app-main .expiry-metric,
    html[data-theme="dark"] .app-main .expiry-metric.danger,
    html[data-theme="dark"] .app-main .expiry-metric.warning,
    html[data-theme="dark"] .app-main .expiry-waste-form {
      background:#172337 !important; border-color:#2c3d55 !important; color:#e5edf9 !important;
    }
    html[data-theme="dark"] .app-main .expiry-title,
    html[data-theme="dark"] .app-main .expiry-section-title,
    html[data-theme="dark"] .app-main .expiry-metric strong,
    html[data-theme="dark"] .app-main .expiry-batch-name,
    html[data-theme="dark"] .app-main .expiry-value { color:#edf4ff !important; }
    html[data-theme="dark"] .app-main .expiry-subtitle,
    html[data-theme="dark"] .app-main .expiry-help,
    html[data-theme="dark"] .app-main .expiry-metric-label,
    html[data-theme="dark"] .app-main .expiry-subtext { color:#aebed3 !important; }
    html[data-theme="dark"] .app-main .expiry-waste-form .form-label { color:#dce7f7 !important; }

  </style>
</head>
<body>
