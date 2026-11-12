<?php
  $rememberedLogin = trim((string)($remembered_login ?? ''));
  $hasRememberedLogin = $rememberedLogin !== '';
?>
<style>
.terminal-login-page {
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  padding:30px 18px; color:#fff;
  background:linear-gradient(145deg,#38a5ed 0%,#2787ed 48%,#2466e8 100%);
}
.terminal-login-card {
  width:100%; max-width:470px; border:0; border-radius:26px; overflow:hidden;
  background:rgba(255,255,255,.20); box-shadow:0 22px 55px rgba(10,55,130,.22);
  backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
}
.terminal-login-head { padding:28px 30px 12px; text-align:center; color:#fff; }
.terminal-login-icon {
  width:72px;height:72px;display:inline-flex;align-items:center;justify-content:center;
  color:#fff;font-size:2.6rem;margin-bottom:10px;
}
.terminal-login-head h1 { color:#fff!important;font-size:1.8rem;font-weight:800;letter-spacing:-.035em; }
.terminal-login-head p { color:rgba(255,255,255,.94)!important;font-size:1.05rem; }
.terminal-login-body { padding:18px 30px 30px; color:#fff; }
.terminal-login-title { color:#fff;font-size:1.12rem;font-weight:700;text-align:center;margin-bottom:8px; }
.terminal-login-subtitle { color:rgba(255,255,255,.92);font-size:.95rem;text-align:center;margin-bottom:22px; }
.terminal-login-alert { color:#163b63; }
.terminal-login-user {
  display:flex;align-items:center;justify-content:center;gap:10px;
  margin:0 auto 16px;color:#fff;font-size:1.05rem;font-weight:700;
}
.terminal-login-user .user-avatar {
  width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.24);border:1px solid rgba(255,255,255,.45);font-size:1.1rem;
}
.terminal-login-page .form-label { color:#fff;font-weight:700;font-size:.95rem;margin-bottom:8px; }
.terminal-login-page .form-control {
  min-height:54px;border:1px solid rgba(255,255,255,.5);border-radius:13px;
  background:rgba(255,255,255,.96);color:#20334d;font-size:1rem;padding:12px 15px;
}
.terminal-login-page .form-control:focus { border-color:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.2); }
.terminal-login-change {
  display:block;margin:0 auto 18px;padding:4px 8px;border:0;background:transparent;
  color:#fff;text-decoration:underline;font-weight:600;font-size:.9rem;
}
.terminal-login-remember { display:flex;align-items:center;justify-content:center;gap:9px;color:#fff;font-size:.9rem;margin:14px 0 20px; }
.terminal-login-remember input { width:18px;height:18px;accent-color:#fff; }
.pin-dots { display:flex;justify-content:center;gap:11px;margin:12px 0 20px; }
.pin-dot { width:15px;height:15px;border:2px solid rgba(255,255,255,.8);border-radius:50%;background:transparent; }
.pin-dot.filled { background:#fff;border-color:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.12); }
.pin-entry { text-align:center;letter-spacing:.45em;font-size:1.35rem!important;font-weight:700; }
.pin-keypad { display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:330px;margin:0 auto 22px; }
.pin-key {
  width:100%;aspect-ratio:1/1;max-height:82px;border:0;border-radius:50%;
  background:#edf5ff;color:#34445c;font-size:1.65rem;font-weight:500;
  box-shadow:0 5px 10px rgba(15,55,110,.14);transition:transform .12s,background .12s;
}
.pin-key:hover { background:#fff;transform:translateY(-2px); }
.pin-key:active { transform:scale(.96); }
.pin-key.utility { font-size:1.25rem; }
.pin-key-spacer { visibility:hidden; }
.terminal-login-submit {
  min-height:56px;border:0;border-radius:13px;background:#fff;color:#2168d8;
  font-size:1rem;font-weight:800;box-shadow:0 7px 18px rgba(15,55,110,.14);
}
.terminal-login-submit:hover { background:#f1f6ff;color:#174fae; }
.terminal-login-links { text-align:center;margin-top:22px;font-size:.92rem; }
.terminal-login-links a { color:#fff;text-decoration:none;font-weight:700; }
.terminal-login-links a:hover { text-decoration:underline; }
.terminal-login-help { text-align:center;color:rgba(255,255,255,.9);font-size:.82rem;margin-top:14px; }
@media(max-width:480px) {
 .terminal-login-page{padding:18px 14px;align-items:center}
 .terminal-login-card{max-width:410px;border-radius:22px}
 .terminal-login-head{padding:22px 20px 8px}
 .terminal-login-body{padding:14px 22px 24px}
 .terminal-login-icon{width:60px;height:60px;font-size:2.2rem}
 .terminal-login-head h1{font-size:1.55rem}
 .pin-keypad{gap:10px;max-width:290px}
 .pin-key{max-height:72px;font-size:1.45rem}
}
</style>

<div class="terminal-login-page">
 <div class="terminal-login-card">
  <div class="terminal-login-head">
   <div class="terminal-login-icon"><i class="bi bi-shop"></i></div>
   <h1 class="mb-1">ServeFlow POS</h1>
   <p class="mb-0">Restaurant operations, made simple.</p>
  </div>
  <div class="terminal-login-body">
   <div class="terminal-login-title">Cashier PIN Sign In</div>
   <div class="terminal-login-subtitle">Enter your staff PIN to open the POS terminal.</div>

   <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
     <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show terminal-login-alert" role="alert">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>
    <?php endforeach; ?>
   <?php endif; ?>

   <form method="POST" action="<?= BASE_URL ?>/terminal-login" autocomplete="off" id="terminalPinForm">
    <?= $csrf_field ?>

    <div id="rememberedStaff" <?= $hasRememberedLogin ? '' : 'hidden' ?>>
     <div class="terminal-login-user">
      <span class="user-avatar"><i class="bi bi-person-fill"></i></span>
      <span id="rememberedStaffName"><?= htmlspecialchars($rememberedLogin, ENT_QUOTES, 'UTF-8') ?></span>
     </div>
     <input type="hidden" id="rememberedLogin" name="login" value="<?= htmlspecialchars($rememberedLogin, ENT_QUOTES, 'UTF-8') ?>">
     <button type="button" class="terminal-login-change" id="switchStaffButton">Switch account</button>
    </div>

    <div id="staffLoginField" <?= $hasRememberedLogin ? 'hidden' : '' ?> class="mb-3">
     <label class="form-label" for="loginInput">Staff username or email</label>
     <input class="form-control" id="loginInput" name="<?= $hasRememberedLogin ? 'alternate_login' : 'login' ?>" maxlength="100" placeholder="Enter staff username" autocomplete="username" <?= $hasRememberedLogin ? '' : 'required' ?>>
    </div>

    <label class="form-label d-block text-center" for="pin">Enter Staff PIN</label>
    <input class="form-control pin-entry mb-2" id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" required autocomplete="one-time-code" placeholder="Enter 4–6 digit PIN" aria-label="Staff PIN">
    <div class="pin-dots" aria-hidden="true">
     <span class="pin-dot"></span><span class="pin-dot"></span><span class="pin-dot"></span>
     <span class="pin-dot"></span><span class="pin-dot"></span><span class="pin-dot"></span>
    </div>

    <div class="pin-keypad" aria-label="PIN keypad">
     <button type="button" class="pin-key" data-digit="1">1</button>
     <button type="button" class="pin-key" data-digit="2">2</button>
     <button type="button" class="pin-key" data-digit="3">3</button>
     <button type="button" class="pin-key" data-digit="4">4</button>
     <button type="button" class="pin-key" data-digit="5">5</button>
     <button type="button" class="pin-key" data-digit="6">6</button>
     <button type="button" class="pin-key" data-digit="7">7</button>
     <button type="button" class="pin-key" data-digit="8">8</button>
     <button type="button" class="pin-key" data-digit="9">9</button>
     <span class="pin-key-spacer" aria-hidden="true"></span>
     <button type="button" class="pin-key" data-digit="0">0</button>
     <button type="button" class="pin-key utility" id="deletePin" aria-label="Delete last PIN digit"><i class="bi bi-backspace-fill"></i></button>
    </div>

    <label class="terminal-login-remember">
     <input type="checkbox" name="remember_login" value="1" <?= $hasRememberedLogin ? 'checked' : '' ?>>
     <span>Remember this username on this device</span>
    </label>

    <button type="submit" class="btn terminal-login-submit w-100"><i class="bi bi-unlock me-2"></i>Verify PIN &amp; Open POS</button>
   </form>

   <div class="terminal-login-help">Forgot your PIN? Contact your restaurant owner or administrator.</div>
   <div class="terminal-login-links">
    <a href="<?= BASE_URL ?>/login">Owner / Admin sign in</a>
    <span class="mx-2">·</span>
    <a href="<?= BASE_URL ?>/register">Register restaurant</a>
   </div>
  </div>
 </div>
</div>

<script>
(function () {
  const form = document.getElementById('terminalPinForm');
  if (!form) return;

  const pin = document.getElementById('pin');
  const dots = Array.from(document.querySelectorAll('.pin-dot'));
  const remembered = document.getElementById('rememberedStaff');
  const rememberedLogin = document.getElementById('rememberedLogin');
  const loginField = document.getElementById('staffLoginField');
  const loginInput = document.getElementById('loginInput');
  const switchButton = document.getElementById('switchStaffButton');
  const rememberCheckbox = form.querySelector('input[name="remember_login"]');

  function updateDots() {
    const count = pin.value.length;
    dots.forEach((dot, index) => dot.classList.toggle('filled', index < count));
  }

  document.querySelectorAll('[data-digit]').forEach(button => {
    button.addEventListener('click', () => {
      if (pin.value.length >= 6) return;
      pin.value += button.dataset.digit;
      updateDots();
    });
  });

  document.getElementById('deletePin').addEventListener('click', () => {
    pin.value = pin.value.slice(0, -1);
    updateDots();
  });

  pin.addEventListener('input', () => {
    pin.value = pin.value.replace(/\D/g, '').slice(0, 6);
    updateDots();
  });

  if (switchButton) {
    switchButton.addEventListener('click', () => {
      remembered.hidden = true;
      loginField.hidden = false;
      loginInput.name = 'login';
      loginInput.required = true;
      rememberedLogin.disabled = true;
      loginInput.focus();
    });
  }

  form.addEventListener('submit', event => {
    const login = remembered.hidden ? loginInput.value.trim() : rememberedLogin.value.trim();
    if (!login) {
      event.preventDefault();
      loginInput.focus();
      return;
    }
    if (!/^[0-9]{4,6}$/.test(pin.value)) {
      event.preventDefault();
      pin.focus();
      return;
    }
    if (!remembered.hidden) {
      rememberedLogin.disabled = false;
    }
  });

  updateDots();
})();
</script>
