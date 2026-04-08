<?php
session_start();
include "../connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Bạn cần đăng nhập để lưu mã!"]);
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['id'])) {
    echo json_encode(["success" => false, "message" => "Không tìm thấy thông tin mã giảm giá!"]);
    exit();
}

$voucher_id = intval($_POST['id']);

// 1. Kiểm tra mã tồn tại không và trạng thái
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_id = ?");
$stmt->bind_param("i", $voucher_id);
$stmt->execute();
$voucher = $stmt->get_result()->fetch_assoc();

if (!$voucher) {
    echo json_encode(["success" => false, "message" => "Mã giảm giá không tồn tại!"]);
    exit();
}

if (strtotime($voucher['start_date']) > time()) {
    echo json_encode(["success" => false, "message" => "Chưa đến thời gian lưu mã này!"]);
    exit();
}

if ($voucher['used_count'] >= $voucher['usage_limit']) {
    echo json_encode(["success" => false, "message" => "Rất tiếc mã này đã hết lượt!"]);
    exit();
}

// 2. Kiểm tra xem khách này đã lưu mã này chưa
$check_sql = "SELECT id FROM user_vouchers WHERE user_id = ? AND voucher_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $user_id, $voucher_id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Bạn đã lưu mã này trong ví rồi!"]);
    exit();
}

// 3. Tiến hành cất vào ví
$conn->begin_transaction();
try {
    // Lưu vào user_vouchers
    $insert_sql = "INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ii", $user_id, $voucher_id);
    
    if ($stmt->execute()) {
        // Cập nhật lượt lưu
        $update_stmt = $conn->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE voucher_id = ?");
        $update_stmt->bind_param("i", $voucher_id);
        $update_stmt->execute();

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Đã lưu mã giảm giá thành công vào ví!"]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Lỗi: " . $e->getMessage()]);
}
?>
