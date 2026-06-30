-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 06:44 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

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
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gmail` varchar(100) DEFAULT NULL,
  `primary_id_path` varchar(255) DEFAULT NULL,
  `secondary_id_path` varchar(255) DEFAULT NULL,
  `car_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `return_time` time DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Approved','Completed','Cancelled') DEFAULT 'Pending',
  `booking_type` enum('online','manual') DEFAULT 'online',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `guest_name`, `phone_number`, `gmail`, `primary_id_path`, `secondary_id_path`, `car_id`, `start_date`, `end_date`, `pickup_time`, `return_time`, `total_price`, `status`, `booking_type`, `created_at`) VALUES
(6, NULL, 'Sablon Rona', NULL, NULL, NULL, NULL, 3, '2026-03-23', '0000-00-00', NULL, NULL, 4500.00, 'Confirmed', 'manual', '2026-03-23 09:41:10'),
(7, 9, NULL, NULL, NULL, NULL, NULL, 8, '2026-03-24', '2026-03-26', NULL, NULL, 15000.00, 'Completed', 'online', '2026-03-24 04:14:55'),
(8, 9, NULL, NULL, NULL, NULL, NULL, 9, '2026-03-24', '2026-03-25', NULL, NULL, 3000.00, 'Cancelled', 'online', '2026-03-24 15:01:35'),
(9, 9, NULL, NULL, NULL, NULL, NULL, 8, '2026-03-24', '2026-03-25', '07:00:00', '07:00:00', 10000.00, 'Cancelled', 'online', '2026-03-24 15:05:02'),
(10, NULL, 'dasdasdasdas', '12312312', 'sadas@gmail.com', '1774366860_primary.jpg', '1774366860_secondary.png', 3, '2026-03-23', '2026-03-28', NULL, NULL, 9000.00, 'Cancelled', 'manual', '2026-03-24 15:41:00'),
(11, 9, NULL, NULL, NULL, NULL, NULL, 9, '2026-03-25', '2026-03-26', NULL, NULL, 3000.00, 'Cancelled', 'online', '2026-03-24 17:23:58'),
(12, 9, NULL, NULL, NULL, NULL, NULL, 8, '2026-03-27', '2026-03-27', NULL, NULL, 5000.00, 'Cancelled', 'online', '2026-03-26 13:17:54'),
(13, 9, NULL, NULL, NULL, NULL, NULL, 9, '2026-03-26', '2026-03-31', '22:18:00', '22:18:00', 9000.00, 'Cancelled', 'online', '2026-03-26 14:18:24'),
(14, 9, NULL, NULL, NULL, 'PRI_9_1774535250.jpg', 'SEC_9_1774535250.jpg', 9, '2026-03-26', '2026-03-31', '23:27:00', '23:27:00', 9000.00, 'Cancelled', 'online', '2026-03-26 14:27:30'),
(15, 9, NULL, NULL, NULL, 'PRI_9_1774566599.jpg', 'SEC_9_1774566599.jpg', 8, '2026-03-27', '2026-03-28', '08:09:00', '13:13:00', 10000.00, 'Cancelled', 'online', '2026-03-26 23:09:59'),
(16, 9, NULL, NULL, NULL, 'PRI_9_1774567397.png', 'SEC_9_1774567397.png', 7, '2026-03-29', '2026-03-30', '08:01:00', '00:23:00', 6400.00, 'Approved', 'online', '2026-03-26 23:23:17'),
(17, NULL, 'Gunce Blaze Arianne', '09876553235', 'gunce@gmail.com', '1774567545_primary.png', '1774567545_secondary.png', 5, '2026-03-28', '2026-03-30', '07:30:00', '12:29:00', 5400.00, 'Approved', 'manual', '2026-03-26 23:25:45'),
(18, 9, NULL, NULL, NULL, 'PRI_9_1774578227.png', 'SEC_9_1774578227.png', 6, '2026-03-27', '2026-03-28', '11:00:00', '22:23:00', 6400.00, 'Approved', 'online', '2026-03-27 02:23:47'),
(19, 9, NULL, NULL, NULL, 'PRI_9_1774592014.png', 'SEC_9_1774592014.png', 8, '2026-03-27', '2026-03-29', '09:11:00', '06:00:00', 15000.00, 'Cancelled', 'online', '2026-03-27 06:13:34');

-- --------------------------------------------------------

--
-- Table structure for table `booking_payments`
--

CREATE TABLE `booking_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `daily_rent` decimal(10,2) DEFAULT 0.00,
  `carwash` decimal(10,2) DEFAULT 0.00,
  `extension_fee` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `jer_delivery_fee` decimal(10,2) DEFAULT 0.00,
  `pickup_fee` decimal(10,2) DEFAULT 0.00,
  `jer_pickup_fee` decimal(10,2) DEFAULT 0.00,
  `fuel` decimal(10,2) DEFAULT 0.00,
  `driver_fee` decimal(10,2) DEFAULT 0.00,
  `damage_fee` decimal(10,2) DEFAULT 0.00,
  `agent_fee` decimal(10,2) DEFAULT 0.00,
  `others` decimal(10,2) DEFAULT 0.00,
  `total_gross` decimal(10,2) DEFAULT 0.00,
  `total_net` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remitted_amount` decimal(10,2) DEFAULT 0.00,
  `remittance_date` date DEFAULT NULL,
  `owner_balance` decimal(10,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `price_per_day` decimal(10,2) DEFAULT NULL,
  `status` enum('Available','Rented','Maintenance','Not Available','Reserved') DEFAULT 'Available',
  `transmission` enum('Manual','Automatic') NOT NULL DEFAULT 'Manual',
  `capacity` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`id`, `user_id`, `brand`, `model`, `plate_number`, `image_path`, `price_per_day`, `status`, `transmission`, `capacity`, `created_at`) VALUES
(3, 7, 'HatchBack', 'Wigo/Mirage', '1234', '1774237284_651194120_1205869804701000_8633291639692457117_n.jpg', 1500.00, 'Available', 'Manual', 5, '2026-03-23 03:39:40'),
(4, 7, 'Sedan', 'Honda City', '123', '1774237334_653463773_915907377693009_8487592824306674200_n.jpg', 2000.00, 'Available', 'Manual', 5, '2026-03-23 03:42:14'),
(5, 7, 'Sedan', 'Vios/Mirage/MG5', '1212', '1774237395_653874605_1404859887993080_1903131416981550368_n.jpg', 1800.00, 'Rented', 'Manual', 5, '2026-03-23 03:43:15'),
(6, 7, 'SUV', 'Fortuner/Montero', '12312', '1774237457_654000464_1256282399979877_1561735839736806923_n.jpg', 3200.00, 'Rented', 'Manual', 5, '2026-03-23 03:44:17'),
(7, 7, 'Pick-Up', 'Navara/Hilux', '123412', '1774237501_654059922_1654245925745876_6811578096547993538_n.jpg', 3200.00, 'Rented', 'Manual', 5, '2026-03-23 03:45:01'),
(8, 7, 'Urban', 'Navaro', '1312', '1774237569_653419277_1292284916130429_4945150164135400678_n.jpg', 5000.00, 'Available', 'Manual', 5, '2026-03-23 03:46:09'),
(9, 8, 'Toyota', 'Vios', 'ABC-123', '1774360382_348335279_623734843001062_3220784779306547951_n.jpg', 1500.00, 'Available', 'Manual', 5, '2026-03-24 13:53:02');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `created_at`) VALUES
(7, 'Admin', 'admin@gmail.com', '$2y$10$fmIO7SFwZo0Fi.GA89fs9uhMkwnDUjsFYxXxMzVLoI1jJsuxevUdq', '123456789', 'admin', '2026-03-24 04:08:27'),
(8, 'Operator', 'operator@gmail.com', '$2y$10$M6LQaV7Z8p6nJ48yULnbueOnyYCLasoGfxjNdTb0oh7QJBhZ0ZRn6', '912345678', 'operator', '2026-03-14 03:36:45'),
(9, 'User', 'user@gmail.com', '$2y$10$H574MWcEDysEmMn9C7tNfuwp1/9rShWBaUOUNkBnZdaaYOIQ3IbMi', '912345678', 'user', '2026-03-13 23:58:39'),
(10, 'Operator1', 'operator1@gmail.com', '$2y$10$M6LQaV7Z8p6nJ48yULnbueOnyYCLasoGfxjNdTb0oh7QJBhZ0ZRn6', '912345678', 'operator', '2026-03-14 03:36:45'),
(13, 'Mary Grace Cubos', 'marygracecubos@gmail.com', '$2y$10$CJHSDKOEfxjM1LzHNJHLQe.e.sTwqqEZyj8HBtrQzkkeVODN/qlFK', '', 'user', '2026-03-26 14:52:21');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `booking_payments`
--
ALTER TABLE `booking_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD CONSTRAINT `booking_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
