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
<canvas id="bg-canvas"></canvas>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<script>
(function(){
  const canvas = document.getElementById('bg-canvas');
  const ctx = canvas.getContext('2d');
  function resize(){ canvas.width = innerWidth; canvas.height = innerHeight; }
  resize(); window.addEventListener('resize', resize);

  const pts = Array.from({length:60}, ()=>({
    x: Math.random(), y: Math.random(),
    r: 0.6 + Math.random()*1.8,
    speed: 0.00018 + Math.random()*0.00028,
    drift: (Math.random()-0.5)*0.00025,
    maxA: 0.12 + Math.random()*0.3,
    col: Math.random()>0.5 ? '#c49454' : '#7a4a20',
  }));

  function draw(ts){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const W=canvas.width, H=canvas.height;
    pts.forEach(p=>{
      p.y -= p.speed; p.x += p.drift;
      if(p.y<0){ p.y=1; p.x=Math.random(); }
      if(p.x<0||p.x>1) p.drift*=-1;
      const rise=1-p.y, fade=rise<0.1?rise/0.1:rise>0.85?(1-rise)/0.15:1;
      ctx.save(); ctx.globalAlpha=p.maxA*fade;
      ctx.beginPath(); ctx.arc(p.x*W,p.y*H,p.r,0,Math.PI*2);
      ctx.fillStyle=p.col; ctx.fill(); ctx.restore();
    });

    for(let i=0;i<5;i++){
      const x=((i/5)+Math.sin(ts*0.0003+i)*0.07)*W;
      const a=Math.sin((ts*0.0002+i/5)*Math.PI)*0.05;
      const g=ctx.createLinearGradient(x,H,x+20,0);
      g.addColorStop(0,`rgba(196,148,84,${a})`);
      g.addColorStop(1,'rgba(196,148,84,0)');
      ctx.save(); ctx.strokeStyle=g; ctx.lineWidth=1;
      ctx.beginPath(); ctx.moveTo(x,H);
      ctx.bezierCurveTo(x+40,H*0.6,x-40,H*0.4,x+20,0);
      ctx.stroke(); ctx.restore();
    }
    requestAnimationFrame(draw);
  }
  requestAnimationFrame(draw);
})();
</script>
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