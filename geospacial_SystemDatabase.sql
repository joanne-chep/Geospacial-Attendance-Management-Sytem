-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 01, 2026 at 01:42 AM
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
-- Database: `geospacial_attendance_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('P','A','L') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attended_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`session_id`, `user_id`, `status`, `ip_address`, `attended_at`) VALUES
(1, 1, 'A', '::1', '2026-04-13 11:08:53'),
(2, 1, 'A', '::1', '2026-04-13 13:21:27'),
(4, 1, 'P', 'QR_SCAN', '2026-04-29 11:47:46'),
(5, 3, 'P', 'QR_SCAN', '2026-04-29 12:34:32'),
(7, 6, 'P', 'QR_SCAN', '2026-04-30 23:02:30');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `fi_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `course_name`, `fi_id`) VALUES
(1, 'CSS 313', 'Mod and Sim', 2),
(2, 'CSS 612', 'FDE', 4),
(3, 'ART413', 'Art and Design', 4);

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `course_id`, `student_id`) VALUES
(1, 1, 1),
(2, 2, 3),
(3, 3, 5),
(4, 3, 6);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `request_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `course_id`, `student_id`, `request_status`, `requested_at`) VALUES
(1, 1, 1, 'Approved', '2026-04-13 11:07:55'),
(2, 2, 3, 'Approved', '2026-04-29 12:22:29'),
(3, 3, 5, 'Approved', '2026-04-30 22:50:40'),
(4, 3, 6, 'Approved', '2026-04-30 22:53:34'),
(5, 3, 7, 'Rejected', '2026-04-30 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_title` varchar(100) NOT NULL,
  `attendance_code` varchar(10) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `course_id`, `session_title`, `attendance_code`, `session_date`, `start_time`, `end_time`) VALUES
(1, 1, 'Week 1 Lecture', 'D7GU', '2026-04-21', '03:07:00', '05:07:00'),
(2, 1, 'Lab1', 'CB9S', '2026-04-17', '18:20:00', '13:25:00'),
(3, 1, 'Lab2', 'Z8RL', '2026-05-01', '00:17:00', '00:28:00'),
(4, 1, 'Python Basics', 'EV2Y', '2026-05-07', '02:22:00', '10:28:00'),
(5, 2, 'Introduction', 'G1ZH', '2026-05-01', '17:18:00', '18:18:00'),
(6, 2, 'yyyy', 'HBN9', '2026-05-07', '13:08:00', '14:08:00'),
(7, 3, 'Week 1 lab', '2Z0K', '2026-05-07', '16:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('fi','student','other') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Joanne Chepkoech', 'joanne.chepkoech@ashesi.edu.gh', '$2y$10$hp5Xaf1IsqgDaw7KtukwEuv7TJMZB/mz3wpfVthUf9MCM32nMiqw2', 'student'),
(2, 'Kelly Gacuti', 'kelly@gmail.com', '$2y$10$u/OUZbJJoCyTVI9MPe8u7eIkN/c0G5WuQGjFzKrhmV/rQ6hg5/IX6', 'fi'),
(3, 'Rose Carlene', 'rose@gmail.com', '$2y$10$NsWJy9WSKptQ5KLrC2SEy.obempy9nQ/BTff/c6J4nixvvL5u8IL6', 'student'),
(4, 'Osarume Chiamaka', 'osarume@gmail.com', '$2y$10$oPSYajeTRCdqsRW86p13l.Sqephu7xvy41yEc/YNGn7pcblov0TV6', 'fi'),
(5, 'Marie', 'marie@gmail.com', '$2y$10$Bjd19zwqerfgjgYj/MAA4eeYCPMpa15CNeRSsW1PY6jY42A6Tri1W', 'student'),
(6, 'joyce', 'joyce@gmail.com', '$2y$10$DVE6Hn4SjOr3zuHJnzUzreGjLbZO5pnsx.Bs38IHV1vMJRhzmy9pi', 'student'),
(7, 'Gorety Atieno', 'atieno@gmail.com', '$2y$10$yx/.9LWNnnQWfndyjl8VF.6d9kUSvOcO/DmmjG2did52tBcZrAaU2', 'student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`session_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `fi_id` (`fi_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`course_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`course_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`fi_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
