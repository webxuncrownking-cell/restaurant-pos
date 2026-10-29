<?php
$e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$name=(string)($restaurant['restaurant_name']??'Restaurant');
$logoPath=trim((string)($restaurant['logo_path']??''));
$logoUrl=$logoPath===''?'':(preg_match('#^https?://#i',$logoPath)?$logoPath:rtrim(BASE_URL,'/').'/'.ltrim($logoPath,'/'));
$today=(new DateTimeImmutable('today'))->format('Y-m-d');
$max=(new DateTimeImmutable('today'))->modify('+90 days')->format('Y-m-d');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f3f6fb"><title>Reserve a table · <?= $e($name) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:#f3f6fb;color:#182338;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(100%,560px)}.brand{text-align:center;margin-bottom:22px}.brand-logo{display:block;width:auto;max-width:150px;height:auto;max-height:100px;object-fit:contain;margin:0 auto 12px}.brand h1{font-size:1.55rem;margin:0}.muted{color:#66758c;margin:4px 0 0}.card{background:#fff;border:1px solid #e1e8f1;border-radius:22px;padding:26px;box-shadow:0 14px 40px rgba(28,49,82,.08)}h2{font-size:1.2rem;margin:0 0 6px}.field{margin:0 0 16px}label{display:block;font-weight:700;font-size:.9rem;margin-bottom:7px}input,select,textarea{display:block;width:100%;min-width:0;max-width:100%;min-height:48px;padding:11px 13px;border:1px solid #cfd9e6;border-radius:11px;background:#fff;color:#182338;font:inherit}textarea{resize:vertical;min-height:80px}.grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}.grid>.field{min-width:0}input[type=date]{display:block;box-sizing:border-box;width:100%;min-width:0;max-width:100%;inline-size:100%;min-inline-size:0;appearance:none;-webkit-appearance:none;text-align:left;font-size:16px;padding:11px 12px;overflow:hidden}
input[type=date]::-webkit-date-and-time-value{text-align:left;min-height:1.4em}
input[type=date]::-webkit-calendar-picker-indicator{flex:0 0 auto;margin-left:8px;opacity:.8}.btn{width:100%;border:0;border-radius:12px;min-height:54px;background:#1769e8;color:white;font:inherit;font-size:1.1rem;font-weight:750;cursor:pointer;padding:14px 18px}.btn:disabled{opacity:.55;cursor:not-allowed}.error{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#b42318;margin-bottom:18px}.hint{font-size:.8rem;color:#66758c;margin-top:6px}.footer{text-align:center;color:#66758c;font-size:.78rem;margin-top:18px}.trap{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}@media(max-width:480px){body{display:block;padding:14px 12px}.wrap{width:100%;min-width:0}.card{padding:19px 16px;border-radius:18px}.grid{grid-template-columns:minmax(0,1fr);gap:0}.field{min-width:0}input,select,textarea{min-width:0;max-width:100%}input[type=date]{width:100%;min-width:0;max-width:100%;inline-size:100%;min-inline-size:0;font-size:16px;padding:11px 10px;overflow:hidden}}
</style></head><body><main class="wrap"><header class="brand"><?php if($logoUrl!==''): ?><img class="brand-logo" src="<?= $e($logoUrl) ?>" alt="<?= $e($name) ?> logo"><?php endif; ?><h1><?= $e($name) ?></h1><p class="muted"><?= $e($restaurant['tagline']??'') ?></p><p class="muted">Book a table online</p></header><section class="card"><h2>Make a reservation</h2><p class="muted" style="margin-bottom:20px">Choose a date and party size to see available times. Your request will be pending until the restaurant confirms it.</p>
<?php if(($error??'')!==''): ?><div class="error" role="alert"><?= $e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $e(BASE_URL.'/reserve/'.$token.'/book') ?>" id="bookingForm">
<input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
<div class="trap" aria-hidden="true"><label for="website">Leave empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="field"><label for="customer_name">Full name *</label><input id="customer_name" name="customer_name" maxlength="120" autocomplete="name" required></div>
<div class="grid"><div class="field"><label for="phone">Mobile number *</label><input id="phone" name="phone" maxlength="30" autocomplete="tel" required placeholder="+63..."></div><div class="field"><label for="email">Email address *</label><input id="email" type="email" name="email" maxlength="254" autocomplete="email" required></div></div>
<div class="grid"><div class="field"><label for="reservation_date">Date *</label><input id="reservation_date" type="date" name="reservation_date" min="<?= $e($today) ?>" max="<?= $e($max) ?>" required></div><div class="field"><label for="party_size">Guests *</label><select id="party_size" name="party_size" required><?php for($i=1;$i<=30;$i++): ?><option value="<?= $i ?>" <?= $i===2?'selected':'' ?>><?= $i ?> <?= $i===1?'guest':'guests' ?></option><?php endfor; ?></select></div></div>
<div class="field"><label for="reservation_time">Available time *</label><select id="reservation_time" name="reservation_time" required disabled><option value="">Select a date first</option></select><div class="hint" id="availabilityMessage" aria-live="polite">Available times will appear after you choose a date.</div></div>
<div class="field"><label for="notes">Special requests <span style="font-weight:400;color:#66758c">(optional)</span></label><textarea id="notes" name="notes" maxlength="500" placeholder="High chair, birthday, etc."></textarea></div>
<button class="btn" type="submit" id="submitBooking">Request reservation</button><p class="hint">Submitting a request does not confirm your reservation. We'll notify you when the restaurant confirms it.</p></form></section><p class="footer">Powered by ServeFlow POS</p></main>
<script>
(function(){
 const date=document.getElementById('reservation_date'), party=document.getElementById('party_size'), times=document.getElementById('reservation_time'), msg=document.getElementById('availabilityMessage'), btn=document.getElementById('submitBooking');
 async function loadSlots(){
   times.innerHTML='<option value="">Loading available times…</option>';times.disabled=true;btn.disabled=true;
   if(!date.value){times.innerHTML='<option value="">Select a date first</option>';msg.textContent='Available times will appear after you choose a date.';return;}
   msg.textContent='Checking availability…';
   try{
     const url=<?= json_encode(BASE_URL.'/reserve/'.$token.'/availability') ?>+'?date='+encodeURIComponent(date.value)+'&party_size='+encodeURIComponent(party.value);
     const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});const data=await response.json();
     times.innerHTML='';
     if(!response.ok||!Array.isArray(data.slots)||data.slots.length===0){
       times.innerHTML='<option value="">No available times</option>';msg.textContent=data.error||'No available times for this date and party size. Please choose another date.';return;
     }
     times.add(new Option('Choose a time',''));
     data.slots.forEach(slot=>times.add(new Option(new Date('2000-01-01T'+slot+':00').toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}),slot)));
     times.disabled=false;msg.textContent=data.slots.length+' available time(s). Availability is checked again when you submit.';btn.disabled=false;
   }catch(e){times.innerHTML='<option value="">Unable to load times</option>';msg.textContent='Could not check availability. Please try again.';}
 }
 date.addEventListener('change',loadSlots);party.addEventListener('change',loadSlots);times.addEventListener('change',()=>{btn.disabled=!times.value;});
 document.getElementById('bookingForm').addEventListener('submit',e=>{if(!times.value){e.preventDefault();msg.textContent='Please choose an available time.';}});
})();
</script></body></html>