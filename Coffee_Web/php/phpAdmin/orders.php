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

// $query = "SELECT ui.*, u.name AS customer_name, u.username
//           FROM user_invoices ui
//           LEFT JOIN users u ON ui.user_id = u.id
//           WHERE 1=1";
// Lấy thông tin từ orders, nối với users (lấy tên khách) và vouchers (lấy tên mã)
// Dùng subquery để đếm xem đơn này có bao nhiêu ly/món
$query = "SELECT o.*, u.name AS customer_name, u.username, v.voucher_code,
          (SELECT COUNT(id) FROM user_invoices WHERE order_id = o.id) as item_count
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN vouchers v ON o.voucher_id = v.voucher_id 
          WHERE 1=1";
// if ($date_from !== "") {
//     $query .= " AND DATE(ui.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
// }

// if ($date_to !== "") {
//     $query .= " AND DATE(ui.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
// }

// if ($search !== "") {
//     $s = $conn->real_escape_string($search);
//     $query .= " AND (u.name LIKE '%$s%'
//                 OR u.username LIKE '%$s%'
//                 OR ui.product_name LIKE '%$s%')";
// }
if ($date_from !== "") {
    $query .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to !== "") {
    $query .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search !== "") {
    $s = $conn->real_escape_string($search);
    $query .= " AND (
        u.name LIKE '%$s%' 
        OR u.username LIKE '%$s%' 
        OR v.voucher_code LIKE '%$s%'
        OR EXISTS (
            SELECT 1 
            FROM user_invoices ui 
            WHERE ui.order_id = o.id AND ui.product_name LIKE '%$s%'
        )
    )";
}

$query .= " ORDER BY o.id DESC";
try {
    $invoices = $conn->query($query);
} catch (mysqli_sql_exception $e) {
    echo "<div style='background:#ffdddd; padding:15px; border:1px solid red; margin-bottom: 20px;'>";
    echo "<strong>Lỗi MySQL báo về:</strong> " . $e->getMessage() . "<br><br>";
    echo "<strong>Câu lệnh SQL lúc chạy:</strong> " . $query;
    echo "</div>";
    exit;
}
// $invoices = $conn->query($query);

/* ===== TÍNH TỔNG DOANH THU ===== */
// $revenue_query = "SELECT SUM(total_price) AS total FROM user_invoices WHERE 1=1";
// if ($date_from !== "") {
//     $revenue_query .= " AND DATE(created_at) >= '" . $conn->real_escape_string($date_from) . "'";
// }
// if ($date_to !== "") {
//     $revenue_query .= " AND DATE(created_at) <= '" . $conn->real_escape_string($date_to) . "'";
// }
// $total_revenue = $conn->query($revenue_query)->fetch_assoc()["total"] ?? 0;
$revenue_query = "SELECT SUM(final_amount) AS total FROM orders WHERE 1=1";
if ($date_from !== "") {
    $revenue_query .= " AND DATE(created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to !== "") {
    $revenue_query .= " AND DATE(created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}
$total_revenue = $conn->query($revenue_query)->fetch_assoc()["total"] ?? 0;
/* ===== TOP KHÁCH HÀNG ===== */
// $customer_stats_query = "SELECT u.name AS customer_name, u.username,
//                          COUNT(ui.id) AS order_count,
//                          SUM(ui.total_price) AS total_spent
//                          FROM user_invoices ui
//                          LEFT JOIN users u ON ui.user_id = u.id
//                          WHERE 1=1";
/* ===== TOP KHÁCH HÀNG (Dựa trên số tiền Thực thu) ===== */
$customer_stats_query = "SELECT u.name AS customer_name, u.username,
                         COUNT(o.id) AS order_count,
                         SUM(o.final_amount) AS total_spent
                         FROM orders o
                         LEFT JOIN users u ON o.user_id = u.id
                         WHERE 1=1";

if ($date_from !== "") {
    $customer_stats_query .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to !== "") {
    $customer_stats_query .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

$customer_stats_query .= " GROUP BY o.user_id ORDER BY total_spent DESC LIMIT 3";
$customer_stats = $conn->query($customer_stats_query);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý hóa đơn</title>
    <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/orders.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="layout">

        <aside class="sidebar">
            <h2>☕ADMIN</h2>
            <ul>
                <li><a href="adminHome.php">Trang chủ</a></li>
                <li><a href="adminUsers.php">Quản lý người dùng</a></li>
                <li><a href="categories.php">Quản lý danh mục sản phẩm</a></li>
                <li><a href="vouchers.php">Quản lý mã giảm giá</a></li>
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
                            <label>Tìm kiếm (Khách hàng / Mã voucher)</label>
                            <input type="text" name="txt_search_invoice" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập tên, username hoặc mã code...">
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
                                <th>Số lượng món</th>
                                <th>Tổng tiền gốc</th>
                                <th>Mã giảm giá</th>
                                <th>Thực thu</th>
                                <th>Ngày mua</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows > 0): ?>
                                <?php while ($row = $invoices->fetch_assoc()): ?>
                                    <tr>
                                        <td style="text-align:center;"><strong>#<?= $row["id"] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row["customer_name"] ?? 'Khách lẻ') ?></strong><br>
                                            <small>@<?= htmlspecialchars($row["username"] ?? 'N/A') ?></small>
                                        </td>
                                        <td style="text-align:center;"><span class="badge"><?= $row["item_count"] ?> món</span></td>
                                        <td style="text-align:center; color:#555;"><del><?= number_format($row["total_amount"], 0, ',', '.') ?>đ</del></td>
                                        
                                        <td style="text-align:center;">
                                            <?php if ($row["discount_amount"] > 0): ?>
                                                <strong style="color:#e74c3c;">-<?= number_format($row["discount_amount"], 0, ',', '.') ?>đ</strong><br>
                                                <small style="background:#f1c40f; padding:2px 5px; border-radius:3px; color:#000;">
                                                    <?= htmlspecialchars($row["voucher_code"]) ?>
                                                </small>
                                            <?php else: ?>
                                                <span style="color:#999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="text-align:center;"><strong style="color:#28a745; font-size:16px;"><?= number_format($row["final_amount"], 0, ',', '.') ?>đ</strong></td>
                                        <td style="text-align:center;"><?= date("d/m/Y H:i", strtotime($row["created_at"])) ?></td>
                                        <td style="text-align:center;">
                                            <button onclick="viewInvoiceDetail(<?= $row['id'] ?>)" class="btn btn-info" style="background:#17a2b8; color:white;">Chi tiết</button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ hóa đơn này?')">
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;">Không có hóa đơn</td>
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
        <div class="modal-content" style="width: 500px;">
            <span class="close" onclick="closeDetailModal()">&times;</span>
            <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px;">Chi tiết hóa đơn #<span id="detail_invoice_id"></span></h3>
            <div id="invoice_details_content" style="margin-top: 15px;">Đang tải...</div>
    </div>

    <script>
        function viewInvoiceDetail(id) {
                        document.getElementById('detail_invoice_id').innerText = id;
            document.getElementById('detailModal').style.display = 'block';

            // Gọi API lấy chi tiết món (File này bạn nhớ viết query từ bảng user_invoices WHERE order_id = id nhé)
            fetch('get_order_details.php?id_invoice=' + id)
                .then(r => r.text())
                .then(html => document.getElementById('invoice_details_content').innerHTML = html)
                .catch(err => document.getElementById('invoice_details_content').innerHTML = '<span style="color:red">Lỗi tải dữ liệu</span>');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
        window.onclick = e => {
            if (e.target === document.getElementById('detailModal')) closeDetailModal();
        };
    </script>

</body>

</html>