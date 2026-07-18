-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 16, 2026 at 06:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tam_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled','Completed') DEFAULT 'Pending',
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_email`, `resource_id`, `purpose`, `start_datetime`, `end_datetime`, `status`, `admin_remarks`, `created_at`, `updated_at`) VALUES
(2, 'mikmik@fit.edu.ph', 2, 'lalaro po :>', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 'Approved', NULL, '2026-07-15 16:37:17', '2026-07-15 16:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `specifications` text DEFAULT NULL,
  `status` enum('Available','Reserved','Out for Repair','Unavailable') DEFAULT 'Available',
  `quantity` int(11) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`id`, `name`, `description`, `category`, `specifications`, `status`, `quantity`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 'Mac Mini M2', 'Apple Mac Mini with 16GB RAM, 512GB SSD', 'Hardware', NULL, 'Available', 5, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(2, 'VR Headset Oculus Quest 2', 'Virtual Reality headset for immersive experiences', 'Hardware', '', 'Available', 2, NULL, '2026-07-12 12:53:09', '2026-07-15 16:39:42'),
(3, 'IoT Development Kit', 'Arduino and Raspberry Pi based IoT learning kit', 'Equipment', NULL, 'Available', 8, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(4, 'Innovation Center Room 201', 'Conference room with smartboard and 4K display', 'Room', NULL, 'Available', 1, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(5, 'COR Lab Station', 'Computer on Request lab station with dual monitors', 'Equipment', NULL, 'Available', 12, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(6, '3D Printer', 'Ultimaker 3D printer for prototyping', 'Hardware', NULL, 'Available', 2, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(7, 'Innovation Center Room 202', 'Collaborative workspace with whiteboard', 'Room', NULL, 'Available', 1, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `role` enum('student','admin') NOT NULL DEFAULT 'student',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`email`, `password`, `first_name`, `last_name`, `student_number`, `role`, `last_login`, `created_at`, `updated_at`) VALUES
('admin@fit.edu.ph', '$2y$10$FTaQdYu/1ClJuJjHe.ML1Oi.KftR0xKO8rjy1TBXUT7wvK.9OE7Ee', 'Admin', 'User', '', 'admin', '2026-07-15 16:38:39', '2026-07-15 11:14:57', '2026-07-15 16:38:39'),
('mikmik@fit.edu.ph', '$2y$10$Nch5GJcVtfWH.gJ17ru3w.YgIoLAbc8WbZCjjyson1g21Bd5MuMRu', 'Mik', 'Dela Rosa', '202412345', 'student', '2026-07-15 16:31:43', '2026-07-15 04:08:30', '2026-07-15 16:31:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_dates` (`start_datetime`,`end_datetime`),
  ADD KEY `idx_user_status` (`user_email`,`status`),
  ADD KEY `idx_resource_status` (`resource_id`,`status`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`email`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_email`) REFERENCES `users` (`email`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
