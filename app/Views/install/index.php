
<style>
/* Diagnostics page: readable contrast in both themes. */
.install-page, body:has(.install-page) { color:#253248; }
body:has(.install-page) { background:#f3f6fb!important; }
body:has(.install-page) .container { max-width:1120px; }
body:has(.install-page) h2.text-dark,
body:has(.install-page) h6.text-dark { color:#17243a!important; }
body:has(.install-page) .card { overflow:hidden; border:1px solid #dfe6f0!important; background:#fff!important; box-shadow:0 10px 28px rgba(22,40,70,.055)!important; }
body:has(.install-page) .card-header { background:#f8faff!important; border-bottom:1px solid #e3eaf4!important; }
body:has(.install-page) .card-header .card-title { color:#243650!important; }
body:has(.install-page) .list-group-item { background:#fff!important; color:#27364c!important; border-color:#e4eaf2!important; }
body:has(.install-page) .text-secondary,
body:has(.install-page) .text-muted { color:#68778d!important; }
body:has(.install-page) .card-footer { background:#f5f8fc!important; border-top:1px solid #e3eaf4!important; }
body:has(.install-page) .btn { border-radius:10px; font-weight:700; }
body:has(.install-page) code { color:#2755a7; background:#edf3ff; padding:2px 5px; border-radius:5px; }
body:has(.install-page) .bg-dark.text-light { background:#111c2e!important; }
html[data-theme="dark"] body:has(.install-page) { background:#0b1220!important; color:#e7edf7!important; }
html[data-theme="dark"] body:has(.install-page) h2.text-dark,
html[data-theme="dark"] body:has(.install-page) h6.text-dark { color:#e7edf7!important; }
html[data-theme="dark"] body:has(.install-page) .card { background:#152238!important; border-color:#2b3b54!important; }
html[data-theme="dark"] body:has(.install-page) .card-header { background:#1c2b42!important; border-color:#2b3b54!important; }
html[data-theme="dark"] body:has(.install-page) .card-header .card-title,
html[data-theme="dark"] body:has(.install-page) .list-group-item { color:#e7edf7!important; }
html[data-theme="dark"] body:has(.install-page) .list-group-item { background:#152238!important; border-color:#2b3b54!important; }
html[data-theme="dark"] body:has(.install-page) .text-secondary,
html[data-theme="dark"] body:has(.install-page) .text-muted { color:#a8b7cc!important; }
html[data-theme="dark"] body:has(.install-page) .card-footer { background:#111d30!important; border-color:#2b3b54!important; }
html[data-theme="dark"] body:has(.install-page) code { color:#b7d0ff; background:#243650; }
</style>

<div class="container py-5 install-page">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <!-- Header -->
      <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
          <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-tools text-primary me-2"></i>System Diagnostics & XAMPP Setup
          </h2>
          <p class="text-muted small mb-0">Phase 1 Multi-Tenant Foundation Verification Environment</p>
        </div>
        <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
      </div>

      <!-- Flash Messages -->
      <?php if (!empty($flashes)): ?>
        <?php foreach ($flashes as $flash): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Diagnostics Checklist Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="card-title mb-0 fw-semibold text-secondary">
            <i class="bi bi-shield-check text-success me-2"></i>Environment Prerequisites Checklist
          </h5>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php foreach ($diagnostics as $key => $item): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                <div>
                  <h6 class="mb-0 fw-medium text-dark"><?= htmlspecialchars($item['label']) ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($item['value']) ?></small>
                </div>
                <?php if ($item['passed']): ?>
                  <span class="badge bg-success bg-opacity-25 text-success border border-success px-3 py-2 rounded-pill">
                    <i class="bi bi-check-circle-fill me-1"></i> Passed
                  </span>
                <?php else: ?>
                  <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-3 py-2 rounded-pill">
                    <i class="bi bi-x-circle-fill me-1"></i> Attention
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="card-footer bg-light p-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <form method="POST" action="<?= BASE_URL ?>/install/migrate" class="d-inline">
            <?= $csrf_field ?>
            <button type="submit" class="btn btn-primary btn-sm px-3" onclick="return confirm('Import sql/schema.sql and sql/seed.sql into the database?')">
              <i class="bi bi-database-down me-1"></i> Import Schema & Seed Data
            </button>
          </form>

          <a href="<?= BASE_URL ?>/install/tests" class="btn btn-success btn-sm px-3">
            <i class="bi bi-play-circle me-1"></i> Run Automated Verification Tests
          </a>
        </div>
      </div>

      <!-- Direct Source Code & Local Pull Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-success">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0 fw-semibold text-secondary">
            <i class="bi bi-cloud-arrow-down text-success me-2"></i>Direct Local Sync & Download
          </h5>
          <span class="badge bg-success bg-opacity-25 text-success border border-success">Ready to Pull</span>
        </div>
        <div class="card-body p-4 small">
          <p class="text-secondary mb-3">
            You can pull the entire repository with full commit history directly into your local machine or XAMPP <code>htdocs</code> right now:
          </p>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= BASE_URL ?>/restaurant-pos.tar.gz" class="btn btn-outline-dark btn-sm d-flex align-items-center gap-2" download>
              <i class="bi bi-file-earmark-zip text-danger fs-6"></i> Download Source Code (.tar.gz)
            </a>
            <a href="<?= BASE_URL ?>/restaurant-pos.bundle" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" download>
              <i class="bi bi-git text-danger fs-6"></i> Download Git Bundle (.bundle)
            </a>
          </div>
          <div class="bg-dark text-light p-3 rounded-3 font-monospace small">
            <div class="text-secondary small mb-1"># Run in your local terminal or Windows PowerShell (htdocs):</div>
            <div class="text-warning user-select-all">git clone <?= BASE_URL ?>/restaurant-pos.bundle restaurant-pos</div>
            <div class="text-warning user-select-all">cd restaurant-pos</div>
            <div class="text-warning user-select-all">git remote set-url origin https://github.com/webxuncrownking-cell/restaurant-pos.git</div>
          </div>
        </div>
      </div>

      <!-- XAMPP Step-by-Step Installation Guide -->
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
          <h5 class="card-title mb-0 fw-semibold text-secondary">
            <i class="bi bi-journal-text text-primary me-2"></i>XAMPP Quick Installation Instructions
          </h5>
        </div>
        <div class="card-body p-4 small text-secondary">
          <ol class="mb-0 ps-3">
            <li class="mb-2">
              <strong>Start Apache & MySQL:</strong> Open the XAMPP Control Panel and start the <strong>Apache</strong> and <strong>MySQL</strong> services.
            </li>
            <li class="mb-2">
              <strong>Extract Codebase:</strong> Ensure this repository folder is located at <code>C:/xampp/htdocs/restaurant-pos/</code>.
            </li>
            <li class="mb-2">
              <strong>Database Creation:</strong> Open phpMyAdmin at <a href="http://localhost/phpmyadmin/" target="_blank">http://localhost/phpmyadmin/</a>, and create database <code>restaurant_pos</code> with collation <code>utf8mb4_unicode_ci</code>.
            </li>
            <li class="mb-2">
              <strong>Run Import:</strong> Either click the <strong>"Import Schema & Seed Data"</strong> button above, or import <code>sql/schema.sql</code> followed by <code>sql/seed.sql</code> in phpMyAdmin.
            </li>
            <li class="mb-0">
              <strong>Access POS:</strong> Visit <a href="http://localhost/restaurant-pos/" target="_blank">http://localhost/restaurant-pos/</a> and sign in with demo accounts:
              <ul class="mt-1">
                <li>Mang Juan Admin: <code>juan_admin</code> / <code>RestoStaff2026!</code></li>
                <li>Cashier Maria: <code>maria_cashier</code> / <code>RestoStaff2026!</code> (Offline PIN: <code>1234</code>)</li>
                <li>SaaS Superadmin: <code>platform_admin</code> / <code>AdminPassword2026!</code></li>
              </ul>
            </li>
          </ol>
        </div>
      </div>

    </div>
  </div>
</div>
