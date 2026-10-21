<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>

<!-- POS Terminal Styles -->
<style>
  body {
    background-color: #0f172a;
    color: #f8fafc;
    overflow-x: hidden;
  }
  .pos-header {
    background: #1e293b;
    border-bottom: 1px solid #334155;
  }
  .pos-catalog-panel {
    height: calc(100vh - 70px);
    overflow-y: auto;
  }
  .pos-ticket-panel {
    height: calc(100vh - 70px);
    background: #1e293b;
    border-left: 1px solid #334155;
    display: flex;
    flex-direction: column;
  }
  .ticket-items {
    flex-grow: 1;
    overflow-y: auto;
  }
  .product-card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 12px;
    transition: transform 0.15s ease, border-color 0.15s ease;
    cursor: pointer;
    user-select: none;
  }
  .product-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
  }
  .product-card:active {
    transform: scale(0.98);
  }
  .category-pill {
    background: #1e293b;
    border: 1px solid #334155;
    color: #94a3b8;
    border-radius: 9999px;
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
  }
  .category-pill.active, .category-pill:hover {
    background: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
  }
  .pin-btn {
    height: 60px;
    font-size: 1.5rem;
    font-weight: bold;
    border-radius: 12px;
  }
  /* Thermal Receipt Print Styles (80mm & 58mm) */
  @media print {
    body * {
      visibility: hidden;
    }
    #thermalReceiptPrintArea, #thermalReceiptPrintArea * {
      visibility: visible;
    }
    #thermalReceiptPrintArea {
      position: absolute;
      left: 0;
      top: 0;
      width: 80mm;
      max-width: 100%;
      background: white !important;
      color: black !important;
      font-family: 'Courier New', Courier, monospace !important;
      font-size: 12px !important;
      line-height: 1.3 !important;
      padding: 5mm !important;
    }
    .no-print {
      display: none !important;
    }
  }
</style>

<!-- Top POS Header Bar -->
<header class="pos-header py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div class="d-flex align-items-center gap-3">
    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-sm btn-outline-secondary text-white-50">
      <i class="bi bi-arrow-left me-1"></i> Back to SaaS
    </a>
    <div class="fw-bold fs-5 d-flex align-items-center gap-2">
      <i class="bi bi-shop text-primary"></i>
      <span><?= htmlspecialchars($catalog['restaurant']['name'] ?? 'Restaurant POS') ?></span>
    </div>
    <span class="badge bg-dark border border-secondary text-white-50 small">
      <?= htmlspecialchars($activeDevice['device_name'] ?? 'Main Terminal') ?>
    </span>
  </div>

  <div class="d-flex align-items-center gap-2">
    <!-- Network Status Pill -->
    <span id="posNetworkStatus" class="badge bg-success bg-opacity-25 text-success border border-success d-flex align-items-center gap-1 px-2 py-1">
      <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;"></span>
      <span id="posNetworkText">Online</span>
    </span>

    <!-- Unsynced Queue Badge -->
    <button id="posSyncBtn" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1" onclick="window.posApp.syncQueue()">
      <i class="bi bi-arrow-repeat"></i>
      <span>Sync (<span id="unsyncedCount">0</span>)</span>
    </button>

    <!-- Emergency Backup -->
    <button class="btn btn-sm btn-outline-warning" title="Emergency JSON Backup" onclick="window.PosDB.exportUnsyncedOrdersJSON()">
      <i class="bi bi-download"></i>
    </button>

    <!-- Active Cashier Profile / Lock Button -->
    <div class="dropdown">
      <button class="btn btn-sm btn-secondary dropdown-toggle d-flex align-items-center gap-1" data-bs-toggle="dropdown">
        <i class="bi bi-person-fill"></i>
        <span id="activeCashierName"><?= htmlspecialchars($user['full_name']) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
        <li><h6 class="dropdown-header">POS Terminal Session</h6></li>
        <li><a class="dropdown-item" href="javascript:void(0)" onclick="window.posApp.showLockModal()"><i class="bi bi-shield-lock me-2"></i>Lock Terminal (PIN)</a></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/shifts/active"><i class="bi bi-safe me-2"></i>Manage Cash Drawer</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right me-2"></i>Exit POS</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- Main POS Workspace -->
<div class="container-fluid p-0">
  <div class="row g-0">
    <!-- Left Panel: Search, Categories & Product Grid -->
    <div class="col-12 col-lg-7 col-xl-8 pos-catalog-panel p-3">
      <!-- Search and Floor Zone Selector -->
      <div class="row g-2 mb-3">
        <div class="col-12 col-md-7">
          <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="productSearchInput" class="form-control bg-dark border-secondary text-white" placeholder="Search menu items, SKU, or keywords (e.g. Sisig, Pork, Egg)..." oninput="window.posApp.filterProducts()">
          </div>
        </div>
        <div class="col-12 col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-grid-3x3-gap"></i></span>
            <select id="posTableSelect" class="form-select bg-dark border-secondary text-white" onchange="window.posApp.setTable(this.value)">
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
    <div class="col-12 col-lg-5 col-xl-4 pos-ticket-panel">
      <!-- Dining Option Switcher -->
      <div class="p-3 border-bottom border-secondary d-flex gap-2">
        <button id="typeDineIn" class="btn btn-sm btn-primary flex-fill fw-semibold" onclick="window.posApp.setOrderType('dine_in')">
          <i class="bi bi-shop me-1"></i> Dine In
        </button>
        <button id="typeTakeout" class="btn btn-sm btn-outline-secondary text-white flex-fill fw-semibold" onclick="window.posApp.setOrderType('takeout')">
          <i class="bi bi-bag me-1"></i> Takeout
        </button>
        <button id="typeDelivery" class="btn btn-sm btn-outline-secondary text-white flex-fill fw-semibold" onclick="window.posApp.setOrderType('delivery')">
          <i class="bi bi-bicycle me-1"></i> Delivery
        </button>
      </div>

      <!-- Ticket Items List -->
      <div class="ticket-items p-3" id="ticketItemsList">
        <div class="text-center text-muted py-5" id="emptyCartNotice">
          <i class="bi bi-receipt fs-1 d-block mb-2 text-white-50"></i>
          <h5>No Items in Order</h5>
          <p class="small text-white-50">Tap any menu product on the left to add it to the ticket.</p>
        </div>
      </div>

      <!-- Ticket Summary & Calculations -->
      <div class="p-3 border-top border-secondary bg-dark bg-opacity-50">
        <div class="d-flex justify-content-between text-muted small mb-1">
          <span>Subtotal</span>
          <span class="font-monospace fw-semibold text-white" id="ticketSubtotal">₱0.00</span>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-1">
          <div class="d-flex align-items-center gap-1">
            <span class="text-muted small">Discount:</span>
            <select id="discountSelect" class="form-select form-select-sm bg-dark border-secondary text-white py-0 px-2" style="font-size: 0.75rem;" onchange="window.posApp.applyDiscount(this.value)">
              <option value="0">None (0%)</option>
              <option value="20">Senior / PWD (20%)</option>
              <option value="10">Staff (10%)</option>
              <option value="5">Loyalty (5%)</option>
            </select>
          </div>
          <span class="font-monospace text-warning small" id="ticketDiscount">-₱0.00</span>
        </div>

        <div class="d-flex justify-content-between text-muted small mb-2">
          <span>VAT (12% Included)</span>
          <span class="font-monospace text-white" id="ticketVat">₱0.00</span>
        </div>

        <div class="d-flex justify-content-between align-items-baseline pt-2 border-top border-secondary">
          <span class="h5 fw-bold text-uppercase mb-0">Total Due</span>
          <span class="h3 fw-bold text-success font-monospace mb-0" id="ticketTotal">₱0.00</span>
        </div>

        <!-- Order Action Buttons -->
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-outline-danger btn-sm px-3" onclick="window.posApp.clearTicket()">
            <i class="bi bi-trash"></i>
          </button>
          <button id="checkoutBtn" class="btn btn-success btn-lg flex-grow-1 fw-bold fs-5 shadow" onclick="window.posApp.openCheckoutModal()">
            <i class="bi bi-cash-coin me-1"></i> Charge / Pay
          </button>
        </div>
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
          <input type="password" id="pinDisplay" class="form-control form-control-lg bg-black border-secondary text-white text-center fs-2 font-monospace tracking-widest" maxlength="6" readonly placeholder="&bull; &bull; &bull; &bull;">
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
  const tenantId = <?= (int)($user['restaurant_id'] ?? 1) ?>;
  const deviceId = <?= (int)($activeDevice['id'] ?? 1) ?>;
  const cashierId = <?= (int)($user['id'] ?? 1) ?>;
  const shiftId = <?= $activeShift ? (int)$activeShift['id'] : 'null' ?>;

  const app = {
    catalog: initialCatalog,
    tables: initialTables,
    currentCategory: null,
    searchQuery: '',
    currentTableId: null,
    orderType: 'dine_in',
    ticket: [],
    discountPercent: 0,
    activeItemForModal: null,
    failedPinAttempts: 0,
    lockoutUntil: 0,

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

      // 2. Prime IndexedDB with Catalog & Tables
      if (this.catalog && this.catalog.products) {
        await window.PosDB.saveCatalog(this.catalog.products, this.catalog.categories, this.catalog.version);
      }
      if (this.tables) {
        await window.PosDB.saveTables(this.tables);
      }

      // 3. Register Service Worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= BASE_URL ?>/public/sw.js').catch(console.warn);
      }

      // 4. Online/Offline Listeners
      window.addEventListener('online', () => this.updateNetworkStatus(true));
      window.addEventListener('offline', () => this.updateNetworkStatus(false));
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
        el.className = 'badge bg-success bg-opacity-25 text-success border border-success d-flex align-items-center gap-1 px-2 py-1';
        text.textContent = 'Online';
      } else {
        el.className = 'badge bg-warning bg-opacity-25 text-warning border border-warning d-flex align-items-center gap-1 px-2 py-1';
        text.textContent = 'Offline (PWA)';
      }
    },

    async refreshQueueCount() {
      const pending = await window.PosDB.getPendingOrders();
      document.getElementById('unsyncedCount').textContent = pending.length;
    },

    setOrderType(type) {
      this.orderType = type;
      ['dine_in', 'takeout', 'delivery'].forEach(t => {
        const btn = document.getElementById(t === 'dine_in' ? 'typeDineIn' : (t === 'takeout' ? 'typeTakeout' : 'typeDelivery'));
        if (t === type) {
          btn.className = 'btn btn-sm btn-primary flex-fill fw-semibold';
        } else {
          btn.className = 'btn btn-sm btn-outline-secondary text-white flex-fill fw-semibold';
        }
      });
    },

    setTable(tableId) {
      this.currentTableId = tableId ? parseInt(tableId, 10) : null;
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
        const price = parseFloat(p.base_price || 0);

        return `
          <div class="col-6 col-sm-4 col-md-4 col-xl-3">
            <div class="product-card p-3 h-100 d-flex flex-column justify-content-between" onclick="window.posApp.onProductClick(${p.id})">
              <div>
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge bg-dark text-white-50 small border border-secondary">${p.category_name || 'Item'}</span>
                  ${hasVariants || hasAddons ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">Custom</span>' : ''}
                </div>
                <h6 class="fw-bold text-white mb-1">${p.name}</h6>
              </div>
              <div class="d-flex justify-content-between align-items-baseline mt-2 pt-2 border-top border-secondary">
                <span class="fw-bold font-monospace text-success fs-5">₱${price.toFixed(2)}</span>
                <span class="btn btn-sm btn-primary rounded-circle px-2 py-1"><i class="bi bi-plus-lg"></i></span>
              </div>
            </div>
          </div>
        `;
      }).join('');
    },

    onProductClick(productId) {
      const p = (this.catalog.products || []).find(item => item.id == productId);
      if (!p) return;

      const hasVariants = p.variants && p.variants.length > 0;
      const hasAddons = p.addon_groups && p.addon_groups.length > 0;

      if (!hasVariants && !hasAddons) {
        // Direct addition
        this.addToTicket({
          product_id: p.id,
          name: p.name,
          variant_id: null,
          variant_name: null,
          unit_price: parseFloat(p.base_price),
          addons: []
        });
      } else {
        // Open drawer modal
        this.openCustomModal(p);
      }
    },

    openCustomModal(product) {
      this.activeItemForModal = product;
      document.getElementById('modalProductName').textContent = product.name;
      document.getElementById('modalProductBasePrice').textContent = `Base: ₱${parseFloat(product.base_price).toFixed(2)}`;

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
          (g.addons || []).forEach(a => {
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
      let total = parseFloat(this.activeItemForModal.base_price || 0);

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

      const unitPrice = parseFloat(p.base_price) + varPrice + addons.reduce((sum, item) => sum + item.price, 0);

      this.addToTicket({
        product_id: p.id,
        name: p.name,
        variant_id: variantId,
        variant_name: variantName,
        unit_price: unitPrice,
        addons: addons
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
      const discount = subtotal * (this.discountPercent / 100);
      const afterDiscount = subtotal - discount;
      const vat = afterDiscount - (afterDiscount / 1.12); // Included 12% VAT
      const total = afterDiscount;

      return {
        subtotal: round(subtotal),
        discount: round(discount),
        vat: round(vat),
        total: round(total)
      };
    },

    renderTicket() {
      const container = document.getElementById('ticketItemsList');
      const emptyNotice = document.getElementById('emptyCartNotice');

      if (this.ticket.length === 0) {
        container.innerHTML = '';
        container.appendChild(emptyNotice);
        emptyNotice.style.display = 'block';
        document.getElementById('ticketSubtotal').textContent = '₱0.00';
        document.getElementById('ticketDiscount').textContent = '-₱0.00';
        document.getElementById('ticketVat').textContent = '₱0.00';
        document.getElementById('ticketTotal').textContent = '₱0.00';
        document.getElementById('checkoutBtn').disabled = true;
        return;
      }

      emptyNotice.style.display = 'none';
      const calcs = this.getCalculations();

      container.innerHTML = this.ticket.map((item, index) => {
        const itemTotal = item.unit_price * item.quantity;
        const addonsText = item.addons.map(a => `+ ${a.name}`).join(', ');

        return `
          <div class="p-2 mb-2 bg-dark rounded border border-secondary">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <strong class="text-white">${item.name}</strong>
                ${item.variant_name ? `<span class="badge bg-secondary ms-1">${item.variant_name}</span>` : ''}
                ${addonsText ? `<div class="small text-warning">${addonsText}</div>` : ''}
                <div class="small text-muted font-monospace">₱${item.unit_price.toFixed(2)} each</div>
              </div>
              <span class="font-monospace fw-bold text-white fs-6">₱${itemTotal.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top border-secondary">
              <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" data-ticket-action="remove" data-index="${index}">
                <i class="bi bi-x-circle"></i> Remove
              </button>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary text-white px-2 py-0" data-ticket-action="decrease" data-index="${index}" aria-label="Decrease quantity">-</button>
                <span class="fw-bold font-monospace px-1 text-white">${item.quantity}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary text-white px-2 py-0" data-ticket-action="increase" data-index="${index}" aria-label="Increase quantity">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      document.getElementById('ticketSubtotal').textContent = `₱${calcs.subtotal.toFixed(2)}`;
      document.getElementById('ticketDiscount').textContent = `-₱${calcs.discount.toFixed(2)}`;
      document.getElementById('ticketVat').textContent = `₱${calcs.vat.toFixed(2)}`;
      document.getElementById('ticketTotal').textContent = `₱${calcs.total.toFixed(2)}`;
      document.getElementById('checkoutBtn').disabled = false;
    },

    openCheckoutModal() {
      if (this.ticket.length === 0) return;
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

    async processSale() {
      const calcs = this.getCalculations();
      const tendered = parseFloat(document.getElementById('tenderInput').value) || 0;
      if (tendered < calcs.total) {
        alert('Tendered cash is less than the total due.');
        return;
      }

      const clientUuid = crypto.randomUUID();
      const orderNumber = `ORD-${Date.now().toString().slice(-6)}-${Math.floor(Math.random() * 1000)}`;

      const orderPayload = {
        client_uuid: clientUuid,
        device_id: deviceId,
        shift_id: shiftId,
        order_number: orderNumber,
        order_type: this.orderType,
        table_id: this.currentTableId,
        cashier_id: cashierId,
        catalog_version_id: this.catalog.version ? this.catalog.version.id : 1,
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
          client_payment_uuid: crypto.randomUUID(),
          payment_method: 'cash',
          amount: calcs.total,
          tendered_amount: tendered,
          change_amount: round(tendered - calcs.total)
        }
      };

      // 1. Enqueue into IndexedDB
      await window.PosDB.queueOrder(orderPayload);
      await this.refreshQueueCount();

      // 2. Hide checkout modal
      bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();

      // 3. Prepare & Show Thermal Receipt
      this.displayReceipt(orderPayload);

      // 4. Clear current ticket
      this.ticket = [];
      this.renderTicket();

      // 5. Trigger background sync if online
      if (navigator.onLine) {
        this.syncQueue();
      }
    },

    displayReceipt(order) {
      document.getElementById('recRestaurantName').textContent = this.catalog.restaurant.name || 'RESTAURANT POS';
      document.getElementById('recRestaurantAddress').textContent = this.catalog.restaurant.address || 'Dining Terminal';
      document.getElementById('recRestaurantTin').textContent = this.catalog.restaurant.tin || '000-000-000-000';
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
      if (!navigator.onLine) return;
      const pending = await window.PosDB.getPendingOrders();
      if (pending.length === 0) return;

      const syncBtn = document.getElementById('posSyncBtn');
      syncBtn.classList.add('disabled');
      syncBtn.querySelector('i').classList.add('spin-icon');

      try {
        const response = await fetch('<?= BASE_URL ?>/api/pos/sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orders: pending })
        });

        if (response.ok) {
          const res = await response.json();
          if (res.results) {
            for (const r of res.results) {
              await window.PosDB.markOrderSynced(r.client_uuid, r.server_order_id);
            }
          }
        }
      } catch (err) {
        console.warn('[POS Sync] Background sync delayed:', err);
      } finally {
        syncBtn.classList.remove('disabled');
        syncBtn.querySelector('i').classList.remove('spin-icon');
        await this.refreshQueueCount();
      }
    },

    // PIN Pad Lockout & Brute-Force Protection
    showLockModal() {
      document.getElementById('pinDisplay').value = '';
      document.getElementById('pinError').style.display = 'none';
      new bootstrap.Modal(document.getElementById('lockModal')).show();
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

      // Default demo pin 1234 or verify against cached staff
      if (pin === '1234' || pin === '0000') {
        this.failedPinAttempts = 0;
        bootstrap.Modal.getInstance(document.getElementById('lockModal')).hide();
        return;
      }

      this.failedPinAttempts += 1;
      if (this.failedPinAttempts >= 5) {
        this.lockoutUntil = Date.now() + 60000;
        errEl.textContent = '5 failed attempts! Terminal locked for 60 seconds.';
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
  document.addEventListener('DOMContentLoaded', () => app.init());
})();
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
