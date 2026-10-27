<?php
include APP_ROOT . '/app/Views/layouts/header.php';
include APP_ROOT . '/app/Views/layouts/navbar.php';
?>
<div class="container-fluid py-4">
<?php foreach (($flashes ?? []) as $flash): ?>
<div class="alert alert-<?= htmlspecialchars((string)($flash['type']??'info'),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($flash['message']??''),ENT_QUOTES,'UTF-8') ?></div>
<?php endforeach; ?>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="h3 fw-bold">Customer CRM</h1><p class="text-muted">Tenant-scoped customer directory and loyalty-point tracking.</p></div>
<form method="get" action="<?= BASE_URL ?>/customers" class="d-flex gap-2"><input class="form-control" name="q" value="<?= htmlspecialchars((string)$search,ENT_QUOTES,'UTF-8') ?>" placeholder="Search name, phone, email"><button class="btn btn-outline-primary">Search</button></form></div>
<div class="row g-3 mb-4">
<div class="col-md-4"><div class="card card-body"><div class="text-muted small">Customers</div><div class="h3"><?= (int)($summary['total']??0) ?></div></div></div>
<div class="col-md-4"><div class="card card-body"><div class="text-muted small">Active</div><div class="h3"><?= (int)($summary['active_count']??0) ?></div></div></div>
<div class="col-md-4"><div class="card card-body"><div class="text-muted small">Loyalty points held</div><div class="h3"><?= number_format((int)($summary['points']??0)) ?></div><small class="text-muted">Earn 1 point per ₱100 on paid linked orders. Points redemption is not enabled yet.</small></div></div>
</div>
<div class="row g-4"><div class="col-12 col-xl-4"><div class="card card-body"><h2 class="h5"><?= !empty($editCustomer) ? 'Edit customer' : 'Add customer' ?></h2>
<form method="post" action="<?= BASE_URL ?>/customers/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)($editCustomer['id'] ?? 0) ?>">
<label class="form-label">Full name *</label><input class="form-control mb-3" name="full_name" maxlength="160" value="<?= htmlspecialchars((string)($editCustomer['full_name'] ?? ''),ENT_QUOTES,'UTF-8') ?>" required>
<label class="form-label">Phone</label><input class="form-control mb-3" name="phone" maxlength="40" value="<?= htmlspecialchars((string)($editCustomer['phone'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
<label class="form-label">Email</label><input class="form-control mb-3" name="email" type="email" maxlength="190" value="<?= htmlspecialchars((string)($editCustomer['email'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
<label class="form-label">Birthday</label><input class="form-control mb-3" name="birthday" type="date" value="<?= htmlspecialchars((string)($editCustomer['birthday'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
<label class="form-label">Notes</label><textarea class="form-control mb-3" name="notes" rows="3" maxlength="4000"><?= htmlspecialchars((string)($editCustomer['notes'] ?? ''),ENT_QUOTES,'UTF-8') ?></textarea><button class="btn btn-primary w-100"><?= !empty($editCustomer) ? 'Update customer' : 'Save customer' ?></button><?php if (!empty($editCustomer)): ?><a class="btn btn-link w-100 mt-2" href="<?= BASE_URL ?>/customers">Cancel editing</a><?php endif; ?></form></div></div>
<div class="col-12 col-xl-8"><div class="card card-body"><h2 class="h5 mb-3">Customer directory</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Contact</th><th>Points</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$customers): ?><tr><td colspan="5" class="text-center text-muted">No customers found.</td></tr><?php endif; ?>
<?php foreach ($customers as $c): ?><tr><td><strong><?= htmlspecialchars((string)$c['full_name'],ENT_QUOTES,'UTF-8') ?></strong><?php if (!empty($c['birthday'])): ?><div class="small text-muted"><?= htmlspecialchars((string)$c['birthday'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?><?php if (!empty($c['notes'])): ?><div class="small text-muted"><?= htmlspecialchars((string)$c['notes'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?></td>
<td><?= htmlspecialchars((string)($c['phone']??'—'),ENT_QUOTES,'UTF-8') ?><div class="small text-muted"><?= htmlspecialchars((string)($c['email']??''),ENT_QUOTES,'UTF-8') ?></div></td><td><?= number_format((int)$c['loyalty_points']) ?></td><td><span class="badge text-bg-<?= $c['is_active']?'success':'secondary' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td><td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/customers?edit=<?= (int)$c['id'] ?>">Edit</a><form method="post" action="<?= BASE_URL ?>/customers/toggle"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="is_active" value="<?= $c['is_active']?0:1 ?>"><button class="btn btn-sm btn-outline-secondary"><?= $c['is_active']?'Deactivate':'Reactivate' ?></button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div></div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
