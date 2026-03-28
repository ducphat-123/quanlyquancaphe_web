-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 28, 2026 lúc 10:02 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanlyquancaphe_web`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cart`
--

CREATE TABLE `cart` (
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `cart`
--

INSERT INTO `cart` (`user_id`, `product_id`, `quantity`, `updated_at`) VALUES
(14, 1, 1, '2026-03-28 16:00:51'),
(14, 2, 1, '2026-03-28 16:00:52');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Cà phê', '2026-01-15 16:18:43'),
(2, 'Trà', '2026-01-15 16:18:43'),
(3, 'Sinh tố', '2026-01-15 16:18:43'),
(4, 'Bánh Ngọt', '2026-01-15 16:18:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `name`, `category_id`, `price`, `quantity`, `description`, `image`, `status`, `created_at`) VALUES
(1, 'Cà phê đen', 1, 25000.00, 48, 'Cà phê đen truyền thống', '69691485c7dcf.png', 'active', '2026-01-15 16:18:43'),
(2, 'Cà phê sữa', 1, 30000.00, 77, 'Cà phê sữa đá Việt Nam', '6969147ccedbf.png', 'active', '2026-01-15 16:18:43'),
(3, 'Trà Đào Cam Sả', 2, 35000.00, 0, 'Trà trái cây thơm mát', '6969146ed1338.png', 'active', '2026-01-15 16:18:43'),
(5, 'Bánh Su Kem', 4, 30000.00, 122, 'ngon', '6969de2cc4cf3.png', 'active', '2026-01-16 06:43:56'),
(8, 'Bánh Chuối', 4, 30000.00, 100, 'siu ngon', '696a75bd230ef.png', 'active', '2026-01-16 17:30:37'),
(9, 'Bánh Tiramisu', 4, 40000.00, 100, 'siu ngon', '696a75d9c2011.png', 'active', '2026-01-16 17:31:05'),
(10, 'Trà Sen', 2, 45000.00, 100, 'siu ngon', '696a75faecce5.png', 'active', '2026-01-16 17:31:38'),
(11, 'Bạc Xỉu Đá', 1, 30000.00, 100, 'siu ngon', '696a761a4a6b8.png', 'active', '2026-01-16 17:32:10'),
(12, 'Sinh Tố Bơ', 3, 30000.00, 100, 'siu ngon', '696a76fe22574.png', 'active', '2026-01-16 17:35:58'),
(13, 'Sinh Tố Xoài', 3, 30000.00, 100, 'siu ngon', '696a77173d8cd.png', 'active', '2026-01-16 17:36:23'),
(15, 'caf phe', 1, 100000.00, 0, '', '', 'active', '2026-01-17 07:04:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `email` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `email`, `address`) VALUES
(9, 'user01', 'user01', '$2y$10$.JlZnbPGg3saAtDVT4J1nOYc9Z5GmDruR7MoaUjNBholtGlx2.5g6', 'user', 'user01@gmail.com', 'Hà Nội - Việt Nam'),
(10, 'admin01', 'admin01', '$2y$10$tfj0iIUg5npnaXpiB5kXQ.COcaVc964B8twwVyMKUq3u1/J.IP2f2', 'admin', 'admin01@gmail.com', NULL),
(13, 'chuong', 'hvc', '$2y$10$DYwc42m8GwE/nosyf3d0BuzLc.4oHwSX1UElHepgQN3Gp8tb0SYsK', 'user', 'chuong@gmail.com', NULL),
(14, 'Trần Đức Phát', 'phat', '$2y$10$ln6MIOuohU6A6LkwVJso2.WfrHAGMe.s/kt9OePNdM3299SFSQT7m', 'user', 'phatnha1702@gmail.com', 'Hà Nội - Việt Nam');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_invoices`
--

CREATE TABLE `user_invoices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_price` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `user_invoices`
--

INSERT INTO `user_invoices` (`id`, `user_id`, `product_name`, `quantity`, `total_price`, `created_at`) VALUES
(1, 9, 'Cà phê đen', 1, 25000, '2026-01-16 11:55:50'),
(4, 9, 'Cà phê sữa', 3, 90000, '2026-01-16 11:57:21'),
(16, 9, 'Cà phê sữa', 2, 60000, '2026-01-16 13:03:17'),
(17, 13, 'Cà phê đen', 6, 150000, '2026-01-16 13:24:15'),
(18, 13, 'Cà phê sữa', 6, 180000, '2026-01-16 13:24:15'),
(19, 13, 'Cà phê sữa', 1, 30000, '2026-01-16 15:02:40'),
(20, 9, 'Cà phê sữa', 6, 180000, '2026-01-16 15:09:19'),
(21, 9, 'Cà phê đen', 4, 100000, '2026-01-16 15:09:19'),
(22, 9, 'Cà phê đen', 7, 175000, '2026-01-16 15:10:49'),
(23, 9, 'Cà phê sữa', 3, 90000, '2026-01-17 00:27:01'),
(24, 9, 'Cà phê sữa', 10, 300000, '2026-01-17 13:18:34'),
(25, 9, 'Cà phê đen', 3, 75000, '2026-01-17 13:18:34'),
(26, 9, 'Cà phê đen', 10, 250000, '2026-01-17 14:01:22'),
(27, 9, 'Cà phê đen', 1, 25000, '2026-03-08 13:12:59'),
(28, 14, 'Cà phê đen', 1, 25000, '2026-03-28 16:00:15'),
(29, 14, 'Cà phê sữa', 1, 30000, '2026-03-28 16:00:16'),
(30, 14, 'Bánh Su Kem', 1, 30000, '2026-03-28 16:00:17');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `user_invoices`
--
ALTER TABLE `user_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `user_invoices`
--
ALTER TABLE `user_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `user_invoices`
--
ALTER TABLE `user_invoices`
  ADD CONSTRAINT `user_invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
