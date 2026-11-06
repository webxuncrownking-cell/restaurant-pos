<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>
<div class="container py-4">
 <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div><h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-lock text-primary me-2"></i>Staff Terminal PINs</h1><p class="text-muted small mb-0">Set or update the 4–6 digit PIN each staff member uses to open the POS terminal.</p></div>
  <a href="<?= BASE_URL ?>/devices" class="btn btn-outline-secondary"><i class="bi bi-tablet me-1"></i> Device Management</a>
 </div>
 <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?= htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?></div><?php unset($_SESSION['flash_success']); endif; ?>
 <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger"><?= htmlspecialchars((string)$_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?></div><?php unset($_SESSION['flash_error']); endif; ?>
 <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead class="table-light"><tr><th>Staff member</th><th>Username</th><th>Status</th><th>PIN</th><th style="min-width:260px">Set / Update PIN</th></tr></thead>
  <tbody>
  <?php if (empty($staffMembers)): ?><tr><td colspan="5" class="text-center text-muted py-5">No staff accounts found.</td></tr>
  <?php else: foreach ($staffMembers as $staff): ?>
   <tr>
    <td class="fw-semibold"><?= htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars($staff['username'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><span class="badge <?= $staff['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= htmlspecialchars(ucfirst($staff['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
    <td><?php if (!empty($staff['pin_configured'])): ?><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Configured</span><?php else: ?><span class="badge bg-warning-subtle text-warning">Not set</span><?php endif; ?></td>
    <td>
     <?php if ($staff['status'] === 'active'): ?>
      <form method="POST" action="<?= BASE_URL ?>/staff-pins" class="d-flex gap-2">
       <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
       <input type="hidden" name="user_id" value="<?= (int)$staff['id'] ?>">
       <input class="form-control form-control-sm" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" placeholder="4–6 digits" autocomplete="new-password" required>
       <button class="btn btn-sm btn-primary text-nowrap" type="submit">Save PIN</button>
      </form>
     <?php else: ?><span class="text-muted small">Inactive staff cannot sign in.</span><?php endif; ?>
    </td>
   </tr>
  <?php endforeach; endif; ?>
  </tbody>
 </table></div></div>
 <div class="alert alert-info mt-3 small"><i class="bi bi-info-circle me-1"></i> PINs are stored as salted PBKDF2 hashes, never as plain text. Staff must use their username, restaurant slug, and PIN on the terminal sign-in screen.</div>
</div>
