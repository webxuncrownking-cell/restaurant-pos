<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="container pt-3">
    <div class="alert alert-success alert-dismissible fade show mb-0" role="alert">
      <?= htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="container pt-3">
    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
      <?= htmlspecialchars((string)$_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-tablet-landscape text-primary me-2"></i>POS Devices & Terminals
      </h1>
      <p class="text-muted small mb-0">Register hardware terminals, rotate cryptographic bearer tokens, and manage offline staff authorizations.</p>
    </div>
    <div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerDeviceModal">
        <i class="bi bi-plus-lg me-1"></i> Register New Device
      </button>
    </div>
  </div>

  <!-- Credentials Flash Display (if freshly generated) -->
  <?php if (!empty($_SESSION['new_device_credentials'])): 
    $newDev = $_SESSION['new_device_credentials'];
    unset($_SESSION['new_device_credentials']);
  ?>
    <div class="alert alert-warning border-warning shadow-sm mb-4">
      <div class="d-flex gap-2 align-items-start">
        <i class="bi bi-shield-lock-fill fs-3 text-warning me-2"></i>
        <div class="flex-grow-1">
          <h5 class="alert-heading fw-bold mb-1">New POS Terminal Registered: <?= htmlspecialchars($newDev['device_name']) ?></h5>
          <p class="small mb-2">
            Store this device pairing token immediately. For security, only the SHA-256 hash is stored on the server; this plain token cannot be retrieved again.
          </p>
          <div class="bg-dark text-white p-3 rounded font-monospace small mb-2">
            <div><span class="text-muted">Device UUID:</span> <strong class="text-info"><?= htmlspecialchars($newDev['device_uuid']) ?></strong></div>
            <div class="mt-1"><span class="text-muted">Pairing Bearer Token:</span> <strong class="text-warning"><?= htmlspecialchars($newDev['plain_token']) ?></strong></div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($newDev['plain_token']) ?>'); alert('Token copied to clipboard!');">
            <i class="bi bi-clipboard me-1"></i> Copy Token
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['rotated_token'])): 
    $rot = $_SESSION['rotated_token'];
    unset($_SESSION['rotated_token']);
  ?>
    <div class="alert alert-info border-info shadow-sm mb-4">
      <h6 class="fw-bold mb-1"><i class="bi bi-arrow-repeat me-1"></i> New Rotated Token for Device #<?= (int)$rot['device_id'] ?></h6>
      <p class="small mb-2">The previous token has been invalidated. Update the terminal config with this new token:</p>
      <div class="bg-dark text-warning p-2 rounded font-monospace small mb-2">
        <?= htmlspecialchars($rot['token']) ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($rot['token']) ?>'); alert('Copied!');">
        <i class="bi bi-clipboard me-1"></i> Copy Rotated Token
      </button>
    </div>
  <?php endif; ?>

  <!-- Registered Devices List -->
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Device Name</th>
            <th>Device UUID</th>
            <th>Status</th>
            <th>Staff Auth</th>
            <th>Open Shifts</th>
            <th>Registered / Last Sync</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($devices)): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-tablet fs-1 d-block mb-2"></i>
                No POS devices registered yet. Click "Register New Device" to get started.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($devices as $dev): ?>
              <tr>
                <td>
                  <div class="fw-bold"><?= htmlspecialchars($dev['device_name']) ?></div>
                  <small class="text-muted">ID #<?= (int)$dev['id'] ?></small>
                </td>
                <td>
                  <span class="font-monospace small text-secondary"><?= htmlspecialchars($dev['device_uuid']) ?></span>
                </td>
                <td>
                  <?php if ($dev['status'] === 'active'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                  <?php elseif ($dev['status'] === 'revoked'): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Revoked</span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= ucfirst($dev['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-light text-dark border">
                    <i class="bi bi-people me-1"></i><?= (int)$dev['staff_count'] ?> Staff
                  </span>
                </td>
                <td>
                  <?php if ($dev['open_shifts'] > 0): ?>
                    <span class="badge bg-primary"><?= (int)$dev['open_shifts'] ?> Open</span>
                  <?php else: ?>
                    <span class="text-muted small">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="small text-muted">Created: <?= date('M d, Y', strtotime($dev['created_at'])) ?></div>
                  <div class="small text-muted">Sync: <?= $dev['last_sync_at'] ? date('M d, H:i', strtotime($dev['last_sync_at'])) : 'Never' ?></div>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <!-- Staff Auth Button -->
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#staffModal_<?= $dev['id'] ?>" title="Manage Staff Authorizations">
                      <i class="bi bi-person-check"></i>
                    </button>

                    <!-- Rotate Key Form -->
                    <form method="POST" action="<?= BASE_URL ?>/devices/rotate" onsubmit="return confirm('Rotate pairing token for <?= htmlspecialchars($dev['device_name']) ?>? The terminal will need to be re-paired.');">
                      <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                      <input type="hidden" name="device_id" value="<?= $dev['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-warning text-dark" title="Rotate Pairing Token">
                        <i class="bi bi-key"></i>
                      </button>
                    </form>

                    <!-- Toggle Revoke / Active -->
                    <form method="POST" action="<?= BASE_URL ?>/devices/status">
                      <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                      <input type="hidden" name="device_id" value="<?= $dev['id'] ?>">
                      <?php if ($dev['status'] === 'active'): ?>
                        <input type="hidden" name="status" value="revoked">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke Device Access" onclick="return confirm('Revoke device <?= htmlspecialchars($dev['device_name']) ?>? It will not be able to sync.');">
                          <i class="bi bi-slash-circle"></i>
                        </button>
                      <?php else: ?>
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivate Device">
                          <i class="bi bi-check-circle"></i>
                        </button>
                      <?php endif; ?>
                    </form>
                  </div>

                  <!-- Staff Modal for this Device -->
                  <div class="modal fade text-start" id="staffModal_<?= $dev['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" action="<?= BASE_URL ?>/devices/authorizations">
                          <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                          <input type="hidden" name="device_id" value="<?= $dev['id'] ?>">
                          <div class="modal-header">
                            <h5 class="modal-title fw-bold">
                              <i class="bi bi-shield-check text-primary me-2"></i>Staff Access: <?= htmlspecialchars($dev['device_name']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <p class="small text-muted mb-2">
                              Select staff members authorized to use this terminal while offline.
                            </p>
                            <div class="alert alert-info small py-2">
                              Set or reset an offline PIN for each selected staff member. PINs must contain 4–6 digits.
                              Leave the PIN field blank to keep the current PIN. No shared demo PIN is used.
                            </div>
                            <?php
                              $deviceAuths = \App\Services\DeviceService::getDeviceStaff((int)$dev['restaurant_id'], (int)$dev['id']);
                              $authMap = array_map('intval', array_column($deviceAuths, 'user_id'));
                            ?>
                            <div class="list-group">
                              <?php foreach ($staffMembers as $sm):
                                $staffId = (int)$sm['id'];
                                $isAuth = in_array($staffId, $authMap, true);
                              ?>
                                <div class="list-group-item">
                                  <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="device_<?= (int)$dev['id'] ?>_staff_<?= $staffId ?>" name="user_ids[]" value="<?= $staffId ?>" <?= $isAuth ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="device_<?= (int)$dev['id'] ?>_staff_<?= $staffId ?>">
                                      <strong><?= htmlspecialchars($sm['full_name']) ?></strong>
                                      <small class="d-block text-muted">@<?= htmlspecialchars($sm['username']) ?> &bull; <?= htmlspecialchars($sm['role_name']) ?></small>
                                    </label>
                                  </div>
                                  <label class="form-label small mb-1" for="device_<?= (int)$dev['id'] ?>_pin_<?= $staffId ?>">
                                    Offline PIN
                                    <?php if (!empty($sm['offline_pin_configured'])): ?>
                                      <span class="badge bg-success-subtle text-success">Configured</span>
                                    <?php else: ?>
                                      <span class="badge bg-warning-subtle text-dark">Not configured</span>
                                    <?php endif; ?>
                                  </label>
                                  <input
                                    class="form-control form-control-sm"
                                    type="password"
                                    id="device_<?= (int)$dev['id'] ?>_pin_<?= $staffId ?>"
                                    name="offline_pins[<?= $staffId ?>]"
                                    inputmode="numeric"
                                    pattern="[0-9]{4,6}"
                                    minlength="4"
                                    maxlength="6"
                                    autocomplete="new-password"
                                    placeholder="Enter 4–6 digits to set/reset"
                                  >
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Authorizations</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Register New Device -->
<div class="modal fade" id="registerDeviceModal" tabindex="-1" aria-labelledby="registerDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= BASE_URL ?>/devices/register">
        <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="registerDeviceModalLabel">
            <i class="bi bi-plus-circle text-primary me-2"></i>Register POS Terminal
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Device Terminal Name <span class="text-danger">*</span></label>
            <input type="text" name="device_name" class="form-control" placeholder="e.g. Counter Tablet #1, Bar POS Terminal" required>
            <div class="form-text">Give this device a recognizable name based on its location in the restaurant.</div>
          </div>
          <div class="alert alert-secondary small mb-0">
            <i class="bi bi-info-circle me-1"></i> A secure UUIDv4 and 256-bit cryptographic bearer token will be generated upon creation.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Register Terminal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Entering an offline PIN also selects that staff member for offline access.
  // The server repeats this behavior as a safeguard against unchecked boxes.
  document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.name.startsWith('offline_pins[')) return;
    if (input.value.trim() === '') return;

    const staffRow = input.closest('.list-group-item');
    const checkbox = staffRow ? staffRow.querySelector('input[name="user_ids[]"]') : null;
    if (checkbox) checkbox.checked = true;
  });
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
