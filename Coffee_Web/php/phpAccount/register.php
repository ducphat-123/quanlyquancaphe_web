<?php
include "../connect.php";

$message = "";
$msg_color = "red";

$name = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name       = trim($_POST["name"] ?? "");
    $username   = trim($_POST["username"] ?? "");
    $email      = trim($_POST["email"] ?? "");
    $password   = $_POST["password"] ?? "";
    $repassword = $_POST["repassword"] ?? "";

    if ($name === "" || $username === "" || $email === "" || $password === "" || $repassword === "") {
        $message = "Vui lòng nhập đầy đủ thông tin";
    } 
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ";
    }
    else if ($password !== $repassword) {
        $message = "Mật khẩu không khớp";
    } 
    else {
        // Kiểm tra username tồn tại
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $message = "Tài khoản đã tồn tại";
            $username = "";
        } 
        else {
            // Kiểm tra email tồn tại
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $message = "Email đã được sử dụng";
                $email = "";
            } 
            else {
                // Mã hóa mật khẩu
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Thêm user
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, "ssss", $name, $username, $email, $hash);
                mysqli_stmt_execute($stmt);

                $message = "Đăng ký thành công";
                $msg_color = "green";

                // reset form
                $name = $username = $email = "";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>ĐĂNG KÝ</title>
    <link rel="stylesheet" href="../../css/account.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="auth-box">
    <h2>ĐĂNG KÝ</h2>

    <form method="post">
        <!-- HỌ VÀ TÊN -->
        <input type="text"
               name="name"
               placeholder="Họ và tên"
               value="<?= htmlspecialchars($name) ?>"
               required>

        <!-- TÀI KHOẢN -->
        <input type="text"
               name="username"
               placeholder="Tài khoản"
               value="<?= htmlspecialchars($username) ?>"
               required>

        <!-- EMAIL -->
        <input type="email"
               name="email"
               placeholder="Email"
               value="<?= htmlspecialchars($email) ?>"
               required>

        <!-- MẬT KHẨU -->
        <div class="password-box">
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="Mật khẩu"
                   required>
            <i class="fa-solid fa-eye-slash toggle-password"
               onclick="togglePassword('password', this)"></i>
        </div>

        <!-- NHẬP LẠI MẬT KHẨU -->
        <div class="password-box">
            <input type="password"
                   id="repassword"
                   name="repassword"
                   placeholder="Nhập lại mật khẩu"
                   required>
            <i class="fa-solid fa-eye-slash toggle-password"
               onclick="togglePassword('repassword', this)"></i>
        </div>

        <button type="submit">Đăng ký</button>
    </form>

    <!-- LABEL THÔNG BÁO (BÊN DƯỚI BUTTON) -->
    <?php if ($message): ?>
        <p style="color:<?= $msg_color ?>; text-align:center; margin-top:10px;">
            <?= $message ?>
        </p>
    <?php endif; ?>

    <div class="auth-links">
        <a href="login.php">Đã có tài khoản? Đăng nhập</a>
    </div>
</div>

<script>
function togglePassword(id, icon) {
    const input = document.getElementById(id);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    }
}
</script>

</body>
</html>
