<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<style>
.activation-form-page { max-width: 920px !important; padding-top: 36px !important; padding-bottom: 36px !important; }
.activation-form-page > .card .card-body { padding: 36px !important; }
.activation-form-page .form-label { font-size: 1rem !important; font-weight: 700; margin-bottom: 9px; }
.activation-form-page .form-control,
.activation-form-page .form-select { min-height: 56px; padding: 12px 15px; font-size: 1rem; }
.activation-form-page .btn-lg { min-height: 60px; font-size: 1.05rem; }
.activation-form-page .card h1 { font-size: 1.8rem; }
.activation-form-page p,
.activation-form-page .form-text { font-size: .95rem; line-height: 1.6; }
@media(max-width:575.98px) {
  .activation-form-page > .card .card-body { padding: 22px !important; }
}
</style>
<div class="container activation-form-page py-4" style="max-width:920px">
  <?php if (!empty($flashes)): foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
  <?php endforeach; endif; ?>
  <div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5">
    <div class="text-center mb-4"><div class="display-5 text-primary"><i class="bi bi-shield-lock"></i></div>
      <h1 class="h3 fw-bold mt-2">Activate <?= htmlspecialchars($restaurant['name']) ?></h1>
      <p class="text-muted">Submit your one-time activation payment. Standard POS access becomes available after verification.</p>
    </div>
    <div class="rounded-3 bg-light p-3 mb-4 text-center">
      <div class="small text-muted">One-time activation fee</div>
      <div class="display-6 fw-bold">₱<?= number_format($activation_fee, 2) ?></div>
      <div class="small text-muted">No automatic monthly subscription or trial is started.</div>
    </div>
    <?php if ($restaurant['activation_status'] === 'activated'): ?>
      <div class="alert alert-success">Your restaurant is activated. <a href="<?= BASE_URL ?>/dashboard">Continue to dashboard</a>.</div>
    <?php else: ?>
      <?php if ($payment_instructions !== ''): ?><div class="alert alert-info"><strong>Payment instructions</strong><br><?= nl2br(htmlspecialchars($payment_instructions)) ?></div><?php endif; ?>
      <?php if ($restaurant['activation_status'] === 'pending'): ?><div class="alert alert-warning">Your payment is awaiting review. You may submit again if your previous details were incorrect.</div><?php endif; ?>
      <form method="post" action="<?= BASE_URL ?>/activation/submit" enctype="multipart/form-data">
        <?= \App\Core\CSRF::formField() ?>
        <div class="mb-3"><label class="form-label">Payment method</label><select class="form-select" name="payment_method" required><option value="">Choose method</option><option value="gcash">GCash</option><option value="bank_transfer">Bank transfer</option><option value="cash_deposit">Cash deposit</option><option value="other">Other</option></select></div>
        <div class="mb-3"><label class="form-label">Transaction reference number</label><input class="form-control" name="transaction_reference" maxlength="120" required></div>
        <div class="mb-3"><label class="form-label">Payer name</label><input class="form-control" name="payer_name" maxlength="120" required></div>
        <div class="mb-4"><label class="form-label">Proof of payment</label><input class="form-control" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required><div class="form-text">JPG, PNG, WEBP, or PDF; maximum 5 MB.</div></div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Submit payment for verification</button>
      </form>
    <?php endif; ?>
  </div></div>
  <?php if (!empty($submissions)): ?><div class="card border-0 shadow-sm mt-4"><div class="card-body"><h2 class="h5 fw-bold">Submission history</h2>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th><th>Review note</th></tr></thead><tbody>
    <?php foreach ($submissions as $s): ?><tr><td><?= htmlspecialchars(date('M d, Y H:i', strtotime($s['created_at']))) ?></td><td><?= htmlspecialchars($s['transaction_reference']) ?></td><td>₱<?= number_format((float)$s['amount'],2) ?></td><td><span class="badge bg-<?= $s['status']==='approved'?'success':($s['status']==='rejected'?'danger':'warning') ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td><td><?= htmlspecialchars((string)($s['reviewer_note'] ?? '—')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div><?php endif; ?>
</div>
<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
