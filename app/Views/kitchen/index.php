<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$statusLabels = [
  'pending' => ['New order', 'status-new', 'bi-bell'],
  'preparing' => ['Preparing', 'status-cooking', 'bi-fire'],
  'ready' => ['Ready', 'status-ready', 'bi-check2-circle'],
  'served' => ['Served', 'status-served', 'bi-bag-check']
];
$filterLabels = ['all' => 'All tickets', 'pending' => 'New', 'preparing' => 'Preparing', 'ready' => 'Ready', 'served' => 'Served'];
$kitchenStations = [];
foreach ($tickets as $stationTicket) {
  foreach (($stationTicket['items'] ?? []) as $stationItem) {
    $stationName = trim((string)($stationItem['kitchen_station'] ?? 'Hot Kitchen')) ?: 'Hot Kitchen';
    $kitchenStations[$stationName] = $stationName;
  }
}
ksort($kitchenStations, SORT_NATURAL | SORT_FLAG_CASE);
?>
<style>
  .kitchen-wrap { padding:24px; }
  .kitchen-hero { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:22px; }
  .kitchen-eyebrow { color:#8491a5; text-transform:uppercase; letter-spacing:.13em; font-size:.68rem; font-weight:800; }
  .kitchen-heading { font-size:1.7rem; font-weight:850; letter-spacing:-.04em; color:#172033; margin:3px 0 5px; }
  .kitchen-subtitle { color:#718096; margin:0; font-size:.88rem; }
  .kitchen-pos-terminal {
    color:#6c757d;
    background-color:#fff;
    border:1px solid #6c757d;
    transition: color .15s ease-in-out, background-color .15s ease-in-out, border-color .15s ease-in-out, box-shadow .15s ease-in-out;
  }
  .kitchen-pos-terminal:hover {
    color:#fff;
    background-color:#6c757d;
    border-color:#6c757d;
  }
  .kitchen-pos-terminal:focus-visible {
    color:#fff;
    background-color:#6c757d;
    border-color:#6c757d;
    box-shadow:0 0 0 .2rem rgba(108,117,125,.28);
    outline:0;
  }
  .kitchen-pos-terminal:active {
    color:#fff;
    background-color:#565e64;
    border-color:#51585e;
  }
  .kitchen-live-pill { display:inline-flex; align-items:center; gap:7px; border:1px solid #b9eed6; border-radius:999px; padding:7px 11px; color:#08794e; background:#eafaf2; font-weight:750; font-size:.74rem; white-space:nowrap; }
  .kitchen-live-dot { width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 0 4px rgba(16,185,129,.12); }
  .kitchen-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:13px; margin-bottom:20px; }
  .kitchen-stat { border:1px solid #e4eaf2; border-radius:15px; background:#fff; padding:16px 18px; box-shadow:0 3px 12px rgba(15,23,42,.025); }
  .kitchen-stat-label { display:flex; align-items:center; justify-content:space-between; color:#6d7a8d; font-size:.76rem; font-weight:750; }
  .kitchen-stat-value { font-size:1.85rem; font-weight:850; letter-spacing:-.055em; margin-top:7px; line-height:1; color:#172033; }
  .kitchen-stat-note { color:#92a0b2; font-size:.7rem; margin-top:7px; }
  .kitchen-stat-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:11px; background:#f1f5f9; color:#64748b; font-size:1rem; }
  .kitchen-stat.new .kitchen-stat-icon { color:#b45309; background:#fff5d8; }
  .kitchen-stat.cooking .kitchen-stat-icon { color:#1d4ed8; background:#eaf2ff; }
  .kitchen-stat.ready .kitchen-stat-icon { color:#047857; background:#e5fbf1; }
  .kitchen-stat.delay .kitchen-stat-icon { color:#b91c1c; background:#fff0f0; }
  .kitchen-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; padding:12px 14px; margin-bottom:17px; background:#fff; border:1px solid #e4eaf2; border-radius:14px; }
  .kitchen-filter-group { display:flex; flex-wrap:wrap; gap:6px; }
  .kitchen-filter { border:1px solid transparent; background:transparent; padding:8px 11px; color:#64748b; border-radius:9px; font-size:.77rem; font-weight:750; }
  .kitchen-filter:hover { background:#f4f7fb; color:#172033; }
  .kitchen-filter.active { color:#1d4ed8; border-color:#d9e7ff; background:#edf4ff; }
  .kitchen-station-filters { width:100%; display:flex; align-items:center; flex-wrap:wrap; gap:7px; padding:10px 12px; margin-top:10px; border:1px solid #e5eaf2; border-radius:12px; background:#f8fafc; }
  .kitchen-station-filter-label { margin-right:4px; color:#8491a5; font-size:.68rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
  .kitchen-filter.kitchen-station-filter { padding:7px 11px; border:1px solid #e0e7f0; border-radius:8px; background:#fff; color:#52627a; font-size:.75rem; font-weight:700; line-height:1.2; }
  .kitchen-filter.kitchen-station-filter:hover { border-color:#b8c9e2; background:#f1f6fc; color:#24344d; }
  .kitchen-filter.kitchen-station-filter.active { border-color:#2563eb; background:#2563eb; color:#fff; }
  @media (max-width:575px) { .kitchen-station-filters { gap:6px; padding:9px; } .kitchen-station-filter-label { width:100%; margin:0 0 2px; } .kitchen-filter.kitchen-station-filter { padding:7px 9px; } }

  .kitchen-refresh-note { font-size:.72rem; color:#8793a5; display:flex; align-items:center; gap:7px; }
  .kitchen-board { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px; align-items:start; }
  .kitchen-ticket { min-width:0; overflow:hidden; border:1px solid #dfe6ef; border-radius:16px; background:#fff; box-shadow:0 4px 16px rgba(15,23,42,.035); }
  .kitchen-ticket.is-delayed { border-color:#f5b4a9; box-shadow:0 4px 18px rgba(185,28,28,.075); }
  .kitchen-ticket-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; padding:16px 16px 13px; border-bottom:1px solid #edf1f6; }
  .kitchen-ticket-kicker { font-size:.65rem; color:#8794a7; letter-spacing:.1em; font-weight:850; text-transform:uppercase; }
  .kitchen-ticket-number { font-size:1.05rem; color:#172033; font-weight:850; letter-spacing:-.025em; overflow-wrap:anywhere; }
  .kitchen-ticket-meta { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:8px; }
  .kitchen-chip { display:inline-flex; align-items:center; gap:5px; border:1px solid #e1e8f0; background:#f8fafc; border-radius:7px; padding:4px 7px; font-size:.68rem; color:#64748b; font-weight:750; }
  .kitchen-status { display:inline-flex; align-items:center; gap:5px; border-radius:8px; padding:6px 8px; font-size:.68rem; line-height:1.2; font-weight:800; white-space:nowrap; }
  .status-new { color:#92400e; background:#fff1c6; }
  .status-cooking { color:#1d4ed8; background:#eaf2ff; }
  .status-ready { color:#047857; background:#dff9ed; }
  .status-served { color:#5b6474; background:#eef1f5; }
  .kitchen-elapsed { color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; font-size:.72rem; font-weight:850; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .is-delayed .kitchen-elapsed { color:#b91c1c; background:#fff1f0; border-color:#f6c1bc; }
  .kitchen-delay-badge { display:none; color:#b91c1c; font-size:.64rem; font-weight:850; text-transform:uppercase; letter-spacing:.04em; margin-top:6px; }
  .kitchen-ticket.is-delayed .kitchen-delay-badge { display:block; }
  .kitchen-ticket-items { padding:8px 16px 14px; }
  .kitchen-item { padding:11px 0; border-bottom:1px dashed #e7edf4; }
  .kitchen-item:last-child { border-bottom:0; }
  .kitchen-item-line { display:flex; align-items:flex-start; gap:9px; }
  .kitchen-qty { display:grid; place-items:center; min-width:29px; min-height:29px; border-radius:9px; background:#f0f5fb; color:#334155; font-weight:850; font-size:.76rem; padding:3px; }
  .kitchen-item-name { color:#243047; font-size:.84rem; font-weight:800; line-height:1.35; overflow-wrap:anywhere; }
  .kitchen-item-variant { color:#728096; font-weight:650; font-size:.74rem; margin-top:3px; }
  .kitchen-item-addon { font-size:.72rem; color:#5c6f8b; margin-top:5px; }
  .kitchen-item-status-row { display:flex; flex-wrap:wrap; align-items:center; gap:7px; margin:8px 0 0 38px; }
  .kitchen-item-status { display:inline-flex; align-items:center; border-radius:7px; padding:4px 7px; font-size:.65rem; font-weight:800; background:#f1f5f9; color:#64748b; }
  .kitchen-item-status.preparing { background:#eaf2ff; color:#1d4ed8; }
  .kitchen-item-status.ready { background:#dff9ed; color:#047857; }
  .kitchen-item-status.served { background:#eef1f5; color:#5b6474; }
  .kitchen-item-status-row .btn { font-size:.67rem; padding:4px 7px; }
  .kitchen-item-note { margin:7px 0 0 38px; color:#8a4b07; font-size:.75rem; background:#fff8e8; border-radius:8px; border:1px solid #fae3b0; padding:7px 9px; overflow-wrap:anywhere; }
  .kitchen-ticket-note { margin:0 16px 14px; border-left:3px solid #d8e3f1; padding:3px 0 3px 10px; color:#68768a; font-size:.76rem; overflow-wrap:anywhere; }
  .kitchen-ticket-actions { display:flex; flex-wrap:wrap; gap:8px; padding:13px 16px 16px; border-top:1px solid #edf1f6; background:#fbfcfe; }
  .kitchen-ticket-actions .btn { flex:1 1 auto; font-size:.77rem; padding:9px 10px; min-width:100px; }
  .kitchen-empty { text-align:center; padding:60px 20px; border:1px dashed #cbd5e1; border-radius:16px; background:#fff; grid-column:1/-1; }
  .kitchen-empty-icon { width:56px; height:56px; margin:0 auto 12px; display:grid; place-items:center; border-radius:17px; color:#64748b; background:#f1f5f9; font-size:1.55rem; }
  .kitchen-ticket[hidden] { display:none!important; }
  @media (max-width:1200px) { .kitchen-board { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:850px) { .kitchen-wrap { padding:18px; } .kitchen-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .kitchen-board { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575px) { .kitchen-wrap { padding:14px; } .kitchen-heading { font-size:1.45rem; } .kitchen-stats { gap:8px; } .kitchen-stat { padding:13px; } .kitchen-board { grid-template-columns:1fr; } .kitchen-toolbar { align-items:flex-start; flex-direction:column; } }
  @media print {
    .app-sidebar,.app-sidebar-backdrop,.app-topbar,.kitchen-no-print,.app-main > footer { display:none!important; }
    .app-main { margin:0!important; }
    .kitchen-wrap { padding:0!important; }
    .kitchen-ticket { box-shadow:none!important; break-inside:avoid; }
    body.printing-ticket .kitchen-ticket:not(.print-active) { display:none!important; }
  }
</style>

<div class="kitchen-wrap">
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show border-0 shadow-sm kitchen-no-print" role="alert">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="kitchen-hero kitchen-no-print">
    <div>
      <div class="kitchen-eyebrow">Live operations</div>
      <h1 class="kitchen-heading"><i class="bi bi-fire me-2 text-primary"></i>Kitchen Display</h1>
      <p class="kitchen-subtitle">Track incoming tickets, preparation time, ready orders, and service handoff.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="kitchen-live-pill" id="kitchenLiveState"><span class="kitchen-live-dot"></span> Live updates on</span>
      <a class="btn kitchen-pos-terminal" href="<?= BASE_URL ?>/pos"><i class="bi bi-display me-1"></i> POS Terminal</a>
      <button class="btn btn-primary" type="button" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
    </div>
  </section>

  <section class="kitchen-stats kitchen-no-print">
    <div class="kitchen-stat new">
      <div class="kitchen-stat-label"><span>New orders</span><span class="kitchen-stat-icon"><i class="bi bi-bell"></i></span></div>
      <div class="kitchen-stat-value"><?= (int)$counts['pending'] ?></div>
      <div class="kitchen-stat-note">Waiting for kitchen start</div>
    </div>
    <div class="kitchen-stat cooking">
      <div class="kitchen-stat-label"><span>Preparing</span><span class="kitchen-stat-icon"><i class="bi bi-fire"></i></span></div>
      <div class="kitchen-stat-value"><?= (int)$counts['preparing'] ?></div>
      <div class="kitchen-stat-note">Currently in preparation</div>
    </div>
    <div class="kitchen-stat ready">
      <div class="kitchen-stat-label"><span>Ready</span><span class="kitchen-stat-icon"><i class="bi bi-check2-circle"></i></span></div>
      <div class="kitchen-stat-value"><?= (int)$counts['ready'] ?></div>
      <div class="kitchen-stat-note">Awaiting service handoff</div>
    </div>
    <div class="kitchen-stat delay">
      <div class="kitchen-stat-label"><span>Delayed</span><span class="kitchen-stat-icon"><i class="bi bi-alarm"></i></span></div>
      <div class="kitchen-stat-value" id="kitchenDelayedCount"><?= (int)$delayed_count ?></div>
      <div class="kitchen-stat-note">New or cooking for 15+ minutes</div>
    </div>
  </section>

  <section class="kitchen-toolbar kitchen-no-print">
    <div class="kitchen-filter-group" role="group" aria-label="Filter kitchen tickets">
      <?php foreach ($filterLabels as $filter => $label): ?>
        <button type="button" class="kitchen-filter <?= $filter === 'all' ? 'active' : '' ?>" data-kitchen-filter="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
      <?php endforeach; ?>
    </div>
    <div class="kitchen-filter-group kitchen-station-filters" role="group" aria-label="Filter kitchen station">
      <span class="kitchen-station-filter-label">Station:</span>
      <button type="button" class="kitchen-filter kitchen-station-filter active" data-station-filter="all">All Stations</button>
      <?php foreach ($kitchenStations as $stationName): ?>
        <button type="button" class="kitchen-filter kitchen-station-filter" data-station-filter="<?= htmlspecialchars($stationName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stationName, ENT_QUOTES, 'UTF-8') ?></button>
      <?php endforeach; ?>
    </div>
    <div class="kitchen-refresh-note"><i class="bi bi-arrow-repeat"></i> Checks for new tickets every 12 seconds <span aria-hidden="true">·</span> Last checked <span id="kitchenLastChecked">just now</span></div>
  </section>

  <section class="kitchen-board" id="kitchenBoard" data-signature="<?= htmlspecialchars((string)$ticket_signature, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (empty($tickets)): ?>
      <div class="kitchen-empty" id="kitchenEmptyState">
        <div class="kitchen-empty-icon"><i class="bi bi-check2-all"></i></div>
        <h2 class="h5 fw-bold mb-2">Kitchen is clear</h2>
        <p class="text-secondary mb-0">New tickets will appear here automatically when orders arrive.</p>
      </div>
    <?php else: ?>
      <?php foreach ($tickets as $ticket):
        $ticketStatus = (string)$ticket['order_status'];
        $status = $statusLabels[$ticketStatus] ?? ['In progress', 'status-served', 'bi-receipt'];
        $createdEpoch = (int)(strtotime((string)$ticket['created_at']) ?: time());
        $isDelayed = in_array($ticketStatus, ['pending', 'preparing'], true) && (int)$ticket['elapsed_seconds'] >= 900;
        $orderTypeLabels = ['dine_in' => 'Dine-in', 'takeout' => 'Takeaway', 'delivery' => 'Delivery'];
        $typeLabel = $orderTypeLabels[(string)$ticket['order_type']] ?? ucfirst((string)$ticket['order_type']);
        $nextStatus = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'served', 'served' => 'completed'][$ticketStatus] ?? '';
        $nextLabel = ['preparing' => 'Start preparing', 'ready' => 'Mark ready', 'served' => 'Mark served', 'completed' => 'Complete order'][$nextStatus] ?? '';
      ?>
        <article class="kitchen-ticket <?= $isDelayed ? 'is-delayed' : '' ?>" data-ticket-id="<?= (int)$ticket['id'] ?>" data-status="<?= htmlspecialchars($ticketStatus, ENT_QUOTES, 'UTF-8') ?>" data-created-at="<?= $createdEpoch ?>">
          <header class="kitchen-ticket-head">
            <div class="min-w-0">
              <div class="kitchen-ticket-kicker">Ticket #<?= (int)$ticket['id'] ?></div>
              <div class="kitchen-ticket-number"><?= htmlspecialchars((string)$ticket['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="kitchen-ticket-meta">
                <span class="kitchen-chip"><i class="bi bi-shop"></i><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($ticket['order_type'] === 'dine_in' && $ticket['table_number'] !== ''): ?>
                  <span class="kitchen-chip"><i class="bi bi-grid-3x3-gap"></i>Table <?= htmlspecialchars((string)$ticket['table_number'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end">
              <span class="kitchen-status <?= htmlspecialchars($status[1], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars($status[2], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($status[0], ENT_QUOTES, 'UTF-8') ?></span>
              <div class="mt-2"><span class="kitchen-elapsed" data-ticket-elapsed><?= (int)floor((int)$ticket['elapsed_seconds'] / 60) ?>m <?= str_pad((string)((int)$ticket['elapsed_seconds'] % 60), 2, '0', STR_PAD_LEFT) ?>s</span></div>
              <div class="kitchen-delay-badge"><i class="bi bi-exclamation-triangle-fill me-1"></i>Delayed ticket</div>
            </div>
          </header>

          <div class="kitchen-ticket-items">
            <?php foreach ($ticket['items'] as $item):
              $quantityLabel = rtrim(rtrim(number_format((float)$item['quantity'], 2, '.', ''), '0'), '.');
            ?>
              <?php
                $itemStatus = (string)($item['kitchen_status'] ?? 'pending');
                $itemStatusLabels = ['pending' => 'Not started', 'preparing' => 'Preparing', 'ready' => 'Ready', 'served' => 'Served'];
                $itemNextStatus = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'][$itemStatus] ?? '';
                $itemNextLabel = ['preparing' => 'Start', 'ready' => 'Mark ready', 'served' => 'Mark served'][$itemNextStatus] ?? '';
              ?>
              <div class="kitchen-item">
                <div class="kitchen-item-line">
                  <span class="kitchen-qty"><?= htmlspecialchars($quantityLabel === '' ? '0' : $quantityLabel, ENT_QUOTES, 'UTF-8') ?>×</span>
                  <div class="flex-grow-1 min-w-0">
                    <div class="kitchen-item-name"><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="kitchen-item-variant"><strong>Station:</strong> <?= htmlspecialchars(trim((string)($item['kitchen_station'] ?? 'Hot Kitchen')) !== '' ? (string)$item['kitchen_station'] : 'Hot Kitchen', ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($item['variant_name'])): ?>
                      <div class="kitchen-item-variant"><?= htmlspecialchars((string)$item['variant_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['addons'])): ?>
                      <div class="kitchen-item-addon"><i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars((string)$item['addons'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="kitchen-item-status-row" data-item-station="<?= htmlspecialchars(trim((string)($item['kitchen_station'] ?? 'Hot Kitchen')) ?: 'Hot Kitchen', ENT_QUOTES, 'UTF-8') ?>">
                  <span class="kitchen-item-status <?= htmlspecialchars($itemStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($itemStatusLabels[$itemStatus] ?? 'Not started', ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if ($itemNextStatus !== ''): ?>
                    <form method="POST" action="<?= BASE_URL ?>/kitchen/item-status" class="d-inline-flex">
                      <?= \App\Core\CSRF::formField() ?>
                      <input type="hidden" name="order_id" value="<?= (int)$ticket['id'] ?>">
                      <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                      <input type="hidden" name="status" value="<?= htmlspecialchars($itemNextStatus, ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="btn btn-outline-primary"><?= htmlspecialchars($itemNextLabel, ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
                <?php if (!empty($item['special_instructions'])): ?>
                  <div class="kitchen-item-note"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars((string)$item['special_instructions'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($ticket['notes'])): ?>
            <div class="kitchen-ticket-note"><strong>Order note:</strong> <?= nl2br(htmlspecialchars((string)$ticket['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
          <?php endif; ?>

          <div class="kitchen-ticket-actions kitchen-no-print">
            <button type="button" class="btn btn-outline-secondary" onclick="window.printKitchenTicket(<?= (int)$ticket['id'] ?>)"><i class="bi bi-printer me-1"></i> Print</button>
            <?php if ($nextStatus !== ''): ?>
              <form method="POST" action="<?= BASE_URL ?>/kitchen/status" class="flex-grow-1">
                <?= \App\Core\CSRF::formField() ?>
                <input type="hidden" name="order_id" value="<?= (int)$ticket['id'] ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-right-circle me-1"></i><?= htmlspecialchars($nextLabel, ENT_QUOTES, 'UTF-8') ?></button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <div class="kitchen-empty" id="kitchenEmptyState" hidden>
        <div class="kitchen-empty-icon"><i class="bi bi-filter-circle"></i></div>
        <h2 class="h5 fw-bold mb-2">No matching tickets</h2>
        <p class="text-secondary mb-0">Choose another filter to view the kitchen queue.</p>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
(() => {
  const board = document.getElementById('kitchenBoard');
  const countNode = document.getElementById('kitchenDelayedCount');
  const lastChecked = document.getElementById('kitchenLastChecked');
  const liveState = document.getElementById('kitchenLiveState');
  const cards = () => Array.from(document.querySelectorAll('.kitchen-ticket'));
  let activeFilter = 'all';
  let activeStation = 'all';
  let polling = false;

  const formatElapsed = seconds => {
    const minutes = Math.floor(seconds / 60);
    const remainder = String(seconds % 60).padStart(2, '0');
    return minutes + 'm ' + remainder + 's';
  };

  const updateTimers = () => {
    let delayed = 0;
    cards().forEach(card => {
      const createdAt = Number(card.dataset.createdAt || 0);
      const elapsed = Math.max(0, Math.floor(Date.now() / 1000 - createdAt));
      const timer = card.querySelector('[data-ticket-elapsed]');
      if (timer) timer.textContent = formatElapsed(elapsed);
      const isLate = ['pending', 'preparing'].includes(card.dataset.status) && elapsed >= 900;
      card.classList.toggle('is-delayed', isLate);
      if (isLate) delayed++;
    });
    if (countNode) countNode.textContent = String(delayed);
  };

  const applyFilter = () => {
    let visible = 0;
    cards().forEach(card => {
      const statusMatches = activeFilter === 'all' || card.dataset.status === activeFilter;
      let stationMatches = activeStation === 'all';
      let visibleItems = 0;
      card.querySelectorAll('.kitchen-item-status-row').forEach(row => {
        const matches = activeStation === 'all' || (row.dataset.itemStation || 'Hot Kitchen') === activeStation;
        row.closest('.kitchen-item')?.toggleAttribute('hidden', !matches);
        if (matches) visibleItems++;
        stationMatches = stationMatches || matches;
      });
      const shown = statusMatches && stationMatches;
      card.hidden = !shown;
      if (shown) visible++;
    });
    const empty = document.getElementById('kitchenEmptyState');
    if (empty && cards().length > 0) empty.hidden = visible > 0;
  };

  document.querySelectorAll('[data-kitchen-filter]').forEach(button => {
    button.addEventListener('click', () => {
      activeFilter = button.dataset.kitchenFilter || 'all';
      document.querySelectorAll('[data-kitchen-filter]').forEach(item => item.classList.toggle('active', item === button));
      applyFilter();
    });
  });

  document.querySelectorAll('[data-station-filter]').forEach(button => {
    button.addEventListener('click', () => {
      activeStation = button.dataset.stationFilter || 'all';
      document.querySelectorAll('[data-station-filter]').forEach(item => item.classList.toggle('active', item === button));
      applyFilter();
    });
  });

  window.printKitchenTicket = ticketId => {
    const ticket = document.querySelector('[data-ticket-id="' + ticketId + '"]');
    if (!ticket) return;
    ticket.classList.add('print-active');
    document.body.classList.add('printing-ticket');
    const cleanup = () => {
      ticket.classList.remove('print-active');
      document.body.classList.remove('printing-ticket');
      window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup);
    window.print();
  };

  const pollForChanges = async () => {
    if (polling || document.hidden || !board) return;
    polling = true;
    try {
      const response = await fetch('<?= BASE_URL ?>/kitchen/orders.json', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {'Accept': 'application/json'}
      });
      if (!response.ok) throw new Error('Kitchen updates unavailable');
      const data = await response.json();
      if (data.signature && data.signature !== board.dataset.signature) {
        window.location.reload();
        return;
      }
      if (liveState) liveState.innerHTML = '<span class="kitchen-live-dot"></span> Live updates on';
    } catch (error) {
      if (liveState) liveState.innerHTML = '<span class="kitchen-live-dot" style="background:#f59e0b;box-shadow:none"></span> Reconnecting';
    } finally {
      polling = false;
      if (lastChecked) lastChecked.textContent = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    }
  };

  updateTimers();
  applyFilter();
  setInterval(updateTimers, 1000);
  setInterval(pollForChanges, 12000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) pollForChanges(); });
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
