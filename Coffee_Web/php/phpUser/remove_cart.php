<?php
// remove_cart.php  –  đặt cùng thư mục với cart.php
session_start();
include "../connect.php";

header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user") {
    echo json_encode(["status" => "error", "msg" => "Chưa đăng nhập"]);
    exit;
}

$userId    = (int)$_SESSION["user_id"];
$productId = (int)($_POST["product_id"] ?? 0);

if ($productId <= 0) {
    echo json_encode(["status" => "error", "msg" => "ID không hợp lệ"]);
    exit;
}

// Xóa khỏi DB
$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();

// Xóa khỏi session
unset($_SESSION["cart"][$productId]);

echo json_encode(["status" => "success"]);