<?php include APP_ROOT . '/app/Views/layouts/header.php'; ?>
<?php include APP_ROOT . '/app/Views/layouts/navbar.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<div class="container py-4">
  <!-- Page Header & Date Range Filter -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-graph-up-arrow text-primary me-2"></i>Sales & Business Reports
      </h1>
      <p class="text-muted small mb-0">Track sales, food cost, item-level profit, refunds, wastage, and cashier cash variances.</p>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
      <form method="GET" action="<?= BASE_URL ?>/reports" class="d-flex flex-wrap align-items-center gap-2">
        <input type="date" name="start_date" class="form-control" style="width:155px" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" aria-label="Start date">
        <span class="text-muted small">to</span>
        <input type="date" name="end_date" class="form-control" style="width:155px" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" aria-label="End date">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Apply</button>
      </form>
      <div class="d-flex flex-wrap justify-content-end gap-2" aria-label="Export reports">
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=sales&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-filetype-csv me-1"></i> Sales CSV</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=profitability&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-filetype-csv me-1"></i> Profitability CSV</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=cashiers&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-filetype-csv me-1"></i> Cashiers CSV</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=reconciliation&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-filetype-csv me-1"></i> Shifts CSV</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=expenses&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-filetype-csv me-1"></i> Expenses CSV</a>
      </div>
    </div>
  </div>

  <!-- KPI Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3 bg-primary text-white">
        <div class="text-white-50 small fw-semibold text-uppercase mb-1">Gross Sales Revenue</div>
        <div class="h2 fw-bold font-monospace mb-0">₱<?= number_format($kpis['gross_sales'], 2) ?></div>
        <div class="small text-white-50 mt-2">All completed customer orders</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Total Orders Completed</div>
        <div class="h2 fw-bold text-dark font-monospace mb-0"><?= number_format($kpis['total_orders']) ?></div>
        <div class="small text-muted mt-2">Dine-in, takeout & delivery</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Average Order Value (AOV)</div>
        <div class="h2 fw-bold text-success font-monospace mb-0">₱<?= number_format($kpis['average_order_value'], 2) ?></div>
        <div class="small text-muted mt-2">Per transaction average</div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Total Discounts Given</div>
        <div class="h2 fw-bold text-warning font-monospace mb-0">₱<?= number_format($kpis['total_discounts'], 2) ?></div>
        <div class="small text-muted mt-2">Senior, PWD & staff promos</div>
      </div>
    </div>
  </div>

  <!-- Operational adjustments and cash control -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Refunds</div>
        <div class="h3 fw-bold text-danger font-monospace mb-0">₱<?= number_format((float)$adjustments['refund_amount'], 2) ?></div>
        <div class="small text-muted mt-2"><?= (int)$adjustments['refund_count'] ?> refund record(s) in this period</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Voids</div>
        <div class="h3 fw-bold text-dark font-monospace mb-0"><?= number_format((int)$adjustments['void_count']) ?></div>
        <div class="small text-muted mt-2">Void audit records in this period</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Recorded Wastage</div>
        <div class="h3 fw-bold text-warning font-monospace mb-0">₱<?= number_format((float)$adjustments['waste_cost'], 2) ?></div>
        <div class="small text-muted mt-2"><?= number_format((int)$adjustments['waste_count']) ?> waste record(s)</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Cash Variance</div>
        <div class="h3 fw-bold <?= (float)$adjustments['total_cash_variance'] > 0 ? 'text-danger' : 'text-success' ?> font-monospace mb-0">₱<?= number_format((float)$adjustments['total_cash_variance'], 2) ?></div>
        <div class="small text-muted mt-2"><?= number_format((int)$adjustments['closed_shifts']) ?> shifts · over ₱<?= number_format((float)$adjustments['cash_over'], 2) ?> / short ₱<?= number_format((float)$adjustments['cash_short'], 2) ?></div>
      </div>
    </div>
  </div>

  <!-- Operating expense summary for the selected period -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100 p-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1">Operating Expenses</div>
        <?php if (!empty($expenseSummary['available'])): ?>
          <div class="h3 fw-bold text-danger font-monospace mb-0">₱<?= number_format((float)$expenseSummary['total'], 2) ?></div>
          <div class="small text-muted mt-2"><?= number_format((int)$expenseSummary['count']) ?> active expense record(s) in the selected period</div>
          <a class="small mt-2" href="<?= BASE_URL ?>/expenses?from=<?= rawurlencode($startDate) ?>&amp;to=<?= rawurlencode($endDate) ?>">Review expense details</a>
        <?php else: ?>
          <div class="fw-semibold text-muted">Not set up yet</div>
          <div class="small text-muted mt-2">Prepare expense tracking to include recorded costs here.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Explainable predictive/decision-support insights from existing sales and cost snapshots -->
  <section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 py-3">
      <h5 class="fw-bold mb-1"><i class="bi bi-lightbulb text-warning me-2"></i>Business Insights</h5>
      <p class="text-muted small mb-0">Rule-based indicators from this tenant's sales, menu margins, and recorded expenses. These are guidance signals, not guaranteed forecasts.</p>
    </div>
    <div class="card-body pt-0">
      <div class="row g-3">
        <?php foreach (($insights ?? []) as $insight): ?>
          <div class="col-12 col-lg-4">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-1"><span class="badge text-bg-<?= htmlspecialchars((string)($insight['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> me-1"><?= htmlspecialchars(strtoupper((string)($insight['type'] ?? 'info')), ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars((string)($insight['title'] ?? 'Insight'), ENT_QUOTES, 'UTF-8') ?></div>
              <p class="small text-muted mb-0"><?= htmlspecialchars((string)($insight['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Visual Charts Section -->
  <div class="row g-4 mb-4">
    <!-- Daily Revenue Trend (Line Chart) -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-3 border-0">
          <h5 class="fw-bold mb-0">Revenue Trend (Selected Period)</h5>
        </div>
        <div class="card-body pt-0">
          <canvas id="revenueTrendChart" style="max-height: 280px;"></canvas>
        </div>
      </div>
    </div>

    <!-- Payment Methods (Donut Chart) -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-3 border-0">
          <h5 class="fw-bold mb-0">Payment Tenders</h5>
        </div>
        <div class="card-body pt-0 d-flex flex-column justify-content-center">
          <canvas id="paymentDonutChart" style="max-height: 250px;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Item-level profitability -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent py-3 border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h5 class="fw-bold mb-1">Menu Profitability</h5>
        <p class="text-muted small mb-0">Estimated from historical order-line cost snapshots and add-on costs, with order-level discounts allocated by line subtotal.</p>
      </div>
      <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=profitability&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-download me-1"></i> Export CSV</a>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Menu Item</th><th>Category</th><th class="text-center">Units</th><th class="text-end">Net Revenue</th><th class="text-end">Food Cost</th><th class="text-end">Gross Profit</th><th class="text-end">Food Cost %</th><th class="text-end">Margin %</th></tr></thead>
        <tbody>
          <?php if (empty($profitability)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No paid item sales in this date range.</td></tr>
          <?php else: foreach ($profitability as $item): ?>
            <?php $profit = (float)$item['estimated_gross_profit']; $margin = (float)$item['gross_margin_pct']; ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)$item['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)$item['category_name'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="text-center font-monospace"><?= number_format((float)$item['total_quantity_sold'], 2) ?></td>
              <td class="text-end font-monospace">₱<?= number_format((float)$item['total_revenue'], 2) ?></td>
              <td class="text-end font-monospace">₱<?= number_format((float)$item['estimated_food_cost'], 2) ?></td>
              <td class="text-end font-monospace fw-bold <?= $profit < 0 ? 'text-danger' : 'text-success' ?>">₱<?= number_format($profit, 2) ?></td>
              <td class="text-end"><span class="badge <?= (float)$item['food_cost_pct'] > 40 ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>"><?= number_format((float)$item['food_cost_pct'], 1) ?>%</span></td>
              <td class="text-end"><span class="badge <?= $margin < 0 ? 'bg-danger' : ($margin < 30 ? 'bg-warning text-dark' : 'bg-success') ?>"><?= number_format($margin, 1) ?>%</span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Data Tables: Top Products & Cashier Performance -->
  <div class="row g-4">
    <!-- Top Selling Products -->
    <div class="col-12 col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-3 border-0">
          <h5 class="fw-bold mb-0">Top Performing Menu Items</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Menu Item</th>
                <th>Category</th>
                <th class="text-center">Units Sold</th>
                <th class="text-end">Total Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($topProducts)): ?>
                <tr>
                  <td colspan="4" class="text-center py-4 text-muted">No sales data recorded in this period.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($topProducts as $item): ?>
                  <tr>
                    <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['category_name']) ?></span></td>
                    <td class="text-center font-monospace fw-semibold"><?= (int)$item['total_quantity_sold'] ?></td>
                    <td class="text-end font-monospace fw-bold text-success">₱<?= number_format((float)$item['total_revenue'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Cashier Performance -->
    <div class="col-12 col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-3 border-0">
          <h5 class="fw-bold mb-0">Staff Cashier Collections</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Staff Member</th>
                <th class="text-center">Orders</th>
                <th class="text-end">Collected</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cashierStats)): ?>
                <tr>
                  <td colspan="3" class="text-center py-4 text-muted">No cashier shift records in this period.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($cashierStats as $cs): ?>
                  <tr>
                    <td class="fw-semibold">
                      <i class="bi bi-person-circle text-muted me-1"></i>
                      <?= htmlspecialchars($cs['cashier_name']) ?>
                    </td>
                    <td class="text-center font-monospace"><?= (int)$cs['orders_handled'] ?></td>
                    <td class="text-end font-monospace fw-bold">₱<?= number_format((float)$cs['total_collected'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Closed cashier shift reconciliation -->
  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent py-3 border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h5 class="fw-bold mb-1">Cashier Shift Reconciliation</h5>
        <p class="text-muted small mb-0">Closed shifts and declared cash differences for the selected period.</p>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/reports/export.csv?type=reconciliation&amp;start_date=<?= rawurlencode($startDate) ?>&amp;end_date=<?= rawurlencode($endDate) ?>"><i class="bi bi-download me-1"></i> Export CSV</a>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Shift</th><th>Cashier</th><th>Closed At</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Difference</th></tr></thead>
        <tbody>
          <?php if (empty($shiftReconciliation)): ?>
            <tr><td colspan="6" class="text-center py-4 text-muted">No closed cashier shifts in this date range.</td></tr>
          <?php else: foreach ($shiftReconciliation as $shift): $diff = (float)($shift['cash_difference'] ?? 0); ?>
            <tr>
              <td class="font-monospace">#<?= (int)$shift['shift_id'] ?></td>
              <td><?= htmlspecialchars((string)$shift['cashier_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$shift['closed_at'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end font-monospace">₱<?= number_format((float)$shift['expected_cash'], 2) ?></td>
              <td class="text-end font-monospace">₱<?= number_format((float)$shift['closing_cash'], 2) ?></td>
              <td class="text-end font-monospace fw-bold <?= $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-warning' : 'text-success') ?>">₱<?= number_format($diff, 2) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Chart.js Setup Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Line Chart: Revenue Trend
  const dailyData = <?= json_encode($dailyTrend) ?>;
  const trendLabels = dailyData.map(d => d.sale_date);
  const trendValues = dailyData.map(d => parseFloat(d.daily_revenue));

  const ctxLine = document.getElementById('revenueTrendChart');
  if (ctxLine) {
    new Chart(ctxLine, {
      type: 'line',
      data: {
        labels: trendLabels.length ? trendLabels : ['No Data'],
        datasets: [{
          label: 'Daily Revenue (₱)',
          data: trendValues.length ? trendValues : [0],
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37, 99, 235, 0.1)',
          fill: true,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(val) { return '₱' + val.toLocaleString(); }
            }
          }
        }
      }
    });
  }

  // 2. Donut Chart: Payment Methods
  const paymentData = <?= json_encode($paymentMethods) ?>;
  const payLabels = paymentData.map(p => p.payment_method.toUpperCase().replace('_', ' '));
  const payValues = paymentData.map(p => parseFloat(p.total_amount));

  const ctxDonut = document.getElementById('paymentDonutChart');
  if (ctxDonut) {
    new Chart(ctxDonut, {
      type: 'doughnut',
      data: {
        labels: payLabels.length ? payLabels : ['Cash'],
        datasets: [{
          data: payValues.length ? payValues : [1],
          backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }
});
</script>

<?php include APP_ROOT . '/app/Views/layouts/footer.php'; ?>
