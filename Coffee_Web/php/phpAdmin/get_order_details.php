<?php
session_start();
include "../connect.php";

/* ===== CHECK QUYỀN ===== */
if (($_SESSION["role"] ?? "") !== "admin") {
    echo "<p style='color:red'>Không có quyền truy cập!</p>";
    exit;
}

/* ===== LẤY ID HÓA ĐƠN ===== */
$id_invoice = (int)($_GET["id_invoice"] ?? 0);
if ($id_invoice <= 0) {
    echo "<p style='color:red'>Hóa đơn không hợp lệ!</p>";
    exit;
}

/* ===== QUERY CHI TIẾT HÓA ĐƠN ===== */
$sql = "
    SELECT ui.*, u.name AS customer_name, u.username
    FROM user_invoices ui
    LEFT JOIN users u ON ui.user_id = u.id
    WHERE ui.id = $id_invoice
";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "<p style='color:red'>Không tìm thấy hóa đơn!</p>";
    exit;
}

$invoice = $result->fetch_assoc();

/* ===== TÍNH TOÁN ===== */
$quantity    = max(1, (int)$invoice["quantity"]); // tránh chia cho 0
$unit_price  = $invoice["total_price"] / $quantity;
?>

<div style="padding:20px">

    <!-- THÔNG TIN KHÁCH HÀNG -->
    <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px">
            <div>
                <p><strong>Khách hàng:</strong>
                    <?= $invoice["customer_name"] ?>
                </p>
                <p><strong>Username:</strong>
                    @<?= $invoice["username"] ?>
                </p>
            </div>
            <div>
                <p><strong>Ngày mua:</strong>
                    <?= date("d/m/Y H:i:s", strtotime($invoice["created_at"])) ?>
                </p>
                <p><strong>Mã hóa đơn:</strong>
                    #<?= $invoice["id"] ?>
                </p>
            </div>
        </div>
    </div>

    <!-- BẢNG SẢN PHẨM -->
    <h4 style="margin-bottom:10px">Thông tin sản phẩm</h4>

    <table style="width:100%; border-collapse:collapse; background:#fff">
        <thead>
            <tr style="background:#e9ecef">
                <th style="padding:12px; border:1px solid #dee2e6; text-align:left">
                    Sản phẩm
                </th>
                <th style="padding:12px; border:1px solid #dee2e6; text-align:center">
                    Số lượng
                </th>
                <th style="padding:12px; border:1px solid #dee2e6; text-align:right">
                    Đơn giá
                </th>
                <th style="padding:12px; border:1px solid #dee2e6; text-align:right">
                    Thành tiền
                </th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td style="padding:12px; border:1px solid #dee2e6">
                    <strong><?= htmlspecialchars($invoice["product_name"]) ?></strong>
                </td>
                <td style="padding:12px; border:1px solid #dee2e6; text-align:center">
                    <?= $invoice["quantity"] ?>
                </td>
                <td style="padding:12px; border:1px solid #dee2e6; text-align:right">
                    <?= number_format($unit_price, 0, ',', '.') ?>đ
                </td>
                <td style="padding:12px; border:1px solid #dee2e6; text-align:right">
                    <strong>
                        <?= number_format($invoice["total_price"], 0, ',', '.') ?>đ
                    </strong>
                </td>
            </tr>
        </tbody>

        <tfoot>
            <tr style="background:#f1f1f1">
                <td colspan="3"
                    style="padding:15px; border:1px solid #dee2e6; text-align:right">
                    <strong>TỔNG CỘNG:</strong>
                </td>
                <td style="padding:15px; border:1px solid #dee2e6; text-align:right">
                    <strong style="color:#dc3545; font-size:18px">
                        <?= number_format($invoice["total_price"], 0, ',', '.') ?>đ
                    </strong>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- GHI CHÚ -->
    <div style="margin-top:20px; padding:15px;
                background:#e7f3ff; border-left:4px solid #007bff;
                border-radius:4px">
        <strong>! Ghi chú:</strong>
        Hóa đơn này đã được thanh toán và hoàn tất.
    </div>

</div>