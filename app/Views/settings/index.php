<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$restaurantName = trim((string)($restaurant['name'] ?? 'Your Restaurant'));
$tagline = (string)($restaurant['tagline'] ?? '');
$primaryColor = (string)($restaurant['pos_primary_color'] ?? '#2563eb');
$secondaryColor = (string)($restaurant['pos_secondary_color'] ?? '#059669');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#2563eb';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor)) $secondaryColor = '#059669';
$restaurantNameEsc = htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8');
?>
<style>
  .settings-intro { max-width: 760px; }
  .settings-card { border: 1px solid #e7ecf3; border-radius: 18px; overflow: hidden; background: #fff; }
  .settings-card + .settings-card { margin-top: 22px; }
  .settings-card-head { display:flex; align-items:center; gap:14px; padding:20px 24px; border-bottom:1px solid #edf1f6; }
  .settings-card-body { padding:24px; }
  .settings-section-icon { display:inline-grid; place-items:center; width:44px; height:44px; flex:0 0 44px; border-radius:14px; color:#2563eb; background:#eff6ff; font-size:1.2rem; }
  .settings-kicker { font-size:.7rem; color:#8995a8; text-transform:uppercase; letter-spacing:.12em; font-weight:800; }
  .settings-logo-stage { display:flex; align-items:center; justify-content:center; min-height:190px; padding:22px; border:1px dashed #cfd8e6; border-radius:15px; background:linear-gradient(145deg,#f9fbff,#f1f5fb); }
  .settings-logo-stage img { display:block; max-width:100%; max-height:135px; object-fit:contain; }
  .settings-image-empty { display:flex; flex-direction:column; align-items:center; gap:8px; color:#98a4b7; text-align:center; }
  .settings-image-empty i { font-size:2.4rem; color:#c4cedc; }
  .settings-field-help { font-size:.78rem; color:#768397; margin-top:7px; }
  .settings-image-wrap { display:flex; align-items:center; justify-content:center; width:92px; height:92px; border:1px solid #e2e8f0; border-radius:18px; background:#fff; overflow:hidden; box-shadow:0 6px 18px rgba(15,23,42,.05); }
  .settings-image-wrap img { width:100%; height:100%; padding:7px; object-fit:contain; }
  .settings-color-control { display:flex; align-items:center; gap:12px; padding:12px; border:1px solid #e5eaf2; border-radius:13px; }
  .settings-color-control input[type=color] { width:48px; height:43px; padding:3px; border:1px solid #dce3ed; border-radius:10px; background:#fff; cursor:pointer; flex:0 0 48px; }
  .settings-brand-preview { border:1px solid #e5eaf2; border-radius:15px; overflow:hidden; background:#f8fafc; }
  .settings-brand-preview-top { display:flex; align-items:center; gap:12px; padding:16px; color:#fff; background:#111827; }
  .settings-brand-preview-mark { display:grid; place-items:center; flex:0 0 44px; width:44px; height:44px; border-radius:13px; overflow:hidden; color:#fff; background:rgba(255,255,255,.12); }
  .settings-brand-preview-mark img { width:100%; height:100%; object-fit:contain; padding:3px; background:#fff; }
  .settings-brand-preview-copy { min-width:0; display:flex; flex-direction:column; }
  .settings-brand-preview-copy strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .settings-brand-preview-copy small { color:#b8c4d6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .settings-brand-preview-body { padding:18px; }
  .settings-brand-preview-button { display:inline-flex; align-items:center; gap:7px; color:#fff; background:var(--settings-primary,#2563eb); padding:9px 13px; border-radius:9px; font-weight:700; font-size:.8rem; }
  .settings-brand-preview-accent { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--settings-secondary,#059669); margin-right:5px; }
  .settings-save-bar { position:sticky; bottom:0; z-index:10; display:flex; justify-content:space-between; align-items:center; gap:15px; padding:16px 20px; margin:22px -1px 0; background:rgba(255,255,255,.96); border:1px solid #e7ecf3; border-radius:16px; box-shadow:0 -5px 22px rgba(15,23,42,.05); backdrop-filter:blur(12px); }
  @media (max-width:575.98px) { .settings-card-head,.settings-card-body { padding:17px; } .settings-save-bar { align-items:stretch; flex-direction:column; padding:14px; } .settings-save-bar .btn { width:100%; } }
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
    <div class="settings-intro">
      <div class="d-flex align-items-center gap-3 mb-2">
        <span class="settings-section-icon"><i class="bi bi-sliders2" aria-hidden="true"></i></span>
        <div>
          <div class="settings-kicker mb-1">Restaurant workspace</div>
          <h1 class="h3 fw-bold mb-0">Settings</h1>
        </div>
      </div>
      <p class="text-secondary mb-0">Manage your restaurant identity, logo, tagline, brand colors, and browser favicon all in one place.</p>
    </div>
    <a class="btn btn-outline-secondary bg-white" href="<?= BASE_URL ?>/dashboard"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
  </div>

  <?php if (empty($tagline_column_ready) || empty($logo_column_ready) || empty($favicon_column_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3" role="alert">
      <div class="d-flex align-items-start gap-3 flex-grow-1">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>
          <strong class="d-block mb-1">One-time settings database update needed</strong>
          <p class="mb-0">This existing installation is missing one or more branding fields. Apply a safe update to add only the missing columns; your restaurant, menu, users, and sales data will be preserved.</p>
        </div>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/settings/migrate" class="ms-lg-auto">
        <?= \\App\\Core\\CSRF::formField() ?>
        <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-database-gear me-1"></i> Apply Safe Settings Update</button>
      </form>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/settings" enctype="multipart/form-data" id="restaurantSettingsForm">
    <?= \App\Core\CSRF::formField() ?>

    <section class="settings-card">
      <div class="settings-card-head">
        <span class="settings-section-icon"><i class="bi bi-shop" aria-hidden="true"></i></span>
        <div>
          <h2 class="h5 fw-bold mb-1">Restaurant identity</h2>
          <p class="small text-secondary mb-0">Your restaurant name and the short line shown beneath it.</p>
        </div>
      </div>
      <div class="settings-card-body">
        <div class="row g-4">
          <div class="col-12 col-lg-6">
            <label for="restaurantName" class="form-label fw-semibold">Restaurant / Carinderia name <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-lg" id="restaurantName" name="restaurant_name" maxlength="150" required value="<?= $restaurantNameEsc ?>" placeholder="e.g. Mang Juan's Carinderia">
            <div class="settings-field-help">This name appears across the management app and POS. Changing it will not change your restaurant ID or login details.</div>
          </div>
          <div class="col-12 col-lg-6">
            <label for="restaurantTagline" class="form-label fw-semibold">Tagline <span class="text-secondary fw-normal">(optional)</span></label>
            <input type="text" class="form-control form-control-lg" id="restaurantTagline" name="tagline" maxlength="180" value="<?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Lutong-bahay, laging bagong luto" <?= empty($tagline_column_ready) ? 'disabled' : '' ?>>
            <div class="settings-field-help">Keep it short. Leave blank to hide the tagline. Maximum 180 characters.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="settings-card">
      <div class="settings-card-head">
        <span class="settings-section-icon"><i class="bi bi-images" aria-hidden="true"></i></span>
        <div>
          <h2 class="h5 fw-bold mb-1">Logo &amp; favicon</h2>
          <p class="small text-secondary mb-0">Upload your workspace logo and a separate icon for the browser tab.</p>
        </div>
      </div>
      <div class="settings-card-body">
        <div class="row g-4">
          <div class="col-12 col-xl-6">
            <div class="d-flex align-items-start gap-3 mb-3">
              <div class="settings-image-wrap">
                <?php if (!empty($logo_url)): ?>
                  <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $restaurantNameEsc ?> logo" id="settingsLogoPreview">
                <?php else: ?>
                  <img src="" alt="Logo preview" id="settingsLogoPreview" class="d-none">
                  <i class="bi bi-shop-window text-secondary fs-3" id="settingsLogoPlaceholder" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 min-w-0">
                <h3 class="h6 fw-bold mb-1">Restaurant logo</h3>
                <p class="small text-secondary mb-0">Shown in the management sidebar and POS header.</p>
              </div>
            </div>
            <label for="restaurantLogoInput" class="form-label small fw-semibold">Upload or replace logo</label>
            <input type="file" class="form-control" id="restaurantLogoInput" name="restaurant_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= empty($logo_column_ready) ? 'disabled' : '' ?>>
            <div class="settings-field-help">JPG, PNG, or WebP · up to 2 MB. A square or horizontal logo works best.</div>
            <?php if (!empty($logo_url) && !empty($logo_column_ready)): ?>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
                <label class="form-check-label small text-danger fw-semibold" for="removeLogo">Remove restaurant logo</label>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-12 col-xl-6">
            <div class="d-flex align-items-start gap-3 mb-3">
              <div class="settings-image-wrap">
                <img src="<?= htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8') ?>" alt="Favicon preview" id="settingsFaviconPreview" data-fallback-url="<?= htmlspecialchars($logo_url !== '' ? $logo_url : BASE_URL . '/public/favicon.svg', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="flex-grow-1 min-w-0">
                <h3 class="h6 fw-bold mb-1">Browser favicon</h3>
                <p class="small text-secondary mb-0">The small icon displayed on your browser tab. Independent from the logo.</p>
              </div>
            </div>
            <label for="restaurantFaviconInput" class="form-label small fw-semibold">Upload or replace favicon</label>
            <input type="file" class="form-control" id="restaurantFaviconInput" name="restaurant_favicon" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" <?= empty($favicon_column_ready) ? 'disabled' : '' ?>>
            <div class="settings-field-help">PNG, JPG, or WebP · up to 512 KB · square image between 16×16 and 512×512 pixels.</div>
            <?php if (!empty($has_custom_favicon) && !empty($favicon_column_ready)): ?>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="removeFavicon">
                <label class="form-check-label small text-danger fw-semibold" for="removeFavicon">Remove custom favicon</label>
                <div class="settings-field-help">The logo will be used as the fallback.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="settings-card">
      <div class="settings-card-head">
        <span class="settings-section-icon"><i class="bi bi-palette2" aria-hidden="true"></i></span>
        <div>
          <h2 class="h5 fw-bold mb-1">Brand colors</h2>
          <p class="small text-secondary mb-0">Choose the primary and secondary colors used for buttons and highlights.</p>
        </div>
      </div>
      <div class="settings-card-body">
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6">
            <label for="primaryColor" class="form-label fw-semibold">Primary color</label>
            <div class="settings-color-control">
              <input type="color" id="primaryColor" name="pos_primary_color" value="<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>" aria-label="Primary brand color">
              <div class="min-w-0"><div class="small fw-semibold">Primary</div><code id="primaryColorValue"><?= htmlspecialchars(strtoupper($primaryColor), ENT_QUOTES, 'UTF-8') ?></code></div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label for="secondaryColor" class="form-label fw-semibold">Secondary color</label>
            <div class="settings-color-control">
              <input type="color" id="secondaryColor" name="pos_secondary_color" value="<?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?>" aria-label="Secondary brand color">
              <div class="min-w-0"><div class="small fw-semibold">Secondary</div><code id="secondaryColorValue"><?= htmlspecialchars(strtoupper($secondaryColor), ENT_QUOTES, 'UTF-8') ?></code></div>
            </div>
          </div>
        </div>

        <div class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.08em">Live preview</div>
        <div class="settings-brand-preview" id="settingsBrandPreview" style="--settings-primary:<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>;--settings-secondary:<?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?>">
          <div class="settings-brand-preview-top">
            <span class="settings-brand-preview-mark">
              <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="" id="settingsBrandLogo">
              <?php else: ?>
                <img src="" alt="" id="settingsBrandLogo" class="d-none">
                <i class="bi bi-shop-window" id="settingsBrandLogoPlaceholder" aria-hidden="true"></i>
              <?php endif; ?>
            </span>
            <span class="settings-brand-preview-copy">
              <strong id="settingsPreviewName"><?= $restaurantNameEsc ?></strong>
              <small id="settingsPreviewTagline"><?= htmlspecialchars($tagline !== '' ? $tagline : 'Restaurant workspace', ENT_QUOTES, 'UTF-8') ?></small>
            </span>
          </div>
          <div class="settings-brand-preview-body">
            <div class="fw-bold mb-2">Your workspace, your brand</div>
            <p class="small text-secondary mb-3"><span class="settings-brand-preview-accent"></span>Preview your selected brand colors and restaurant identity.</p>
            <span class="settings-brand-preview-button" id="settingsPreviewButton"><i class="bi bi-display"></i> POS Terminal</span>
          </div>
        </div>
      </div>
    </section>

    <div class="settings-save-bar">
      <div class="small text-secondary"><i class="bi bi-shield-check me-1"></i>Settings apply only to this restaurant workspace.</div>
      <button type="submit" class="btn btn-primary px-4 py-2" id="saveRestaurantSettings">
        <i class="bi bi-check2-circle me-1"></i> Save All Settings
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('restaurantSettingsForm');
  const nameInput = document.getElementById('restaurantName');
  const taglineInput = document.getElementById('restaurantTagline');
  const logoInput = document.getElementById('restaurantLogoInput');
  const faviconInput = document.getElementById('restaurantFaviconInput');
  const logoPreview = document.getElementById('settingsLogoPreview');
  const logoPlaceholder = document.getElementById('settingsLogoPlaceholder');
  const faviconPreview = document.getElementById('settingsFaviconPreview');
  const brandLogo = document.getElementById('settingsBrandLogo');
  const brandLogoPlaceholder = document.getElementById('settingsBrandLogoPlaceholder');
  const removeLogo = document.getElementById('removeLogo');
  const removeFavicon = document.getElementById('removeFavicon');
  const primary = document.getElementById('primaryColor');
  const secondary = document.getElementById('secondaryColor');
  const preview = document.getElementById('settingsBrandPreview');
  const saveButton = document.getElementById('saveRestaurantSettings');
  let logoObjectUrl = null;
  let faviconObjectUrl = null;

  const setPreviewImage = (img, placeholder, src) => {
    if (!img) return;
    img.src = src;
    img.classList.remove('d-none');
    placeholder?.classList.add('d-none');
  };

  logoInput?.addEventListener('change', () => {
    const file = logoInput.files && logoInput.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      alert('Choose a JPG, PNG, or WebP logo up to 2 MB.');
      logoInput.value = '';
      return;
    }
    if (logoObjectUrl) URL.revokeObjectURL(logoObjectUrl);
    logoObjectUrl = URL.createObjectURL(file);
    setPreviewImage(logoPreview, logoPlaceholder, logoObjectUrl);
    setPreviewImage(brandLogo, brandLogoPlaceholder, logoObjectUrl);
    if (removeLogo) removeLogo.checked = false;
  });

  faviconInput?.addEventListener('change', () => {
    const file = faviconInput.files && faviconInput.files[0];
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 512 * 1024) {
      alert('Choose a PNG, JPG, or WebP favicon up to 512 KB.');
      faviconInput.value = '';
      return;
    }
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => {
      URL.revokeObjectURL(url);
      if (image.naturalWidth < 16 || image.naturalHeight < 16 || image.naturalWidth > 512 || image.naturalHeight > 512) {
        alert('Favicon dimensions must be between 16×16 and 512×512 pixels.');
        faviconInput.value = '';
        return;
      }
      if (faviconObjectUrl) URL.revokeObjectURL(faviconObjectUrl);
      faviconObjectUrl = URL.createObjectURL(file);
      setPreviewImage(faviconPreview, null, faviconObjectUrl);
      if (removeFavicon) removeFavicon.checked = false;
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      alert('The chosen favicon could not be read as an image.');
      faviconInput.value = '';
    };
    image.src = url;
  });

  removeLogo?.addEventListener('change', () => {
    if (!removeLogo.checked) return;
    if (logoInput) logoInput.value = '';
    logoPreview?.classList.add('d-none');
    logoPlaceholder?.classList.remove('d-none');
    brandLogo?.classList.add('d-none');
    brandLogoPlaceholder?.classList.remove('d-none');
  });

  removeFavicon?.addEventListener('change', () => {
    if (!removeFavicon.checked) return;
    if (faviconInput) faviconInput.value = '';
    if (faviconPreview) faviconPreview.src = faviconPreview.dataset.fallbackUrl || '';
  });

  const syncIdentity = () => {
    const name = nameInput?.value.trim() || 'Your Restaurant';
    const tagline = taglineInput?.value.trim() || 'Restaurant workspace';
    const previewName = document.getElementById('settingsPreviewName');
    const previewTagline = document.getElementById('settingsPreviewTagline');
    if (previewName) previewName.textContent = name;
    if (previewTagline) previewTagline.textContent = tagline;
  };
  nameInput?.addEventListener('input', syncIdentity);
  taglineInput?.addEventListener('input', syncIdentity);

  const syncColors = () => {
    if (!preview || !primary || !secondary) return;
    preview.style.setProperty('--settings-primary', primary.value);
    preview.style.setProperty('--settings-secondary', secondary.value);
    document.getElementById('primaryColorValue').textContent = primary.value.toUpperCase();
    document.getElementById('secondaryColorValue').textContent = secondary.value.toUpperCase();
    if (saveButton) {
      saveButton.style.backgroundColor = primary.value;
      saveButton.style.borderColor = primary.value;
    }
  };
  primary?.addEventListener('input', syncColors);
  secondary?.addEventListener('input', syncColors);
  syncIdentity();
  syncColors();

  form?.addEventListener('submit', event => {
    if (!nameInput?.value.trim()) {
      event.preventDefault();
      nameInput?.focus();
      alert('Enter a restaurant name before saving.');
    }
  });
});
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
