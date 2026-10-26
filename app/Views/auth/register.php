<div class="container py-4 py-lg-5" style="max-width: 760px;">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-warning-emphasis rounded-circle mb-3" style="width:64px;height:64px;"><i class="bi bi-shop fs-2"></i></div>
    <h1 class="h2 fw-bold text-dark">Create your ServeFlow POS account</h1>
    <p class="text-muted mb-0">Register your restaurant and start with a 14-day trial.</p>
  </div>

  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white px-4 py-3"><h2 class="h5 mb-0"><i class="bi bi-person-plus text-primary me-2"></i>Restaurant and owner details</h2></div>
    <div class="card-body p-4">
      <form method="POST" action="<?= BASE_URL ?>/register" autocomplete="on">
        <?= $csrf_field ?>
        <div class="mb-3">
          <label class="form-label" for="restaurant_name">Restaurant / Carinderia Name</label>
          <input class="form-control" id="restaurant_name" name="restaurant_name" maxlength="150" required autocomplete="organization">
        </div>
        <div class="mb-3">
          <label class="form-label" for="owner_name">Owner's Full Name</label>
          <input class="form-control" id="owner_name" name="owner_name" maxlength="100" required autocomplete="name">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="email">Email Address</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="100" required autocomplete="email">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Contact Number</label>
            <input class="form-control" type="tel" id="phone" name="phone" maxlength="30" required autocomplete="tel">
          </div>
        </div>
        <hr class="my-4">
        <h3 class="h6 fw-semibold mb-3">Create your login</h3>
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input class="form-control" id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" required autocomplete="username">
          <div class="form-text">Use 3–50 letters, numbers, dots, underscores, or hyphens.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" minlength="10" required autocomplete="new-password">
            <div class="form-text">At least 10 characters.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirm Password</label>
            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="10" required autocomplete="new-password">
          </div>
        </div>
        <div class="alert alert-info small mt-4 mb-3"><i class="bi bi-info-circle me-1"></i> Your restaurant will be created with an Owner account and a 14-day trial on the lowest-priced active plan. No payment is collected by this form.</div>
        <button type="submit" class="btn btn-warning text-dark fw-semibold w-100 py-2"><i class="bi bi-person-check me-2"></i>Create Restaurant Account</button>
      </form>
    </div>
  </div>
  <p class="text-center text-muted mt-4 mb-0">Already registered? <a href="<?= BASE_URL ?>/login" class="fw-semibold text-decoration-none">Sign in</a></p>
</div>
