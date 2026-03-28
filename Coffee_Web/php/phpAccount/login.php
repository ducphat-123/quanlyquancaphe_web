<?php
session_start();
include "../connect.php";
$message = "";
$msg_color = "red";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $message = "Vui lòng nhập đầy đủ thông tin";
    } else {
        // Lấy user theo username
        $stmt = mysqli_prepare($conn, "SELECT id, name, username, password, role FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // So khớp mật khẩu
            if (password_verify($password, $row["password"])) {
                $_SESSION['user_id']  = $row['id'];
                $_SESSION["name"]     = $row["name"];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];

                if ($row['role'] === 'admin') {
                    header("Location: ../phpAdmin/adminHome.php");
                } else {
                    header("Location: ../phpUser/userHome.php");
                }
                exit;
            } else {
                $message = "Mật khẩu không đúng";
            }
        } else {
            $message = "Tài khoản không tồn tại";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>ĐĂNG NHẬP</title>
    <link rel="stylesheet" href="/quanlyquancaphe_web/Coffee_Web/css/account.css?v=<?php echo time(); ?>">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="auth-box">
        <h2>ĐĂNG NHẬP</h2>

        <form method="post">
            <input type="text" name="username" placeholder="Tên đăng nhập" required>

            <div class="password-box">
                <input type="password" id="password" name="password" placeholder="Mật khẩu" required>
                <i class="fa-solid fa-eye-slash toggle-password"
                    onclick="togglePassword('password', this)"></i>
            </div>

            <button type="submit">Đăng nhập</button>
        </form>

        <?php if ($message): ?>
            <p style="color:<?= $msg_color ?>; text-align:center; margin-top:10px;">
                <?= $message ?>
            </p>
        <?php endif; ?>

        <div class="auth-links">
            <a href="register.php">Đăng ký</a> |
            <a href="forgotPassword.php">Quên mật khẩu?</a>
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