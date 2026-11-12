<div class="container-fluid px-4 py-4">
  <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 fw-bold">Activation Payments</h1><p class="text-muted mb-0">Review payment references and verify uploaded receipts before activating accounts.</p></div><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard">Dashboard</a></div>
  <?php if (!empty($flashes)): foreach ($flashes as $flash): ?><div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endforeach; endif; ?>
  <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Restaurant</th><th>Submitted by</th><th>Payment</th><th>Reference</th><th>Proof</th><th>Status</th><th>Review</th></tr></thead><tbody>
  <?php foreach ($submissions as $s): ?><tr><td><strong><?= htmlspecialchars($s['restaurant_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($s['slug']) ?></small></td><td><?= htmlspecialchars($s['submitted_by_name']) ?><br><small class="text-muted"><?= htmlspecialchars($s['created_at']) ?></small></td><td>₱<?= number_format((float)$s['amount'],2) ?><br><small><?= htmlspecialchars(str_replace('_',' ', $s['payment_method'])) ?></small><br><small><?= htmlspecialchars($s['payer_name']) ?></small></td><td><?= htmlspecialchars($s['transaction_reference']) ?></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activationProofModal" data-proof-url="<?= BASE_URL ?>/platform-activation-payments/proof?id=<?= (int)$s['id'] ?>" data-proof-title="Payment proof — <?= htmlspecialchars($s['restaurant_name'], ENT_QUOTES, 'UTF-8') ?>"> <i class="bi bi-eye me-1"></i>View proof</button></td><td><span class="badge bg-<?= $s['status']==='approved'?'success':($s['status']==='rejected'?'danger':'warning') ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span><?php if ($s['status']==='pending' && !empty($s['activation_trial_until'])): ?><div class="small text-info fw-semibold">Temporary access until <?= htmlspecialchars(date('M d, Y h:i A', strtotime($s['activation_trial_until']))) ?></div><?php endif; ?><?php if (!empty($s['reviewer_note'])): ?><div class="small text-muted"><?= htmlspecialchars($s['reviewer_note']) ?></div><?php endif; ?></td><td>
  <?php if ($s['status']==='pending'): ?><form method="post" action="<?= BASE_URL ?>/platform-activation-payments/review" class="d-grid gap-2"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>"><input class="form-control form-control-sm" name="reviewer_note" maxlength="500" placeholder="Optional note"><button class="btn btn-sm btn-success" name="decision" value="approved" onclick="return confirm('Confirm that payment was received and activate this restaurant?')">Approve & activate</button><button class="btn btn-sm btn-outline-danger" name="decision" value="rejected" onclick="return confirm('Reject this payment submission?')">Reject</button></form><?php else: ?>—<?php endif; ?>
  </td></tr><?php endforeach; ?>
  <?php if (!$submissions): ?><tr><td colspan="7" class="text-center text-muted py-4">No activation payment submissions yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div>

  <div class="modal fade" id="activationProofModal" tabindex="-1" aria-labelledby="activationProofModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="activationProofModalTitle">Payment proof</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-2 p-md-3" style="min-height:65vh">
          <iframe id="activationProofFrame" title="Payment proof preview" src="about:blank" style="width:100%;height:65vh;border:0;border-radius:10px;background:#f8fafc"></iframe>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var modal = document.getElementById('activationProofModal');
      var frame = document.getElementById('activationProofFrame');
      var title = document.getElementById('activationProofModalTitle');
      if (!modal || !frame) return;
      modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        frame.src = trigger.getAttribute('data-proof-url') || 'about:blank';
        title.textContent = trigger.getAttribute('data-proof-title') || 'Payment proof';
      });
      modal.addEventListener('hidden.bs.modal', function () {
        frame.src = 'about:blank';
      });
    });
  </script>
