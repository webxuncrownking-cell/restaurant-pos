<div class="container-fluid px-4 py-4 staff-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Staff</h1>
      <p class="text-muted small mb-0">Manage staff accounts and their 4–6 digit POS sign-in PINs.</p>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars((string)$_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStaffModal">
      <i class="bi bi-person-plus me-1"></i> Add Staff
    </button>
  </div>

  <div class="modal fade" id="createStaffModal" tabindex="-1" aria-labelledby="createStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow">
        <form method="POST" action="<?= BASE_URL ?>/staff-pins/create">
          <div class="modal-header border-0 px-4 pt-4">
            <div>
              <h2 class="modal-title h5 fw-bold" id="createStaffModalLabel"><i class="bi bi-person-plus text-primary me-2"></i>Add staff member</h2>
              <p class="text-muted small mb-0">Create a staff login and assign its restaurant role.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Full name</label>
                <input class="form-control" name="full_name" maxlength="100" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Username</label>
                <input class="form-control" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Staff role</label>
                <select class="form-select" name="role_id" required>
                  <option value="">Select role</option>
                  <?php foreach (($staffRoles ?? []) as $role): ?>
                    <option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Temporary password</label>
                <input class="form-control" type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required>
                <div class="form-text">At least 8 characters. Staff must change it at first sign-in.</div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Terminal PIN <span class="text-muted fw-normal">(optional)</span></label>
                <input class="form-control" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="new-password" placeholder="4–6 digits">
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Create staff account</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <h2 class="h5 fw-bold mb-1">Staff accounts</h2>
          <p class="text-muted small mb-0">Review staff access, update account details, or reset terminal PINs.</p>
        </div>
        <span class="badge rounded-pill text-bg-light"><?= count($staffMembers) ?> staff</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Staff member</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Terminal PIN</th>
            <th class="text-end pe-4">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($staffMembers)): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">No staff accounts found. Use the form above to add your first staff member.</td></tr>
        <?php else: foreach ($staffMembers as $staff): ?>
          <tr>
            <td class="ps-4">
              <div class="fw-semibold"><?= htmlspecialchars((string)$staff['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
              
            </td>
            <td><?= htmlspecialchars((string)$staff['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge bg-light text-dark"><?= htmlspecialchars((string)($staff['role_name'] ?? 'Staff'), ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><span class="badge rounded-pill <?= $staff['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= htmlspecialchars(ucfirst((string)$staff['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>
              <?php if (!empty($staff['pin_configured'])): ?>
                <span class="text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>PIN configured</span>
              <?php else: ?>
                <span class="text-muted small">Not configured</span>
              <?php endif; ?>
            </td>
            <td class="text-end pe-4">
              <button
                class="btn btn-sm btn-light border staff-edit-button"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#editStaff<?= (int)$staff['id'] ?>"
                aria-label="Edit <?= htmlspecialchars((string)$staff['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                title="Edit staff details">
                <i class="bi bi-pencil-square"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php foreach ($staffMembers as $staff): ?>
    <div class="modal fade" id="editStaff<?= (int)$staff['id'] ?>" tabindex="-1" aria-labelledby="editStaffLabel<?= (int)$staff['id'] ?>" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <form method="POST" action="<?= BASE_URL ?>/staff-pins/update">
            <div class="modal-header border-0 px-4 pt-4">
              <div>
                <h2 class="modal-title h5 fw-bold" id="editStaffLabel<?= (int)$staff['id'] ?>">Edit staff details</h2>
                <p class="text-muted small mb-0">Update account information and terminal PIN.</p>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
              <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
              <input type="hidden" name="user_id" value="<?= (int)$staff['id'] ?>">
              <div class="mb-3">
                <label class="form-label fw-semibold">Full name</label>
                <input class="form-control" type="text" name="full_name" maxlength="150" value="<?= htmlspecialchars((string)$staff['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <input class="form-control" type="text" name="username" maxlength="80" value="<?= htmlspecialchars((string)$staff['username'], ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Email address</label>
                <input class="form-control" type="email" name="email" maxlength="190" value="<?= htmlspecialchars((string)($staff['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Account status</label>
                <select class="form-select" name="status" required>
                  <option value="active" <?= $staff['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $staff['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </div>
              <div class="mb-1">
                <label class="form-label fw-semibold">New terminal PIN <span class="text-muted fw-normal">(optional)</span></label>
                <input class="form-control" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" placeholder="Leave blank to keep current PIN" autocomplete="new-password">
                <div class="form-text">Use 4–6 digits. The PIN is securely hashed before it is saved.</div>
              </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="alert alert-info mt-3 small">
    <i class="bi bi-info-circle me-1"></i>
    Staff sign in with their username, restaurant slug, and PIN. PINs are stored as salted PBKDF2 hashes, never as plain text.
  </div>
</div>

<style>
.staff-page .staff-edit-button { width: 38px; height: 38px; padding: 0; color: #2563eb; }
.staff-page .staff-edit-button:hover { color: #fff; background: #2563eb !important; border-color: #2563eb !important; }
.staff-page .table td { vertical-align: middle; }
</style>
