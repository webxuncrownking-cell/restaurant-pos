<style>
.terminal-login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 18px;background:radial-gradient(ellipse at 50% 0%,#eaf1ff 0,#f5f7fb 52%,#eef2f8 100%)}
.terminal-login-card{width:100%;max-width:440px;border:1px solid #e0e7f1;border-radius:22px;background:#fff;box-shadow:0 22px 60px rgba(20,38,68,.12);overflow:hidden}
.terminal-login-head{padding:28px 28px 20px;text-align:center}
.terminal-login-icon{width:68px;height:68px;display:inline-flex;align-items:center;justify-content:center;border-radius:20px;background:#2563eb;color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.25);font-size:2rem}
.terminal-login-body{padding:8px 28px 28px}
.terminal-login-page .form-label{font-weight:700;color:#34445c;font-size:.88rem}
.terminal-login-page .form-control{min-height:49px;border:1px solid #d5deeb;border-radius:11px;background:#f8fafd;color:#18263d}
.terminal-login-page .form-control:focus{border-color:#4b83e6;box-shadow:0 0 0 4px rgba(59,130,246,.13)}
.terminal-login-page .btn-primary{min-height:50px;border:0;border-radius:11px;background:#2563eb;font-weight:700}
.pin-entry{font-size:1.45rem;letter-spacing:.35em;text-align:center;font-weight:700}
.terminal-login-links a{color:#2563eb;text-decoration:none;font-weight:600}
@media(max-width:480px){.terminal-login-body{padding:8px 20px 22px}.terminal-login-head{padding:24px 20px 16px}}

/* Larger cashier PIN sign-in layout */
.terminal-login-page { padding: 40px 20px !important; }
.terminal-login-card { max-width: 560px !important; border-radius: 24px !important; }
.terminal-login-head { padding: 34px 36px 24px !important; }
.terminal-login-icon { width: 78px !important; height: 78px !important; font-size: 2.3rem !important; }
.terminal-login-head h1 { font-size: 2.2rem !important; }
.terminal-login-head p { font-size: 1rem !important; }
.terminal-login-body { padding: 12px 36px 36px !important; }
.terminal-login-body h2 { font-size: 1.3rem !important; }
.terminal-login-body .text-muted { font-size: .95rem !important; }
.terminal-login-page .form-label { font-size: 1rem !important; margin-bottom: 9px; }
.terminal-login-page .form-control { min-height: 58px !important; font-size: 1.05rem !important; padding: 13px 16px !important; }
.terminal-login-page .btn-primary { min-height: 60px !important; font-size: 1.05rem !important; }
.pin-entry { font-size: 1.7rem !important; }
.terminal-login-links { font-size: .95rem !important; }
@media(max-width:480px) {
  .terminal-login-page { padding: 22px 14px !important; }
  .terminal-login-head { padding: 28px 22px 20px !important; }
  .terminal-login-body { padding: 10px 22px 28px !important; }
  .terminal-login-head h1 { font-size: 1.85rem !important; }
}
</style>
<div class="terminal-login-page">
 <div class="terminal-login-card">
  <div class="terminal-login-head">
   <div class="terminal-login-icon mb-3"><i class="bi bi-shop"></i></div>
   <h1 class="h3 fw-bold mb-1" style="color:#14213a;letter-spacing:-.04em">ServeFlow POS</h1>
   <p class="text-muted mb-0">Restaurant operations, made simple.</p>
  </div>
  <div class="px-4"><div class="border-top"></div></div>
  <div class="terminal-login-body">
   <div class="text-center mb-4">
    <h2 class="h5 fw-bold mb-1" style="color:#18263d"><i class="bi bi-key-fill text-primary me-2"></i>Cashier PIN Sign In</h2>
    <p class="small text-muted mb-0">Enter your staff account and terminal PIN.</p>
   </div>
   <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
     <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>
    <?php endforeach; ?>
   <?php endif; ?>
   <form method="POST" action="<?= BASE_URL ?>/terminal-login" autocomplete="off">
    <?= $csrf_field ?>
    <div class="mb-3">
     <label class="form-label" for="login">Staff Username or Email</label>
     <input class="form-control" id="login" name="login" required maxlength="100" placeholder="Enter your staff username" autocomplete="username">
    </div>
    <div class="mb-4">
     <label class="form-label d-block" for="pin">4–6 Digit PIN</label>
     <input class="form-control pin-entry" id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" required placeholder="••••" autocomplete="one-time-code">
    </div>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-unlock me-2"></i>Open POS Terminal</button>
   </form>
   <div class="terminal-login-links text-center mt-4 small">
    <a href="<?= BASE_URL ?>/login">Owner / Admin sign in</a>
    <span class="text-muted mx-2">·</span>
    <a href="<?= BASE_URL ?>/register">Register restaurant</a>
   </div>
  </div>
 </div>
</div>
