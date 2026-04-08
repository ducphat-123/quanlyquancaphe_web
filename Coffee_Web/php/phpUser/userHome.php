<?php
session_start();
include "../connect.php";
// CHỈ CHO USER
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    header("Location: ../phpAccount/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>User Home</title>
    <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
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
                <li><a href="kho_voucher.php"> Săn voucher</a></li>
                <li><a href="my_vouchers.php"> Ví voucher của tôi</a></li>
                <li><a href="invoice.php">Hóa đơn</a></li>
                <li><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
            </ul>
        </div>

        <!-- CONTENT -->
        <div class="content">

            <!-- TOP BAR -->
            <div class="topbar">
                <h1>TRANG CHỦ</h1>

                <div class="account">
                    👤
                    <b><?= htmlspecialchars($_SESSION["name"]) ?></b> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="welcome">
                <h2>Xin chào, <?= htmlspecialchars($_SESSION["name"]) ?> 👋</h2>
                <br>
                <p>Cảm ơn vì đã đến với cửa hàng cà phê của chúng tôi, chúc bạn một ngày mua sắm vui vẻ!</p>
            </div>

        </div>
    </div>

</body>

</html>