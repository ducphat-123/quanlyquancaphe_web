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
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $message = "Tài khoản đã tồn tại";
            $username = "";
        } 
        else {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $message = "Email đã được sử dụng";
                $email = "";
            } 
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $name, $username, $email, $hash);
                mysqli_stmt_execute($stmt);
                $message = "Đăng ký thành công";
                $msg_color = "green";
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
    <link rel="stylesheet" href="../../css/account.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- Background giống login -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<canvas id="bg-canvas"></canvas>

<div class="auth-box">
    <h2>Đăng Ký</h2>
    <p class="auth-subtitle">Tạo tài khoản mới</p>

    <form method="post">

        <input type="text"
               name="name"
               placeholder="Họ và tên"
               value="<?= htmlspecialchars($name) ?>"
               required>

        <input type="text"
               name="username"
               placeholder="Tên đăng nhập"
               value="<?= htmlspecialchars($username) ?>"
               required>

        <input type="email"
               name="email"
               placeholder="Email"
               value="<?= htmlspecialchars($email) ?>"
               required>

        <div class="password-box">
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="Mật khẩu"
                   required>
            <i class="fa-solid fa-eye-slash toggle-password"
               onclick="togglePassword('password', this)"></i>
        </div>

        <div class="password-box" style="margin-top:1rem;">
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

    <?php if ($message): ?>
        <p class="auth-msg <?= $msg_color === 'green' ? 'auth-msg--success' : 'auth-msg--error' ?>">
            <?= $message ?>
        </p>
    <?php endif; ?>

    <div class="auth-links" style="justify-content:center;">
        <a href="login.php">
            <i class="fa-solid fa-arrow-left" style="font-size:11px;margin-right:4px;"></i>
            Đã có tài khoản? Đăng nhập
        </a>
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

(function () {
    const canvas = document.getElementById('bg-canvas');
    const ctx = canvas.getContext('2d');
    function resize() { canvas.width = innerWidth; canvas.height = innerHeight; }
    resize();
    window.addEventListener('resize', resize);

    const pts = Array.from({ length: 65 }, () => ({
        x: Math.random(), y: Math.random(),
        r: 0.7 + Math.random() * 2.2,
        spd: 0.00016 + Math.random() * 0.00032,
        drift: (Math.random() - 0.5) * 0.00022,
        maxA: 0.18 + Math.random() * 0.45,
        col: ['#e8b86d', '#c47c30', '#f5dfa0', '#a05820'][Math.floor(Math.random() * 4)]
    }));

    function draw(ts) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const W = canvas.width, H = canvas.height;
        pts.forEach(p => {
            p.y -= p.spd; p.x += p.drift;
            if (p.y < 0) { p.y = 1; p.x = Math.random(); }
            if (p.x < 0 || p.x > 1) p.drift *= -1;
            const rise = 1 - p.y;
            const fade = rise < 0.08 ? rise / 0.08 : rise > 0.88 ? (1 - rise) / 0.12 : 1;
            ctx.save();
            ctx.globalAlpha = p.maxA * fade;
            ctx.beginPath();
            ctx.arc(p.x * W, p.y * H, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.col;
            ctx.fill();
            ctx.restore();
        });
        for (let i = 0; i < 7; i++) {
            const ph = (ts * 0.00018 + i / 7) * Math.PI * 2;
            const x = (i / 7 + Math.sin(ph) * 0.06) * W;
            const a = Math.abs(Math.sin(ph)) * 0.07;
            const g = ctx.createLinearGradient(x, H, x + 25, 0);
            g.addColorStop(0, `rgba(232,184,109,${a})`);
            g.addColorStop(0.4, `rgba(232,184,109,${a * 0.5})`);
            g.addColorStop(1, 'rgba(232,184,109,0)');
            ctx.save();
            ctx.strokeStyle = g; ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(x, H);
            ctx.bezierCurveTo(x + 50, H * 0.65, x - 50, H * 0.38, x + 25, 0);
            ctx.stroke();
            ctx.restore();
        }
        requestAnimationFrame(draw);
    }
    requestAnimationFrame(draw);
})();
</script>

</body>
</html>