<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';
?>
<div class="container py-4">
  <h1 class="h3 fw-bold mb-1">Scheduled Menu Pricing</h1>
  <p class="text-muted mb-4">Create happy-hour and day/time-specific menu price rules.</p>
  <?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?></div><?php elseif (isset($_GET['saved']) || isset($_GET['updated'])): ?><div class="alert alert-success">Pricing rule saved.</div><?php endif; ?>
  <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
    <form method="post" action="<?= BASE_URL ?>/pricing/save" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
      <div class="col-md-6"><label class="form-label">Rule name</label><input class="form-control" name="name" maxlength="120" placeholder="Happy Hour" required></div>
      <div class="col-md-6"><label class="form-label">Menu item</label><select class="form-select" name="product_id" required><option value="">Choose item</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> — ₱<?= number_format((float)$p['base_price'], 2) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Start time</label><input type="time" class="form-control" name="starts_at" required></div>
      <div class="col-md-3"><label class="form-label">End time</label><input type="time" class="form-control" name="ends_at" required></div>
      <div class="col-md-3"><label class="form-label">Price type</label><select class="form-select" name="price_type"><option value="fixed">Set final price (₱)</option><option value="percent_off">Percent off (%)</option></select></div>
      <div class="col-md-3"><label class="form-label">Value</label><input type="number" min="0" step="0.01" class="form-control" name="price_value" required></div>
      <div class="col-12"><label class="form-label d-block">Days active</label><?php foreach ([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $n=>$day): ?><label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="<?= $n ?>" checked><span class="form-check-label"><?= $day ?></span></label><?php endforeach; ?></div>
      <div class="col-md-3"><label class="form-label">Starts on (optional)</label><input type="date" class="form-control" name="starts_on"></div>
      <div class="col-md-3"><label class="form-label">Ends on (optional)</label><input type="date" class="form-control" name="ends_on"></div>
      <div class="col-12"><button class="btn btn-primary" type="submit">Save pricing rule</button></div>
    </form>
  </div></div>
  <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-0"><div class="p-3 border-bottom"><h2 class="h5 fw-bold mb-0">Existing rules</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Rule / Item</th><th>Schedule</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
  <?php if (!$rules): ?><tr><td colspan="5" class="text-center text-muted py-4">No rules configured.</td></tr><?php endif; ?>
  <?php foreach ($rules as $r): ?><tr><td><strong><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8') ?></div></td><td><?= htmlspecialchars(substr((string)$r['starts_at'],0,5), ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars(substr((string)$r['ends_at'],0,5), ENT_QUOTES, 'UTF-8') ?><div class="small text-muted">Days: <?= htmlspecialchars($r['days_of_week'], ENT_QUOTES, 'UTF-8') ?></div></td><td><?= $r['price_type']==='fixed'?'Set ₱'.number_format((float)$r['price_value'],2):number_format((float)$r['price_value'],1).'% off' ?></td><td><span class="badge text-bg-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Active':'Paused' ?></span></td><td><form method="post" action="<?= BASE_URL ?>/pricing/toggle"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="is_active" value="<?= $r['is_active']?0:1 ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['is_active']?'Pause':'Activate' ?></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
