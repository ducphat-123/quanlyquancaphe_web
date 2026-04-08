<?php
session_start();
include "../connect.php";

// Kiểm tra quyền admin
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    echo "Không có quyền truy cập.";
    exit;
}

if (isset($_GET['id_invoice'])) {
    $id_invoice = (int)$_GET['id_invoice'];

    /* ===== QUERY MỚI: TÌM TẤT CẢ CÁC MÓN THUỘC VỀ ORDER NÀY ===== */
    $sql = "
        SELECT ui.*, u.name AS customer_name, u.username
        FROM user_invoices ui
        LEFT JOIN users u ON ui.user_id = u.id
        WHERE ui.order_id = $id_invoice
    ";
    
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        // Bắt đầu vẽ bảng HTML trả về cho Popup
        echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        echo '<thead style="background: #f8f9fa;">
                <tr>
                    <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: left;">Sản phẩm</th>
                    <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: center;">Số lượng</th>
                    <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: right;">Đơn giá</th>
                    <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: right;">Thành tiền</th>
                </tr>
              </thead>';
        echo '<tbody>';
        
        $sum_items = 0; // Biến tính tổng tiền nháp

        while ($row = $result->fetch_assoc()) {
            // Do bảng của bạn đang gộp thành total_price, mình tính ngược lại đơn giá 1 ly
            $unit_price = $row['total_price'] / $row['quantity']; 
            $sum_items += $row['total_price'];

            echo '<tr>';
            echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($row['product_name']) . '</td>';
            echo '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">' . $row['quantity'] . '</td>';
            echo '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">' . number_format($unit_price, 0, ',', '.') . 'đ</td>';
            echo '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">' . number_format($row['total_price'], 0, ',', '.') . 'đ</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';

        // Hiển thị thêm dòng tổng cộng tạm tính dưới cùng cho rõ ràng
        echo '<div style="text-align: right; margin-top: 15px; font-size: 16px;">';
        echo 'Tổng cộng: <strong style="color: #e74c3c;">' . number_format($sum_items, 0, ',', '.') . 'đ</strong>';
        echo '</div>';

    } else {
        echo '<div style="padding: 20px; text-align: center; color: #777;">Không tìm thấy chi tiết món nào cho hóa đơn này! (Hoặc hóa đơn bị lỗi dữ liệu)</div>';
    }
} else {
    echo "Thiếu mã hóa đơn.";
}
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