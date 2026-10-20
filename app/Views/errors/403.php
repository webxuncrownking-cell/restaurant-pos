<div class="container my-auto py-5 text-center">
  <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning p-4 rounded-circle mb-3" style="width: 80px; height: 80px;">
    <i class="bi bi-shield-x fs-1"></i>
  </div>
  <h1 class="display-6 fw-bold text-dark">403 - Access Forbidden</h1>
  <p class="text-muted"><?= htmlspecialchars($message ?? 'You do not have permission to access this resource.') ?></p>
  <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm px-4 mt-2">
    <i class="bi bi-house me-1"></i> Return to Dashboard
  </a>
</div>
