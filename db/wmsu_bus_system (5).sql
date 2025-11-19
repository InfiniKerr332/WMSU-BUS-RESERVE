-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 02:10 AM
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
-- Database: `wmsu_bus_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_alerts`
--

CREATE TABLE `admin_alerts` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `alert_type` enum('deadline_warning','72hour_approaching') DEFAULT 'deadline_warning',
  `alert_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `deadline_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int(11) NOT NULL,
  `bus_name` varchar(50) NOT NULL,
  `plate_no` varchar(20) NOT NULL,
  `capacity` int(11) DEFAULT 30,
  `status` enum('available','unavailable') DEFAULT 'available',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`id`, `bus_name`, `plate_no`, `capacity`, `status`, `deleted`, `deleted_at`, `created_at`) VALUES
(1, 'WMSU Bus 1', 'ABC-1234', 30, 'available', 0, NULL, '2025-10-13 22:23:46'),
(2, 'WMSU Bus 2', 'XYZ-5678', 30, 'available', 0, NULL, '2025-10-13 22:23:46'),
(3, 'WMSU Bus 3', 'DEF-9012', 30, 'available', 0, NULL, '2025-10-13 22:23:46'),
(10, 'Wmsu Bus 4', 'ACD-2324', 30, 'available', 0, NULL, '2025-11-06 03:36:43'),
(11, 'Wmsu Bus 5', 'DW1-4921', 40, 'available', 0, NULL, '2025-11-06 03:37:01'),
(12, 'WMSU Bus 6', '2016', 35, 'available', 0, NULL, '2025-11-06 12:51:08'),
(13, 'WMSU Bus 7', 'YEW-2145', 45, 'available', 0, NULL, '2025-11-07 04:38:20'),
(14, 'WMSU BUS X', 'AE2-314', 35, 'available', 0, NULL, '2025-11-07 13:12:38');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `assigned_bus_id` int(11) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `status` enum('available','unavailable') DEFAULT 'available',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `assigned_bus_id`, `contact_no`, `license_no`, `status`, `deleted`, `deleted_at`, `created_at`) VALUES
(1, 'Juan Dela Cruz', 1, '09111111111', 'N01-12-123456', 'available', 0, NULL, '2025-10-13 22:23:46'),
(2, 'Pedro Santos', 2, '09222222222', 'N02-13-234567', 'available', 0, NULL, '2025-10-13 22:23:46'),
(3, 'Maria Garcia', 3, '09333333333', 'N03-14-345678', 'available', 0, NULL, '2025-10-13 22:23:46'),
(6, 'Mark cordovilla', 10, '09936692953', 'N32-2122-334543', 'available', 0, NULL, '2025-11-06 12:12:29'),
(7, 'Kurt Ortega', NULL, '09949210421', 'N13-2792-3348973', 'available', 0, NULL, '2025-11-06 23:38:22'),
(8, 'Zambales', NULL, '09928374615', 'KE2-3214-2135125', 'available', 1, '2025-11-07 06:46:21', '2025-11-07 06:46:15');

-- --------------------------------------------------------

--
-- Table structure for table `driver_assignments`
--

CREATE TABLE `driver_assignments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assignment_status` enum('pending','accepted','rejected','completed') DEFAULT 'pending',
  `notified_at` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `recipient`, `subject`, `status`, `error_message`, `created_at`) VALUES
(1, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:54:56'),
(2, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:55:08'),
(3, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:55:12'),
(4, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:55:17'),
(5, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:56:55'),
(6, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:56:58'),
(7, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'failed', 'SMTP Error: Could not authenticate.', '2025-11-19 00:57:08'),
(8, 'kerrzaragoza43@gmail.com', 'WMSU Bus System - Test Email', 'sent', NULL, '2025-11-19 01:08:06');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `token_expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('reservation','approval','rejection','cancellation','reminder','driver_assignment','verification') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bus_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `purpose` text NOT NULL,
  `destination` varchar(255) NOT NULL,
  `reservation_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `reservation_time` time NOT NULL,
  `return_time` time DEFAULT NULL,
  `passenger_count` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `bus_id`, `driver_id`, `purpose`, `destination`, `reservation_date`, `return_date`, `reservation_time`, `return_time`, `passenger_count`, `status`, `admin_remarks`, `approved_by`, `approved_at`, `reminder_sent`, `created_at`) VALUES
(1, 5, 1, 1, 'Education field trip.', '', '2025-10-16', NULL, '10:00:00', NULL, 20, 'approved', '', 4, '2025-10-14 02:20:25', 0, '2025-10-14 02:19:57'),
(2, 5, 2, 2, 'Educational field to butterfly garden', '', '2025-10-16', NULL, '14:56:00', NULL, 30, 'approved', '', 4, '2025-10-14 06:56:02', 0, '2025-10-14 06:55:38'),
(3, 6, 1, 1, 'official business meeting', 'Zamboanga City Hall', '2025-10-31', '2025-10-31', '10:00:00', '11:30:00', 20, 'approved', '', 4, '2025-10-28 00:18:04', 0, '2025-10-27 14:10:09'),
(4, 5, 2, 2, 'Business meeting', 'Zamboanga City Hall', '2025-10-31', '2025-10-31', '10:29:00', '13:30:00', 30, 'approved', '', 4, '2025-10-28 01:32:32', 0, '2025-10-28 01:30:26'),
(5, 5, 3, NULL, 'Business trip', 'Zamboanga City Hall', '2025-10-31', '2025-10-31', '10:43:00', '13:43:00', 30, 'pending', NULL, NULL, NULL, 0, '2025-10-28 01:44:17'),
(6, 5, 1, 1, 'Educational recollection for G12 students', 'Mercedes', '2025-11-07', '2025-11-07', '10:00:00', '16:00:00', 25, 'approved', 'Have a fun trip!', 4, '2025-11-04 01:20:48', 0, '2025-11-04 01:18:27'),
(7, 5, 11, 6, 'Educational field trip', 'Museum', '2025-11-10', '2025-11-10', '11:44:00', '14:44:00', 34, 'approved', '', 4, '2025-11-06 12:20:41', 0, '2025-11-06 03:45:06'),
(8, 5, 10, NULL, 'Educational', 'Museum', '2025-11-10', '2025-11-10', '11:45:00', '13:45:00', 10, 'pending', NULL, NULL, NULL, 0, '2025-11-06 03:46:14'),
(10, 5, 11, NULL, 'Official Business meeting', 'City Hall', '2025-11-11', '2025-11-11', '08:53:00', '11:54:00', 31, 'cancelled', NULL, NULL, NULL, 0, '2025-11-06 12:57:38'),
(11, 5, 10, 3, 'Educational field trip', 'Museum', '2025-11-11', '2025-11-11', '08:00:00', '12:00:00', 30, 'approved', '', 4, '2025-11-06 23:37:25', 0, '2025-11-06 23:20:24'),
(12, 5, 12, NULL, 'Educational field trip', 'City Hall', '2025-11-11', '2025-11-11', '08:00:00', '12:00:00', 35, 'pending', NULL, NULL, NULL, 0, '2025-11-06 23:35:15'),
(13, 5, 14, 1, 'Educational field trip', 'City Hall', '2025-11-11', '2025-11-11', '10:00:00', '12:00:00', 35, 'approved', '', 4, '2025-11-07 13:18:47', 0, '2025-11-07 13:17:05'),
(14, 5, 14, 7, 'dda', 'weqe', '2025-11-14', '2025-11-21', '11:34:00', '10:34:00', 35, 'approved', '', 4, '2025-11-11 01:37:14', 0, '2025-11-11 01:34:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `employee_id_image` varchar(255) DEFAULT NULL,
  `employee_id_back_image` varchar(255) DEFAULT NULL,
  `account_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `approved_by_admin` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `role` enum('student','employee','teacher','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `name`, `email`, `contact_no`, `department`, `position`, `password`, `employee_id_image`, `employee_id_back_image`, `account_status`, `email_verified`, `email_verified_at`, `verification_token`, `verification_expires`, `approved_by_admin`, `approved_at`, `rejection_reason`, `role`, `created_at`) VALUES
(4, 'WMSU', NULL, 'Administrator', 'WMSU Administrator', 'admin@wmsu.edu.ph', '09123456789', 'Administration', 'System Administrator', '$2y$10$2QzlHxcObiLkyKPuFv3oQunxAY9wfREBieNBPCr733vkTqsmsUHcK', NULL, NULL, 'approved', 0, NULL, NULL, NULL, NULL, '2025-10-27 08:29:20', NULL, 'admin', '2025-10-14 00:37:16'),
(5, 'Kerr', 'Xandrex', 'Zaragoza', 'Kerr Xandrex Zaragoza', 'ae202401442@wmsu.edu.ph', '09653188726', 'BSIT', '', '$2y$10$fFAeh//VchhKf.RNOEUIi.v3cMSw4/FwWt9v0mx8Efcn8vCbh0q.e', NULL, NULL, 'approved', 0, NULL, NULL, NULL, 4, '2025-10-28 00:19:29', NULL, 'employee', '2025-10-14 01:54:42'),
(6, 'Kerr', 'Xandrex Chua', 'Zaragoza', 'Kerr Xandrex Chua Zaragoza', 'ae202401443@wmsu.edu.ph', '09471028557', 'CCS', 'Teacher', '$2y$10$brPnuMYdtZCo.eYga7E75OBUndyDJeXqo96PjbqugPD.dYHO7VhHu', 'uploads/employee_ids/emp_front_1761571747_68ff73a35a775.png', 'uploads/employee_ids/emp_back_1761571747_68ff73a35ac76.png', 'approved', 0, NULL, NULL, NULL, 4, '2025-10-27 13:37:39', NULL, 'teacher', '2025-10-27 13:29:07'),
(7, 'Kerr', 'Xandrex Chua', 'Zaragoza', 'Kerr Xandrex Chua Zaragoza', 'ae202401444@wmsu.edu.ph', '09653188726', 'CCS', 'Employee', '$2y$10$pSA361j85Do.yW21GMMUxuWNQPxQ31VhDX06kwvJgysPyJmha3uk6', 'uploads/employee_ids/emp_front_1761572333_68ff75ed82194.png', 'uploads/employee_ids/emp_back_1761572333_68ff75ed8287e.png', 'rejected', 0, NULL, NULL, NULL, 4, '2025-11-06 12:51:53', 'Not valid.', 'employee', '2025-10-27 13:38:53'),
(8, NULL, NULL, NULL, 'Kurt  Ortega', 'ae20240214@wmsu.edu.ph', '09936437834', 'CCS', 'Employee', '$2y$10$3b9o/db1XD5FTTAXgRQAf.Sg1dugdg98LeUDBlFAbRR7uwDeqEolq', 'uploads/employee_ids/emp_front_1762497872_690d955034fd1.png', 'uploads/employee_ids/emp_back_1762497872_690d9550353b6.png', 'pending', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'employee', '2025-11-07 06:44:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `alert_sent` (`alert_sent`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_no` (`plate_no`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_bus_id` (`assigned_bus_id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Indexes for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient` (`recipient`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `verification_token` (`verification_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_verified` (`is_verified`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_approved_by` (`approved_by_admin`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  ADD CONSTRAINT `fk_admin_alerts_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`assigned_bus_id`) REFERENCES `buses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  ADD CONSTRAINT `fk_driver_assignments_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_assignments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by_admin`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
