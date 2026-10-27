<style>
  .login-page-wrap {
    min-height: calc(100vh - 58px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(20px, 4vh, 44px) 18px;
  }
  .login-page-wrap > .container {
    width: 100%;
    margin: 0 !important;
    padding: 0 !important;
  }
  .login-page-wrap .login-column {
    width: 100%;
    max-width: 560px;
    margin-inline: auto;
  }
  .login-page-wrap .login-brand-title {
    font-size: clamp(1.8rem, 2.2vw, 2.15rem);
    letter-spacing: -.035em;
  }
  .login-page-wrap .login-brand-subtitle { margin-bottom: 0; }
  @media (max-height: 760px) {
    .login-page-wrap { align-items: flex-start; padding-top: 22px; padding-bottom: 22px; }
  }

/* ServeFlow public authentication page refresh */
.login-page-wrap { min-height:100vh; padding:clamp(24px,5vh,64px) 20px; background:radial-gradient(ellipse at 50% 0%, #eaf1ff 0, #f5f7fb 48%, #eef2f8 100%); }
html[data-theme="dark"] .login-page-wrap { background:radial-gradient(ellipse at 50% 0%, #1b2b45 0, #0b1220 58%, #080e19 100%); }
.login-page-wrap .login-column { max-width:460px; }
.login-page-wrap .login-brand-title { color:#14213a !important; font-size:2rem; font-weight:800; letter-spacing:-.045em; }
.login-page-wrap .login-brand-subtitle { color:#64748b !important; font-size:.9rem !important; }
.login-page-wrap .card { border:1px solid #e0e7f1 !important; border-radius:20px !important; background:#fff !important; box-shadow:0 22px 60px rgba(20,38,68,.12) !important; }
.login-page-wrap .card-header { background:#fff !important; color:#18263d !important; border-bottom:1px solid #e7edf5 !important; padding:19px 24px !important; }
.login-page-wrap .card-header h5 { color:#18263d !important; font-size:1.05rem; }
.login-page-wrap .card-body { padding:24px !important; }
.login-page-wrap .form-label { color:#34445c !important; font-size:.85rem !important; font-weight:700 !important; }
.login-page-wrap .form-control { min-height:48px; background:#f8fafd !important; color:#18263d !important; border:1px solid #d5deeb !important; border-radius:0 11px 11px 0 !important; }
.login-page-wrap input#tenant_slug { border-radius:11px !important; }
.login-page-wrap .input-group-text { min-width:44px; justify-content:center; background:#edf3fc !important; color:#3769b3 !important; border:1px solid #d5deeb !important; border-right:0 !important; border-radius:11px 0 0 11px !important; }
.login-page-wrap .form-control:focus { background:#fff !important; border-color:#4b83e6 !important; box-shadow:0 0 0 4px rgba(59,130,246,.13) !important; }
.login-page-wrap .btn-warning { min-height:48px; border:0 !important; border-radius:11px !important; background:#2563eb !important; color:#fff !important; box-shadow:0 7px 18px rgba(37,99,235,.2) !important; }
.login-page-wrap .btn-warning:hover { background:#1d4ed8 !important; }
.login-page-wrap a { color:#2563eb; }
.login-page-wrap .text-muted { color:#687890 !important; }
html[data-theme="dark"] .login-page-wrap .login-brand-title { color:#f1f5fb !important; }
html[data-theme="dark"] .login-page-wrap .login-brand-subtitle,
html[data-theme="dark"] .login-page-wrap .text-muted { color:#a6b4c9 !important; }
html[data-theme="dark"] .login-page-wrap .card,
html[data-theme="dark"] .login-page-wrap .card-header,
html[data-theme="dark"] .login-page-wrap .card-body { background:#162338 !important; color:#e8eef8 !important; border-color:#2a3a53 !important; }
html[data-theme="dark"] .login-page-wrap .card-header h5,
html[data-theme="dark"] .login-page-wrap .form-label { color:#e8eef8 !important; }
html[data-theme="dark"] .login-page-wrap .form-control { background:#0d1727 !important; color:#f1f5fb !important; border-color:#354862 !important; }
html[data-theme="dark"] .login-page-wrap .input-group-text { background:#223550 !important; color:#a8c8ff !important; border-color:#354862 !important; }
html[data-theme="dark"] .login-page-wrap .form-control::placeholder { color:#8394ad !important; }


  .auth-theme-control { position:fixed; top:20px; right:22px; z-index:1200; display:flex; align-items:center; gap:9px; padding:8px 12px; border:1px solid #dbe4f0; border-radius:999px; background:rgba(255,255,255,.92); color:#334155; box-shadow:0 8px 24px rgba(15,23,42,.12); backdrop-filter:blur(12px); font-size:.82rem; font-weight:700; }
  .auth-theme-control button { display:flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:0; border-radius:50%; background:#e8eef8; color:#27456f; cursor:pointer; }
  .auth-theme-control button:focus-visible { outline:3px solid #60a5fa; outline-offset:2px; }
  .auth-brand-icon { width:68px; height:68px; display:inline-flex; align-items:center; justify-content:center; border-radius:20px !important; background:#2563eb !important; color:#fff !important; border:1px solid rgba(255,255,255,.35); box-shadow:0 10px 24px rgba(37,99,235,.28) !important; }
  .auth-brand-icon i { color:#fff !important; font-size:2rem; }
  html[data-theme="dark"] .auth-theme-control { background:rgba(22,35,56,.94); border-color:#354862; color:#e8eef8; }
  html[data-theme="dark"] .auth-theme-control button { background:#263a57; color:#dce9fb; }
  @media(max-width:575.98px) { .auth-theme-control { top:12px; right:12px; padding:6px 9px; font-size:.75rem; } .auth-theme-control button { width:30px; height:30px; } }


</style>

<div class="auth-theme-control" role="group" aria-label="Appearance">
  <span class="auth-theme-label">Light mode</span>
  <button type="button" class="auth-theme-toggle" aria-label="Switch to dark mode" title="Switch theme"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
</div>
<script>
(function () {
  var root = document.documentElement;
  var control = document.querySelector('.auth-theme-control');
  if (!control) return;
  var button = control.querySelector('.auth-theme-toggle');
  var label = control.querySelector('.auth-theme-label');
  function apply(theme) {
    root.setAttribute('data-theme', theme);
    var dark = theme === 'dark';
    label.textContent = dark ? 'Dark mode' : 'Light mode';
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    button.innerHTML = '<i class="bi ' + (dark ? 'bi-sun-fill' : 'bi-moon-stars-fill') + '" aria-hidden="true"></i>';
  }
  var saved = 'light';
  try { saved = localStorage.getItem('serveflow-theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
  apply(saved);
  button.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    apply(next);
    try { localStorage.setItem('serveflow-theme', next); } catch (e) {}
  });
})();
</script>

<div class="login-page-wrap">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 login-column">
      
      <!-- Brand Header -->
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center auth-brand-icon mb-3 shadow-sm" style="width: 68px; height: 68px;">
          <i class="bi bi-shop fs-1"></i>
        </div>
        <h1 class="fw-bold text-dark mb-1 login-brand-title">ServeFlow POS</h1>
        <p class="text-muted small login-brand-subtitle">Restaurant operations, made simple.</p>
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

      <!-- Login Card -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <h5 class="mb-0 fw-semibold text-secondary d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock text-primary"></i> Sign In to Terminal
          </h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="<?= BASE_URL ?>/login" id="loginForm">
            <?= $csrf_field ?>

            <div class="mb-3">
              <label for="login" class="form-label fw-medium text-secondary small">Username or Email</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control border-start-0 ps-3" id="login" name="login" required placeholder="Enter username or email" autofocus>
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-medium text-secondary small">Password</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                <input type="password" class="form-control border-start-0 ps-3" id="password" name="password" required placeholder="Enter password">
              </div>
            </div>

            <div class="mb-4">
              <label for="tenant_slug" class="form-label fw-medium text-secondary small d-flex justify-content-between">
                <span>Restaurant Slug <span class="text-muted fw-normal">(Optional)</span></span>
                <span class="text-muted fw-normal">e.g. mang-juan</span>
              </label>
              <input type="text" class="form-control" id="tenant_slug" name="tenant_slug" placeholder="Leave empty for auto-detect or superadmin">
            </div>

            <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark shadow-sm">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
          </form>
        </div>
      </div>

      <div class="text-center mt-3"><span class="text-muted small">New to ServeFlow POS?</span> <a href="<?= BASE_URL ?>/register" class="text-decoration-none fw-semibold small">Register your restaurant</a></div>

      <!-- Diagnostics Link -->
      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/install" class="text-decoration-none text-muted small">
          <i class="bi bi-tools me-1"></i> System Diagnostics & Setup Wizard
        </a>
      </div>

    </div>
  </div>
</div>
</div>

