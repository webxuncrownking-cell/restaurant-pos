<div class="container-fluid px-4 py-4">
  <div class="mb-4"><h1 class="h3 fw-bold">Subscription Payment Reviews</h1><p class="text-muted mb-0">Verify receipts before enabling paid subscription plans.</p></div>
  <?php foreach (($flashes ?? []) as $flash): ?><div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endforeach; ?>
  <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Restaurant</th><th>Plan</th><th>Payment</th><th>Reference</th><th>Receipt</th><th>Status</th><th>Review</th></tr></thead><tbody>
    <?php foreach ($submissions as $s): ?><tr>
      <td><strong><?= htmlspecialchars($s['restaurant_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($s['slug']) ?></small></td>
      <td><?= htmlspecialchars($s['plan_name']) ?><br><small><?= htmlspecialchars($s['billing_cycle']) ?></small></td>
      <td>₱<?= number_format((float)$s['amount'],2) ?><br><small><?= htmlspecialchars(str_replace('_',' ',$s['payment_method'])) ?></small><br><small><?= htmlspecialchars($s['payer_name']) ?></small></td>
      <td><?= htmlspecialchars($s['transaction_reference']) ?><br><small><?= htmlspecialchars($s['created_at']) ?></small></td>
      <td><a target="_blank" rel="noopener" href="<?= BASE_URL ?>/platform-subscription-payments/proof?id=<?= (int)$s['id'] ?>">View receipt</a></td>
      <td><span class="badge bg-<?= $s['status']==='approved'?'success':($s['status']==='rejected'?'danger':'warning') ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span><?php if (!empty($s['reviewer_note'])): ?><div class="small text-muted"><?= htmlspecialchars($s['reviewer_note']) ?></div><?php endif; ?></td>
      <td><?php if ($s['status']==='pending'): ?><form method="post" action="<?= BASE_URL ?>/platform-subscription-payments/review" class="d-grid gap-2"><?= \App\Core\CSRF::formField() ?><input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>"><input class="form-control form-control-sm" name="reviewer_note" maxlength="500" placeholder="Optional note"><button class="btn btn-sm btn-success" name="decision" value="approved" onclick="return confirm('Confirm receipt of payment and activate this plan?')">Approve & activate</button><button class="btn btn-sm btn-outline-danger" name="decision" value="rejected" onclick="return confirm('Reject this payment?')">Reject</button></form><?php else: ?>—<?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$submissions): ?><tr><td colspan="7" class="text-center text-muted py-4">No subscription payment submissions.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>