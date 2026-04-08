<?php
session_start();
include "../connect.php";

/* CHỈ USER */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    header("Location: ../phpAccount/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// Load giỏ hàng từ DB
$cart = [];
$stmtCart = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
$stmtCart->bind_param("i", $userId);
$stmtCart->execute();
$resultCart = $stmtCart->get_result();
while ($row = $resultCart->fetch_assoc()) {
    $cart[(int)$row["product_id"]] = (int)$row["quantity"];
}

if (empty($_SESSION["cart"]) && !empty($cart)) {
    $_SESSION["cart"] = $cart;
}

$stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userInfo = $result->fetch_assoc();
$userAddress = $userInfo["address"] ?? "Chưa cập nhật";

/* AJAX CHECKOUT */
if (isset($_POST["ajax_checkout"])) {
    header("Content-Type: application/json");

    if (empty($cart)) { echo json_encode(["status"=>"error","msg"=>"Giỏ hàng trống"]); exit; }

    $selectedIds = $_POST["selected"] ?? [];
    if (!is_array($selectedIds) || count($selectedIds) === 0) {
        echo json_encode(["status"=>"error","msg"=>"Vui lòng chọn ít nhất một sản phẩm để thanh toán"]);
        exit;
    }

    $selectedIds = array_filter(array_map('intval', $selectedIds), fn($id) => isset($cart[$id]));
    if (empty($selectedIds)) { echo json_encode(["status"=>"error","msg"=>"Sản phẩm chọn không hợp lệ"]); exit; }

    mysqli_begin_transaction($conn);
    try {
        $grandTotal = 0;
        $items_to_insert = []; // Mảng tạm để lưu dữ liệu insert chi tiết sau

        // BƯỚC 1: Quét các sản phẩm được chọn, khóa dòng, check tồn kho và TÍNH TỔNG TIỀN
        foreach ($selectedIds as $productId) {
            $qty = (int)$cart[$productId];
            $rs  = mysqli_query($conn, "SELECT name, price, quantity FROM products WHERE id = $productId FOR UPDATE");
            $p   = mysqli_fetch_assoc($rs);
            
            if (!$p || $qty > $p["quantity"]) {
                throw new Exception(($p["name"] ?? "Sản phẩm") . " không đủ tồn kho");
            }

            $totalPrice = $p["price"] * $qty;
            $grandTotal += $totalPrice; // Tính tổng tiền chuẩn xác tại Backend

            // Lưu tạm vào mảng để dành cho bước 4
            $items_to_insert[] = [
                "id"         => $productId,
                "name"       => $p["name"],
                "qty"        => $qty,
                "totalPrice" => $totalPrice
            ];
        }

        // BƯỚC 2: XỬ LÝ VOUCHER (Bây giờ $grandTotal đã có giá trị)
        $discountAmount = 0;
        $finalAmount = $grandTotal;
        $voucher_id = isset($_POST['voucher_id']) && !empty($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : "NULL";

        if ($voucher_id !== "NULL") {
            $v_sql = "SELECT v.*, uv.id as uv_id FROM user_vouchers uv 
                      JOIN vouchers v ON uv.voucher_id = v.voucher_id 
                      WHERE uv.user_id = $userId AND uv.voucher_id = $voucher_id AND uv.is_used = 0";
            $v_rs = mysqli_query($conn, $v_sql);
            if ($v_rs && $v_rs->num_rows > 0) {
                $v = mysqli_fetch_assoc($v_rs);
                if (strtotime($v['end_date']) >= time() && strtotime($v['start_date']) <= time() && $grandTotal >= $v['min_order_value']) {
                    if ($v['discount_type'] === 'fixed') {
                        $discountAmount = $v['discount_value'];
                    } else {
                        $discountAmount = ($grandTotal * $v['discount_value']) / 100;
                    }
                    if ($discountAmount > $grandTotal) $discountAmount = $grandTotal;
                    $finalAmount = $grandTotal - $discountAmount;
                    
                    // Đánh dấu đã dùng Voucher
                    mysqli_query($conn, "UPDATE user_vouchers SET is_used = 1 WHERE id = {$v['uv_id']}");
                } else {
                    $voucher_id = "NULL"; 
                }
            } else {
                $voucher_id = "NULL";
            }
        }

        // BƯỚC 3: LƯU BẢNG ORDERS CHÍNH
        mysqli_query($conn, "
            INSERT INTO orders (user_id, total_amount, voucher_id, discount_amount, final_amount, created_at)
            VALUES ($userId, $grandTotal, $voucher_id, $discountAmount, $finalAmount, NOW())
        ");
        $order_id = mysqli_insert_id($conn);

        // BƯỚC 4: LƯU HÓA ĐƠN CHI TIẾT (user_invoices) VÀ TRỪ KHO, XÓA GIỎ HÀNG
        foreach ($items_to_insert as $item) {
            // Lưu chi tiết
            mysqli_query($conn, "
                INSERT INTO user_invoices (order_id, user_id, product_name, quantity, total_price, created_at)
                VALUES ($order_id, $userId, '" . mysqli_real_escape_string($conn, $item["name"]) . "', {$item['qty']}, {$item['totalPrice']}, NOW())
            ");

            // Trừ kho
            mysqli_query($conn, "UPDATE products SET quantity = quantity - {$item['qty']} WHERE id = {$item['id']}");

            // Xóa giỏ hàng
            $stmtDel = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmtDel->bind_param("ii", $userId, $item['id']);
            $stmtDel->execute();
            unset($_SESSION["cart"][$item['id']]);
        }

        mysqli_commit($conn);
        echo json_encode(["status"=>"success"]);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(["status"=>"error","msg"=>$e->getMessage()]);
        exit;
    }
}
// Lấy danh sách Voucher người dùng để đổ vào Select
$v_dropdown_sql = "SELECT v.* FROM user_vouchers uv 
                    JOIN vouchers v ON uv.voucher_id = v.voucher_id 
                    WHERE uv.user_id = $userId AND uv.is_used = 0 AND v.end_date >= NOW() AND v.start_date <= NOW()";
$vouchers_rs = mysqli_query($conn, $v_dropdown_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng</title>
    <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/cart.css?v=<?php echo time(); ?>">
   <link rel="stylesheet" href="../../css/products.css?v=<?php echo time(); ?>">
    
    <style>
    /* Nút xóa */
    .btn-remove {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 18px;
        color: #c0392b;
        padding: 4px 8px;
        border-radius: 5px;
        transition: background .15s, transform .15s;
        line-height: 1;
    }
    .btn-remove:hover {
        background: #fdecea;
        transform: scale(1.15);
    }

    /* Hiệu ứng xóa dòng */
    .cart-row {
        transition: opacity .35s, transform .35s;
    }
    .cart-row.removing {
        opacity: 0;
        transform: translateX(30px);
    }

    /* Toast thông báo */
    #toast {
        position: fixed;
        bottom: 28px;
        right: 28px;
        background: #4b2e2a;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 13.5px;
        box-shadow: 0 4px 16px rgba(0,0,0,.2);
        opacity: 0;
        transform: translateY(10px);
        transition: opacity .3s, transform .3s;
        z-index: 9999;
        pointer-events: none;
    }
    #toast.show {
        opacity: 1;
        transform: translateY(0);
    }
    </style>
</head>
<body>

<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2>☕ COFFEE SHOP</h2>
        <ul>
            <li><a href="userHome.php">Trang chủ</a></li>
                <li><a href="products.php">Sản phẩm</a></li>
                <li class="active"><a href="cart.php">Giỏ hàng</a></li>
                <li><a href="kho_voucher.php"> Săn voucher</a></li>
                <li><a href="my_vouchers.php"> Ví voucher của tôi</a></li>
                
                <li><a href="invoice.php">Hóa đơn</a></li>
                <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
        </ul>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="topbar">
            <h1>GIỎ HÀNG</h1>
            <div class="account">
                👤 <b><?= htmlspecialchars($_SESSION["name"]) ?></b>
            </div>
        </div>

        <div class="welcome">

            <?php if (empty($cart)): ?>
                <p>🛒 Giỏ hàng trống</p>
            <?php else: ?>

                <table border="0" width="100%" cellpadding="10" class="cart-table">
                    <tr>
                        <th><input type="checkbox" id="selectAll" checked></th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Giá</th>
                        <th>Tổng</th>
                        <th>Xóa</th>
                    </tr>

                    <?php
                    $grandTotal = 0;
                    $jsProducts = [];

                    foreach ($cart as $id => $qty):
                        $rs = mysqli_query($conn, "SELECT name, price, image FROM products WHERE id = $id");
                        $p  = mysqli_fetch_assoc($rs);
                        if(!$p) continue; // Bỏ qua nếu không tìm thấy sp
                        $sum = $p["price"] * $qty;
                        $grandTotal += $sum;

                        $jsProducts[] = [
                            "id"     => $id,
                            "name"   => $p["name"],
                            "qty"    => $qty,
                            "price"  => number_format($p["price"]),
                            "sum"    => number_format($sum),
                            "sumRaw" => $sum
                        ];

                        $imagePath = $p["image"]
                            ? "../../uploads/products/" . $p["image"]
                            : "../../images/default-product.png";
                    ?>
                    <tr class="cart-row" id="row-<?= $id ?>" data-id="<?= $id ?>" data-price="<?= $p['price'] ?>" data-qty="<?= $qty ?>">
                        <td><input type="checkbox" class="item-select" value="<?= $id ?>" checked></td>
                        <td>
                            <div class="cart-item">
                                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($p["name"]) ?>" style="width: 50px;">
                                <div class="cart-item-info">
                                    <strong><?= htmlspecialchars($p["name"]) ?></strong>
                                    <p>SKU: #<?= $id ?></p>
                                </div>
                            </div>
                        </td>
                        <td><?= $qty ?></td>
                        <td><?= number_format($p["price"]) ?> VNĐ</td>
                        <td class="item-sum"><?= number_format($sum) ?> VNĐ</td>

                        <!-- ── NÚT XÓA ── -->
                        <td>
                            <button class="btn-remove"
                                    onclick="removeCartItem(<?= $id ?>, <?= $p['price'] * $qty ?>, this)"
                                    title="Xóa khỏi giỏ hàng">
                                🗑️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="cart-total-row">
                        <td colspan="4"><b>TỔNG TIỀN ĐÃ CHỌN</b></td>
                        <td colspan="2"><b id="grandTotal"><?= number_format($grandTotal) ?> VNĐ</b></td>
                    </tr>
                </table>

                                <div class="checkout-wrapper" style="margin-top: 20px; text-align: right;">
                    <div style="margin: 15px 0; background: #f9f9f9; padding: 10px; border-radius: 5px; display: inline-block; text-align: left;">
                        <label><b>🎫 Áp dụng Voucher: </b></label>
                        <select id="voucherSelect" onchange="applyVoucher()" style="padding: 5px; width: 300px;">
                            <option value="" data-type="" data-val="0" data-min="0">-- Không dùng voucher --</option>
                            <?php if($vouchers_rs): while ($v = mysqli_fetch_assoc($vouchers_rs)) { ?>
                                <option value="<?= $v['voucher_id'] ?>" 
                                        data-type="<?= $v['discount_type'] ?>" 
                                        data-val="<?= $v['discount_value'] ?>" 
                                        data-min="<?= $v['min_order_value'] ?>">
                                    <?= htmlspecialchars($v['voucher_code']) ?> 
                                    (Giảm <?= $v['discount_type'] == 'fixed' ? number_format($v['discount_value']).'đ' : $v['discount_value'].'%' ?>, Đơn tối thiểu <?= number_format($v['min_order_value']) ?>đ)
                                </option>
                            <?php } endif; ?>
                        </select>
                        <span id="voucherStatus" style="color: #e74c3c; margin-left:10px; font-weight: bold;"></span>
                    </div>
                    <br>
                    <button type="button" class="btn-checkout" onclick="checkoutCart()" style="padding: 10px 20px; background-color: #2ecc71; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                        ✔ Thanh toán mục đã chọn
                    </button>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>




<div id="confirmModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(30, 20, 15, 0.65); align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-content" style="background-color: #fcf8f5; width: 420px; max-width: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); overflow: hidden; border: 1px solid #e8d8d3; margin: auto;">
        
        <div class="modal-header" style="text-align: center; padding-top: 25px; padding-bottom: 10px;">
         
            <div class="modal-icon" id="modalIcon" style="font-size: 40px; line-height: 1; margin-bottom: 5px;">💳</div>
            <h2 id="modalTitle" style="color: #000000; font-size: 20px; font-weight: 700; margin: 0; letter-spacing: 0.5px;">XÁC NHẬN THANH TOÁN</h2>
        </div>

        <div class="modal-body" style="padding: 15px 30px;">
            <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #c89c7c;">
                <span class="label" style="color: #886a5a; font-size: 14px; font-weight: 600;">Sản phẩm</span>
                <p id="modalProduct" style="margin: 0; color: #33221c; font-size: 14px; font-weight: 700; text-align: right; max-width: 65%; word-break: break-word;"></p>
            </div>
            
            <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #c89c7c;">
                <span class="label" style="color: #886a5a; font-size: 14px; font-weight: 600;">Tổng số lượng</span>
                <p id="modalQty" style="margin: 0; color: #33221c; font-size: 14.5px; font-weight: 700;"></p>
            </div>
            
            <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #c89c7c;">
                <span class="label" style="color: #886a5a; font-size: 14px; font-weight: 600;">Tổng tiền gốc</span>
                <p id="modalTotal" style="margin: 0; color: #4b2e2a; font-size: 15px; font-weight: 700;"></p>
            </div>
            
            <div class="info-item" id="discountRow" style="display: none; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #e74c3c;">
                <span class="label" style="color: #886a5a; font-size: 14px; font-weight: 600;">Giảm giá</span>
                <p id="displayDiscount" style="margin: 0; color: #e74c3c; font-size: 15px; font-weight: 700;"></p>
            </div>
            
            <div class="info-item" id="finalRow" style="display: none; justify-content: space-between; align-items: center; background: #e8f5e9; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #27ae60;">
                <span class="label" style="color: #2e7d32; font-size: 14px; font-weight: 600;">Thực thu</span>
                <p id="displayFinal" style="margin: 0; color: #27ae60; font-size: 16px; font-weight: 800;"></p>
            </div>
            
            <div class="info-item" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #c89c7c;">
                <span class="label" style="color: #886a5a; font-size: 14px; font-weight: 600;">Địa chỉ nhận</span>
                <p id="modalAddress" style="margin: 0; color: #33221c; font-size: 14px; font-weight: 700; text-align: right; max-width: 60%;"><?= htmlspecialchars($userAddress ?? 'Chưa cập nhật') ?></p>
            </div>
        </div>

        <div class="modal-buttons" style="display: flex; gap: 15px; padding: 15px 30px 25px 30px;">
            <button type="button" class="btn-confirm" onclick="confirmCheckoutCart()" style="flex: 1; padding: 12px 0; font-size: 15px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; background-color: #5d4037; color: #fff;">✓ Xác nhận</button>
            <button type="button" class="btn-cancel" onclick="closeModal()" style="flex: 1; padding: 12px 0; font-size: 15px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; background-color: #e8d8d3; color: #4b2e2a;">✕ Hủy</button>
        </div>
    </div>
</div>
<div id="toast"></div>



<script src="../../js/products.js?v=<?php echo time(); ?>"></script>
<script src="../../js/cart.js?v=<?php echo time(); ?>"></script>
<script>
    const products = <?= json_encode($jsProducts ?? []) ?>;
    const total    = "<?= number_format($grandTotal ?? 0) ?>";
    const checkout = new CartCheckout(products, total);
</script>
<script>
    // Các biến lưu trữ trạng thái hiện tại
    window.currentVoucherId = "";
    window.currentDiscount = 0;
    window.currentTotal = 0;

    // Hàm lấy danh sách SP đang được tick và tính tổng tiền động
    function getCheckedCartData() {
        let total = 0;
        let totalQty = 0; // Thêm biến đếm tổng số lượng
        let items = [];
        let ids = [];
        document.querySelectorAll(".cart-row").forEach(row => {
            const cb = row.querySelector(".item-select");
            if (cb && cb.checked) {
                const price = parseInt(row.dataset.price) || 0;
                const qty   = parseInt(row.dataset.qty)   || 0;
                total += price * qty;
                totalQty += qty;
                
                const nameEl = row.querySelector("strong");
                if (nameEl) items.push(nameEl.innerText + " (x" + qty + ")");
                
                ids.push(row.dataset.id);
            }
        });
        return { total, totalQty, items, ids };
    }

    /* ── TÍNH TOÁN VOUCHER ĐỘNG (Dựa trên Checkbox) ── */
    window.applyVoucher = function() {
        const select = document.getElementById("voucherSelect");
        if (!select) return;
        const option = select.options[select.selectedIndex];
        
        const id = option.value;
        const type = option.getAttribute("data-type");
        const val = parseFloat(option.getAttribute("data-val")) || 0;
        const min = parseFloat(option.getAttribute("data-min")) || 0;
        
        const statusLabel = document.getElementById("voucherStatus");
        if (statusLabel) statusLabel.innerText = "";

        // Cập nhật lại tổng tiền thực tế đang được chọn
        const cartData = getCheckedCartData();
        window.currentTotal = cartData.total;
        window.currentVoucherId = "";
        window.currentDiscount = 0;
        
        if (id !== "") {
            // Nếu tổng tiền không đạt tối thiểu
            if (window.currentTotal < min) {
                if (statusLabel) {
                    statusLabel.innerText = "Chưa đạt đơn tối thiểu (" + min.toLocaleString('vi-VN') + "đ)!";
                    statusLabel.style.color = "#e74c3c";
                }
                select.selectedIndex = 0; // Tự động reset voucher về mặc định
                window.updateUI();
                return;
            }
            
            if (statusLabel) {
                statusLabel.innerText = "Voucher khả dụng!";
                statusLabel.style.color = "#27ae60";
            }

            // Tính tiền giảm
            window.currentVoucherId = id;
            if (type === 'fixed') {
                window.currentDiscount = val;
            } else {
                window.currentDiscount = (window.currentTotal * val) / 100;
            }
            // Không giảm quá tổng tiền đơn
            if (window.currentDiscount > window.currentTotal) {
                window.currentDiscount = window.currentTotal;
            }
        }
        
        window.updateUI();
    }

    /* ── CẬP NHẬT TIỀN TRÊN MÀN HÌNH VÀ MODAL ── */
    window.updateUI = function() {
        const discountRow = document.getElementById('discountRow');
        const finalRow = document.getElementById('finalRow');
        const grandTotalEl = document.getElementById("grandTotal");
        const finalTotal = window.currentTotal - window.currentDiscount;
        
        // Cập nhật tổng tiền gốc trên màn hình giỏ hàng
        if (grandTotalEl) grandTotalEl.textContent = window.currentTotal.toLocaleString("vi-VN") + " VNĐ";

        // Cập nhật Modal
        if (window.currentDiscount > 0) {
            if(discountRow) discountRow.style.display = 'flex';
            if(finalRow) finalRow.style.display = 'flex';
            document.getElementById('displayDiscount').innerText = "-" + window.currentDiscount.toLocaleString('vi-VN') + " VNĐ";
            document.getElementById('displayFinal').innerText = finalTotal.toLocaleString('vi-VN') + " VNĐ";
        } else {
            if(discountRow) discountRow.style.display = 'none';
            if(finalRow) finalRow.style.display = 'none';
        }
    }

    /* ── MỞ MODAL XÁC NHẬN ── */
    window.checkoutCart = function() {
        const cartData = getCheckedCartData();
        if (cartData.ids.length === 0) {
            window.showToast("Vui lòng chọn ít nhất 1 sản phẩm để thanh toán!");
            return;
        }

        // Đẩy dữ liệu vào Modal
        const modalTotalEl = document.getElementById('modalTotal');
        if (modalTotalEl) modalTotalEl.innerText = cartData.total.toLocaleString('vi-VN') + " VNĐ";
        
        const modalQtyEl = document.getElementById('modalQty');
        if (modalQtyEl) modalQtyEl.innerText = cartData.totalQty;
        
        const modalProductEl = document.getElementById('modalProduct');
        if (modalProductEl) modalProductEl.innerText = cartData.items.join(", ");
        
        window.applyVoucher(); // Kiểm tra lại Voucher lần cuối
        
        const modal = document.getElementById("confirmModal");
        if (modal) modal.style.display = "flex"; // Dùng flex để căn giữa bảng
    }

    /* ── GỬI DỮ LIỆU ĐẶT HÀNG LÊN SERVER (NÚT XÁC NHẬN) ── */
    window.confirmCheckoutCart = function() {
        const cartData = getCheckedCartData();
        if (cartData.ids.length === 0) return;

        const formData = new URLSearchParams();
        formData.append("ajax_checkout", "1");
        if (window.currentVoucherId !== "") {
            formData.append("voucher_id", window.currentVoucherId);
        }
        cartData.ids.forEach(id => {
            formData.append("selected[]", id);
        });

        // Đổi text nút bấm an toàn (Có check null)
        const btn = document.querySelector(".btn-confirm");
        let oldText = "✓ Xác nhận";
        if (btn) {
            oldText = btn.innerText;
            btn.innerText = "Đang xử lý...";
            btn.disabled = true;
        }

        fetch("cart.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                window.location.href = "invoice.php?success=1";
            } else {
                alert("Lỗi: " + data.msg);
                if (btn) { btn.innerText = oldText; btn.disabled = false; }
            }
        })
        .catch(err => {
            alert("Lỗi kết nối máy chủ");
            if (btn) { btn.innerText = oldText; btn.disabled = false; }
        });
    }

    /* ── ĐÓNG MODAL ── */
    window.closeModal = function() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    /* ── PHỤC HỒI NÚT XÓA ── */
    window.showToast = function(msg, duration = 2500) {
        const t = document.getElementById("toast");
        if(!t) return;
        t.textContent = msg;
        t.classList.add("show");
        setTimeout(() => t.classList.remove("show"), duration);
    }

    window.removeCartItem = function(productId, itemSum, btn) {
        const row = document.getElementById("row-" + productId);
        if(row) row.classList.add("removing");

        fetch("remove_cart.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "product_id=" + productId
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                setTimeout(() => {
                    if(row) row.remove();
                    window.applyVoucher(); // Cập nhật lại tổng tiền & Voucher sau khi xóa
                    window.showToast("🗑️ Đã xóa sản phẩm khỏi giỏ hàng");

                    const rows = document.querySelectorAll(".cart-row");
                    if (rows.length === 0) {
                        document.querySelector(".welcome").innerHTML = "<p>🛒 Giỏ hàng trống</p>";
                    }
                }, 350);
            } else {
                if(row) row.classList.remove("removing");
                window.showToast("❌ " + (data.msg || "Có lỗi xảy ra"));
            }
        })
        .catch(() => {
            if(row) row.classList.remove("removing");
            window.showToast("❌ Không thể kết nối máy chủ");
        });
    }

    // Tự động tính lại tổng tiền khi ấn chọn/bỏ chọn checkbox
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll(".item-select").forEach(cb => {
            cb.addEventListener('change', () => {
                window.applyVoucher();
            });
        });
    });
</script>
</body>
</html>