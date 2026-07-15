-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 15, 2026 at 05:49 AM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'VR Headset Oculus Quest 2', 'Virtual Reality headset for immersive experiences', 'Hardware', NULL, 'Available', 3, NULL, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
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
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_email`,`action`),
  ADD KEY `idx_created` (`created_at`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_email`) REFERENCES `users` (`email`) ON DELETE CASCADE;

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
