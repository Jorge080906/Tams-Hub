-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2026 at 05:21 PM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`id`, `name`, `description`, `category`, `specifications`, `status`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 'Mac Mini M2', 'Apple Mac Mini with 16GB RAM, 512GB SSD', 'Hardware', NULL, 'Available', 5, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(2, 'VR Headset Oculus Quest 2', 'Virtual Reality headset for immersive experiences', 'Hardware', '', 'Available', 2, '2026-07-12 12:53:09', '2026-07-15 16:39:42'),
(3, 'IoT Development Kit', 'Arduino and Raspberry Pi based IoT learning kit', 'Equipment', NULL, 'Available', 8, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(4, 'Innovation Center Room 201', 'Conference room with smartboard and 4K display', 'Room', NULL, 'Available', 1, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(5, 'COR Lab Station', 'Computer on Request lab station with dual monitors', 'Equipment', NULL, 'Available', 12, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(6, '3D Printer', 'Ultimaker 3D printer for prototyping', 'Hardware', NULL, 'Available', 2, '2026-07-12 12:53:09', '2026-07-12 12:53:09'),
(7, 'Innovation Center Room 202', 'Collaborative workspace with whiteboard', 'Room', NULL, 'Available', 1, '2026-07-12 12:53:09', '2026-07-12 12:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `room` varchar(50) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `semester` varchar(50) DEFAULT '1st Semester 2024-2025',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `subject_code`, `subject_name`, `room`, `day_of_week`, `start_time`, `end_time`, `semester`, `status`, `created_at`, `updated_at`) VALUES
(1, 'AUTOMATA', 'Automata Theory', 'FIT608', 'Tuesday', '10:00:00', '12:00:00', '1st Semester 2024-2025', 'Active', '2026-07-18 15:12:47', '2026-07-18 15:12:47'),
(2, 'PURPOSIVE', 'Purposive Communication', 'FIT609', 'Monday', '08:00:00', '10:00:00', '1st Semester 2024-2025', 'Active', '2026-07-18 15:12:47', '2026-07-18 15:12:47'),
(3, 'PYTHON', 'Python', 'FIT610', 'Wednesday', '13:00:00', '15:00:00', '1st Semester 2024-2025', 'Active', '2026-07-18 15:12:47', '2026-07-18 15:12:47'),
(4, 'TECHNO', 'Technopreneurship', 'FIT611', 'Thursday', '10:00:00', '12:00:00', '1st Semester 2024-2025', 'Active', '2026-07-18 15:12:47', '2026-07-18 15:12:47'),
(5, 'APPDEV', 'Applications Development', 'FIT612', 'Friday', '08:00:00', '10:00:00', '1st Semester 2024-2025', 'Active', '2026-07-18 15:12:47', '2026-07-18 15:12:47');

-- --------------------------------------------------------

--
-- Table structure for table `student_schedules`
--

CREATE TABLE `student_schedules` (
  `id` int(11) NOT NULL,
  `student_email` varchar(100) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `status` enum('Enrolled','Pending Change','Change','Dropped') DEFAULT 'Enrolled',
  `requested_room` varchar(50) DEFAULT NULL,
  `requested_day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `requested_start_time` time DEFAULT NULL,
  `requested_end_time` time DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('admin@fit.edu.ph', '$2y$10$6TkQteqjHB30bn3sK4j9GeCJexs7U4HaJ2n2y3Fd5Z2Vug3btV9Rq', 'Admin', 'User', '000000001', 'admin', '2026-07-18 15:15:41', '2026-07-18 15:14:20', '2026-07-18 15:16:33');

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
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule` (`subject_code`,`room`,`day_of_week`,`start_time`,`semester`);

--
-- Indexes for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_schedule` (`student_email`,`schedule_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_status` (`status`);

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
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_schedules`
--
ALTER TABLE `student_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_email`) REFERENCES `users` (`email`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD CONSTRAINT `fk_student_schedules_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_schedules_student` FOREIGN KEY (`student_email`) REFERENCES `users` (`email`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
