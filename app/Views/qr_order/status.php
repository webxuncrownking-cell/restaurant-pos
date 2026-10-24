<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <title>Order Status · <?= htmlspecialchars((string)$table['restaurant_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --ink:#172033; --muted:#68778d; --blue:#2563eb; --line:#e2e9f2; --green:#07834f; }
    * { box-sizing:border-box; }
    body { margin:0; background:#f4f7fb; color:var(--ink); font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    .status-shell { max-width:760px; margin:0 auto; padding:26px 16px 40px; }
    .status-brand { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
    .status-logo { width:48px; height:48px; object-fit:contain; object-position:center; border-radius:13px; border:1px solid var(--line); padding:4px; background:#fff; }
    .status-brand-name { font-size:1.1rem; font-weight:850; letter-spacing:-.03em; overflow-wrap:anywhere; }
    .status-brand-sub { color:var(--muted); font-size:.82rem; margin-top:2px; }
    .status-card { background:#fff; border:1px solid var(--line); border-radius:22px; box-shadow:0 8px 28px rgba(15,23,42,.045); overflow:hidden; }
    .status-head { padding:25px 25px 21px; border-bottom:1px solid #edf1f6; }
    .status-table { display:inline-flex; align-items:center; gap:7px; background:#edf4ff; color:#1d4ed8; border:1px solid #d3e3ff; border-radius:999px; padding:7px 11px; font-size:.76rem; font-weight:850; }
    h1 { margin:15px 0 6px; font-size:1.65rem; line-height:1.2; font-weight:900; letter-spacing:-.045em; }
    .status-order-number { color:var(--muted); font-size:.88rem; overflow-wrap:anywhere; }
    .status-main { padding:23px 25px 25px; }
    .status-current { display:flex; align-items:center; gap:12px; padding:15px; background:#f4f8ff; border:1px solid #dfeaff; border-radius:14px; }
    .status-current-icon { width:44px; height:44px; flex:0 0 44px; display:grid; place-items:center; border-radius:13px; background:#dceaff; color:#1d4ed8; font-size:1.25rem; font-weight:900; }
    .status-current-label { color:var(--muted); font-size:.73rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .status-current-value { margin-top:3px; font-size:1.02rem; font-weight:900; }
    .status-message { color:var(--muted); font-size:.83rem; margin-top:5px; line-height:1.45; }
    .timeline { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin:25px 0 22px; }
    .step { position:relative; text-align:center; min-width:0; }
    .step-circle { width:33px; height:33px; margin:0 auto 8px; display:grid; place-items:center; border-radius:50%; border:2px solid #dbe3ed; background:#fff; color:#8290a3; font-weight:900; }
    .step.complete .step-circle,.step.current .step-circle { border-color:#07834f; color:#fff; background:#07834f; }
    .step.current .step-circle { box-shadow:0 0 0 5px #dff8ec; }
    .step-label { color:#7a879a; font-size:.73rem; font-weight:750; line-height:1.3; }
    .step.complete .step-label,.step.current .step-label { color:#166344; }
    .step-line { height:3px; background:#e4eaf2; margin:0 18px 12px; border-radius:999px; }
    .step-line.complete { background:#20a56d; }
    .status-facts { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:20px; }
    .status-fact { background:#f8fafc; border:1px solid #e9eef5; border-radius:12px; padding:12px 13px; min-width:0; }
    .status-fact-label { color:var(--muted); font-size:.72rem; font-weight:800; margin-bottom:5px; }
    .status-fact-value { font-weight:850; font-size:.9rem; overflow-wrap:anywhere; }
    .payment-badge { display:inline-flex; border-radius:999px; padding:5px 9px; background:#fff3d6; color:#8c5a00; font-size:.72rem; font-weight:850; }
    .payment-badge.paid { background:#dcf9eb; color:#087447; }
    .payment-badge.refunded { background:#fee2e2; color:#991b1b; }
    .status-cancelled { margin-top:18px; padding:14px; border-radius:12px; background:#fff0f0; color:#991b1b; font-weight:750; }
    .status-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
    .status-button { display:inline-flex; justify-content:center; align-items:center; gap:8px; min-height:43px; padding:10px 15px; border:1px solid var(--blue); border-radius:11px; background:var(--blue); color:#fff; font-weight:850; text-decoration:none; }
    .status-button:hover { background:#1d4ed8; color:#fff; }
    .status-button.secondary { background:#fff; color:#475569; border-color:#cbd5e1; }
    .status-button.secondary:hover { background:#f8fafc; color:#1e293b; }
    .status-poll { color:var(--muted); font-size:.74rem; text-align:center; margin-top:16px; }
    .status-footnote { color:var(--muted); font-size:.76rem; line-height:1.5; margin-top:18px; }
    @media(max-width:520px) { .status-shell { padding:15px 10px 25px; } .status-head { padding:20px 18px 17px; } .status-main { padding:18px; } h1 { font-size:1.42rem; } .timeline { gap:3px; } .step-label { font-size:.65rem; } .step-line { margin-left:12px; margin-right:12px; } .status-facts { grid-template-columns:1fr; } .status-actions > a { flex:1 1 100%; } }
  </style>
</head>
<body>
<div class="status-shell">
  <header class="status-brand">
    <?php if (!empty($table['logo_path'])): ?>
      <img class="status-logo" src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$table['logo_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="">
    <?php else: ?>
      <div class="status-logo" style="display:grid;place-items:center;font-size:1.35rem;color:#2563eb">●</div>
    <?php endif; ?>
    <div>
      <div class="status-brand-name"><?= htmlspecialchars((string)$table['restaurant_name'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="status-brand-sub">Live order status</div>
    </div>
  </header>

  <main class="status-card" id="customerOrderStatus"
        data-status-url="<?= htmlspecialchars($statusJsonUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-order-status="<?= htmlspecialchars((string)$statusSnapshot['order_status'], ENT_QUOTES, 'UTF-8') ?>">
    <section class="status-head">
      <span class="status-table">Table <?= htmlspecialchars((string)$order['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$order['table_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <h1 id="statusHeading">Your order is <?= htmlspecialchars((string)$statusSnapshot['status_label'], ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="status-order-number">Order <strong><?= htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    </section>

    <section class="status-main">
      <div class="status-current">
        <div class="status-current-icon" id="statusCurrentIcon" aria-hidden="true">✓</div>
        <div>
          <div class="status-current-label">Current status</div>
          <div class="status-current-value" id="statusCurrentLabel"><?= htmlspecialchars((string)$statusSnapshot['status_label'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="status-message" id="statusMessage">We’ll update this page when the kitchen changes your order status.</div>
        </div>
      </div>

      <div id="statusTimeline" class="timeline" aria-label="Order progress">
        <?php
          $steps = [
              'pending' => ['1', 'Received'],
              'preparing' => ['2', 'Preparing'],
              'ready' => ['3', 'Ready'],
              'served' => ['4', 'Served']
          ];
          $statuses = ['pending', 'preparing', 'ready', 'served', 'completed'];
          $currentIndex = array_search((string)$statusSnapshot['order_status'], $statuses, true);
          if ($currentIndex === false) $currentIndex = -1;
          if ((string)$statusSnapshot['order_status'] === 'completed') $currentIndex = 3;
        ?>
        <?php foreach ($steps as $stepStatus => $step): ?>
          <?php $stepIndex = array_search($stepStatus, array_keys($steps), true); $done = $currentIndex >= $stepIndex; $active = (string)$statusSnapshot['order_status'] === $stepStatus; ?>
          <div class="step <?= $done ? 'complete' : '' ?> <?= $active ? 'current' : '' ?>" data-step="<?= htmlspecialchars($stepStatus, ENT_QUOTES, 'UTF-8') ?>">
            <div class="step-circle"><?= $done ? '✓' : $step[0] ?></div>
            <div class="step-label"><?= htmlspecialchars($step[1], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="status-facts">
        <div class="status-fact">
          <div class="status-fact-label">Payment</div>
          <div class="status-fact-value"><span id="statusPayment" class="payment-badge <?= $statusSnapshot['payment_status'] === 'paid' ? 'paid' : ($statusSnapshot['payment_status'] === 'refunded' ? 'refunded' : '') ?>"><?= htmlspecialchars((string)$statusSnapshot['payment_label'], ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
        <div class="status-fact">
          <div class="status-fact-label">Order total</div>
          <div class="status-fact-value" id="statusTotal">₱<?= number_format((float)$statusSnapshot['total_amount'], 2) ?></div>
        </div>
      </div>

      <div id="statusCancelled" class="status-cancelled" <?= $statusSnapshot['order_status'] === 'cancelled' ? '' : 'hidden' ?>>This order was cancelled. Please ask restaurant staff if you need assistance.</div>

      <div class="status-actions">
        <a class="status-button secondary" href="<?= htmlspecialchars($menuUrl, ENT_QUOTES, 'UTF-8') ?>">Back to menu</a>
        <button class="status-button" type="button" id="refreshStatus">Refresh status</button>
      </div>
      <div class="status-poll" id="statusLastChecked" aria-live="polite">Status checked just now · Updates automatically</div>
      <p class="status-footnote mb-0">Your order remains unpaid until you pay the cashier. Kitchen status and payment status are tracked separately.</p>
    </section>
  </main>
</div>
<script>
(() => {
  const root = document.getElementById('customerOrderStatus');
  const url = root.dataset.statusUrl;
  const labels = {
    pending:'Order received',
    preparing:'Preparing your order',
    ready:'Ready for pickup / service',
    served:'Served',
    completed:'Completed',
    cancelled:'Cancelled'
  };
  const messages = {
    pending:'Your order has reached the kitchen queue.',
    preparing:'The kitchen is preparing your order now.',
    ready:'Your order is ready. Please wait for a team member to serve it.',
    served:'Your order has been served. Enjoy your meal!',
    completed:'Your order is complete. Thank you for dining with us!',
    cancelled:'This order was cancelled. Please ask restaurant staff for assistance.'
  };
  const steps = ['pending','preparing','ready','served'];
  const currency = value => '₱' + Number(value || 0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  let inFlight = false;
  let terminal = false;

  function renderStatus(status) {
    const state = status.order_status || 'pending';
    const label = status.status_label || labels[state] || labels.pending;
    root.dataset.orderStatus = state;
    document.getElementById('statusHeading').textContent = 'Your order is ' + label;
    document.getElementById('statusCurrentLabel').textContent = label;
    document.getElementById('statusMessage').textContent = messages[state] || messages.pending;
    document.getElementById('statusCurrentIcon').textContent = state === 'cancelled' ? '!' : (state === 'preparing' ? '…' : (state === 'ready' ? '✓' : '✓'));
    document.getElementById('statusCancelled').hidden = state !== 'cancelled';

    const currentIndex = state === 'completed' ? 3 : steps.indexOf(state);
    document.querySelectorAll('[data-step]').forEach(step => {
      const index = steps.indexOf(step.dataset.step);
      const done = currentIndex >= 0 && index <= currentIndex;
      step.classList.toggle('complete', done);
      step.classList.toggle('current', state === step.dataset.step);
      step.querySelector('.step-circle').textContent = done ? '✓' : String(index + 1);
    });

    const payment = document.getElementById('statusPayment');
    payment.textContent = status.payment_label || 'Payment status unavailable';
    payment.classList.toggle('paid', status.payment_status === 'paid');
    payment.classList.toggle('refunded', status.payment_status === 'refunded');
    document.getElementById('statusTotal').textContent = currency(status.total_amount);
    document.getElementById('statusLastChecked').textContent = 'Status checked just now' + (terminal ? '' : ' · Updates automatically');
    terminal = Boolean(status.is_terminal);
  }

  async function refreshStatus() {
    if (inFlight) return;
    inFlight = true;
    try {
      const response = await fetch(url, { method:'GET', cache:'no-store', headers:{'Accept':'application/json'} });
      if (!response.ok) throw new Error('Status request failed');
      const payload = await response.json();
      if (!payload.ok || !payload.status) throw new Error('Status response unavailable');
      renderStatus(payload.status);
    } catch (error) {
      document.getElementById('statusLastChecked').textContent = 'Unable to refresh right now · Will retry automatically';
    } finally {
      inFlight = false;
    }
  }

  document.getElementById('refreshStatus').addEventListener('click', refreshStatus);
  refreshStatus();
  window.setInterval(() => { if (!terminal) refreshStatus(); }, 8000);
})();
</script>
</body>
</html>
