<div class="container-fluid px-4 py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
    <div><h2 class="h3 fw-bold mb-1"><i class="bi bi-credit-card text-primary me-2"></i>Plans & Subscriptions</h2><p class="text-muted small mb-0">Manage restaurant subscription status, plan assignments, and trial extensions.</p></div>
    <a href="<?= BASE_URL ?>/restaurant-accounts" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building me-1"></i>Restaurant Accounts</a>
  </div>
  <?php foreach (($flashes ?? []) as $flash): ?><div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
  <?php if (!empty($page_error)): ?><div class="alert alert-warning"><?= htmlspecialchars($page_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Restaurant</th><th>Current Plan</th><th>Status</th><th>Period End / Trial</th><th>Update</th></tr></thead>
        <tbody>
        <?php if (!empty($subscriptions)): foreach ($subscriptions as $s): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($s['restaurant_name'], ENT_QUOTES, 'UTF-8') ?><div class="small text-muted">Subscription #<?= (int)$s['subscription_id'] ?></div></td>
            <td><?= htmlspecialchars($s['plan_name'], ENT_QUOTES, 'UTF-8') ?><div class="small text-muted"><?= htmlspecialchars(ucfirst($s['billing_cycle']), ENT_QUOTES, 'UTF-8') ?></div></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($s['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><div><?= htmlspecialchars((string)$s['current_period_end'], ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted">Trial: <?= htmlspecialchars((string)($s['trial_ends_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
            <td style="min-width:360px">
              <form method="POST" action="<?= BASE_URL ?>/platform-subscriptions/update" class="d-flex flex-wrap gap-2 align-items-end">
                <?= \App\Core\CSRF::formField() ?>
                <input type="hidden" name="subscription_id" value="<?= (int)$s['subscription_id'] ?>">
                <div><label class="form-label small mb-1">Plan</label><select name="plan_id" class="form-select form-select-sm" required>
                  <?php foreach (($plans ?? []) as $p): if (!(int)$p['is_active']) continue; ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$s['plan_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> — ₱<?= number_format((float)$p['price_monthly'], 2) ?>/mo</option>
                  <?php endforeach; ?>
                </select></div>
                <div><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm" required>
                  <?php foreach (['trial','active','past_due','suspended','cancelled','expired'] as $st): ?><option value="<?= $st ?>" <?= $st === $s['status'] ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option><?php endforeach; ?>
                </select></div>
                <div><label class="form-label small mb-1">Extend (days)</label><input class="form-control form-control-sm" style="width:100px" type="number" min="0" max="365" name="extend_trial_days" value="0"></div>
                <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Apply these subscription changes?')">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?><tr><td colspan="5" class="text-center text-muted py-4">No subscriptions found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-3 mb-0">Changes are recorded in the platform audit log. Extending days adjusts both the trial end date and current period end; it does not charge the customer.</p>
</div>