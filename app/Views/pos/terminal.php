<?php
try {
  $posRestaurant = \App\Core\TenantContext::getRestaurant();
} catch (\Throwable $ignored) {
  $posRestaurant = null;
}
if (!is_array($posRestaurant)) {
  $posRestaurant = isset($catalog['restaurant']) && is_array($catalog['restaurant']) ? $catalog['restaurant'] : [];
}
$restaurant = $posRestaurant;
$posPrimaryColor = (string)($restaurant['pos_primary_color'] ?? '#2563eb');
$posSecondaryColor = (string)($restaurant['pos_secondary_color'] ?? '#059669');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $posPrimaryColor)) $posPrimaryColor = '#2563eb';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $posSecondaryColor)) $posSecondaryColor = '#059669';
$posRestaurantName = (string)($catalog['restaurant']['name'] ?? $restaurant['name'] ?? 'Restaurant POS');
$posTagline = trim((string)($catalog['restaurant']['tagline'] ?? $restaurant['tagline'] ?? ''));
$posLogoPath = trim((string)($restaurant['logo_path'] ?? $catalog['restaurant']['logo_path'] ?? ''));
$posLogoUrl = $posLogoPath !== '' ? BASE_URL . '/' . ltrim($posLogoPath, '/') : '';
include APP_ROOT . '/app/Views/layouts/header.php';
?>

<!-- Modern POS Terminal Styles -->
<style>
  :root {
    --terminal-primary: <?= htmlspecialchars($posPrimaryColor, ENT_QUOTES, 'UTF-8') ?>;
    --terminal-secondary: <?= htmlspecialchars($posSecondaryColor, ENT_QUOTES, 'UTF-8') ?>;
    --terminal-ink: #192335;
    --terminal-muted: #738096;
    --terminal-line: #e3e8f0;
    --terminal-page: #f4f6fa;
    --terminal-surface: #ffffff;
  }
  html, body { min-height: 100%; }
  body {
    margin: 0; background: var(--terminal-page) !important; color: var(--terminal-ink) !important;
    overflow-x: hidden; font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  .pos-header {
    position: sticky; top: 0; z-index: 1020; min-height: 76px; padding: 12px 24px;
    background: rgba(255,255,255,.96); border-bottom: 1px solid var(--terminal-line);
    box-shadow: 0 3px 14px rgba(16,24,40,.035); backdrop-filter: blur(14px);
  }
  .pos-brand-lockup { display:flex; align-items:center; gap:12px; min-width:0; }
  .pos-brand-mark {
    display:grid; place-items:center; flex:0 0 44px; width:44px; height:44px; overflow:hidden;
    border-radius:14px; color:#fff; font-size:1.15rem;
    background:linear-gradient(140deg,var(--terminal-primary),color-mix(in srgb,var(--terminal-primary) 72%,#111827));
    box-shadow:0 5px 13px color-mix(in srgb,var(--terminal-primary) 18%,transparent);
  }
  .pos-brand-mark img { width:100%; height:100%; object-fit:contain; padding:3px; background:#fff; }
  .pos-brand-copy { min-width:0; display:flex; flex-direction:column; line-height:1.2; }
  .pos-brand-copy strong { max-width:min(32vw,390px); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:1rem; font-weight:800; letter-spacing:-.035em; color:#172033; }
  .pos-brand-copy small { margin-top:5px; color:#8993a5; text-transform:uppercase; letter-spacing:.1em; font-size:.61rem; font-weight:800; }
  .pos-device-badge { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; color:#596579; background:#f6f8fb; border:1px solid #e5eaf2; border-radius:999px; font-size:.7rem; font-weight:700; }
  .pos-header-actions { display:flex; align-items:center; justify-content:flex-end; gap:9px; min-width:0; }
  .pos-network-status { display:inline-flex; align-items:center; gap:7px; padding:8px 11px; border-radius:999px; font-size:.72rem; line-height:1; font-weight:800; white-space:nowrap; border:1px solid; }
  .pos-network-status .pos-status-dot { width:7px; height:7px; border-radius:50%; }
  .pos-network-status.online { color:#12805c; border-color:#c7f0df; background:#ecfbf4; }
  .pos-network-status.online .pos-status-dot { background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.13); }
  .pos-network-status.offline { color:#9a5a0b; border-color:#fde2a8; background:#fff8e6; }
  .pos-network-status.offline .pos-status-dot { background:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
  .pos-header .btn { border-radius:11px; min-height:39px; display:inline-flex; align-items:center; justify-content:center; gap:7px; font-size:.77rem; font-weight:750; }
  .pos-sync-btn { padding:8px 12px; color:#334155; border:1px solid #dfe5ee; background:#fff; }
  .pos-sync-btn:hover { color:var(--terminal-primary); border-color:var(--terminal-primary); background:#f8faff; }
  .pos-sync-btn .pos-sync-count { display:inline-grid; place-items:center; min-width:21px; height:21px; padding:0 5px; border-radius:999px; color:#344054; background:#eef2f7; font-size:.68rem; }
  .pos-sync-btn.has-pending .pos-sync-count { color:#9a5a0b; background:#fff0c8; }
  .pos-icon-btn { width:39px; padding:0; color:#596579; background:#fff; border:1px solid #dfe5ee; }
  .pos-icon-btn:hover { color:var(--terminal-primary); background:#f8faff; border-color:var(--terminal-primary); }
  .pos-cashier-btn { padding:4px 10px 4px 5px; background:#fff; border:1px solid #dfe5ee; color:#334155; max-width:235px; }
  .pos-cashier-avatar { display:grid; place-items:center; width:29px; height:29px; border-radius:9px; color:var(--terminal-primary); background:color-mix(in srgb,var(--terminal-primary) 10%,white); }
  .pos-cashier-name { max-width:155px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  /* Keep the cashier dropdown readable even when the global theme sets light text. */
  .pos-header .dropdown-menu {
    display:none;
    min-width:250px;
    padding:8px;
    color:#263247!important;
    background:#fff!important;
    border:1px solid #e4e9f1;
    border-radius:13px;
    box-shadow:0 16px 38px rgba(16,24,40,.13);
  }
  .pos-header .dropdown-menu.show { display:block; }
  .pos-header .dropdown-menu .dropdown-header {
    display:block!important;
    padding:9px 11px;
    color:#718096!important;
    font-size:.72rem;
    font-weight:800;
    line-height:1.4;
    opacity:1!important;
    visibility:visible!important;
  }
  .pos-header .dropdown-menu .dropdown-item {
    display:flex!important;
    align-items:center;
    gap:8px;
    width:100%;
    min-height:40px;
    padding:9px 11px;
    border-radius:8px;
    color:#344054!important;
    background:transparent!important;
    font-size:.84rem;
    font-weight:600;
    line-height:1.4;
    opacity:1!important;
    visibility:visible!important;
  }
  .pos-header .dropdown-menu .dropdown-item i {
    display:inline-block!important;
    flex:0 0 18px;
    width:18px;
    color:#64748b!important;
    opacity:1!important;
    visibility:visible!important;
    text-align:center;
  }
  .pos-header .dropdown-menu .dropdown-item:hover,
  .pos-header .dropdown-menu .dropdown-item:focus {
    color:#174ea6!important;
    background:#eff6ff!important;
  }
  .pos-header .dropdown-menu .dropdown-item.text-danger,
  .pos-header .dropdown-menu .dropdown-item.text-danger i {
    color:#dc3545!important;
  }
  .pos-header .dropdown-menu .dropdown-divider {
    margin:7px 0;
    border-top:1px solid #e6eaf1!important;
    opacity:1;
  }
  #posBackToSaaS:not(.d-none) { color:#5c6678!important; border:1px solid #e0e5ed!important; background:#fff!important; }

  #posOfflineGuardNotice, #posSyncNotice {
    margin:10px 18px 0!important;
    border-radius:11px;
    border:1px solid transparent;
    box-shadow:0 2px 8px rgba(16,24,40,.025);
    font-size:.82rem;
  }
  /* A restored local draft is informational, not an error: use the POS neutral palette. */
  #posOfflineGuardNotice.alert-info {
    color:#475569!important;
    background:#f1f5f9!important;
    border-color:#dbe3ec!important;
  }
  /* Keep actual offline/sync warnings amber so they're distinct from draft recovery. */
  #posOfflineGuardNotice.alert-warning, #posSyncNotice.alert-warning {
    color:#805b12!important;
    background:#fff8e8!important;
    border-color:#f2d99b!important;
  }
  .pos-workspace { background:var(--terminal-page); }
  .pos-workspace-row { align-items:stretch; }
  .pos-catalog-panel { height:calc(100vh - 76px); min-height:470px; overflow-y:auto; padding:22px 24px!important; background:var(--terminal-page); }
  .pos-ticket-panel {
    height:calc(100vh - 76px); min-height:470px; overflow:hidden; background:var(--terminal-surface);
    border-left:1px solid var(--terminal-line); display:flex; flex-direction:column;
  }
  .pos-search-group { filter:drop-shadow(0 2px 4px rgba(16,24,40,.025)); }
  .pos-search-group .input-group-text { min-width:44px; justify-content:center; border-color:#dfe5ee!important; background:#fff!important; color:#8691a3!important; }
  .pos-search-group .form-control, .pos-search-group .form-select {
    min-height:45px; border-color:#dfe5ee!important; background:#fff!important; color:#243047!important;
    border-radius:0 11px 11px 0; font-size:.83rem; box-shadow:none;
  }
  .pos-search-group .form-control::placeholder { color:#9aa3b2!important; }
  .pos-search-group .input-group-text { border-radius:11px 0 0 11px; }
  .pos-search-group .form-control:focus, .pos-search-group .form-select:focus { border-color:color-mix(in srgb,var(--terminal-primary) 60%,#dfe5ee)!important; box-shadow:0 0 0 3px color-mix(in srgb,var(--terminal-primary) 12%,transparent); }
  #categoryPillsContainer { scrollbar-width:thin; scrollbar-color:#cbd5e1 transparent; gap:8px!important; padding-bottom:12px!important; }
  .category-pill { background:#fff; border:1px solid #e2e8f0; color:#647085; border-radius:999px; padding:9px 15px; font-size:.75rem; font-weight:750; cursor:pointer; white-space:nowrap; transition:all .16s ease; }
  .category-pill:hover { border-color:color-mix(in srgb,var(--terminal-primary) 42%,#e2e8f0); color:var(--terminal-primary); background:#fff; }
  .category-pill.active { color:#fff; background:var(--terminal-primary); border-color:var(--terminal-primary); box-shadow:0 4px 10px color-mix(in srgb,var(--terminal-primary) 20%,transparent); }
  #productsGrid { --bs-gutter-x:1rem; --bs-gutter-y:1rem; }
  .product-card {
    min-height:230px; padding:13px!important; background:#fff!important; color:var(--terminal-ink);
    border:1px solid #e3e8f0!important; border-radius:16px!important; box-shadow:0 3px 10px rgba(16,24,40,.025);
    transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease; cursor:pointer; user-select:none;
  }
  .product-image-wrap {
    position:relative; display:grid; place-items:center; width:100%; aspect-ratio:4 / 3; margin-bottom:13px;
    overflow:hidden; border-radius:11px; background:linear-gradient(145deg,#f8fafc,#eef2f7); color:#b2bdcd;
  }
  .product-image-wrap i { font-size:1.9rem; color:#bdc7d5; }
  .product-image-wrap img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block; }
  .product-image-wrap.is-fallback { background:linear-gradient(145deg,#f8fafc,#eef2f7); }

  .product-card:hover { border-color:color-mix(in srgb,var(--terminal-primary) 52%,#e3e8f0)!important; transform:translateY(-2px); box-shadow:0 12px 25px rgba(16,24,40,.07); }
  .product-card:active { transform:scale(.985); }
  .product-card .product-category-tag { background:#f4f6fa; border:1px solid #e6eaf1; border-radius:6px; padding:4px 7px; color:#748096; font-size:.62rem; font-weight:800; line-height:1; }
  .product-card .product-custom-tag { border-radius:6px; background:color-mix(in srgb,var(--terminal-primary) 10%,white); color:var(--terminal-primary); border:1px solid color-mix(in srgb,var(--terminal-primary) 22%,white); font-size:.61rem; font-weight:800; }
  .product-card .product-title { color:#202b3d!important; font-size:.88rem; line-height:1.35; letter-spacing:-.015em; overflow-wrap:anywhere; }
  .product-card .product-price { color:var(--terminal-secondary); font-size:1rem; font-weight:850; letter-spacing:-.035em; }
  .product-card .product-add-icon { display:grid; place-items:center; width:30px; height:30px; border-radius:10px; color:#fff; background:var(--terminal-primary); box-shadow:0 3px 8px color-mix(in srgb,var(--terminal-primary) 17%,transparent); }
  .pos-ticket-panel .pos-order-type-switcher { padding:14px!important; display:flex; gap:7px!important; border-bottom:1px solid var(--terminal-line)!important; background:#fff; }
  .pos-order-type { flex:1 1 0; border:1px solid transparent!important; border-radius:10px!important; color:#69758a!important; background:#f4f6fa!important; padding:11px 9px!important; font-size:.73rem!important; font-weight:800!important; transition:all .15s ease; }
  .pos-order-type:hover { color:var(--terminal-primary)!important; border-color:color-mix(in srgb,var(--terminal-primary) 20%,#fff)!important; background:color-mix(in srgb,var(--terminal-primary) 5%,white)!important; }
  .pos-order-type.active { color:var(--terminal-primary)!important; background:color-mix(in srgb,var(--terminal-primary) 10%,white)!important; border-color:color-mix(in srgb,var(--terminal-primary) 26%,white)!important; box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--terminal-primary) 6%,white); }
  .ticket-items { flex:1 1 auto; min-height:0; overflow-y:auto; padding:18px!important; background:#f8f9fc; }
  .pos-empty-cart { min-height:220px; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:30px 16px; text-align:center; color:#8b95a7; }
  .pos-empty-cart .pos-empty-icon { display:grid; place-items:center; width:58px; height:58px; margin-bottom:15px; border:1px solid #e6ebf2; border-radius:18px; color:#98a3b5; background:#fff; font-size:1.55rem; box-shadow:0 4px 12px rgba(16,24,40,.03); }
  .pos-empty-cart h5 { color:#344054; font-size:.95rem; font-weight:800; margin-bottom:6px; }
  .pos-empty-cart p { max-width:245px; margin:0; color:#8a95a7!important; line-height:1.5; }
  .ticket-item-card { padding:15px!important; margin-bottom:11px!important; background:#fff!important; border:1px solid #e5eaf1!important; border-radius:13px!important; box-shadow:0 2px 6px rgba(16,24,40,.025); }
  .ticket-item-card .ticket-item-name { color:#233047!important; font-size:.83rem; line-height:1.35; }
  .ticket-item-card .ticket-item-price { color:#344054!important; font-size:.85rem; font-weight:800; white-space:nowrap; }
  .ticket-item-card .ticket-item-meta { color:#8a95a7!important; font-size:.72rem; }
  .ticket-item-card .ticket-addon-text { color:#b56a16!important; font-size:.7rem; }
  .ticket-item-card .ticket-item-controls { border-top:1px solid #edf0f5!important; margin-top:12px!important; padding-top:10px!important; }
  .ticket-item-card .ticket-remove-btn { color:#d04444!important; font-size:.71rem; font-weight:700; }
  .ticket-item-card .ticket-qty-btn { display:grid; place-items:center; width:29px; height:29px; padding:0!important; border:1px solid #dfe5ed!important; border-radius:8px!important; color:#465367!important; background:#fff!important; }
  .ticket-item-card .ticket-qty-btn:hover { color:var(--terminal-primary)!important; border-color:var(--terminal-primary)!important; }
  .ticket-item-card .ticket-qty { min-width:18px; color:#263247!important; text-align:center; font-size:.8rem; }
  .pos-ticket-summary { padding:18px 20px 20px!important; border-top:1px solid var(--terminal-line)!important; background:#fff!important; box-shadow:0 -4px 16px rgba(16,24,40,.02); }
  .pos-ticket-summary .text-white { color:#344054!important; }
  .pos-ticket-summary .text-muted { color:#8590a3!important; }
  .pos-ticket-summary .text-uppercase { letter-spacing:.03em; }
  .pos-ticket-summary .form-select { max-width:180px; min-height:34px; font-size:.72rem!important; border-color:#dfe5ee!important; background:#fff!important; color:#344054!important; }
  .pos-ticket-summary #ticketDiscount { color:#bd7a22!important; font-weight:750; }
  .pos-ticket-summary #ticketTotal { color:var(--terminal-primary)!important; font-size:1.9rem!important; letter-spacing:-.045em; }
  .pos-ticket-summary .pos-clear-ticket { flex:0 0 43px; width:43px; height:45px; border:1px solid #f1cccc!important; background:#fff8f8!important; color:#c24141!important; border-radius:11px!important; }
  .pos-ticket-summary .pos-checkout-btn { min-height:46px; border:0!important; background:var(--terminal-primary)!important; color:#fff!important; border-radius:11px!important; box-shadow:0 5px 12px color-mix(in srgb,var(--terminal-primary) 17%,transparent); font-size:.91rem!important; font-weight:850!important; }
  .pos-ticket-summary .pos-checkout-btn:hover { filter:brightness(.95); transform:translateY(-1px); }
  .pos-ticket-summary .pos-checkout-btn:disabled { background:#b5bfce!important; box-shadow:none; }
  .pos-header .dropdown-menu-dark { background:#fff!important; color:#344054!important; }
  .pos-header .dropdown-menu-dark .dropdown-item { color:#344054!important; }
  .pos-header .dropdown-menu-dark .dropdown-item:hover { color:#172033!important; background:#f2f5fa!important; }
  .pos-header .dropdown-menu-dark .dropdown-header { color:#7b8799!important; }
  .pos-header .dropdown-menu-dark .dropdown-divider { border-color:#e6eaf1!important; }

  /* Light, consistent dialogs while retaining the cashier PIN keypad and receipt behavior. */
  .modal-content.bg-dark { background:#fff!important; color:#202b3d!important; border:1px solid #e2e8f0!important; border-radius:18px!important; box-shadow:0 24px 80px rgba(15,23,42,.22); }
  .modal-content.bg-dark .modal-header, .modal-content.bg-dark .modal-footer { border-color:#e9edf3!important; }
  .modal-content.bg-dark .modal-title, .modal-content.bg-dark .text-white { color:#1f2937!important; }
  .modal-content.bg-dark .text-white-50, .modal-content.bg-dark .text-muted { color:#788397!important; }
  .modal-content.bg-dark .bg-black { background:#f6f8fb!important; }
  .modal-content.bg-dark .form-control.bg-dark, .modal-content.bg-dark .form-control.bg-black, .modal-content.bg-dark .form-select.bg-dark {
    background:#fff!important; color:#202b3d!important; border-color:#dfe5ee!important;
  }
  .modal-content.bg-dark .form-control::placeholder { color:#99a3b3!important; }
  .modal-content.bg-dark .btn-close-white { filter:invert(1) grayscale(100%) brightness(35%); }
  .modal-content.bg-dark .list-group-item-dark { background:#fff!important; color:#344054!important; border-color:#e5eaf1!important; }
  .modal-content.bg-dark .text-warning { color:#b56a16!important; }
  .modal-content.bg-dark .text-info { color:var(--terminal-primary)!important; }
  .modal-content.bg-dark .btn-outline-light { background:#fff!important; color:#344054!important; border-color:#dfe5ee!important; }
  .modal-content.bg-dark .btn-outline-light:hover { color:var(--terminal-primary)!important; border-color:var(--terminal-primary)!important; background:#f7faff!important; }
  .modal-content.bg-dark .btn-secondary { background:#e9eef5!important; border-color:#e9eef5!important; color:#344054!important; }
  .modal-content.bg-dark .btn-secondary:hover { background:#dce4ef!important; }
  .modal-content.bg-dark .pin-btn { height:56px; border-radius:12px!important; font-size:1.22rem; font-weight:800; box-shadow:0 2px 5px rgba(16,24,40,.04); }
  #lockModal .modal-content { max-width:100%; }
  #lockModal .modal-header { padding:20px; }
  #lockModal .modal-body { padding:22px!important; }
  #pinDisplay { letter-spacing:.35em; font-weight:800; }
  .modal-content.bg-dark .btn-primary { background:var(--terminal-primary)!important; border-color:var(--terminal-primary)!important; }
  .modal-content.bg-dark .btn-success { background:var(--terminal-secondary)!important; border-color:var(--terminal-secondary)!important; }

  /* Thermal Receipt Print Styles (80mm & 58mm) */
  @media print {
    body * { visibility: hidden; }
    #thermalReceiptPrintArea, #thermalReceiptPrintArea * { visibility: visible; }
    #thermalReceiptPrintArea {
      position:absolute; left:0; top:0; width:80mm; max-width:100%; background:#fff!important; color:#000!important;
      font-family:'Courier New',Courier,monospace!important; font-size:12px!important; line-height:1.3!important; padding:5mm!important;
    }
    .no-print { display:none!important; }
  }
  @media (max-width:1199.98px) {
    .pos-header { padding-left:18px; padding-right:18px; }
    .pos-catalog-panel { padding:20px!important; }
    .pos-header-actions { gap:7px; }
  }
  @media (max-width:991.98px) {
    .pos-header { position:sticky; padding:10px 14px; flex-wrap:wrap; }
    .pos-brand-copy strong { max-width:45vw; }
    .pos-header-actions { flex-wrap:wrap; }
    .pos-catalog-panel { height:auto; min-height:0; overflow:visible; padding:18px 14px!important; }
    .pos-ticket-panel { height:auto; min-height:570px; overflow:visible; border-left:0; border-top:1px solid var(--terminal-line); }
    .ticket-items { min-height:170px; max-height:45vh; }
    .pos-empty-cart { min-height:170px; }
  }
  @media (max-width:575.98px) {
    .pos-header { align-items:flex-start!important; gap:10px!important; }
    .pos-brand-lockup { max-width:100%; }
    .pos-brand-mark { width:39px; height:39px; flex-basis:39px; }
    .pos-brand-copy strong { max-width:50vw; font-size:.9rem; }
    .pos-device-badge { display:none; }
    .pos-header-actions { width:100%; justify-content:flex-start; gap:7px; }
    .pos-header .btn { min-height:36px; }
    .pos-network-status { padding:7px 9px; }
    .pos-cashier-btn { margin-left:auto; max-width:150px; }
    .pos-cashier-name { max-width:83px; }
    .pos-catalog-panel { padding:15px 12px!important; }
    #productsGrid .col-6 { width:50%; }
    .product-card { min-height:124px; padding:12px!important; border-radius:13px!important; }
    .product-card .product-title { font-size:.78rem; }
    .product-card .product-price { font-size:.9rem; }
    .product-card .product-add-icon { width:26px; height:26px; }
    .category-pill { padding:8px 12px; font-size:.7rem; }
    .pos-order-type { padding:10px 6px!important; font-size:.68rem!important; }
    .pos-ticket-summary { padding:15px!important; }
    .pos-ticket-summary #ticketTotal { font-size:1.55rem!important; }
  }

  /* Cash settlement modal: keep the tender symbol and amounts readable in the light modal theme. */
  #checkoutModal .modal-content { background:#fff!important; color:#202b3b!important; border:1px solid #dce4ee!important; border-radius:22px!important; overflow:hidden; box-shadow:0 24px 70px rgba(15,23,42,.22); }
  #checkoutModal .modal-header { background:#e2f2e9!important; border-bottom:1px solid #dce7e0!important; }
  #checkoutModal .modal-header .modal-title { color:#202b3b!important; }
  #checkoutModal .modal-header .btn-close { filter:none!important; opacity:.55; }
  #checkoutModal .modal-body .text-white-50 { color:#758399!important; }
  #checkoutModal #checkoutTotalDisplay { color:#202b3b!important; }
  #checkoutModal .form-label { color:#344054!important; }
  #checkoutModal #quickTenderRow .btn { color:#344054!important; background:#fff!important; border:1px solid #d8e1ed!important; border-radius:12px!important; min-height:54px; }
  #checkoutModal #quickTenderRow .btn:hover { color:#145bd7!important; background:#f3f7ff!important; border-color:#a9c5ff!important; }
  #checkoutModal .input-group-text { display:flex!important; align-items:center!important; justify-content:center!important; min-width:64px!important; background:#20252c!important; color:#fff!important; border:1px solid #cfd8e5!important; font-size:1.35rem!important; }
  #checkoutModal #tenderInput { background:#fff!important; color:#202b3b!important; border:1px solid #d8e1ed!important; caret-color:#202b3b!important; }
  #checkoutModal #tenderInput:focus { border-color:#7aa7ff!important; box-shadow:0 0 0 .2rem rgba(37,99,235,.13)!important; }
  #checkoutModal .card.bg-black { background:#f4f7fb!important; }
  #checkoutModal .card .text-muted { color:#758399!important; }
  #checkoutModal .modal-footer { border-top:1px solid #e4eaf2!important; }
  #checkoutModal .modal-footer .btn-outline-secondary { color:#344054!important; border-color:#aab4c2!important; }


  /* Usability pass: readable product cards, clearer order controls, and touch targets. */
  .pos-device-badge, .pos-network-status { font-size: .82rem; }
  .pos-search-group input, .pos-search-group .form-control, .pos-search-group .form-select { min-height: 48px; font-size: 1rem; }
  .category-pill { min-height: 42px; font-size: .9rem; padding: .6rem .9rem; }
  .product-card { min-height: 190px; }
  .product-title { font-size: 1rem; line-height: 1.35; }
  .product-price { font-size: 1.05rem; font-weight: 800; }
  .product-category-tag, .product-custom-tag { font-size: .75rem; }
  .ticket-item-name { font-size: 1.1rem; line-height: 1.45; }
  .ticket-item-price { font-size: 1.06rem; font-weight: 800; }
  .ticket-item-meta, .ticket-addon-text { font-size: .92rem; line-height: 1.5; }
  .ticket-qty-btn, .ticket-remove-btn { min-width: 40px; min-height: 40px; }
  .ticket-qty { min-width: 30px; font-size: 1.05rem; }
  .pos-order-type { min-height: 44px; font-size: .92rem; }
  .pos-clear-ticket, .pos-checkout-btn { min-height: 48px; font-size: 1rem; }
  .pos-ticket-summary { font-size: 1rem; }
  .pos-ticket-summary strong { font-size: 1.12rem; }
  .modal .form-control, .modal .form-select { min-height: 46px; font-size: 1rem; }
  .modal .btn { min-height: 42px; }
  .product-card:focus-visible, .category-pill:focus-visible, .pos-order-type:focus-visible {
    outline: 3px solid rgba(37,99,235,.65); outline-offset: 2px;
  }
  @media (max-width: 767.98px) {
    .pos-header { padding: 10px 12px; }
    .pos-brand-copy strong { max-width: 46vw; font-size: .96rem; }
    .pos-header-actions { gap: 6px; flex-wrap: wrap; }
    .pos-device-badge { display: none; }
    .pos-workspace { padding: 10px !important; }
    .pos-catalog-panel, .pos-ticket-panel { border-radius: 14px; }
    .product-card { min-height: 160px; }
    .product-title { font-size: 1rem; }
    .ticket-item-name { font-size: 1.04rem; }
    .ticket-item-meta, .ticket-addon-text { font-size: .9rem; }
    .ticket-item-card { padding: 12px !important; }
    .pos-clear-ticket, .pos-checkout-btn { min-height: 48px; }
  }


  /* POS terminal visual refinement: stronger hierarchy, intentional image fallbacks, calmer surfaces. */
  .pos-header { min-height:68px; padding:10px 18px; background:rgba(255,255,255,.97); }
  .pos-catalog-panel { padding:18px 20px!important; }
  .pos-ticket-panel { box-shadow:-10px 0 28px rgba(15,23,42,.045); }
  .pos-search-group .form-control, .pos-search-group .form-select { min-height:48px; font-size:.9rem; }
  .product-card { min-height:250px; padding:12px!important; border-radius:18px!important; box-shadow:0 5px 18px rgba(24,39,75,.055); }
  .product-image-wrap { aspect-ratio: 5 / 3; margin-bottom:12px; border-radius:13px; background:linear-gradient(135deg,#e7efff 0%,#f2f6ff 48%,#e2f3f0 100%); color:#5277b8; }
  .product-image-wrap i { display:grid; place-items:center; width:52px; height:52px; border:1px solid rgba(255,255,255,.8); border-radius:17px; background:rgba(255,255,255,.72); color:#5277b8; font-size:1.65rem; box-shadow:0 6px 18px rgba(49,83,140,.08); }
  .product-image-wrap.is-fallback { background:linear-gradient(135deg,#e6efff 0%,#f1f5ff 52%,#e0f2ef 100%); }
  .product-image-wrap.is-fallback::after { content:"PHOTO NOT ADDED"; position:absolute; bottom:10px; left:0; right:0; text-align:center; font-size:.55rem; font-weight:850; letter-spacing:.13em; color:#7890b7; }
  .product-card .product-category-tag { border-radius:999px; padding:5px 8px; font-size:.65rem; }
  .product-card .product-title { font-size:.94rem; line-height:1.4; min-height:2.6em; }
  .product-card .product-price { font-size:1.08rem; }
  .product-card .product-add-icon { width:34px; height:34px; border-radius:11px; }
  .pos-ticket-panel .pos-order-type-switcher { padding:12px!important; }
  .ticket-items { padding:14px!important; background:#f5f7fb; }
  .ticket-item-card { padding:14px!important; border-radius:15px!important; }
  .pos-ticket-summary { padding:16px!important; }
  .pos-ticket-summary #ticketTotal { font-size:1.8rem!important; }
  @media (max-width: 767.98px) {
    .pos-header { padding:8px 10px; }
    .pos-catalog-panel { height:auto; min-height:0; padding:14px 12px!important; }
    .pos-ticket-panel { height:auto; min-height:460px; border-left:0; border-top:1px solid var(--terminal-line); }
    .product-card { min-height:220px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .product-card, .category-pill, .pos-order-type, .pos-checkout-btn { transition:none!important; }
  }



  /* POS is intentionally light-only; keep the shared application footer light too. */
  html[data-theme="dark"] body:has(.pos-workspace),
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-catalog-panel {
    background:#f4f6fa!important;
    color:#192335!important;
  }
  html[data-theme="dark"] .app-footer {
    background:#ffffff!important;
    color:#738096!important;
    border-top:1px solid #e3e8f0!important;
    box-shadow:none!important;
  }
  html[data-theme="dark"] .app-footer,
  html[data-theme="dark"] .app-footer * {
    color:#738096!important;
  }


  /* POS terminal stays light regardless of the management theme preference. */
  html[data-theme="dark"] body:has(.pos-workspace),
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-catalog-panel,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-panel,
  html[data-theme="dark"] body:has(.pos-workspace) .ticket-items {
    background:#f4f6fa!important;
    color:#192335!important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-panel,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-order-type-switcher,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-summary {
    background:#fff!important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace .form-select,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace select,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace .form-control,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace input {
    background-color:#fff!important;
    color:#263247!important;
    border-color:#dfe5ee!important;
    color-scheme:light;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace select option {
    background:#fff!important;
    color:#263247!important;
  }
  .pos-order-type {
    min-height:64px!important;
    padding:15px 12px!important;
    font-size:1rem!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:10px!important;
  }
  .pos-order-type i {
    font-size:1.25rem!important;
    line-height:1!important;
  }
  .ticket-item-card .ticket-item-name {
    font-size:1.05rem!important;
    line-height:1.45!important;
    font-weight:750!important;
  }
  .ticket-item-card .ticket-item-price {
    font-size:1.05rem!important;
  }
  .ticket-item-card .ticket-item-meta {
    font-size:.86rem!important;
  }
  .ticket-item-card .ticket-addon-text {
    font-size:.84rem!important;
  }
  .ticket-item-card .ticket-qty {
    font-size:1rem!important;
    font-weight:750!important;
  }
  .ticket-item-card .ticket-remove-btn {
    font-size:.86rem!important;
  }
  @media (max-width: 576px) {
    .pos-order-type { min-height:56px!important; font-size:.9rem!important; gap:6px!important; padding:12px 6px!important; }
    .pos-order-type i { font-size:1.1rem!important; }
    .ticket-item-card .ticket-item-name { font-size:.98rem!important; }
  }


  /* SERVEFLOW POS APP REDESIGN — PASS 1 */

  .pos-header {
    position: sticky;
    top: 0;
    z-index: 1030;
    min-height: 68px;
    padding: 10px 22px;
    background: rgba(255,255,255,.97);
    border-bottom: 1px solid #e7ebf2;
    box-shadow: 0 2px 10px rgba(15,23,42,.035);
    backdrop-filter: blur(14px);
  }

  .pos-brand-lockup { gap: 12px; }
  .pos-brand-mark {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #eff6ff;
    color: #2563eb;
    box-shadow: inset 0 0 0 1px #dbeafe;
  }
  .pos-brand-copy strong {
    color: #172033;
    font-size: .94rem;
    letter-spacing: -.025em;
  }
  .pos-brand-copy small { color: #8792a5; }

  .pos-device-badge {
    padding: 6px 10px;
    border: 1px solid #e7ebf2;
    border-radius: 9px;
    background: #f8fafc;
    color: #64748b;
    font-size: .72rem;
    font-weight: 700;
  }

  .pos-header-actions { gap: 8px; }
  .pos-header .btn {
    min-height: 40px;
    border-radius: 10px;
  }
  .pos-sync-btn {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    color: #2563eb;
  }
  .pos-icon-btn {
    width: 40px;
    border: 1px solid #e4e9f1;
    background: #fff;
    color: #64748b;
  }
  .pos-cashier-btn {
    padding: 5px 11px 5px 6px;
    border: 1px solid #e4e9f1;
    background: #fff;
    color: #344054;
  }

  .pos-workspace {
    min-height: calc(100vh - 68px);
    background: #f3f5f9;
  }

  @media (min-width: 1200px) {
    .pos-workspace-row {
      display: grid;
      grid-template-columns: minmax(0, 1.72fr) minmax(370px, .88fr);
      min-height: calc(100vh - 68px);
    }
    .pos-workspace-row > .pos-catalog-panel,
    .pos-workspace-row > .pos-ticket-panel {
      width: auto;
      max-width: none;
      min-width: 0;
    }
    .pos-catalog-panel {
      height: calc(100vh - 68px);
      min-height: 0;
      overflow-y: auto;
      padding: 22px 24px 30px !important;
    }
    .pos-ticket-panel {
      position: sticky;
      top: 68px;
      height: calc(100vh - 68px);
      min-height: 0;
      overflow: hidden;
      border-left: 1px solid #e5eaf1;
      box-shadow: -8px 0 24px rgba(15,23,42,.035);
    }
  }

  .pos-search-group {
    min-height: 48px;
    overflow: hidden;
    border: 1px solid #dfe5ee;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 5px rgba(15,23,42,.025);
  }
  .pos-search-group:focus-within {
    border-color: #93b4ff;
    box-shadow: 0 0 0 3px rgba(37,99,235,.09);
  }
  .pos-search-group .input-group-text {
    padding-left: 15px;
    padding-right: 10px;
    border: 0;
    background: transparent;
    color: #8491a5;
  }
  .pos-search-group .form-control,
  .pos-search-group .form-select {
    min-height: 46px;
    border: 0;
    background: transparent;
    color: #263348;
    box-shadow: none;
    font-size: .88rem;
  }
  .pos-search-group .form-control::placeholder { color: #a0aabc; }

  #posCustomerSelect,
  #posLoyaltyRedeem {
    min-height: 44px;
    border-color: #dfe5ee;
    border-radius: 10px;
    color: #344054;
    font-size: .84rem;
  }

  #categoryPillsContainer {
    gap: 8px !important;
    padding: 3px 1px 13px !important;
    margin-bottom: 12px !important;
  }
  .category-pill {
    min-height: 36px;
    padding: 7px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    color: #64748b;
    font-size: .76rem;
    font-weight: 750;
    white-space: nowrap;
    transition: .18s ease;
  }
  .category-pill:hover {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
  }
  .category-pill.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 3px 8px rgba(37,99,235,.18);
  }

  #productsGrid {
    --bs-gutter-x:  .85rem;
    --bs-gutter-y:  .85rem;
  }
  @media (min-width: 1200px) {
    #productsGrid > [class*="col-"] {
      flex: 0 0 20%;
      max-width: 20%;
    }
  }
  @media (min-width: 1200px) and (max-width: 1450px) {
    #productsGrid > [class*="col-"] {
      flex: 0 0 25%;
      max-width: 25%;
    }
  }

  .product-card {
    min-height: 220px;
    padding: 9px;
    overflow: hidden;
    border: 1px solid #e7ebf2;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 2px 7px rgba(15,23,42,.035);
    cursor: pointer;
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
  }
  .product-card:hover {
    transform: translateY(-2px);
    border-color: #c8d8f5;
    box-shadow: 0 9px 22px rgba(15,23,42,.085);
  }
  .product-image-wrap {
    height: 126px;
    margin: -1px -1px 11px;
    overflow: hidden;
    border-radius: 10px;
    background: #f1f4f8;
  }
  .product-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .product-image-wrap.is-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b6c1d0;
  }
  .product-image-wrap.is-fallback i { font-size: 2rem; }
  .product-category-tag {
    color: #8a96a8;
    font-size: .65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .045em;
  }
  .product-title {
    color: #263348;
    font-size: .82rem;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }
  .product-price {
    color: #1d4ed8;
    font-size: .9rem;
    font-weight: 850;
  }
  .product-add-icon {
    display: inline-flex;
    width: 30px;
    height: 30px;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #eff6ff;
    color: #2563eb;
  }

  .pos-ticket-panel {
    display: flex;
    flex-direction: column;
    background: #fff;
  }
  .pos-order-type-switcher {
    flex: 0 0 auto;
    gap: 7px !important;
    padding: 14px !important;
    border-bottom: 1px solid #e9edf3 !important;
    background: #fff;
  }
  .pos-order-type {
    flex: 1;
    min-height: 43px !important;
    padding: 9px 7px !important;
    border: 1px solid #e4e9f1 !important;
    border-radius: 10px !important;
    background: #f8fafc !important;
    color: #64748b !important;
    font-size: .78rem !important;
    font-weight: 750 !important;
  }
  .pos-order-type.active {
    border-color: #2563eb !important;
    background: #eff6ff !important;
    color: #2563eb !important;
    box-shadow: inset 0 0 0 1px #2563eb;
  }
  .ticket-items {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 17px !important;
    background: #f8fafc !important;
  }
  .pos-empty-cart {
    padding: 45px 16px;
    color: #8a96a8;
    text-align: center;
  }
  .pos-empty-cart h5 {
    margin-top: 12px;
    color: #344054;
    font-size: .98rem;
    font-weight: 800;
  }
  .pos-empty-cart p {
    max-width: 250px;
    margin: 6px auto 0;
    font-size: .8rem;
    line-height: 1.5;
  }
  .pos-empty-icon {
    display: inline-flex;
    width: 58px;
    height: 58px;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: #edf3ff;
    color: #5684db;
    font-size: 1.5rem;
  }

  .pos-ticket-summary {
    flex: 0 0 auto;
    padding: 17px !important;
    border-top: 1px solid #e7ebf2 !important;
    background: #fff !important;
    box-shadow: 0 -5px 16px rgba(15,23,42,.025);
  }
  .pos-ticket-summary #ticketTotal {
    color: #1d4ed8 !important;
    font-size: 1.9rem !important;
    font-weight: 900 !important;
  }
  .pos-ticket-summary .pos-checkout-btn {
    min-height: 49px;
    border-radius: 11px !important;
    background: #2563eb !important;
    box-shadow: 0 4px 10px rgba(37,99,235,.18);
    font-size: .9rem !important;
    font-weight: 850 !important;
  }
  .pos-ticket-summary .pos-clear-ticket {
    width: 46px;
    min-height: 49px;
    border-radius: 11px !important;
  }
  #saveOpenOrderBtn {
    min-height: 43px;
    border-radius: 10px;
    font-size: .82rem;
    font-weight: 750;
  }

  @media (max-width: 1199.98px) {
    .pos-catalog-panel {
      height: auto;
      min-height: 0;
      padding: 18px !important;
    }
    .pos-ticket-panel {
      position: relative;
      height: auto;
      min-height: 520px;
      border-top: 1px solid #e5eaf1;
    }
    .ticket-items { min-height: 220px; max-height: 48vh; }
  }

  @media (max-width: 767.98px) {
    .pos-header {
      position: relative;
      padding: 10px 12px;
    }
    .pos-brand-lockup { gap: 8px; }
    .pos-device-badge { display: none; }
    .pos-header-actions {
      width: 100%;
      justify-content: flex-start;
      flex-wrap: wrap;
    }
    .pos-catalog-panel { padding: 14px 11px !important; }
    #productsGrid > [class*="col-"] {
      flex: 0 0 50%;
      max-width: 50%;
    }
    .product-card { min-height: 195px; }
    .product-image-wrap { height: 105px; }
    .product-title { font-size: .77rem; }
    .pos-ticket-panel { min-height: 470px; }
    .pos-ticket-summary { padding: 14px !important; }
    .pos-ticket-summary #ticketTotal { font-size: 1.65rem !important; }
  }

  html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-catalog-panel {
    background: #111827 !important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .pos-header,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-panel,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-order-type-switcher,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-summary {
    background: #1f2937 !important;
    border-color: #374151 !important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .product-card,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-search-group,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-icon-btn,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-cashier-btn {
    background: #1f2937 !important;
    border-color: #374151 !important;
    color: #e5e7eb !important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .product-title,
  html[data-theme="dark"] body:has(.pos-workspace) .pos-brand-copy strong {
    color: #f3f4f6 !important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .ticket-items {
    background: #111827 !important;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .pos-empty-cart h5 {
    color: #e5e7eb;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .category-pill {
    background: #1f2937;
    border-color: #374151;
    color: #cbd5e1;
  }
  html[data-theme="dark"] body:has(.pos-workspace) .category-pill.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
  }


/* =========================================================
   SERVEFLOW POS — FINAL VISUAL OVERRIDES
   One consistent light interface; preserve existing behavior.
   ========================================================= */

.pos-header {
    position: sticky;
    top: 0;
    z-index: 1030;
    min-height: 68px;
    padding: 10px 22px;
    background: #1769d2 !important;
    color: #fff !important;
    border: 0 !important;
    box-shadow: 0 3px 14px rgba(15, 45, 90, .16);
}

.pos-header,
.pos-header * {
    color: #fff;
}

.pos-brand-mark {
    background: rgba(255,255,255,.16) !important;
    border: 1px solid rgba(255,255,255,.2);
}

.pos-device-badge,
.pos-network-status,
.pos-sync-btn,
.pos-icon-btn,
.pos-cashier-btn {
    color: #fff !important;
}

.pos-sync-btn,
.pos-icon-btn,
.pos-cashier-btn {
    background: rgba(255,255,255,.13) !important;
    border: 1px solid rgba(255,255,255,.24) !important;
}

.pos-workspace {
    background: #f3f6fb !important;
    color: #1c2b41 !important;
    padding: 18px !important;
}

.pos-catalog-panel,
.pos-ticket-panel {
    background: #fff !important;
    color: #1c2b41 !important;
    border: 1px solid #e5eaf2 !important;
    border-radius: 14px;
}

.pos-catalog-panel {
    padding: 20px !important;
}

.pos-ticket-panel {
    padding: 0 !important;
    box-shadow: 0 5px 24px rgba(25, 45, 75, .07);
}

.pos-search-group .form-control,
.pos-search-group .form-select {
    min-height: 46px;
    border-color: #dce3ed;
    border-radius: 9px;
    background: #fff !important;
    color: #26364c !important;
}

.category-pill {
    border-radius: 8px !important;
    padding: 9px 15px !important;
    background: #fff !important;
    color: #526176 !important;
    border: 1px solid #e0e6ef !important;
    font-weight: 600;
    white-space: nowrap;
}

.category-pill.active {
    background: #1769d2 !important;
    border-color: #1769d2 !important;
    color: #fff !important;
}

.pos-order-type-switcher {
    background: #fff !important;
    border-bottom: 1px solid #e8edf4;
    border-radius: 14px 14px 0 0;
}

.pos-order-type {
    min-height: 48px !important;
    border-radius: 8px !important;
    font-weight: 650;
}

.pos-order-type.active {
    background: #1769d2 !important;
    border-color: #1769d2 !important;
    color: #fff !important;
}

#productsGrid {
    row-gap: 16px;
}

#productsGrid > [class*="col-"] {
    display: flex;
}

.product-card {
    width: 100%;
    min-height: 0 !important;
    padding: 10px !important;
    background: #fff !important;
    color: #1c2b41 !important;
    border: 1px solid #e6ebf2 !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(20, 40, 70, .045) !important;
    transition: box-shadow .18s ease, transform .18s ease;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 18px rgba(20, 40, 70, .09) !important;
}

.product-image-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    height: auto !important;
    overflow: hidden;
    margin-bottom: 10px;
    border-radius: 9px;
    background: #f0f3f8;
}

.product-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image-wrap.is-fallback {
    display: grid;
    place-items: center;
}

.product-image-wrap.is-fallback i {
    display: block;
    color: #9aa9bd;
    background: transparent;
    border: 0;
    box-shadow: none;
}

.product-image-wrap.is-fallback::after {
    content: "IMAGE NOT AVAILABLE";
    position: absolute;
    bottom: 8px;
    left: 0;
    right: 0;
    text-align: center;
    color: #8997aa;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .06em;
}

.product-category-tag {
    background: #eef4fc !important;
    color: #42658f !important;
    border-radius: 5px !important;
    font-size: 10px !important;
}

.product-title {
    min-height: 0 !important;
    font-size: .9rem !important;
    line-height: 1.35 !important;
    color: #25354b !important;
}

.product-price {
    color: #1769d2 !important;
    font-size: 1rem !important;
    font-weight: 750;
}

.product-add-button {
    display: flex;
    width: 100%;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 9px;
    border-radius: 7px;
    background: #1769d2;
    color: #fff !important;
    font-size: .83rem;
    font-weight: 700;
}

.product-card > div:last-child {
    display: block !important;
    border-top: 1px solid #edf0f5 !important;
    padding-top: 10px;
}

.product-card > div:last-child .product-price {
    display: block;
}

.ticket-items {
    background: #f7f9fc !important;
}

.ticket-item-card {
    background: #fff !important;
    border: 1px solid #e6ebf2 !important;
    border-radius: 10px !important;
}

.pos-ticket-summary {
    background: #fff !important;
    border-top: 1px solid #e8edf4;
}

.pos-ticket-summary #ticketTotal {
    color: #1769d2 !important;
    font-size: 1.8rem !important;
    font-weight: 800;
}

.pos-checkout-btn {
    min-height: 52px;
    border-radius: 9px !important;
    background: #1769d2 !important;
    border-color: #1769d2 !important;
    color: #fff !important;
    font-weight: 750;
}

.pos-empty-cart {
    color: #8390a3;
}

/* Wide screens: six products per row. */
@media (min-width: 1600px) {
    #productsGrid > [class*="col-"] {
        flex: 0 0 16.666667%;
        max-width: 16.666667%;
    }
}

/* Desktop: four products per row. */
@media (min-width: 992px) and (max-width: 1599.98px) {
    #productsGrid > [class*="col-"] {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

/* Tablet: three products per row. */
@media (min-width: 576px) and (max-width: 991.98px) {
    #productsGrid > [class*="col-"] {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

/* Mobile: two products per row and natural document scrolling. */
@media (max-width: 575.98px) {
    .pos-header {
        padding: 9px 12px;
    }

    .pos-brand-copy strong {
        max-width: 48vw;
        font-size: .9rem;
    }

    .pos-header-actions {
        gap: 5px;
    }

    .pos-workspace {
        padding: 9px !important;
    }

    .pos-catalog-panel {
        padding: 12px !important;
    }

    #productsGrid > [class*="col-"] {
        flex: 0 0 50%;
        max-width: 50%;
    }

    .product-card {
        padding: 8px !important;
    }

    .product-title {
        font-size: .82rem !important;
    }

    .pos-ticket-panel {
        min-height: 0;
        margin-top: 12px;
    }
}

/* Keep the terminal light even if an old theme preference exists. */
html[data-theme="dark"] body:has(.pos-workspace),
html[data-theme="dark"] body:has(.pos-workspace) .pos-workspace,
html[data-theme="dark"] body:has(.pos-workspace) .pos-catalog-panel,
html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-panel,
html[data-theme="dark"] body:has(.pos-workspace) .ticket-items {
    background: #f3f6fb !important;
    color: #1c2b41 !important;
}

html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-panel,
html[data-theme="dark"] body:has(.pos-workspace) .pos-order-type-switcher,
html[data-theme="dark"] body:has(.pos-workspace) .pos-ticket-summary,
html[data-theme="dark"] body:has(.pos-workspace) .product-card {
    background: #fff !important;
    color: #1c2b41 !important;
}


/* ServeFlow POS visual polish: improve readability */
.pos-brand-copy strong {
    font-size: 1.08rem;
}

.pos-brand-copy small {
    font-size: .72rem;
}

.pos-device-badge,
.pos-network-status {
    font-size: .82rem;
}

.pos-header .btn {
    font-size: .88rem;
}

.category-pill {
    font-size: .86rem;
}

.product-card .product-category-tag,
.product-card .product-custom-tag {
    font-size: .72rem;
}

.product-card .product-title {
    font-size: 1rem;
    line-height: 1.4;
}

.product-card .product-price {
    font-size: 1.1rem;
}

.pos-order-type {
    font-size: .84rem !important;
}

.pos-search-group .form-control,
.pos-search-group .form-select {
    font-size: .95rem;
}

.ticket-item-card .ticket-item-name {
    font-size: .95rem;
}

.ticket-item-card .ticket-item-price {
    font-size: .95rem;
}

.ticket-item-card .ticket-item-meta,
.ticket-item-card .ticket-addon-text {
    font-size: .82rem;
}

.ticket-item-card .ticket-qty {
    font-size: .9rem;
}

.pos-ticket-summary .form-select {
    font-size: .84rem !important;
}

.pos-ticket-summary .pos-checkout-btn {
    font-size: 1rem !important;
}

@media (max-width: 650px) {
    .pos-brand-copy strong {
        font-size: .95rem;
    }

    .product-card .product-title {
        font-size: .94rem;
    }

    .category-pill {
        font-size: .8rem;
    }
}


/* ServeFlow POS: final workspace layout */
.pos-workspace,
.pos-workspace-row {
    width: 100% !important;
    max-width: none !important;
}

.pos-catalog-panel {
    padding: 24px !important;
}

.pos-ticket-panel {
    min-width: 0;
}

#productsGrid > [class*="col-"] {
    flex: 0 0 25% !important;
    width: 25% !important;
    max-width: 25% !important;
}

.product-card {
    min-height: 285px !important;
    padding: 14px !important;
}

.product-image-wrap {
    height: 145px !important;
    aspect-ratio: auto !important;
}

.product-card .product-title {
    font-size: 1rem !important;
    line-height: 1.45 !important;
}

.product-card .product-price {
    font-size: 1.15rem !important;
}

.product-card .product-category-tag,
.product-card .product-custom-tag {
    font-size: .75rem !important;
}

.product-card .product-add-icon {
    width: 36px !important;
    height: 36px !important;
}

.pos-header .btn,
.category-pill,
.pos-order-type {
    font-size: .9rem !important;
}

.ticket-item-card .ticket-item-name,
.ticket-item-card .ticket-item-price {
    font-size: .98rem !important;
}

@media(max-width:1199px) {
    #productsGrid > [class*="col-"] {
        flex: 0 0 33.3333% !important;
        width: 33.3333% !important;
        max-width: 33.3333% !important;
    }
}

@media(max-width:767px) {
    #productsGrid > [class*="col-"] {
        flex: 0 0 50% !important;
        width: 50% !important;
        max-width: 50% !important;
    }

    .pos-catalog-panel {
        padding: 15px !important;
    }

    .product-card {
        min-height: 225px !important;
    }

    .product-image-wrap {
        height: 110px !important;
    }
}


/* ServeFlow POS header and loyalty field correction */
.pos-header {
    min-height: 68px !important;
    padding: 10px 18px !important;
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    background: #1769d2 !important;
    border-bottom: 0 !important;
    box-shadow: 0 3px 12px rgba(15, 45, 90, .16) !important;
}

.pos-brand-lockup {
    gap: 12px !important;
}

.pos-brand-copy strong {
    color: #fff !important;
    font-size: 1.02rem !important;
    letter-spacing: -.02em;
}

.pos-brand-copy small {
    color: rgba(255,255,255,.78) !important;
    font-size: .75rem !important;
    letter-spacing: .07em;
}

.pos-header .app-back-button {
    flex-shrink: 0;
    color: #fff !important;
    background: rgba(255,255,255,.14) !important;
    border: 1px solid rgba(255,255,255,.3) !important;
}

.pos-header .app-back-button:hover {
    background: rgba(255,255,255,.24) !important;
}

.pos-header .pos-device-badge {
    color: #fff !important;
    background: rgba(255,255,255,.15) !important;
    border: 1px solid rgba(255,255,255,.22) !important;
}

.pos-header .pos-header-actions .btn {
    min-height: 40px;
    border-radius: 10px;
}



.redeem-loyalty-select,
.redeem-loyalty-select:disabled {
    background-color: #fff !important;
    color: #475569 !important;
    opacity: 1 !important;
    border-color: #dce4ef !important;
    cursor: default;
}

@media(max-width:767px) {
    .pos-header {
        padding: 9px 12px !important;
        flex-wrap: wrap;
    }

    .pos-brand-copy strong {
        font-size: .94rem !important;
    }
}


/* ServeFlow POS header: clean branding and readable controls */
.pos-header {
    min-height: 68px !important;
    padding: 10px 18px !important;
    background: #1769d2 !important;
    border: 0 !important;
    box-shadow: 0 3px 12px rgba(15,45,90,.16) !important;
}

.pos-brand-lockup {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
}

.pos-brand-copy strong {
    color: #fff !important;
    font-size: 1.05rem !important;
    font-weight: 800 !important;
}

.pos-brand-copy small {
    color: rgba(255,255,255,.88) !important;
    font-size: .76rem !important;
    font-weight: 700 !important;
    letter-spacing: .06em;
}

/* Remove the low-contrast device description from the header. */
.pos-device-badge {
    display: none !important;
}

/* Keep sync, online, download and account controls legible. */
.pos-header-actions {
    align-items: center !important;
    gap: 9px !important;
}

.pos-header-actions .btn {
    color: #fff !important;
    background: rgba(255,255,255,.14) !important;
    border: 1px solid rgba(255,255,255,.28) !important;
}

.pos-header .app-back-button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    height: 40px !important;
    min-width: 40px !important;
    padding: 0 12px !important;
    color: #fff !important;
    background: rgba(255,255,255,.16) !important;
    border: 1px solid rgba(255,255,255,.32) !important;
    border-radius: 10px !important;
    font-size: .9rem !important;
    text-decoration: none !important;
}

.pos-header .app-back-button:hover {
    color: #fff !important;
    background: rgba(255,255,255,.25) !important;
}

.pos-brand-mark:empty {
    display: none !important;
}

@media(max-width:767px) {
    .pos-header {
        padding: 9px 12px !important;
    }

    .pos-header .app-back-button {
        min-width: 38px !important;
        width: 38px !important;
        padding: 0 !important;
        font-size: 0 !important;
    }

    .pos-header .app-back-button i {
        font-size: 1rem !important;
    }
}





/* POS network status visibility correction */
#posNetworkStatus {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    visibility: visible !important;
    opacity: 1 !important;
    flex-shrink: 0 !important;
    min-height: 36px;
    padding: 6px 12px !important;
    border-radius: 999px !important;
    color: #fff !important;
    background: rgba(255,255,255,.16) !important;
    border: 1px solid rgba(255,255,255,.3) !important;
    font-size: .82rem !important;
    font-weight: 750 !important;
    white-space: nowrap !important;
}

#posNetworkStatus i,
#posNetworkStatus .bi {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.pos-header .pos-header-actions {
    display: flex !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 9px !important;
}

@media(max-width:767px) {
    .pos-header .pos-header-actions {
        flex-wrap: wrap !important;
    }
}


/* Checkout / Menus workspace tabs */
.pos-workspace-tabs {
  display:none;
  gap:8px;
  padding:12px 16px;
  background:#fff;
  border-bottom:1px solid #e3e8f0;
}
.pos-workspace-tab {
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:9px;
  flex:1 1 0;
  min-height:48px;
  padding:10px 16px;
  border:1px solid #e1e7f0;
  border-radius:12px;
  background:#f5f7fb;
  color:#64748b;
  font-size:1rem;
  font-weight:800;
  cursor:pointer;
  transition:background .15s ease,color .15s ease,border-color .15s ease;
}
.pos-workspace-tab i { font-size:1.15rem; }
.pos-workspace-tab.active {
  color:#fff;
  background:var(--terminal-primary,#2563eb);
  border-color:var(--terminal-primary,#2563eb);
  box-shadow:0 4px 12px rgba(37,99,235,.16);
}
.pos-workspace-tab:focus-visible {
  outline:3px solid rgba(37,99,235,.35);
  outline-offset:2px;
}
.pos-tab-count {
  display:inline-grid;
  place-items:center;
  min-width:23px;
  height:23px;
  padding:0 6px;
  border-radius:999px;
  background:rgba(255,255,255,.2);
  color:inherit;
  font-size:.75rem;
}
.pos-workspace-tab:not(.active) .pos-tab-count {
  background:#e8edf5;
  color:#475569;
}
.pos-tab-pane.pos-tab-hidden { display:none!important; }
.pos-workspace-tabs + .pos-workspace {
  min-height:calc(100vh - 140px);
}
@media(max-width:767.98px) {
  .pos-workspace-tabs {
    display:flex;
    position:sticky;
    top:0;
    z-index:1005;
    padding:10px 12px;
    gap:8px;
    box-shadow:0 3px 12px rgba(15,23,42,.045);
  }
  .pos-workspace-tab {
    min-height:52px;
    font-size:1rem;
    border-radius:13px;
  }
  .pos-workspace-tabs + .pos-workspace { min-height:0; }
  #posMenusPane, #posCheckoutPane {
    width:100%!important;
    max-width:100%!important;
    flex:0 0 100%!important;
  }
}

/* ServeFlow POS fullscreen checkout and compact mobile header */

/* ServeFlow POS fullscreen checkout and compact mobile header */

/* Desktop: keep the two-panel workspace filling the available screen. */
@media (min-width: 768px) {
    body:has(#posCheckoutPane) {
        min-height: 100vh;
    }

    #posCheckoutPane,
    #posMenusPane {
        min-height: calc(100vh - 150px);
    }

    .pos-ticket-panel {
        min-height: calc(100vh - 150px);
        display: flex;
        flex-direction: column;
    }
}

/* Mobile: use the viewport as the POS application workspace. */
@media (max-width: 767.98px) {
    body:has(#posCheckoutPane) {
        min-height: 100dvh;
    }

    body:has(#posCheckoutPane) .app-footer,
    body:has(#posCheckoutPane) footer {
        display: none !important;
    }

    #posCheckoutPane {
        min-height: calc(100dvh - 180px) !important;
        display: flex;
        flex-direction: column;
    }

    #posCheckoutPane .pos-ticket-panel {
        min-height: calc(100dvh - 180px) !important;
        display: flex;
        flex-direction: column;
    }

    /* Keep the ticket panel tall; allow the item list to scroll. */
    .pos-ticket-panel > .card {
        min-height: calc(100dvh - 180px);
        display: flex;
        flex-direction: column;
    }

    .pos-ticket-panel .ticket-items,
    .pos-ticket-panel .cart-items,
    .pos-ticket-panel .order-items {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }

    /* One-line mobile header. */
    .pos-header {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 7px !important;
        min-height: 64px !important;
        padding: 7px 9px !important;
        overflow: hidden;
    }

    .pos-brand-lockup {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        gap: 7px !important;
        overflow: hidden;
    }

    .pos-brand-copy {
        min-width: 0 !important;
        overflow: hidden;
    }

    .pos-brand-copy strong {
        display: block !important;
        max-width: 145px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: .88rem !important;
    }

    .pos-brand-copy small {
        display: none !important;
    }

    .pos-header-actions {
        display: flex !important;
        flex: 0 0 auto !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 4px !important;
        min-width: 0 !important;
    }

    .pos-header-actions .btn {
        min-width: 34px !important;
        min-height: 36px !important;
        padding: 5px 7px !important;
        font-size: .75rem !important;
        white-space: nowrap !important;
    }

    #posNetworkStatus {
        min-height: 34px !important;
        padding: 5px 8px !important;
        font-size: .72rem !important;
    }

    .pos-header-actions .dropdown-toggle {
        max-width: 44px !important;
        overflow: hidden !important;
    }

    .pos-header-actions .dropdown-toggle .user-name,
    .pos-header-actions .dropdown-toggle .user-role {
        display: none !important;
    }
}

/* Very narrow phones: prioritize the brand and essential status controls. */
@media (max-width: 420px) {
    .pos-header {
        gap: 5px !important;
        padding-left: 6px !important;
        padding-right: 6px !important;
    }

    .pos-brand-copy strong {
        max-width: 105px;
        font-size: .78rem !important;
    }

    .pos-header .app-back-button {
        width: 34px !important;
        min-width: 34px !important;
        height: 34px !important;
    }

    .pos-header-actions .btn {
        min-width: 31px !important;
        padding: 4px 5px !important;
    }

    #posNetworkStatus {
        width: 31px !important;
        min-width: 31px !important;
        overflow: hidden !important;
        font-size: 0 !important;
    }

    #posNetworkStatus::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #22c55e;
    }
}


/* ServeFlow POS: authoritative header layout */
.pos-header {
    display:flex !important;
    flex-wrap:nowrap !important;
    align-items:center !important;
    justify-content:space-between !important;
    gap:12px !important;
    width:100% !important;
    min-height:70px !important;
    padding:10px 18px !important;
    overflow:visible !important;
    background:#087fc2 !important;
    color:#fff !important;
}

.pos-brand-lockup {
    display:flex !important;
    flex:1 1 auto !important;
    align-items:center !important;
    min-width:0 !important;
    gap:11px !important;
}

.pos-brand-mark {
    display:flex !important;
    flex:0 0 44px !important;
    width:44px !important;
    height:44px !important;
    align-items:center !important;
    justify-content:center !important;
    overflow:hidden !important;
    border-radius:10px !important;
    background:rgba(255,255,255,.16) !important;
}

.pos-brand-mark img {
    display:block !important;
    width:100% !important;
    height:100% !important;
    object-fit:contain !important;
    padding:3px !important;
    background:#fff !important;
}

.pos-brand-copy {
    display:flex !important;
    flex-direction:column !important;
    min-width:0 !important;
    overflow:hidden !important;
}

.pos-brand-copy strong {
    display:block !important;
    max-width:360px !important;
    overflow:hidden !important;
    color:#fff !important;
    font-size:1.05rem !important;
    font-weight:800 !important;
    white-space:nowrap !important;
    text-overflow:ellipsis !important;
}

.pos-brand-copy small {
    display:block !important;
    overflow:hidden !important;
    color:rgba(255,255,255,.85) !important;
    font-size:.72rem !important;
    white-space:nowrap !important;
    text-overflow:ellipsis !important;
}

.pos-device-badge {
    display:none !important;
}

.pos-header-actions {
    display:flex !important;
    flex:0 0 auto !important;
    align-items:center !important;
    justify-content:flex-end !important;
    flex-wrap:nowrap !important;
    gap:8px !important;
}

.pos-header-actions .btn,
.pos-network-status {
    color:#fff !important;
    background:rgba(255,255,255,.15) !important;
    border:1px solid rgba(255,255,255,.28) !important;
}

.pos-network-status {
    display:inline-flex !important;
    align-items:center !important;
    gap:7px !important;
    padding:8px 11px !important;
    border-radius:10px !important;
    font-size:.8rem !important;
    font-weight:750 !important;
    white-space:nowrap !important;
}

.pos-status-dot {
    display:inline-block !important;
    width:8px !important;
    height:8px !important;
    flex:0 0 8px !important;
    border-radius:50% !important;
    background:#4ade80 !important;
}

.pos-terminal-back {
    display:inline-flex !important;
    flex:0 0 38px !important;
    width:38px !important;
    height:38px !important;
    align-items:center !important;
    justify-content:center !important;
    border:1px solid rgba(255,255,255,.3) !important;
    border-radius:10px !important;
    color:#fff !important;
    background:rgba(255,255,255,.15) !important;
    text-decoration:none !important;
    font-size:1.1rem !important;
}

.pos-terminal-back:hover {
    color:#fff !important;
    background:rgba(255,255,255,.28) !important;
}

@media(max-width:767.98px) {
    .pos-header {
        min-height:60px !important;
        padding:7px 9px !important;
        gap:6px !important;
    }

    .pos-brand-lockup {
        gap:6px !important;
    }

    .pos-brand-mark {
        display:none !important;
    }

    .pos-brand-copy strong {
        max-width:115px !important;
        font-size:.82rem !important;
    }

    .pos-brand-copy small {
        display:none !important;
    }

    .pos-terminal-back {
        flex-basis:32px !important;
        width:32px !important;
        height:34px !important;
    }

    .pos-header-actions {
        gap:4px !important;
    }

    .pos-header-actions .btn {
        min-width:32px !important;
        min-height:34px !important;
        padding:4px 6px !important;
        font-size:.72rem !important;
    }

    #posNetworkStatus {
        min-height:32px !important;
        padding:5px 7px !important;
        font-size:.7rem !important;
    }
}

@media(max-width:380px) {
    .pos-brand-copy strong {
        max-width:85px !important;
    }

    .pos-header-actions {
        gap:3px !important;
    }
}


/* ServeFlow mobile POS refinement: consolidated responsive behavior. */
@media (max-width: 767.98px) {
  html, body { min-height:100%; height:100%; }
  body:has(#posCheckoutPane) { min-height:100dvh; overflow-x:hidden; }
  .pos-header {
    position:relative !important; display:flex !important; flex-wrap:nowrap !important;
    align-items:center !important; justify-content:flex-start !important; gap:8px !important;
    width:100% !important; min-height:58px !important; padding:7px 10px !important;
    overflow:visible !important;
  }
  .pos-terminal-back {
    display:inline-flex !important; flex:0 0 34px !important; width:34px !important;
    height:36px !important; align-items:center !important; justify-content:center !important;
  }
  .pos-brand-lockup {
    display:flex !important; flex:1 1 auto !important; min-width:0 !important;
    align-items:center !important; gap:7px !important; overflow:hidden !important;
  }
  .pos-brand-mark {
    display:grid !important; flex:0 0 34px !important; width:34px !important;
    height:34px !important; border-radius:9px !important;
  }
  .pos-brand-mark img { width:100% !important; height:100% !important; object-fit:contain !important; }
  .pos-brand-copy { min-width:0 !important; overflow:hidden !important; }
  .pos-brand-copy strong {
    max-width:clamp(78px, 25vw, 145px) !important; font-size:.82rem !important;
    line-height:1.15 !important; white-space:nowrap !important; overflow:hidden !important;
    text-overflow:ellipsis !important;
  }
  .pos-brand-copy small, .pos-device-badge { display:none !important; }
  .pos-header-actions {
    display:flex !important; flex:0 0 auto !important; width:auto !important;
    min-width:0 !important; flex-wrap:nowrap !important; align-items:center !important;
    justify-content:flex-end !important; gap:4px !important;
  }
  .pos-header-actions .btn {
    display:inline-flex !important; flex:0 0 auto !important; align-items:center !important;
    justify-content:center !important; min-width:32px !important; min-height:34px !important;
    padding:4px 6px !important; border-radius:8px !important; font-size:0 !important;
  }
  .pos-header-actions .btn i { font-size:.92rem !important; }
  #posSyncBtn { gap:3px !important; }
  #posSyncBtn > span:not(.pos-sync-count) { display:none !important; }
  #posSyncBtn .pos-sync-count {
    display:inline-grid !important; min-width:16px !important; height:16px !important;
    padding:0 3px !important; font-size:.6rem !important;
  }
  #posNetworkStatus {
    display:inline-flex !important; flex:0 0 auto !important; min-width:0 !important;
    min-height:31px !important; padding:5px 7px !important; gap:5px !important;
    border-radius:8px !important; font-size:.66rem !important; white-space:nowrap !important;
  }
  .pos-header-actions .dropdown { flex:0 0 auto !important; }
  .pos-header-actions .pos-cashier-btn {
    width:34px !important; min-width:34px !important; max-width:34px !important;
    min-height:34px !important; margin:0 !important; padding:3px !important;
  }
  .pos-cashier-btn .pos-cashier-avatar { width:25px !important; height:25px !important; }
  .pos-cashier-name, .pos-cashier-btn.dropdown-toggle::after { display:none !important; }

  .pos-workspace-tabs {
    position:sticky !important; top:0 !important; z-index:1005 !important;
    display:flex !important; gap:8px !important; padding:8px 10px !important;
    background:#fff !important;
  }
  .pos-workspace-tab {
    min-height:42px !important; padding:8px 12px !important;
    border-radius:10px !important; font-size:.86rem !important;
  }
  .pos-workspace { min-height:0 !important; }
  #posMenusPane, #posCheckoutPane {
    width:100% !important; max-width:100% !important; flex:0 0 100% !important;
  }
  #posMenusPane {
    height:auto !important; min-height:0 !important; max-height:none !important;
    overflow:visible !important; padding:12px 10px 24px !important;
  }
  #posCheckoutPane {
    display:flex !important; flex-direction:column !important;
    height:calc(100dvh - 108px) !important;
    min-height:calc(100dvh - 108px) !important;
    max-height:calc(100dvh - 108px) !important;
    overflow:hidden !important; border:0 !important; background:#fff !important;
  }
  #posCheckoutPane .pos-order-type-switcher {
    flex:0 0 auto !important; padding:8px 10px !important; gap:6px !important;
  }
  #posCheckoutPane .pos-order-type {
    min-height:42px !important; padding:8px 5px !important;
    border-radius:8px !important; font-size:.76rem !important;
  }
  #posCheckoutPane .ticket-items {
    flex:1 1 0 !important; min-height:0 !important; max-height:none !important;
    overflow-y:auto !important; padding:10px !important; overscroll-behavior:contain;
    background:#f5f7fb !important;
  }
  #posCheckoutPane .pos-empty-cart {
    min-height:100% !important; height:auto !important; padding:14px !important;
  }
  #posCheckoutPane .pos-empty-cart .pos-empty-icon {
    width:46px !important; height:46px !important; margin-bottom:9px !important;
    font-size:1.2rem !important;
  }
  #posCheckoutPane .pos-empty-cart h5 { margin-bottom:4px !important; font-size:.87rem !important; }
  #posCheckoutPane .pos-empty-cart p { font-size:.75rem !important; }
  #posCheckoutPane .pos-ticket-summary {
    flex:0 0 auto !important; max-height:42dvh !important; overflow-y:auto !important;
    padding:10px 12px calc(10px + env(safe-area-inset-bottom)) !important;
    box-shadow:0 -4px 16px rgba(15,23,42,.06) !important;
  }
  #posCheckoutPane .pos-ticket-summary #ticketTotal { font-size:1.45rem !important; }
  #posCheckoutPane .pos-ticket-summary .pos-checkout-btn,
  #posCheckoutPane .pos-ticket-summary .pos-clear-ticket { min-height:42px !important; }
  body:has(#posCheckoutPane) .app-footer,
  body:has(#posCheckoutPane) footer { display:none !important; }
}
@media (max-width: 380px) {
  .pos-header { gap:5px !important; padding-left:7px !important; padding-right:7px !important; }
  .pos-brand-copy strong { max-width:72px !important; }
  .pos-header-actions { gap:3px !important; }
  #posNetworkStatus { padding:5px 6px !important; }
}

/* POS mobile header corrections: one Back control, clear status, visible cashier menu. */
@media (max-width: 767.98px) {
  /* The shared layout Back button is retained; remove the duplicate view-level button. */
  .pos-terminal-back { display:none !important; }

  #posNetworkStatus {
    width:30px !important;
    min-width:30px !important;
    max-width:30px !important;
    height:30px !important;
    min-height:30px !important;
    padding:0 !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    overflow:hidden !important;
    border:0 !important;
    border-radius:50% !important;
    background:transparent !important;
    box-shadow:none !important;
    font-size:0 !important;
  }
  #posNetworkText { display:none !important; }
  #posNetworkStatus .pos-status-dot {
    display:block !important;
    width:11px !important;
    height:11px !important;
    min-width:11px !important;
    border-radius:50% !important;
    background:#22c55e !important;
    box-shadow:0 0 0 4px rgba(34,197,94,.18) !important;
  }
  #posNetworkStatus.offline .pos-status-dot {
    background:#f59e0b !important;
    box-shadow:0 0 0 4px rgba(245,158,11,.2) !important;
  }

  .pos-header .pos-cashier-btn {
    width:38px !important;
    min-width:38px !important;
    max-width:38px !important;
    height:38px !important;
    min-height:38px !important;
    padding:3px !important;
    overflow:visible !important;
  }
  .pos-header .pos-cashier-avatar {
    display:grid !important;
    place-items:center !important;
    width:29px !important;
    height:29px !important;
    border-radius:8px !important;
    background:#fff !important;
    color:#087fc2 !important;
  }
  .pos-header .pos-cashier-avatar i {
    display:inline-block !important;
    color:#087fc2 !important;
    font-size:1.15rem !important;
    line-height:1 !important;
    visibility:visible !important;
    opacity:1 !important;
  }

  .pos-header .dropdown { position:relative !important; }
  .pos-header .dropdown-menu {
    position:absolute !important;
    z-index:3000 !important;
    min-width:220px !important;
    margin-top:8px !important;
    padding:8px !important;
    background:#fff !important;
    color:#344054 !important;
    border:1px solid #e2e8f0 !important;
    border-radius:14px !important;
    box-shadow:0 18px 48px rgba(15,23,42,.22) !important;
  }
  .pos-header .dropdown-menu .dropdown-header {
    display:block !important;
    padding:9px 11px !important;
    color:#64748b !important;
    font-size:.72rem !important;
    font-weight:800 !important;
    white-space:normal !important;
  }
  .pos-header .dropdown-menu .dropdown-item {
    display:flex !important;
    align-items:center !important;
    min-height:42px !important;
    padding:10px 11px !important;
    color:#344054 !important;
    background:transparent !important;
    font-size:.86rem !important;
    white-space:normal !important;
  }
  .pos-header .dropdown-menu .dropdown-item i { color:#64748b !important; }
  .pos-header .dropdown-menu .dropdown-item:hover,
  .pos-header .dropdown-menu .dropdown-item:focus {
    color:#087fc2 !important;
    background:#eff6ff !important;
  }
  .pos-header .dropdown-menu .dropdown-item.text-danger,
  .pos-header .dropdown-menu .dropdown-item.text-danger i { color:#dc2626 !important; }
  .pos-header .dropdown-menu .dropdown-divider { border-color:#e5eaf1 !important; }
}

/* Prevent a second, pseudo-element network dot; use the real status indicator only. */
@media (max-width: 767.98px) {
  #posNetworkStatus::before,
  #posNetworkStatus::after {
    content:none !important;
    display:none !important;
  }
}

/* Segmented Checkout / Menus switch and floating POS quick navigation. */
@media (max-width: 767.98px) {
  #posWorkspaceTabs {
    display:flex !important;
    align-items:center !important;
    gap:4px !important;
    margin:0 !important;
    padding:5px !important;
    border:1px solid #e2e8f0 !important;
    border-radius:14px !important;
    background:#edf2f8 !important;
    box-shadow:inset 0 1px 2px rgba(15,23,42,.04) !important;
  }
  #posWorkspaceTabs .pos-workspace-tab {
    flex:1 1 50% !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:8px !important;
    min-width:0 !important;
    min-height:43px !important;
    padding:9px 10px !important;
    border:0 !important;
    border-radius:10px !important;
    background:transparent !important;
    color:#64748b !important;
    box-shadow:none !important;
    font-size:.88rem !important;
    font-weight:750 !important;
    transition:background .18s ease,color .18s ease,box-shadow .18s ease !important;
  }
  #posWorkspaceTabs .pos-workspace-tab.active {
    background:#fff !important;
    color:#1769d2 !important;
    box-shadow:0 2px 7px rgba(15,23,42,.12) !important;
  }
  #posWorkspaceTabs .pos-workspace-tab i { font-size:1rem !important; }
  #posWorkspaceTabs .pos-tab-count {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    min-width:20px !important;
    height:20px !important;
    padding:0 6px !important;
    border-radius:999px !important;
    background:#e8f1ff !important;
    color:#1769d2 !important;
    font-size:.68rem !important;
    font-weight:800 !important;
  }
  #posWorkspaceTabs .pos-workspace-tab.active .pos-tab-count {
    background:#1769d2 !important;
    color:#fff !important;
  }
}
.pos-floating-nav {
  position:fixed;
  right:18px;
  bottom:22px;
  z-index:1200;
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:10px;
  pointer-events:none;
}
.pos-floating-nav button,
.pos-floating-nav a { pointer-events:auto; }
.pos-floating-nav-list {
  display:none;
  flex-direction:column;
  align-items:stretch;
  gap:7px;
  width:218px;
  padding:9px;
  border:1px solid #e2e8f0;
  border-radius:16px;
  background:#fff;
  box-shadow:0 16px 42px rgba(15,23,42,.2);
}
.pos-floating-nav.is-open .pos-floating-nav-list { display:flex; }
.pos-floating-nav-item {
  display:flex;
  align-items:center;
  gap:11px;
  min-height:43px;
  padding:9px 11px;
  border:0;
  border-radius:10px;
  background:#fff;
  color:#334155;
  text-decoration:none;
  text-align:left;
  font-size:.84rem;
  font-weight:700;
}
.pos-floating-nav-item:hover,
.pos-floating-nav-item:focus-visible {
  color:#1769d2;
  background:#eff6ff;
  outline:none;
}
.pos-floating-nav-item i { width:19px; text-align:center; font-size:1rem; color:#64748b; }
.pos-floating-nav-trigger {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:9px;
  min-width:54px;
  height:54px;
  padding:0 17px;
  border:0;
  border-radius:999px;
  background:#1769d2;
  color:#fff;
  box-shadow:0 8px 22px rgba(23,105,210,.34);
  font-size:1.15rem;
}
.pos-floating-nav-trigger:hover { background:#125bb9; color:#fff; }
.pos-floating-nav-trigger .pos-floating-nav-trigger-label { display:none; font-size:.82rem; font-weight:800; }
.pos-floating-nav.is-open .pos-floating-nav-trigger { background:#334155; }
@media (max-width: 767.98px) {
  .pos-floating-nav { right:14px; bottom:calc(16px + env(safe-area-inset-bottom)); }
  .pos-floating-nav-list { width:min(218px, calc(100vw - 36px)); }
  .pos-floating-nav-trigger { width:54px; min-width:54px; height:54px; padding:0; }
}
@media (min-width: 768px) {
  .pos-floating-nav-trigger .pos-floating-nav-trigger-label { display:inline; }
}

/* POS quick actions: mobile speed-dial above subtotal; desktop uses header shortcuts. */
.pos-floating-nav { display:none !important; }
.pos-desktop-quick-actions { display:flex; align-items:center; gap:6px; }
.pos-desktop-action {
  min-height:36px !important;
  padding:7px 10px !important;
  color:#fff !important;
  background:rgba(255,255,255,.14) !important;
  border:1px solid rgba(255,255,255,.22) !important;
  border-radius:9px !important;
  white-space:nowrap;
}
.pos-desktop-action:hover { color:#fff !important; background:rgba(255,255,255,.24) !important; }
.pos-desktop-action .pos-sync-count {
  display:inline-grid; place-items:center; min-width:19px; height:19px; padding:0 5px;
  border-radius:999px; background:rgba(255,255,255,.2); color:#fff; font-size:.65rem;
}
@media (max-width: 767.98px) {
  .pos-desktop-quick-actions { display:none !important; }
  .pos-floating-nav {
    display:flex !important;
    position:relative !important;
    inset:auto !important;
    z-index:20 !important;
    flex-direction:column !important;
    align-items:flex-end !important;
    gap:12px !important;
    width:100% !important;
    margin:0 0 16px !important;
    pointer-events:none !important;
  }
  .pos-floating-nav button,
  .pos-floating-nav a { pointer-events:auto !important; }
  .pos-floating-nav-list {
    display:none !important;
    flex-direction:column !important;
    align-items:flex-end !important;
    gap:13px !important;
    width:auto !important;
    padding:0 !important;
    border:0 !important;
    border-radius:0 !important;
    background:transparent !important;
    box-shadow:none !important;
  }
  .pos-floating-nav.is-open .pos-floating-nav-list { display:flex !important; }
  .pos-floating-nav-item {
    display:flex !important;
    align-items:center !important;
    justify-content:flex-end !important;
    gap:22px !important;
    min-height:0 !important;
    padding:0 !important;
    border:0 !important;
    border-radius:0 !important;
    background:transparent !important;
    color:#fff !important;
    text-decoration:none !important;
    box-shadow:none !important;
  }
  .pos-quick-label {
    display:inline-flex !important;
    align-items:center !important;
    min-height:38px !important;
    padding:5px 14px !important;
    border-radius:11px !important;
    background:#202b3a !important;
    color:#fff !important;
    box-shadow:0 8px 18px rgba(15,23,42,.13) !important;
    font-size:.9rem !important;
    font-weight:700 !important;
    white-space:nowrap !important;
  }
  .pos-quick-icon {
    position:relative !important;
    display:grid !important;
    place-items:center !important;
    flex:0 0 64px !important;
    width:64px !important;
    height:64px !important;
    border-radius:50% !important;
    color:#fff !important;
    font-size:1.45rem !important;
    box-shadow:0 8px 20px rgba(15,23,42,.13) !important;
  }
  .pos-quick-orders .pos-quick-icon { background:#0788c9 !important; }
  .pos-quick-pending .pos-quick-icon { background:#ff7417 !important; }
  .pos-quick-printer .pos-quick-icon { background:#4b5563 !important; }
  .pos-quick-badge {
    position:absolute !important;
    top:-2px !important;
    right:-2px !important;
    display:grid !important;
    place-items:center !important;
    min-width:20px !important;
    height:20px !important;
    padding:0 5px !important;
    border:2px solid #fff !important;
    border-radius:50% !important;
    background:#ef4444 !important;
    color:#fff !important;
    font-size:.62rem !important;
    font-weight:800 !important;
  }
  .pos-floating-nav-trigger {
    display:grid !important;
    place-items:center !important;
    flex:0 0 68px !important;
    width:68px !important;
    height:68px !important;
    padding:0 !important;
    border:3px solid #1769d2 !important;
    border-radius:50% !important;
    background:#0da5e9 !important;
    color:#fff !important;
    box-shadow:0 8px 22px rgba(15,23,42,.17) !important;
    font-size:2rem !important;
    pointer-events:auto !important;
  }
  .pos-floating-nav.is-open .pos-floating-nav-trigger { background:#0da5e9 !important; }
  .pos-floating-nav-trigger-label { display:none !important; }
}


/* Anchor the speed-dial over the grey cart area, not inside the white subtotal panel. */
@media (max-width: 767.98px) {
  .pos-ticket-panel { position:relative !important; }
  .ticket-items { position:relative !important; }
  .pos-floating-nav {
    position:absolute !important;
    right:16px !important;
    bottom:16px !important;
    width:auto !important;
    margin:0 !important;
    z-index:30 !important;
  }
  .pos-ticket-summary { position:relative !important; z-index:2 !important; }
}

/* Correct cart-stage positioning: speed dial floats within grey ticket area above subtotal. */
@media (max-width: 767.98px) {
  #posCheckoutPane {
    display:flex !important;
    flex-direction:column !important;
    min-height:0 !important;
  }
  #posCheckoutPane .pos-cart-stage {
    position:relative !important;
    display:flex !important;
    flex:1 1 0 !important;
    min-height:0 !important;
    overflow:hidden !important;
    background:#f5f7fb !important;
  }
  #posCheckoutPane .pos-cart-stage #ticketItemsList {
    position:relative !important;
    flex:1 1 auto !important;
    width:100% !important;
    height:100% !important;
    min-height:0 !important;
    max-height:none !important;
    overflow-y:auto !important;
    padding:10px 10px 88px !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-floating-nav {
    position:absolute !important;
    right:12px !important;
    bottom:12px !important;
    z-index:50 !important;
    width:auto !important;
    margin:0 !important;
    pointer-events:none !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-floating-nav button,
  #posCheckoutPane .pos-cart-stage .pos-floating-nav a {
    pointer-events:auto !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-list {
    gap:8px !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-quick-icon {
    flex:0 0 52px !important;
    width:52px !important;
    height:52px !important;
    color:#fff !important;
    font-size:1.25rem !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-quick-icon i {
    display:inline-block !important;
    color:#fff !important;
    opacity:1 !important;
    visibility:visible !important;
    font-family:"bootstrap-icons" !important;
    font-style:normal !important;
    line-height:1 !important;
    font-size:1.25rem !important;
    -webkit-font-smoothing:antialiased !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-quick-icon .pos-quick-badge {
    color:#fff !important;
    font-family:inherit !important;
    font-size:.62rem !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-quick-label {
    min-height:34px !important;
    padding:4px 11px !important;
    font-size:.82rem !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger {
    flex:0 0 54px !important;
    width:54px !important;
    height:54px !important;
    min-width:54px !important;
    min-height:54px !important;
    border-width:2px !important;
    font-size:1.55rem !important;
  }
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger i {
    color:#fff !important;
    opacity:1 !important;
    visibility:visible !important;
  }
}
@media (min-width: 768px) {
  .pos-cart-stage { display:contents; }
}

/* Reference-style pill switch and borderless quick-action launcher. */
@media (max-width: 767.98px) {
  #posWorkspaceTabs {
    display:flex !important;
    align-items:center !important;
    gap:0 !important;
    width:calc(100% - 28px) !important;
    margin:14px auto 16px !important;
    padding:8px !important;
    min-height:72px !important;
    border:0 !important;
    border-radius:999px !important;
    background:#e2e8f0 !important;
    box-shadow:none !important;
  }
  #posWorkspaceTabs .pos-workspace-tab {
    flex:1 1 50% !important;
    min-width:0 !important;
    min-height:56px !important;
    padding:10px 14px !important;
    border:0 !important;
    border-radius:999px !important;
    background:transparent !important;
    color:#667085 !important;
    box-shadow:none !important;
    font-size:1rem !important;
    font-weight:750 !important;
    gap:7px !important;
  }
  #posWorkspaceTabs .pos-workspace-tab.active {
    background:#fff !important;
    color:#087fc2 !important;
    box-shadow:0 4px 10px rgba(15,23,42,.12) !important;
  }
  #posWorkspaceTabs .pos-workspace-tab > i { display:none !important; }
  #posWorkspaceTabs .pos-tab-count {
    min-width:21px !important;
    height:21px !important;
    padding:0 6px !important;
    border-radius:999px !important;
    background:#e8f1ff !important;
    color:#087fc2 !important;
    font-size:.7rem !important;
  }
  #posWorkspaceTabs .pos-workspace-tab.active .pos-tab-count {
    background:#087fc2 !important;
    color:#fff !important;
  }

  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger,
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger:hover,
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger:focus,
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger:focus-visible,
  #posCheckoutPane .pos-cart-stage .pos-floating-nav-trigger:active {
    border:0 !important;
    outline:0 !important;
    box-shadow:0 8px 22px rgba(15,23,42,.17) !important;
  }
}

/* Compact mobile Checkout / Menus pill switch. */
@media (max-width: 767.98px) {
  #posWorkspaceTabs {
    width:calc(100% - 32px) !important;
    min-height:0 !important;
    margin:10px auto 12px !important;
    padding:5px !important;
    border-radius:999px !important;
  }
  #posWorkspaceTabs .pos-workspace-tab {
    min-height:44px !important;
    padding:7px 10px !important;
    font-size:.9rem !important;
    gap:6px !important;
  }
  #posWorkspaceTabs .pos-tab-count {
    min-width:19px !important;
    height:19px !important;
    font-size:.66rem !important;
  }
  /* Reduce the blank vertical gap before the order-type controls. */
  #posWorkspaceTabs + .pos-workspace {
    margin-top:0 !important;
    padding-top:0 !important;
  }
  #posWorkspaceTabs + .pos-workspace #posCheckoutPane,
  #posWorkspaceTabs + .pos-workspace #posMenusPane {
    margin-top:0 !important;
  }
}
</style>

<!-- Modern POS Header Bar -->
<header class="pos-header d-flex flex-wrap justify-content-between align-items-center gap-3 no-print">
<div class="pos-brand-lockup">
    
    <span class="pos-brand-mark">
      <?php if ($posLogoUrl !== ''): ?>
        <img src="<?= htmlspecialchars($posLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($posRestaurantName, ENT_QUOTES, 'UTF-8') ?> logo">
      <?php else: ?>
        <i class="bi bi-shop-window" aria-hidden="true"></i>
      <?php endif; ?>
    </span>
    <div class="pos-brand-copy">
      <strong><?= htmlspecialchars($posRestaurantName, ENT_QUOTES, 'UTF-8') ?></strong>
      <small><?php if ($posTagline !== ''): ?><?= htmlspecialchars($posTagline, ENT_QUOTES, 'UTF-8') ?><?php else: ?><i class="bi bi-display me-1"></i>Point of sale<?php endif; ?></small>
    </div>
    <span class="pos-device-badge"><i class="bi bi-tablet-landscape"></i><?= htmlspecialchars((string)($activeDevice['device_name'] ?? 'Main Terminal'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div class="pos-header-actions">
    <span id="posNetworkStatus" class="pos-network-status online" role="status" aria-live="polite">
      <span class="pos-status-dot" aria-hidden="true"></span>
      <span id="posNetworkText">Online</span>
    </span>

    <div class="pos-desktop-quick-actions no-print">
      <a class="btn pos-desktop-action" href="<?= BASE_URL ?>/bills" title="Orders and bills">
        <i class="bi bi-list-check"></i><span>Orders</span>
      </a>
      <button class="btn pos-desktop-action" type="button" id="posDesktopPending" onclick="window.posApp.syncQueue()" title="Sync pending orders">
        <i class="bi bi-hourglass-split"></i><span>Pending</span><span class="pos-sync-count" id="posDesktopPendingCount">0</span>
      </button>
      <button class="btn pos-desktop-action" type="button" id="posDesktopPrint" onclick="window.print()" title="Print the current receipt">
        <i class="bi bi-printer-fill"></i><span>Printer</span>
      </button>
    </div>

    <button id="posSyncBtn" class="btn pos-sync-btn" onclick="window.posApp.syncQueue()" title="Synchronize saved orders">
      <i class="bi bi-arrow-repeat"></i><span>Sync</span><span class="pos-sync-count" id="unsyncedCount">0</span>
    </button>

    <button class="btn pos-icon-btn" type="button" title="Download emergency order backup" aria-label="Download emergency order backup" onclick="window.PosDB.exportUnsyncedOrdersJSON()">
      <i class="bi bi-download"></i>
    </button>

    <div class="dropdown">
      <button class="btn pos-cashier-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open cashier menu">
        <span class="pos-cashier-avatar"><i class="bi bi-person-fill"></i></span>
        <span id="activeCashierName" class="pos-cashier-name"><?= htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? 'Cashier'), ENT_QUOTES, 'UTF-8') ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">POS Terminal Session</h6></li>
        <li><a class="dropdown-item" href="javascript:void(0)" onclick="window.posApp.showLockModal()"><i class="bi bi-shield-lock me-2"></i>Lock Terminal (PIN)</a></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/shifts/active"><i class="bi bi-safe2 me-2"></i>Manage Cash Drawer</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Exit POS</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- Offline navigation/reload guard notices are separate from sync errors. -->
<div id="posOfflineGuardNotice" class="alert alert-warning py-2 px-3 mx-3 mt-2 mb-0 d-none" role="status" aria-live="polite"></div>

<!-- Visible sync diagnostics: keep queued orders safe and explain retry failures to the cashier. -->
<div id="posSyncNotice" class="alert alert-warning py-2 px-3 mx-3 mt-2 mb-0 d-none" role="alert" aria-live="polite"></div>

<!-- Main POS Workspace -->


<!-- POS Workspace Tabs -->
<div id="posWorkspaceTabs" class="pos-workspace-tabs no-print" role="tablist" aria-label="POS workspace">
  <button type="button" class="pos-workspace-tab active"
          id="posCheckoutTab" role="tab" aria-selected="true"
          aria-controls="posCheckoutPane" data-pos-tab="checkout">
    <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
    <span>Checkout</span>
    <span class="pos-tab-count" id="posCheckoutCount">0</span>
  </button>
  <button type="button" class="pos-workspace-tab"
          id="posMenusTab" role="tab" aria-selected="false"
          aria-controls="posMenusPane" data-pos-tab="menus">
    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
    <span>Menus</span>
  </button>
</div>

<div class="container-fluid p-0 pos-workspace">
  <div class="row g-0 pos-workspace-row">
    <!-- Left Panel: Search, Categories & Product Grid -->
    <div id="posMenusPane" class="col-12 col-lg-7 col-xl-8 pos-catalog-panel pos-tab-pane">
      <!-- Search and Floor Zone Selector -->
      <div class="row g-2 mb-4">
        <div class="col-12 col-md-7">
          <div class="input-group pos-search-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" id="productSearchInput" class="form-control" placeholder="Search dishes, drinks, or SKU…" aria-label="Search menu items" oninput="window.posApp.filterProducts()">
          </div>
        </div>
        <div class="col-12 col-md-5">
          <div class="input-group pos-search-group">
            <span class="input-group-text"><i class="bi bi-grid-3x3-gap"></i></span>
            <select id="posTableSelect" class="form-select" aria-label="Select table or order location" onchange="window.posApp.setTable(this.value)">
              <option value="">Takeaway / Walk-in (No Table)</option>
              <?php foreach ($tables as $t): ?>
                <option value="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['name']) ?>" data-zone="<?= htmlspecialchars($t['floor_zone']) ?>">
                  Table #<?= htmlspecialchars($t['table_number']) ?> - <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['floor_zone']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <?php if (!empty($customers)): ?>
      <div class="row g-2 mb-3">
        <div class="col-12 col-md-7">
          <label class="form-label small fw-semibold text-muted mb-1" for="posCustomerSelect">Customer (optional)</label>
          <select id="posCustomerSelect" class="form-select" onchange="window.posApp.setCustomer(this.value)">
            <option value="">Walk-in customer</option>
            <?php foreach ($customers as $customer): ?>
              <option value="<?= (int)$customer['id'] ?>"><?= htmlspecialchars((string)$customer['full_name'], ENT_QUOTES, 'UTF-8') ?><?= !empty($customer['phone']) ? ' · ' . htmlspecialchars((string)$customer['phone'], ENT_QUOTES, 'UTF-8') : '' ?> · <?= (int)$customer['loyalty_points'] ?> pts</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label small fw-semibold text-muted mb-1" for="posLoyaltyRedeem">Redeem loyalty points</label>
          <select id="posLoyaltyRedeem" class="form-select" onchange="window.posApp.setLoyaltyRedeem(this.value)" disabled>
            <option value="0">Select a customer first</option>
          </select>
          <div class="form-text">100 points = ₱5 off paid sales.</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Category Filter Pills -->
      <div class="d-flex gap-2 overflow-x-auto pb-2 mb-3" id="categoryPillsContainer">
        <button class="category-pill active" onclick="window.posApp.filterCategory(null, this)">All Items</button>
        <?php foreach ($catalog['categories'] as $cat): ?>
          <button class="category-pill" onclick="window.posApp.filterCategory(<?= $cat['id'] ?>, this)">
            <?= htmlspecialchars($cat['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Products Grid -->
      <div class="row g-3" id="productsGrid">
        <!-- Rendered by JavaScript for offline speed -->
      </div>
    </div>

    <!-- Right Panel: Cart / Order Ticket -->
    <div id="posCheckoutPane" class="col-12 col-lg-5 col-xl-4 pos-ticket-panel pos-tab-pane">
      <!-- Dining Option Switcher -->
      <div class="pos-order-type-switcher">
        <button id="typeDineIn" class="btn pos-order-type active" onclick="window.posApp.setOrderType('dine_in')">
          <i class="bi bi-shop me-1"></i> Dine In
        </button>
        <button id="typeTakeout" class="btn pos-order-type" onclick="window.posApp.setOrderType('takeout')">
          <i class="bi bi-bag me-1"></i> Takeout
        </button>
        <button id="typeDelivery" class="btn pos-order-type" onclick="window.posApp.setOrderType('delivery')">
          <i class="bi bi-bicycle me-1"></i> Delivery
        </button>
      </div>

      <!-- Cart stage keeps the quick-action speed dial inside the grey ticket area. -->
      <div class="pos-cart-stage">
      <!-- Ticket Items List -->
      <div class="ticket-items p-3" id="ticketItemsList">
        <div class="pos-empty-cart" id="emptyCartNotice">
          <span class="pos-empty-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span>
          <h5>Your ticket is empty</h5>
          <p>Choose a product from the menu to start a new order.</p>
        </div>
      </div>

        <!-- Mobile-only floating quick actions, anchored immediately above order totals. -->
        <div class="pos-floating-nav no-print" id="posFloatingNav">
          <div class="pos-floating-nav-list" id="posFloatingNavList" aria-label="POS quick actions">
            <a class="pos-floating-nav-item pos-quick-orders" href="<?= BASE_URL ?>/bills">
              <span class="pos-quick-label">Orders</span><span class="pos-quick-icon"><i class="bi bi-list-check"></i></span>
            </a>
            <button class="pos-floating-nav-item pos-quick-pending" type="button" id="posFloatingSync">
              <span class="pos-quick-label">Pending</span><span class="pos-quick-icon"><i class="bi bi-hourglass-split"></i><span class="pos-quick-badge" id="posFloatingPendingCount">0</span></span>
            </button>
            <button class="pos-floating-nav-item pos-quick-printer" type="button" id="posFloatingPrint">
              <span class="pos-quick-label">Printer</span><span class="pos-quick-icon"><i class="bi bi-printer-fill"></i></span>
            </button>
          </div>
          <button class="pos-floating-nav-trigger" type="button" id="posFloatingNavToggle"
                  aria-label="Open quick actions" aria-controls="posFloatingNavList" aria-expanded="false">
            <i class="bi bi-three-dots" aria-hidden="true"></i>
          </button>
        </div>
      </div><!-- /.pos-cart-stage -->

      <!-- Ticket Summary & Calculations -->
      <div class="pos-ticket-summary">
        <div class="d-flex justify-content-between text-muted small mb-2">
          <span>Subtotal</span>
          <span class="font-monospace fw-semibold text-white" id="ticketSubtotal">₱0.00</span>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-1">
          <div class="d-flex align-items-center gap-1">
            <span id="ticketDiscountLabel" class="text-muted small">Discount:</span>
            <select id="discountSelect" class="form-select form-select-sm bg-dark border-secondary text-white py-0 px-2" style="font-size: 0.75rem;" onchange="window.posApp.applyDiscount(this.value)">
              <option value="0">None (0%)</option>
              <option value="20">Senior / PWD (20%)</option>
              <option value="10">Staff (10%)</option>
            </select>
          </div>
          <span class="font-monospace text-warning small" id="ticketDiscount">-₱0.00</span>
        </div>

        <div class="d-flex justify-content-between text-muted small mb-2">
          <span>VAT (12% Included)</span>
          <span class="font-monospace text-white" id="ticketVat">₱0.00</span>
        </div>

        <div class="d-flex justify-content-between align-items-baseline pt-3 mt-3 border-top">
          <span class="fw-bold mb-0">Total due</span>
          <span class="h3 fw-bold font-monospace mb-0" id="ticketTotal">₱0.00</span>
        </div>

        <!-- Order Action Buttons -->
        <div class="d-flex gap-2 mt-3">
          <button class="btn pos-clear-ticket" aria-label="Clear current ticket" title="Clear current ticket" onclick="window.posApp.clearTicket()">
            <i class="bi bi-trash3"></i>
          </button>
          <button id="checkoutBtn" class="btn pos-checkout-btn flex-grow-1" onclick="window.posApp.openCheckoutModal()">
            <i class="bi bi-credit-card-2-front me-1"></i> Charge / Pay
          </button>
        </div>
        <button id="saveOpenOrderBtn" class="btn btn-outline-primary w-100 mt-2" type="button" disabled onclick="window.posApp.saveOpenOrder()" title="Send this dine-in ticket to the kitchen and leave the bill unpaid">
          <i class="bi bi-fire me-1"></i> Send to Kitchen / Save Open Bill
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Variant & Addon Drawer Selection -->
<div class="modal fade" id="variantModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary text-white">
      <div class="modal-header border-secondary">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="modalProductName">Product Name</h5>
          <small class="text-muted" id="modalProductBasePrice">Base: ₱0.00</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Variant Options -->
        <div id="modalVariantSection" class="mb-4">
          <label class="form-label fw-bold text-info small text-uppercase">Select Size / Variant</label>
          <div class="list-group" id="modalVariantsList"></div>
        </div>

        <!-- Add-on Options -->
        <div id="modalAddonSection" class="mb-3">
          <label class="form-label fw-bold text-warning small text-uppercase">Customize Add-ons</label>
          <div class="list-group" id="modalAddonsList"></div>
        </div>
      </div>
      <div class="modal-footer border-secondary d-flex justify-content-between">
        <span class="fw-bold fs-5 text-success font-monospace" id="modalCustomTotal">₱0.00</span>
        <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="window.posApp.confirmCustomItem()">
          Add to Ticket
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Cash Checkout & Tender Calculator -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary text-white">
      <div class="modal-header border-secondary bg-success bg-opacity-25">
        <h5 class="modal-title fw-bold text-success">
          <i class="bi bi-cash-stack me-2"></i>Cash Payment Settlement
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="text-center mb-4">
          <div class="text-white-50 text-uppercase small fw-semibold">Amount to Pay</div>
          <div class="display-5 fw-bold text-white font-monospace" id="checkoutTotalDisplay">₱0.00</div>
        </div>

        <!-- Cash Quick Tender Buttons -->
        <label class="form-label fw-semibold small text-muted text-uppercase">Quick Cash Tender (₱)</label>
        <div class="row g-2 mb-3" id="quickTenderRow">
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.setTenderExact()">Exact</button></div>
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.addTender(100)">+₱100</button></div>
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.addTender(500)">+₱500</button></div>
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.setTender(500)">₱500</button></div>
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.setTender(1000)">₱1,000</button></div>
          <div class="col-4"><button class="btn btn-outline-light w-100 fw-bold" onclick="window.posApp.setTender(2000)">₱2,000</button></div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Cash Tendered (₱)</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-dark border-secondary text-white fw-bold">₱</span>
            <input type="number" step="0.01" min="0" id="tenderInput" class="form-control form-control-lg bg-dark border-secondary text-white font-monospace fw-bold fs-3" oninput="window.posApp.computeChange()">
          </div>
        </div>

        <div class="card border-0 bg-black p-3 mb-2 rounded-3">
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted fw-bold">Change Due:</span>
            <span class="h3 fw-bold font-monospace mb-0 text-success" id="changeDisplay">₱0.00</span>
          </div>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary text-white" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmSaleBtn" class="btn btn-success btn-lg fw-bold px-4" onclick="window.posApp.processSale()">
          <i class="bi bi-check-circle-fill me-1"></i> Complete Sale & Receipt
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Thermal Receipt & Print Preview -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark">
      <div class="modal-header border-bottom no-print">
        <h5 class="modal-title fw-bold text-dark">
          <i class="bi bi-receipt text-primary me-2"></i>Customer Receipt
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="thermalReceiptPrintArea">
        <div class="text-center mb-3">
          <h4 class="fw-bold mb-0 text-uppercase" id="recRestaurantName">RESTAURANT NAME</h4>
          <div class="small text-muted" id="recRestaurantAddress">123 Street Address</div>
          <div class="small text-muted">TIN: <span id="recRestaurantTin">000-000-000-000</span></div>
          <div class="small text-muted">OFFICIAL SALES INVOICE</div>
        </div>

        <div class="border-top border-bottom border-dark py-2 small mb-3">
          <div class="d-flex justify-content-between">
            <span>Order #: <strong id="recOrderNumber">ORD-0001</strong></span>
            <span id="recOrderDate">Oct 20, 2026</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>Cashier: <span id="recCashierName">Staff</span></span>
            <span>Type: <strong id="recOrderType">Dine In</strong></span>
          </div>
          <div id="recTableLine" class="d-flex justify-content-between">
            <span>Table: <strong id="recTableName">Table 1</strong></span>
          </div>
        </div>

        <table class="table table-sm table-borderless small mb-3">
          <thead class="border-bottom border-dark">
            <tr>
              <th>Item</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Price</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody id="recItemsBody"></tbody>
        </table>

        <div class="border-top border-dark pt-2 small font-monospace">
          <div class="d-flex justify-content-between">
            <span>Subtotal:</span>
            <span id="recSubtotal">₱0.00</span>
          </div>
          <div class="d-flex justify-content-between" id="recDiscountRow">
            <span>Discount:</span>
            <span id="recDiscount">-₱0.00</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>VAT (12% Included):</span>
            <span id="recVat">₱0.00</span>
          </div>
          <div class="d-flex justify-content-between fw-bold fs-6 border-top border-dark pt-1 mt-1">
            <span>TOTAL AMOUNT:</span>
            <span id="recTotal">₱0.00</span>
          </div>
          <div class="d-flex justify-content-between mt-1">
            <span>Cash Tendered:</span>
            <span id="recTendered">₱0.00</span>
          </div>
          <div class="d-flex justify-content-between fw-bold">
            <span>Change:</span>
            <span id="recChange">₱0.00</span>
          </div>
        </div>

        <div class="text-center mt-4 small text-muted">
          <div>Thank you for dining with us!</div>
          <div>Please come again.</div>
        </div>
      </div>
      <div class="modal-footer border-top no-print">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary px-4 fw-bold" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> Print Thermal Receipt (80mm)
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Offline PBKDF2 Cashier PIN Pad -->
<div class="modal fade" id="lockModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content bg-dark border-secondary text-white text-center">
      <div class="modal-header border-secondary justify-content-center">
        <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock text-primary me-2"></i>Enter Cashier PIN</h5>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
          <input
            type="password"
            id="pinDisplay"
            class="form-control form-control-lg bg-black border-secondary text-white text-center fs-2 font-monospace tracking-widest"
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            aria-label="Cashier PIN"
            placeholder="&bull; &bull; &bull; &bull;"
          >
        </div>
        <div id="pinError" class="text-danger small mb-3" style="display: none;"></div>

        <!-- 3x4 Keypad -->
        <div class="row g-2 mb-2">
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('1')">1</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('2')">2</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('3')">3</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('4')">4</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('5')">5</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('6')">6</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('7')">7</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('8')">8</button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('9')">9</button></div>
          <div class="col-4"><button class="btn btn-outline-danger w-100 pin-btn" onclick="window.posApp.pinClear()"><i class="bi bi-backspace"></i></button></div>
          <div class="col-4"><button class="btn btn-secondary w-100 pin-btn" onclick="window.posApp.pinKey('0')">0</button></div>
          <div class="col-4"><button class="btn btn-primary w-100 pin-btn" onclick="window.posApp.pinSubmit()"><i class="bi bi-check-lg"></i></button></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Load Client IndexedDB Database Script -->
<script src="<?= BASE_URL ?>/public/assets/js/pos-db.js"></script>

<!-- Embedded Client POS Application Logic -->
<script>
(function() {
  const initialCatalog = <?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const initialTables = <?= json_encode($tables, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const initialCustomers = <?= json_encode($customers ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const tenantId = <?= (int)($user['restaurant_id'] ?? 1) ?>;
  const deviceId = <?= (int)($activeDevice['id'] ?? 1) ?>;
  const cashierId = <?= (int)($user['id'] ?? 1) ?>;
  const shiftId = <?= $activeShift ? (int)$activeShift['id'] : 'null' ?>;

  // generateClientUuid() is not available in every non-secure LAN context (HTTP
  // on a tablet). getRandomValues() still works there and keeps the UUID stable
  // for the queued order/payment retries.
  function generateClientUuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    const bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' +
      hex.slice(16, 20) + '-' + hex.slice(20);
  }

  const app = {
    catalog: initialCatalog,
    tables: initialTables,
    customers: initialCustomers,
    selectedCustomerId: null,
    loyaltyRedeemPoints: 0,
    currentCategory: null,
    searchQuery: '',
    currentTableId: null,
    orderType: 'dine_in',
    ticket: [],
    discountPercent: 0,
    activeItemForModal: null,
    failedPinAttempts: 0,
    lockoutUntil: 0,
    isProcessingSale: false,
    isSyncingQueue: false,
    offlineLease: null,
    restoredDraftTicket: false,

    setCustomer(value) {
      const id = Number.parseInt(value, 10);
      this.selectedCustomerId = Number.isInteger(id) && id > 0 ? id : null;
      this.loyaltyRedeemPoints = 0;
      this.renderLoyaltyOptions();
      this.renderTicket();
    },

    setLoyaltyRedeem(value) {
      const points = Number.parseInt(value, 10);
      this.loyaltyRedeemPoints = Number.isInteger(points) && points > 0 ? Math.floor(points / 100) * 100 : 0;
      this.renderTicket();
    },

    renderLoyaltyOptions() {
      const select = document.getElementById('posLoyaltyRedeem');
      if (!select) return;
      const customer = this.customers.find(c => Number(c.id) === Number(this.selectedCustomerId));
      const subtotal = this.ticket.reduce((sum, item) => sum + (Number(item.unit_price) * Number(item.quantity)), 0);
      const baseDiscount = subtotal * (Number(this.discountPercent) / 100);
      const maxAffordable = Math.floor(Math.max(0, subtotal - baseDiscount) / 5) * 100;
      const available = customer ? Math.max(0, Number(customer.loyalty_points) || 0) : 0;
      const maxPoints = Math.min(Math.floor(available / 100) * 100, maxAffordable);
      if (this.loyaltyRedeemPoints > maxPoints) this.loyaltyRedeemPoints = 0;
      select.disabled = !customer || maxPoints < 100;
      let options = '<option value="0">Do not redeem points</option>';
      for (let points = 100; points <= maxPoints; points += 100) {
        options += `<option value="${points}" ${points === this.loyaltyRedeemPoints ? 'selected' : ''}>Redeem ${points} pts (₱${(points / 100 * 5).toFixed(2)})</option>`;
      }
      select.innerHTML = options;
      select.value = String(this.loyaltyRedeemPoints);
    },

    async init() {
      // 1. Init IndexedDB
      await window.PosDB.init(tenantId, deviceId);

      // Use delegated click handling for ticket controls because ticket markup
      // is rebuilt with innerHTML after every cart change.
      const ticketContainer = document.getElementById('ticketItemsList');
      ticketContainer.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-ticket-action]');
        if (!button || !ticketContainer.contains(button)) return;

        const index = Number.parseInt(button.dataset.index, 10);
        if (!Number.isInteger(index) || index < 0) return;

        switch (button.dataset.ticketAction) {
          case 'increase':
            this.updateQty(index, 1);
            break;
          case 'decrease':
            this.updateQty(index, -1);
            break;
          case 'remove':
            this.removeItem(index);
            break;
        }
      });

      // 2. Restore the last server-issued catalog snapshot when reopening offline.
      // Never overwrite a valid cached database catalog ID with the display-only timestamp
      // embedded in the server-rendered page.
      let cachedCatalog = null;
      try { cachedCatalog = await window.PosDB.getCatalog(); } catch (error) {
        console.warn('[POS Catalog] Unable to read cached catalog metadata:', error);
      }
      const hasCachedServerVersion = cachedCatalog && cachedCatalog.version && Number(cachedCatalog.version.id) > 0;
      if (!navigator.onLine && hasCachedServerVersion && Array.isArray(cachedCatalog.products) && cachedCatalog.products.length > 0) {
        this.catalog.products = cachedCatalog.products;
        this.catalog.categories = cachedCatalog.categories || [];
        this.catalog.version = cachedCatalog.version;
      }
      if (this.catalog && this.catalog.products && this.catalog.version && Number(this.catalog.version.id) > 0) {
        await window.PosDB.saveCatalog(this.catalog.products, this.catalog.categories, this.catalog.version);
      }
      if (this.tables) {
        await window.PosDB.saveTables(this.tables);
      }

      // Restore an unfinished cart after an accidental refresh/reopen.
      await this.restoreDraftTicket();

      // Restore the last cached offline authorization lease, then refresh staff
      // credentials from the authenticated bootstrap API whenever we're online.
      this.offlineLease = await window.PosDB.getLease();
      if (navigator.onLine) {
        await this.refreshOfflineAuthorizationCache();
      }

      // 3. Register Service Worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js', { scope: '<?= BASE_URL ?>/' }).catch((error) => {
          console.error('[ServiceWorker] Registration failed:', error);
        });
      }

      // 4. Online/Offline Listeners
      window.addEventListener('online', () => {
        this.updateNetworkStatus(true);
        // Refresh authorized offline credentials first, then resume queued orders.
        void this.refreshOfflineAuthorizationCache().finally(() => this.syncQueue());
      });
      window.addEventListener('offline', () => this.updateNetworkStatus(false));

      // Recover queued orders after a transient connection/server failure even
      // when the browser does not emit another "online" event.
      window.setInterval(() => {
        if (navigator.onLine && !document.hidden && !this.isSyncingQueue) {
          void this.syncQueue();
        }
      }, 30000);

      document.addEventListener('visibilitychange', () => {
        if (!document.hidden && navigator.onLine && !this.isSyncingQueue) {
          void this.syncQueue();
        }
      });

      // Don't allow common keyboard reload shortcuts while offline. A browser's
      // toolbar/menu reload cannot be disabled by a web page, so use the native
      // beforeunload confirmation for that path as an additional safeguard.
      window.addEventListener('keydown', (event) => {
        if (navigator.onLine) return;
        const key = String(event.key || '').toLowerCase();
        const isReloadShortcut = key === 'f5' ||
          ((event.ctrlKey || event.metaKey) && key === 'r');
        if (!isReloadShortcut) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        const notice = document.getElementById('posOfflineGuardNotice');
        if (notice) {
          notice.className = 'alert alert-warning py-2 px-3 mx-3 mt-2 mb-0';
          notice.textContent = 'A reload shortcut was detected. Chrome may still reload the page; the unfinished ticket is saved locally and will be restored when the POS opens again.';
        }
      }, true);

      window.addEventListener('beforeunload', (event) => {
        if (navigator.onLine) return;
        event.preventDefault();
        event.returnValue = '';
      });

      const backToSaaS = document.getElementById('posBackToSaaS');
      if (backToSaaS) {
        backToSaaS.addEventListener('click', (event) => {
          if (navigator.onLine) return;
          event.preventDefault();
          const notice = document.getElementById('posOfflineGuardNotice');
          if (notice) {
            notice.className = 'alert alert-warning py-2 px-3 mx-3 mt-2 mb-0';
            notice.textContent = 'Back to SaaS is unavailable while offline. Reconnect before leaving the POS terminal.';
          }
        });
      }

      this.updateNetworkStatus(navigator.onLine);

      // 5. Update Unsynced Badge
      await this.refreshQueueCount();

      // 6. Render Products
      this.renderProducts();

      // 7. Auto sync if online
      if (navigator.onLine) {
        this.syncQueue();
      }
    },

    updateNetworkStatus(isOnline) {
      const el = document.getElementById('posNetworkStatus');
      const text = document.getElementById('posNetworkText');
      if (isOnline) {
        el.className = 'pos-network-status online';
        text.textContent = 'Online';
      } else {
        el.className = 'pos-network-status offline';
        text.textContent = 'Offline (PWA)';
      }

      // Hide Back to SaaS unless the server can be reached.
      const backToSaaS = document.getElementById('posBackToSaaS');
      if (backToSaaS) {
        backToSaaS.classList.toggle('d-none', !isOnline);
        backToSaaS.setAttribute('aria-hidden', isOnline ? 'false' : 'true');
        if (isOnline) {
          backToSaaS.removeAttribute('tabindex');
        } else {
          backToSaaS.setAttribute('tabindex', '-1');
        }
      }

      // Browsers can reload a page without delivering a cancellable keyboard
      // event, so the reliable protection is saving/restoring the current cart.
      const guardNotice = document.getElementById('posOfflineGuardNotice');
      if (guardNotice) {
        if (this.restoredDraftTicket) {
          guardNotice.className = 'alert alert-info py-2 px-3 mx-3 mt-2 mb-0';
          guardNotice.textContent = 'An unfinished ticket was restored from this device. Review the items and total before continuing.';
        } else if (!isOnline) {
          guardNotice.className = 'alert alert-warning py-2 px-3 mx-3 mt-2 mb-0';
          guardNotice.textContent = 'Offline mode: your unfinished ticket is saved on this device. Chrome may still reload the page; if it does, the POS will restore the saved ticket when it opens again.';
        } else {
          guardNotice.className = 'alert alert-warning py-2 px-3 mx-3 mt-2 mb-0 d-none';
          guardNotice.textContent = '';
        }
      }
    },

    async refreshOfflineAuthorizationCache() {
      if (!navigator.onLine) return false;

      try {
        const response = await fetch(
          '<?= BASE_URL ?>/api/pos/bootstrap?device_id=' + encodeURIComponent(deviceId),
          {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
          }
        );
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || !Array.isArray(payload.staff)) {
          const detail = payload && payload.error ? String(payload.error) : 'Bootstrap request failed.';
          throw new Error(detail);
        }

        await window.PosDB.saveStaff(payload.staff.filter(staff =>
          staff &&
          Number(staff.id) > 0 &&
          typeof staff.pin_salt === 'string' &&
          typeof staff.pin_hash === 'string' &&
          staff.pin_salt.length > 0 &&
          staff.pin_hash.length === 64
        ));

        if (payload.lease && payload.lease.lease_id) {
          await window.PosDB.saveLease(payload.lease);
          this.offlineLease = payload.lease;
        }

        // Bootstrap publishes a tenant-scoped catalog snapshot. Replace cached
        // product metadata here too so newly uploaded product photos become
        // available in the live terminal and in IndexedDB for offline use.
        if (payload.catalog && Array.isArray(payload.catalog.products)) {
          this.catalog.products = payload.catalog.products;
          if (Array.isArray(payload.catalog.categories)) {
            this.catalog.categories = payload.catalog.categories;
          }
          if (payload.catalog.restaurant && typeof payload.catalog.restaurant === 'object') {
            this.catalog.restaurant = payload.catalog.restaurant;
          }
          this.renderProducts();
        }

        if (payload.catalog_version && Number(payload.catalog_version.id) > 0) {
          this.catalog.version = {
            ...(this.catalog.version || {}),
            ...payload.catalog_version
          };
          await window.PosDB.saveCatalog(
            this.catalog.products || [],
            this.catalog.categories || [],
            this.catalog.version
          );
        }

        console.info('[POS Offline Auth] Cached ' + payload.staff.length + ' authorized staff credential(s).');
        return true;
      } catch (error) {
        console.warn('[POS Offline Auth] Unable to refresh offline authorization cache:', error);
        return false;
      }
    },

    async persistDraftTicket() {
      try {
        const items = Array.isArray(this.ticket) ? this.ticket : [];
        if (items.length === 0) {
          await window.PosDB.saveDraftTicket(null);
          return;
        }

        const draft = {
          ticket: items.map(item => ({
            product_id: Number(item.product_id),
            name: String(item.name || 'Menu item'),
            variant_id: item.variant_id ? Number(item.variant_id) : null,
            variant_name: item.variant_name ? String(item.variant_name) : null,
            unit_price: Number(item.unit_price),
            quantity: Number(item.quantity),
            addons: Array.isArray(item.addons) ? item.addons.map(addon => ({
              id: Number(addon.id) || null,
              name: String(addon.name || ''),
              price: Number(addon.price) || 0
            })) : []
          })),
          order_type: this.orderType,
          table_id: this.currentTableId,
          discount_percent: this.discountPercent,
          saved_at: new Date().toISOString()
        };
        await window.PosDB.saveDraftTicket(draft);
      } catch (error) {
        console.warn('[POS Draft] Unable to save the in-progress ticket:', error);
      }
    },

    async restoreDraftTicket() {
      try {
        const draft = await window.PosDB.getDraftTicket();
        if (!draft || !Array.isArray(draft.ticket) || draft.ticket.length === 0) return false;

        const restoredItems = draft.ticket.slice(0, 100).filter(item => {
          return item &&
            Number.isInteger(Number(item.product_id)) &&
            Number(item.product_id) > 0 &&
            typeof item.name === 'string' &&
            Number.isFinite(Number(item.unit_price)) &&
            Number(item.unit_price) >= 0 &&
            Number.isInteger(Number(item.quantity)) &&
            Number(item.quantity) > 0;
        }).map(item => ({
          product_id: Number(item.product_id),
          name: item.name,
          variant_id: item.variant_id ? Number(item.variant_id) : null,
          variant_name: item.variant_name ? String(item.variant_name) : null,
          unit_price: Number(item.unit_price),
          quantity: Number(item.quantity),
          addons: Array.isArray(item.addons) ? item.addons.map(addon => ({
            id: Number(addon.id) || null,
            name: String(addon.name || ''),
            price: Number(addon.price) || 0
          })) : []
        }));

        if (restoredItems.length === 0) {
          await window.PosDB.saveDraftTicket(null);
          return false;
        }

        this.ticket = restoredItems;
        this.orderType = ['dine_in', 'takeout', 'delivery'].includes(draft.order_type)
          ? draft.order_type
          : 'dine_in';

        const discount = Number(draft.discount_percent);
        this.discountPercent = [0, 5, 10, 20].includes(discount) ? discount : 0;

        const tableSelect = document.getElementById('posTableSelect');
        const requestedTable = draft.table_id ? String(draft.table_id) : '';
        const validTable = requestedTable && Array.from(tableSelect.options).some(option => option.value === requestedTable);
        this.currentTableId = validTable ? Number(requestedTable) : null;
        tableSelect.value = validTable ? requestedTable : '';

        const discountSelect = document.getElementById('discountSelect');
        if (discountSelect) discountSelect.value = String(this.discountPercent);

        this.setOrderType(this.orderType);
        this.restoredDraftTicket = true;
        this.renderTicket();
        console.info('[POS Draft] Restored unfinished ticket after page reload.');
        return true;
      } catch (error) {
        console.warn('[POS Draft] Unable to restore the in-progress ticket:', error);
        return false;
      }
    },

    async refreshQueueCount() {
      const pending = await window.PosDB.getPendingOrders();
      const count = document.getElementById('unsyncedCount');
      if (count) count.textContent = pending.length;
      const syncButton = document.getElementById('posSyncBtn');
      if (syncButton) syncButton.classList.toggle('has-pending', pending.length > 0);
    },

    showSyncNotice(message, variant = 'warning') {
      const notice = document.getElementById('posSyncNotice');
      if (!notice) return;
      notice.className = `alert alert-${variant} py-2 px-3 mx-3 mt-2 mb-0`;
      notice.textContent = message;
    },

    clearSyncNotice() {
      const notice = document.getElementById('posSyncNotice');
      if (!notice) return;
      notice.className = 'alert alert-warning py-2 px-3 mx-3 mt-2 mb-0 d-none';
      notice.textContent = '';
    },

    updateOpenOrderButton() {
      const button = document.getElementById('saveOpenOrderBtn');
      if (!button) return;

      const dineIn = this.orderType === 'dine_in';
      const hasItems = Array.isArray(this.ticket) && this.ticket.length > 0;
      button.classList.toggle('d-none', !dineIn);
      // Keep the action clickable for a non-empty Dine In ticket even if the
      // cashier has not chosen a table yet; saveOpenOrder() then explains what
      // is missing instead of leaving a permanently disabled button.
      button.disabled = !dineIn || !hasItems || this.isProcessingSale;
      button.title = !dineIn
        ? 'Available for dine-in orders only'
        : !hasItems
          ? 'Add at least one item to the ticket first'
          : !this.currentTableId
            ? 'Select a dining table before saving this open bill'
            : 'Send this dine-in ticket to the kitchen and leave the bill unpaid';
    },

    setOrderType(type) {
      this.orderType = type;
      this.updateOpenOrderButton();
      ['dine_in', 'takeout', 'delivery'].forEach(t => {
        const btn = document.getElementById(t === 'dine_in' ? 'typeDineIn' : (t === 'takeout' ? 'typeTakeout' : 'typeDelivery'));
        if (t === type) {
          btn.className = 'btn pos-order-type active';
        } else {
          btn.className = 'btn pos-order-type';
        }
      });
      void this.persistDraftTicket();
    },

    setTable(tableId) {
      const parsedTableId = tableId ? Number.parseInt(tableId, 10) : null;
      this.currentTableId = Number.isInteger(parsedTableId) && parsedTableId > 0 ? parsedTableId : null;
      this.updateOpenOrderButton();
      void this.persistDraftTicket();
    },

    filterCategory(catId, btn) {
      this.currentCategory = catId;
      document.querySelectorAll('.category-pill').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      this.renderProducts();
    },

    filterProducts() {
      this.searchQuery = document.getElementById('productSearchInput').value.toLowerCase().trim();
      this.renderProducts();
    },

    renderProducts() {
      const container = document.getElementById('productsGrid');
      const products = this.catalog.products || [];

      const filtered = products.filter(p => {
        const matchesCat = this.currentCategory === null || p.category_id == this.currentCategory;
        const matchesSearch = !this.searchQuery || 
          p.name.toLowerCase().includes(this.searchQuery) || 
          (p.sku && p.sku.toLowerCase().includes(this.searchQuery));
        return matchesCat && matchesSearch;
      });

      if (filtered.length === 0) {
        container.innerHTML = `
          <div class="col-12 text-center text-muted py-5">
            <i class="bi bi-search fs-2 mb-2 d-block"></i>
            No matching items found.
          </div>
        `;
        return;
      }

      container.innerHTML = filtered.map(p => {
        const hasVariants = p.variants && p.variants.length > 0;
        const hasAddons = p.addon_groups && p.addon_groups.length > 0;
        const isCombo = Boolean(p.is_combo);
        const price = parseFloat(p.scheduled_base_price ?? p.base_price ?? 0);
        const imageUrl = typeof p.image_url === 'string' ? p.image_url : '';

        return `
          <div class="col-6 col-sm-4 col-md-4 col-xl-3">
            <div class="product-card h-100 d-flex flex-column justify-content-between" onclick="window.posApp.onProductClick(${p.id})">
              <div>
                ${imageUrl
                  ? `<div class="product-image-wrap"><i class="bi bi-image" aria-hidden="true"></i><img src="${imageUrl}" alt="" loading="eager" decoding="async" onerror="this.remove(); this.parentElement.classList.add('is-fallback')"></div>`
                  : `<div class="product-image-wrap is-fallback"><i class="bi bi-image" aria-hidden="true"></i></div>`}
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                  <span class="product-category-tag">${p.category_name || 'Menu item'}</span>
                  ${isCombo ? '<span class="product-custom-tag badge">Combo</span>' : (hasVariants || hasAddons ? '<span class="product-custom-tag badge">Custom</span>' : '')}
                </div>
                <h6 class="product-title fw-bold mb-0">${p.name}</h6>
                ${isCombo ? `<div class="small ${p.combo_valid ? 'text-muted' : 'text-danger'} mt-1">${p.combo_valid ? p.combo_components.map(c => `${c.name} × ${c.quantity}`).join(' · ') : 'Combo needs at least two active components'}</div>` : ''}
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <span class="product-price">₱${price.toFixed(2)}</span>
                <span class="product-add-button"><i class="bi bi-plus-lg"></i> Add</span>
              </div>
            </div>
          </div>
        `;
      }).join('');
    },

    onProductClick(productId) {
      const p = (this.catalog.products || []).find(item => item.id == productId);
      if (!p) return;
      if (p.is_combo && !p.combo_valid) {
        alert('This combo is incomplete because one or more component items are inactive. Update the combo definition before selling it.');
        return;
      }

      const hasVariants = p.variants && p.variants.length > 0;
      const hasAddons = p.addon_groups && p.addon_groups.length > 0;

      if (!hasVariants && !hasAddons) {
        // Direct addition
        this.addToTicket({
          product_id: p.id,
          name: p.name,
          variant_id: null,
          variant_name: null,
          unit_price: parseFloat(p.scheduled_base_price ?? p.base_price),
          addons: [],
          combo_components: Array.isArray(p.combo_components) ? p.combo_components : []
        });
      } else {
        // Open drawer modal
        this.openCustomModal(p);
      }
    },

    openCustomModal(product) {
      this.activeItemForModal = product;
      document.getElementById('modalProductName').textContent = product.name;
      document.getElementById('modalProductBasePrice').textContent = `Base: ₱${parseFloat(product.scheduled_base_price ?? product.base_price).toFixed(2)}`;

      // Variants
      const vSec = document.getElementById('modalVariantSection');
      const vList = document.getElementById('modalVariantsList');
      if (product.variants && product.variants.length > 0) {
        vSec.style.display = 'block';
        vList.innerHTML = product.variants.map((v, i) => `
          <label class="list-group-item list-group-item-dark d-flex justify-content-between align-items-center">
            <div>
              <input class="form-check-input me-2" type="radio" name="modal_variant" value="${v.id}" data-name="${v.name}" data-price="${v.additional_price}" ${i === 0 ? 'checked' : ''} onchange="window.posApp.calcModalTotal()">
              <span>${v.name}</span>
            </div>
            <span class="font-monospace text-muted">+₱${parseFloat(v.additional_price).toFixed(2)}</span>
          </label>
        `).join('');
      } else {
        vSec.style.display = 'none';
        vList.innerHTML = '';
      }

      // Addons
      const aSec = document.getElementById('modalAddonSection');
      const aList = document.getElementById('modalAddonsList');
      if (product.addon_groups && product.addon_groups.length > 0) {
        aSec.style.display = 'block';
        let addonHtml = '';
        product.addon_groups.forEach(g => {
          // MenuService exposes each group's selectable entries as "options".
          // Keep "addons" as a fallback for older cached catalog data.
          (g.options || g.addons || []).forEach(a => {
            addonHtml += `
              <label class="list-group-item list-group-item-dark d-flex justify-content-between align-items-center">
                <div>
                  <input class="form-check-input me-2" type="checkbox" name="modal_addons" value="${a.id}" data-name="${a.name}" data-price="${a.price}" onchange="window.posApp.calcModalTotal()">
                  <span>${a.name}</span>
                </div>
                <span class="font-monospace text-warning">+₱${parseFloat(a.price).toFixed(2)}</span>
              </label>
            `;
          });
        });
        aList.innerHTML = addonHtml;
      } else {
        aSec.style.display = 'none';
        aList.innerHTML = '';
      }

      this.calcModalTotal();
      new bootstrap.Modal(document.getElementById('variantModal')).show();
    },

    calcModalTotal() {
      if (!this.activeItemForModal) return;
      let total = parseFloat(this.activeItemForModal.scheduled_base_price ?? this.activeItemForModal.base_price ?? 0);

      const selVar = document.querySelector('input[name="modal_variant"]:checked');
      if (selVar) {
        total += parseFloat(selVar.getAttribute('data-price') || 0);
      }

      const selAddons = document.querySelectorAll('input[name="modal_addons"]:checked');
      selAddons.forEach(a => {
        total += parseFloat(a.getAttribute('data-price') || 0);
      });

      document.getElementById('modalCustomTotal').textContent = `₱${total.toFixed(2)}`;
      return total;
    },

    confirmCustomItem() {
      if (!this.activeItemForModal) return;
      const p = this.activeItemForModal;

      const selVar = document.querySelector('input[name="modal_variant"]:checked');
      const variantId = selVar ? parseInt(selVar.value, 10) : null;
      const variantName = selVar ? selVar.getAttribute('data-name') : null;
      const varPrice = selVar ? parseFloat(selVar.getAttribute('data-price') || 0) : 0;

      const addons = [];
      document.querySelectorAll('input[name="modal_addons"]:checked').forEach(a => {
        addons.push({
          id: parseInt(a.value, 10),
          name: a.getAttribute('data-name'),
          price: parseFloat(a.getAttribute('data-price') || 0)
        });
      });

      const unitPrice = parseFloat(p.scheduled_base_price ?? p.base_price) + varPrice + addons.reduce((sum, item) => sum + item.price, 0);

      this.addToTicket({
        product_id: p.id,
        name: p.name,
        variant_id: variantId,
        variant_name: variantName,
        unit_price: unitPrice,
        addons: addons,
        combo_components: Array.isArray(p.combo_components) ? p.combo_components : []
      });

      bootstrap.Modal.getInstance(document.getElementById('variantModal')).hide();
    },

    addToTicket(item) {
      // Check if identical item already in ticket
      const existing = this.ticket.find(t => 
        t.product_id === item.product_id && 
        t.variant_id === item.variant_id && 
        JSON.stringify(t.addons) === JSON.stringify(item.addons)
      );

      if (existing) {
        existing.quantity += 1;
      } else {
        this.ticket.push({
          ...item,
          quantity: 1
        });
      }

      this.renderTicket();
    },

    updateQty(index, delta) {
      const item = this.ticket[index];
      if (!item) return;

      const currentQuantity = Number(item.quantity);
      const increment = Number(delta);
      if (!Number.isFinite(currentQuantity) || !Number.isFinite(increment)) return;

      item.quantity = currentQuantity + increment;
      if (item.quantity <= 0) {
        this.ticket.splice(index, 1);
      }
      this.renderTicket();
    },

    removeItem(index) {
      this.ticket.splice(index, 1);
      this.renderTicket();
    },

    clearTicket() {
      if (this.ticket.length === 0) return;
      if (confirm('Clear all items from this order ticket?')) {
        this.ticket = [];
        this.renderTicket();
      }
    },

    applyDiscount(val) {
      this.discountPercent = parseFloat(val) || 0;
      this.renderTicket();
    },

    getCalculations() {
      const subtotal = this.ticket.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
      const baseDiscount = subtotal * (this.discountPercent / 100);
      const loyaltyDiscount = Math.floor(this.loyaltyRedeemPoints / 100) * 5;
      const discount = Math.min(subtotal, baseDiscount + loyaltyDiscount);
      const afterDiscount = subtotal - discount;
      const vat = afterDiscount - (afterDiscount / 1.12); // Included 12% VAT
      const total = afterDiscount;

      return {
        subtotal: round(subtotal),
        discount: round(discount),
        loyaltyRedeemPoints: this.loyaltyRedeemPoints,
        loyaltyDiscount: round(loyaltyDiscount),
        vat: round(vat),
        total: round(total)
      };
    },

    renderTicket() {
      const container = document.getElementById('ticketItemsList');
      if (!container) return;

      this.renderLoyaltyOptions();
      // Save every cart edit, including removals, before a possible page reload.
      void this.persistDraftTicket();

      if (this.ticket.length === 0) {
        this.restoredDraftTicket = false;
        // Recreate the placeholder each time. The prior placeholder is removed
        // when the cart is rendered, so an old DOM reference must not be reused.
        container.innerHTML = `
          <div class="pos-empty-cart" id="emptyCartNotice">
            <span class="pos-empty-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span>
            <h5>Your ticket is empty</h5>
            <p>Choose a product from the menu to start a new order.</p>
          </div>
        `;
        document.getElementById('ticketSubtotal').textContent = '₱0.00';
        document.getElementById('ticketDiscount').textContent = '-₱0.00';
        const emptyDiscountLabel = document.getElementById('ticketDiscountLabel');
        if (emptyDiscountLabel) emptyDiscountLabel.textContent = 'Discount:';
        document.getElementById('ticketVat').textContent = '₱0.00';
        document.getElementById('ticketTotal').textContent = '₱0.00';
        document.getElementById('checkoutBtn').disabled = true;
        this.updateOpenOrderButton();
        return;
      }

      const calcs = this.getCalculations();

      container.innerHTML = this.ticket.map((item, index) => {
        const itemTotal = item.unit_price * item.quantity;
        const addonsText = item.addons.map(a => `+ ${a.name}`).join(', ');
        const comboText = Array.isArray(item.combo_components) && item.combo_components.length
          ? item.combo_components.map(c => `${c.name} × ${c.quantity}`).join(', ')
          : '';

        return `
          <div class="ticket-item-card">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div class="min-w-0">
                <strong class="ticket-item-name d-block">${item.name}</strong>
                ${item.variant_name ? `<span class="badge bg-light text-secondary border mt-1">${item.variant_name}</span>` : ''}
                ${addonsText ? `<div class="ticket-addon-text mt-1">${addonsText}</div>` : ''}
                ${comboText ? `<div class="small text-primary mt-1">Combo: ${comboText}</div>` : ''}
                <div class="ticket-item-meta font-monospace mt-2">₱${item.unit_price.toFixed(2)} each</div>
              </div>
              <span class="ticket-item-price font-monospace">₱${itemTotal.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center ticket-item-controls">
              <button type="button" class="btn btn-link ticket-remove-btn p-0 text-decoration-none" data-ticket-action="remove" data-index="${index}">
                <i class="bi bi-trash3 me-1"></i>Remove
              </button>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn ticket-qty-btn" data-ticket-action="decrease" data-index="${index}" aria-label="Decrease quantity">−</button>
                <span class="ticket-qty fw-bold font-monospace">${item.quantity}</span>
                <button type="button" class="btn ticket-qty-btn" data-ticket-action="increase" data-index="${index}" aria-label="Increase quantity">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      document.getElementById('ticketSubtotal').textContent = `₱${calcs.subtotal.toFixed(2)}`;
      document.getElementById('ticketDiscount').textContent = `-₱${calcs.discount.toFixed(2)}`;
      const discountLabel = document.getElementById('ticketDiscountLabel');
      if (discountLabel) discountLabel.textContent = calcs.loyaltyRedeemPoints > 0
        ? `Discount + ${calcs.loyaltyRedeemPoints} loyalty pts`
        : 'Discount:';
      document.getElementById('ticketVat').textContent = `₱${calcs.vat.toFixed(2)}`;
      document.getElementById('ticketTotal').textContent = `₱${calcs.total.toFixed(2)}`;
      document.getElementById('checkoutBtn').disabled = false;
      this.updateOpenOrderButton();
    },

    async openCheckoutModal() {
      if (this.ticket.length === 0 || this.isProcessingSale) return;

      // If the browser is online but has unresolved orders, don't open a new
      // checkout. Try syncing the existing order(s), then require the cashier
      // to verify them before starting another sale.
      if (navigator.onLine) {
        const pendingBefore = await window.PosDB.getPendingOrders();
        if (pendingBefore.length > 0) {
          await this.syncQueue();
          const pendingAfter = await window.PosDB.getPendingOrders();
          alert(
            `The terminal had ${pendingBefore.length} pending order(s), so checkout was paused to prevent duplicate sales. ` +
            `Sync was attempted; ${pendingAfter.length} order(s) remain pending. Check the Orders page and Sync badge before starting another sale.`
          );
          return;
        }
      }

      const calcs = this.getCalculations();
      document.getElementById('checkoutTotalDisplay').textContent = `₱${calcs.total.toFixed(2)}`;
      document.getElementById('tenderInput').value = calcs.total.toFixed(2);
      this.computeChange();
      new bootstrap.Modal(document.getElementById('checkoutModal')).show();
    },

    setTenderExact() {
      const calcs = this.getCalculations();
      document.getElementById('tenderInput').value = calcs.total.toFixed(2);
      this.computeChange();
    },

    setTender(amount) {
      document.getElementById('tenderInput').value = parseFloat(amount).toFixed(2);
      this.computeChange();
    },

    addTender(extra) {
      const current = parseFloat(document.getElementById('tenderInput').value) || 0;
      document.getElementById('tenderInput').value = (current + extra).toFixed(2);
      this.computeChange();
    },

    computeChange() {
      const calcs = this.getCalculations();
      const tendered = parseFloat(document.getElementById('tenderInput').value) || 0;
      const change = tendered - calcs.total;
      const display = document.getElementById('changeDisplay');
      const btn = document.getElementById('confirmSaleBtn');

      if (change < 0) {
        display.className = 'h3 fw-bold font-monospace mb-0 text-danger';
        display.textContent = `Short: ₱${Math.abs(change).toFixed(2)}`;
        btn.disabled = true;
      } else {
        display.className = 'h3 fw-bold font-monospace mb-0 text-success';
        display.textContent = `₱${change.toFixed(2)}`;
        btn.disabled = false;
      }
    },

    async saveOpenOrder() {
      if (this.isProcessingSale || !Array.isArray(this.ticket) || this.ticket.length === 0) return;
      if (!this.catalog.version || Number(this.catalog.version.id) <= 0) {
        alert('This terminal has no verified server catalog snapshot. Reconnect and refresh the POS before saving an order.');
        return;
      }

      if (this.orderType !== 'dine_in' || !this.currentTableId) {
        alert('Select a dining table and choose Dine In before saving an open bill.');
        return;
      }
      if (this.loyaltyRedeemPoints > 0) {
        alert('Loyalty points can only be redeemed on an immediately paid sale. Set redemption to zero before saving an open bill.');
        return;
      }
      if (!confirm('Send this order to the kitchen without collecting payment now? It will appear in Open Bills for partial or split payment.')) {
        return;
      }

      this.isProcessingSale = true;
      const button = document.getElementById('saveOpenOrderBtn');
      const originalHtml = button ? button.innerHTML : '';
      if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving Open Bill...';
      }

      let queued = false;
      try {
        const calcs = this.getCalculations();
        const clientUuid = generateClientUuid();
        const orderNumber = `ORD-${Date.now().toString().slice(-6)}-${Math.floor(Math.random() * 1000)}`;
        const orderPayload = {
          client_uuid: clientUuid,
          device_id: deviceId,
          shift_id: shiftId,
          order_number: orderNumber,
          order_type: 'dine_in',
          table_id: this.currentTableId,
          cashier_id: cashierId,
          customer_id: this.selectedCustomerId,
          loyalty_redeem_points: calcs.loyaltyRedeemPoints,
          catalog_version_id: this.catalog.version && Number(this.catalog.version.id) > 0 ? Number(this.catalog.version.id) : null,
          offline_lease_id: this.offlineLease && this.offlineLease.lease_id ? this.offlineLease.lease_id : null,
          save_as_open: true,
          subtotal: calcs.subtotal,
          discount_amount: calcs.discount,
          tax_amount: calcs.vat,
          total_amount: calcs.total,
          paid_amount: 0,
          change_amount: 0,
          created_at: new Date().toISOString(),
          items: this.ticket.map(item => ({
            product_id: item.product_id,
            variant_id: item.variant_id,
            name: item.name,
            variant_name: item.variant_name,
            unit_price: item.unit_price,
            quantity: item.quantity,
            addons: item.addons
          }))
        };

        await window.PosDB.queueOrder(orderPayload);
        queued = true;
        await this.refreshQueueCount();

        this.ticket = [];
        this.renderTicket();
        const pending = await window.PosDB.getPendingOrders();
        if (navigator.onLine) void this.syncQueue();

        alert(
          'Open bill ' + orderNumber + ' was saved to this terminal. ' +
          (navigator.onLine
            ? 'Synchronization has started; check Open Bills after it completes.'
            : 'The terminal is offline, so it will sync when connectivity returns.') +
          (pending.length > 0 ? ' Do not submit the same ticket again; check the Sync badge if it remains pending.' : '')
        );
      } catch (error) {
        console.error('[POS Open Bill] Unable to save the open ticket:', error);
        if (queued) {
          this.ticket = [];
          this.renderTicket();
          alert('The open bill was saved locally. Do not resubmit it; reconnect and check the Sync badge.');
        } else {
          alert('The open bill could not be saved to this terminal. No order was submitted; check the browser Console before retrying.');
        }
      } finally {
        this.isProcessingSale = false;
        if (button) button.innerHTML = originalHtml;
        this.updateOpenOrderButton();
      }
    },

    async processSale() {
      // Prevent a rapid double-click from generating multiple UUIDs/orders.
      if (this.isProcessingSale) return;
      if (!Array.isArray(this.ticket) || this.ticket.length === 0) return;
      if (!this.catalog.version || Number(this.catalog.version.id) <= 0) {
        alert('This terminal has no verified server catalog snapshot. Reconnect and refresh the POS before charging this order.');
        return;
      }

      this.isProcessingSale = true;
      const confirmButton = document.getElementById('confirmSaleBtn');
      const originalButtonHtml = confirmButton.innerHTML;
      confirmButton.disabled = true;
      confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving Sale...';

      let orderPayload = null;
      let queued = false;

      try {
        const calcs = this.getCalculations();
        const tendered = parseFloat(document.getElementById('tenderInput').value) || 0;
        if (tendered < calcs.total) {
          alert('Tendered cash is less than the total due.');
          return;
        }

        // Re-check at the final payment action too: a pending order may have
        // appeared after the checkout modal was opened (for example, another tab).
        if (navigator.onLine) {
          const pendingBeforeSale = await window.PosDB.getPendingOrders();
          if (pendingBeforeSale.length > 0) {
            await this.syncQueue();
            const pendingAfterSaleCheck = await window.PosDB.getPendingOrders();
            alert(
              `No new sale was created because ${pendingBeforeSale.length} earlier order(s) were pending. ` +
              `Sync was attempted; ${pendingAfterSaleCheck.length} order(s) remain pending. ` +
              'Check the Orders page and confirm the earlier sales before submitting this ticket.'
            );
            return;
          }
        }

        const clientUuid = generateClientUuid();
        const orderNumber = `ORD-${Date.now().toString().slice(-6)}-${Math.floor(Math.random() * 1000)}`;

        orderPayload = {
          client_uuid: clientUuid,
          device_id: deviceId,
          shift_id: shiftId,
          order_number: orderNumber,
          order_type: this.orderType,
          table_id: this.currentTableId,
          cashier_id: cashierId,
          customer_id: this.selectedCustomerId,
          loyalty_redeem_points: calcs.loyaltyRedeemPoints,
          catalog_version_id: this.catalog.version && Number(this.catalog.version.id) > 0 ? Number(this.catalog.version.id) : null,
          offline_lease_id: this.offlineLease && this.offlineLease.lease_id ? this.offlineLease.lease_id : null,
          subtotal: calcs.subtotal,
          discount_amount: calcs.discount,
          tax_amount: calcs.vat,
          total_amount: calcs.total,
          paid_amount: tendered,
          change_amount: round(tendered - calcs.total),
          created_at: new Date().toISOString(),
          items: this.ticket.map(item => ({
            product_id: item.product_id,
            variant_id: item.variant_id,
            name: item.name,
            variant_name: item.variant_name,
            unit_price: item.unit_price,
            quantity: item.quantity,
            addons: item.addons
          })),
          payment: {
            client_payment_uuid: generateClientUuid(),
            payment_method: 'cash',
            amount: calcs.total,
            tendered_amount: tendered,
            change_amount: round(tendered - calcs.total)
          }
        };

        // A successful local queue write is the commit point for this browser checkout.
        // Never generate a second UUID for the same in-flight click.
        await window.PosDB.queueOrder(orderPayload);
        queued = true;

        try {
          await this.refreshQueueCount();
        } catch (queueCountError) {
          console.warn('[POS Queue] Order saved, but queue badge refresh failed:', queueCountError);
        }

        const checkoutModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
        if (checkoutModal) checkoutModal.hide();

        // Clear the live ticket before receipt display so preview errors cannot
        // cause the cashier to submit the same ticket again.
        this.ticket = [];
        this.renderTicket();

        if (navigator.onLine) {
          void this.syncQueue();
        }

        try {
          this.displayReceipt(orderPayload);
        } catch (receiptError) {
          console.error('[POS Receipt] Order was queued, but receipt rendering failed:', receiptError);
          alert('The order was saved to this terminal, but the receipt preview could not be displayed. Do not submit it again; check the Sync badge and Orders page.');
        }
      } catch (error) {
        console.error('[POS Sale] Checkout handling failed:', error);

        if (queued && orderPayload) {
          // If the local write succeeded, treat it as a sale already recorded locally.
          this.ticket = [];
          this.renderTicket();
          const checkoutModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
          if (checkoutModal) checkoutModal.hide();

          try {
            this.displayReceipt(orderPayload);
          } catch (receiptError) {
            console.error('[POS Receipt] Receipt fallback failed:', receiptError);
          }
          alert('This sale was already saved to the terminal. Do not submit it again. Check the Sync badge and Orders page.');
        } else {
          alert('The sale could not be saved to this terminal, so it was not submitted. Check the browser Console before retrying.');
        }
      } finally {
        this.isProcessingSale = false;
        confirmButton.innerHTML = originalButtonHtml;
        if (this.ticket.length > 0) {
          this.computeChange();
        } else {
          confirmButton.disabled = true;
        }
      }
    },

    displayReceipt(order) {
      // Keep receipts robust for old cached catalogs or catalog payloads without metadata.
      const restaurant = this.catalog && this.catalog.restaurant && typeof this.catalog.restaurant === 'object'
        ? this.catalog.restaurant
        : {};
      document.getElementById('recRestaurantName').textContent = restaurant.name || 'RESTAURANT POS';
      document.getElementById('recRestaurantAddress').textContent = restaurant.address || 'Dining Terminal';
      document.getElementById('recRestaurantTin').textContent = restaurant.tin || '000-000-000-000';
      document.getElementById('recOrderNumber').textContent = order.order_number;
      document.getElementById('recOrderDate').textContent = new Date().toLocaleString();
      document.getElementById('recCashierName').textContent = document.getElementById('activeCashierName').textContent;
      document.getElementById('recOrderType').textContent = order.order_type.toUpperCase().replace('_', ' ');

      const tblLine = document.getElementById('recTableLine');
      if (order.table_id) {
        const tObj = (this.tables || []).find(t => t.id == order.table_id);
        document.getElementById('recTableName').textContent = tObj ? `#${tObj.table_number} (${tObj.name})` : `Table ${order.table_id}`;
        tblLine.style.display = 'flex';
      } else {
        tblLine.style.display = 'none';
      }

      document.getElementById('recItemsBody').innerHTML = order.items.map(it => `
        <tr>
          <td>
            <strong>${it.name}</strong>
            ${it.variant_name ? ` (${it.variant_name})` : ''}
            ${it.addons && it.addons.length ? `<br><small>+ ${it.addons.map(a => a.name).join(', ')}</small>` : ''}
          </td>
          <td class="text-center">${it.quantity}</td>
          <td class="text-end">₱${it.unit_price.toFixed(2)}</td>
          <td class="text-end">₱${(it.unit_price * it.quantity).toFixed(2)}</td>
        </tr>
      `).join('');

      document.getElementById('recSubtotal').textContent = `₱${order.subtotal.toFixed(2)}`;
      document.getElementById('recDiscount').textContent = `-₱${order.discount_amount.toFixed(2)}`;
      document.getElementById('recVat').textContent = `₱${order.tax_amount.toFixed(2)}`;
      document.getElementById('recTotal').textContent = `₱${order.total_amount.toFixed(2)}`;
      document.getElementById('recTendered').textContent = `₱${order.paid_amount.toFixed(2)}`;
      document.getElementById('recChange').textContent = `₱${order.change_amount.toFixed(2)}`;

      new bootstrap.Modal(document.getElementById('receiptModal')).show();
    },

    async syncQueue() {
      if (!navigator.onLine || this.isSyncingQueue) return;

      // Acquire the sync lock before the first await so simultaneous calls
      // cannot both submit the same pending batch.
      this.isSyncingQueue = true;
      const syncBtn = document.getElementById('posSyncBtn');
      let pendingOrderCount = 0;

      try {
        const pending = await window.PosDB.getPendingOrders();
        pendingOrderCount = pending.length;
        if (pending.length === 0) {
          this.clearSyncNotice();
          return;
        }

        if (syncBtn) {
          syncBtn.classList.add('disabled');
          const spinner = syncBtn.querySelector('i');
          if (spinner) spinner.classList.add('spin-icon');
        }

        const response = await fetch('<?= BASE_URL ?>/api/pos/sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orders: pending })
        });

        const res = await response.json().catch(() => ({}));
        if (!response.ok) {
          const detail = res && res.error ? String(res.error) : `HTTP ${response.status}`;
          console.error('[POS Sync] Server rejected queued order batch:', response.status, detail);
          this.showSyncNotice(
            `Sync failed for ${pending.length} queued order(s): ${detail}. The orders remain saved locally; do not submit them again.`,
            'danger'
          );
          return;
        }

        if (!['success', 'partial'].includes(res.status) || !Array.isArray(res.results)) {
          console.error('[POS Sync] Unexpected server response for queued orders:', res);
          this.showSyncNotice(
            `The server returned an unexpected sync response. ${pending.length} order(s) remain queued locally; do not submit them again.`,
            'danger'
          );
          return;
        }

        for (const result of res.results) {
          if (
            result &&
            ['synced', 'already_synced'].includes(result.status) &&
            result.client_uuid &&
            Number(result.server_order_id) > 0
          ) {
            await window.PosDB.markOrderSynced(result.client_uuid, result.server_order_id);
            if (Number.isFinite(Number(result.loyalty_points_earned)) && Number.isFinite(Number(result.loyalty_points_redeemed))) {
              const queuedOrder = pending.find(order => order && order.client_uuid === result.client_uuid);
              const customerId = Number(result.customer_id || (queuedOrder && queuedOrder.customer_id) || 0);
              const customer = this.customers.find(item => Number(item.id) === customerId);
              if (customer && customerId > 0) {
                customer.loyalty_points = Math.max(0,
                  Number(customer.loyalty_points || 0) +
                  Number(result.loyalty_points_earned || 0) -
                  Number(result.loyalty_points_redeemed || 0)
                );
                const customerSelect = document.getElementById('posCustomerSelect');
                const option = customerSelect ? Array.from(customerSelect.options).find(item => Number(item.value) === customerId) : null;
                if (option) {
                  const phone = customer.phone ? ' · ' + customer.phone : '';
                  option.textContent = customer.full_name + phone + ' · ' + customer.loyalty_points + ' pts';
                }
                this.renderLoyaltyOptions();
              }
            }
          } else {
            console.error('[POS Sync] An order was not acknowledged by the server:', result);
          }
        }

        const remaining = await window.PosDB.getPendingOrders();
        if (remaining.length > 0) {
          const rejected = (res.results || []).find(result =>
            !result ||
            !['synced', 'already_synced'].includes(result.status) ||
            !result.client_uuid ||
            Number(result.server_order_id) <= 0
          );
          const reason = rejected && (rejected.error || rejected.message || rejected.reason);
          this.showSyncNotice(
            `Sync did not complete. ${remaining.length} order(s) remain queued locally${reason ? `: ${String(reason)}` : ''}. Do not submit them again.`,
            'warning'
          );
        } else {
          this.clearSyncNotice();
        }
      } catch (err) {
        console.warn('[POS Sync] Background sync delayed:', err);
        const detail = err && err.message ? String(err.message) : 'Unknown sync error';
        this.showSyncNotice(
          `Could not sync ${pendingOrderCount || 'the queued'} order(s): ${detail}. Queued orders remain saved locally; do not submit them again.`,
          'danger'
        );
      } finally {
        if (syncBtn) {
          syncBtn.classList.remove('disabled');
          const spinner = syncBtn.querySelector('i');
          if (spinner) spinner.classList.remove('spin-icon');
        }
        this.isSyncingQueue = false;
        try {
          await this.refreshQueueCount();
        } catch (queueCountError) {
          console.warn('[POS Queue] Unable to refresh pending order count:', queueCountError);
        }
      }
    },

    // PIN Pad Lockout & Brute-Force Protection
    showLockModal() {
      const pinInput = document.getElementById('pinDisplay');
      pinInput.value = '';
      document.getElementById('pinError').style.display = 'none';
      new bootstrap.Modal(document.getElementById('lockModal')).show();
      window.setTimeout(() => pinInput.focus(), 150);
    },

    pinKey(num) {
      if (Date.now() < this.lockoutUntil) return;
      const disp = document.getElementById('pinDisplay');
      if (disp.value.length < 6) {
        disp.value += num;
      }
    },

    pinClear() {
      const disp = document.getElementById('pinDisplay');
      disp.value = disp.value.slice(0, -1);
    },

    async pinSubmit() {
      const errEl = document.getElementById('pinError');
      if (Date.now() < this.lockoutUntil) {
        const rem = Math.ceil((this.lockoutUntil - Date.now()) / 1000);
        errEl.textContent = `Too many failed attempts. Locked for ${rem}s.`;
        errEl.style.display = 'block';
        return;
      }

      const pin = document.getElementById('pinDisplay').value;
      if (pin.length < 4) {
        errEl.textContent = 'Please enter at least 4 digits.';
        errEl.style.display = 'block';
        return;
      }

      if (!/^[0-9]{4,6}$/.test(pin)) {
        errEl.textContent = 'PIN must contain 4 to 6 digits.';
        errEl.style.display = 'block';
        return;
      }

      try {
        const staff = await window.PosDB.getStaff(cashierId);
        if (!staff || !staff.pin_salt || !staff.pin_hash) {
          errEl.textContent = navigator.onLine
            ? 'No offline PIN is configured for this cashier on this device. Open Terminals → Staff Authorizations and set one first.'
            : 'This cashier has no authorized offline PIN cached on this device. Reconnect and configure the PIN before locking the terminal.';
          errEl.style.display = 'block';
          return;
        }

        const valid = await window.PosDB.verifyStaffPin(pin, staff.pin_salt, staff.pin_hash);
        if (valid) {
          this.failedPinAttempts = 0;
          const modal = bootstrap.Modal.getInstance(document.getElementById('lockModal'));
          if (modal) modal.hide();
          return;
        }
      } catch (error) {
        console.error('[POS Offline Auth] PIN verification failed:', error);
        errEl.textContent = 'PIN verification could not be completed. Reconnect and check the device authorization setup.';
        errEl.style.display = 'block';
        return;
      }

      this.failedPinAttempts += 1;
      if (this.failedPinAttempts >= 5) {
        this.lockoutUntil = Date.now() + 60000;
        errEl.textContent = '5 failed attempts! Locked for 60 seconds.';
      } else {
        errEl.textContent = `Invalid PIN (${5 - this.failedPinAttempts} attempts left).`;
      }
      errEl.style.display = 'block';
      document.getElementById('pinDisplay').value = '';
    }
  };

  function round(val) {
    return Math.round(val * 100) / 100;
  }

  window.posApp = app;

  // Support both hardware keyboards and the on-screen POS keypad. Keep
  // keyboard input numeric-only and capped at six digits; Enter submits PIN.
  document.addEventListener('DOMContentLoaded', () => {
    const pinInput = document.getElementById('pinDisplay');
    if (pinInput) {
      pinInput.addEventListener('input', () => {
        const digitsOnly = pinInput.value.replace(/[^0-9]/g, '').slice(0, 6);
        if (pinInput.value !== digitsOnly) pinInput.value = digitsOnly;
      });
      pinInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          void app.pinSubmit();
        }
      });
      document.getElementById('lockModal')?.addEventListener('shown.bs.modal', () => pinInput.focus());
    }
    app.init();
  });
})();
</script>

<script>
(function () {
  const tabs = document.getElementById('posWorkspaceTabs');
  const checkoutPane = document.getElementById('posCheckoutPane');
  const menusPane = document.getElementById('posMenusPane');

  if (!tabs || !checkoutPane || !menusPane) return;

  const checkoutTab = document.getElementById('posCheckoutTab');
  const menusTab = document.getElementById('posMenusTab');
  const count = document.getElementById('posCheckoutCount');

  function updateCartCount() {
    // Read the rendered ticket quantities; do not alter POS cart state.
    const quantityNodes = checkoutPane.querySelectorAll('.ticket-qty');
    let total = 0;

    quantityNodes.forEach(function (node) {
      const value = Number.parseInt(node.textContent.trim(), 10);
      if (Number.isFinite(value) && value > 0) total += value;
    });

    if (count) count.textContent = String(total);
  }

  function showTab(name) {
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    const checkoutActive = name === 'checkout';

    if (!isMobile) {
      checkoutPane.classList.remove('pos-tab-hidden');
      menusPane.classList.remove('pos-tab-hidden');
      updateCartCount();
      return;
    }

    checkoutPane.classList.toggle('pos-tab-hidden', !checkoutActive);
    menusPane.classList.toggle('pos-tab-hidden', checkoutActive);

    checkoutTab.classList.toggle('active', checkoutActive);
    menusTab.classList.toggle('active', !checkoutActive);

    checkoutTab.setAttribute('aria-selected', String(checkoutActive));
    menusTab.setAttribute('aria-selected', String(!checkoutActive));

    updateCartCount();
    window.dispatchEvent(new Event('resize'));
  }

  window.addEventListener('resize', function () {
    showTab(checkoutTab.classList.contains('active') ? 'checkout' : 'menus');
  });

  checkoutTab.addEventListener('click', function () {
    showTab('checkout');
  });

  menusTab.addEventListener('click', function () {
    showTab('menus');
  });

  // Keep the cart count current after POS ticket updates.
  const observer = new MutationObserver(updateCartCount);
  observer.observe(checkoutPane, { childList:true, subtree:true, characterData:true });

  // Checkout is the initial view on both desktop and mobile.
  showTab('checkout');
})();
</script>



<script>
(function () {
  const nav = document.getElementById('posFloatingNav');
  const toggle = document.getElementById('posFloatingNavToggle');
  const sync = document.getElementById('posFloatingSync');
  const print = document.getElementById('posFloatingPrint');
  if (!nav || !toggle) return;

  function setOpen(open) {
    nav.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close quick actions' : 'Open quick actions');
    const icon = toggle.querySelector('i');
    if (icon) icon.className = open ? 'bi bi-x-lg' : 'bi bi-three-dots';
  }

  function refreshPendingCount() {
    const source = document.getElementById('unsyncedCount');
    const count = source ? source.textContent.trim() : '0';
    ['posFloatingPendingCount', 'posDesktopPendingCount'].forEach(function (id) {
      const target = document.getElementById(id);
      if (target) target.textContent = count || '0';
    });
  }

  toggle.addEventListener('click', function () {
    setOpen(!nav.classList.contains('is-open'));
  });
  nav.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () { setOpen(false); });
  });
  sync?.addEventListener('click', function () {
    setOpen(false);
    if (window.posApp && typeof window.posApp.syncQueue === 'function') {
      void window.posApp.syncQueue();
    }
  });
  print?.addEventListener('click', function () {
    setOpen(false);
    window.print();
  });

  const pendingSource = document.getElementById('unsyncedCount');
  if (pendingSource && window.MutationObserver) {
    new MutationObserver(refreshPendingCount).observe(pendingSource, { childList:true, characterData:true, subtree:true });
  }
  refreshPendingCount();

  document.addEventListener('click', function (event) {
    if (!nav.contains(event.target)) setOpen(false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setOpen(false);
  });
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
