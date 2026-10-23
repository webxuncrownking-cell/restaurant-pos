<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$restaurantName = (string)($restaurant['name'] ?? 'Your Restaurant');
$hasCustomFavicon = !empty($favicon_path);
?>
<style>
  .favicon-intro { max-width: 760px; }
  .favicon-card { border: 1px solid #e7ecf3; border-radius: 18px; overflow: hidden; }
  .favicon-card .card-body { padding: 24px; }
  .favicon-icon { display:inline-grid; place-items:center; width:44px; height:44px; border-radius:14px; color:#2563eb; background:#eff6ff; font-size:1.25rem; }
  .favicon-preview-stage { display:flex; align-items:center; justify-content:center; gap:18px; min-height:210px; border:1px dashed #cfd8e6; border-radius:16px; background:linear-gradient(145deg,#f9fbff,#f1f5fb); padding:24px; }
  .favicon-preview-square { flex:0 0 96px; width:96px; height:96px; display:grid; place-items:center; border-radius:23px; background:#fff; border:1px solid #e2e8f0; box-shadow:0 8px 24px rgba(15,23,42,.08); overflow:hidden; }
  .favicon-preview-square img { display:block; width:100%; height:100%; padding:8px; object-fit:contain; }
  .favicon-browser-preview { border:1px solid #d9e1ec; background:#fff; border-radius:12px; padding:10px 12px; max-width:340px; width:100%; box-shadow:0 3px 12px rgba(15,23,42,.04); }
  .favicon-tab { display:flex; align-items:center; gap:8px; font-size:.78rem; color:#344054; white-space:nowrap; overflow:hidden; }
  .favicon-tab img { width:18px; height:18px; object-fit:contain; flex:0 0 18px; }
  .favicon-tab span { overflow:hidden; text-overflow:ellipsis; }
  .favicon-help { background:#f8fafc; border:1px solid #e7ecf3; border-radius:12px; padding:14px; color:#667085; font-size:.82rem; }
  @media (max-width:575.98px) { .favicon-preview-stage { flex-direction:column; } .favicon-card .card-body { padding:18px; } }
</style>

<div class="container-fluid px-3 px-lg-4 py-4">
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div class="favicon-intro">
      <div class="d-flex align-items-center gap-3 mb-2">
        <span class="favicon-icon"><i class="bi bi-app-indicator" aria-hidden="true"></i></span>
        <div>
          <div class="small text-uppercase fw-bold text-muted mb-1" style="letter-spacing:.1em">Workspace settings</div>
          <h1 class="h3 fw-bold mb-0">Favicon Settings</h1>
        </div>
      </div>
      <p class="text-secondary mb-0">Set a small browser-tab icon for <?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?>. This is saved separately from the restaurant logo.</p>
    </div>
    <a class="btn btn-outline-secondary bg-white" href="<?= BASE_URL ?>/settings/branding"><i class="bi bi-arrow-left me-1"></i> Branding &amp; Logo</a>
  </div>

  <?php if (empty($favicon_column_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm" role="alert">
      <div class="d-flex gap-3 align-items-start">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>
          <h2 class="h6 fw-bold mb-1">One-time database update required</h2>
          <p class="mb-2">Run this statement once in phpMyAdmin → select your restaurant database → SQL. It adds a separate favicon field and does not replace your logo.</p>
          <code>ALTER TABLE restaurants ADD COLUMN favicon_path VARCHAR(255) NULL AFTER logo_path;</code>
          <p class="small mt-2 mb-0">The form stays disabled until the column exists.</p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card favicon-card shadow-sm">
    <div class="card-body">
      <div class="row g-4 align-items-start">
        <div class="col-12 col-lg-5">
          <div class="favicon-preview-stage mb-3">
            <div class="favicon-preview-square">
              <img src="<?= htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8') ?>" id="faviconPreviewImage" alt="Current favicon preview">
            </div>
            <div class="favicon-browser-preview">
              <div class="small text-uppercase text-secondary fw-bold mb-2" style="letter-spacing:.07em">Browser tab preview</div>
              <div class="favicon-tab">
                <img src="<?= htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8') ?>" id="faviconTabPreview" alt="">
                <span><?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?> — POS</span>
                <i class="bi bi-x ms-auto text-secondary" aria-hidden="true"></i>
              </div>
            </div>
          </div>
          <div class="favicon-help">
            <strong class="d-block text-dark mb-1"><i class="bi bi-lightbulb me-1"></i>Recommended</strong>
            Use a simple square icon with a transparent or solid background. A PNG between 32×32 and 256×256 pixels usually works well.
          </div>
          <?php if (!empty($using_logo_fallback)): ?>
            <div class="small text-secondary mt-3"><i class="bi bi-info-circle me-1"></i>No custom favicon is saved yet, so the restaurant logo is being used as a fallback.</div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-lg-7">
          <form method="POST" action="<?= BASE_URL ?>/settings/favicon" enctype="multipart/form-data" id="faviconForm">
            <?= \App\Core\CSRF::formField() ?>
            <label for="restaurantFaviconInput" class="form-label fw-semibold">Upload favicon image</label>
            <input class="form-control" type="file" id="restaurantFaviconInput" name="restaurant_favicon" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" <?= empty($favicon_column_ready) ? 'disabled' : '' ?>>
            <div class="form-text mt-2">PNG, JPG, or WebP · maximum 512 KB · dimensions from 16×16 up to 512×512 pixels. A square image is recommended.</div>

            <?php if ($hasCustomFavicon && !empty($favicon_column_ready)): ?>
              <div class="form-check border rounded-3 p-3 ps-5 mt-4">
                <input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="removeFavicon">
                <label class="form-check-label text-danger fw-semibold" for="removeFavicon">Remove custom favicon</label>
                <div class="small text-secondary mt-1">The browser will fall back to the restaurant logo, or the default restaurant icon.</div>
              </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center pt-4 mt-4 border-top">
              <a href="<?= BASE_URL ?>/settings/branding" class="btn btn-light">Cancel</a>
              <button type="submit" class="btn btn-primary px-4" <?= empty($favicon_column_ready) ? 'disabled' : '' ?>>
                <i class="bi bi-check2-circle me-1"></i> Save Favicon
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('restaurantFaviconInput');
  const largePreview = document.getElementById('faviconPreviewImage');
  const tabPreview = document.getElementById('faviconTabPreview');
  const remove = document.getElementById('removeFavicon');
  const form = document.getElementById('faviconForm');
  let objectUrl = null;

  input?.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const allowed = ['image/png', 'image/jpeg', 'image/webp'];
    if (!allowed.includes(file.type) || file.size > 512 * 1024) {
      alert('Choose a PNG, JPG, or WebP image up to 512 KB.');
      input.value = '';
      return;
    }
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    if (largePreview) largePreview.src = objectUrl;
    if (tabPreview) tabPreview.src = objectUrl;
    if (remove) remove.checked = false;
  });

  remove?.addEventListener('change', () => {
    if (remove.checked) {
      if (input) input.value = '';
      const fallback = '<?= htmlspecialchars(BASE_URL . '/public/favicon.svg', ENT_QUOTES, 'UTF-8') ?>';
      if (largePreview) largePreview.src = fallback;
      if (tabPreview) tabPreview.src = fallback;
    }
  });

  form?.addEventListener('submit', event => {
    const file = input && input.files ? input.files[0] : null;
    if (!file && !remove?.checked) {
      event.preventDefault();
      alert('Choose a favicon image or select Remove custom favicon.');
    }
  });
});
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
