<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$defaultTime = (new DateTimeImmutable('now'))->modify('+1 hour')->setTime(
    (int)(new DateTimeImmutable('now'))->modify('+1 hour')->format('H'),
    0
)->format('Y-m-d\TH:i');
$csrf = (string)($csrf_token ?? \App\Core\CSRF::getToken());
$availableTables = array_values(array_filter($tables ?? [], static fn(array $table): bool =>
    in_array((string)($table['status'] ?? ''), ['available', 'reserved'], true)
));
?>
<style>
  .res-page { padding:24px; }
  .res-hero { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
  .res-kicker { color:#8794a7; text-transform:uppercase; letter-spacing:.12em; font-size:.67rem; font-weight:850; }
  .res-title { color:#172033; font-size:1.65rem; letter-spacing:-.04em; font-weight:850; margin:4px 0 6px; }
  .res-subtitle { color:#718096; margin:0; font-size:.88rem; }
  .res-card { border:1px solid #e4eaf2; border-radius:16px; overflow:hidden; background:#fff; box-shadow:0 3px 12px rgba(15,23,42,.025); }
  .res-card + .res-card { margin-top:20px; }
  .res-card-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:17px 20px; border-bottom:1px solid #edf1f6; }
  .res-card-body { padding:20px; }
  .res-section-title { color:#172033; font-size:1rem; font-weight:800; margin:0 0 4px; }
  .res-help { color:#8895a7; font-size:.76rem; margin:0; }
  .res-icon { display:grid; place-items:center; flex:0 0 40px; width:40px; height:40px; border-radius:12px; color:#2563eb; background:#eff6ff; font-size:1.1rem; }
  .res-metric { display:flex; align-items:center; gap:13px; background:#fff; border:1px solid #e4eaf2; border-radius:14px; padding:14px 16px; }
  .res-metric strong { display:block; font-size:1.4rem; line-height:1; color:#172033; }
  .res-metric > div > span { display:block; margin-top:5px; color:#718096; font-size:.75rem; }
  .res-metric > .res-icon {
    display:grid; place-items:center; margin:0; padding:0;
    width:40px; height:40px; flex:0 0 40px; line-height:1;
    color:#2563eb; background:#eff6ff; font-size:1.1rem;
  }
  .res-metric > .res-icon > .bi { display:block; line-height:1; font-size:1.1rem; }
  .res-list-empty { padding:30px 18px; text-align:center; color:#718096; }
  .res-list-empty i { display:block; font-size:1.7rem; margin-bottom:7px; color:#a6b2c2; }
  .res-status { display:inline-flex; align-items:center; padding:5px 8px; border-radius:999px; font-size:.68rem; font-weight:800; text-transform:capitalize; background:#f1f5f9; color:#475569; }
  .res-status.booked,.res-status.waiting { color:#92400e; background:#fff4cf; }
  .res-status.seated { color:#047857; background:#dcfce7; }
  .res-actions { min-width:180px; }
  .res-actions form { display:inline; }
  .res-table { vertical-align:middle; }
  .res-table td { padding:13px 12px; }
  .res-table thead th { background:#f8fafc; }
  .res-inline-table { min-width:145px; max-width:190px; }
  .res-inline-table .form-select { min-height:34px; font-size:.75rem; padding-top:5px; padding-bottom:5px; }
  @media(max-width:850px) { .res-page { padding:16px; } .res-card-body { padding:15px; } .res-card-head { padding:15px; } }
</style>

<div class="res-page">
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="res-hero">
    <div>
      <div class="res-kicker">Front-of-house operations</div>
      <h1 class="res-title"><i class="bi bi-calendar2-check text-primary me-2"></i>Reservations &amp; Waiting List</h1>
      <p class="res-subtitle">Plan upcoming bookings, organize walk-ins, and seat guests from one place.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>/tables" class="btn btn-outline-secondary"><i class="bi bi-grid-3x3-gap me-1"></i> Floor Plan</a>
      <a href="<?= BASE_URL ?>/pos" class="btn btn-primary"><i class="bi bi-display me-1"></i> POS Terminal</a>
    </div>
  </section>

  <?php if (empty($schema_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3" role="alert">
      <div class="d-flex align-items-start gap-3">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>
          <strong class="d-block mb-1">One-time reservations update required</strong>
          <div class="small">This adds two new tables for reservations and the waiting list. It does not change existing orders, restaurant records, or sales history.</div>
        </div>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/reservations/migrate">
        <?= \App\Core\CSRF::formField() ?>
        <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-database-gear me-1"></i> Apply Reservations Update</button>
      </form>
    </div>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="res-metric"><span class="res-icon"><i class="bi bi-calendar-event"></i></span><div><strong><?= count(array_filter($reservations, static fn(array $r): bool => $r['status'] === 'booked')) ?></strong><span>Booked reservations</span></div></div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="res-metric"><span class="res-icon"><i class="bi bi-people"></i></span><div><strong><?= count($waitlist) ?></strong><span>Guests waiting</span></div></div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="res-metric"><span class="res-icon"><i class="bi bi-grid"></i></span><div><strong><?= count($availableTables) ?></strong><span>Available or reserved tables</span></div></div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="res-metric"><span class="res-icon"><i class="bi bi-clock-history"></i></span><div><strong><?= count(array_filter($reservations, static fn(array $r): bool => $r['status'] === 'seated')) ?></strong><span>Seated reservations in view</span></div></div>
      </div>
    </div>

    <div class="row g-3 align-items-stretch">
      <div class="col-12 col-xl-7">
        <section class="res-card h-100">
          <div class="res-card-head">
            <div class="d-flex align-items-center gap-3">
              <span class="res-icon"><i class="bi bi-calendar-plus"></i></span>
              <div><h2 class="res-section-title">New reservation</h2><p class="res-help">Bookings for the next 14 days.</p></div>
            </div>
          </div>
          <div class="res-card-body">
            <form method="POST" action="<?= BASE_URL ?>/reservations/create">
              <?= \App\Core\CSRF::formField() ?>
              <div class="row g-3">
                <div class="col-12 col-md-7">
                  <label class="form-label fw-semibold" for="reservationCustomer">Customer name *</label>
                  <input class="form-control" id="reservationCustomer" name="customer_name" maxlength="120" required>
                </div>
                <div class="col-12 col-md-5">
                  <label class="form-label fw-semibold" for="reservationPhone">Phone</label>
                  <input class="form-control" id="reservationPhone" name="phone" maxlength="30" autocomplete="tel">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold" for="reservationTime">Date &amp; time *</label>
                  <input class="form-control" type="datetime-local" id="reservationTime" name="reservation_at" min="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($defaultTime, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-semibold" for="reservationParty">Guests *</label>
                  <input class="form-control" type="number" id="reservationParty" name="party_size" min="1" max="100" value="2" required>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-semibold" for="reservationDuration">Duration</label>
                  <select class="form-select" id="reservationDuration" name="duration_minutes">
                    <option value="60">60 min</option><option value="90" selected>90 min</option><option value="120">2 hours</option><option value="180">3 hours</option><option value="240">4 hours</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold" for="reservationTable">Assign table <span class="text-secondary fw-normal">(optional)</span></label>
                  <select class="form-select" id="reservationTable" name="table_id">
                    <option value="">Assign later</option>
                    <?php foreach ($tables as $table): ?>
                      <?php if ((int)$table['capacity'] >= 1 && in_array($table['status'], ['available','reserved'], true)): ?>
                        <option value="<?= (int)$table['id'] ?>"><?= htmlspecialchars('#' . $table['table_number'] . ' · ' . $table['name'] . ' · ' . $table['capacity'] . ' seats', ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <div class="res-help mt-1">The system checks table capacity and overlapping reservations.</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold" for="reservationNotes">Notes / special requests</label>
                  <textarea class="form-control" id="reservationNotes" name="notes" rows="2" maxlength="500" placeholder="High chair, birthday, window seat..."></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary px-4"><i class="bi bi-calendar-check me-1"></i> Save reservation</button>
                </div>
              </div>
            </form>
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-5">
        <section class="res-card h-100">
          <div class="res-card-head">
            <div class="d-flex align-items-center gap-3">
              <span class="res-icon"><i class="bi bi-person-lines-fill"></i></span>
              <div><h2 class="res-section-title">Add walk-in guest</h2><p class="res-help">Guests waiting for the next available table.</p></div>
            </div>
          </div>
          <div class="res-card-body">
            <form method="POST" action="<?= BASE_URL ?>/reservations/waitlist/create">
              <?= \App\Core\CSRF::formField() ?>
              <div class="mb-3"><label class="form-label fw-semibold" for="waitCustomer">Customer name *</label><input class="form-control" id="waitCustomer" name="customer_name" maxlength="120" required></div>
              <div class="mb-3"><label class="form-label fw-semibold" for="waitPhone">Phone</label><input class="form-control" id="waitPhone" name="phone" maxlength="30" autocomplete="tel"></div>
              <div class="row g-3">
                <div class="col-6"><label class="form-label fw-semibold" for="waitParty">Guests *</label><input class="form-control" id="waitParty" type="number" name="party_size" min="1" max="100" value="2" required></div>
                <div class="col-6"><label class="form-label fw-semibold" for="waitTime">Est. wait (min)</label><input class="form-control" id="waitTime" type="number" name="estimated_wait_minutes" min="0" max="360" value="15"></div>
              </div>
              <div class="mt-3 mb-3"><label class="form-label fw-semibold" for="waitNotes">Notes</label><input class="form-control" id="waitNotes" name="notes" maxlength="500" placeholder="Stroller, accessibility needs..."></div>
              <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-person-plus me-1"></i> Add to waitlist</button></div>
            </form>
          </div>
        </section>
      </div>
    </div>

    <section class="res-card mt-4">
      <div class="res-card-head">
        <div class="d-flex align-items-center gap-3"><span class="res-icon"><i class="bi bi-calendar2-week"></i></span><div><h2 class="res-section-title">Upcoming reservations</h2><p class="res-help">Bookings in the previous 24 hours and next 14 days. Seat, cancel, or mark no-show.</p></div></div>
      </div>
      <?php if (empty($reservations)): ?>
        <div class="res-list-empty"><i class="bi bi-calendar-x"></i>No reservations in this period yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table res-table table-hover mb-0">
            <thead><tr><th>When</th><th>Guest</th><th>Party</th><th>Table</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
              <tr>
                <td class="text-nowrap"><strong><?= htmlspecialchars(date('M j, g:i A', strtotime((string)$reservation['reservation_at'])), ENT_QUOTES, 'UTF-8') ?></strong><div class="res-help"><?= (int)$reservation['duration_minutes'] ?> minutes</div></td>
                <td><strong><?= htmlspecialchars((string)$reservation['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if (!empty($reservation['phone'])): ?><div class="res-help"><?= htmlspecialchars((string)$reservation['phone'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if (!empty($reservation['notes'])): ?><div class="res-help"><?= htmlspecialchars((string)$reservation['notes'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?></td>
                <td><?= (int)$reservation['party_size'] ?> guests</td>
                <td><?= !empty($reservation['table_number']) ? htmlspecialchars('#' . $reservation['table_number'] . ' · ' . $reservation['table_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-secondary">Unassigned</span>' ?></td>
                <td><span class="res-status <?= htmlspecialchars((string)$reservation['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(str_replace('_',' ',(string)$reservation['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="res-actions">
                  <?php if ($reservation['status'] === 'booked'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/reservations/action" class="d-flex flex-column gap-2">
                      <?= \App\Core\CSRF::formField() ?>
                      <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                      <select class="form-select form-select-sm" name="table_id" aria-label="Table for <?= htmlspecialchars((string)$reservation['customer_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">Choose table to seat</option>
                        <?php foreach ($tables as $table): if ((int)$table['capacity'] < (int)$reservation['party_size'] || !in_array($table['status'], ['available','reserved'], true)) continue; ?>
                          <option value="<?= (int)$table['id'] ?>" <?= (int)($reservation['table_id'] ?? 0) === (int)$table['id'] ? 'selected' : '' ?>>#<?= htmlspecialchars((string)$table['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$table['capacity'] ?> seats</option>
                        <?php endforeach; ?>
                      </select>
                      <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-success btn-sm" name="status" value="seated" type="submit">Seat</button>
                        <button class="btn btn-outline-secondary btn-sm" name="status" value="no_show" type="submit">No-show</button>
                        <button class="btn btn-outline-danger btn-sm" name="status" value="cancelled" type="submit">Cancel</button>
                      </div>
                    </form>
                  <?php else: ?><span class="text-secondary small">No actions</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="res-card">
      <div class="res-card-head">
        <div class="d-flex align-items-center gap-3"><span class="res-icon"><i class="bi bi-people"></i></span><div><h2 class="res-section-title">Live waiting list</h2><p class="res-help">Guests are shown in arrival order. Seat a guest and assign an available table.</p></div></div>
        <span class="res-status waiting"><?= count($waitlist) ?> waiting</span>
      </div>
      <?php if (empty($waitlist)): ?>
        <div class="res-list-empty"><i class="bi bi-person-check"></i>There are no guests waiting.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table res-table table-hover mb-0">
            <thead><tr><th>Queue</th><th>Guest</th><th>Party</th><th>Wait estimate</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($waitlist as $index => $entry): ?>
              <tr>
                <td><span class="badge text-bg-light border">#<?= $index + 1 ?></span><div class="res-help"><?= htmlspecialchars(date('g:i A', strtotime((string)$entry['created_at'])), ENT_QUOTES, 'UTF-8') ?> arrival</div></td>
                <td><strong><?= htmlspecialchars((string)$entry['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if (!empty($entry['phone'])): ?><div class="res-help"><?= htmlspecialchars((string)$entry['phone'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if (!empty($entry['notes'])): ?><div class="res-help"><?= htmlspecialchars((string)$entry['notes'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?></td>
                <td><?= (int)$entry['party_size'] ?> guests</td>
                <td><?= (int)$entry['estimated_wait_minutes'] > 0 ? (int)$entry['estimated_wait_minutes'] . ' min' : 'Not set' ?></td>
                <td class="res-actions">
                  <form method="POST" action="<?= BASE_URL ?>/reservations/waitlist/action" class="d-flex flex-wrap align-items-center gap-2">
                    <?= \App\Core\CSRF::formField() ?>
                    <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
                    <select class="form-select form-select-sm res-inline-table" name="table_id" aria-label="Table to seat <?= htmlspecialchars((string)$entry['customer_name'], ENT_QUOTES, 'UTF-8') ?>">
                      <option value="">Choose table</option>
                      <?php foreach ($tables as $table): if ((int)$table['capacity'] < (int)$entry['party_size'] || !in_array($table['status'], ['available','reserved'], true)) continue; ?>
                        <option value="<?= (int)$table['id'] ?>">#<?= htmlspecialchars((string)$table['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$table['capacity'] ?> seats</option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success btn-sm" name="status" value="seated" type="submit">Seat</button>
                    <button class="btn btn-outline-secondary btn-sm" name="status" value="left" type="submit">Left</button>
                    <button class="btn btn-outline-danger btn-sm" name="status" value="cancelled" type="submit">Cancel</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
