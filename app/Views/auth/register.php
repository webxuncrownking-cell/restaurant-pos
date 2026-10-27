<style>
  .register-page-wrap {
    min-height: calc(100vh - 58px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(20px, 4vh, 44px) 18px;
  }
  .register-page-wrap > .container {
    width: 100%;
    max-width: 760px;
    margin: 0 !important;
    padding: 0 !important;
  }
  .register-page-wrap .register-brand-title {
    font-size: clamp(1.8rem, 2.2vw, 2.15rem);
    letter-spacing: -.035em;
  }
  @media (max-height: 900px) {
    .register-page-wrap { align-items: flex-start; padding-top: 22px; padding-bottom: 22px; }
  }

/* ServeFlow registration form refresh */
.register-page-wrap { min-height:100vh; padding:clamp(24px,4vh,52px) 20px; background:radial-gradient(ellipse at 50% 0%, #eaf1ff 0, #f5f7fb 48%, #eef2f8 100%); }
html[data-theme="dark"] .register-page-wrap { background:radial-gradient(ellipse at 50% 0%, #1b2b45 0, #0b1220 58%, #080e19 100%); }
.register-page-wrap > .container { max-width:700px; }
.register-page-wrap .register-brand-title { color:#14213a !important; font-size:2rem; font-weight:800; letter-spacing:-.045em; }
.register-page-wrap .card { border:1px solid #e0e7f1 !important; border-radius:20px !important; background:#fff !important; box-shadow:0 22px 60px rgba(20,38,68,.12) !important; }
.register-page-wrap .card-header { background:#fff !important; color:#18263d !important; border-bottom:1px solid #e7edf5 !important; padding:19px 24px !important; }
.register-page-wrap .card-header h2 { color:#18263d !important; font-weight:750; }
.register-page-wrap .card-body { padding:clamp(20px,4vw,32px) !important; }
.register-page-wrap .form-label { color:#34445c !important; font-size:.88rem !important; font-weight:700 !important; }
.register-page-wrap .form-control { min-height:47px; background:#f8fafd !important; color:#18263d !important; border:1px solid #d5deeb !important; border-radius:11px !important; }
.register-page-wrap .form-control:focus { background:#fff !important; border-color:#4b83e6 !important; box-shadow:0 0 0 4px rgba(59,130,246,.13) !important; }
.register-page-wrap .form-text { color:#687890 !important; }
.register-page-wrap .btn-warning { min-height:49px; border:0 !important; border-radius:11px !important; background:#2563eb !important; color:#fff !important; box-shadow:0 7px 18px rgba(37,99,235,.2) !important; }
.register-page-wrap .btn-warning:hover { background:#1d4ed8 !important; }
.register-page-wrap .text-muted { color:#687890 !important; }
html[data-theme="dark"] .register-page-wrap .register-brand-title { color:#f1f5fb !important; }
html[data-theme="dark"] .register-page-wrap .text-muted,
html[data-theme="dark"] .register-page-wrap .form-text { color:#a6b4c9 !important; }
html[data-theme="dark"] .register-page-wrap .card,
html[data-theme="dark"] .register-page-wrap .card-header,
html[data-theme="dark"] .register-page-wrap .card-body { background:#162338 !important; color:#e8eef8 !important; border-color:#2a3a53 !important; }
html[data-theme="dark"] .register-page-wrap .card-header h2,
html[data-theme="dark"] .register-page-wrap .form-label { color:#e8eef8 !important; }
html[data-theme="dark"] .register-page-wrap .form-control { background:#0d1727 !important; color:#f1f5fb !important; border-color:#354862 !important; }
html[data-theme="dark"] .register-page-wrap .form-control::placeholder { color:#8394ad !important; }


  .auth-theme-control { position:fixed; top:20px; right:22px; z-index:1200; display:flex; align-items:center; gap:9px; padding:8px 12px; border:1px solid #dbe4f0; border-radius:999px; background:rgba(255,255,255,.92); color:#334155; box-shadow:0 8px 24px rgba(15,23,42,.12); backdrop-filter:blur(12px); font-size:.82rem; font-weight:700; }
  .auth-theme-control button { display:flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:0; border-radius:50%; background:#e8eef8; color:#27456f; cursor:pointer; }
  .auth-theme-control button:focus-visible { outline:3px solid #60a5fa; outline-offset:2px; }
  .auth-brand-icon { width:68px; height:68px; display:inline-flex; align-items:center; justify-content:center; border-radius:20px !important; background:#2563eb !important; color:#fff !important; border:1px solid rgba(255,255,255,.35); box-shadow:0 10px 24px rgba(37,99,235,.28) !important; }
  .auth-brand-icon i { color:#fff !important; font-size:2rem; }
  html[data-theme="dark"] .auth-theme-control { background:rgba(22,35,56,.94); border-color:#354862; color:#e8eef8; }
  html[data-theme="dark"] .auth-theme-control button { background:#263a57; color:#dce9fb; }
  @media(max-width:575.98px) { .auth-theme-control { top:12px; right:12px; padding:6px 9px; font-size:.75rem; } .auth-theme-control button { width:30px; height:30px; } }


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
<div class="register-page-wrap">
<div class="container">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center auth-brand-icon mb-3 shadow-sm" style="width:68px;height:68px;"><i class="bi bi-shop fs-1"></i></div>
    <h1 class="fw-bold text-dark mb-1 register-brand-title">ServeFlow POS</h1>
    <p class="text-muted small mb-0">Restaurant operations, made simple.</p>
  </div>

  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white px-4 py-3"><h2 class="h5 mb-0"><i class="bi bi-person-plus text-primary me-2"></i>Restaurant and owner details</h2></div>
    <div class="card-body p-4">
      <form method="POST" action="<?= BASE_URL ?>/register" autocomplete="on">
        <?= $csrf_field ?>
        <div class="mb-3">
          <label class="form-label" for="restaurant_name">Restaurant / Carinderia Name</label>
          <input class="form-control" id="restaurant_name" name="restaurant_name" maxlength="150" required autocomplete="organization">
        </div>
        <div class="mb-3">
          <label class="form-label" for="owner_name">Owner's Full Name</label>
          <input class="form-control" id="owner_name" name="owner_name" maxlength="100" required autocomplete="name">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="email">Email Address</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="100" required autocomplete="email">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Contact Number</label>
            <input class="form-control" type="tel" id="phone" name="phone" maxlength="30" required autocomplete="tel">
          </div>
        </div>
        <hr class="my-4">
        <h3 class="h6 fw-semibold mb-3">Create your login</h3>
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input class="form-control" id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" required autocomplete="username">
          <div class="form-text">Use 3–50 letters, numbers, dots, underscores, or hyphens.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" minlength="10" required autocomplete="new-password">
            <div class="form-text">At least 10 characters.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirm Password</label>
            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="10" required autocomplete="new-password">
          </div>
        </div>
        <div class="alert alert-info small mt-4 mb-3"><i class="bi bi-info-circle me-1"></i> Your restaurant will be created with an Owner account and a 14-day trial on the lowest-priced active plan. No payment is collected by this form.</div>
        <button type="submit" class="btn btn-warning text-dark fw-semibold w-100 py-2"><i class="bi bi-person-check me-2"></i>Create Restaurant Account</button>
      </form>
    </div>
  </div>
  <p class="text-center text-muted mt-4 mb-0">Already registered? <a href="<?= BASE_URL ?>/login" class="fw-semibold text-decoration-none">Sign in</a></p>
</div>
</div>
