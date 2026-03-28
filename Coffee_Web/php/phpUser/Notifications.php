<?php
session_start();
include "../connect.php";

// Nếu chưa có bảng notification thì tạo
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('success','error','info') NOT NULL DEFAULT 'info',
  product_name VARCHAR(255) DEFAULT NULL,
  product_price DECIMAL(10,2) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Chỉ cho phép user đã đăng nhập
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "msg" => "Chưa đăng nhập"]);
    exit;
}

$userId = (int)$_SESSION["user_id"];

/* ===================================================
   GET — Load danh sách thông báo chưa đọc
   =================================================== */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $rs = mysqli_query($conn, "
        SELECT id, message, type, product_name, product_price, created_at
        FROM notifications
        WHERE user_id = $userId
          AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 50
    ");

    $data = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $data[] = [
            "id"           => (int)$row["id"],
            "message"      => $row["message"],
            "type"         => $row["type"],
            "productName"  => $row["product_name"],
            "productPrice" => (float)$row["product_price"],
            "timestamp"    => date("H:i:s", strtotime($row["created_at"])),
        ];
    }

    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

/* ===================================================
   POST — Thêm / Đánh dấu đã đọc
   =================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    /* ---------- Thêm thông báo mới ---------- */
    if ($action === "add") {
        $message      = mysqli_real_escape_string($conn, $_POST["message"]      ?? "");
        $type         = mysqli_real_escape_string($conn, $_POST["type"]         ?? "info");
        $productName  = mysqli_real_escape_string($conn, $_POST["product_name"] ?? "");
        $productPrice = (float)($_POST["product_price"] ?? 0);

        mysqli_query($conn, "
            INSERT INTO notifications
                (user_id, message, type, product_name, product_price, is_read, created_at)
            VALUES
                ($userId, '$message', '$type', '$productName', $productPrice, 0, NOW())
        ");

        $newId = mysqli_insert_id($conn);

        echo json_encode([
            "status" => "success",
            "notification" => [
                "id"           => $newId,
                "message"      => $_POST["message"],
                "type"         => $_POST["type"],
                "productName"  => $_POST["product_name"],
                "productPrice" => $productPrice,
                "timestamp"    => date("H:i:s"),
            ],
        ]);
        exit;
    }

    /* ---------- Đánh dấu đã đọc (ẩn khỏi panel, giữ trong DB) ---------- */
    if ($action === "read") {
        $id = (int)($_POST["id"] ?? 0);

        mysqli_query($conn, "
            UPDATE notifications
            SET is_read = 1
            WHERE id = $id
              AND user_id = $userId
        ");

        echo json_encode(["status" => "success"]);
        exit;
    }
}

echo json_encode(["status" => "error", "msg" => "Yêu cầu không hợp lệ"]);