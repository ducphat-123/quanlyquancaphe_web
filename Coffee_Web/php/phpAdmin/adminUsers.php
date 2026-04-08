<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

/* XỬ LÝ XÓA USER */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    if ($id !== $_SESSION["id"]) {
        $sql = "DELETE FROM users WHERE id = ? AND role = 'user'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: adminUsers.php");
    exit;
}
    $keyword = '';

    if (isset($_GET['keyword'])) {
        $keyword = trim($_GET['keyword']);
    }

    $sql = "SELECT id, username, name, email, role 
            FROM users 
            WHERE role = 'user'";

    if ($keyword !== '') {
        $sql .= " AND (username LIKE ? OR name LIKE ? OR email LIKE ?)";
        $stmt = $conn->prepare($sql);
        $search = "%$keyword%";
        $stmt->bind_param("sss", $search, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý người dùng</title>
    <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
</head>

<body>

    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <h2>☕ADMIN</h2>
            <ul>
                <li><a href="adminHome.php">Trang chủ</a></li>
                <li><a href="adminUsers.php">Quản lý người dùng</a></li>
                <li><a href="categories.php">Quản lý danh mục sản phẩm</a></li>
                <li><a href="vouchers.php">Quản lý mã giảm giá</a></li>
                <li><a href="products.php">Quản lý sản phẩm</a></li>
                <li><a href="orders.php">Quản lý hóa đơn</a></li>
                <li><a href="statistics.php">Thống kê</a></li>
            </ul>
        </aside>

        <!-- CONTENT -->
        <main class="content">

            <!-- TOPBAR -->
            <header class="topbar">
                <h1>QUẢN LÝ NGƯỜI DÙNG</h1>
                <div class="account">
                    👤
                    <?= htmlspecialchars($_SESSION["username"]) ?> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </header>
            
            <!-- Tim kiem -->
            <form method="get" class="search-form">
                <input 
                    type="text" 
                    name="keyword" 
                    placeholder="Tìm theo tên, email hoặc username..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >
                <button type="submit">Tìm</button>
            </form>

            <!-- TABLE USERS -->
            <table>
                <tr>
                    <th>Tên đăng nhập</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Quyền</th>
                    <th>Mật khẩu</th>
                    <th>Hành động</th>
                </tr>

                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row["username"]) ?></td>
                        <td><?= htmlspecialchars($row["name"]) ?></td>
                        <td><?= htmlspecialchars($row["email"]) ?></td>
                        <td><?= $row["role"] ?></td>
                        <td>******</td>
                        <td>
                            <?php if ($row["role"] !== "admin") { ?>
                                <a
                                    class="btn-delete"
                                    href="?delete=<?= $row["id"] ?>"
                                    onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                                    Xóa
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>

        </main>

    </div>

</body>

</html>