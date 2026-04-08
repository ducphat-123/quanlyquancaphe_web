<?php
session_start();
include "../connect.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../phpAccount/login.php");
    exit;
}

/* ===== THÊM MÃ GIẢM GIÁ ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_add_voucher"])) {
    // Bắt đúng tên "name" từ form HTML addModal
    $code = strtoupper(trim($_POST["voucher_code"])); // In hoa mã code
    $type = $_POST["discount_type"];
    $val = (int)$_POST["discount_value"];
    $min = (int)$_POST["min_order_value"];
    $limit = (int)$_POST["usage_limit"];
    $start = $_POST["start_date"];
    $end = $_POST["end_date"];

    if ($code !== "") {
        if (strtotime($end) <= strtotime($start)) {
            $error = "Ngày hết hạn phải sau ngày bắt đầu!";
        } else {
            $sql = "INSERT INTO vouchers (voucher_code, discount_type, discount_value, min_order_value, usage_limit, start_date, end_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiiss", $code, $type, $val, $min, $limit, $start, $end);
                    
            if ($stmt->execute()) {
                $success = "Thêm mã giảm giá thành công!";
            } else {
                $error = "Lỗi thêm: " . $conn->error;
            }
        }
    }
}
/* ===== SỬA MÃ GIẢM GIÁ ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_edit_voucher"])) {
    // Bắt đúng biến id_voucher từ form HTML editModal
    $id = (int)$_POST["id_voucher"]; 
    $code = strtoupper(trim($_POST["voucher_code"]));
    $type = $_POST["discount_type"];
    $val = (int)$_POST["discount_value"];
    $min = (int)$_POST["min_order_value"];
    $limit = (int)$_POST["usage_limit"];
    $start = $_POST["start_date"];
    $end = $_POST["end_date"];

    if ($code !== "") {
        if (strtotime($end) <= strtotime($start)) {
            $error = "Ngày hết hạn phải sau ngày bắt đầu!";
        } else {
            $sql = "UPDATE vouchers SET 
                    voucher_code=?, 
                    discount_type=?, 
                    discount_value=?, 
                    min_order_value=?, 
                    usage_limit=?, 
                    start_date=?, 
                    end_date=? 
                    WHERE voucher_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiissi", $code, $type, $val, $min, $limit, $start, $end, $id);
                    
            if ($stmt->execute()) {
                $success = "Cập nhật mã thành công!";
            } else {
                $error = "Lỗi cập nhật: " . $conn->error;
            }
        }
    }
}


/* ===== XÓA MÃ GIẢM GIÁ ===== */
if (isset($_POST["btn_delete_voucher"])) {
    // HTML truyền lên là id_voucher
    $id = (int)$_POST["id_voucher"];

    // Kiểm tra xem đã có ai dùng mã này chưa (dựa vào used_count)
    $stmt_check = $conn->prepare("SELECT used_count FROM vouchers WHERE voucher_id=?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        if ($row["used_count"] > 0) {
            $error = "Không thể xóa vì đã có khách hàng sử dụng mã này!";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM vouchers WHERE voucher_id=?");
            $stmt_del->bind_param("i", $id);
            if ($stmt_del->execute()) {
                $success = "Xóa mã thành công!";
            } else {
                $error = "Lỗi xóa mã: " . $conn->error;
            }
        }
    }
}

/* ===== LẤY DANH SÁCH HIỂN THỊ RA BẢNG ===== */
// Lọc dữ liệu nếu Admin có dùng thanh tìm kiếm
$search = "";
if (isset($_GET["txt_search_category"])) {
    $search = trim($_GET["txt_search_category"]);
    $sql_select = "SELECT * FROM vouchers WHERE voucher_code LIKE '%$search%' ORDER BY voucher_id DESC";
} else {
    $sql_select = "SELECT * FROM vouchers ORDER BY voucher_id DESC";
}
// Biến $result này sẽ được dùng cho vòng lặp while ở dưới HTML
$result = $conn->query($sql_select);
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
                <li><a href="categories.php">Quản lý danh mục sản phẩm</a></li>
                <li><a href="vouchers.php" class ="active">Quản lý mã giảm giá</a></li>
                <li><a href="products.php">Quản lý sản phẩm</a></li>
                <li><a href="orders.php">Quản lý hóa đơn</a></li>
                <li><a href="statistics.php">Thống kê</a></li>
            </ul>
        </aside>

        <main class="content">

            <header class="topbar">
                <h1>QUẢN LÝ MÃ GIẢM GIÁ</h1>
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
                        + Thêm MÃ GIẢM GIÁ MỚI
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
                                placeholder="Tên mã giảm giá...">
                        </div>
                        <button class="btn btn-primary">Lọc</button>
                        <a href="vouchers.php" class="btn btn-delete">Reset</a>
                    </form>
                </div>


                <div class="table-wrap">
    <h3>Danh sách mã giảm giá (<?= $result->num_rows ?>)</h3>
    <!-- <button class="btn btn-add" onclick="openAddModal()">+ Thêm mã mới</button> -->
    <br><br>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mã giảm giá</th>
                <th>Loại giảm giá</th>
                <th>Giá trị giảm</th>
                <th>Đơn tối thiểu</th>
                <th>Đã dùng / Tổng</th>
                <th>Ngày bắt đầu</th>
                <th>Ngày hết hạn</th>
                <th>Hành động</th> </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="center"><?= $row["voucher_id"] ?? $row["id"] ?></td>
                    <td class="left"><strong><?= $row["voucher_code"] ?></strong></td>
                    <td class="center">
                        <?= $row["discount_type"] == 'fixed' ? 'Tiền mặt (VNĐ)' : 'Phần trăm (%)' ?>
                    </td>
                    <td class="center"><?= number_format($row["discount_value"]) ?></td>
                    <td class="center"><?= number_format($row["min_order_value"]) ?></td>
                    <td class="center"><?= $row["used_count"] ?> / <?= $row["usage_limit"] ?></td>
                    <td class="center"><?= date('d/m/Y H:i', strtotime($row["start_date"])) ?></td>
                    <td class="center"><?= date('d/m/Y H:i', strtotime($row["end_date"])) ?></td>
                    <td class="center">
                        <button class="btn btn-edit"
                            onclick="openEditModal(
                                <?= $row['voucher_id'] ?? $row['id'] ?>, 
                                '<?= $row['voucher_code'] ?>', 
                                '<?= $row['discount_type'] ?>', 
                                <?= $row['discount_value'] ?>, 
                                <?= $row['min_order_value'] ?>, 
                                <?= $row['usage_limit'] ?>, 
                                '<?= date('Y-m-d\TH:i', strtotime($row['start_date'])) ?>', 
                                '<?= date('Y-m-d\TH:i', strtotime($row['end_date'])) ?>'
                            )">
                            Sửa
                        </button>

                        <form method="POST" style="display:inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mã này?')">
                            <input type="hidden" name="id_voucher" value="<?= $row['voucher_id'] ?? $row['id'] ?>">
                            <button name="btn_delete_voucher" class="btn btn-delete" <?= $row["used_count"] > 0 ? "disabled" : "" ?>>
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

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h3>Thêm mã giảm giá mới</h3>
        <form method="POST">
            <div class="form-group">
                <label>Mã Code</label>
                <input type="text" name="voucher_code" required style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Loại giảm giá</label>
                <select name="discount_type" required>
                    <option value="fixed">Giảm theo tiền mặt (VNĐ)</option>
                    <option value="percent">Giảm theo phần trăm (%)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Giá trị giảm</label>
                <input type="number" name="discount_value" required min="1">
            </div>
            <div class="form-group">
                <label>Áp dụng cho đơn tối thiểu (VNĐ)</label>
                <input type="number" name="min_order_value" value="0" required min="0">
            </div>
            <div class="form-group">
                <label>Số lượng mã phát hành</label>
                <input type="number" name="usage_limit" value="100" required min="1">
            </div>
            <div class="form-group">
                <label>Ngày bắt đầu</label>
                <input type="datetime-local" name="start_date" required>
            </div>
            <div class="form-group">
                <label>Ngày hết hạn</label>
                <input type="datetime-local" name="end_date" required>
            </div>
            <button name="btn_add_voucher" class="btn btn-add">Lưu mã giảm giá</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h3>Sửa mã giảm giá</h3>
        <form method="POST">
            <input type="hidden" name="id_voucher" id="edit_id">
            
            <div class="form-group">
                <label>Mã Code</label>
                <input type="text" name="voucher_code" id="edit_code" required style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Loại giảm giá</label>
                <select name="discount_type" id="edit_type" required>
                    <option value="fixed">Giảm theo tiền mặt (VNĐ)</option>
                    <option value="percent">Giảm theo phần trăm (%)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Giá trị giảm</label>
                <input type="number" name="discount_value" id="edit_value" required min="1">
            </div>
            <div class="form-group">
                <label>Áp dụng cho đơn tối thiểu (VNĐ)</label>
                <input type="number" name="min_order_value" id="edit_min" required min="0">
            </div>
            <div class="form-group">
                <label>Số lượng mã phát hành</label>
                <input type="number" name="usage_limit" id="edit_limit" required min="1">
            </div>
            <div class="form-group">
                <label>Ngày bắt đầu</label>
                <input type="datetime-local" name="start_date" id="edit_start" required>
            </div>
            <div class="form-group">
                <label>Ngày hết hạn</label>
                <input type="datetime-local" name="end_date" id="edit_end" required>
            </div>

            <button name="btn_edit_voucher" class="btn btn-edit">Cập nhật mã</button>
        </form>
    </div>
</div>

<script>
    // Xử lý Modal Thêm
    function openAddModal() {
        document.getElementById('addModal').style.display = 'block';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    // Xử lý Modal Sửa
    function openEditModal(id, code, type, value, min, limit, start, end) {
        // Gán dữ liệu vào các ô input trong Modal Sửa
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_code').value = code;
        document.getElementById('edit_type').value = type;
        document.getElementById('edit_value').value = value;
        document.getElementById('edit_min').value = min;
        document.getElementById('edit_limit').value = limit;
        document.getElementById('edit_start').value = start;
        document.getElementById('edit_end').value = end;
        
        // Hiển thị Modal
        document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Click ra ngoài vùng đen để đóng modal
    window.onclick = function(event) {
        if (event.target == document.getElementById('addModal')) {
            closeAddModal();
        }
        if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    }
</script>
</body>

</html>