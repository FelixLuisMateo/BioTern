-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2026 at 10:38 AM
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
-- Database: `biotern_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `internship_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `morning_time_in` time DEFAULT NULL,
  `morning_time_out` time DEFAULT NULL,
  `break_time_in` time DEFAULT NULL,
  `break_time_out` time DEFAULT NULL,
  `afternoon_time_in` time DEFAULT NULL,
  `afternoon_time_out` time DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `source` enum('biometric','manual','uploaded') DEFAULT 'manual',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `rejection_remarks` text DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendances`
--

INSERT INTO `attendances` (`id`, `student_id`, `internship_id`, `attendance_date`, `morning_time_in`, `morning_time_out`, `break_time_in`, `break_time_out`, `afternoon_time_in`, `afternoon_time_out`, `total_hours`, `source`, `status`, `approved_by`, `approved_at`, `remarks`, `rejection_remarks`, `rejected_by`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '2026-02-09', '17:03:00', '12:00:00', '12:30:00', '13:30:00', '14:00:00', '17:00:00', 140.00, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:33:20', '2026-02-10 09:03:49'),
(2, 2, NULL, '2026-02-09', '16:30:00', '12:15:00', '12:45:00', '13:45:00', '14:15:00', '17:15:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:33:20', '2026-02-09 09:18:56'),
(3, 3, NULL, '2026-02-09', '08:30:00', '12:30:00', '13:00:00', '14:00:00', '14:30:00', '16:27:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:33:20', '2026-02-09 08:27:36'),
(4, 1, NULL, '2026-02-08', '07:45:00', '11:45:00', '12:15:00', '13:15:00', '13:45:00', '16:45:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:33:20', '2026-02-09 06:33:20'),
(5, 2, NULL, '2026-02-08', '08:00:00', '12:00:00', '12:30:00', '13:30:00', '14:00:00', '17:00:00', NULL, 'manual', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:33:20', '2026-02-09 06:33:20'),
(6, 1, NULL, '2026-02-09', '17:03:00', '12:00:00', '12:30:00', '13:30:00', '14:00:00', '17:00:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 09:03:00'),
(7, 1, NULL, '2026-02-08', '08:15:00', '12:15:00', '12:45:00', '13:45:00', '14:15:00', '17:15:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(8, 1, NULL, '2026-02-07', '08:30:00', '12:30:00', '13:00:00', '14:00:00', '14:30:00', '17:30:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(9, 2, NULL, '2026-02-09', '16:30:00', '12:00:00', '12:30:00', '13:30:00', '14:00:00', '17:00:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 09:18:56'),
(10, 2, NULL, '2026-02-08', '07:45:00', '11:45:00', '12:15:00', '13:15:00', '13:45:00', '16:45:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(11, 2, NULL, '2026-02-07', '08:15:00', '12:15:00', '12:45:00', '13:45:00', '14:15:00', '17:15:00', NULL, 'manual', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(12, 3, NULL, '2026-02-09', '08:30:00', '12:30:00', '13:00:00', '14:00:00', '14:30:00', '16:27:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 08:27:36'),
(13, 3, NULL, '2026-02-08', '08:00:00', '12:00:00', '12:30:00', '13:30:00', '14:00:00', '17:00:00', NULL, 'manual', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(14, 3, NULL, '2026-02-07', '09:00:00', '12:30:00', '13:00:00', '14:00:00', '14:30:00', '17:30:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(15, 2, NULL, '2026-02-10', '09:57:00', '09:50:00', '09:50:00', NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-10 01:38:53', '2026-02-10 01:57:14'),
(16, 3, NULL, '2026-02-10', '09:40:00', '10:01:00', NULL, NULL, '17:52:00', '17:52:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-10 01:40:34', '2026-02-10 09:52:56'),
(17, 1, NULL, '2026-02-10', '09:57:00', '09:57:00', '09:58:00', '09:58:00', NULL, '09:59:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-10 01:57:41', '2026-02-10 01:59:23'),
(18, 2, NULL, '2026-02-11', NULL, NULL, NULL, NULL, '12:53:00', '15:30:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 04:53:11', '2026-02-11 07:30:36'),
(19, 1, NULL, '2026-02-11', NULL, NULL, NULL, NULL, '13:01:00', '13:02:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 05:01:14', '2026-02-11 05:02:53'),
(20, 3, NULL, '2026-02-11', NULL, NULL, NULL, NULL, '15:31:00', '16:43:00', NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 07:31:02', '2026-02-11 08:43:47'),
(21, 2, NULL, '2026-02-12', '11:00:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 03:00:31', '2026-02-12 03:00:31'),
(22, 5, NULL, '2026-02-12', NULL, NULL, NULL, NULL, '15:19:00', NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 07:19:20', '2026-02-12 07:19:20'),
(23, 5, NULL, '2026-02-13', '09:16:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 01:16:56', '2026-02-13 01:16:56'),
(24, 2, NULL, '2026-02-13', '09:18:00', '09:19:00', NULL, NULL, '09:19:00', NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 01:18:45', '2026-02-13 01:19:49'),
(25, 7, NULL, '2026-02-16', '13:45:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-16 05:45:00', '2026-02-16 05:45:00');

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_with_student_info`
-- (See below for the actual view)
--
CREATE TABLE `attendance_with_student_info` (
`id` bigint(20) unsigned
,`attendance_date` date
,`student_number` varchar(255)
,`first_name` varchar(255)
,`last_name` varchar(255)
,`email` varchar(255)
,`course_name` varchar(255)
,`internship_position` varchar(255)
,`morning_time_in` time
,`morning_time_out` time
,`break_time_in` time
,`break_time_out` time
,`afternoon_time_in` time
,`afternoon_time_out` time
,`status` enum('pending','approved','rejected')
,`approved_by_name` varchar(255)
,`approved_at` timestamp
,`remarks` text
);

-- --------------------------------------------------------

--
-- Table structure for table `biometric_data`
--

CREATE TABLE `biometric_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `biometric_type` enum('fingerprint','face','iris') NOT NULL,
  `template` longblob DEFAULT NULL,
  `registered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `certificate_type` varchar(255) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `certificate_number` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `office_location` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coordinators`
--

INSERT INTO `coordinators` (`id`, `user_id`, `first_name`, `last_name`, `middle_name`, `email`, `phone`, `department_id`, `office_location`, `bio`, `profile_picture`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(4, 15, 'Jomer', 'De Guzman', NULL, 'jomers@gmail.com', '92345678901', 1, 'Sa bahay niya', NULL, NULL, 1, '2026-02-12 07:29:31', '2026-02-12 07:29:31', NULL),
(5, 24, 'Prince', 'Basmayor', NULL, 'basmayor@gmail.com', '9134897513', 1, '12345 basmayor house', NULL, NULL, 1, '2026-02-16 05:38:46', '2026-02-16 05:38:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `internal_ojt_hours` int(11) NOT NULL DEFAULT 300,
  `external_ojt_hours` int(11) NOT NULL DEFAULT 300,
  `total_ojt_hours` int(11) NOT NULL DEFAULT 600,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `code`, `description`, `internal_ojt_hours`, `external_ojt_hours`, `total_ojt_hours`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Information Technology', 'IT-2024', 'Bachelor of Science in Information Technology', 300, 300, 600, 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(2, 'Computer Science', 'CS-2024', 'Bachelor of Science in Computer Science', 300, 300, 600, 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(3, 'Business Administration', 'BA-2024', 'Bachelor of Science in Business Administration', 250, 350, 600, 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `daily_time_records`
--

CREATE TABLE `daily_time_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `hours_worked` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remarks` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department_head` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `department_head`, `contact_email`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Information Technology', 'DEPT-IT', 'Department of Information Technology', 'Dr. Juan Santos', 'it@biotern.com', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(2, 'Business', 'DEPT-BUS', 'Department of Business Administration', 'Dr. Maria Cruz', 'business@biotern.com', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(3, 'Student Services', 'DEPT-SS', 'Department of Student Services', 'Ms. Rosa Garcia', 'services@biotern.com', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `last_modified_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `evaluator_name` varchar(255) DEFAULT NULL,
  `evaluation_date` date NOT NULL,
  `score` int(11) DEFAULT NULL,
  `feedback` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hour_logs`
--

CREATE TABLE `hour_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `date` date NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internships`
--

CREATE TABLE `internships` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('internal','external') NOT NULL DEFAULT 'external',
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `ojt_description` text DEFAULT NULL,
  `status` enum('pending','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `school_year` varchar(50) NOT NULL,
  `required_hours` int(11) NOT NULL DEFAULT 600,
  `rendered_hours` int(11) NOT NULL DEFAULT 0,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `internships`
--

INSERT INTO `internships` (`id`, `student_id`, `course_id`, `department_id`, `coordinator_id`, `supervisor_id`, `type`, `company_name`, `company_address`, `position`, `start_date`, `end_date`, `ojt_description`, `status`, `school_year`, `required_hours`, `rendered_hours`, `completion_percentage`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 2, 3, 'external', 'Tech Solutions Inc', '100 Business Park, Metro', 'Junior Developer', '2026-01-15', '2026-06-15', 'Web Development Internship', 'ongoing', '2026-2027', 600, 0, 0.00, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(2, 2, 1, 1, 2, 3, 'external', 'Digital Agency Co', '200 Innovation Ave, Metro', 'UI/UX Designer', '2026-01-20', '2026-06-20', 'Design Internship', 'ongoing', '2026-2027', 600, 0, 0.00, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL),
(3, 3, 2, 1, 2, 3, 'internal', 'BioTern Labs', '500 Campus Blvd, City', 'Data Analyst', '2026-02-01', '2026-07-01', 'Data Analysis Internship', 'ongoing', '2026-2027', 600, 0, 0.00, '2026-02-09 06:36:38', '2026-02-09 06:36:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `manual_dtr_attachments`
--

CREATE TABLE `manual_dtr_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'e.g., Biometric Machine Breakdown',
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `from_user_id` bigint(20) UNSIGNED NOT NULL,
  `to_user_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` longtext NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_attendance_records`
-- (See below for the actual view)
--
CREATE TABLE `pending_attendance_records` (
`id` bigint(20) unsigned
,`attendance_date` date
,`student_id` varchar(255)
,`student_name` varchar(511)
,`course` varchar(255)
,`internship_company` varchar(255)
,`morning_time_in` time
,`break_time_in` time
,`afternoon_time_in` time
,`status` enum('pending','approved','rejected')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `description` longtext DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `section_id` int(11) NOT NULL,
  `supervisor_name` text DEFAULT NULL,
  `coordinator_name` text DEFAULT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coordinator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `total_hours_remaining` int(11) DEFAULT NULL,
  `total_hours` int(11) DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `biometric_registered` tinyint(1) NOT NULL DEFAULT 0,
  `biometric_registered_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `course_id`, `student_id`, `first_name`, `last_name`, `middle_name`, `username`, `password`, `email`, `section_id`, `supervisor_name`, `coordinator_name`, `supervisor_id`, `coordinator_id`, `phone`, `date_of_birth`, `gender`, `address`, `total_hours_remaining`, `total_hours`, `emergency_contact`, `profile_picture`, `biometric_registered`, `biometric_registered_at`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, 1, '05-0001', 'John', 'Doe', 'Michael', NULL, '', 'john@student.com', 0, 'Jomer De Guzman', 'Felix Luis Mateo', NULL, NULL, '09123456789', '2003-01-15', 'male', '123 Main St, City', NULL, 140, 'Maria Doe', NULL, 1, '2026-02-01 07:49:45', 'Ongoing', '2026-02-09 06:36:38', '2026-02-11 06:30:13', NULL),
(2, 5, 3, '05-0002', 'Jane', 'Smith', 'Marie', NULL, '', 'jane@student.com', 0, 'Naven Cuenca', 'Ivy Bactazo', NULL, NULL, '092345678901', '2003-06-20', 'female', '456 Oak Ave, City', NULL, 250, 'Robert Smith', NULL, 1, '2026-02-03 07:49:58', '0', '2026-02-09 06:36:38', '2026-02-14 03:02:37', NULL),
(3, 6, 2, '05-0003', 'Mike', 'Johnson', 'Anthony', NULL, '', 'mike@student.com', 0, 'Naven Cuenca', 'Ivy Bactazo', NULL, NULL, '09345678901', '2003-03-10', 'male', '789 Pine Rd, City', NULL, 100, 'Sarah Johnson', NULL, 1, '2026-02-05 07:50:04', 'Ongoing', '2026-02-09 06:36:38', '2026-02-11 06:30:25', NULL),
(5, 12, 1, '05-8502', 'Felix Luis', 'Mateo', 'Manaloto', 'felixluismateo16', '$2y$10$bK.ueb82uspJ1esWCiWZlOrlMwB79bEB3FfkzOeYqrXFuw4sWprJ2', 'felixluismanalotomateo16@gmail.com', 0, 'Jomer De Guzman', 'Ivy Bactazo', NULL, NULL, '(+63) 991 633 2193', '2006-03-16', 'male', '6000 Dau, Mabalacat Cuty, Pampanga', NULL, NULL, '09091783340', 'uploads/profile_pictures/student_5_1771039576.png', 0, NULL, '1', '2026-02-12 06:49:03', '2026-02-14 03:42:07', NULL),
(6, 23, 3, '05-1234', 'Lucky', 'Mateo', '', 'lucky', '$2y$10$YDK.6KIiuSgJxNWBYP9gNe.YXQvS9iwLIlJYPlxu9yQCrcnGKAjHq', 'luckymateo@gmail.com', 0, NULL, NULL, NULL, NULL, '09213410354', '2006-06-15', 'male', '6170 Dau, Mabalacat City, Pampanga', NULL, 200, 'Car', NULL, 0, NULL, '1', '2026-02-16 05:34:41', '2026-02-16 05:34:41', NULL),
(7, 25, 3, '05-1233', 'Brian', 'Leonardo', '', 'brian', '$2y$10$2ydLo1skdlQYf90dr3yg0OEsP31LITpr2D8hSGWuv/jl9L1iyrJBG', 'brian@gmail.com', 0, 'Lucky Manaloto', 'Prince Basmayor', 4, 5, '055985451', '2004-07-09', 'male', '2356 Leonardo street', NULL, 200, 'Kuku', '', 0, NULL, '1', '2026-02-16 05:44:36', '2026-02-16 06:42:14', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `student_attendance_summary` (
`id` bigint(20) unsigned
,`student_id` varchar(255)
,`student_name` varchar(511)
,`total_attendance_records` bigint(21)
,`approved_count` decimal(22,0)
,`pending_count` decimal(22,0)
,`rejected_count` decimal(22,0)
,`approval_percentage` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_profile_with_internship`
-- (See below for the actual view)
--
CREATE TABLE `student_profile_with_internship` (
);

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supervisors`
--

INSERT INTO `supervisors` (`id`, `user_id`, `first_name`, `last_name`, `middle_name`, `email`, `phone`, `department_id`, `specialization`, `bio`, `profile_picture`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(4, 16, 'Lucky', 'Manaloto', NULL, 'luckyluckymateo@gmail.com', '092345678901', NULL, 'Everywhere', NULL, NULL, 1, '2026-02-12 07:33:13', '2026-02-12 07:33:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upload_settings`
--

CREATE TABLE `upload_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `upload_type` varchar(50) NOT NULL COMMENT 'profile_picture, manual_dtr, documents',
  `base_path` varchar(255) NOT NULL COMMENT 'Path relative to web root',
  `max_file_size` bigint(20) DEFAULT 5242880 COMMENT 'In bytes (5MB default)',
  `allowed_extensions` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `upload_settings`
--

INSERT INTO `upload_settings` (`id`, `upload_type`, `base_path`, `max_file_size`, `allowed_extensions`, `created_at`, `updated_at`) VALUES
(1, 'profile_picture', '/uploads/profile_pictures/', 5242880, 'jpg,jpeg,png,gif', '2026-02-14 03:09:01', '2026-02-14 03:09:01'),
(2, 'manual_dtr', '/uploads/manual_dtr/', 10485760, 'jpg,jpeg,png,pdf', '2026-02-14 03:09:01', '2026-02-14 03:09:01'),
(3, 'documents', '/uploads/documents/', 20971520, 'pdf,doc,docx,xls,xlsx,txt', '2026-02-14 03:09:01', '2026-02-14 03:09:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `role` enum('admin','coordinator','supervisor','student') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `remember_token`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', '', 'admin@biotern.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(2, 'Coordinator', '', 'coordinator@biotern.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'coordinator', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(3, 'Supervisor', '', 'supervisor@biotern.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'supervisor', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(4, 'John Doe Student', '', 'john@student.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'student', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(5, 'Jane Smith Student', '', 'jane@student.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'student', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(6, 'Mike Johnson Student', '', 'mike@student.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'student', 1, '2026-02-09 06:36:38', '2026-02-09 06:36:38'),
(7, 'felixluismateo16', 'felixluismateo16', 'mateo.felixluis@gmail.com', NULL, '$2y$10$E1F.HN7rEo3hMOV3W0.TlecWiC25G6TA7fJpvKjQpqlAWHNgAkuiu', NULL, 'student', 1, '2026-02-12 06:28:58', '2026-02-12 06:28:58'),
(9, 'felixluismateo16', 'felixluismateo16', 'mateos.felixluis@gmail.com', NULL, '$2y$10$nQBdeCo9Ov19ZyFq3DM8puWT9wTEbj7IQoyuy6epzo7MPYT2NZWUO', NULL, 'student', 1, '2026-02-12 06:29:10', '2026-02-12 06:29:10'),
(12, 'Felix Mateo', 'felixluismateo16', 'felixluismanalotomateo16@gmail.com', NULL, '$2y$10$bK.ueb82uspJ1esWCiWZlOrlMwB79bEB3FfkzOeYqrXFuw4sWprJ2', NULL, 'student', 1, '2026-02-12 06:49:03', '2026-02-12 06:49:03'),
(13, 'ToxicDaw', 'ToxicDaw', 'jomer@gmail.com', NULL, '$2y$10$l4a1M9972YjUDgnK21QqNemcdJVI9COcf50Tfj1tnoZz2/swYhs2O', NULL, 'coordinator', 1, '2026-02-12 07:21:26', '2026-02-12 07:21:26'),
(15, 'ToxicDaw', 'ToxicDaw', 'jomers@gmail.com', NULL, '$2y$10$8B/tX8rPSRlr8IInFaMOgOnCGw83JNsTxdScB7LcZA5c45bn0inMi', NULL, 'coordinator', 1, '2026-02-12 07:29:31', '2026-02-12 07:29:31'),
(16, 'Lucky', 'Lucky', 'luckyluckymateo@gmail.com', NULL, '$2y$10$g8QlU.4rdfGnfcp/v.itgum51fEs4JpPAFCbohBLS//KvOTR2N/hS', NULL, 'supervisor', 1, '2026-02-12 07:33:13', '2026-02-12 07:33:13'),
(17, 'Ivan Sanchez', 'Ivan', 'IvanSanchez@gmail.com', NULL, '$2y$10$oHgjisJkgBJc0P.MCSvkgOP/7YGWSckBVor4dDpProiaN3L8y5MCS', NULL, 'student', 1, '2026-02-16 04:57:43', '2026-02-16 04:57:43'),
(23, 'Lucky Mateo', 'lucky', 'luckymateo@gmail.com', NULL, '$2y$10$YDK.6KIiuSgJxNWBYP9gNe.YXQvS9iwLIlJYPlxu9yQCrcnGKAjHq', NULL, 'student', 1, '2026-02-16 05:34:41', '2026-02-16 05:34:41'),
(24, 'Prince', 'Prince', 'basmayor@gmail.com', NULL, '$2y$10$IpoVpycSK9xjb4xafvR66uIPJu3r6x99wUe1rV6asOlYwZ9g06bXm', NULL, 'coordinator', 1, '2026-02-16 05:38:46', '2026-02-16 05:38:46'),
(25, 'Brian Leonardo', 'brian', 'brian@gmail.com', NULL, '$2y$10$2ydLo1skdlQYf90dr3yg0OEsP31LITpr2D8hSGWuv/jl9L1iyrJBG', NULL, 'student', 1, '2026-02-16 05:44:36', '2026-02-16 05:44:36');

-- --------------------------------------------------------

--
-- Structure for view `attendance_with_student_info`
--
DROP TABLE IF EXISTS `attendance_with_student_info`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_with_student_info`  AS SELECT `a`.`id` AS `id`, `a`.`attendance_date` AS `attendance_date`, `s`.`student_id` AS `student_number`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `s`.`email` AS `email`, `c`.`name` AS `course_name`, `i`.`position` AS `internship_position`, `a`.`morning_time_in` AS `morning_time_in`, `a`.`morning_time_out` AS `morning_time_out`, `a`.`break_time_in` AS `break_time_in`, `a`.`break_time_out` AS `break_time_out`, `a`.`afternoon_time_in` AS `afternoon_time_in`, `a`.`afternoon_time_out` AS `afternoon_time_out`, `a`.`status` AS `status`, `u`.`name` AS `approved_by_name`, `a`.`approved_at` AS `approved_at`, `a`.`remarks` AS `remarks` FROM ((((`attendances` `a` left join `students` `s` on(`a`.`student_id` = `s`.`id`)) left join `courses` `c` on(`s`.`course_id` = `c`.`id`)) left join `internships` `i` on(`s`.`id` = `i`.`student_id` and `i`.`status` in ('ongoing','completed'))) left join `users` `u` on(`a`.`approved_by` = `u`.`id`)) ORDER BY `a`.`attendance_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `pending_attendance_records`
--
DROP TABLE IF EXISTS `pending_attendance_records`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_attendance_records`  AS SELECT `a`.`id` AS `id`, `a`.`attendance_date` AS `attendance_date`, `s`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, `c`.`name` AS `course`, `i`.`company_name` AS `internship_company`, `a`.`morning_time_in` AS `morning_time_in`, `a`.`break_time_in` AS `break_time_in`, `a`.`afternoon_time_in` AS `afternoon_time_in`, `a`.`status` AS `status`, `a`.`created_at` AS `created_at` FROM (((`attendances` `a` join `students` `s` on(`a`.`student_id` = `s`.`id`)) left join `courses` `c` on(`s`.`course_id` = `c`.`id`)) left join `internships` `i` on(`s`.`id` = `i`.`student_id`)) WHERE `a`.`status` = 'pending' ORDER BY `a`.`attendance_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `student_attendance_summary`
--
DROP TABLE IF EXISTS `student_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_attendance_summary`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, count(0) AS `total_attendance_records`, sum(case when `a`.`status` = 'approved' then 1 else 0 end) AS `approved_count`, sum(case when `a`.`status` = 'pending' then 1 else 0 end) AS `pending_count`, sum(case when `a`.`status` = 'rejected' then 1 else 0 end) AS `rejected_count`, round(sum(case when `a`.`status` = 'approved' then 1 else 0 end) / count(0) * 100,2) AS `approval_percentage` FROM (`attendances` `a` join `students` `s` on(`a`.`student_id` = `s`.`id`)) GROUP BY `s`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `student_profile_with_internship`
--
DROP TABLE IF EXISTS `student_profile_with_internship`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_profile_with_internship`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',coalesce(`s`.`middle_name`,''),' ',`s`.`last_name`) AS `full_name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`date_of_birth` AS `date_of_birth`, `s`.`gender` AS `gender`, `s`.`address` AS `address`, `s`.`emergency_contact` AS `emergency_contact`, `s`.`is_active` AS `is_active`, `c`.`name` AS `course`, `c`.`code` AS `course_code`, `u`.`name` AS `user_name`, `u`.`role` AS `user_role`, `i`.`id` AS `internship_id`, `i`.`type` AS `internship_type`, `i`.`company_name` AS `company_name`, `i`.`position` AS `position`, `i`.`start_date` AS `start_date`, `i`.`end_date` AS `end_date`, `i`.`status` AS `internship_status`, `i`.`required_hours` AS `required_hours`, `i`.`rendered_hours` AS `rendered_hours`, `i`.`completion_percentage` AS `completion_percentage`, `coord`.`name` AS `coordinator_name`, `sup`.`name` AS `supervisor_name` FROM (((((`students` `s` left join `courses` `c` on(`s`.`course_id` = `c`.`id`)) left join `users` `u` on(`s`.`user_id` = `u`.`id`)) left join `internships` `i` on(`s`.`id` = `i`.`student_id`)) left join `users` `coord` on(`i`.`coordinator_id` = `coord`.`id`)) left join `users` `sup` on(`i`.`supervisor_id` = `sup`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_internship_attendance` (`internship_id`,`attendance_date`),
  ADD KEY `idx_student_attendance` (`student_id`,`attendance_date`);

--
-- Indexes for table `biometric_data`
--
ALTER TABLE `biometric_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `biometric_data_student_id_foreign` (`student_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `certificates_student_id_foreign` (`student_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `daily_time_records_student_id_foreign` (`student_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documents_student_id_foreign` (`student_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluations_student_id_foreign` (`student_id`);

--
-- Indexes for table `hour_logs`
--
ALTER TABLE `hour_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hour_logs_student_id_foreign` (`student_id`);

--
-- Indexes for table `internships`
--
ALTER TABLE `internships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_coordinator_id` (`coordinator_id`),
  ADD KEY `idx_supervisor_id` (`supervisor_id`);

--
-- Indexes for table `manual_dtr_attachments`
--
ALTER TABLE `manual_dtr_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_attendance_date` (`attendance_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `messages_from_user_id_foreign` (`from_user_id`),
  ADD KEY `messages_to_user_id_foreign` (`to_user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_foreign` (`user_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `sections_code_unique` (`code`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`),
  ADD UNIQUE KEY `system_settings_key_unique` (`key`);

--
-- Indexes for table `upload_settings`
--
ALTER TABLE `upload_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_upload_type` (`upload_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `biometric_data`
--
ALTER TABLE `biometric_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hour_logs`
--
ALTER TABLE `hour_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internships`
--
ALTER TABLE `internships`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `manual_dtr_attachments`
--
ALTER TABLE `manual_dtr_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upload_settings`
--
ALTER TABLE `upload_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendances_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendances_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `biometric_data`
--
ALTER TABLE `biometric_data`
  ADD CONSTRAINT `biometric_data_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD CONSTRAINT `coordinators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coordinators_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  ADD CONSTRAINT `daily_time_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hour_logs`
--
ALTER TABLE `hour_logs`
  ADD CONSTRAINT `hour_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internships`
--
ALTER TABLE `internships`
  ADD CONSTRAINT `internships_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internships_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `internships_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `internships_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `internships_ibfk_5` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `manual_dtr_attachments`
--
ALTER TABLE `manual_dtr_attachments`
  ADD CONSTRAINT `manual_dtr_attachments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_dtr_attachments_ibfk_2` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD CONSTRAINT `supervisors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supervisors_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
