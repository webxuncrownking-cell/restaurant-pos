<?php
  $rememberedLogin = trim((string)($remembered_login ?? ''));
  $hasRememberedLogin = $rememberedLogin !== '';
?>
<style>
.terminal-login-page {
  --terminal-brand:#1769d2;
  --terminal-brand-dark:#1555ad;
  width:100%;
  min-height:100vh;
  box-sizing:border-box;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  padding:42px 18px 34px;
  background:linear-gradient(135deg,#f4f7fc 0%,#eaf1fb 100%);
  color:#172033;
}
.terminal-login-shell {
  width:min(100%,620px);
  margin:0 auto;
  display:flex;
  flex-direction:column;
  align-items:stretch;
  box-sizing:border-box;
}
.terminal-login-brand {
  width:100%;
  max-width:none;
  text-align:center;
  margin:0 auto 28px;
}
.terminal-login-brand-icon {
  width:88px;
  height:88px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:22px;
  background:#2563eb;
  color:#fff;
  font-size:2.7rem;
  box-shadow:0 12px 24px rgba(37,99,235,.22);
  margin-bottom:18px;
}
.terminal-login-brand h1 {
  margin:0;
  color:#172033;
  font-size:2.45rem;
  line-height:1.15;
  font-weight:800;
  letter-spacing:-.045em;
}
.terminal-login-brand p {
  margin:8px 0 0;
  color:#718096;
  font-size:1.12rem;
}
.terminal-login-card {
  width:100%;
  max-width:none;
  box-sizing:border-box;
  background:#fff;
  border:1px solid #e2e9f3;
  border-radius:24px;
  box-shadow:0 18px 48px rgba(24,53,94,.11);
  overflow:hidden;
}
.terminal-login-body { padding:30px 48px 36px; }
.terminal-login-title {
  margin:0 0 24px;
  color:#1d2b42;
  text-align:center;
  font-size:1.35rem;
  line-height:1.3;
  font-weight:750;
}
.pin-instruction {
  display:block;
  width:100%;
  margin:18px auto 12px;
  color:#536783;
  text-align:center;
  font-size:1rem;
  line-height:1.4;
  font-weight:700;
}
.terminal-login-alert { font-size:.95rem;border-radius:11px; }
.terminal-login-user {
  display:flex;align-items:center;justify-content:center;gap:11px;
  margin:0 auto 8px;color:#24364f;font-size:1.12rem;font-weight:750;
}
.terminal-login-user .user-avatar {
  width:42px;height:42px;border-radius:13px;display:inline-flex;align-items:center;justify-content:center;
  background:#eaf2ff;color:var(--terminal-brand);font-size:1.2rem;
}
.terminal-login-change {
  display:block;margin:0 auto 22px;padding:5px 9px;border:0;background:transparent;
  color:var(--terminal-brand);font-weight:700;font-size:.92rem;
}
.terminal-login-page .form-label { color:#34445c;font-weight:700;font-size:1rem;margin-bottom:8px; }
.terminal-login-page .form-control {
  min-height:56px;border:1px solid #d8e1ee;border-radius:12px;
  background:#f8fafd;color:#1d2b42;font-size:1.05rem;padding:13px 15px;
}
.terminal-login-page .form-control:focus {
  border-color:#7eaceb;box-shadow:0 0 0 3px rgba(23,105,210,.12);background:#fff;
}
.pin-capture {
  position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;
  overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important;
}
.pin-dots { display:flex;justify-content:center;gap:15px;margin:8px 0 22px; }
.pin-dot {
  width:16px;height:16px;border:2px solid #b7c7dc;border-radius:50%;
  background:#fff;transition:background .12s,border-color .12s,transform .12s;
}
.pin-dot.filled { background:var(--terminal-brand);border-color:var(--terminal-brand);transform:scale(1.06); }
.pin-keypad { display:grid;grid-template-columns:repeat(3,1fr);gap:13px;max-width:390px;margin:0 auto 20px; }
.pin-key {
  width:100%;height:76px;border:1px solid #e0e8f3;border-radius:16px;
  background:#f8fafd;color:#263b59;font-size:1.55rem;font-weight:700;
  transition:background .12s,border-color .12s,transform .12s;
}
.pin-key:hover { background:#eaf2ff;border-color:#b8d1f5;color:var(--terminal-brand); }
.pin-key:active { transform:scale(.97); }
.pin-key.utility { color:#5d6e85;font-size:1.15rem; }
.pin-key.confirm { background:#eaf2ff;border-color:#c8dcfa;color:var(--terminal-brand);font-size:1.3rem; }
.terminal-login-remember {
  display:flex;align-items:center;justify-content:center;gap:9px;
  color:#53647c;font-size:.92rem;margin:16px 0 20px;
}
.terminal-login-remember input { width:18px;height:18px;accent-color:var(--terminal-brand); }
.terminal-login-submit {
  min-height:58px;border:0;border-radius:12px;background:var(--terminal-brand);color:#fff;
  font-size:1.05rem;font-weight:750;box-shadow:0 5px 12px rgba(23,105,210,.18);
}
.terminal-login-submit:hover { background:var(--terminal-brand-dark);color:#fff; }
.terminal-login-submit:focus-visible,.pin-key:focus-visible,.terminal-login-change:focus-visible {
  outline:3px solid rgba(23,105,210,.28);outline-offset:2px;
}
.terminal-login-links {
  display:block;
  position:static;
  float:none;
  clear:both;
  flex:0 0 auto;
  width:100%;
  max-width:none;
  box-sizing:border-box;
  text-align:center;
  margin:22px 0 0!important;
  padding:0 8px;
  font-size:.95rem;
}
.terminal-login-links a { color:var(--terminal-brand);text-decoration:none;font-weight:700; }
.terminal-login-links a:hover { text-decoration:underline; }
@media(max-width:520px) {
 .terminal-login-page{padding:28px 12px 24px;justify-content:flex-start}
 .terminal-login-shell{width:100%;max-width:440px}
 .terminal-login-brand{margin-bottom:22px}
 .terminal-login-brand-icon{width:70px;height:70px;border-radius:18px;font-size:2.15rem;margin-bottom:13px}
 .terminal-login-brand h1{font-size:1.85rem}
 .terminal-login-brand p{font-size:.95rem}
 .terminal-login-card{max-width:440px;border-radius:20px}
.terminal-login-body{padding:22px 22px 28px}
 .terminal-login-title{font-size:1.2rem;margin-bottom:20px}
 .pin-instruction{margin-top:16px}
 .terminal-login-title{font-size:1.2rem;margin-bottom:20px}
 .pin-instruction{margin-top:16px}
 .pin-keypad{max-width:340px;gap:9px}
 .pin-key{height:64px}
 .pin-dot{width:14px;height:14px}
 .terminal-login-links{margin:18px 0 0!important;font-size:.88rem}
}
</style>

<div class="terminal-login-page">
 <div class="terminal-login-shell">
 <div class="terminal-login-brand">
  <div class="terminal-login-brand-icon"><i class="bi bi-shop"></i></div>
  <h1>ServeFlow POS</h1>
  <p>Restaurant operations, made simple.</p>
 </div>

 <div class="terminal-login-card">
  <div class="terminal-login-body">

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
    <h2 class="terminal-login-title">Staff Login</h2>

    <div id="rememberedStaff" <?= $hasRememberedLogin ? '' : 'hidden' ?>>
     <div class="terminal-login-user">
      <span class="user-avatar"><i class="bi bi-person-fill"></i></span>
      <span id="rememberedStaffName"><?= htmlspecialchars($rememberedLogin, ENT_QUOTES, 'UTF-8') ?></span>
     </div>
     <input type="hidden" id="rememberedLogin" name="login" value="<?= htmlspecialchars($rememberedLogin, ENT_QUOTES, 'UTF-8') ?>">
     <input type="hidden" name="remember_login" value="1" id="keepRememberedLogin">
     <button type="button" class="terminal-login-change" id="switchStaffButton">Not you? Switch account</button>
    </div>

    <div id="staffLoginField" <?= $hasRememberedLogin ? 'hidden' : '' ?> class="mb-3">
     <label class="form-label" for="loginInput">Staff username or email</label>
     <input class="form-control" id="loginInput" name="<?= $hasRememberedLogin ? 'alternate_login' : 'login' ?>" maxlength="100" placeholder="Enter your username" autocomplete="username" <?= $hasRememberedLogin ? '' : 'required' ?>>
    </div>

    <div class="pin-instruction">Enter your 4–6 digit PIN</div>
    <input class="pin-capture" id="pin" name="pin" type="password" inputmode="numeric"
      pattern="[0-9]{4,6}" minlength="4" maxlength="6" required autocomplete="one-time-code"
      aria-label="Staff PIN" tabindex="0">
    <div class="pin-dots" id="pinDots" aria-label="PIN entry progress">
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
     <button type="button" class="pin-key" data-digit="0">0</button>
     <button type="button" class="pin-key utility" id="deletePin" aria-label="Delete last PIN digit"><i class="bi bi-backspace"></i></button>
     <button type="button" class="pin-key confirm" id="confirmPin" aria-label="Submit PIN"><i class="bi bi-check-lg"></i></button>
    </div>

    <label class="terminal-login-remember" id="rememberOption" <?= $hasRememberedLogin ? 'hidden' : '' ?>>
     <input type="checkbox" name="remember_login" value="1">
     <span>Remember this username on this device</span>
    </label>

    <button type="submit" class="btn terminal-login-submit w-100" id="signInButton" <?= $hasRememberedLogin ? 'hidden' : '' ?>>
     <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to POS
    </button>
   </form>
  </div>
 </div>

 <div class="terminal-login-links">
  <a href="<?= BASE_URL ?>/login">Owner / Admin sign in</a>
  <span class="text-muted mx-2">·</span>
  <a href="<?= BASE_URL ?>/register">Register restaurant</a>
 </div>
 </div>
</div>

<script>
(function () {
 const form=document.getElementById('terminalPinForm');
 if(!form)return;
 const pin=document.getElementById('pin');
 const dots=Array.from(document.querySelectorAll('.pin-dot'));
 const remembered=document.getElementById('rememberedStaff');
 const rememberedLogin=document.getElementById('rememberedLogin');
 const loginField=document.getElementById('staffLoginField');
 const loginInput=document.getElementById('loginInput');
 const switchButton=document.getElementById('switchStaffButton');
 const rememberOption=document.getElementById('rememberOption');
 const signInButton=document.getElementById('signInButton');
 const keepRemembered=document.getElementById('keepRememberedLogin');
 const rememberedOnLoad=!!(remembered&&!remembered.hidden);
 let submitting=false;
 function updateDots(){dots.forEach((dot,i)=>dot.classList.toggle('filled',i<pin.value.length));}
 function submitPin(){
  const login=remembered&&!remembered.hidden?rememberedLogin.value.trim():(loginInput?loginInput.value.trim():'');
  if(submitting||!login||!/^\d{4,6}$/.test(pin.value))return;
  submitting=true;form.requestSubmit();
 }
 function addDigit(digit){
  if(submitting||pin.value.length>=6)return;
  pin.value+=digit;updateDots();
  if(rememberedOnLoad&&pin.value.length===6)window.setTimeout(submitPin,180);
 }
 document.querySelectorAll('[data-digit]').forEach(btn=>btn.addEventListener('click',()=>addDigit(btn.dataset.digit)));
 document.getElementById('deletePin').addEventListener('click',()=>{if(submitting)return;pin.value=pin.value.slice(0,-1);updateDots();});
 document.getElementById('confirmPin').addEventListener('click',submitPin);
 pin.addEventListener('input',()=>{pin.value=pin.value.replace(/\D/g,'').slice(0,6);updateDots();});
 pin.addEventListener('keydown',e=>{
  if(/^\d$/.test(e.key)){e.preventDefault();addDigit(e.key);}
  else if(e.key==='Backspace'){e.preventDefault();pin.value=pin.value.slice(0,-1);updateDots();}
  else if(e.key==='Enter'){e.preventDefault();submitPin();}
 });
 if(switchButton)switchButton.addEventListener('click',()=>{
  remembered.hidden=true;loginField.hidden=false;loginInput.name='login';loginInput.required=true;
  rememberedLogin.disabled=true;if(keepRemembered)keepRemembered.disabled=true;
  rememberOption.hidden=false;signInButton.hidden=false;loginInput.focus();
 });
 form.addEventListener('submit',e=>{
  const login=remembered&&!remembered.hidden?rememberedLogin.value.trim():(loginInput?loginInput.value.trim():'');
  if(!login||!/^\d{4,6}$/.test(pin.value)){e.preventDefault();submitting=false;if(!login&&loginInput)loginInput.focus();}
 });
 updateDots();
})();
</script>
