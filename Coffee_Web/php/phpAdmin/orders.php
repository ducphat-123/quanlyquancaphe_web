<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

/* ===== XỬ LÝ XÓA HÓA ĐƠN ===== */
if (isset($_POST["btn_delete_invoice"])) {
    $id_invoice = (int)$_POST["id_invoice"];

    $sql = "DELETE FROM user_invoices WHERE id = $id_invoice";
    if ($conn->query($sql) === TRUE) {
        $success = "Xóa hóa đơn thành công!";
    } else {
        $error = "Lỗi: " . $conn->error;
    }
}

/* ===== LẤY DANH SÁCH HÓA ĐƠN + BỘ LỌC ===== */
$date_from = $_GET["date_from"] ?? "";
$date_to   = $_GET["date_to"] ?? "";
$search    = trim($_GET["txt_search_invoice"] ?? "");

$query = "SELECT ui.*, u.name AS customer_name, u.username
          FROM user_invoices ui
          LEFT JOIN users u ON ui.user_id = u.id
          WHERE 1=1";

if ($date_from !== "") {
    $query .= " AND DATE(ui.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to !== "") {
    $query .= " AND DATE(ui.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search !== "") {
    $s = $conn->real_escape_string($search);
    $query .= " AND (u.name LIKE '%$s%'
                OR u.username LIKE '%$s%'
                OR ui.product_name LIKE '%$s%')";
}

$query .= " ORDER BY ui.id DESC";
$invoices = $conn->query($query);

/* ===== TÍNH TỔNG DOANH THU ===== */
$revenue_query = "SELECT SUM(total_price) AS total FROM user_invoices WHERE 1=1";
if ($date_from !== "") {
    $revenue_query .= " AND DATE(created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to !== "") {
    $revenue_query .= " AND DATE(created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}
$total_revenue = $conn->query($revenue_query)->fetch_assoc()["total"] ?? 0;

/* ===== TOP KHÁCH HÀNG ===== */
$customer_stats_query = "SELECT u.name AS customer_name, u.username,
                         COUNT(ui.id) AS order_count,
                         SUM(ui.total_price) AS total_spent
                         FROM user_invoices ui
                         LEFT JOIN users u ON ui.user_id = u.id
                         WHERE 1=1";

if ($date_from !== "") {
    $customer_stats_query .= " AND DATE(ui.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to !== "") {
    $customer_stats_query .= " AND DATE(ui.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

$customer_stats_query .= " GROUP BY ui.user_id ORDER BY total_spent DESC LIMIT 3";
$customer_stats = $conn->query($customer_stats_query);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý hóa đơn</title>
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/orders.css?v=<?php echo time(); ?>">
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
                <li><a href="orders.php" class="active">Quản lý hóa đơn</a></li>
                <li><a href="statistics.php">Thống kê</a></li>
            </ul>
        </aside>

        <main class="content">
            <header class="topbar">
                <h1>QUẢN LÝ HÓA ĐƠN</h1>
                <div class="account">
                    👤 <?= htmlspecialchars($_SESSION["username"]) ?> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </header>

            <section class="welcome">

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <!-- THỐNG KÊ -->
                <div class="stats-container">
                    <div class="stats-box">
                        <h3>Tổng doanh thu</h3>
                        <div class="amount"><?= number_format($total_revenue, 0, ',', '.') ?>đ</div>
                    </div>
                    <div class="stats-box" style="background:linear-gradient(135deg,#f093fb,#f5576c)">
                        <h3>Tổng số hóa đơn</h3>
                        <div class="amount"><?= $invoices->num_rows ?></div>
                    </div>
                </div>

                <!-- TOP KHÁCH -->
                <?php if ($customer_stats->num_rows > 0): ?>
                    <div class="customer-stats">
                        <h3>Top khách hàng chi tiêu nhiều nhất</h3>
                        <?php while ($cs = $customer_stats->fetch_assoc()): ?>
                            <div class="customer-item">
                                <div>
                                    <strong><?= htmlspecialchars($cs["customer_name"]) ?></strong>
                                    <small>@<?= htmlspecialchars($cs["username"]) ?></small><br>
                                    <small><?= $cs["order_count"] ?> đơn</small>
                                </div>
                                <div>
                                    <strong style="color:#28a745;font-size:18px">
                                        <?= number_format($cs["total_spent"], 0, ',', '.') ?>đ
                                    </strong>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

                <!-- FILTER -->
                <div class="filter-section">
                    <h3>Bộ lọc hóa đơn</h3>
                    <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;">
                        <div class="form-group">
                            <label>Tìm kiếm</label>
                            <input type="text" name="txt_search_invoice" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="form-group">
                            <label>Từ ngày</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="form-group">
                            <label>Đến ngày</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div style="display:flex;gap:10px;align-items:end;">
                            <button class="btn btn-primary">Lọc</button>
                            <a href="orders.php" class="btn btn-danger">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- TABLE -->
                <div style="background:#fff;padding:20px;border-radius:8px;">
                    <h3>Danh sách hóa đơn (<?= $invoices->num_rows ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã HĐ</th>
                                <th>Khách hàng</th>
                                <th>Sản phẩm</th>
                                <th>Số lượng</th>
                                <th>Tổng tiền</th>
                                <th>Ngày mua</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows > 0): ?>
                                <?php while ($row = $invoices->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= $row["id"] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row["customer_name"]) ?></strong><br>
                                            <small>@<?= htmlspecialchars($row["username"]) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row["product_name"]) ?></td>
                                        <td><?= $row["quantity"] ?></td>
                                        <td><strong><?= number_format($row["total_price"], 0, ',', '.') ?>đ</strong></td>
                                        <td><?= date("d/m/Y H:i", strtotime($row["created_at"])) ?></td>
                                        <td>
                                            <button onclick="viewInvoiceDetail(<?= $row['id'] ?>)" class="btn btn-info">Chi tiết</button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Xóa hóa đơn này?')">
                                                <input type="hidden" name="id_invoice" value="<?= $row['id'] ?>">
                                                <button name="btn_delete_invoice" class="btn btn-danger">Xóa</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">Không có hóa đơn</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </main>
    </div>

    <!-- MODAL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDetailModal()">&times;</span>
            <h3>Chi tiết hóa đơn #<span id="detail_invoice_id"></span></h3>
            <div id="invoice_details_content">Đang tải...</div>
        </div>
    </div>

    <script>
        function viewInvoiceDetail(id) {
            detail_invoice_id.innerText = id;
            detailModal.style.display = 'block';

            fetch('get_order_details.php?id_invoice=' + id)
                .then(r => r.text())
                .then(html => invoice_details_content.innerHTML = html);
        }

        function closeDetailModal() {
            detailModal.style.display = 'none';
        }
        window.onclick = e => {
            if (e.target === detailModal) detailModal.style.display = 'none';
        };
    </script>

</body>

</html>