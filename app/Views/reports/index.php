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
      <p class="text-muted small mb-0">Monitor revenue trends, payment breakdowns, product velocities, and cashier performance.</p>
    </div>
    <div>
      <form method="GET" action="<?= BASE_URL ?>/reports" class="d-flex align-items-center gap-2">
        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
        <span class="text-muted small">to</span>
        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
      </form>
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

  <!-- Visual Charts Section -->
  <div class="row g-4 mb-4">
    <!-- Daily Revenue Trend (Line Chart) -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-3 border-0">
          <h5 class="fw-bold mb-0">Revenue Trend (Last 30 Days)</h5>
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
