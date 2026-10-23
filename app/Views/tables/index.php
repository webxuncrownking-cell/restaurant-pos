<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-grid-3x3-gap text-primary me-2"></i>Tables & Floor Plan
      </h1>
      <p class="text-muted small mb-0">Manage dining floor zones, seating capacity, and real-time occupancy status.</p>
    </div>
    <div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTableModal">
        <i class="bi bi-plus-lg me-1"></i> Add New Table
      </button>
    </div>
  </div>

  <!-- KPI Badges -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-success p-3 rounded-3 text-center">
        <div class="h3 fw-bold mb-0"><?= $stats['available'] ?></div>
        <div class="small fw-semibold text-uppercase">Available</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm bg-danger bg-opacity-10 text-danger p-3 rounded-3 text-center">
        <div class="h3 fw-bold mb-0"><?= $stats['occupied'] ?></div>
        <div class="small fw-semibold text-uppercase">Occupied</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-warning p-3 rounded-3 text-center">
        <div class="h3 fw-bold mb-0"><?= $stats['reserved'] ?></div>
        <div class="small fw-semibold text-uppercase">Reserved</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm bg-info bg-opacity-10 text-info p-3 rounded-3 text-center">
        <div class="h3 fw-bold mb-0"><?= $stats['cleaning'] ?></div>
        <div class="small fw-semibold text-uppercase">Cleaning</div>
      </div>
    </div>
  </div>

  <!-- Floor Zone Filter Tabs -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2 d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small fw-semibold px-2">Floor Zones:</span>
      <a href="<?= BASE_URL ?>/tables" class="btn btn-sm <?= ($currentZone === 'all' || empty($currentZone)) ? 'btn-primary' : 'btn-outline-secondary' ?>">
        All Zones (<?= $stats['total'] ?>)
      </a>
      <?php foreach ($floorZones as $zone): ?>
        <a href="<?= BASE_URL ?>/tables?zone=<?= urlencode($zone) ?>" class="btn btn-sm <?= ($currentZone === $zone) ? 'btn-primary' : 'btn-outline-secondary' ?>">
          <?= htmlspecialchars($zone) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($activeOrders)): ?>
    <section class="card border-0 shadow-sm mb-4">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex align-items-start gap-3 mb-3">
          <span class="d-inline-grid place-items-center rounded-3 bg-primary bg-opacity-10 text-primary p-2"><i class="bi bi-arrow-left-right fs-5"></i></span>
          <div>
            <h2 class="h6 fw-bold mb-1">Transfer Active Dine-in Order</h2>
            <p class="text-muted small mb-0">Move one open kitchen ticket to a free table. The order history records the transfer; the source table is released when it has no other active tickets.</p>
          </div>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/tables/transfer" class="row g-2 align-items-end">
          <input type="hidden" name="_csrf_token" value="<?= \\App\\Core\\CSRF::getToken() ?>">
          <div class="col-12 col-lg-5">
            <label class="form-label small fw-semibold" for="transferOrder">Active order</label>
            <select class="form-select" id="transferOrder" name="order_id" required>
              <option value="">Choose an order...</option>
              <?php foreach ($activeOrders as $activeOrder): ?>
                <option value="<?= (int)$activeOrder['id'] ?>"><?= htmlspecialchars((string)$activeOrder['order_number'], ENT_QUOTES, 'UTF-8') ?> · Table #<?= htmlspecialchars((string)$activeOrder['table_number'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst((string)$activeOrder['order_status']), ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-5">
            <label class="form-label small fw-semibold" for="transferDestination">Destination table</label>
            <select class="form-select" id="transferDestination" name="destination_table_id" required>
              <option value="">Choose an available table...</option>
              <?php foreach (($allTables ?? $tables) as $targetTable): if (($targetTable['status'] ?? '') !== 'available') continue; ?>
                <option value="<?= (int)$targetTable['id'] ?>"><?= htmlspecialchars('#' . $targetTable['table_number'] . ' · ' . $targetTable['name'] . ' · ' . $targetTable['floor_zone'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-primary" <?= empty(array_filter(($allTables ?? $tables), static fn(array $targetTable): bool => ($targetTable['status'] ?? '') === 'available')) ? 'disabled' : '' ?>><i class="bi bi-arrow-left-right me-1"></i> Transfer order</button>
          </div>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <!-- Tables Visual Grid -->
  <?php if (empty($tables)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
      <div class="card-body">
        <i class="bi bi-grid text-muted fs-1 mb-2"></i>
        <h5 class="fw-bold">No Tables Found</h5>
        <p class="text-muted small">No tables registered in this floor zone. Click "Add New Table" to create one.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($tables as $tbl): 
        $statusBg = 'bg-success';
        if ($tbl['status'] === 'occupied') $statusBg = 'bg-danger';
        elseif ($tbl['status'] === 'reserved') $statusBg = 'bg-warning text-dark';
        elseif ($tbl['status'] === 'cleaning') $statusBg = 'bg-info text-dark';
      ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="card border-0 shadow-sm h-100 position-relative">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge <?= $statusBg ?> px-2 py-1 text-uppercase fw-semibold" style="font-size: 0.72rem;">
                  <?= htmlspecialchars($tbl['status']) ?>
                </span>
                <span class="badge bg-light text-secondary border">
                  <i class="bi bi-people-fill me-1"></i><?= (int)$tbl['capacity'] ?> Seats
                </span>
              </div>
              <h5 class="fw-bold mb-1">
                <?= htmlspecialchars($tbl['name']) ?>
                <span class="text-muted fs-6 fw-normal ms-1">#<?= htmlspecialchars($tbl['table_number']) ?></span>
              </h5>
              <div class="small text-muted mb-3">
                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($tbl['floor_zone'] ?? 'Main Dining') ?>
              </div>

              <!-- Quick Status Update Form -->
              <div class="d-flex gap-2 align-items-center pt-2 border-top">
                <form method="POST" action="<?= BASE_URL ?>/tables/status" class="flex-grow-1">
                  <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                  <input type="hidden" name="table_id" value="<?= $tbl['id'] ?>">
                  <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="available" <?= $tbl['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="occupied" <?= $tbl['status'] === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                    <option value="reserved" <?= $tbl['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                    <option value="cleaning" <?= $tbl['status'] === 'cleaning' ? 'selected' : '' ?>>Cleaning</option>
                  </select>
                </form>

                <form method="POST" action="<?= BASE_URL ?>/tables/delete" onsubmit="return confirm('Soft-delete Table <?= htmlspecialchars($tbl['table_number']) ?>?');">
                  <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                  <input type="hidden" name="table_id" value="<?= $tbl['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Table">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Add New Table -->
<div class="modal fade" id="newTableModal" tabindex="-1" aria-labelledby="newTableModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= BASE_URL ?>/tables/create">
        <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="newTableModalLabel">
            <i class="bi bi-plus-circle text-primary me-2"></i>Add Dining Table
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Table Number / Code <span class="text-danger">*</span></label>
            <input type="text" name="table_number" class="form-control" placeholder="e.g. 101, T-12, B-4" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Table Display Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Window Booth 1">
            <div class="form-text">Defaults to 'Table [Number]' if left blank.</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Seating Capacity</label>
              <input type="number" name="capacity" class="form-control" value="4" min="1" max="100" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Initial Status</label>
              <select name="status" class="form-select">
                <option value="available" selected>Available</option>
                <option value="reserved">Reserved</option>
                <option value="occupied">Occupied</option>
                <option value="cleaning">Cleaning</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Floor Zone</label>
            <input type="text" name="floor_zone" class="form-control" list="zonesList" value="Main Dining" required>
            <datalist id="zonesList">
              <?php foreach ($floorZones as $z): ?>
                <option value="<?= htmlspecialchars($z) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Table</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
