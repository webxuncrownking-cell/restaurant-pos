<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';

$primaryColor = (string)($restaurant['pos_primary_color'] ?? '#2563eb');
$secondaryColor = (string)($restaurant['pos_secondary_color'] ?? '#059669');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#2563eb';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor)) $secondaryColor = '#059669';
$restaurantName = (string)($restaurant['name'] ?? 'Your Restaurant');
?>
<style>
  .branding-intro { max-width: 760px; }
  .branding-card { height: 100%; overflow: hidden; }
  .branding-card .card-header { padding: 20px 22px; background: #fff; }
  .branding-card .card-body { padding: 22px; }
  .branding-icon { display: inline-grid; place-items: center; width: 42px; height: 42px; border-radius: 13px; color: #2563eb; background: #eff6ff; font-size: 1.2rem; }
  .logo-stage { display: flex; align-items: center; justify-content: center; min-height: 220px; padding: 24px; border: 1px dashed #cfd8e6; border-radius: 15px; background: linear-gradient(145deg,#f9fbff,#f1f5fb); }
  .logo-stage img { display: block; max-width: 100%; max-height: 170px; object-fit: contain; }
  .logo-stage-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #94a3b8; text-align: center; }
  .logo-stage-empty i { font-size: 3rem; color: #c1cddd; }
  .color-control { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e5eaf2; border-radius: 12px; }
  .color-control input[type=color] { width: 48px; height: 43px; padding: 3px; border: 1px solid #dce3ed; border-radius: 10px; background: #fff; cursor: pointer; }
  .brand-preview { border: 1px solid #e5eaf2; border-radius: 15px; overflow: hidden; background: #f8fafc; }
  .brand-preview-bar { display: flex; align-items: center; gap: 11px; padding: 15px; color: #fff; background: #111827; }
  .brand-preview-logo { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 12px; color: #fff; background: rgba(255,255,255,.12); overflow: hidden; }
  .brand-preview-logo img { width: 100%; height: 100%; object-fit: contain; padding: 3px; background: #fff; }
  .brand-preview-body { padding: 18px; }
  .brand-preview-button { display: inline-flex; align-items: center; gap: 7px; color: #fff; background: var(--preview-primary,#2563eb); padding: 9px 13px; border-radius: 9px; font-weight: 700; font-size: .8rem; }
  .brand-preview-accent { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--preview-secondary,#059669); margin-right: 5px; }
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
    <div class="branding-intro">
      <div class="d-flex align-items-center gap-3 mb-2">
        <span class="branding-icon"><i class="bi bi-palette2" aria-hidden="true"></i></span>
        <div>
          <div class="small text-uppercase fw-bold text-muted mb-1" style="letter-spacing:.1em">Workspace settings</div>
          <h1 class="h3 fw-bold mb-0">Branding &amp; Logo</h1>
        </div>
      </div>
      <p class="text-secondary mb-0">Give <?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?> its own identity in the management app with a restaurant logo and brand colors.</p>
    </div>
    <a class="btn btn-outline-secondary bg-white" href="<?= BASE_URL ?>/dashboard"><i class="bi bi-arrow-left me-1"></i> Back to Dashboard</a>
  </div>

  <?php if (empty($logo_column_ready)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex gap-3 align-items-start" role="alert">
      <i class="bi bi-database-exclamation fs-4"></i>
      <div class="flex-grow-1">
        <h2 class="h6 fw-bold mb-1">One-time database update required for logo uploads</h2>
        <p class="mb-2">Your current database predates logo storage. Apply this statement once in phpMyAdmin → select your restaurant database → SQL. Your existing restaurant data will be preserved.</p>
        <code>ALTER TABLE restaurants ADD COLUMN logo_path VARCHAR(255) NULL AFTER name;</code>
        <p class="small mb-0 mt-2">The brand-color controls below already work without this update. Do not run the statement again after the column has been added.</p>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/settings/branding" enctype="multipart/form-data">
    <?= \App\Core\CSRF::formField() ?>
    <div class="row g-4 align-items-stretch">
      <div class="col-12 col-xl-5">
        <section class="card border-0 branding-card">
          <div class="card-header d-flex align-items-center gap-3">
            <span class="branding-icon"><i class="bi bi-image" aria-hidden="true"></i></span>
            <div>
              <h2 class="h5 fw-bold mb-1">Restaurant logo</h2>
              <p class="text-secondary small mb-0">Displayed in the management sidebar</p>
            </div>
          </div>
          <div class="card-body">
            <div class="logo-stage mb-3">
              <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?> logo" id="logoPreviewImage">
                <div class="logo-stage-empty d-none" id="logoPreviewEmpty">
                  <i class="bi bi-shop-window" aria-hidden="true"></i><strong>No logo selected</strong>
                </div>
              <?php else: ?>
                <img src="" alt="Logo preview" id="logoPreviewImage" class="d-none">
                <div class="logo-stage-empty" id="logoPreviewEmpty">
                  <i class="bi bi-shop-window" aria-hidden="true"></i>
                  <strong>No logo uploaded yet</strong>
                  <span class="small">Your logo preview will appear here</span>
                </div>
              <?php endif; ?>
            </div>

            <label for="restaurantLogoInput" class="form-label fw-semibold">Upload a new logo</label>
            <input class="form-control" type="file" id="restaurantLogoInput" name="restaurant_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= empty($logo_column_ready) ? 'disabled' : '' ?>>
            <div class="form-text mt-2">JPG, PNG, or WebP · up to 2 MB · square or horizontal logos work best.</div>

            <?php if (!empty($logo_url) && !empty($logo_column_ready)): ?>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
                <label class="form-check-label text-danger small fw-semibold" for="removeLogo">Remove current logo</label>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-7">
        <section class="card border-0 branding-card">
          <div class="card-header d-flex align-items-center gap-3">
            <span class="branding-icon"><i class="bi bi-droplet-half" aria-hidden="true"></i></span>
            <div>
              <h2 class="h5 fw-bold mb-1">Brand colors</h2>
              <p class="text-secondary small mb-0">Customize the primary buttons and accent highlights</p>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="primaryColor">Primary color</label>
                <div class="color-control">
                  <input type="color" id="primaryColor" name="pos_primary_color" value="<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>" aria-label="Primary brand color">
                  <div class="min-w-0">
                    <div class="fw-semibold small">Primary</div>
                    <code id="primaryColorValue"><?= htmlspecialchars(strtoupper($primaryColor), ENT_QUOTES, 'UTF-8') ?></code>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="secondaryColor">Secondary color</label>
                <div class="color-control">
                  <input type="color" id="secondaryColor" name="pos_secondary_color" value="<?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?>" aria-label="Secondary brand color">
                  <div class="min-w-0">
                    <div class="fw-semibold small">Secondary</div>
                    <code id="secondaryColorValue"><?= htmlspecialchars(strtoupper($secondaryColor), ENT_QUOTES, 'UTF-8') ?></code>
                  </div>
                </div>
              </div>
            </div>

            <div class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.08em">Live preview</div>
            <div class="brand-preview mb-4" id="brandPreview" style="--preview-primary:<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>;--preview-secondary:<?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?>">
              <div class="brand-preview-bar">
                <span class="brand-preview-logo">
                  <?php if (!empty($logo_url)): ?>
                    <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="" id="brandPreviewLogo">
                  <?php else: ?>
                    <img src="" alt="" id="brandPreviewLogo" class="d-none">
                    <i class="bi bi-shop-window" id="brandPreviewIcon" aria-hidden="true"></i>
                  <?php endif; ?>
                </span>
                <div class="min-w-0">
                  <div class="fw-bold text-truncate"><?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="small" style="color:#9eacc1">Restaurant workspace</div>
                </div>
                <span class="ms-auto small" style="color:#cbd5e1"><i class="bi bi-person-circle me-1"></i>Owner</span>
              </div>
              <div class="brand-preview-body">
                <div class="fw-bold mb-2">Your workspace, your brand</div>
                <p class="small text-secondary mb-3"><span class="brand-preview-accent"></span>Preview how your brand accents will look across the app.</p>
                <span class="brand-preview-button"><i class="bi bi-display"></i> POS Terminal</span>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
              <p class="small text-secondary mb-0"><i class="bi bi-info-circle me-1"></i>Changes apply to the selected restaurant only.</p>
              <button type="submit" class="btn btn-primary px-4 py-2" style="background:var(--preview-primary,<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>);border-color:var(--preview-primary,<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>)">
                <i class="bi bi-check2-circle me-1"></i> Save Branding
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('restaurantLogoInput');
    const logoPreview = document.getElementById('logoPreviewImage');
    const emptyPreview = document.getElementById('logoPreviewEmpty');
    const miniLogo = document.getElementById('brandPreviewLogo');
    const miniIcon = document.getElementById('brandPreviewIcon');
    const removeLogo = document.getElementById('removeLogo');
    const primary = document.getElementById('primaryColor');
    const secondary = document.getElementById('secondaryColor');
    const preview = document.getElementById('brandPreview');
    const saveButton = document.querySelector('button[type="submit"]');

    fileInput?.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      const allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type) || file.size > 2 * 1024 * 1024) {
        alert('Choose a JPG, PNG, or WebP image up to 2 MB.');
        fileInput.value = '';
        return;
      }
      const objectUrl = URL.createObjectURL(file);
      if (logoPreview) {
        logoPreview.src = objectUrl;
        logoPreview.classList.remove('d-none');
      }
      emptyPreview?.classList.add('d-none');
      if (miniLogo) {
        miniLogo.src = objectUrl;
        miniLogo.classList.remove('d-none');
      } else if (miniIcon) {
        miniIcon.classList.add('d-none');
      }
      if (removeLogo) removeLogo.checked = false;
    });

    removeLogo?.addEventListener('change', () => {
      if (!removeLogo.checked || !logoPreview) return;
      logoPreview.classList.add('d-none');
      emptyPreview?.classList.remove('d-none');
      miniLogo?.classList.add('d-none');
      miniIcon?.classList.remove('d-none');
      if (fileInput) fileInput.value = '';
    });

    const syncColors = () => {
      if (!preview) return;
      preview.style.setProperty('--preview-primary', primary.value);
      preview.style.setProperty('--preview-secondary', secondary.value);
      document.getElementById('primaryColorValue').textContent = primary.value.toUpperCase();
      document.getElementById('secondaryColorValue').textContent = secondary.value.toUpperCase();
      if (saveButton) {
        saveButton.style.backgroundColor = primary.value;
        saveButton.style.borderColor = primary.value;
      }
    };
    primary?.addEventListener('input', syncColors);
    secondary?.addEventListener('input', syncColors);
    syncColors();
  });
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
