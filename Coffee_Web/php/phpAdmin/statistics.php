<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

// ── THỐNG KÊ TỔNG ──
$totalUser    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM users WHERE role='user'"))["t"];
$totalProduct = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM products"))["t"];
$totalOrder   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM user_invoices"))["t"];
$totalRevenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(total_price),0) AS t FROM user_invoices"))["t"];

// ── BỘ LỌC ──
$filter = $_GET["filter"] ?? "month";
$year   = (int)($_GET["year"]  ?? date("Y"));
$month  = (int)($_GET["month"] ?? date("m"));
$week   = (int)($_GET["week"]  ?? date("W"));

switch ($filter) {
    case "day":
        $sqlChart = "SELECT HOUR(created_at) AS label, COUNT(*) AS orders, IFNULL(SUM(total_price),0) AS revenue
                     FROM user_invoices WHERE DATE(created_at) = CURDATE()
                     GROUP BY HOUR(created_at) ORDER BY label";
        $labelRange = range(0, 23);
        $labelFmt   = fn($v) => sprintf("%02d:00", $v);
        break;
    case "week":
        $sqlChart = "SELECT DAYOFWEEK(created_at) AS label, COUNT(*) AS orders, IFNULL(SUM(total_price),0) AS revenue
                     FROM user_invoices WHERE YEAR(created_at) = $year AND WEEK(created_at, 1) = $week
                     GROUP BY DAYOFWEEK(created_at) ORDER BY label";
        $labelRange = range(2, 8);
        $dayNames   = ["","CN","T2","T3","T4","T5","T6","T7","CN"];
        $labelFmt   = fn($v) => $dayNames[$v % 9] ?? "?";
        break;
    case "year":
        $sqlChart = "SELECT MONTH(created_at) AS label, COUNT(*) AS orders, IFNULL(SUM(total_price),0) AS revenue
                     FROM user_invoices WHERE YEAR(created_at) = $year
                     GROUP BY MONTH(created_at) ORDER BY label";
        $labelRange = range(1, 12);
        $labelFmt   = fn($v) => "Th$v";
        break;
    default:
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $sqlChart = "SELECT DAY(created_at) AS label, COUNT(*) AS orders, IFNULL(SUM(total_price),0) AS revenue
                     FROM user_invoices WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month
                     GROUP BY DAY(created_at) ORDER BY label";
        $labelRange = range(1, $daysInMonth);
        $labelFmt   = fn($v) => "$v";
        break;
}

$chartResult = mysqli_query($conn, $sqlChart);
$chartRaw = [];
while ($r = mysqli_fetch_assoc($chartResult)) $chartRaw[(int)$r["label"]] = $r;

$chartLabels = $chartOrders = $chartRevenue = [];
foreach ($labelRange as $v) {
    $chartLabels[]  = $labelFmt($v);
    $chartOrders[]  = $chartRaw[$v]["orders"]  ?? 0;
    $chartRevenue[] = $chartRaw[$v]["revenue"] ?? 0;
}

// Top 5 sản phẩm
$topProducts = [];
$topRes = mysqli_query($conn, "SELECT product_name AS name, SUM(quantity) AS sold FROM user_invoices GROUP BY product_name ORDER BY sold DESC LIMIT 5");
if ($topRes) while ($r = mysqli_fetch_assoc($topRes)) $topProducts[] = $r;

// Danh sách năm
$yearsRes = mysqli_query($conn, "SELECT DISTINCT YEAR(created_at) AS y FROM user_invoices ORDER BY y DESC");
$availableYears = [];
while ($r = mysqli_fetch_assoc($yearsRes)) $availableYears[] = $r["y"];
if (!in_array($year, $availableYears)) $availableYears[] = $year;
rsort($availableYears);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thống kê | Admin</title>
<link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/home.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
* { font-family: 'Be Vietnam Pro', Arial, sans-serif; }

/* KPI */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}
.kpi-card {
  background: white;
  border-radius: 8px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
  border-left: 4px solid var(--c, #4b2e2a);
  transition: transform .18s, box-shadow .18s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.1); }
.kpi-icon {
  width: 46px; height: 46px; border-radius: 10px;
  background: var(--bg, #f5ece9);
  display: grid; place-items: center;
  font-size: 1.35rem; flex-shrink: 0;
}
.kpi-value { font-size: 1.55rem; font-weight: 700; color: #2d1a17; line-height: 1.1; }
.kpi-label {
  font-size: .72rem; color: #999; font-weight: 600;
  text-transform: uppercase; letter-spacing: .04em; margin-top: 3px;
}

/* CHART + SECTIONS */
.stat-section {
  background: white;
  border-radius: 8px;
  padding: 20px 22px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
}
.sec-head {
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 10px; margin-bottom: 16px;
}
.sec-title {
  font-size: .95rem; font-weight: 700; color: #4b2e2a;
  display: flex; align-items: center; gap: 8px;
}
.sec-title::before {
  content: '';
  display: inline-block;
  width: 4px; height: 17px;
  background: #4b2e2a; border-radius: 3px;
}

/* FILTER */
.f-bar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.f-bar a {
  padding: 5px 13px; border-radius: 5px;
  border: 1.5px solid #d0b9b3;
  color: #6f4e37; font-size: .78rem; font-weight: 600;
  text-decoration: none; transition: all .15s;
}
.f-bar a:hover  { border-color: #4b2e2a; background: #fdf5f2; color: #4b2e2a; }
.f-bar a.on     { background: #4b2e2a; border-color: #4b2e2a; color: #fff; }
.f-bar select {
  padding: 5px 10px; border-radius: 5px;
  border: 1.5px solid #d0b9b3; color: #4b2e2a;
  font-size: .78rem; font-weight: 600;
  background: white; cursor: pointer;
  font-family: inherit; outline: none;
}
.f-bar select:focus { border-color: #4b2e2a; }

/* CHART */
.chart-wrap { position: relative; height: 290px; }

/* BOTTOM */
.bottom-grid { display: grid; grid-template-columns: 1fr 310px; gap: 20px; }

/* TOP PRODUCTS */
.prod-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 0; border-bottom: 1px solid #f3eeec;
}
.prod-row:last-child { border-bottom: none; }
.prod-no {
  width: 24px; height: 24px; border-radius: 5px; flex-shrink: 0;
  background: #f5ece9; color: #6f4e37;
  display: grid; place-items: center;
  font-size: .73rem; font-weight: 800;
}
.prod-no.top1 { background: #4b2e2a; color: #fff; }
.prod-name  { flex: 1; font-size: .85rem; font-weight: 500; color: #2d1a17; }
.bar-bg     { width: 76px; height: 6px; background: #f0e8e5; border-radius: 4px; overflow: hidden; }
.bar-fill   { height: 100%; background: linear-gradient(90deg,#4b2e2a,#a0522d); border-radius: 4px; }
.prod-qty   { font-size: .8rem; font-weight: 700; color: #6f4e37; min-width: 28px; text-align: right; }

/* DONUT */
.donut-wrap { position: relative; height: 195px; }
.donut-center {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; pointer-events: none;
}
.d-num { font-size: 1.75rem; font-weight: 700; color: #2d1a17; line-height: 1; }
.d-lbl { font-size: .68rem; color: #aaa; text-transform: uppercase; letter-spacing: .05em; }
.leg-list { display: flex; flex-direction: column; gap: 5px; margin-top: 4px; }
.leg-row  { display: flex; align-items: center; gap: 7px; font-size: .78rem; }
.leg-dot  { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }

@media(max-width:1100px){ .kpi-grid{ grid-template-columns: repeat(2,1fr); } }
@media(max-width:800px) { .bottom-grid{ grid-template-columns: 1fr; } }
@media(max-width:560px) { .kpi-grid{ grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<div class="layout">

  <aside class="sidebar">
    <h2>☕ADMIN</h2>
    <ul>
      <li><a href="adminHome.php">Trang chủ</a></li>
      <li><a href="adminUsers.php">Quản lý người dùng</a></li>
      <li><a href="categories.php">Quản lý danh mục sản phẩm</a></li>
      <li><a href="products.php">Quản lý sản phẩm</a></li>
      <li><a href="orders.php">Quản lý hóa đơn</a></li>
      <li><a href="statistics.php" >Thống kê</a></li>
    </ul>
  </aside>

  <main class="content">

    <div class="topbar">
      <h1>📊 Thống kê</h1>
      <div class="account">
        👤 <?= htmlspecialchars($_SESSION["username"]) ?> |
        <a href="../phpAccount/logout.php">Đăng xuất</a>
      </div>
    </div>

    <!-- KPI CARDS -->
    <div class="kpi-grid">
      <div class="kpi-card" style="--c:#4b2e2a;--bg:#f5ece9">
        <div class="kpi-icon">👤</div>
        <div><div class="kpi-value"><?= number_format($totalUser) ?></div><div class="kpi-label">Người dùng</div></div>
      </div>
      <div class="kpi-card" style="--c:#6f4e37;--bg:#fdf0e8">
        <div class="kpi-icon">☕</div>
        <div><div class="kpi-value"><?= number_format($totalProduct) ?></div><div class="kpi-label">Sản phẩm</div></div>
      </div>
      <div class="kpi-card" style="--c:#a0522d;--bg:#fdf5ee">
        <div class="kpi-icon">🧾</div>
        <div><div class="kpi-value"><?= number_format($totalOrder) ?></div><div class="kpi-label">Đơn hàng</div></div>
      </div>
      <div class="kpi-card" style="--c:#c0392b;--bg:#fdecea">
        <div class="kpi-icon">💰</div>
        <div><div class="kpi-value"><?= number_format($totalRevenue/1000000,1) ?>M ₫</div><div class="kpi-label">Doanh thu</div></div>
      </div>
    </div>

    <!-- BIỂU ĐỒ -->
    <div class="stat-section">
      <div class="sec-head">
        <div class="sec-title">Biểu đồ đơn hàng &amp; doanh thu</div>
        <form method="GET" style="display:contents">
          <div class="f-bar">
            <?php foreach(["day"=>"Hôm nay","week"=>"Tuần","month"=>"Tháng","year"=>"Năm"] as $k=>$v): ?>
              <a href="?filter=<?=$k?>&year=<?=$year?>&month=<?=$month?>&week=<?=$week?>"
                 class="<?=$filter===$k?'on':''?>"><?=$v?></a>
            <?php endforeach; ?>

            <select name="year" onchange="this.form.submit()">
              <?php foreach($availableYears as $y): ?>
                <option value="<?=$y?>" <?=$y===$year?'selected':''?>><?=$y?></option>
              <?php endforeach; ?>
            </select>

            <?php if(in_array($filter,['month','week'])): ?>
            <select name="month" onchange="this.form.submit()">
              <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?=$m?>" <?=$m===$month?'selected':''?>>Tháng <?=$m?></option>
              <?php endfor; ?>
            </select>
            <?php endif; ?>

            <?php if($filter==='week'): ?>
            <select name="week" onchange="this.form.submit()">
              <?php for($w=1;$w<=53;$w++): ?>
                <option value="<?=$w?>" <?=$w===$week?'selected':''?>>Tuần <?=$w?></option>
              <?php endfor; ?>
            </select>
            <?php endif; ?>

            <input type="hidden" name="filter" value="<?=$filter?>">
          </div>
        </form>
      </div>
      <div class="chart-wrap"><canvas id="mainChart"></canvas></div>
    </div>

    <!-- BOTTOM -->
    <div class="bottom-grid">

      <div class="stat-section" style="margin-bottom:0">
        <div class="sec-head"><div class="sec-title">Top sản phẩm bán chạy</div></div>
        <?php
        $maxSold = $topProducts[0]["sold"] ?? 1;
        foreach($topProducts as $i=>$p):
          $pct = round($p["sold"]/$maxSold*100);
        ?>
        <div class="prod-row">
          <div class="prod-no <?=$i===0?'top1':''?>"><?=$i+1?></div>
          <div class="prod-name"><?= htmlspecialchars($p["name"]) ?></div>
          <div class="bar-bg"><div class="bar-fill" style="width:<?=$pct?>%"></div></div>
          <div class="prod-qty"><?= $p["sold"] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($topProducts)): ?>
          <p style="color:#bbb;text-align:center;padding:18px 0;font-size:.84rem">Chưa có dữ liệu</p>
        <?php endif; ?>
      </div>

      <div class="stat-section" style="margin-bottom:0">
        <div class="sec-head"><div class="sec-title">Phân bổ đơn hàng</div></div>
        <div class="donut-wrap">
          <canvas id="donutChart"></canvas>
          <div class="donut-center">
            <div class="d-num"><?= array_sum($chartOrders) ?></div>
            <div class="d-lbl">đơn hàng</div>
          </div>
        </div>
        <div class="leg-list" id="donutLegend"></div>
      </div>

    </div>
  </main>
</div>

<script>
const labels  = <?= json_encode($chartLabels) ?>;
const orders  = <?= json_encode($chartOrders) ?>;
const revenue = <?= json_encode(array_map('floatval', $chartRevenue)) ?>;

Chart.defaults.font.family = "'Be Vietnam Pro', Arial, sans-serif";
Chart.defaults.color = '#999';

// Gradient cho bar
const ctx = document.getElementById('mainChart').getContext('2d');
const gBar = ctx.createLinearGradient(0, 0, 0, 290);
gBar.addColorStop(0, 'rgba(75,46,42,.82)');
gBar.addColorStop(1, 'rgba(75,46,42,.12)');
const gLine = ctx.createLinearGradient(0, 0, 0, 290);
gLine.addColorStop(0, 'rgba(160,82,45,.28)');
gLine.addColorStop(1, 'rgba(160,82,45,0)');

new Chart(ctx, {
  data: {
    labels,
    datasets: [
      {
        type: 'bar', label: 'Số đơn hàng', data: orders,
        backgroundColor: gBar, borderColor: '#4b2e2a', borderWidth: 1.5,
        borderRadius: 5, yAxisID: 'yL',
      },
      {
        type: 'line', label: 'Doanh thu (₫)', data: revenue,
        borderColor: '#a0522d', backgroundColor: gLine,
        borderWidth: 2.5, tension: 0.4,
        pointRadius: 4, pointBackgroundColor: '#fff',
        pointBorderColor: '#a0522d', pointBorderWidth: 2,
        fill: true, yAxisID: 'yR',
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top', labels: { boxWidth: 12, padding: 18, color: '#555', font: { weight: '600' } } },
      tooltip: {
        backgroundColor: '#fff', titleColor: '#2d1a17', bodyColor: '#6f4e37',
        borderColor: '#e8d8d3', borderWidth: 1, padding: 12,
        callbacks: {
          label: c => c.datasetIndex === 1
            ? ' ' + c.parsed.y.toLocaleString('vi-VN') + ' ₫'
            : ' ' + c.parsed.y + ' đơn'
        }
      }
    },
    scales: {
      x: { grid: { color: '#f0eae8' }, ticks: { maxRotation: 0 } },
      yL: { position: 'left',  grid: { color: '#f0eae8' }, ticks: { stepSize: 1, color: '#4b2e2a', font: { weight: '600' } } },
      yR: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => (v/1e6).toFixed(1)+'M', color: '#a0522d', font: { weight: '600' } } }
    }
  }
});

// Donut
const palette = ['#4b2e2a','#6f4e37','#a0522d','#c0895a','#d4b896','#8b5e3c'];
const ctx2 = document.getElementById('donutChart').getContext('2d');
new Chart(ctx2, {
  type: 'doughnut',
  data: {
    labels,
    datasets: [{ data: orders, backgroundColor: palette, borderColor: '#fff', borderWidth: 3, hoverOffset: 8 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '68%',
    plugins: {
      legend: { display: false },
      tooltip: { backgroundColor:'#fff', titleColor:'#2d1a17', bodyColor:'#6f4e37', borderColor:'#e8d8d3', borderWidth:1, padding:10 }
    }
  }
});

const leg = document.getElementById('donutLegend');
labels.forEach((l, i) => {
  if (orders[i] > 0)
    leg.innerHTML += `<div class="leg-row"><div class="leg-dot" style="background:${palette[i%palette.length]}"></div><span style="flex:1;color:#666">${l}</span><strong style="color:#4b2e2a">${orders[i]}</strong></div>`;
});
</script>
</body>
</html>