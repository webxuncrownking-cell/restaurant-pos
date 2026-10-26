<div class="container-fluid px-4 py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
    <div>
      <h2 class="h3 fw-bold mb-1"><i class="bi bi-building text-primary me-2"></i>Restaurant Accounts</h2>
      <p class="text-muted small mb-0">View registered tenant accounts, subscription plans, and open a tenant workspace for inspection.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
      <a href="<?= BASE_URL ?>/platform-analytics" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up-arrow me-1"></i>Platform Analytics</a>
    </div>
  </div>

  <?php if (!empty($accounts_error)): ?>
    <div class="alert alert-warning" role="alert"><?= htmlspecialchars($accounts_error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0 fw-semibold">Registered Tenants <span class="badge bg-light text-dark border ms-1"><?= number_format(count($restaurants ?? [])) ?></span></h5>
      <input type="search" id="restaurantAccountSearch" class="form-control form-control-sm" style="max-width:320px" placeholder="Search restaurant, slug, or contact..." aria-label="Search restaurant accounts">
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="restaurantAccountsTable">
        <thead class="table-light small text-uppercase text-muted"><tr><th>ID</th><th>Restaurant</th><th>Slug</th><th>Contact</th><th>Plan</th><th>Account Status</th><th>Subscription</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!empty($restaurants)): foreach ($restaurants as $r): ?>
            <tr>
              <td class="fw-bold">#<?= (int)$r['id'] ?></td>
              <td class="fw-semibold text-dark"><?= htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><code><?= htmlspecialchars((string)($r['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
              <td class="small text-muted"><?= htmlspecialchars((string)($r['contact_email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string)($r['contact_phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="badge bg-info text-dark"><?= htmlspecialchars((string)($r['plan_name'] ?? 'No plan'), ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><span class="badge <?= strtolower((string)($r['status'] ?? '')) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars(ucfirst((string)($r['status'] ?? 'Unknown')), ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= htmlspecialchars(ucfirst((string)($r['sub_status'] ?? 'None')), ENT_QUOTES, 'UTF-8') ?></td>
              <td><a href="<?= BASE_URL ?>/dashboard/switch-tenant?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Inspect Tenant</a></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No tenant restaurants registered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const search = document.getElementById('restaurantAccountSearch');
  const rows = document.querySelectorAll('#restaurantAccountsTable tbody tr');
  if (!search) return;
  search.addEventListener('input', function () {
    const query = search.value.trim().toLowerCase();
    rows.forEach(function (row) {
      row.hidden = !row.textContent.toLowerCase().includes(query);
    });
  });
});
</script>
