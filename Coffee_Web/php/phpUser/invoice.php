<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    header("Location: ../phpAccount/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// Lấy hóa đơn kèm ảnh sản phẩm (JOIN với bảng products theo tên)
$invoices = mysqli_query($conn, "
    SELECT 
        ui.id,
        ui.product_name,
        ui.quantity,
        ui.total_price,
        ui.created_at,
        p.image,
        p.price AS unit_price
    FROM user_invoices ui
    LEFT JOIN products p ON p.name = ui.product_name
    WHERE ui.user_id = $userId
    ORDER BY ui.created_at DESC
");

// Tổng tiền đã chi
$totalSpent = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT IFNULL(SUM(total_price),0) AS t FROM user_invoices WHERE user_id = $userId"
))["t"];

// Bộ lọc tìm kiếm
$search    = trim($_GET["search"] ?? "");
$dateFrom  = $_GET["date_from"] ?? "";
$dateTo    = $_GET["date_to"]   ?? "";

// Build query có filter
$where = "WHERE ui.user_id = $userId";
if ($search !== "")  $where .= " AND ui.product_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
if ($dateFrom !== "") $where .= " AND DATE(ui.created_at) >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
if ($dateTo   !== "") $where .= " AND DATE(ui.created_at) <= '" . mysqli_real_escape_string($conn, $dateTo)   . "'";

$invoices = mysqli_query($conn, "
    SELECT ui.id, ui.product_name, ui.quantity, ui.total_price, ui.created_at,
           p.image, p.price AS unit_price
    FROM user_invoices ui
    LEFT JOIN products p ON p.name = ui.product_name
    $where
    ORDER BY ui.created_at DESC
");

$totalRows = mysqli_num_rows($invoices);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn của tôi</title>
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/home.css?v=<?php echo time(); ?>">
    <style>
    /* ── SEARCH BAR ── */
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
        align-items: center;
    }
    .filter-bar input[type="text"],
    .filter-bar input[type="date"] {
        padding: 8px 12px;
        border: 1px solid #d0b9b3;
        border-radius: 5px;
        font-size: 13px;
        outline: none;
        font-family: inherit;
        color: #4b2e2a;
        background: #fff;
    }
    .filter-bar input:focus { border-color: #4b2e2a; }
    .filter-bar button {
        padding: 8px 18px;
        background: #4b2e2a;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        font-family: inherit;
    }
    .filter-bar button:hover { background: #6d4c41; }
    .filter-bar a.btn-reset {
        padding: 8px 14px;
        border: 1px solid #d0b9b3;
        border-radius: 5px;
        color: #888;
        font-size: 13px;
        text-decoration: none;
    }
    .filter-bar a.btn-reset:hover { border-color: #4b2e2a; color: #4b2e2a; }

    /* ── STAT STRIP ── */
    .stat-strip {
        display: flex;
        gap: 14px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .stat-box {
        flex: 1;
        min-width: 140px;
        background: white;
        border-left: 4px solid #4b2e2a;
        border-radius: 7px;
        padding: 14px 18px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .stat-box .s-val {
        font-size: 1.35rem;
        font-weight: 700;
        color: #4b2e2a;
        line-height: 1.1;
    }
    .stat-box .s-lbl {
        font-size: .72rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-top: 3px;
    }

    /* ── TABLE ── */
    .welcome table {
        width: 100%;
        margin-top: 0;
        background: #fafafa;
        border-collapse: collapse;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .welcome th {
        background: #f0e6e0;
        color: #4b2e2a;
        padding: 12px 14px;
        font-size: 13px;
        text-align: center;
        font-weight: 700;
        letter-spacing: .03em;
    }
    .welcome td {
        padding: 12px 14px;
        font-size: 13.5px;
        border-bottom: 1px solid #f0e8e5;
        text-align: center;
        vertical-align: middle;
    }
    .welcome tr:last-child td { border-bottom: none; }
    .welcome tr:hover td { background: #fdf5f2; }

    /* ── PRODUCT CELL ── */
    .prod-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }
    .prod-thumb {
        width: 52px;
        height: 52px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #e8d8d3;
        flex-shrink: 0;
        background: #f5ece9;
    }
    .prod-thumb-placeholder {
        width: 52px;
        height: 52px;
        border-radius: 8px;
        background: #f0e6e0;
        border: 1px solid #e8d8d3;
        display: grid;
        place-items: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .prod-name-txt {
        font-weight: 600;
        color: #2d1a17;
        font-size: 13.5px;
    }
    .prod-unit {
        font-size: 12px;
        color: #aaa;
        margin-top: 2px;
    }

    /* ── BADGES ── */
    .badge-date {
        display: inline-block;
        background: #f0e6e0;
        color: #6f4e37;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    .badge-qty {
        display: inline-block;
        background: #4b2e2a;
        color: white;
        width: 28px; height: 28px;
        border-radius: 50%;
        line-height: 28px;
        font-size: 13px;
        font-weight: 700;
    }
    .price-txt {
        font-weight: 700;
        color: #c0392b;
        font-size: 14px;
    }

    /* ── EMPTY STATE ── */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #bbb;
    }
    .empty-state .e-icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-state p { font-size: 15px; }

    /* ── SUCCESS TOAST ── */
    .toast-success {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
        color: #2e7d32;
        padding: 10px 16px;
        border-radius: 5px;
        font-size: 13.5px;
        margin-bottom: 16px;
    }

    /* ── PRINT ── */
    @media print {
        .sidebar, .topbar, .filter-bar, .stat-strip { display: none !important; }
        .content { padding: 0 !important; }
    }
    </style>
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2>☕COFFEE SHOP</h2>
        <ul>
            <li><a href="userHome.php">Trang chủ</a></li>
            <li><a href="products.php">Sản phẩm</a></li>
            <li><a href="cart.php">Giỏ hàng</a></li>
            <li class="active"><a href="invoice.php">Hóa đơn</a></li>
            <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
        </ul>
    </div>

    <div class="content">

        <!-- TOPBAR -->
        <div class="topbar">
            <h1>🧾 HÓA ĐƠN CỦA TÔI</h1>
            <div class="account">
                👤 <?= htmlspecialchars($_SESSION["username"]) ?> |
                <a href="../phpAccount/logout.php">Đăng xuất</a>
            </div>
        </div>

        <div class="welcome">

            <?php if (isset($_GET["success"])): ?>
            <div class="toast-success">✔ Thanh toán thành công! Cảm ơn bạn đã ủng hộ quán.</div>
            <?php endif; ?>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-box">
                    <div class="s-val"><?= $totalRows ?></div>
                    <div class="s-lbl">Hóa đơn<?= ($search||$dateFrom||$dateTo)?' (đã lọc)':'' ?></div>
                </div>
                <div class="stat-box" style="border-color:#a0522d">
                    <div class="s-val" style="color:#a0522d"><?= number_format($totalSpent) ?> ₫</div>
                    <div class="s-lbl">Tổng đã chi</div>
                </div>
            </div>

            <!-- FILTER -->
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="🔍 Tìm sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="Từ ngày">
                <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="Đến ngày">
                <button type="submit">Lọc</button>
                <?php if ($search || $dateFrom || $dateTo): ?>
                    <a href="invoice.php" class="btn-reset">✕ Xóa lọc</a>
                <?php endif; ?>
            </form>

            <!-- TABLE -->
            <?php if ($totalRows == 0): ?>
            <div class="empty-state">
                <div class="e-icon">🧾</div>
                <p>Không tìm thấy hóa đơn nào.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;padding-left:16px">Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Tổng tiền</th>
                        <th>Ngày mua</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($invoices)): ?>
                <tr>
                    <!-- Ảnh + tên sản phẩm -->
                    <td style="text-align:left">
                        <div class="prod-cell">
                            <?php
                            $imgPath = "/quanlyquancaphe_web/Coffee_Web/uploads/" . ($row["image"] ?? "");
                            if (!empty($row["image"])):
                            ?>
                                <img src="<?= htmlspecialchars($imgPath) ?>"
                                     alt="<?= htmlspecialchars($row["product_name"]) ?>"
                                     class="prod-thumb"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                                <div class="prod-thumb-placeholder" style="display:none">☕</div>
                            <?php else: ?>
                                <div class="prod-thumb-placeholder">☕</div>
                            <?php endif; ?>
                            <div>
                                <div class="prod-name-txt"><?= htmlspecialchars($row["product_name"]) ?></div>
                                <?php if ($row["unit_price"]): ?>
                                <div class="prod-unit"><?= number_format($row["unit_price"]) ?> ₫ / ly</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Số lượng -->
                    <td><span class="badge-qty"><?= $row["quantity"] ?></span></td>

                    <!-- Đơn giá -->
                    <td style="color:#888;font-size:13px">
                        <?= $row["unit_price"] ? number_format($row["unit_price"]) . ' ₫' : '—' ?>
                    </td>

                    <!-- Tổng tiền -->
                    <td><span class="price-txt"><?= number_format($row["total_price"]) ?> ₫</span></td>

                    <!-- Ngày mua -->
                    <td>
                        <span class="badge-date">
                            <?= date("d/m/Y", strtotime($row["created_at"])) ?><br>
                            <span style="font-weight:400;font-size:11px;color:#a07060">
                                <?= date("H:i", strtotime($row["created_at"])) ?>
                            </span>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div><!-- /welcome -->
    </div><!-- /content -->
</div><!-- /layout -->
</body>
</html>