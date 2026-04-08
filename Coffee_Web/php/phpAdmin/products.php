<?php
session_start();
include "../connect.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

/* ===== CONFIG - CẤU HÌNH ===== */
$upload_dir = "../../uploads/products/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

/* ===== CHỨC NĂNG TẢI ẢNH ===== */
function uploadImage($file, $dir, $old = "")
{
    if ($file["error"] !== 0) return $old;

    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg", "jpeg", "png", "gif"])) return $old;

    if ($old && file_exists($dir . $old)) unlink($dir . $old);

    $name = uniqid() . "." . $ext;
    move_uploaded_file($file["tmp_name"], $dir . $name);
    return $name;
}

/* ===== THÊM SẢN PHẨM ===== */
if (isset($_POST["add_product"])) {
    $image = uploadImage($_FILES["image"], $upload_dir);

    $name        = $conn->real_escape_string($_POST["name"]);
    $category_id = (int)$_POST["category_id"];
    $price       = (float)$_POST["price"];
    $quantity    = (int)$_POST["quantity"];
    $description = $conn->real_escape_string($_POST["description"]);
    $status      = $conn->real_escape_string($_POST["status"]);

    $sql = "
        INSERT INTO products (name, category_id, price, quantity, description, image, status)
        VALUES ('$name', $category_id, $price, $quantity, '$description', '$image', '$status')
    ";

    if ($conn->query($sql)) {
        $success = "Thêm sản phẩm thành công!";
    } else {
        $error = "Lỗi: " . $conn->error;
    }
}

/* ===== SỬA ===== */
if (isset($_POST["edit_product"])) {
    $id = (int)$_POST["id"];

    $old = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
    $image = uploadImage($_FILES["image"], $upload_dir, $old["image"] ?? "");

    $name        = $conn->real_escape_string($_POST["name"]);
    $category_id = (int)$_POST["category_id"];
    $price       = (float)$_POST["price"];
    $quantity    = (int)$_POST["quantity"];
    $description = $conn->real_escape_string($_POST["description"]);
    $status      = $conn->real_escape_string($_POST["status"]);

    $sql = "
        UPDATE products SET
            name='$name',
            category_id=$category_id,
            price=$price,
            quantity=$quantity,
            description='$description',
            image='$image',
            status='$status'
        WHERE id=$id
    ";

    if ($conn->query($sql)) {
        $success = "Cập nhật thành công!";
    } else {
        $error = "Lỗi cập nhật: " . $conn->error;
    }
}

/* ===== XÓA ===== */
if (isset($_POST["delete_id"])) {
    $id = (int)$_POST["delete_id"];

    $old = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
    if (!empty($old["image"]) && file_exists($upload_dir . $old["image"])) {
        unlink($upload_dir . $old["image"]);
    }

    if ($conn->query("DELETE FROM products WHERE id=$id")) {
        $success = "Xóa sản phẩm thành công!";
    } else {
        $error = "Lỗi xóa: " . $conn->error;
    }
}

/* ===== DỮ LIỆU  ===== */
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

/* ===== TÌM KIẾM + LỌC ===== */
$search   = trim($_GET["search"] ?? "");
$category = (int)($_GET["category"] ?? 0);

$sql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";

if ($search !== "") {
    $s = $conn->real_escape_string($search);
    $sql .= " AND p.name LIKE '%$s%'";
}
if ($category > 0) {
    $sql .= " AND p.category_id = $category";
}

$sql .= " ORDER BY p.id DESC";
$products = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý sản phẩm</title>
       <link rel="stylesheet" href="../../css/home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/productsadmin.css?v=<?php echo time(); ?>">
</head>

<body>

    <div class="layout">

        <aside class="sidebar">
            <h2>☕ADMIN</h2>
            <ul>
                <li><a href="adminHome.php">Trang chủ</a></li>
                <li><a href="adminUsers.php">Quản lý người dùng</a></li>
                <li><a href="categories.php">Quản lý danh mục sản phẩm</a></li>
                <li><a href="vouchers.php">Quản lý mã giảm giá</a></li>
                <li><a href="products.php" class="active">Quản lý sản phẩm</a></li>
                <li><a href="orders.php">Quản lý hóa đơn</a></li>
                <li><a href="statistics.php">Thống kê</a></li>
            </ul>
        </aside>

        <main class="content">

            <header class="topbar">
                <h1>QUẢN LÝ SẢN PHẨM</h1>
                <div class="account">
                    👤 <?= htmlspecialchars($_SESSION["username"]) ?> |
                    <a href="../phpAccount/logout.php">Đăng xuất</a>
                </div>
            </header>

            <section class="welcome">
                <!-- Hiện thông báo khi thêm thành công hoặc báo lỗi -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <button onclick="openAddModal()" class="btn btn-success">+ Thêm sản phẩm mới</button>
                </div>

                <!-- Bộ lọc -->
                <div class="filter-section">
                    <form method="GET" style="display: flex; gap: 10px; width: 100%; align-items: end;">
                        <div class="form-group" style="flex: 2;">
                            <label>Tìm kiếm:</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập tên sản phẩm...">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Danh mục:</label>
                            <select name="category">
                                <option value="0">Tất cả</option>
                                <?php
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()):
                                ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Lọc</button>
                        <a href="products.php" class="btn btn-danger">Reset</a>
                    </form>
                </div>

                <!-- Danh sách sản phẩm -->
                <div style="background: white; padding: 20px; border-radius: 8px;">
                    <h3>Danh sách sản phẩm (<?= $products->num_rows ?> sản phẩm)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Giá</th>
                                <th>Số lượng</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products->num_rows > 0): ?>
                                <?php while ($row = $products->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $row["id"] ?></td>
                                        <td>
                                            <?php if (!empty($row["image"])): ?>
                                                <img src="<?= $upload_dir . $row['image'] ?>" class="product-image" alt="Product">
                                            <?php else: ?>
                                                <img src="../../images/no-image.png" class="product-image" alt="No image">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $row["name"] ?></td>
                                        <td><?= $row["category_name"] ?></td>
                                        <td><?= number_format($row["price"], 0, ',', '.') ?>đ</td>
                                        <td><?= $row["quantity"] ?></td>
                                        <td>
                                            <?php
                                            $isOutOfStock = ($row['quantity'] <= 0);
                                            ?>
                                            <span class="status-badge <?= $isOutOfStock ? 'status-inactive' : 'status-active' ?>">
                                                <?= $isOutOfStock ? 'Hết hàng' : 'Còn hàng' ?>
                                            </span>
                                        </td>

                                        <td>
                                            <button onclick='openEditModal(<?= json_encode($row) ?>)' class="btn btn-warning">Sửa</button>
                                            <form method="POST" style="display:inline"
                                                onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">
                                                <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Xóa</button>
                                            </form>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">Không tìm thấy sản phẩm nào</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>

        </main>

    </div>

    <!-- Modal thêm/sửa sản phẩm -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Thêm sản phẩm mới</h3>
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="id" id="product_id">

                <div class="form-group">
                    <label>Tên sản phẩm: *</label>
                    <input type="text" name="name" id="product_name" required>
                </div>

                <div class="form-group">
                    <label>Danh mục: *</label>
                    <select name="category_id" id="product_category" required>
                        <?php
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()):
                        ?>
                            <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Giá: *</label>
                    <input type="number" name="price" id="product_price" step="1000" required>
                </div>

                <div class="form-group">
                    <label>Số lượng: *</label>
                    <input type="number" name="quantity" id="product_quantity" required>
                </div>

                <div class="form-group">
                    <label>Mô tả:</label>
                    <textarea name="description" id="product_description" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label>Ảnh sản phẩm:</label>
                    <input type="file" name="image" id="product_image" accept="image/*">
                    <img id="preview_image" style="max-width: 200px; margin-top: 10px; display: none;">
                </div>

                <div class="form-group">
                    <label>Trạng thái: *</label>
                    <select name="status" id="product_status" required>
                        <option value="active">Còn hàng</option>
                        <option value="inactive">Hết hàng</option>
                    </select>
                </div>

                <button type="submit" name="add_product" id="submitBtn" class="btn btn-success">Thêm sản phẩm</button>
                <button type="button" onclick="closeModal()" class="btn btn-danger">Hủy</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Thêm sản phẩm mới';
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('submitBtn').name = 'add_product';
            document.getElementById('submitBtn').innerText = 'Thêm sản phẩm';
            document.getElementById('preview_image').style.display = 'none';
            document.getElementById('productModal').style.display = 'block';
        }

        function openEditModal(product) {
            document.getElementById('modalTitle').innerText = 'Sửa sản phẩm';
            document.getElementById('product_id').value = product.id;
            document.getElementById('product_name').value = product.name;
            document.getElementById('product_category').value = product.category_id;
            document.getElementById('product_price').value = product.price;
            document.getElementById('product_quantity').value = product.quantity;
            document.getElementById('product_description').value = product.description || '';
            document.getElementById('product_status').value = product.status;
            document.getElementById('submitBtn').name = 'edit_product';
            document.getElementById('submitBtn').innerText = 'Cập nhật';

            if (product.image) {
                document.getElementById('preview_image').src = '<?= $upload_dir ?>' + product.image;
                document.getElementById('preview_image').style.display = 'block';
            }

            document.getElementById('productModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        document.getElementById('product_image').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_image').src = e.target.result;
                    document.getElementById('preview_image').style.display = 'block';
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>

</body>

</html>