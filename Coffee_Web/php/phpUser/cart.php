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
        foreach ($selectedIds as $productId) {
            $qty = (int)$cart[$productId];
            $rs  = mysqli_query($conn, "SELECT name, price, quantity FROM products WHERE id = $productId FOR UPDATE");
            $p   = mysqli_fetch_assoc($rs);
            if (!$p || $qty > $p["quantity"]) throw new Exception(($p["name"] ?? "Sản phẩm") . " không đủ tồn kho");

            $totalPrice = $p["price"] * $qty;
            mysqli_query($conn, "INSERT INTO user_invoices (user_id, product_name, quantity, total_price, created_at)
                VALUES ($userId, '" . mysqli_real_escape_string($conn, $p["name"]) . "', $qty, $totalPrice, NOW())");
            mysqli_query($conn, "UPDATE products SET quantity = quantity - $qty WHERE id = $productId");

            $stmtDel = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmtDel->bind_param("ii", $userId, $productId);
            $stmtDel->execute();
            unset($_SESSION["cart"][$productId]);
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng</title>
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/products.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/cart.css?v=<?php echo time(); ?>">
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
                            ? "/quanlyquancaphe_web/Coffee_Web/uploads/products/" . $p["image"]
                            : "/quanlyquancaphe_web/Coffee_Web/images/default-product.png";
                    ?>
                    <tr class="cart-row" id="row-<?= $id ?>" data-id="<?= $id ?>" data-price="<?= $p['price'] ?>" data-qty="<?= $qty ?>">
                        <td><input type="checkbox" class="item-select" value="<?= $id ?>" checked></td>
                        <td>
                            <div class="cart-item">
                                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($p["name"]) ?>">
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

                <br>
                <button type="button" id="checkoutBtn" onclick="checkoutCart()">
                    ✔ Thanh toán mục đã chọn
                </button>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <img id="modalImage" src="" alt="Sản phẩm" class="modal-product-image">
        </div>
        <div class="modal-info">
            <div class="modal-icon" id="modalIcon">💳</div>
            <h2 id="modalTitle">XÁC NHẬN THANH TOÁN</h2>
            <div class="modal-body">
                <div class="info-item">
                    <span class="label">Sản phẩm</span>
                    <p id="modalProduct"></p>
                </div>
                <div class="info-item">
                    <span class="label">Tổng số lượng</span>
                    <p id="modalQty"></p>
                </div>
                <div class="info-item">
                    <span class="label">Tổng tiền</span>
                    <p id="modalTotal"></p>
                </div>
                <div class="info-item">
                    <span class="label">Địa chỉ giao hàng</span>
                    <p id="modalAddress"></p>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn btn-confirm" onclick="confirmCheckoutCart()">✓ Xác nhận</button>
                <button class="modal-btn btn-cancel"  onclick="closeModal()">✕ Hủy</button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<script>
const userAddress = "<?= htmlspecialchars($userAddress) ?>";

/* ── TOAST ── */
function showToast(msg, duration = 2500) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    setTimeout(() => t.classList.remove("show"), duration);
}

/* ── XÓA SẢN PHẨM KHỎI GIỎ ── */
function removeCartItem(productId, itemSum, btn) {
    // Gạch mờ dòng ngay lập tức
    const row = document.getElementById("row-" + productId);
    row.classList.add("removing");

    fetch("remove_cart.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "product_id=" + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            // Đợi animation xong rồi xóa dòng
            setTimeout(() => {
                row.remove();
                recalcTotal();
                showToast("🗑️ Đã xóa sản phẩm khỏi giỏ hàng");

                // Nếu giỏ trống → thông báo
                const rows = document.querySelectorAll(".cart-row");
                if (rows.length === 0) {
                    document.querySelector(".welcome").innerHTML = "<p>🛒 Giỏ hàng trống</p>";
                }
            }, 350);
        } else {
            // Rollback animation nếu lỗi
            row.classList.remove("removing");
            showToast("❌ " + (data.msg || "Có lỗi xảy ra"));
        }
    })
    .catch(() => {
        row.classList.remove("removing");
        showToast("❌ Không thể kết nối máy chủ");
    });
}

/* ── TÍNH LẠI TỔNG SAU KHI XÓA ── */
function recalcTotal() {
    let total = 0;
    document.querySelectorAll(".cart-row").forEach(row => {
        const cb = row.querySelector(".item-select");
        if (cb && cb.checked) {
            const price = parseInt(row.dataset.price) || 0;
            const qty   = parseInt(row.dataset.qty)   || 0;
            total += price * qty;
        }
    });
    const el = document.getElementById("grandTotal");
    if (el) el.textContent = total.toLocaleString("vi-VN") + " VNĐ";
}
</script>

<script src="../../js/products.js?v=<?php echo time(); ?>"></script>
<script src="../../js/cart.js?v=<?php echo time(); ?>"></script>
<script>
    const products = <?= json_encode($jsProducts ?? []) ?>;
    const total    = "<?= number_format($grandTotal ?? 0) ?>";
    const checkout = new CartCheckout(products, total);
</script>

</body>
</html>