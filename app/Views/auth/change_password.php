<style>
  .password-page-wrap {
    min-height: calc(100vh - 58px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(20px, 4vh, 44px) 18px;
  }
  .password-page-wrap > .container {
    width: 100%;
    max-width: 560px;
    margin: 0 !important;
    padding: 0 !important;
  }
  .password-page-wrap .password-brand-title {
    font-size: clamp(1.8rem, 2.2vw, 2.15rem);
    letter-spacing: -.035em;
  }
  @media (max-height: 760px) {
    .password-page-wrap { align-items: flex-start; padding-top: 22px; padding-bottom: 22px; }
  }

/* ServeFlow password-change form refresh */
.password-page-wrap { min-height:100vh; padding:clamp(24px,5vh,64px) 20px; background:radial-gradient(ellipse at 50% 0%, #eaf1ff 0, #f5f7fb 48%, #eef2f8 100%); }
html[data-theme="dark"] .password-page-wrap { background:radial-gradient(ellipse at 50% 0%, #1b2b45 0, #0b1220 58%, #080e19 100%); }
.password-page-wrap .password-brand-title { color:#14213a !important; font-size:2rem; font-weight:800; letter-spacing:-.045em; }
.password-page-wrap .card { border:1px solid #e0e7f1 !important; border-radius:20px !important; background:#fff !important; box-shadow:0 22px 60px rgba(20,38,68,.12) !important; }
.password-page-wrap .card-header { background:#fff !important; color:#18263d !important; border-bottom:1px solid #e7edf5 !important; padding:19px 24px !important; }
.password-page-wrap .card-header h2 { color:#18263d !important; }
.password-page-wrap .card-body { padding:24px !important; }
.password-page-wrap .form-label { color:#34445c !important; font-size:.88rem !important; font-weight:700 !important; }
.password-page-wrap .form-control { min-height:48px; background:#f8fafd !important; color:#18263d !important; border:1px solid #d5deeb !important; border-radius:11px !important; }
.password-page-wrap .form-control:focus { background:#fff !important; border-color:#4b83e6 !important; box-shadow:0 0 0 4px rgba(59,130,246,.13) !important; }
.password-page-wrap .btn-warning { min-height:49px; border:0 !important; border-radius:11px !important; background:#2563eb !important; color:#fff !important; box-shadow:0 7px 18px rgba(37,99,235,.2) !important; }
.password-page-wrap .btn-warning:hover { background:#1d4ed8 !important; }
.password-page-wrap .text-muted { color:#687890 !important; }
html[data-theme="dark"] .password-page-wrap .password-brand-title { color:#f1f5fb !important; }
html[data-theme="dark"] .password-page-wrap .text-muted { color:#a6b4c9 !important; }
html[data-theme="dark"] .password-page-wrap .card,
html[data-theme="dark"] .password-page-wrap .card-header,
html[data-theme="dark"] .password-page-wrap .card-body { background:#162338 !important; color:#e8eef8 !important; border-color:#2a3a53 !important; }
html[data-theme="dark"] .password-page-wrap .card-header h2,
html[data-theme="dark"] .password-page-wrap .form-label { color:#e8eef8 !important; }
html[data-theme="dark"] .password-page-wrap .form-control { background:#0d1727 !important; color:#f1f5fb !important; border-color:#354862 !important; }
html[data-theme="dark"] .password-page-wrap .form-control::placeholder { color:#8394ad !important; }


  .auth-theme-control { position:fixed; top:20px; right:22px; z-index:1200; display:flex; align-items:center; justify-content:center; padding:5px; border:1px solid #dbe4f0; border-radius:999px; background:rgba(255,255,255,.92); color:#334155; box-shadow:0 8px 24px rgba(15,23,42,.12); backdrop-filter:blur(12px); width:48px; height:48px; }
  .auth-theme-control button { display:flex; align-items:center; justify-content:center; flex:0 0 36px; width:36px; height:36px; padding:0; border:0; border-radius:50%; background:#e8eef8; color:#27456f; cursor:pointer; }
  .auth-theme-control button:focus-visible { outline:3px solid #60a5fa; outline-offset:2px; }
  .auth-brand-icon { width:68px; height:68px; display:inline-flex; align-items:center; justify-content:center; border-radius:20px !important; background:#2563eb !important; color:#fff !important; border:1px solid rgba(255,255,255,.35); box-shadow:0 10px 24px rgba(37,99,235,.28) !important; }
  .auth-brand-icon i { color:#fff !important; font-size:2rem; }
  html[data-theme="dark"] .auth-theme-control { background:rgba(22,35,56,.94); border-color:#354862; color:#e8eef8; }
  html[data-theme="dark"] .auth-theme-control button { background:#263a57; color:#f1f5fb; }
  @media(max-width:575.98px) { .auth-theme-control { top:12px; right:12px; width:44px; height:44px; padding:4px; } .auth-theme-control button { width:34px; height:34px; flex-basis:34px; } }


</style>

<div class="auth-theme-control" role="group" aria-label="Appearance">
  
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
    if (label) label.textContent = dark ? 'Dark mode' : 'Light mode';
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    button.innerHTML = dark ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>';
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

<div class="password-page-wrap">
  <div class="container">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center auth-brand-icon mb-3 shadow-sm" style="width:68px;height:68px;">
        <i class="bi bi-shop fs-1"></i>
      </div>
      <h1 class="fw-bold text-dark mb-1 password-brand-title">ServeFlow POS</h1>
      <p class="text-muted small mb-0">Restaurant operations, made simple.</p>
    </div>

    <?php if (!empty($flashes)): ?>
      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show shadow-sm" role="alert">
          <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <h2 class="h5 mb-0 fw-semibold text-secondary d-flex align-items-center gap-2">
          <i class="bi bi-shield-lock text-primary"></i> Set New Secure Password
        </h2>
      </div>
      <div class="card-body p-4">
        <p class="text-muted small mb-4">Your account was provisioned with a default password. Choose a private, secure password before continuing.</p>
        <form method="POST" action="<?= BASE_URL ?>/change-password" id="changePasswordForm">
          <?= $csrf_field ?>

          <div class="mb-3">
            <label for="new_password" class="form-label fw-medium text-secondary small">New Password (min. 8 characters)</label>
            <input type="password" class="form-control ps-3" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password" autocomplete="new-password" autofocus>
          </div>

          <div class="mb-4">
            <label for="confirm_password" class="form-label fw-medium text-secondary small">Confirm New Password</label>
            <input type="password" class="form-control ps-3" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Update Password & Proceed
          </button>
        </form>
      </div>
    </div>
    <div class="text-center mt-3">
      <span class="text-muted small">Restaurant operations, made simple.</span>
    </div>
  </div>
</div>
