<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
          <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-clipboard2-check text-success me-2"></i>Automated Integration Test Results
          </h2>
          <p class="text-muted small mb-0">Phase 1 Database Constraints, Isolation & Sequence Verification</p>
        </div>
        <a href="<?= BASE_URL ?>/install" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Diagnostics
        </a>
      </div>

      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0 fw-semibold text-secondary">Test Execution Suite</h5>
          <span class="badge bg-light text-dark border"><?= count($results ?? []) ?> tests executed</span>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (!empty($results)): ?>
              <?php foreach ($results as $test): ?>
                <li class="list-group-item p-3 px-4">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="mb-0 fw-semibold text-dark"><?= htmlspecialchars($test['name']) ?></h6>
                    <?php if ($test['passed']): ?>
                      <span class="badge bg-success bg-opacity-25 text-success border border-success px-3 py-1 rounded-pill">
                        <i class="bi bi-check-lg me-1"></i> PASS
                      </span>
                    <?php else: ?>
                      <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-3 py-1 rounded-pill">
                        <i class="bi bi-x-lg me-1"></i> FAIL
                      </span>
                    <?php endif; ?>
                  </div>
                  <p class="small text-muted mb-0 font-monospace"><?= htmlspecialchars($test['message']) ?></p>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="list-group-item text-center py-4 text-muted">No test results to display.</li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="card-footer bg-light p-3 text-end">
          <a href="<?= BASE_URL ?>/install/tests" class="btn btn-success btn-sm px-4">
            <i class="bi bi-arrow-clockwise me-1"></i> Re-Run Tests
          </a>
        </div>
      </div>

    </div>
  </div>
</div>
