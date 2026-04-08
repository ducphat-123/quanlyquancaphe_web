<?php
session_start();
include "../connect.php";

/* CHỈ USER */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    header("Location: ../phpAccount/login.php");
    exit;
}

$userId = $_SESSION["user_id"];
$message = "";
$error = "";

/* XỬ LÝ UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* CẬP NHẬT TÊN */
    if (isset($_POST["update_profile"])) {
        $name = trim($_POST["name"] ?? "");
        $address = trim($_POST["address"] ?? "");

        if ($name === "") {
            $error = "Tên không được để trống";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $address, $userId);
            $stmt->execute();

            $_SESSION["name"] = $name;
            $message = "Cập nhật thông tin thành công";
        }
    }

    /* ĐỔI MẬT KHẨU */
    if (isset($_POST["change_password"])) {
        $oldPass = $_POST["old_password"] ?? "";
        $newPass = $_POST["new_password"] ?? "";
        $rePass  = $_POST["re_password"] ?? "";

        // Lấy mật khẩu hiện tại
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!password_verify($oldPass, $row["password"])) {
            $error = "Mật khẩu hiện tại không đúng";
        } elseif ($newPass === "" || $newPass !== $rePass) {
            $error = "Mật khẩu mới không khớp";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $userId);
            $stmt->execute();

            $message = "Đổi mật khẩu thành công";
        }
    }
}

/* LẤY THÔNG TIN USER */
$stmt = $conn->prepare("SELECT username, name, email, address FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
    <link rel="stylesheet" href="../../css/profile.css">
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
                
                <li><a href="my_vouchers.php"> Ví voucher của tôi</a></li>
                <li><a href="kho_voucher.php"> Săn voucher</a></li>
                <li><a href="invoice.php">Hóa đơn</a></li>
                <li class="active"><a href="personalProfile.php">Hồ sơ cá nhân</a></li>
            </ul>
        </div>

        <!-- CONTENT -->
        <div class="content">

            <div class="topbar">
                <h1>HỒ SƠ CÁ NHÂN</h1>
                <div class="account">
                    👤 <b><?= htmlspecialchars($_SESSION["name"]) ?></b> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </div>

            <div class="welcome">
                <div class="profile-container">

                    <!-- CỘT THÔNG TIN -->
                    <div class="profile-box">
                        <h3>Thông tin tài khoản</h3>

                        <form method="post">
                            <p>
                                <b>Tên đăng nhập</b><br>
                                <input type="text" value="<?= htmlspecialchars($user["username"]) ?>" disabled>
                            </p>

                            <p>
                                <b>Email</b><br>
                                <input type="email" value="<?= htmlspecialchars($user["email"]) ?>" disabled>
                            </p>

                            <p>
                                <b>Họ và tên</b><br>
                                <input type="text" name="name" value="<?= htmlspecialchars($user["name"]) ?>">
                            </p>

                            <p>
                                <b>Địa chỉ</b><br>
                                <input type="text" name="address" placeholder="Nhập địa chỉ của bạn" value="<?= htmlspecialchars($user["address"] ?? "") ?>">
                            </p>

                            <button type="submit" name="update_profile">Cập nhật thông tin</button>
                        </form>
                    </div>

                    <!-- CỘT ĐỔI MẬT KHẨU -->
                    <div class="profile-box">
                        <h3>Đổi mật khẩu</h3>

                        <form method="post">
                            <p>
                                <b>Mật khẩu hiện tại</b><br>
                                <input type="password" name="old_password" required>
                            </p>

                            <p>
                                <b>Mật khẩu mới</b><br>
                                <input type="password" name="new_password" required>
                            </p>

                            <p>
                                <b>Nhập lại mật khẩu mới</b><br>
                                <input type="password" name="re_password" required>
                            </p>

                            <button type="submit" name="change_password">Đổi mật khẩu</button>
                        </form>
                    </div>
                </div>
                <?php if ($error || $message): ?>
                    <p class="form-message <?= $error ? 'error' : 'success' ?>"><?= $error ?: $message ?></p>
                <?php endif; ?>
            </div>
        </div>
</body>

</html>