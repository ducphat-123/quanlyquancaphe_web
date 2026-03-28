<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../connect.php";

$message = "";
$msg_color = "red";

$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $repass   = $_POST["repassword"] ?? "";

    if ($username === "" || $email === "" || $password === "" || $repass === "") {
        $message = "Vui lòng nhập đầy đủ thông tin";
    } 
    else if ($password !== $repass) {
        $message = "Mật khẩu không khớp";
    } 
    else {
        // Kiểm tra username + email có khớp không
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ? AND email = ?"
        );
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) === 0) {
            $message = "Tài khoản hoặc email không đúng";
        } 
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE users SET password = ? WHERE username = ?"
            );
            mysqli_stmt_bind_param($stmt, "ss", $hash, $username);
            mysqli_stmt_execute($stmt);

            $message = "Đổi mật khẩu thành công";
            $msg_color = "green";

            // reset form
            $username = $email = "";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
    <link rel="stylesheet" href="../../css/account.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="auth-box">
    <h2>QUÊN MẬT KHẨU</h2>

    <form method="post">
        <input type="text"
               name="username"
               placeholder="Tài khoản"
               value="<?= htmlspecialchars($username) ?>"
               required>

        <input type="email"
               name="email"
               placeholder="Email đăng ký"
               value="<?= htmlspecialchars($email) ?>"
               required>

        <div class="password-box">
            <input type="password" id="password"
                   name="password"
                   placeholder="Mật khẩu mới" required>
            <i class="fa-solid fa-eye-slash toggle-password"
               onclick="togglePassword('password', this)"></i>
        </div>

        <div class="password-box">
            <input type="password" id="repassword"
                   name="repassword"
                   placeholder="Nhập lại mật khẩu" required>
            <i class="fa-solid fa-eye-slash toggle-password"
               onclick="togglePassword('repassword', this)"></i>
        </div>

        <button type="submit">Đổi mật khẩu</button>
    </form>

    <!-- LABEL THÔNG BÁO (DƯỚI BUTTON) -->
    <?php if ($message): ?>
        <p style="color:<?= $msg_color ?>; text-align:center; margin-top:10px;">
            <?= $message ?>
        </p>
    <?php endif; ?>

    <div class="auth-links">
        <a href="login.php">Quay lại đăng nhập</a>
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
