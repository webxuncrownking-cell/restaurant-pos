<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<style>
  .table-qr-page { padding:24px; max-width:1120px; margin:0 auto; }
  .table-qr-card { background:#fff; border:1px solid #e3eaf2; border-radius:18px; box-shadow:0 4px 16px rgba(15,23,42,.04); overflow:hidden; }
  .table-qr-preview { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:28px; background:#f8fafc; border:1px dashed #d7e1ec; border-radius:15px; min-height:310px; }
  #tableQrCanvas { width:224px; height:224px; padding:12px; background:#fff; border:1px solid #e0e7ef; border-radius:12px; display:flex; align-items:center; justify-content:center; }
  #tableQrCanvas img, #tableQrCanvas canvas { max-width:100%; height:auto !important; }
  .table-qr-url { overflow-wrap:anywhere; word-break:break-word; font-size:.84rem; }
  @media print {
    body { background:#fff !important; }
    .navbar, header, .sidebar, .app-header, .table-qr-actions, .table-qr-config, .no-print { display:none !important; }
    .table-qr-page { max-width:none; padding:0; }
    .table-qr-card { border:0; box-shadow:none; }
    .table-qr-preview { background:#fff; border:0; }
    .table-qr-print-brand { display:block !important; }
  }
  .table-qr-print-brand { display:none; text-align:center; margin-bottom:16px; }
</style>
<div class="table-qr-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
    <div>
      <div class="text-muted text-uppercase fw-bold" style="letter-spacing:.12em;font-size:.68rem">Table Management</div>
      <h1 class="h3 fw-bold mt-1 mb-1"><i class="bi bi-qr-code text-primary me-2"></i>Table QR Ordering</h1>
      <p class="text-muted small mb-0">Print this QR code so guests can scan the menu for this table.</p>
    </div>
    <a href="<?= BASE_URL ?>/tables" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Tables</a>
  </div>

  <div class="row g-4 align-items-stretch">
    <div class="col-12 col-lg-5">
      <section class="table-qr-card h-100">
        <div class="p-4 border-bottom">
          <span class="badge bg-primary-subtle text-primary mb-2">Table <?= $h($table['table_number']) ?></span>
          <h2 class="h4 fw-bold mb-1"><?= $h($table['name']) ?></h2>
          <p class="text-muted mb-0"><?= $h($table['floor_zone'] ?? 'Main Dining') ?> · <?= (int)$table['capacity'] ?> seats</p>
        </div>
        <div class="p-4 table-qr-config">
          <label for="qrBaseUrl" class="form-label fw-semibold small">Public website base URL</label>
          <input type="url" class="form-control mb-2" id="qrBaseUrl" value="<?= $h($baseUrl) ?>" placeholder="https://your-restaurant.example" autocomplete="url">
          <div class="form-text mb-3">For a deployed site, use your public website address. For local XAMPP testing from a phone, use your Mac's LAN IP and app path instead of localhost.</div>
          <label class="form-label fw-semibold small">Customer menu link</label>
          <div id="tableQrUrl" class="table-qr-url rounded-3 border bg-light p-3 mb-3"><?= $h($defaultUrl) ?></div>
          <div class="d-flex flex-wrap gap-2 table-qr-actions">
            <button type="button" class="btn btn-primary" id="copyTableQrUrl"><i class="bi bi-copy me-1"></i> Copy Link</button>
            <a class="btn btn-outline-secondary" id="openTableQrUrl" href="<?= $h($defaultUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i> Preview Menu</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print QR</button>
          </div>
          <div class="small text-muted mt-3" id="qrStatus" aria-live="polite"></div>
        </div>
      </section>
    </div>
    <div class="col-12 col-lg-7">
      <section class="table-qr-card h-100">
        <div class="p-4">
          <div class="table-qr-print-brand">
            <h2 class="fw-bold mb-1"><?= $h($restaurant['name'] ?? '') ?></h2>
            <h3 class="h4 fw-bold">Scan to Order</h3>
            <div><?= $h($table['name']) ?> · Table <?= $h($table['table_number']) ?></div>
          </div>
          <div class="table-qr-preview">
            <div id="tableQrCanvas" aria-label="Table QR code"></div>
            <h2 class="h4 fw-bold text-center mt-4 mb-1">Scan to Order</h2>
            <p class="text-muted text-center small mb-0">Browse the menu and send your order to our kitchen.</p>
            <div class="fw-bold mt-2"><?= $h($table['name']) ?> · Table <?= $h($table['table_number']) ?></div>
          </div>
          <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>This QR code is unique to this table. Display the printed code at the assigned table so guests order to the correct location.</p>
        </div>
      </section>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(() => {
  const baseInput = document.getElementById('qrBaseUrl');
  const urlBox = document.getElementById('tableQrUrl');
  const openLink = document.getElementById('openTableQrUrl');
  const status = document.getElementById('qrStatus');
  const token = <?= json_encode((string)$table['qr_token']) ?>;
  const initialBaseUrl = <?= json_encode(rtrim((string)$baseUrl, '/')) ?>;
  const path = <?= json_encode('/order/' . (string)$table['qr_token']) ?>;
  let qr = null;
  function normalizedBase() {
    return (baseInput.value || '').trim().replace(/\/+$/, '');
  }
  function updateQr() {
    const base = normalizedBase();
    if (!/^https?:\/\//i.test(base)) {
      status.textContent = 'Enter a complete URL beginning with http:// or https://.';
      return;
    }
    const url = base + path;
    urlBox.textContent = url;
    openLink.href = url;
    status.textContent = '';
    if (!window.QRCode) {
      status.textContent = 'QR generator did not load. Check your internet connection and refresh this page.';
      return;
    }
    const target = document.getElementById('tableQrCanvas');
    target.innerHTML = '';
    qr = new QRCode(target, { text:url, width:200, height:200, colorDark:'#111827', colorLight:'#ffffff', correctLevel:QRCode.CorrectLevel.M });
  }
  baseInput.addEventListener('input', updateQr);
  document.getElementById('copyTableQrUrl').addEventListener('click', async () => {
    const value = urlBox.textContent;
    try {
      await navigator.clipboard.writeText(value);
      status.textContent = 'Customer menu link copied.';
    } catch (error) {
      const range = document.createRange();
      range.selectNodeContents(urlBox);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      status.textContent = 'Select and copy the highlighted link.';
    }
  });
  baseInput.value = initialBaseUrl;
  updateQr();
})();
</script>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
