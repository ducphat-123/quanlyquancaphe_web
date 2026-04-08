<?php
session_start();
include "../connect.php"; 

// Giả lập ID người dùng đang đăng nhập (Bạn thay bằng $_SESSION['user_id'] thực tế nhé)
$user_id = $_SESSION['user_id'] ?? 13; 

$success = $error = "";

/* ===== LOGIC LƯU MÃ VOUCHER MỚI ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_save_code"])) {
    $code_input = strtoupper(trim($_POST["txt_code"]));

    if (!empty($code_input)) {
        // 1. Tìm mã trong kho tổng
        $stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_code = ? AND status = 1");
        $stmt->bind_param("s", $code_input);
        $stmt->execute();
        $voucher = $stmt->get_result()->fetch_assoc();

        if (!$voucher) {
            $error = "Mã giảm giá không tồn tại hoặc đã bị khóa!";
        } elseif (strtotime($voucher['start_date']) > time()) {
            $error = "Mã giảm giá này chưa đến thời gian sử dụng!";
        } elseif (strtotime($voucher['end_date']) < time()) {
            $error = "Mã giảm giá này đã hết hạn!";
        } elseif ($voucher['used_count'] >= $voucher['usage_limit']) {
            $error = "Mã giảm giá này đã hết lượt lưu!";
        } else {
            // 2. Kiểm tra xem user này đã lưu mã này chưa
            $v_id = $voucher['id'] ?? $voucher['voucher_id']; // Dùng tên cột id thực tế của bạn
            $check_stmt = $conn->prepare("SELECT id FROM user_vouchers WHERE user_id = ? AND voucher_id = ?");
            $check_stmt->bind_param("ii", $user_id, $v_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Bạn đã lưu mã giảm giá này trong ví rồi!";
            } else {
                // 3. Lưu vào ví
                $insert_stmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)");
                $insert_stmt->bind_param("ii", $user_id, $v_id);
                if ($insert_stmt->execute()) {
                    $update_stmt = $conn->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE voucher_id = ?");
                    $update_stmt->bind_param("i", $v_id);
                    $update_stmt->execute();

                    $success = "Đã lưu mã giảm giá thành công vào ví!";
                }
            }
        }
    }
}

/* ===== LOGIC LẤY DANH SÁCH VOUCHER TRONG VÍ ===== */
// Kết nối bảng user_vouchers với bảng vouchers để lấy thông tin chi tiết
$sql_get_vouchers = "
    SELECT uv.is_used, v.* FROM user_vouchers uv
    JOIN vouchers v ON uv.voucher_id = v.voucher_id 
    WHERE uv.user_id = $user_id
    ORDER BY uv.is_used ASC, v.end_date ASC
";
$result_vouchers = $conn->query($sql_get_vouchers);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Ví Voucher Của Tôi</title>
    <link rel="stylesheet" href="../../css/home.css"> 
    <link rel="stylesheet" href="../../css/categories.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <h2>☕ COFFEE SHOP</h2>
            <ul>

                <li><a href="userHome.php">Trang chủ</a></li>
                <li><a href="products.php">Sản phẩm</a></li>
                <li><a href="cart.php">Giỏ hàng</a></li>
                <li><a href="kho_voucher.php"> Săn voucher</a></li>
                <li><a href="my_vouchers.php" class="active">Ví voucher của tôi</a></li>
                <li><a href="invoice.php">Hóa đơn</a></li>
                <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
            </ul>
        </aside>

        <main class="content">
            <header class="topbar">
                <h1>VÍ VOUCHER CỦA TÔI</h1>
                <div class="account">
                    👤
                    <b><?= htmlspecialchars($_SESSION["name"]) ?></b> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </header>

            <section class="welcome">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <div class="filter-section" style="background: #e8f5e9; border: 1px solid #c8e6c9;">
                    <form method="POST">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="color: #2e7d32;">Thêm mã giảm giá mới vào ví</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="text" name="txt_code" placeholder="Nhập mã khuyến mãi" required style="width: 300px; text-transform: uppercase;">
                                <button type="submit" name="btn_save_code" class="btn btn-success">Lưu mã</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-wrap">
                    <h3>Kho Voucher (<?= $result_vouchers->num_rows ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã giảm giá</th>
                                <th>Điều kiện áp dụng</th>
                                <th>Mức giảm</th>
                                <th>Hạn sử dụng</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_vouchers->num_rows > 0): ?>
                                <?php while ($row = $result_vouchers->fetch_assoc()): ?>
                                    <?php 
                                        // Kiểm tra trạng thái mã
                                        $is_expired = strtotime($row['end_date']) < time();
                                        $status_text = "";
                                        $btn_class = "";
                                        
                                        if ($row['is_used'] == 1) {
                                            $status_text = "Đã sử dụng";
                                            $btn_class = "btn-delete"; // Dùng màu đỏ/xám
                                        } elseif ($is_expired) {
                                            $status_text = "Đã hết hạn";
                                            $btn_class = "btn-delete";
                                        } else {
                                            $status_text = "Dùng ngay";
                                            $btn_class = "btn-success"; // Dùng màu xanh
                                        }
                                    ?>
                                    <tr style="<?= ($row['is_used'] == 1 || $is_expired) ? 'opacity: 0.6; background: #f9f9f9;' : '' ?>">
                                        <td class="center"><strong><?= htmlspecialchars($row["voucher_code"]) ?></strong></td>
                                        <td class="left">Đơn tối thiểu <?= number_format($row["min_order_value"]) ?>đ</td>
                                        <td class="center" style="color: #e74c3c; font-weight: bold;">
                                            <?= $row["discount_type"] == 'fixed' ? '-' . number_format($row["discount_value"]) . 'đ' : '-' . $row["discount_value"] . '%' ?>
                                        </td>
                                        <td class="center">
                                            <?= date('d/m/Y H:i', strtotime($row["end_date"])) ?>
                                        </td>
                                        <td class="center">
                                            <?php if ($row['is_used'] == 0 && !$is_expired): ?>
                                                <a href="products.php" class="btn <?= $btn_class ?>"><?= $status_text ?></a>
                                            <?php else: ?>
                                                <button class="btn <?= $btn_class ?>" disabled><?= $status_text ?></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="center">Ví của bạn hiện đang trống. Hãy lưu thêm mã nhé!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </main>
    </div>
</body>
</html>