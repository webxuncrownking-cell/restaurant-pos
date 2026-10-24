<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <title>Order at <?= htmlspecialchars((string)$table['restaurant_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <link href="<?= htmlspecialchars(BASE_URL . '/public/assets/css/bootstrap.min.css', ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars(BASE_URL . '/public/assets/css/qr-icons.css', ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <style>
    :root { --qr-blue:#2563eb; --qr-ink:#172033; --qr-muted:#66758b; --qr-border:#e3eaf3; }
    body { background:#f4f7fb; color:var(--qr-ink); font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    .qr-shell { max-width:1360px; margin:0 auto; padding:22px 18px 42px; }
    .qr-brand { display:flex; align-items:center; gap:13px; min-width:0; }
    .qr-logo { width:54px; height:54px; flex:0 0 54px; object-fit:contain; object-position:center; border-radius:15px; background:#fff; border:1px solid var(--qr-border); padding:5px; }
    .qr-restaurant { font-size:1.26rem; font-weight:850; letter-spacing:-.035em; margin:0; overflow-wrap:anywhere; }
    .qr-sub { font-size:.82rem; color:var(--qr-muted); margin-top:3px; }
    .qr-table-pill { display:inline-flex; align-items:center; gap:7px; background:#eaf2ff; border:1px solid #c9dcff; color:#1d4ed8; border-radius:999px; padding:8px 12px; font-weight:800; font-size:.8rem; white-space:nowrap; }
    .qr-hero { background:#fff; border:1px solid var(--qr-border); border-radius:20px; padding:20px; box-shadow:0 5px 20px rgba(15,23,42,.035); }
    .qr-section-title { font-size:1.1rem; font-weight:850; letter-spacing:-.025em; margin:0; }
    .qr-search { position:relative; min-width:min(100%,260px); }
    .qr-search .bi { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#8290a5; }
    .qr-search input { padding-left:38px; border-radius:12px; min-height:44px; border-color:#dce5f0; }
    .qr-categories { display:flex; flex-wrap:wrap; gap:8px; }
    .qr-category { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:7px 13px; border:1px solid #d5deea; border-radius:999px; color:#536176; background:#fff; font-size:.79rem; font-weight:750; }
    .qr-category.active { background:var(--qr-ink); color:#fff; border-color:var(--qr-ink); }
    .qr-product { height:100%; overflow:hidden; background:#fff; border:1px solid var(--qr-border); border-radius:17px; box-shadow:0 3px 12px rgba(15,23,42,.035); }
    .qr-photo { position:relative; width:100%; height:180px; overflow:hidden; background:linear-gradient(140deg,#f2f6fb,#eaf0f7); }
    .qr-photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; }
    .qr-no-photo { position:absolute; inset:0; display:grid; place-items:center; color:#a9b6c8; font-size:2.1rem; }
    .qr-product-body { display:flex; flex-direction:column; padding:14px; min-height:168px; }
    .qr-product-title { font-size:1rem; font-weight:850; line-height:1.3; margin:0; overflow-wrap:anywhere; }
    .qr-product-category { display:inline-flex; align-self:flex-start; background:#f1f5f9; color:#546277; border:1px solid #e2e8f0; border-radius:7px; padding:4px 7px; font-size:.68rem; font-weight:750; }
    .qr-price { color:#07834f; font-weight:900; font-size:1.05rem; white-space:nowrap; }
    .qr-product-description { color:var(--qr-muted); font-size:.78rem; line-height:1.45; }
    .qr-add { display:inline-flex; align-items:center; justify-content:center; gap:7px; width:100%; min-height:42px; color:#fff; background:var(--qr-blue); border:1px solid var(--qr-blue); border-radius:11px; font-weight:800; transition:background .15s,border-color .15s,transform .15s; }
    .qr-add:hover { background:#1d4ed8; border-color:#1d4ed8; color:#fff; transform:translateY(-1px); }
    .qr-cart { position:sticky; top:18px; background:#fff; border:1px solid var(--qr-border); border-radius:18px; box-shadow:0 4px 18px rgba(15,23,42,.045); overflow:hidden; }
    .qr-cart-head { padding:16px 18px; border-bottom:1px solid #edf1f6; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .qr-cart-count { display:inline-flex; min-width:25px; height:25px; align-items:center; justify-content:center; border-radius:999px; padding:0 7px; background:#eaf2ff; color:#1d4ed8; font-size:.75rem; font-weight:850; }
    .qr-cart-items { padding:6px 18px; max-height:310px; overflow:auto; }
    .qr-empty-cart { padding:24px 0; text-align:center; color:#8390a3; font-size:.83rem; }
    .qr-empty-cart .bi { display:block; font-size:1.7rem; margin-bottom:6px; }
    .qr-cart-line { display:flex; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid #edf1f6; }
    .qr-cart-line:last-child { border-bottom:0; }
    .qr-cart-name { font-size:.83rem; font-weight:800; line-height:1.3; }
    .qr-cart-detail { margin-top:4px; color:#748196; font-size:.72rem; line-height:1.4; overflow-wrap:anywhere; }
    .qr-qty { display:inline-flex; align-items:center; gap:8px; margin-top:7px; }
    .qr-qty button { width:27px; height:27px; display:grid; place-items:center; border:1px solid #dbe3ed; background:#fff; color:#334155; border-radius:8px; }
    .qr-qty span { min-width:14px; text-align:center; font-size:.79rem; font-weight:800; }
    .qr-cart-foot { padding:16px 18px; background:#f9fbfd; border-top:1px solid #edf1f6; }
    .qr-input-label { display:block; font-size:.75rem; font-weight:800; margin-bottom:6px; }
    .qr-cart-foot .form-control { min-height:42px; border-color:#dce5f0; border-radius:10px; }
    .qr-submit { width:100%; min-height:48px; display:flex; align-items:center; justify-content:center; gap:8px; background:#0b8754; color:#fff; border:1px solid #0b8754; border-radius:12px; font-weight:850; }
    .qr-submit:hover { background:#087447; color:#fff; border-color:#087447; }
    .qr-submit:disabled { opacity:.5; cursor:not-allowed; }
    .qr-footnote { color:#7b889b; font-size:.72rem; line-height:1.45; }
    .qr-modal .modal-content { border:0; border-radius:18px; overflow:hidden; }
    .qr-modal .modal-header { border-bottom:1px solid #edf1f6; padding:18px 20px; }
    .qr-modal .modal-body { padding:20px; }
    .qr-modal .form-control,.qr-modal .form-select { border-color:#dce5f0; border-radius:10px; min-height:42px; }
    .qr-modal .form-check { padding:9px 10px 9px 34px; border:1px solid #e6edf5; border-radius:10px; margin:7px 0 0; }
    .qr-modal .form-check-input { margin-left:-23px; }
    .qr-modal .form-check-label { display:flex; justify-content:space-between; gap:10px; font-size:.83rem; font-weight:650; }
    .qr-modal-total { background:#f6f9fd; padding:12px 14px; border-radius:11px; font-weight:850; }
    @media(max-width:991px) { .qr-shell { padding:14px 12px 32px; } .qr-cart { position:static; } }
    @media(max-width:575px) { .qr-brand { align-items:flex-start; } .qr-logo { width:44px; height:44px; flex-basis:44px; } .qr-restaurant { font-size:1.05rem; } .qr-hero { padding:15px; } .qr-photo { height:148px; } .qr-product-body { padding:11px; } .qr-product-title { font-size:.91rem; } }
  </style>
</head>
<body>
<div class="qr-shell">
  <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="qr-brand">
      <?php if (!empty($table['logo_path'])): ?>
        <img class="qr-logo" src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$table['logo_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php else: ?>
        <span class="qr-logo d-grid place-items-center text-primary fs-4"><i class="bi bi-shop"></i></span>
      <?php endif; ?>
      <div class="min-w-0">
        <h1 class="qr-restaurant"><?= htmlspecialchars((string)$table['restaurant_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="qr-sub">Browse the menu and send your order to our kitchen</div>
      </div>
    </div>
    <div class="qr-table-pill"><i class="bi bi-geo-alt-fill"></i> Table <?= htmlspecialchars((string)$table['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$table['table_name'], ENT_QUOTES, 'UTF-8') ?></div>
  </header>

  <?php if ($submitted_order_number !== ''): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-start gap-3" role="status">
      <i class="bi bi-check-circle-fill fs-3"></i>
      <div><strong class="d-block">Order sent to the kitchen!</strong>Your order number is <strong><?= htmlspecialchars($submitted_order_number, ENT_QUOTES, 'UTF-8') ?></strong>. Please pay at the cashier when requested by staff. You may keep browsing for another order.</div>
    </div>
  <?php endif; ?>
  <?php if ($error_message !== ''): ?>
    <div class="alert alert-warning border-0 shadow-sm" role="alert"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (($table['table_status'] ?? '') === 'cleaning'): ?>
    <div class="alert alert-info border-0 shadow-sm"><strong>Table unavailable.</strong> Please ask a staff member for assistance.</div>
  <?php endif; ?>

  <form id="qrOrderForm" method="POST" action="<?= htmlspecialchars(BASE_URL . '/order/' . $qr_token . '/submit', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="client_uuid" value="<?= htmlspecialchars($client_uuid, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="items_json" id="qrItemsJson" value="[]">

    <div class="row g-4">
      <section class="col-12 col-lg-8">
        <div class="qr-hero mb-3">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
              <h2 class="qr-section-title">Explore our menu</h2>
              <p class="text-muted small mb-0 mt-1">Choose your dishes and customize them before sending.</p>
            </div>
            <div class="qr-search">
              <i class="bi bi-search"></i>
              <input id="qrSearch" type="search" class="form-control" placeholder="Search dishes..." autocomplete="off" aria-label="Search menu">
            </div>
          </div>
          <div id="qrCategories" class="qr-categories">
            <button class="qr-category active" type="button" data-category="all">All items</button>
            <?php foreach ($categories as $category): ?>
              <button class="qr-category" type="button" data-category="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name'], ENT_QUOTES, 'UTF-8') ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="qrProducts" class="row g-3">
          <?php foreach ($products as $product): ?>
            <div class="col-6 col-md-6 col-xl-4 qr-product-col" data-product-card data-product-id="<?= (int)$product['id'] ?>" data-category-id="<?= (int)$product['category_id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower((string)$product['name'] . ' ' . (string)$product['category_name']), ENT_QUOTES, 'UTF-8') ?>">
              <article class="qr-product">
                <div class="qr-photo">
                  <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= htmlspecialchars((string)$product['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                  <?php else: ?>
                    <div class="qr-no-photo"><i class="bi bi-image"></i></div>
                  <?php endif; ?>
                </div>
                <div class="qr-product-body">
                  <span class="qr-product-category mb-2"><?= htmlspecialchars((string)$product['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                  <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <h3 class="qr-product-title"><?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <span class="qr-price">₱<?= number_format((float)$product['base_price'], 2) ?></span>
                  </div>
                  <div class="qr-product-description mb-3">
                    <?= !empty($product['variants']) ? 'Serving options available' : 'Freshly prepared to order' ?>
                    <?= !empty($product['addon_groups']) ? ' · Add-ons available' : '' ?>
                  </div>
                  <button class="qr-add mt-auto" type="button" data-add-product="<?= (int)$product['id'] ?>" <?= (($table['table_status'] ?? '') === 'cleaning') ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle"></i> Customize &amp; add
                  </button>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="qrNoProducts" class="qr-hero text-center text-muted d-none"><i class="bi bi-search fs-3 d-block mb-2"></i>No menu items match your search.</div>
      </section>

      <aside class="col-12 col-lg-4">
        <div class="qr-cart">
          <div class="qr-cart-head">
            <div><h2 class="qr-section-title">Your order</h2><div class="small text-muted mt-1">Table <?= htmlspecialchars((string)$table['table_number'], ENT_QUOTES, 'UTF-8') ?></div></div>
            <span class="qr-cart-count" id="qrCartCount">0</span>
          </div>
          <div id="qrCartItems" class="qr-cart-items">
            <div class="qr-empty-cart"><i class="bi bi-basket2"></i>Your order is empty.<br>Add a dish to begin.</div>
          </div>
          <div class="qr-cart-foot">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
              <span class="fw-bold">Estimated total</span><strong class="fs-5" id="qrCartTotal">₱0.00</strong>
            </div>
            <div class="mb-3">
              <label for="qrCustomerName" class="qr-input-label">Your name</label>
              <input class="form-control" id="qrCustomerName" name="customer_name" maxlength="120" required placeholder="Name for the order">
            </div>
            <div class="mb-3">
              <label for="qrCustomerNote" class="qr-input-label">Order note <span class="fw-normal text-muted">(optional)</span></label>
              <textarea class="form-control" id="qrCustomerNote" name="customer_note" rows="2" maxlength="500" placeholder="Anything staff should know?"></textarea>
            </div>
            <button class="qr-submit" type="submit" id="qrSubmit" disabled><i class="bi bi-send-fill"></i> Send order to kitchen</button>
            <p class="qr-footnote mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>This submits an unpaid dine-in order to the kitchen. Please pay at the cashier. Prices are confirmed by the restaurant when the order is submitted.</p>
          </div>
        </div>
      </aside>
    </div>
  </form>
</div>

<div class="modal fade qr-modal" id="qrCustomizeModal" tabindex="-1" aria-labelledby="qrCustomizeTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div><h2 class="modal-title fs-5 fw-bold" id="qrCustomizeTitle">Customize item</h2><div class="small text-muted mt-1" id="qrCustomizeBasePrice"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="qrCustomizeFields"></div>
        <div class="mt-3">
          <label class="form-label small fw-bold" for="qrItemNote">Special instructions (optional)</label>
          <textarea class="form-control" id="qrItemNote" rows="2" maxlength="255" placeholder="e.g. sauce on the side"></textarea>
        </div>
        <div class="d-flex align-items-center justify-content-between gap-3 mt-3">
          <label for="qrItemQuantity" class="small fw-bold mb-0">Quantity</label>
          <input id="qrItemQuantity" class="form-control" style="max-width:105px" type="number" min="1" max="50" value="1">
        </div>
        <div class="qr-modal-total d-flex justify-content-between gap-3 mt-3"><span>Item total</span><span id="qrModalTotal">₱0.00</span></div>
        <div id="qrCustomizeError" class="text-danger small mt-2 d-none"></div>
      </div>
      <div class="modal-footer border-0 pt-0 px-3 pb-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary px-4 fw-bold" id="qrConfirmAdd"><i class="bi bi-plus-lg me-1"></i> Add to order</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars(BASE_URL . '/public/assets/js/bootstrap.bundle.min.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(() => {
  const products = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const productById = new Map(products.map(product => [Number(product.id), product]));
  const cart = [];
  let selectedProduct = null;
  let selectedCategory = 'all';
  const modal = new bootstrap.Modal(document.getElementById('qrCustomizeModal'));
  const currency = value => '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
  const productUnitPrice = (productId, variantId, addonIds) => {
    const product = productById.get(Number(productId));
    if (!product) return 0;
    const variant = (product.variants || []).find(item => Number(item.id) === Number(variantId));
    let total = Number(product.base_price || 0) + Number(variant?.additional_price || 0);
    (product.addon_groups || []).forEach(group => (group.options || []).forEach(option => {
      if (addonIds.includes(Number(option.id))) total += Number(option.price || 0);
    }));
    return Math.max(0, total);
  };

  function updateModalTotal() {
    if (!selectedProduct) return;
    const quantity = Math.max(1, Math.min(50, parseInt(document.getElementById('qrItemQuantity').value || '1', 10)));
    const variantId = document.getElementById('qrVariantSelect')?.value || '';
    const addonIds = Array.from(document.querySelectorAll('#qrCustomizeFields input[data-addon-id]:checked')).map(input => Number(input.dataset.addonId));
    document.getElementById('qrModalTotal').textContent = currency(productUnitPrice(selectedProduct.id, variantId, addonIds) * quantity);
  }

  function openCustomize(productId) {
    selectedProduct = productById.get(Number(productId));
    if (!selectedProduct) return;
    document.getElementById('qrCustomizeTitle').textContent = selectedProduct.name;
    document.getElementById('qrCustomizeBasePrice').textContent = 'From ' + currency(selectedProduct.base_price);
    document.getElementById('qrItemQuantity').value = '1';
    document.getElementById('qrItemNote').value = '';
    document.getElementById('qrCustomizeError').classList.add('d-none');
    let html = '';

    if ((selectedProduct.variants || []).length) {
      html += '<div class="mb-3"><label class="form-label small fw-bold" for="qrVariantSelect">Serving / size</label><select id="qrVariantSelect" class="form-select" required><option value="">Choose a serving...</option>';
      selectedProduct.variants.forEach(variant => {
        const amount = Number(selectedProduct.base_price || 0) + Number(variant.additional_price || 0);
        html += '<option value="' + Number(variant.id) + '">' + escapeHtml(variant.name) + ' · ' + currency(amount) + '</option>';
      });
      html += '</select></div>';
    }

    (selectedProduct.addon_groups || []).forEach((group, index) => {
      const groupId = Number(group.id);
      html += '<fieldset class="mb-3" data-addon-group="' + groupId + '" data-required="' + (Number(group.is_required) === 1 ? '1' : '0') + '"><legend class="form-label small fw-bold mb-1">' + escapeHtml(group.name) + (Number(group.is_required) === 1 ? ' <span class="text-danger">*</span>' : ' <span class="text-muted fw-normal">(optional)</span>') + '</legend>';
      (group.options || []).forEach(option => {
        const optionId = Number(option.id);
        html += '<div class="form-check"><input class="form-check-input" type="checkbox" data-addon-id="' + optionId + '" id="qr-addon-' + groupId + '-' + optionId + '"><label class="form-check-label" for="qr-addon-' + groupId + '-' + optionId + '"><span>' + escapeHtml(option.name) + '</span><span>' + (Number(option.price) ? '+' + currency(option.price) : 'Included') + '</span></label></div>';
      });
      html += '</fieldset>';
    });

    if (!html) html = '<p class="text-muted small mb-0">No extra options for this item.</p>';
    document.getElementById('qrCustomizeFields').innerHTML = html;
    document.querySelectorAll('#qrCustomizeFields input[data-addon-id], #qrVariantSelect, #qrItemQuantity').forEach(input => input.addEventListener('change', updateModalTotal));
    document.getElementById('qrItemQuantity').addEventListener('input', updateModalTotal);
    updateModalTotal();
    modal.show();
  }

  function renderCart() {
    const target = document.getElementById('qrCartItems');
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    const total = cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
    document.getElementById('qrCartCount').textContent = String(count);
    document.getElementById('qrCartTotal').textContent = currency(total);
    document.getElementById('qrSubmit').disabled = cart.length === 0;
    if (!cart.length) {
      target.innerHTML = '<div class="qr-empty-cart"><i class="bi bi-basket2"></i>Your order is empty.<br>Add a dish to begin.</div>';
      document.getElementById('qrItemsJson').value = '[]';
      return;
    }

    target.innerHTML = cart.map((item, index) => {
      const product = productById.get(item.product_id);
      const variant = (product?.variants || []).find(v => Number(v.id) === Number(item.variant_id));
      const addonNames = item.addons.map(id => (product?.addon_groups || []).flatMap(group => group.options || []).find(option => Number(option.id) === Number(id))?.name).filter(Boolean);
      const details = [variant?.name, ...addonNames].filter(Boolean).join(' · ');
      return '<div class="qr-cart-line"><div class="min-w-0"><div class="qr-cart-name">' + escapeHtml(product?.name || 'Menu item') + '</div>' +
        (details ? '<div class="qr-cart-detail">' + escapeHtml(details) + '</div>' : '') +
        (item.special_instructions ? '<div class="qr-cart-detail"><i class="bi bi-chat-left-text me-1"></i>' + escapeHtml(item.special_instructions) + '</div>' : '') +
        '<div class="qr-qty"><button type="button" data-cart-action="decrease" data-index="' + index + '" aria-label="Decrease quantity"><i class="bi bi-dash"></i></button><span>' + item.quantity + '</span><button type="button" data-cart-action="increase" data-index="' + index + '" aria-label="Increase quantity"><i class="bi bi-plus"></i></button><button type="button" data-cart-action="remove" data-index="' + index + '" aria-label="Remove item" class="ms-1 text-danger"><i class="bi bi-trash3"></i></button></div></div>' +
        '<strong class="small text-nowrap">' + currency(item.unit_price * item.quantity) + '</strong></div>';
    }).join('');
    document.getElementById('qrItemsJson').value = JSON.stringify(cart.map(item => ({
      product_id:item.product_id, variant_id:item.variant_id, addons:item.addons,
      quantity:item.quantity, special_instructions:item.special_instructions
    })));
  }

  document.querySelectorAll('[data-add-product]').forEach(button => button.addEventListener('click', () => openCustomize(button.dataset.addProduct)));
  document.getElementById('qrConfirmAdd').addEventListener('click', () => {
    if (!selectedProduct) return;
    const variantSelect = document.getElementById('qrVariantSelect');
    if (variantSelect && !variantSelect.value) {
      document.getElementById('qrCustomizeError').textContent = 'Choose a serving or size before adding this item.';
      document.getElementById('qrCustomizeError').classList.remove('d-none');
      return;
    }
    for (const group of document.querySelectorAll('#qrCustomizeFields [data-addon-group][data-required="1"]')) {
      if (!group.querySelector('input[data-addon-id]:checked')) {
        document.getElementById('qrCustomizeError').textContent = 'Choose an option for ' + (group.querySelector('legend')?.textContent || 'the required add-on group') + '.';
        document.getElementById('qrCustomizeError').classList.remove('d-none');
        return;
      }
    }
    const variantId = variantSelect?.value ? Number(variantSelect.value) : null;
    const addonIds = Array.from(document.querySelectorAll('#qrCustomizeFields input[data-addon-id]:checked')).map(input => Number(input.dataset.addonId)).sort((a,b) => a-b);
    const quantity = Math.max(1, Math.min(50, parseInt(document.getElementById('qrItemQuantity').value || '1', 10)));
    const specialInstructions = document.getElementById('qrItemNote').value.trim().slice(0,255);
    const price = productUnitPrice(selectedProduct.id, variantId, addonIds);
    const existingIndex = cart.findIndex(item => item.product_id === Number(selectedProduct.id)
      && item.variant_id === variantId && item.special_instructions === specialInstructions
      && item.addons.join(',') === addonIds.join(','));
    if (existingIndex >= 0) cart[existingIndex].quantity = Math.min(50, cart[existingIndex].quantity + quantity);
    else cart.push({product_id:Number(selectedProduct.id),variant_id:variantId,addons:addonIds,quantity,special_instructions:specialInstructions,unit_price:price});
    modal.hide();
    renderCart();
  });

  document.getElementById('qrCartItems').addEventListener('click', event => {
    const button = event.target.closest('[data-cart-action]');
    if (!button) return;
    const item = cart[Number(button.dataset.index)];
    if (!item) return;
    if (button.dataset.cartAction === 'increase') item.quantity = Math.min(50, item.quantity + 1);
    else if (button.dataset.cartAction === 'decrease') item.quantity -= 1;
    else if (button.dataset.cartAction === 'remove') item.quantity = 0;
    if (item.quantity <= 0) cart.splice(Number(button.dataset.index), 1);
    renderCart();
  });

  document.querySelectorAll('[data-category]').forEach(button => button.addEventListener('click', () => {
    selectedCategory = button.dataset.category;
    document.querySelectorAll('[data-category]').forEach(categoryButton => categoryButton.classList.toggle('active', categoryButton === button));
    applyProductFilters();
  }));

  function applyProductFilters() {
    const query = document.getElementById('qrSearch').value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('[data-product-card]').forEach(card => {
      const categoryOk = selectedCategory === 'all' || card.dataset.categoryId === selectedCategory;
      const textOk = !query || card.dataset.search.includes(query);
      card.classList.toggle('d-none', !(categoryOk && textOk));
      if (categoryOk && textOk) visible++;
    });
    document.getElementById('qrNoProducts').classList.toggle('d-none', visible !== 0);
  }
  document.getElementById('qrSearch').addEventListener('input', applyProductFilters);
  document.getElementById('qrOrderForm').addEventListener('submit', event => {
    if (!cart.length) {
      event.preventDefault();
      alert('Add at least one item to your order.');
      return;
    }
    document.getElementById('qrItemsJson').value = JSON.stringify(cart.map(item => ({
      product_id:item.product_id, variant_id:item.variant_id, addons:item.addons,
      quantity:item.quantity, special_instructions:item.special_instructions
    })));
  });
})();
</script>
<script>
  // Cache the local app shell on this phone after the first successful visit.
  // QR order submissions still require the restaurant's local server to be reachable.
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js', { scope: '<?= BASE_URL ?>/' })
      .catch(error => console.warn('[QR Offline] Service worker registration failed:', error));
  }
</script>
</body>
</html>
