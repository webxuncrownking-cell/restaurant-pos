<?php
$restaurantName = (string)($restaurant['restaurant_name'] ?? 'Restaurant');
$logoPath = trim((string)($restaurant['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? BASE_URL . '/' . ltrim($logoPath, '/') : '';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#f4f7fb">
<title>Join the Waiting List · <?= $e($restaurantName) ?></title>
<style>
:root{color-scheme:light;--ink:#182338;--muted:#66758c;--line:#e1e8f1;--blue:#1769e8;--surface:#fff;--bg:#f3f6fb}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px}
.wrap{width:min(100%,520px)}.brand{text-align:center;margin-bottom:22px}.logo{width:64px;height:64px;border-radius:18px;object-fit:cover;background:#fff;border:1px solid var(--line)}h1{font-size:1.7rem;line-height:1.2;margin:12px 0 6px;letter-spacing:-.035em}.muted{color:var(--muted);margin:0}.card{background:var(--surface);border:1px solid var(--line);border-radius:22px;padding:26px;box-shadow:0 14px 40px rgba(28,49,82,.08)}.intro{margin-bottom:22px}.field{margin-bottom:16px}label{display:block;font-weight:700;font-size:.9rem;margin-bottom:7px}input,select,textarea{display:block;width:100%;min-height:48px;padding:11px 13px;border:1px solid #cfd9e6;border-radius:11px;background:#fff;color:var(--ink);font:inherit;outline:none}input:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(23,105,232,.13)}textarea{resize:vertical;min-height:84px}.grid{display:grid;grid-template-columns:1fr 130px;gap:12px}.btn{width:100%;border:0;border-radius:12px;min-height:50px;background:var(--blue);color:white;font:700 1rem inherit;cursor:pointer;padding:13px 16px}.btn:hover{background:#0d55c5}.error{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#b42318;margin-bottom:18px;font-size:.9rem}.privacy{font-size:.8rem;color:var(--muted);margin:14px 0 0;text-align:center}.trap{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}@media(max-width:480px){body{padding:16px}.card{padding:20px}.grid{grid-template-columns:1fr 110px}h1{font-size:1.5rem}}
</style>
</head>
<body><main class="wrap">
<header class="brand"><?php if ($logoUrl !== ''): ?><img class="logo" src="<?= $e($logoUrl) ?>" alt=""><?php else: ?><div class="logo" style="display:inline-grid;place-items:center;font-size:28px">🍽</div><?php endif; ?>
<h1><?= $e($restaurantName) ?></h1><p class="muted">Join our waiting list</p></header>
<section class="card">
<div class="intro"><h2 style="font-size:1.15rem;margin:0 0 5px">Get in the queue</h2><p class="muted">Tell us who's joining. We'll show your queue position after you sign up.</p></div>
<?php if ($error !== ''): ?><div class="error" role="alert"><?= $e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $e(BASE_URL . '/waitlist/' . $token . '/join') ?>">
<input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
<div class="trap" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="field"><label for="customer_name">Your name *</label><input id="customer_name" name="customer_name" maxlength="120" autocomplete="name" required placeholder="Full name"></div>
<div class="field"><label for="phone">Mobile number *</label><input id="phone" name="phone" maxlength="30" autocomplete="tel" required placeholder="e.g. 09XX XXX XXXX"></div>
<div class="grid"><div class="field"><label for="party_size">Number of guests *</label><select id="party_size" name="party_size" required><?php for ($i=1;$i<=30;$i++): ?><option value="<?= $i ?>" <?= $i===2?'selected':'' ?>><?= $i ?> <?= $i===1?'guest':'guests' ?></option><?php endfor; ?></select></div><div class="field"><label for="notes">Requests</label><input id="notes" name="notes" maxlength="500" placeholder="Optional"></div></div>
<button class="btn" type="submit">Join waiting list</button>
<p class="privacy">Your details are used by this restaurant to manage your visit. Please don't submit sensitive information.</p>
</form></section><p class="muted" style="text-align:center;font-size:.78rem;margin-top:18px">Powered by ServeFlow POS</p>
</main></body></html>