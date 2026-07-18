-- Schedule System Database Schema for TAM-HUB
-- Run this in phpMyAdmin or MySQL CLI to create the schedule tables

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `schedules`
-- Master schedule: subject assignments to rooms/times (admin-managed)
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `room` varchar(50) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `semester` varchar(50) DEFAULT '1st Semester 2024-2025',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_schedule` (`subject_code`,`room`,`day_of_week`,`start_time`,`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `student_schedules`
-- Student enrollments in schedules (student-specific view)
--

CREATE TABLE `student_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_schedule` (`student_email`,`schedule_id`),
  KEY `idx_schedule_id` (`schedule_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Add foreign key constraints (run after tables created)
--

ALTER TABLE `student_schedules`
  ADD CONSTRAINT `fk_student_schedules_student` FOREIGN KEY (`student_email`) REFERENCES `users` (`email`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_schedules_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

--
-- Sample Data: 5 Subjects with rooms, days, and times
--

INSERT INTO `schedules` (`subject_code`, `subject_name`, `room`, `day_of_week`, `start_time`, `end_time`, `semester`, `status`) VALUES
('AUTOMATA', 'Automata Theory', 'FIT608', 'Tuesday', '10:00:00', '12:00:00', '1st Semester 2024-2025', 'Active'),
('PURPOSIVE', 'Purposive Communication', 'FIT609', 'Monday', '08:00:00', '10:00:00', '1st Semester 2024-2025', 'Active'),
('PYTHON', 'Python', 'FIT610', 'Wednesday', '13:00:00', '15:00:00', '1st Semester 2024-2025', 'Active'),
('TECHNO', 'Technopreneurship', 'FIT611', 'Thursday', '10:00:00', '12:00:00', '1st Semester 2024-2025', 'Active'),
('APPDEV', 'Applications Development', 'FIT612', 'Friday', '08:00:00', '10:00:00', '1st Semester 2024-2025', 'Active');

COMMIT;