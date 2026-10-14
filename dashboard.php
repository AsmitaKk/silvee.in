<?php
require_once "auth_check.php";

$page_title = "Dashboard";
require "templates/header.php";

// Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalQty = $pdo->query("SELECT COALESCE(SUM(total_stock_qty),0) FROM products")->fetchColumn();

// Total weight based on stock movements (IN - OUT)
$totalWeight = $pdo->query("SELECT COALESCE(SUM(bulk_total_weight), 0) FROM products")->fetchColumn();

// Total Refunded Amount (Prorated manually by individual items returned)
$totalRefundsSQL = "
    SELECT COALESCE(SUM(i.returned_qty * i.price_per_item), 0)
    FROM store_order_items i
    JOIN store_orders o ON i.order_id = o.id
    WHERE o.status IN ('Completed', 'Partially Returned', 'Returned')
";
$totalRefunds = $pdo->query($totalRefundsSQL)->fetchColumn();

// Top-level (main) categories for dashboard cards
$stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
$topCategories = $stmt->fetchAll();

// All categories map: id => [id, parent_id, name]
$stmtAll = $pdo->query("SELECT id, parent_id, name FROM categories");
$allCategories = $stmtAll->fetchAll();
$catById = [];
foreach ($allCategories as $c) {
  $catById[$c['id']] = $c;
}

// All low-stock products (product-level)
$lowProducts = $pdo->query("
    SELECT id, name, category_id 
    FROM products 
    WHERE total_stock_qty <= min_stock_level 
      AND min_stock_level > 0
")->fetchAll();

/**
 * Requirement:
 * For each MAIN category, show how many SUB-CATEGORIES under it
 * have at least one low-stock product.
 */

// main_id => [ subcat_id => true, ... ]
$lowSubcatsByMain = [];

foreach ($lowProducts as $p) {
  $leafId = (int) $p['category_id'];
  if (!$leafId || !isset($catById[$leafId])) {
    continue;
  }

  // Walk up to the top-level, and keep track of the chain
  $chain = [];
  $currentId = $leafId;

  while (isset($catById[$currentId])) {
    $chain[] = $currentId;
    $parentId = $catById[$currentId]['parent_id'];

    if ($parentId === null) {
      // reached top-level
      break;
    }

    if (!isset($catById[$parentId])) {
      break;
    }

    $currentId = (int) $parentId;
  }

  if (empty($chain)) {
    continue;
  }

  // Last in chain is top-level category ID
  $topId = end($chain);

  // The sub-category we count
  if (count($chain) === 1) {
    $subcatId = $topId;
  } else {
    $subcatId = $chain[count($chain) - 2];
  }

  if (!isset($lowSubcatsByMain[$topId])) {
    $lowSubcatsByMain[$topId] = [];
  }
  $lowSubcatsByMain[$topId][$subcatId] = true;
}

// Now build final: main_id => count of low-stock sub-categories
$lowSubcatCountByMain = [];
foreach ($lowSubcatsByMain as $mainId => $subcatSet) {
  $lowSubcatCountByMain[$mainId] = count($subcatSet);
}

$today = date('F j, Y');
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'there';
?>



<!-- Category cards -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <h5 class="mb-0">Categories</h5>
  <span class="small text-secondary">
    Click a category to see its sub-categories and products.
  </span>
</div>

<div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3 mb-4">
  <?php foreach ($topCategories as $cat): ?>
    <?php
    $lowSubcats = $lowSubcatCountByMain[$cat['id']] ?? 0;
    $needsAttention = $lowSubcats > 0;
    ?>
    <div class="col-6 col-md-3 col-lg-2">
      <a href="category.php?id=<?= $cat['id'] ?>" class="text-decoration-none text-reset">
        <div class="card category-card h-100 position-relative">
          <?php if ($needsAttention): ?>
            <span class="badge bg-danger category-low-badge position-absolute text-wrap text-center"
              style="top:8px; right:8px; left: 8px; z-index: 10; font-size: 0.65rem; padding: 0.2rem 0.4rem;">
              Low in <?= $lowSubcats ?> sub-cat<?= $lowSubcats > 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>

          <?php if (!empty($cat['image_path'])): ?>
            <img src="<?= e($cat['image_path']) ?>" class="card-img-top" style="height:130px;object-fit:cover;">
          <?php else: ?>
            <div class="card-img-top d-flex align-items-center justify-content-center"
              style="height:130px; background-color:#f3f4f6;">
              <span class="fw-semibold text-secondary"><?= e($cat['name']) ?></span>
            </div>
          <?php endif; ?>

          <div class="card-body text-center py-2 px-1">
            <h6 class="card-title mb-1 small text-truncate px-1"><?= e($cat['name']) ?></h6>
            <span class="small text-secondary text-wrap" style="font-size: 0.7rem; display: block; line-height: 1.1;">
              <?= $needsAttention ? 'Some sub-categories are low' : 'All good' ?>
            </span>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>

  <?php if (empty($topCategories)): ?>
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body text-center text-secondary">
          No categories yet. Create some in the <strong>Categories</strong> page to get started.
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card h-100 p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
        <div>
          <div class="stat-label">Total Products</div>
          <div class="stat-value"><?= (int) $totalProducts ?></div>
        </div>
        <div class="stat-icon bg-indigo-50 text-primary-emphasis border border-primary-subtle">
          <i class="bi bi-gem"></i>
        </div>
      </div>
      <div class="small text-secondary">
        Unique product entries.
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="stat-card h-100 p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
        <div>
          <div class="stat-label">Total Quantity</div>
          <div class="stat-value"><?= (int) $totalQty ?></div>
        </div>
        <div class="stat-icon bg-sky-50 text-info-emphasis border border-info-subtle">
          <i class="bi bi-box-seam"></i>
        </div>
      </div>
      <div class="small text-secondary">
        Total pieces in stock.
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="stat-card h-100 p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
        <div>
          <div class="stat-label">Total Weight</div>
          <div class="stat-value"><?= number_format($totalWeight, 2) ?> <span style="font-size:1.1rem;">gm</span></div>
        </div>
        <div class="stat-icon bg-amber-50 text-warning-emphasis border border-warning-subtle">
          <i class="bi bi-speedometer2"></i>
        </div>
      </div>
      <div class="small text-secondary">
        Net weight in stock.
      </div>
    </div>
  </div>
</div>

<!-- Sales Analytics Chart -->
<div class="sales-chart-container">
  <div class="chart-header">
    <h5 class="chart-title">Sales Analytics</h5>
    <div class="chart-controls">
      <select id="yearSelector" class="form-select">
        <?php
        $currentYear = date('Y');
        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
          echo "<option value='$year'" . ($year == $currentYear ? ' selected' : '') . ">$year</option>";
        }
        ?>
      </select>
      <button id="backToMonthly" class="btn btn-outline-secondary btn-sm" style="display: none;">
        <i class="bi bi-arrow-left"></i> Back to Monthly
      </button>
    </div>
  </div>

  <nav id="breadcrumbNav" style="display: none;">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="#" id="monthlyBreadcrumb">Monthly View</a>
      </li>
      <li class="breadcrumb-item active" id="dailyBreadcrumb"></li>
    </ol>
  </nav>

  <div class="chart-canvas-container">
    <canvas id="salesChart"></canvas>
    <div id="chartLoading" class="chart-loading" style="display: none;">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
  </div>

  <div class="chart-stats">
    <div class="chart-stat">
      <div class="chart-stat-value" id="totalSalesStat">0</div>
      <div class="chart-stat-label">Total Sales</div>
    </div>
    <div class="chart-stat">
      <div class="chart-stat-value" id="totalItemsStat">0</div>
      <div class="chart-stat-label">Total Items</div>
    </div>
    <div class="chart-stat">
      <div class="chart-stat-value" id="totalWeightStat">0</div>
      <div class="chart-stat-label">Total Weight</div>
    </div>
    <div class="chart-stat">
      <div class="chart-stat-value" id="avgItemsStat">0</div>
      <div class="chart-stat-label">Avg Items/Sale</div>
    </div>
  </div>
</div>

<!-- ✅ Sales Trend Comparison -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
      <h6 class="mb-0">Sales Trend Comparison</h6>
      <small class="text-secondary" id="trendPeriodText">—</small>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">This Month vs Last Month (₹)</div>
          <div class="fw-semibold" id="momAmountText">—</div>
          <div class="small" id="momItemsText">—</div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">This Month vs Same Month Last Year (₹)</div>
          <div class="fw-semibold" id="yoyAmountText">—</div>
          <div class="small" id="yoyItemsText">—</div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">YTD vs Last Year YTD (₹)</div>
          <div class="fw-semibold" id="ytdAmountText">—</div>
          <div class="small" id="ytdItemsText">—</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Month Insights (City-wise ₹ Amount + Top Product) -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
      <h6 class="mb-0">Month Insights</h6>
      <small class="text-secondary">Shown when you open a month (Daily View)</small>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">Highest City (Items)</div>
          <div class="fw-semibold" id="highestCityText">—</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">Lowest City (Items)</div>
          <div class="fw-semibold" id="lowestCityText">—</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded border bg-light">
          <div class="small text-secondary">Most Sold Product (Qty)</div>
          <div class="fw-semibold" id="topProductText">—</div>
        </div>
      </div>
    </div>

    <div style="height:320px;">
      <canvas id="citySalesChart"></canvas>
    </div>
  </div>
</div>



<?php require "templates/footer.php"; ?>

<script>
  let salesChart = null;
  let citySalesChart = null;

  let currentView = 'monthly';
  let currentYear = new Date().getFullYear();
  let currentMonth = null;

  const formatINR = (n) => new Intl.NumberFormat('en-IN', {
    style: 'currency', currency: 'INR', maximumFractionDigits: 0
  }).format(Number(n || 0));

  const formatPct = (n) => {
    const v = Number(n || 0);
    const sign = v > 0 ? '+' : '';
    return `${sign}${v.toFixed(1)}%`;
  };

  // Initialize chart on page load
  document.addEventListener('DOMContentLoaded', function () {
    loadMonthlyData(currentYear);

    // Load trend comparison for current month initially
    const nowMonth = new Date().getMonth() + 1;
    loadTrendComparison(currentYear, nowMonth);

    // Year selector change event
    document.getElementById('yearSelector').addEventListener('change', function () {
      currentYear = parseInt(this.value);

      if (currentView === 'monthly') {
        loadMonthlyData(currentYear);

        // keep comparison for current month but in selected year
        const m = (new Date().getMonth() + 1);
        loadTrendComparison(currentYear, m);

      } else {
        loadDailyData(currentYear, currentMonth);
        loadMonthInsights(currentYear, currentMonth);
        loadTrendComparison(currentYear, currentMonth);
      }
    });

    // Back to monthly button
    document.getElementById('backToMonthly').addEventListener('click', function () {
      showMonthlyView();
    });

    // Monthly breadcrumb click
    document.getElementById('monthlyBreadcrumb').addEventListener('click', function (e) {
      e.preventDefault();
      showMonthlyView();
    });
  });

  function showMonthlyView() {
    currentView = 'monthly';
    currentMonth = null;
    document.getElementById('backToMonthly').style.display = 'none';
    document.getElementById('breadcrumbNav').style.display = 'none';

    // Reset Month Insights
    document.getElementById('highestCityText').textContent = '—';
    document.getElementById('lowestCityText').textContent = '—';
    document.getElementById('topProductText').textContent = '—';
    if (citySalesChart) {
      citySalesChart.destroy();
      citySalesChart = null;
    }

    loadMonthlyData(currentYear);

    // trend comparison for current month
    const m = (new Date().getMonth() + 1);
    loadTrendComparison(currentYear, m);
  }

  function showDailyView(month) {
    currentView = 'daily';
    currentMonth = month;
    document.getElementById('backToMonthly').style.display = 'inline-flex';
    document.getElementById('breadcrumbNav').style.display = 'block';
    document.getElementById('dailyBreadcrumb').textContent = new Date(currentYear, month - 1).toLocaleDateString('en-US', { month: 'long' }) + ' ' + currentYear;

    loadDailyData(currentYear, month);
    loadMonthInsights(currentYear, month);
    loadTrendComparison(currentYear, month);
  }

  function showLoading(show) {
    document.getElementById('chartLoading').style.display = show ? 'flex' : 'none';
    if (salesChart) {
      salesChart.canvas.style.display = show ? 'none' : 'block';
    }
  }

  function updateStats(data) {
    let totalSales = 0;
    let totalItems = 0;
    let totalWeight = 0;
    let totalTransactions = 0;

    data.forEach(item => {
      totalSales += item.total_sales || 0;
      totalItems += item.total_items || 0;
      totalWeight += item.total_weight || 0;
      if (item.total_sales > 0) totalTransactions++;
    });

    document.getElementById('totalSalesStat').textContent = totalSales;
    document.getElementById('totalItemsStat').textContent = totalItems;
    document.getElementById('totalWeightStat').textContent = totalWeight.toFixed(2);
    document.getElementById('avgItemsStat').textContent = totalTransactions > 0 ? (totalItems / totalTransactions).toFixed(1) : '0';
  }

  function loadMonthlyData(year) {
    showLoading(true);

    fetch(`sales_api.php?type=monthly&year=${year}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderMonthlyChart(data.data);
          updateStats(data.data);
        } else {
          console.error('Error loading monthly data:', data.error);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      })
      .finally(() => {
        showLoading(false);
      });
  }

  function loadDailyData(year, month) {
    showLoading(true);

    fetch(`sales_api.php?type=daily&year=${year}&month=${month}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderDailyChart(data.data);
          updateStats(data.data);
        } else {
          console.error('Error loading daily data:', data.error);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      })
      .finally(() => {
        showLoading(false);
      });
  }

  // Month insights: city-wise amount + top product
  function loadMonthInsights(year, month) {
    fetch(`sales_api.php?type=month_insights&year=${year}&month=${month}`)
      .then(response => response.json())
      .then(res => {
        console.log('Month insights response:', res); // Debug log
        if (res.success) {
          renderCityAmountChart(res.data.city_totals || []);
          updateMonthInsightCards(res.data);
        } else {
          console.error('Error loading month insights:', res.error);
        }
      })
      .catch(error => {
        console.error('Month insights error:', error);
      });
  }

  function updateMonthInsightCards(d) {
    console.log('Updating insight cards with data:', d); // Debug log
    const highest = d.highest_city;
    const lowest = d.lowest_city;
    const topProd = d.top_product;

    document.getElementById('highestCityText').textContent =
      highest ? `${highest.city} • ${highest.total_items} pcs` : '—';

    document.getElementById('lowestCityText').textContent =
      lowest ? `${lowest.city} • ${lowest.total_items} pcs` : '—';

    document.getElementById('topProductText').textContent =
      topProd ? `${topProd.product_name} • ${topProd.total_items} pcs` : '—';

    console.log('Insight cards updated successfully');
  }

  function renderCityAmountChart(rows) {
    const el = document.getElementById('citySalesChart');
    if (!el) {
      console.log('City sales chart element not found');
      return;
    }

    const ctx = el.getContext('2d');
    if (citySalesChart) {
      citySalesChart.destroy();
    }

    console.log('Rendering city chart with data:', rows); // Debug log

    citySalesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.city),
        datasets: [{
          label: 'Items Sold',
          data: rows.map(r => Number(r.total_items || 0)),
          backgroundColor: 'rgba(245, 158, 11, 0.8)',
          borderColor: 'rgba(245, 158, 11, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (context) {
                return 'Quantity: ' + context.parsed.y + ' pcs';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });

    console.log('City chart rendered successfully');
  }

  // ✅ Trend comparison (MoM + YoY + YTD)
  function loadTrendComparison(year, month) {
    fetch(`sales_api.php?type=trend_comparison&year=${year}&month=${month}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          console.error('Trend comparison error:', res.error);
          return;
        }
        renderTrendComparison(res.data);
      })
      .catch(err => console.error('Trend comparison fetch error:', err));
  }

  function renderTrendComparison(d) {
    const p = d.period;
    const monthName = new Date(p.year, p.month - 1).toLocaleDateString('en-US', { month: 'long' });

    document.getElementById('trendPeriodText').textContent =
      `${monthName} ${p.year} (Comparisons)`;

    // MoM
    document.getElementById('momAmountText').textContent =
      `${formatINR(d.month_current.total_amount)} vs ${formatINR(d.month_previous.total_amount)} (${formatPct(d.changes.mom_amount_pct)})`;
    document.getElementById('momItemsText').textContent =
      `Qty: ${d.month_current.total_items} vs ${d.month_previous.total_items} (${formatPct(d.changes.mom_items_pct)})`;

    // YoY
    document.getElementById('yoyAmountText').textContent =
      `${formatINR(d.month_current.total_amount)} vs ${formatINR(d.month_same_last_year.total_amount)} (${formatPct(d.changes.yoy_amount_pct)})`;
    document.getElementById('yoyItemsText').textContent =
      `Qty: ${d.month_current.total_items} vs ${d.month_same_last_year.total_items} (${formatPct(d.changes.yoy_items_pct)})`;

    // YTD
    document.getElementById('ytdAmountText').textContent =
      `${formatINR(d.ytd_current.total_amount)} vs ${formatINR(d.ytd_last_year.total_amount)} (${formatPct(d.changes.ytd_amount_pct)})`;
    document.getElementById('ytdItemsText').textContent =
      `Qty: ${d.ytd_current.total_items} vs ${d.ytd_last_year.total_items} (${formatPct(d.changes.ytd_items_pct)})`;
  }

  function renderMonthlyChart(data) {
    const ctx = document.getElementById('salesChart').getContext('2d');

    if (salesChart) {
      salesChart.destroy();
    }

    salesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(item => item.month_name.substring(0, 3)),
        datasets: [{
          label: 'Total Sales',
          data: data.map(item => item.total_sales),
          backgroundColor: 'rgba(59, 130, 246, 0.8)',
          borderColor: 'rgba(59, 130, 246, 1)',
          borderWidth: 1,
          borderRadius: 4,
          hoverBackgroundColor: 'rgba(59, 130, 246, 1)'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        onClick: (event, elements) => {
          if (elements.length > 0) {
            const index = elements[0].index;
            const month = data[index].month;
            showDailyView(month);
          }
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              title: function (context) {
                return data[context[0].dataIndex].month_name + ' ' + currentYear;
              },
              label: function (context) {
                return 'Sales: ' + context.parsed.y;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              borderDash: [2, 2]
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        },
        animation: {
          duration: 750,
          easing: 'easeInOutQuart'
        }
      }
    });
  }

  function renderDailyChart(data) {
    const ctx = document.getElementById('salesChart').getContext('2d');

    if (salesChart) {
      salesChart.destroy();
    }

    salesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(item => item.day),
        datasets: [{
          label: 'Total Sales',
          data: data.map(item => item.total_sales),
          backgroundColor: 'rgba(16, 185, 129, 0.8)',
          borderColor: 'rgba(16, 185, 129, 1)',
          borderWidth: 1,
          borderRadius: 4,
          hoverBackgroundColor: 'rgba(16, 185, 129, 1)'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              title: function (context) {
                const monthName = new Date(currentYear, currentMonth - 1).toLocaleDateString('en-US', { month: 'long' });
                return monthName + ' ' + context[0].label + ', ' + currentYear;
              },
              label: function (context) {
                return 'Sales: ' + context.parsed.y;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              borderDash: [2, 2]
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        },
        animation: {
          duration: 750,
          easing: 'easeInOutQuart'
        }
      }
    });
  }
</script>