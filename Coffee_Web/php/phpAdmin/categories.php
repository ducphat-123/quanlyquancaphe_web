<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

/* ===== THÊM ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_add_category"])) {
    $name = trim($_POST["txt_category_name"]);

    if ($name !== "") {
        $sql = "INSERT INTO categories(name) VALUES ('$name')";
        if ($conn->query($sql) === TRUE) {
            $success = "Thêm danh mục thành công!";
        } else {
            $error = "Lỗi thêm danh mục!";
        }
    }
}

/* ===== SỬA ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_edit_category"])) {
    $id   = (int)$_POST["id_category"];
    $name = trim($_POST["txt_category_name"]);

    if ($name !== "") {
        $sql = "UPDATE categories SET name='$name' WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $success = "Cập nhật danh mục thành công!";
        } else {
            $error = "Lỗi cập nhật danh mục!";
        }
    }
}

/* ===== XÓA ===== */
if (isset($_POST["btn_delete_category"])) {
    $id = (int)$_POST["id_category"];

    $check_sql = "SELECT COUNT(*) AS total FROM products WHERE category_id=$id";
    $check_result = $conn->query($check_sql);
    $row = $check_result->fetch_assoc();
    $count = $row["total"];

    if ($count > 0) {
        $error = "Không thể xóa vì còn $count sản phẩm!";
    } else {
        $sql = "DELETE FROM categories WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $success = "Xóa danh mục thành công!";
        } else {
            $error = "Lỗi xóa danh mục!";
        }
    }
}

/* ===== SEARCH AN TOÀN ===== */
$search = trim($_GET["txt_search_category"] ?? "");
$search_param = "%$search%"; // Chuẩn bị tham số tìm kiếm

// Sử dụng dấu ? để làm đại diện (placeholder)
$sql = "
    SELECT c.id, c.name, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    WHERE c.name LIKE ?
    GROUP BY c.id, c.name
    ORDER BY c.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search_param); // "s" nghĩa là string
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý danh mục</title>
     <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/categories.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="layout">

        <aside class="sidebar">
            <h2>☕ADMIN</h2>
            <ul>
                <li><a href="adminHome.php">Trang chủ</a></li>
                <li><a href="adminUsers.php">Quản lý người dùng</a></li>
                <li><a href="categories.php" class="active">Quản lý danh mục sản phẩm</a></li>
                <li><a href="vouchers.php">Quản lý mã giảm giá</a></li>
                <li><a href="products.php">Quản lý sản phẩm</a></li>
                <li><a href="orders.php">Quản lý hóa đơn</a></li>
                <li><a href="statistics.php">Thống kê</a></li>
            </ul>
        </aside>

        <main class="content">

            <header class="topbar">
                <h1>QUẢN LÝ DANH MỤC SẢN PHẨM</h1>
                <div class="account">
                    👤 <?= htmlspecialchars($_SESSION["username"]) ?> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </header>

            <section class="welcome">

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <div>
                    <button onclick="openAddModal()" class="btn btn-success">
                        + Thêm danh mục sản phẩm mới
                    </button>
                </div>

                <!-- FILTER -LỌC-->
                <div class="filter-section">
                    <form method="GET">
                        <div class="form-group">
                            <label>Tìm kiếm</label>
                            <input type="text"
                                name="txt_search_category"
                                value="<?= $search ?>"
                                placeholder="Tên danh mục...">
                        </div>
                        <button class="btn btn-primary">Lọc</button>
                        <a href="categories.php" class="btn btn-delete">Reset</a>
                    </form>
                </div>

                <!-- TABLE -->
                <div class="table-wrap">
                    <h3>Danh sách danh mục (<?= $result->num_rows ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên danh mục</th>
                                <th>Sản phẩm</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="center"><?= $row["id"] ?></td>
                                    <td class="left"><?= $row["name"] ?></td>
                                    <td class="center">
                                        <span class="badge"><?= $row["product_count"] ?> SP</span>
                                    </td>
                                    <td class="center">
                                        <button class="btn btn-edit"
                                            onclick="openEditModal(<?= $row['id'] ?>,'<?= $row['name'], ENT_QUOTES ?>')">
                                            Sửa
                                        </button>

                                        <form method="POST" style="display:inline"
                                            onsubmit="return confirm('Xóa danh mục này?')">
                                            <input type="hidden" name="id_category" value="<?= $row['id'] ?>">
                                            <button name="btn_delete_category"
                                                class="btn btn-delete"
                                                <?= $row["product_count"] > 0 ? "disabled" : "" ?>>
                                                Xóa
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

        </main>
    </div>

    <!-- MODAL THÊM DANH MỤC -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h3>Thêm danh mục</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Tên danh mục</label>
                    <input name="txt_category_name" required>
                </div>
                <button name="btn_add_category" class="btn btn-add">Lưu</button>
            </form>
        </div>
    </div>

    <!-- MODAL SỦA DANH MỤC -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>Sửa danh mục</h3>
            <form method="POST">
                <input type="hidden" name="id_category" id="edit_id">
                <div class="form-group">
                    <label>Tên danh mục</label>
                    <input name="txt_category_name" id="edit_name" required>
                </div>
                <button name="btn_edit_category" class="btn btn-edit">Cập nhật</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            addModal.style.display = 'block';
        }

        function closeAddModal() {
            addModal.style.display = 'none';
        }

        function openEditModal(id, name) {
            edit_id.value = id;
            edit_name.value = name;
            editModal.style.display = 'block';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
        }
    </script>

</body>

</html>