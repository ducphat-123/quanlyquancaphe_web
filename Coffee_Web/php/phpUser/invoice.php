<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    header("Location: ../phpAccount/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// Tổng tiền đã chi (Lấy từ bảng orders để tính luôn tiền đã áp dụng voucher)
$totalSpent = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT IFNULL(SUM(final_amount),0) AS t FROM orders WHERE user_id = $userId"
))["t"];

// Bộ lọc tìm kiếm
$search    = trim($_GET["search"] ?? "");
$dateFrom  = $_GET["date_from"] ?? "";
$dateTo    = $_GET["date_to"]   ?? "";

// Build query cho bảng orders (Gộp đơn)
$whereOrders = "WHERE o.user_id = $userId";
if ($dateFrom !== "") $whereOrders .= " AND DATE(o.created_at) >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
if ($dateTo   !== "") $whereOrders .= " AND DATE(o.created_at) <= '" . mysqli_real_escape_string($conn, $dateTo)   . "'";

// Nếu có tìm kiếm tên sản phẩm -> Lọc ra các đơn hàng (order_id) có chứa sản phẩm đó
if ($search !== "") {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $whereOrders .= " AND o.id IN (SELECT order_id FROM user_invoices WHERE user_id = $userId AND product_name LIKE '%$safeSearch%')";
}

// Truy vấn danh sách Đơn hàng
$ordersQuery = mysqli_query($conn, "
    SELECT o.id, o.total_amount, o.discount_amount, o.final_amount, o.created_at 
    FROM orders o 
    $whereOrders 
    ORDER BY o.created_at DESC
");

$totalOrders = mysqli_num_rows($ordersQuery);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn của tôi</title>
    <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
    <style>
    /* ── SEARCH BAR ── */
    .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; align-items: center; }
    .filter-bar input[type="text"], .filter-bar input[type="date"] {
        padding: 8px 12px; border: 1px solid #d0b9b3; border-radius: 5px; font-size: 13px; outline: none; color: #4b2e2a; background: #fff;
    }
    .filter-bar input:focus { border-color: #4b2e2a; }
    .filter-bar button { padding: 8px 18px; background: #4b2e2a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
    .filter-bar button:hover { background: #6d4c41; }
    .filter-bar a.btn-reset { padding: 8px 14px; border: 1px solid #d0b9b3; border-radius: 5px; color: #888; font-size: 13px; text-decoration: none; }
    .filter-bar a.btn-reset:hover { border-color: #4b2e2a; color: #4b2e2a; }

    /* ── STAT STRIP ── */
    .stat-strip { display: flex; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
    .stat-box { flex: 1; min-width: 140px; background: white; border-left: 4px solid #4b2e2a; border-radius: 7px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .stat-box .s-val { font-size: 1.35rem; font-weight: 700; color: #4b2e2a; line-height: 1.1; }
    .stat-box .s-lbl { font-size: .72rem; color: #999; text-transform: uppercase; letter-spacing: .04em; margin-top: 3px; }

    /* ── ORDER BOX (Kết hợp Code 1 & Code 2) ── */
    .order-box { background: #fff; border: 1px solid #e8d8d3; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.04); overflow: hidden; }
    .order-header { background: #fdf5f2; padding: 12px 18px; border-bottom: 1px solid #e8d8d3; display: flex; justify-content: space-between; align-items: center; }
    .order-header .o-id { font-weight: 700; color: #4b2e2a; font-size: 15px; }
    .order-footer { padding: 15px 18px; background: #fffaf0; border-top: 1px dashed #e8d8d3; text-align: right; }
    
    /* ── TABLE INSIDE ORDER ── */
    .order-table { width: 100%; border-collapse: collapse; }
    .order-table th { background: #fff; color: #888; padding: 10px 18px; font-size: 12px; text-transform: uppercase; text-align: center; border-bottom: 1px solid #f0e8e5; font-weight: 600; }
    .order-table td { padding: 12px 18px; border-bottom: 1px solid #f0e8e5; vertical-align: middle; }
    .order-table tr:last-child td { border-bottom: none; }
    .order-table tr:hover td { background: #fafafa; }

    /* ── PRODUCT CELL (Từ Code 2) ── */
    .prod-cell { display: flex; align-items: center; gap: 12px; text-align: left; }
    .prod-thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid #e8d8d3; flex-shrink: 0; background: #f5ece9; }
    .prod-thumb-placeholder { width: 48px; height: 48px; border-radius: 8px; background: #f0e6e0; border: 1px solid #e8d8d3; display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
    .prod-name-txt { font-weight: 600; color: #2d1a17; font-size: 13.5px; }
    .prod-unit { font-size: 12px; color: #aaa; margin-top: 2px; }

    /* ── BADGES ── */
    .badge-date { display: inline-block; background: #f0e6e0; color: #6f4e37; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; margin-right: 5px; }
    .badge-qty { display: inline-block; background: #4b2e2a; color: white; width: 26px; height: 26px; border-radius: 50%; line-height: 26px; font-size: 13px; font-weight: 700; text-align: center; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align: center; padding: 50px 20px; color: #bbb; background: #fff; border-radius: 8px; border: 1px dashed #e8d8d3; }
    .empty-state .e-icon { font-size: 3rem; margin-bottom: 12px; }

    /* ── SUCCESS TOAST ── */
    .toast-success { background: #e8f5e9; border-left: 4px solid #4caf50; color: #2e7d32; padding: 10px 16px; border-radius: 5px; font-size: 13.5px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="layout">

    <div class="sidebar">
        <h2>☕COFFEE SHOP</h2>
        <ul>
            <li><a href="userHome.php">Trang chủ</a></li>
            <li><a href="products.php">Sản phẩm</a></li>
            <li><a href="cart.php">Giỏ hàng</a></li>
            <li><a href="kho_voucher.php"> Săn voucher</a></li>
            <li><a href="my_vouchers.php"> Ví voucher của tôi</a></li>
            
            <li class="active"><a href="invoice.php">Hóa đơn</a></li>
            <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
        </ul>
    </div>

    <div class="content">

        <div class="topbar">
            <h1>🧾 HÓA ĐƠN CỦA TÔI</h1>
            <div class="account">
                👤 <?= htmlspecialchars($_SESSION["name"] ?? 'Khách') ?> |
                <a href="../phpAccount/logout.php">Đăng xuất</a>
            </div>
        </div>

        <div class="welcome">

            <?php if (isset($_GET["success"])): ?>
            <div class="toast-success">✔ Thanh toán thành công! Cảm ơn bạn đã ủng hộ quán.</div>
            <?php endif; ?>

            <div class="stat-strip">
                <div class="stat-box">
                    <div class="s-val"><?= $totalOrders ?></div>
                    <div class="s-lbl">Đơn hàng<?= ($search||$dateFrom||$dateTo)?' (đã lọc)':'' ?></div>
                </div>
                <div class="stat-box" style="border-color:#a0522d">
                    <div class="s-val" style="color:#a0522d"><?= number_format($totalSpent) ?> ₫</div>
                    <div class="s-lbl">Tổng tiền đã chi</div>
                </div>
            </div>

            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="🔍 Tìm theo sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="Từ ngày">
                <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="Đến ngày">
                <button type="submit">Lọc dữ liệu</button>
                <?php if ($search || $dateFrom || $dateTo): ?>
                    <a href="invoice.php" class="btn-reset">✕ Xóa lọc</a>
                <?php endif; ?>
            </form>

            <?php if ($totalOrders == 0): ?>
            <div class="empty-state">
                <div class="e-icon">🧾</div>
                <p>Không tìm thấy lịch sử mua hàng nào.</p>
            </div>
            <?php else: ?>

                <?php while ($order = mysqli_fetch_assoc($ordersQuery)): 
                    $orderId = $order['id'];
                    // Lấy chi tiết món hàng của Order này (Kèm ảnh từ bảng products)
                    $itemsQuery = mysqli_query($conn, "
                        SELECT ui.product_name, ui.quantity, ui.total_price, 
                               p.image, p.price AS unit_price
                        FROM user_invoices ui
                        LEFT JOIN products p ON p.name = ui.product_name
                        WHERE ui.order_id = $orderId
                    ");
                ?>
                <div class="order-box">
                    <div class="order-header">
                        <span class="o-id">🛒 Đơn hàng: #<?= str_pad($orderId, 5, '0', STR_PAD_LEFT) ?></span>
                        <span style="color: #666; font-size: 13px;">
                            <span class="badge-date"><?= date('d/m/Y', strtotime($order['created_at'])) ?></span>
                            <?= date('H:i', strtotime($order['created_at'])) ?>
                        </span>
                    </div>

                    <table class="order-table">
                        <thead>
                            <tr>
                                <th style="text-align:left;">Sản phẩm</th>
                                <th style="width: 15%;">Số lượng</th>
                                <th style="width: 25%; text-align:right;">Tính tiền lẻ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($itemsQuery)): 
                                $imgPath = "../../uploads/products/" . ($item["image"] ?? ""); // Đổi link upload ảnh nếu bạn lưu ở đường dẫn khác
                            ?>
                            <tr>
                                <td style="text-align:left;">
                                    <div class="prod-cell">
                                        <?php if (!empty($item["image"])): ?>
                                            <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($item["product_name"]) ?>" class="prod-thumb" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                                            <div class="prod-thumb-placeholder" style="display:none">☕</div>
                                        <?php else: ?>
                                            <div class="prod-thumb-placeholder">☕</div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="prod-name-txt"><?= htmlspecialchars($item["product_name"]) ?></div>
                                            <?php if ($item["unit_price"]): ?>
                                                <div class="prod-unit"><?= number_format($item["unit_price"]) ?> ₫ / ly</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-qty"><?= $item["quantity"] ?></span>
                                </td>
                                <td style="text-align: right; font-weight: 500; color: #555;">
                                    <?= number_format($item["total_price"]) ?> ₫
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div class="order-footer">
                        <p style="margin: 0 0 5px 0; color: #555; font-size: 13.5px;">Tạm tính chung: <strong style="width: 120px; display: inline-block;"><?= number_format($order['total_amount']) ?> ₫</strong></p>
                        
                        <?php if ($order['discount_amount'] > 0): ?>
                            <p style="margin: 0 0 5px 0; color: #e74c3c; font-size: 13.5px;">🎫 Đã giảm giá: <strong style="width: 120px; display: inline-block;">-<?= number_format($order['discount_amount']) ?> ₫</strong></p>
                        <?php endif; ?>
                        
                        <p style="margin: 5px 0 0 0; font-size: 1.25em; color: #27ae60;">
                            <strong>Thực thu: <span style="width: 120px; display: inline-block;"><?= number_format($order['final_amount']) ?> ₫</span></strong>
                        </p>
                    </div>
                </div>
                <?php endwhile; ?>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>