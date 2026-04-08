<?php
session_start();
include "../connect.php";

// Kiểm tra user đăng nhập
if (!isset($_SESSION['user_id'])) {
    $user_id = 13; // Fallback hoặc có thể chuyển hướng về login
} else {
    $user_id = $_SESSION['user_id'];
}

// Lấy danh sách voucher (Đang diễn ra & Sắp diễn ra)
$sql = "SELECT * FROM vouchers WHERE end_date >= NOW() AND status = 1 ORDER BY start_date ASC";
$result = $conn->query($sql);

$vouchers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
}

// Lấy danh sách mã người dùng đã lưu
$saved_vouchers = [];
$check_sql = "SELECT voucher_id FROM user_vouchers WHERE user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $saved_vouchers[] = $row['voucher_id'];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kho Voucher - Săn Ưu Đãi</title>
    <link rel="stylesheet" href="../../css/home.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/kho_voucher.css">
</head>

<body style="font-family: 'Inter', sans-serif; background-color: #f8f9fa;">
    <div class="layout">
        <aside class="sidebar">
            <h2>☕ COFFEE SHOP</h2>
            <ul>
                <li><a href="userHome.php">Trang chủ</a></li>
                <li><a href="products.php">Sản phẩm</a></li>
                <li><a href="cart.php">Giỏ hàng</a></li>
                <li><a href="kho_voucher.php" class="active">Săn Voucher</a></li>
                <li><a href="my_vouchers.php">Ví voucher của tôi</a></li>
                <li><a href="invoice.php">Hóa đơn</a></li>
                <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
            </ul>
        </aside>

        <main class="content">
            <header class="topbar"
                style="background: #fff; padding: 15px 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                <h1 style="margin:0; font-size: 24px;">KHO VOUCHER</h1>
                <div class="account">
                    <b><?= htmlspecialchars($_SESSION["name"]) ?></b> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>                </div>
            </header>

            <div class="promo-banner">
                <h2>Cơn Mưa Ưu Đãi </h2>
                <p>Nhanh tay thu thập các mã giảm giá siêu hot trước khi hết lượt!</p>
            </div>

            <div class="voucher-container">
                <?php if (count($vouchers) > 0): ?>
                    <?php foreach ($vouchers as $v):
                      
                        $v_id = isset($v['voucher_id']) ? $v['voucher_id'] : $v['id'];

                        $is_saved = in_array($v_id, $saved_vouchers);
                        $is_upcoming = strtotime($v['start_date']) > time();
                        $is_depleted = $v['used_count'] >= $v['usage_limit'];

                        $percent_used = ($v['usage_limit'] > 0) ? round(($v['used_count'] / $v['usage_limit']) * 100) : 0;
                        if ($percent_used > 100)
                            $percent_used = 100;

                        $card_class = "";
                        if ($is_depleted)
                            $card_class .= " depleted";
                        if ($is_upcoming)
                            $card_class .= " upcoming";

                        // Format discount
                        $discount_display = "";
                        if ($v['discount_type'] == 'fixed') {
                            if ($v['discount_value'] >= 1000) {
                                $discount_display = ($v['discount_value'] / 1000) . "K";
                            } else {
                                $discount_display = number_format($v['discount_value']) . "đ";
                            }
                        } else {
                            $discount_display = $v['discount_value'] . "%";
                        }
                        ?>
                        <div class="voucher-card <?= $card_class ?>" data-voucher-id="<?= $v_id ?>"
                            data-start-time="<?= strtotime($v['start_date']) * 1000 ?>">

                            <div class="voucher-left">
                                <span class="discount-val">GIẢM<br><?= $discount_display ?></span>
                            </div>

                            <div class="voucher-right">
                                <div>
                                    <div class="voucher-code">Mã: <?= htmlspecialchars($v['voucher_code']) ?></div>
                                    <div class="min-order">Đơn tối thiểu <?= number_format($v['min_order_value']) ?>đ</div>
                                    <div class="voucher-dates">
                                        HSD: <?= date('d/m/Y', strtotime($v['start_date'])) ?> -
                                        <?= date('d/m/Y', strtotime($v['end_date'])) ?>
                                    </div>

                                    <?php if (!$is_upcoming): ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $percent_used ?>%"></div>
                                        </div>
                                        <span class="progress-text">Đã dùng <?= $percent_used ?>%</span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <?php if ($is_saved): ?>
                                        <button class="btn-action btn-saved" disabled>Đã có trong ví</button>
                                    <?php elseif ($is_depleted): ?>
                                        <button class="btn-action btn-saved" disabled>Đã hết lượt</button>
                                    <?php elseif ($is_upcoming): ?>
                                        <button class="btn-action btn-upcoming btn-save-action" disabled>
                                            Chưa tới giờ
                                        </button>
                                        <div class="countdown countdown-timer">Đang tính toán...</div>
                                    <?php else: ?>
                                        <button class="btn-action btn-save btn-save-action" onclick="saveVoucher(<?= $v_id ?>)">
                                            Lưu Mã Ngay
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #888;">
                        <h3>Hiện chưa có mã giảm giá nào phát hành.</h3>
                        <p>Vui lòng quay lại sau nhé!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function saveVoucher(id) {
            $.ajax({
                url: 'luu_voucher.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        // Update button to "Đã có trong ví"
                        const card = document.querySelector(`.voucher-card[data-voucher-id="${id}"]`);
                        if (card) {
                            const btnBox = card.querySelector('.btn-save-action').parentElement;
                            btnBox.innerHTML = '<button class="btn-action btn-saved" disabled>Đã có trong ví</button>';
                        }
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Có lỗi xảy ra khi lưu mã ưu đãi.', 'error');
                }
            });
        }

        // Tự động cập nhật thời gian cho các voucher Sắp diễn ra
        function updateTimers() {
            const now = new Date().getTime();
            const upcomingCards = document.querySelectorAll('.voucher-card.upcoming');

            upcomingCards.forEach(card => {
                const startTime = parseInt(card.getAttribute('data-start-time'));
                const distance = startTime - now;
                const timerElem = card.querySelector('.countdown-timer');
                const btnElem = card.querySelector('.btn-save-action');

                if (distance <= 0) {
                    // Đã đến giờ, tự động cập nhật UI
                    card.classList.remove('upcoming');
                    if (timerElem) timerElem.style.display = 'none';
                    if (btnElem) {
                        btnElem.classList.remove('btn-upcoming');
                        btnElem.classList.add('btn-save');
                        btnElem.removeAttribute('disabled');
                        btnElem.innerHTML = 'Lưu Mã Ngay';
                        const vId = card.getAttribute('data-voucher-id');
                        btnElem.setAttribute('onclick', `saveVoucher(${vId})`);
                    }
                } else {
                    // Cập nhật countdown
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    let timeStr = "Mở sau: ";
                    if (days > 0) timeStr += days + "d ";
                    timeStr += hours + "h " + minutes + "m " + seconds + "s";

                    if (timerElem) timerElem.innerHTML = timeStr;
                }
            });
        }

        // Chạy timer mỗi giây
        setInterval(updateTimers, 1000);
        // Chạy ngay lúc nạp trang
        updateTimers();

        // Toast notification functions
        function showToast(message, type = 'info') {
            console.log('showToast called with:', message, type);
            const toastContainer = document.getElementById('toast-container');
            console.log('toastContainer:', toastContainer);
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;

            toastContainer.appendChild(toast);
            console.log('Toast appended');

            // Trigger animation using requestAnimationFrame
            requestAnimationFrame(() => {
                toast.classList.add('show');
                console.log('Toast shown');
            });

            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    </script>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Test Toast Button (tạm thời) -->
    <button onclick="showToast('Test success message!', 'success')" style="position: fixed; bottom: 20px; left: 20px; z-index: 1001;">Test Success Toast</button>
    <button onclick="showToast('Test error message!', 'error')" style="position: fixed; bottom: 20px; left: 150px; z-index: 1001;">Test Error Toast</button>
</body>

</html>