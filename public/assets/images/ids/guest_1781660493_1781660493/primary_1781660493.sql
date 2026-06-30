-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 13, 2026 at 06:00 AM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_rental`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `guest_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gmail` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `primary_id_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `secondary_id_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `car_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `return_time` time DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Completed','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `booking_type` enum('online','manual') COLLATE utf8mb4_general_ci DEFAULT 'online',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `discount_price` decimal(10,2) DEFAULT '0.00',
  `down_payment` decimal(10,2) DEFAULT '0.00',
  `extension_price` decimal(10,2) DEFAULT '0.00',
  `extension_hours` int DEFAULT '0',
  `proof_billing_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_payments`
--

CREATE TABLE `booking_payments` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `daily_rent` decimal(10,2) DEFAULT '0.00',
  `carwash` decimal(10,2) DEFAULT '0.00',
  `extension_fee` decimal(10,2) DEFAULT '0.00',
  `delivery_fee` decimal(10,2) DEFAULT '0.00',
  `jer_delivery_fee` decimal(10,2) DEFAULT '0.00',
  `pickup_fee` decimal(10,2) DEFAULT '0.00',
  `jer_pickup_fee` decimal(10,2) DEFAULT '0.00',
  `fuel` decimal(10,2) DEFAULT '0.00',
  `driver_fee` decimal(10,2) DEFAULT '0.00',
  `damage_fee` decimal(10,2) DEFAULT '0.00',
  `agent_fee` decimal(10,2) DEFAULT '0.00',
  `others` decimal(10,2) DEFAULT '0.00',
  `total_gross` decimal(10,2) DEFAULT '0.00',
  `total_net` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remitted_amount` decimal(10,2) DEFAULT '0.00',
  `remittance_date` date DEFAULT NULL,
  `owner_balance` decimal(10,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `brand` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fuel_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plate_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price_per_day` decimal(10,2) DEFAULT NULL,
  `status` enum('Available','Active','Maintenance','Not Available','Reserved') COLLATE utf8mb4_general_ci DEFAULT 'Available',
  `transmission` enum('Manual','Automatic') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Manual',
  `capacity` int DEFAULT '5',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `operator_rate` decimal(10,2) DEFAULT '0.00',
  `tie_up_rate` decimal(10,2) DEFAULT '0.00',
  `extension_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `color` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`id`, `user_id`, `brand`, `model`, `type`, `fuel_type`, `plate_number`, `image_path`, `price_per_day`, `status`, `transmission`, `capacity`, `created_at`, `operator_rate`, `tie_up_rate`, `extension_price`, `color`) VALUES
(3, 7, 'HatchBack', 'Wigo/Mirage', 'SUV', 'green', '1234', '1774237284_651194120_1205869804701000_8633291639692457117_n.jpg', 1500.00, 'Active', 'Manual', 5, '2026-03-23 03:39:40', 1200.00, 300.00, 100.00, 'Red'),
(4, 7, 'Sedan', 'Honda City', 'SUV', 'diesel', '123', '1774237334_653463773_915907377693009_8487592824306674200_n.jpg', 2000.00, 'Available', 'Manual', 5, '2026-03-23 03:42:14', 0.00, 0.00, 0.00, 'Blue'),
(5, 7, 'Sedan', 'Vios/Mirage/MG5', 'SUV', NULL, '1212', '1774237395_653874605_1404859887993080_1903131416981550368_n.jpg', 1800.00, 'Available', 'Manual', 5, '2026-03-23 03:43:15', 0.00, 0.00, 0.00, 'Orange'),
(6, 7, 'SUV', 'Fortuner/Montero', NULL, NULL, '12312', '1774237457_654000464_1256282399979877_1561735839736806923_n.jpg', 3200.00, 'Available', 'Manual', 5, '2026-03-23 03:44:17', 0.00, 0.00, 0.00, 'White'),
(7, 7, 'Pick-Up', 'Navara/Hilux', NULL, NULL, '123412', '1774237501_654059922_1654245925745876_6811578096547993538_n.jpg', 3200.00, 'Available', 'Manual', 5, '2026-03-23 03:45:01', 0.00, 0.00, 0.00, 'Black'),
(8, 7, 'Urban', 'Navaro', NULL, NULL, '1312', '1774237569_653419277_1292284916130429_4945150164135400678_n.jpg', 5000.00, 'Available', 'Manual', 5, '2026-03-23 03:46:09', 0.00, 0.00, 0.00, 'Violet'),
(9, 8, 'Toyota', 'Vios', NULL, NULL, 'ABC-123', '1774360382_348335279_623734843001062_3220784779306547951_n.jpg', 1500.00, 'Available', 'Manual', 5, '2026-03-24 13:53:02', 0.00, 0.00, 0.00, 'Purple');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `review_text` text COLLATE utf8mb4_general_ci,
  `admin_reply` text COLLATE utf8mb4_general_ci,
  `review_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected','replied') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `created_at`, `reset_token`, `token_expiry`) VALUES
(7, 'Admin', 'admin@gmail.com', '$2y$10$fmIO7SFwZo0Fi.GA89fs9uhMkwnDUjsFYxXxMzVLoI1jJsuxevUdq', '123456789', 'admin', '2026-03-24 04:08:27', NULL, NULL),
(8, 'Operator', 'operator@gmail.com', '$2y$10$M6LQaV7Z8p6nJ48yULnbueOnyYCLasoGfxjNdTb0oh7QJBhZ0ZRn6', '912345678', 'operator', '2026-03-14 03:36:45', NULL, NULL),
(9, 'User', 'user@gmail.com', '$2y$10$H574MWcEDysEmMn9C7tNfuwp1/9rShWBaUOUNkBnZdaaYOIQ3IbMi', '912345678', 'user', '2026-03-13 23:58:39', NULL, NULL),
(10, 'Operator1', 'operator1@gmail.com', '$2y$10$M6LQaV7Z8p6nJ48yULnbueOnyYCLasoGfxjNdTb0oh7QJBhZ0ZRn6', '912345678', 'operator', '2026-03-14 03:36:45', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `bookings_ibfk_2` (`car_id`);

--
-- Indexes for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking` (`booking_id`),
  ADD UNIQUE KEY `idx_booking_id` (`booking_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `booking_payments`
--
ALTER TABLE `booking_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD CONSTRAINT `booking_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
