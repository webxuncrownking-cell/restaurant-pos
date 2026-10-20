<div class="container my-auto py-5 text-center">
  <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger p-4 rounded-circle mb-3" style="width: 80px; height: 80px;">
    <i class="bi bi-exclamation-triangle fs-1"></i>
  </div>
  <h1 class="display-6 fw-bold text-dark">404 - Page Not Found</h1>
  <p class="text-muted"><?= htmlspecialchars($message ?? 'The page you requested does not exist.') ?></p>
  <a href="<?= BASE_URL ?>/dashboard" class="btn btn-primary btn-sm px-4 mt-2">
    <i class="bi bi-house me-1"></i> Return to Dashboard
  </a>
</div>
